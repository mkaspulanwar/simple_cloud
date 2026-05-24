<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\CloudStorageService;

$fileName = (string) ($_GET['file'] ?? '');
$storage = new CloudStorageService(app_config('storage'));
$fileInfo = $storage->resolveFile($fileName);

if ($fileInfo === null) {
    http_response_code(404);
    echo 'Preview tidak ditemukan.';
    exit;
}

$mime = (string) $fileInfo['mime'];
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
    http_response_code(403);
    echo 'Preview hanya tersedia untuk gambar.';
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) $fileInfo['size']);
header('Cache-Control: public, max-age=300');

readfile((string) $fileInfo['path']);
exit;
