<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Support\Flash;

AuthManager::requirePermission('backup');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint backup hanya menerima metode POST.');
    header('Location: dashboard.php?view=backup');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan backup dibatalkan demi keamanan.');
    header('Location: dashboard.php?view=backup');
    exit;
}

$type = (string) ($_POST['backup_type'] ?? 'manual');
if (!in_array($type, ['manual', 'before_update'], true)) {
    $type = 'manual';
}

$backupService = new BackupService(
    app_config('backup'),
    app_config('storage'),
    app_config('database')
);
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$result = $backupService->createFullBackup($type);

if ($result['success'] === true) {
    Flash::add(
        'success',
        $type === 'before_update' ? 'Backup sebelum update berhasil' : 'Backup berhasil',
        'File source_code.zip, uploads.zip, trash.zip, dan database.sql berhasil dibuat.'
    );
    $auditLogger->log('backup', 'success', [
        'type' => $type,
        'backup_dir' => $result['backup_dir'] ?? null,
        'external_dir' => $result['external_dir'] ?? null,
        'user_id' => AuthManager::userId(),
    ]);
    header('Location: dashboard.php?view=backup');
    exit;
}

Flash::add('error', 'Backup gagal', (string) ($result['error'] ?? $result['message']));
$auditLogger->log('backup', 'failed', [
    'type' => $type,
    'backup_dir' => $result['backup_dir'] ?? null,
    'reason' => $result['error'] ?? $result['message'],
    'user_id' => AuthManager::userId(),
]);
header('Location: dashboard.php?view=backup');
exit;
