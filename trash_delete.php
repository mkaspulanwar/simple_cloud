<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\CloudStorageService;
use App\Services\TrashMetadataStore;
use App\Support\Flash;

AuthManager::requirePermission('manage_trash');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint trash hanya menerima metode POST.');
    header('Location: trash.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan hapus permanen dibatalkan demi keamanan.');
    header('Location: trash.php');
    exit;
}

$fileName = (string) ($_POST['file'] ?? '');
if ($fileName === '') {
    Flash::add('error', 'Nama file tidak valid', 'Pilih file trash yang ingin dihapus permanen.');
    header('Location: trash.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$trashMetadata = new TrashMetadataStore((string) app_config('storage.trash_metadata_path'));
$currentUser = AuthManager::user();

if ($currentUser === null || !$trashMetadata->canAccess($fileName, $currentUser)) {
    Flash::add('error', 'Akses file ditolak', 'Anda tidak memiliki izin untuk menghapus permanen file ini.');
    $auditLogger->log('trash_delete', 'failed', [
        'file_name' => $fileName,
        'reason' => 'forbidden',
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: trash.php');
    exit;
}

if ($storage->deleteFromTrash($fileName)) {
    $trashMetadata->remove($fileName);
    $backupService = new BackupService(
        app_config('backup'),
        app_config('storage'),
        app_config('database')
    );
    $trashBackup = $backupService->backupTrashAfterDelete();
    $backupMessage = $trashBackup['success'] === true
        ? ' Backup trash juga sudah diperbarui.'
        : ' File terhapus permanen, tetapi backup trash gagal: ' . (string) ($trashBackup['error'] ?? $trashBackup['message']);

    Flash::add('success', 'File trash dihapus permanen', $fileName . ' sudah dihapus dari folder trash.' . $backupMessage);
    $auditLogger->log('trash_delete', 'success', [
        'file_name' => $fileName,
        'trash_backup' => $trashBackup,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: trash.php');
    exit;
}

Flash::add('error', 'Hapus permanen gagal', 'File tidak ditemukan di folder trash atau tidak bisa dihapus.');
$auditLogger->log('trash_delete', 'failed', [
    'file_name' => $fileName,
    'user_id' => AuthManager::userId(),
]);
header('Location: trash.php');
exit;
