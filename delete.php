<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Support\Flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint delete hanya menerima metode POST.');
    header('Location: index.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan delete dibatalkan demi keamanan.');
    header('Location: index.php');
    exit;
}

$fileName = (string) ($_POST['file'] ?? '');
if ($fileName === '') {
    Flash::add('error', 'Nama file tidak valid', 'Pilih file yang ingin dihapus dari tabel file.');
    header('Location: index.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));

if ($storage->delete($fileName)) {
    Flash::add('success', 'Delete berhasil', 'File berhasil dihapus dari storage server.');
    $auditLogger->log('delete', 'success', ['file_name' => $fileName]);
    header('Location: index.php');
    exit;
}

Flash::add('error', 'Delete gagal', 'File tidak ditemukan atau tidak bisa dihapus.');
$auditLogger->log('delete', 'failed', ['file_name' => $fileName]);
header('Location: index.php');
exit;
