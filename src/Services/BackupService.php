<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use ZipArchive;

final class BackupService
{
    private string $localDir;
    private ?string $externalDir;
    private string $sourceDir;
    private string $uploadDir;
    private string $trashDir;
    /** @var array<int, string> */
    private array $excludeDirs;
    /** @var array<string, mixed> */
    private array $databaseConfig;

    /**
     * @param array{
     *     local_dir:string,
     *     external_dir?:string|null,
     *     source_dir:string,
     *     exclude_dirs?:array<int, string>
     * } $backupConfig
     * @param array<string, mixed> $storageConfig
     * @param array<string, mixed> $databaseConfig
     */
    public function __construct(array $backupConfig, array $storageConfig, array $databaseConfig)
    {
        $this->localDir = $this->normalizePath((string) $backupConfig['local_dir']);
        $externalDir = (string) ($backupConfig['external_dir'] ?? '');
        $this->externalDir = $externalDir === '' ? null : $this->normalizePath($externalDir);
        $this->sourceDir = $this->normalizePath((string) $backupConfig['source_dir']);
        $this->uploadDir = $this->normalizePath((string) $storageConfig['upload_dir']);
        $this->trashDir = $this->normalizePath((string) ($storageConfig['trash_dir'] ?? dirname($this->uploadDir) . DIRECTORY_SEPARATOR . 'trash'));
        $this->excludeDirs = array_map('strtolower', $backupConfig['exclude_dirs'] ?? []);
        $this->databaseConfig = $databaseConfig;
    }

