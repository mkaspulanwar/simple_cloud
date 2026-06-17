<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Security\AuthManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Support\Flash;

/**
 * @return array<int, array<string, mixed>>
 */
function uploaded_files_from_request(array $fileInput): array
{
    if (!isset($fileInput['name'])) {
        return [];
    }

    if (!is_array($fileInput['name'])) {
        return [$fileInput];
    }

    $files = [];
    $count = count($fileInput['name']);

    for ($index = 0; $index < $count; $index++) {
        $files[] = [
            'name' => $fileInput['name'][$index] ?? '',
            'type' => $fileInput['type'][$index] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
            'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$index] ?? 0,
        ];
    }

    return $files;
}

AuthManager::requirePermission('upload');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint upload hanya menerima metode POST.');
    header('Location: dashboard.php?view=library');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Silakan refresh halaman lalu coba upload kembali.');
    header('Location: dashboard.php?view=library');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$currentUserId = AuthManager::userId();
$uploadedFiles = uploaded_files_from_request($_FILES['fileToUpload'] ?? []);

if ($uploadedFiles === []) {
    Flash::add('error', 'Upload gagal', 'Tidak ada gambar yang dipilih.');
    header('Location: dashboard.php?view=library');
    exit;
}

$successCount = 0;
$failedCount = 0;
$uploadedNames = [];
$failedMessages = [];

foreach ($uploadedFiles as $uploadedFile) {
    $uploadResult = $storage->upload($uploadedFile);

    if ($uploadResult['success'] !== true) {
        $failedCount++;
        $failedName = (string) ($uploadedFile['name'] ?? 'Gambar');
        $failedMessages[] = $failedName . ': ' . $uploadResult['message'];
        $auditLogger->log('upload', 'failed', [
            'file_name' => $failedName,
            'reason' => $uploadResult['message'],
            'user_id' => $currentUserId,
        ]);
        continue;
    }

    $successCount++;
    $uploadedFileName = (string) ($uploadResult['file_name'] ?? '');
    $uploadedNames[] = $uploadedFileName;

    if ($uploadedFileName !== '' && $currentUserId !== null) {
        $ownershipStore->setOwner(
            $uploadedFileName,
            $currentUserId,
            isset($uploadResult['size']) ? (int) $uploadResult['size'] : null,
            isset($uploadResult['mime']) ? (string) $uploadResult['mime'] : null
        );
    }

    $auditLogger->log('upload', 'success', [
        'file_name' => $uploadedFileName,
        'user_id' => $currentUserId,
        'size' => $uploadResult['size'] ?? null,
        'mime' => $uploadResult['mime'] ?? null,
    ]);
}

if ($successCount > 0 && $failedCount === 0) {
    Flash::add('success', 'Upload berhasil', $successCount . ' gambar berhasil diunggah.');
} elseif ($successCount > 0) {
    Flash::add(
        'warning',
        'Upload sebagian berhasil',
        $successCount . ' gambar berhasil, ' . $failedCount . ' gagal. ' . implode(' | ', array_slice($failedMessages, 0, 3))
    );
} else {
    Flash::add('error', 'Upload gagal', implode(' | ', array_slice($failedMessages, 0, 3)));
}

header('Location: dashboard.php?view=library');
exit;
