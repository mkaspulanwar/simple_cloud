<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;

$fileName = (string) ($_GET['file'] ?? '');
$inline = (string) ($_GET['inline'] ?? '') === '1';
$public = (string) ($_GET['public'] ?? '') === '1';
$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));

if (!$public) {
    AuthManager::requirePermission('download');
}

if ($fileName === '') {
    $auditLogger->log('download', 'failed', ['reason' => 'missing_file_parameter']);
    http_response_code(400);
    echo 'Parameter file tidak valid.';
    exit;
}

$fileInfo = $storage->resolveFile($fileName);
$currentUser = AuthManager::user();
if ($fileInfo === null || (!$public && ($currentUser === null || !$ownershipStore->canAccess($fileName, $currentUser)))) {
    $auditLogger->log('download', 'failed', [
        'file_name' => $fileName,
        'reason' => $fileInfo === null ? 'not_found_or_invalid' : 'forbidden',
        'user_id' => AuthManager::userId(),
        'public' => $public,
    ]);
    http_response_code(404);
    echo 'File tidak ditemukan.';
    exit;
}

$downloadName = (string) $fileInfo['name'];
$encodedName = rawurlencode($downloadName);

header('Content-Description: File Transfer');
header('Content-Type: ' . (string) $fileInfo['mime']);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Length: ' . (string) $fileInfo['size']);
header('Cache-Control: private, no-transform, no-store');
header('Pragma: public');
header('Expires: 0');

if (!$inline) {
    $auditLogger->log($public ? 'public_download' : 'download', 'success', [
        'file_name' => $downloadName,
        'user_id' => AuthManager::userId(),
        'public' => $public,
        'size' => $fileInfo['size'],
        'mime' => $fileInfo['mime'],
    ]);
}

readfile((string) $fileInfo['path']);
exit;
