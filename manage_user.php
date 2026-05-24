<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Security\AuthManager;
use App\Security\CsrfManager;
use App\Services\AuditLogger;
use App\Services\UserStore;
use App\Support\Flash;

AuthManager::requirePermission('manage_users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::add('error', 'Akses ditolak', 'Endpoint user management hanya menerima metode POST.');
    header('Location: dashboard.php');
    exit;
}

if (!CsrfManager::validate($_POST['csrf_token'] ?? null)) {
    Flash::add('error', 'Token keamanan tidak valid', 'Permintaan manajemen user dibatalkan.');
    header('Location: dashboard.php');
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');
$userId = (string) ($_POST['user_id'] ?? '');
$currentUserId = AuthManager::userId();
$userStore = new UserStore((string) app_config('auth.users_path'));
$auditLogger = new AuditLogger((string) app_config('audit.log_path'));

if ($action === 'delete') {
    if ($currentUserId !== null && strtolower(trim($userId)) === $currentUserId) {
        Flash::add('error', 'User tidak dihapus', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        header('Location: dashboard.php');
        exit;
    }

    $result = $userStore->deleteUser($userId);
    Flash::add($result['success'] ? 'success' : 'error', $result['success'] ? 'User dihapus' : 'User gagal dihapus', $result['message']);
    $auditLogger->log('manage_user_delete', $result['success'] ? 'success' : 'failed', [
        'target_user_id' => $userId,
        'user_id' => $currentUserId,
        'reason' => $result['success'] ? null : $result['message'],
    ]);
    header('Location: dashboard.php');
    exit;
}

$name = (string) ($_POST['name'] ?? '');
$role = (string) ($_POST['role'] ?? 'user');
$active = isset($_POST['active']);
$password = (string) ($_POST['password'] ?? '');

if ($currentUserId !== null && strtolower(trim($userId)) === $currentUserId) {
    $existing = $userStore->find($userId);
    if (($existing['role'] ?? '') === 'superadmin') {
        $role = 'superadmin';
        $active = true;
    }
}

$result = $userStore->saveUser($userId, $name, $role, $active, $password === '' ? null : $password);
Flash::add($result['success'] ? 'success' : 'error', $result['success'] ? 'User disimpan' : 'User gagal disimpan', $result['message']);
$auditLogger->log('manage_user_save', $result['success'] ? 'success' : 'failed', [
    'target_user_id' => $userId,
    'target_role' => $role,
    'target_active' => $active,
    'user_id' => $currentUserId,
    'reason' => $result['success'] ? null : $result['message'],
]);

header('Location: dashboard.php');
exit;
