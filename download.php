<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\AuditLogger;
use App\Services\CloudStorageService;

$fileName = (string) ($_GET['file'] ?? '');
$storage = new CloudStorageService(app_config('storage'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));

if ($fileName === '') {
    $auditLogger->log('download', 'failed', ['reason' => 'missing_file_parameter']);
    http_response_code(400);
    echo 'Parameter file tidak valid.';
    exit;
}

$fileInfo = $storage->resolveFile($fileName);
if ($fileInfo === null) {
    $auditLogger->log('download', 'failed', ['file_name' => $fileName, 'reason' => 'not_found_or_invalid']);
    http_response_code(404);
    echo 'File tidak ditemukan.';
    exit;
}

$downloadName = (string) $fileInfo['name'];
$encodedName = rawurlencode($downloadName);

header('Content-Description: File Transfer');
header('Content-Type: ' . (string) $fileInfo['mime']);
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Length: ' . (string) $fileInfo['size']);
header('Cache-Control: private, no-transform, no-store');
header('Pragma: public');
header('Expires: 0');

$auditLogger->log('download', 'success', [
    'file_name' => $downloadName,
    'size' => $fileInfo['size'],
    'mime' => $fileInfo['mime'],
]);

readfile((string) $fileInfo['path']);
exit;
