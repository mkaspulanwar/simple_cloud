<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Security\AuthManager;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Services\TrashMetadataStore;
use App\Support\Flash;

AuthManager::requirePermission('delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint delete hanya menerima metode POST.');
    header('Location: dashboard.php?view=library');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan delete dibatalkan demi keamanan.');
    header('Location: dashboard.php?view=library');
    exit;
}

$fileName = (string) ($_POST['file'] ?? '');
if ($fileName === '') {
    Flash::add('error', 'Nama file tidak valid', 'Pilih file yang ingin dihapus dari tabel file.');
    header('Location: dashboard.php?view=library');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$trashMetadata = new TrashMetadataStore((string) app_config('storage.trash_metadata_path'));
$currentUser = AuthManager::user();

if ($currentUser === null || $storage->resolveFile($fileName) === null || !$ownershipStore->canAccess($fileName, $currentUser)) {
    Flash::add('error', 'Akses file ditolak', 'Anda tidak memiliki izin untuk menghapus file ini.');
    $auditLogger->log('delete', 'failed', [
        'file_name' => $fileName,
        'reason' => 'forbidden',
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php?view=library');
    exit;
}

$trashResult = $storage->moveToTrash($fileName);

if ($trashResult !== null) {
    $trashMetadata->remember(
        (string) $trashResult['trash_name'],
        (string) $trashResult['original_name'],
        [
            'id' => (string) ($ownershipStore->ownerFor($fileName) ?? ($currentUser['id'] ?? '')),
            'name' => (string) ($currentUser['name'] ?? ''),
            'role' => (string) ($currentUser['role'] ?? 'user'),
        ],
        $currentUser
    );
    $ownershipStore->remove($fileName);
    $backupService = new BackupService(
        app_config('backup'),
        app_config('storage'),
        app_config('database')
    );
    $trashBackup = $backupService->backupTrashAfterDelete();
    $backupMessage = $trashBackup['success'] === true
        ? ' Folder trash juga sudah dibackup.'
        : ' File sudah masuk trash, tetapi backup trash gagal: ' . (string) ($trashBackup['error'] ?? $trashBackup['message']);

    Flash::add('success', 'Delete berhasil', 'File dipindahkan ke folder trash.' . $backupMessage);
    $auditLogger->log('delete', 'success', [
        'file_name' => $fileName,
        'trash_name' => $trashResult['trash_name'],
        'trash_backup' => $trashBackup,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php?view=library');
    exit;
}

Flash::add('error', 'Delete gagal', 'File tidak ditemukan atau tidak bisa dihapus.');
$auditLogger->log('delete', 'failed', [
    'file_name' => $fileName,
    'user_id' => AuthManager::userId(),
]);
header('Location: dashboard.php?view=library');
exit;