    /**
     * @return array{success:bool,message:string,backup_dir?:string,external_dir?:string,files?:array<int, string>,error?:string}
     */
    public function createFullBackup(string $type = 'scheduled'): array
    {
        $date = date('Y-m-d');
        $folderName = $type === 'before_update'
            ? 'before_update_' . $date . '_' . date('His')
            : $date;
        $targetDir = $this->localDir . DIRECTORY_SEPARATOR . $folderName;
        $createdFiles = [];

        try {
            $this->ensureDirectory($targetDir);
            $sourceZip = $targetDir . DIRECTORY_SEPARATOR . 'source_code.zip';
            $uploadsZip = $targetDir . DIRECTORY_SEPARATOR . 'uploads.zip';
            $trashZip = $targetDir . DIRECTORY_SEPARATOR . 'trash.zip';
            $databaseSql = $targetDir . DIRECTORY_SEPARATOR . 'database_cloud_storage_' . $date . '.sql';

            $this->zipDirectory($this->sourceDir, $sourceZip, true);
            $createdFiles[] = $sourceZip;

            $this->zipDirectory($this->uploadDir, $uploadsZip, false);
            $createdFiles[] = $uploadsZip;

            $this->zipDirectory($this->trashDir, $trashZip, false);
            $createdFiles[] = $trashZip;

            $this->dumpDatabase($databaseSql);
            $createdFiles[] = $databaseSql;

            $externalTarget = $this->copyToExternalLocation($targetDir, $folderName);

            return [
                'success' => true,
                'message' => 'Backup berhasil dibuat.',
                'backup_dir' => $targetDir,
                'external_dir' => $externalTarget,
                'files' => $createdFiles,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Backup gagal dibuat.',
                'backup_dir' => $targetDir,
                'files' => $createdFiles,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,backup_dir?:string,file?:string,error?:string}
     */
    public function backupTrashAfterDelete(): array
    {
        $date = date('Y-m-d');
        $targetDir = $this->localDir . DIRECTORY_SEPARATOR . $date;
        $trashZip = $targetDir . DIRECTORY_SEPARATOR . 'trash.zip';

        try {
            $this->ensureDirectory($targetDir);
            $this->zipDirectory($this->trashDir, $trashZip, false);
            $this->copyToExternalLocation($targetDir, $date);

            return [
                'success' => true,
                'message' => 'Backup trash berhasil diperbarui.',
                'backup_dir' => $targetDir,
                'file' => $trashZip,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Backup trash gagal diperbarui.',
                'backup_dir' => $targetDir,
                'file' => $trashZip,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function zipDirectory(string $sourceDir, string $zipPath, bool $respectSourceExcludes): void
    {
        $this->ensureDirectory(dirname($zipPath));

        if (class_exists(ZipArchive::class)) {
            $this->zipDirectoryWithExtension($sourceDir, $zipPath, $respectSourceExcludes);
            return;
        }

        $this->zipDirectoryWithFallback($sourceDir, $zipPath, $respectSourceExcludes);
    }

    private function zipDirectoryWithExtension(string $sourceDir, string $zipPath, bool $respectSourceExcludes): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak bisa membuat file zip: ' . $zipPath);
        }

        if (!is_dir($sourceDir)) {
            $zip->addFromString('README.txt', 'Folder belum tersedia saat backup dibuat.' . PHP_EOL);
            $zip->close();
            return;
        }

        $baseLength = strlen($sourceDir) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) use ($respectSourceExcludes): bool {
                    if (!$current->isDir() || !$respectSourceExcludes) {
                        return true;
                    }

                    return !in_array(strtolower($current->getFilename()), $this->excludeDirs, true);
                }
            )
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();
            $relativePath = substr($path, $baseLength);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $zip->addFile($path, str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
        }

        $zip->close();
    }

    private function zipDirectoryWithFallback(string $sourceDir, string $zipPath, bool $respectSourceExcludes): void
    {
        $handle = fopen($zipPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Tidak bisa membuat file zip: ' . $zipPath);
        }

        $entries = [];

        if (!is_dir($sourceDir)) {
            $this->writeStoredZipEntry($handle, $entries, 'README.txt', 'Folder belum tersedia saat backup dibuat.' . PHP_EOL);
            $this->finishStoredZip($handle, $entries);
            fclose($handle);
            return;
        }

        $baseLength = strlen($sourceDir) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) use ($respectSourceExcludes): bool {
                    if (!$current->isDir() || !$respectSourceExcludes) {
                        return true;
                    }

                    return !in_array(strtolower($current->getFilename()), $this->excludeDirs, true);
                }
            )
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();
            $relativePath = substr($path, $baseLength);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $this->writeStoredZipEntry(
                $handle,
                $entries,
                str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
                $contents,
                $item->getMTime()
            );
        }

        if ($entries === []) {
            $this->writeStoredZipEntry($handle, $entries, 'README.txt', 'Folder kosong saat backup dibuat.' . PHP_EOL);
        }

        $this->finishStoredZip($handle, $entries);
        fclose($handle);
    }

    /**
     * @param resource $handle
     * @param array<int, array{name:string,crc:int,size:int,offset:int,time:int,date:int}> $entries
     */
    private function writeStoredZipEntry($handle, array &$entries, string $name, string $contents, ?int $timestamp = null): void
    {
        [$dosTime, $dosDate] = $this->dosDateTime($timestamp ?? time());
        $crc = (int) sprintf('%u', crc32($contents));
        $size = strlen($contents);
        $offset = ftell($handle);

        fwrite($handle, pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0));
        fwrite($handle, $name);
        fwrite($handle, $contents);

        $entries[] = [
            'name' => $name,
            'crc' => $crc,
            'size' => $size,
            'offset' => (int) $offset,
            'time' => $dosTime,
            'date' => $dosDate,
        ];
    }

    /**
     * @param resource $handle
     * @param array<int, array{name:string,crc:int,size:int,offset:int,time:int,date:int}> $entries
     */
    private function finishStoredZip($handle, array $entries): void
    {
        $centralOffset = ftell($handle);

        foreach ($entries as $entry) {
            fwrite($handle, pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $entry['time'],
                $entry['date'],
                $entry['crc'],
                $entry['size'],
                $entry['size'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ));
            fwrite($handle, $entry['name']);
        }

        $centralSize = (int) ftell($handle) - (int) $centralOffset;
        $entryCount = count($entries);

        fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, $centralSize, (int) $centralOffset, 0));
    }

    /**
     * @return array{0:int,1:int}
     */
    private function dosDateTime(int $timestamp): array
    {
        $parts = getdate($timestamp);
        $year = max(1980, (int) $parts['year']);
        $dosTime = ((int) $parts['hours'] << 11) | ((int) $parts['minutes'] << 5) | ((int) ($parts['seconds'] / 2));
        $dosDate = (($year - 1980) << 9) | ((int) $parts['mon'] << 5) | (int) $parts['mday'];

        return [$dosTime, $dosDate];
    }

    private function dumpDatabase(string $targetPath): void
    {
        $this->ensureDirectory(dirname($targetPath));

        $pdo = Connection::pdo();
        $databaseName = (string) ($this->databaseConfig['name'] ?? '');
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
        $handle = fopen($targetPath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Tidak bisa menulis dump database: ' . $targetPath);
        }

        fwrite($handle, '-- Backup database Cloudify' . PHP_EOL);
        fwrite($handle, '-- Database: ' . $databaseName . PHP_EOL);
        fwrite($handle, '-- Tanggal: ' . date('c') . PHP_EOL . PHP_EOL);
        fwrite($handle, 'SET FOREIGN_KEY_CHECKS=0;' . PHP_EOL . PHP_EOL);

        foreach ($tables as $tableRow) {
            $table = (string) ($tableRow[0] ?? '');
            if ($table === '') {
                continue;
            }

            $createStatement = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            fwrite($handle, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;' . PHP_EOL);
            fwrite($handle, (string) ($createStatement['Create Table'] ?? '') . ';' . PHP_EOL . PHP_EOL);

            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                $values = array_map(static function (mixed $value) use ($pdo): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, array_values($row));

                fwrite($handle, 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');' . PHP_EOL);
            }

            fwrite($handle, PHP_EOL);
        }

        fwrite($handle, 'SET FOREIGN_KEY_CHECKS=1;' . PHP_EOL);
        fclose($handle);
    }

    private function copyToExternalLocation(string $sourceDir, string $folderName): ?string
    {
        if ($this->externalDir === null) {
            return null;
        }

        $externalTarget = $this->externalDir . DIRECTORY_SEPARATOR . $folderName;
        $this->ensureDirectory($externalTarget);

        foreach (glob($sourceDir . DIRECTORY_SEPARATOR . '*') ?: [] as $sourceFile) {
            if (!is_file($sourceFile)) {
                continue;
            }

            copy($sourceFile, $externalTarget . DIRECTORY_SEPARATOR . basename($sourceFile));
        }

        return $externalTarget;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak bisa membuat folder: ' . $directory);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $realPath = realpath($path);
        if ($realPath !== false) {
            return rtrim($realPath, DIRECTORY_SEPARATOR);
        }

        $parent = realpath(dirname($path));
        if ($parent !== false) {
            return $parent . DIRECTORY_SEPARATOR . basename($path);
        }

        return $path;
    }
}
