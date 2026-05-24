<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class UserStore
{
    /** @var array<int, string> */
    private const ROLES = ['superadmin', 'admin', 'user', 'guest'];

    private PDO $pdo;

    public function __construct(string $usersPath)
    {
        $this->pdo = Connection::pdo();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT id, name, role, active, password_hash FROM users ORDER BY role ASC, name ASC');
        $rows = $statement === false ? [] : $statement->fetchAll();
        $users = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $row['active'] = (bool) ($row['active'] ?? false);
            $users[$id] = $row;
        }

        return $users;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $users = array_values($this->all());
        usort(
            $users,
            static fn (array $a, array $b): int => strcmp((string) ($a['role'] ?? ''), (string) ($b['role'] ?? ''))
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return $users;
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return self::ROLES;
    }

    /**
     * @return array<string, int>
     */
    public function countsByRole(): array
    {
        $counts = array_fill_keys(self::ROLES, 0);

        foreach ($this->all() as $user) {
            $role = (string) ($user['role'] ?? 'user');
            if (array_key_exists($role, $counts)) {
                $counts[$role]++;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $id = $this->normalizeId($id);
        if ($id === '') {
            return null;
        }

        $users = $this->all();
        $user = $users[$id] ?? null;

        return is_array($user) ? $user : null;
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function registerUser(string $id, string $name, string $password): array
    {
        $id = $this->normalizeId($id);

        if ($id === '') {
            return ['success' => false, 'message' => 'Username hanya boleh berisi huruf, angka, titik, underscore, atau minus.'];
        }

        if ($this->find($id) !== null) {
            return ['success' => false, 'message' => 'Username sudah digunakan. Silakan pilih username lain.'];
        }

        if (strlen(trim($password)) < 6) {
            return ['success' => false, 'message' => 'Password minimal 6 karakter.'];
        }

        return $this->saveUser($id, $name, 'user', true, $password);
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function saveUser(string $id, string $name, string $role, bool $active, ?string $password = null): array
    {
        $id = $this->normalizeId($id);
        $name = trim($name);
        $role = strtolower(trim($role));

        if ($id === '') {
            return ['success' => false, 'message' => 'Username hanya boleh berisi huruf, angka, titik, underscore, atau minus.'];
        }

        if ($name === '') {
            return ['success' => false, 'message' => 'Nama pengguna wajib diisi.'];
        }

        if (!in_array($role, self::ROLES, true)) {
            return ['success' => false, 'message' => 'Role pengguna tidak valid.'];
        }

        $existing = $this->find($id) ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $passwordHash = $existing['password_hash'] ?? null;
        if ($password !== null && trim($password) !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        if (!is_string($passwordHash) || $passwordHash === '') {
            return ['success' => false, 'message' => 'Password wajib diisi untuk pengguna baru.'];
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO users (id, name, role, active, password_hash)
             VALUES (:id, :name, :role, :active, :password_hash)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                role = VALUES(role),
                active = VALUES(active),
                password_hash = VALUES(password_hash)'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'active' => $active ? 1 : 0,
            'password_hash' => $passwordHash,
        ]);

        return ['success' => true, 'message' => 'Data pengguna berhasil disimpan.'];
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function deleteUser(string $id): array
    {
        $id = $this->normalizeId($id);
        $user = $this->find($id);

        if ($id === '' || $user === null) {
            return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
        }

        if (($user['role'] ?? '') === 'superadmin' && $this->countsByRole()['superadmin'] <= 1) {
            return ['success' => false, 'message' => 'Minimal satu superadmin harus tetap aktif di sistem.'];
        }

        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);

        return ['success' => true, 'message' => 'Pengguna berhasil dihapus.'];
    }

    private function normalizeId(string $id): string
    {
        $id = strtolower(trim($id));

        return preg_match('/^[a-z0-9._-]{3,40}$/', $id) === 1 ? $id : '';
    }

}
