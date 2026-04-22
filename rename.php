<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\CloudStorageService;
use App\Support\Flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint rename hanya menerima metode POST.');
    header('Location: index.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan rename dibatalkan demi keamanan.');
    header('Location: index.php');
    exit;
}

$currentName = (string) ($_POST['current_file'] ?? '');
$newName = (string) ($_POST['new_file'] ?? '');

if ($currentName === '' || $newName === '') {
    Flash::add('error', 'Data rename tidak lengkap', 'Nama file asal dan nama file baru wajib diisi.');
    header('Location: index.php');
    exit;
}

$storage = new CloudStorageService(app_config('storage'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$renameResult = $storage->rename($currentName, $newName);

if ($renameResult['success'] === true) {
    Flash::add('success', 'Rename berhasil', $renameResult['message']);
    $auditLogger->log('rename', 'success', [
        'old_name' => $renameResult['old_name'] ?? $currentName,
        'new_name' => $renameResult['new_name'] ?? $newName,
    ]);
    header('Location: index.php');
    exit;
}

Flash::add('error', 'Rename gagal', $renameResult['message']);
$auditLogger->log('rename', 'failed', [
    'old_name' => $currentName,
    'requested_name' => $newName,
    'reason' => $renameResult['message'],
]);
header('Location: index.php');
exit;
