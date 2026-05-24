<?php
declare(strict_types=1);

namespace App\Services;

final class TrashMetadataStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * @param array{id:string,name:string,role:string} $user
     */
    public function canAccess(string $trashName, array $user): bool
    {
        if (in_array($user['role'], ['superadmin', 'admin'], true)) {
            return true;
        }

        $record = $this->recordFor($trashName);
        if ($record === null) {
            return false;
        }

        return ($record['owner_id'] ?? null) === $user['id'];
    }

    /**
     * @param array<int, array{name:string,size:int,modified:int,extension:string,mime:string}> $files
     * @param array{id:string,name:string,role:string} $user
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string,original_name:?string,owner_id:?string,owner_name:?string,deleted_at:?string}>
     */
    public function filterFilesForUser(array $files, array $user): array
    {
        $records = $this->all();
        $visible = [];

        foreach ($files as $file) {
            $trashName = (string) ($file['name'] ?? '');
            $record = $records[$trashName] ?? null;

            if (!in_array($user['role'], ['superadmin', 'admin'], true)) {
                if (!is_array($record) || ($record['owner_id'] ?? null) !== $user['id']) {
                    continue;
                }
            }

            $file['original_name'] = is_array($record) ? ($record['original_name'] ?? null) : null;
            $file['owner_id'] = is_array($record) ? ($record['owner_id'] ?? null) : null;
            $file['owner_name'] = is_array($record) ? ($record['owner_name'] ?? null) : null;
            $file['deleted_at'] = is_array($record) ? ($record['deleted_at'] ?? null) : null;
            $visible[] = $file;
        }

        return array_values($visible);
    }

    /**
     * @param array{id:string,name:string,role:string}|null $owner
     * @param array{id:string,name:string,role:string}|null $deletedBy
     */
    public function remember(string $trashName, string $originalName, ?array $owner, ?array $deletedBy): void
    {
        $records = $this->all();
        $records[$trashName] = [
            'trash_name' => $trashName,
            'original_name' => $originalName,
            'owner_id' => $owner['id'] ?? null,
            'owner_name' => $owner['name'] ?? null,
            'deleted_by_id' => $deletedBy['id'] ?? null,
            'deleted_by_name' => $deletedBy['name'] ?? null,
            'deleted_at' => date('c'),
        ];
        $this->write($records);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recordFor(string $trashName): ?array
    {
        $records = $this->all();
        $record = $records[$trashName] ?? null;

        return is_array($record) ? $record : null;
    }

    public function remove(string $trashName): void
    {
        $records = $this->all();
        unset($records[$trashName]);
        $this->write($records);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $json = file_get_contents($this->path);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $records
     */
    private function write(array $records): void
    {
        $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents($this->path, $json . PHP_EOL, LOCK_EX);
    }
}
