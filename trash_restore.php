<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Services\TrashMetadataStore;
use App\Support\Flash;

AuthManager::requirePermission('manage_trash');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint restore trash hanya menerima metode POST.');
    header('Location: trash.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan restore dibatalkan demi keamanan.');
    header('Location: trash.php');
    exit;
}

$trashName = (string) ($_POST['file'] ?? '');
if ($trashName === '') {
    Flash::add('error', 'Nama file tidak valid', 'Pilih file trash yang ingin dikembalikan.');
    header('Location: trash.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$trashMetadata = new TrashMetadataStore((string) app_config('storage.trash_metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$currentUser = AuthManager::user();
$trashRecord = $trashMetadata->recordFor($trashName);

if ($currentUser === null || !$trashMetadata->canAccess($trashName, $currentUser)) {
    Flash::add('error', 'Akses file ditolak', 'Anda tidak memiliki izin untuk mengembalikan file ini.');
    $auditLogger->log('trash_restore', 'failed', [
        'file_name' => $trashName,
        'reason' => 'forbidden',
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: trash.php');
    exit;
}

$preferredName = (string) ($trashRecord['original_name'] ?? $trashName);
$restoreResult = $storage->restoreFromTrash($trashName, $preferredName);

if ($restoreResult !== null) {
    $ownerId = (string) ($trashRecord['owner_id'] ?? $currentUser['id']);
    $ownershipStore->setOwner(
        (string) $restoreResult['restored_name'],
        $ownerId,
        (int) $restoreResult['size'],
        (string) $restoreResult['mime']
    );
    $trashMetadata->remove($trashName);

    $backupService = new BackupService(
        app_config('backup'),
        app_config('storage'),
        app_config('database')
    );
    $trashBackup = $backupService->backupTrashAfterDelete();

    Flash::add('success', 'File dikembalikan', (string) $restoreResult['restored_name'] . ' sudah kembali ke katalog.');
    $auditLogger->log('trash_restore', 'success', [
        'trash_name' => $trashName,
        'restored_name' => $restoreResult['restored_name'],
        'trash_backup' => $trashBackup,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: trash.php');
    exit;
}

Flash::add('error', 'Restore gagal', 'File tidak ditemukan di trash atau tidak bisa dikembalikan.');
$auditLogger->log('trash_restore', 'failed', [
    'file_name' => $trashName,
    'user_id' => AuthManager::userId(),
]);
header('Location: trash.php');
exit;
