<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Security\AuthManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Services\FileOwnershipStore;
use App\Support\Flash;

AuthManager::requirePermission('rename');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint rename hanya menerima metode POST.');
    header('Location: dashboard.php?view=library');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan rename dibatalkan demi keamanan.');
    header('Location: dashboard.php?view=library');
    exit;
}

$currentName = (string) ($_POST['current_file'] ?? '');
$newName = (string) ($_POST['new_file'] ?? '');

if ($currentName === '' || $newName === '') {
    Flash::add('error', 'Data rename tidak lengkap', 'Nama file asal dan nama file baru wajib diisi.');
    header('Location: dashboard.php?view=library');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$ownershipStore = new FileOwnershipStore((string) app_config('storage.metadata_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$currentUser = AuthManager::user();

if ($currentUser === null || $storage->resolveFile($currentName) === null || !$ownershipStore->canAccess($currentName, $currentUser)) {
    Flash::add('error', 'Akses file ditolak', 'Anda tidak memiliki izin untuk rename file ini.');
    $auditLogger->log('rename', 'failed', [
        'old_name' => $currentName,
        'requested_name' => $newName,
        'reason' => 'forbidden',
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php?view=library');
    exit;
}

$renameResult = $storage->rename($currentName, $newName);

if ($renameResult['success'] === true) {
    $oldName = (string) ($renameResult['old_name'] ?? $currentName);
    $renamedName = (string) ($renameResult['new_name'] ?? $newName);
    $ownershipStore->rename($oldName, $renamedName);
    Flash::add('success', 'Rename berhasil', $renameResult['message']);
    $auditLogger->log('rename', 'success', [
        'old_name' => $oldName,
        'new_name' => $renamedName,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php?view=library');
    exit;
}

Flash::add('error', 'Rename gagal', $renameResult['message']);
$auditLogger->log('rename', 'failed', [
    'old_name' => $currentName,
    'requested_name' => $newName,
    'reason' => $renameResult['message'],
    'user_id' => AuthManager::userId(),
]);
header('Location: dashboard.php?view=library');
exit;
