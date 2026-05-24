<?php
declare(strict_types=1);

namespace App\Security;

use App\Services\UserStore;

final class AuthManager
{
    private const SESSION_KEY = '_auth_user';

    /**
     * @return array{id:string,name:string,role:string}|null
     */
    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user)) {
            return null;
        }

        $id = $user['id'] ?? null;
        $name = $user['name'] ?? null;
        $role = $user['role'] ?? null;

        if (!is_string($id) || !is_string($name) || !is_string($role)) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $name,
            'role' => $role,
        ];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && in_array($user['role'], ['superadmin', 'admin'], true);
    }

    public static function can(string $permission): bool
    {
        $user = self::user();

        if ($user === null) {
            return $permission === 'home';
        }

        return self::roleAllows((string) $user['role'], $permission);
    }

    public static function roleAllows(string $role, string $permission): bool
    {
        $permissions = [
            'superadmin' => [
                'home',
                'dashboard',
                'upload',
                'download',
                'delete',
                'rename',
                'backup',
                'manage_trash',
                'view_audit',
                'manage_users',
                'manage_all_files',
            ],
            'admin' => [
                'home',
                'dashboard',
                'upload',
                'download',
                'delete',
                'rename',
                'backup',
                'manage_trash',
                'view_audit',
                'manage_all_files',
            ],
            'user' => [
                'home',
                'dashboard',
                'upload',
                'download',
                'delete',
                'rename',
                'manage_trash',
            ],
            'guest' => [
                'home',
                'dashboard',
            ],
        ];

        return in_array($permission, $permissions[$role] ?? [], true);
    }

    public static function userId(): ?string
    {
        $user = self::user();

        return $user['id'] ?? null;
    }

    public static function attempt(string $username, string $password): bool
    {
        $username = strtolower(trim($username));
        $users = self::configuredUsers();
        $configuredUser = $users[$username] ?? null;

        if (!is_array($configuredUser)) {
            return false;
        }

        if (($configuredUser['active'] ?? false) !== true) {
            return false;
        }

        $hash = $configuredUser['password_hash'] ?? '';
        if (!is_string($hash) || !password_verify($password, $hash)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id' => (string) ($configuredUser['id'] ?? $username),
            'name' => (string) ($configuredUser['name'] ?? $username),
            'role' => (string) ($configuredUser['role'] ?? 'user'),
        ];

        return true;
    }

    public static function loginConfiguredUser(string $username): bool
    {
        $username = strtolower(trim($username));
        $users = self::configuredUsers();
        $configuredUser = $users[$username] ?? null;

        if (!is_array($configuredUser)) {
            return false;
        }

        if (($configuredUser['active'] ?? false) !== true) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id' => (string) ($configuredUser['id'] ?? $username),
            'name' => (string) ($configuredUser['name'] ?? $username),
            'role' => (string) ($configuredUser['role'] ?? 'user'),
        ];

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        header('Location: login.php');
        exit;
    }

    public static function requirePermission(string $permission, string $fallback = 'dashboard.php'): void
    {
        self::requireLogin();

        if (self::can($permission)) {
            return;
        }

        header('Location: ' . $fallback);
        exit;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function configuredUsers(): array
    {
        $usersPath = (string) \app_config('auth.users_path');
        $userStore = new UserStore($usersPath);

        return $userStore->all();
    }
}
