<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class FileOwnershipStore
{
    private PDO $pdo;
    private ?bool $hasExtensionColumn = null;
    /** @var array<int, string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function __construct(string $metadataPath)
    {
        $this->pdo = Connection::pdo();
    }

    /**
     * @param array{id:string,name:string,role:string} $user
     */
    public function canAccess(string $fileName, array $user): bool
    {
        if ($user['role'] === 'guest') {
            return false;
        }

        if (in_array($user['role'], ['superadmin', 'admin'], true)) {
            return true;
        }

        $metadata = $this->recordFor($fileName);
        if ($metadata === null) {
            return false;
        }

        return ($metadata['owner_id'] ?? null) === $user['id'];
    }

    /**
     * @param array{id:string,name:string,role:string} $user
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,owner_id:?string,owner_name:?string}>
     */
    public function listFilesForUser(array $user): array
    {
        if ($user['role'] === 'guest') {
            return [];
        }

        if (in_array($user['role'], ['superadmin', 'admin'], true)) {
            return $this->fetchFiles(
                'SELECT files.file_name,
                        files.owner_id,
                        ' . $this->extensionSelect() . ',
                        files.size,
                        files.mime,
                        files.created_at,
                        files.updated_at,
                        users.name AS owner_name
                 FROM files
                 LEFT JOIN users ON users.id = files.owner_id
                 ORDER BY files.created_at DESC, files.file_name ASC'
            );
        }

        return $this->fetchFiles(
            'SELECT files.file_name,
                    files.owner_id,
                    ' . $this->extensionSelect() . ',
                    files.size,
                    files.mime,
                    files.created_at,
                    files.updated_at,
                    users.name AS owner_name
             FROM files
             LEFT JOIN users ON users.id = files.owner_id
             WHERE files.owner_id = :owner_id
             ORDER BY files.created_at DESC, files.file_name ASC',
            ['owner_id' => $user['id']]
        );
    }

    /**
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,owner_id:?string,owner_name:?string}>
     */
    public function listPublicImageFiles(): array
    {
        [$whereSql, $params] = $this->imageWhereClause();

        return $this->fetchFiles(
            'SELECT files.file_name,
                    files.owner_id,
                    ' . $this->extensionSelect() . ',
                    files.size,
                    files.mime,
                    files.created_at,
                    files.updated_at,
                    users.name AS owner_name
             FROM files
             LEFT JOIN users ON users.id = files.owner_id
             WHERE ' . $whereSql . '
             ORDER BY files.created_at DESC, files.file_name ASC',
            $params
        );
    }

    /**
     * @param array<int, array{name:string,size:int,modified:int,extension:string,mime:string}> $files
     * @param array{id:string,name:string,role:string} $user
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,owner_id:?string,owner_name:?string}>
     */
    public function filterFilesForUser(array $files, array $user): array
    {
        $visibleFiles = [];

        foreach ($files as $file) {
            $fileName = (string) $file['name'];
            if (!$this->canAccess($fileName, $user)) {
                continue;
            }

            $record = $this->recordFor($fileName);
            $file['owner_id'] = $record['owner_id'] ?? null;
            $file['owner_name'] = $record['owner_name'] ?? null;
            $visibleFiles[] = $file;
        }

        return array_values($visibleFiles);
    }

    /**
     * @param array<int, array{name:string,size:int,modified:int,extension:string,mime:string}> $files
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,owner_id:?string,owner_name:?string}>
     */
    public function attachOwners(array $files): array
    {
        if ($files === []) {
            return [];
        }

        $fileNames = array_values(array_unique(array_map(
            static fn (array $file): string => (string) ($file['name'] ?? ''),
            $files
        )));
        $fileNames = array_values(array_filter($fileNames, static fn (string $fileName): bool => $fileName !== ''));

        if ($fileNames === []) {
            return $files;
        }

        $placeholders = implode(', ', array_fill(0, count($fileNames), '?'));
        $statement = $this->pdo->prepare(
            'SELECT files.file_name,
                    files.owner_id,
                    users.name AS owner_name
             FROM files
             LEFT JOIN users ON users.id = files.owner_id
             WHERE files.file_name IN (' . $placeholders . ')'
        );
        $statement->execute($fileNames);

        $owners = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $owners[(string) ($row['file_name'] ?? '')] = [
                'owner_id' => $row['owner_id'] ?? null,
                'owner_name' => $row['owner_name'] ?? null,
            ];
        }

        foreach ($files as &$file) {
            $fileName = (string) ($file['name'] ?? '');
            $file['owner_id'] = $owners[$fileName]['owner_id'] ?? null;
            $file['owner_name'] = $owners[$fileName]['owner_name'] ?? null;
        }
        unset($file);

        return $files;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,owner_id:?string,owner_name:?string}>
     */
    private function fetchFiles(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $files = [];

        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $fileName = (string) ($row['file_name'] ?? '');
            if ($fileName === '') {
                continue;
            }

            $files[] = [
                'name' => $fileName,
                'size' => (int) ($row['size'] ?? 0),
                'modified' => $this->timestampFromRow($row),
                'extension' => (string) ($row['extension'] ?? strtolower(pathinfo($fileName, PATHINFO_EXTENSION))),
                'mime' => (string) ($row['mime'] ?? ''),
                'owner_id' => isset($row['owner_id']) ? (string) $row['owner_id'] : null,
                'owner_name' => isset($row['owner_name']) ? (string) $row['owner_name'] : null,
            ];
        }

        return $files;
    }

    private function extensionSelect(): string
    {
        if ($this->hasExtensionColumn()) {
            return 'files.extension';
        }

        return "LOWER(SUBSTRING_INDEX(files.file_name, '.', -1)) AS extension";
    }

    /**
     * @return array{0:string,1:array<string, string>}
     */
    private function imageWhereClause(): array
    {
        if ($this->hasExtensionColumn()) {
            $params = [];
            foreach (self::IMAGE_EXTENSIONS as $index => $extension) {
                $params['extension_' . $index] = $extension;
            }

            $placeholders = implode(', ', array_map(
                static fn (int $index): string => ':extension_' . $index,
                array_keys(self::IMAGE_EXTENSIONS)
            ));

            return ['files.extension IN (' . $placeholders . ')', $params];
        }

        $conditions = [];
        $params = [];
        foreach (self::IMAGE_EXTENSIONS as $index => $extension) {
            $conditions[] = 'LOWER(files.file_name) LIKE :file_extension_' . $index;
            $params['file_extension_' . $index] = '%.' . $extension;
        }

        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    private function hasExtensionColumn(): bool
    {
        if ($this->hasExtensionColumn !== null) {
            return $this->hasExtensionColumn;
        }

        $statement = $this->pdo->query("SHOW COLUMNS FROM `files` LIKE 'extension'");
        $this->hasExtensionColumn = $statement !== false && $statement->fetch() !== false;

        return $this->hasExtensionColumn;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timestampFromRow(array $row): int
    {
        $date = (string) ($row['updated_at'] ?? $row['created_at'] ?? '');
        if ($date === '') {
            return time();
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? time() : $timestamp;
    }

    public function ownerFor(string $fileName): ?string
    {
        $record = $this->recordFor($fileName);

        if ($record === null || !isset($record['owner_id']) || !is_string($record['owner_id'])) {
            return null;
        }

        return $record['owner_id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recordFor(string $fileName): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT files.file_name,
                    files.owner_id,
                    users.name AS owner_name,
                    users.role AS owner_role
             FROM files
             LEFT JOIN users ON users.id = files.owner_id
             WHERE files.file_name = :file_name
             LIMIT 1'
        );
        $statement->execute(['file_name' => $fileName]);
        $record = $statement->fetch();

        return is_array($record) ? $record : null;
    }

    public function setOwner(string $fileName, string $ownerId, ?int $size = null, ?string $mime = null): void
    {
        if (!$this->hasExtensionColumn()) {
            $statement = $this->pdo->prepare(
                'INSERT INTO files (file_name, owner_id, size, mime)
                 VALUES (:file_name, :owner_id, :size, :mime)
                 ON DUPLICATE KEY UPDATE
                    owner_id = VALUES(owner_id),
                    size = VALUES(size),
                    mime = VALUES(mime)'
            );
            $statement->execute([
                'file_name' => $fileName,
                'owner_id' => $ownerId,
                'size' => $size,
                'mime' => $mime,
            ]);

            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO files (file_name, owner_id, extension, size, mime)
             VALUES (:file_name, :owner_id, :extension, :size, :mime)
             ON DUPLICATE KEY UPDATE
                owner_id = VALUES(owner_id),
                extension = VALUES(extension),
                size = VALUES(size),
                mime = VALUES(mime)'
        );
        $statement->execute([
            'file_name' => $fileName,
            'owner_id' => $ownerId,
            'extension' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            'size' => $size,
            'mime' => $mime,
        ]);
    }

    public function remove(string $fileName): void
    {
        $statement = $this->pdo->prepare('DELETE FROM files WHERE file_name = :file_name');
        $statement->execute(['file_name' => $fileName]);
    }

    public function rename(string $oldName, string $newName): void
    {
        if ($this->recordFor($oldName) === null) {
            return;
        }

        if (!$this->hasExtensionColumn()) {
            $statement = $this->pdo->prepare(
                'UPDATE files
                 SET file_name = :new_name
                 WHERE file_name = :old_name'
            );
            $statement->execute([
                'new_name' => $newName,
                'old_name' => $oldName,
            ]);

            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE files
             SET file_name = :new_name,
                 extension = :extension
             WHERE file_name = :old_name'
        );
        $statement->execute([
            'new_name' => $newName,
            'extension' => strtolower(pathinfo($newName, PATHINFO_EXTENSION)),
            'old_name' => $oldName,
        ]);
    }
}
