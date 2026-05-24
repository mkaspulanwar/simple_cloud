<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Security\AuthManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Support\Flash;

AuthManager::requirePermission('delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint delete hanya menerima metode POST.');
    header('Location: dashboard.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan delete dibatalkan demi keamanan.');
    header('Location: dashboard.php');
    exit;
}

$fileName = (string) ($_POST['file'] ?? '');
if ($fileName === '') {
    Flash::add('error', 'Nama file tidak valid', 'Pilih file yang ingin dihapus dari tabel file.');
    header('Location: dashboard.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$currentUser = AuthManager::user();

if ($currentUser === null || $storage->resolveFile($fileName) === null || !$ownershipStore->canAccess($fileName, $currentUser)) {
    Flash::add('error', 'Akses file ditolak', 'Anda tidak memiliki izin untuk menghapus file ini.');
    $auditLogger->log('delete', 'failed', [
        'file_name' => $fileName,
        'reason' => 'forbidden',
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php');
    exit;
}

if ($storage->delete($fileName)) {
    $ownershipStore->remove($fileName);
    Flash::add('success', 'Delete berhasil', 'File berhasil dihapus dari storage server.');
    $auditLogger->log('delete', 'success', [
        'file_name' => $fileName,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php');
    exit;
}

Flash::add('error', 'Delete gagal', 'File tidak ditemukan atau tidak bisa dihapus.');
$auditLogger->log('delete', 'failed', [
    'file_name' => $fileName,
    'user_id' => AuthManager::userId(),
]);
header('Location: dashboard.php');
exit;
