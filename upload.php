<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Support\Flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint upload hanya menerima metode POST.');
    header('Location: index.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Silakan refresh halaman lalu coba upload kembali.');
    header('Location: index.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$uploadResult = $storage->upload($_FILES['fileToUpload'] ?? []);

if ($uploadResult['success'] === true) {
    Flash::add('success', 'Upload berhasil', $uploadResult['message']);
    $auditLogger->log('upload', 'success', [
        'file_name' => $uploadResult['file_name'] ?? null,
        'size' => $uploadResult['size'] ?? null,
        'mime' => $uploadResult['mime'] ?? null,
    ]);
    header('Location: index.php');
    exit;
}

Flash::add('error', 'Upload gagal', $uploadResult['message']);
$auditLogger->log('upload', 'failed', [
    'reason' => $uploadResult['message'],
]);
header('Location: index.php');
exit;
