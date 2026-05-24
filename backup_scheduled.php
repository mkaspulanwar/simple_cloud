<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\AuditLogger;
use App\Services\BackupService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Backup terjadwal hanya boleh dijalankan dari CLI.' . PHP_EOL;
    exit(1);
}

$backupService = new BackupService(
    app_config('backup'),
    app_config('storage'),
    app_config('database')
);
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));
$result = $backupService->createFullBackup('scheduled');

$auditLogger->log('backup_scheduled', $result['success'] === true ? 'success' : 'failed', [
    'backup_dir' => $result['backup_dir'] ?? null,
    'external_dir' => $result['external_dir'] ?? null,
    'reason' => $result['error'] ?? null,
]);

echo ($result['success'] === true ? 'Backup terjadwal berhasil: ' : 'Backup terjadwal gagal: ')
    . (string) ($result['backup_dir'] ?? $result['message'])
    . PHP_EOL;

exit($result['success'] === true ? 0 : 1);
