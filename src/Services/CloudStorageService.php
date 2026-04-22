<?php
declare(strict_types=1);

namespace App\Services;

final class CloudStorageService
{
    private string $uploadDir;
    private int $maxFileSize;
    private int $maxStorageCapacity;
    /** @var array<int, string> */
    private array $allowedExtensions;
    /** @var array<string, array<int, string>> */
    private array $allowedMimeTypes;

    /**
     * @param array{
     *     upload_dir:string,
     *     max_file_size:int,
     *     max_storage_capacity:int,
     *     allowed_extensions:array<int, string>,
     *     allowed_mime_types:array<string, array<int, string>>
     * } $config
     */
    public function __construct(array $config)
    {
        $this->uploadDir = rtrim($config['upload_dir'], DIRECTORY_SEPARATOR);
        $this->maxFileSize = (int) $config['max_file_size'];
        $this->maxStorageCapacity = (int) $config['max_storage_capacity'];
        $this->allowedExtensions = array_map('strtolower', $config['allowed_extensions']);
        $this->allowedMimeTypes = $config['allowed_mime_types'];

        $this->ensureUploadDirectory();
    }

    public function uploadDirectoryName(): string
    {
        return basename($this->uploadDir);
    }

    public function maxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * @return array{success:bool,message:string,file_name?:string,size?:int,mime?:string}
     */
    public function upload(array $file): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => $this->uploadErrorMessage($errorCode),
            ];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return [
                'success' => false,
                'message' => 'Ukuran file tidak valid.',
            ];
        }

        if ($size > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'Ukuran file melebihi batas maksimum upload.',
            ];
        }

        $stats = $this->getStats();
        if ($stats['total_size'] + $size > $this->maxStorageCapacity) {
            return [
                'success' => false,
                'message' => 'Kapasitas storage tidak mencukupi untuk upload file ini.',
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [
                'success' => false,
                'message' => 'Sumber upload tidak valid.',
            ];
        }

        $originalName = (string) ($file['name'] ?? '');
        $sanitizedName = $this->sanitizeFileName($originalName);
        if ($sanitizedName === '') {
            return [
                'success' => false,
                'message' => 'Nama file tidak valid.',
            ];
        }

        $extension = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
            return [
                'success' => false,
                'message' => 'Ekstensi file tidak diizinkan oleh kebijakan sistem.',
            ];
        }

        $detectedMime = $this->detectMimeType($tmpName);
        if (!$this->isMimeAllowed($extension, $detectedMime)) {
            return [
                'success' => false,
                'message' => 'Tipe file tidak sesuai dengan ekstensi yang diizinkan.',
            ];
        }

        $targetName = $this->buildUniqueFileName($sanitizedName);
        $targetPath = $this->pathFor($targetName);

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return [
                'success' => false,
                'message' => 'Gagal memindahkan file ke storage server.',
            ];
        }

        return [
            'success' => true,
            'message' => 'File berhasil diunggah ke cloud storage lokal.',
            'file_name' => $targetName,
            'size' => $size,
            'mime' => $detectedMime,
        ];
    }

    /**
     * @return array<int, array{name:string,size:int,modified:int,extension:string,mime:string}>
     */
    public function listFiles(): array
    {
        $items = scandir($this->uploadDir);
        if ($items === false) {
            return [];
        }

        $files = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $this->pathFor($item);
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => $item,
                'size' => filesize($path) ?: 0,
                'modified' => filemtime($path) ?: time(),
                'extension' => strtolower(pathinfo($item, PATHINFO_EXTENSION)),
                'mime' => $this->detectMimeType($path),
            ];
        }

        usort(
            $files,
            static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']
        );

        return $files;
    }

    /**
     * @return array{total_files:int,total_size:int,max_capacity:int,usage_percent:float,last_modified:int|null}
     */
    public function getStats(): array
    {
        $files = $this->listFiles();
        $totalSize = 0;
        $lastModified = null;

        foreach ($files as $file) {
            $totalSize += $file['size'];
            if ($lastModified === null || $file['modified'] > $lastModified) {
                $lastModified = $file['modified'];
            }
        }

        $usagePercent = $this->maxStorageCapacity > 0
            ? ($totalSize / $this->maxStorageCapacity) * 100
            : 0;

        return [
            'total_files' => count($files),
            'total_size' => $totalSize,
            'max_capacity' => $this->maxStorageCapacity,
            'usage_percent' => round(min($usagePercent, 100), 2),
            'last_modified' => $lastModified,
        ];
    }

    public function delete(string $fileName): bool
    {
        $safeName = $this->sanitizeRequestedName($fileName);
        if ($safeName === null) {
            return false;
        }

        $filePath = $this->pathFor($safeName);
        if (!is_file($filePath)) {
            return false;
        }

        return unlink($filePath);
    }

    /**
     * @return array{success:bool,message:string,old_name?:string,new_name?:string}
     */
    public function rename(string $currentName, string $newName): array
    {
        $safeCurrentName = $this->sanitizeRequestedName($currentName);
        if ($safeCurrentName === null) {
            return [
                'success' => false,
                'message' => 'Nama file asal tidak valid.',
            ];
        }

        $currentPath = $this->pathFor($safeCurrentName);
        if (!is_file($currentPath)) {
            return [
                'success' => false,
                'message' => 'File asal tidak ditemukan.',
            ];
        }

        $safeNewName = $this->sanitizeFileName($newName);
        if ($safeNewName === '') {
            return [
                'success' => false,
                'message' => 'Nama baru file tidak valid.',
            ];
        }

        $currentExtension = strtolower(pathinfo($safeCurrentName, PATHINFO_EXTENSION));
        $newExtension = strtolower(pathinfo($safeNewName, PATHINFO_EXTENSION));

        if ($newExtension === '' && $currentExtension !== '') {
            $safeNewName .= '.' . $currentExtension;
            $newExtension = $currentExtension;
        }

        if ($currentExtension !== '' && $newExtension !== $currentExtension) {
            return [
                'success' => false,
                'message' => 'Rename tidak boleh mengubah ekstensi file.',
            ];
        }

        if ($newExtension === '' || !in_array($newExtension, $this->allowedExtensions, true)) {
            return [
                'success' => false,
                'message' => 'Ekstensi file tujuan tidak diizinkan.',
            ];
        }

        if ($safeNewName === $safeCurrentName) {
            return [
                'success' => false,
                'message' => 'Nama file baru sama dengan nama sebelumnya.',
            ];
        }

        $targetPath = $this->pathFor($safeNewName);
        if (file_exists($targetPath)) {
            return [
                'success' => false,
                'message' => 'Nama file tujuan sudah dipakai file lain.',
            ];
        }

        if (!rename($currentPath, $targetPath)) {
            return [
                'success' => false,
                'message' => 'Gagal melakukan rename file.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Nama file berhasil diperbarui.',
            'old_name' => $safeCurrentName,
            'new_name' => $safeNewName,
        ];
    }

    /**
     * @return array{name:string,path:string,size:int,mime:string}|null
     */
    public function resolveFile(string $fileName): ?array
    {
        $safeName = $this->sanitizeRequestedName($fileName);
        if ($safeName === null) {
            return null;
        }

        $filePath = $this->pathFor($safeName);
        if (!is_file($filePath)) {
            return null;
        }

        return [
            'name' => $safeName,
            'path' => $filePath,
            'size' => filesize($filePath) ?: 0,
            'mime' => $this->detectMimeType($filePath),
        ];
    }

    private function ensureUploadDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    private function pathFor(string $fileName): string
    {
        return $this->uploadDir . DIRECTORY_SEPARATOR . $fileName;
    }

    private function detectMimeType(string $path): string
    {
        $mime = mime_content_type($path);
        if ($mime === false || $mime === '') {
            return 'application/octet-stream';
        }

        return $mime;
    }

    private function sanitizeFileName(string $name): string
    {
        $baseName = basename(trim($name));
        if ($baseName === '' || $baseName === '.' || $baseName === '..') {
            return '';
        }

        $safeName = preg_replace('/\s+/', '_', $baseName);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $safeName);
        $safeName = preg_replace('/_+/', '_', (string) $safeName);
        $safeName = trim((string) $safeName, '._-');

        return $safeName;
    }

    private function sanitizeRequestedName(string $name): ?string
    {
        $safeName = basename(trim($name));
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._-]+$/', $safeName) !== 1) {
            return null;
        }

        return $safeName;
    }

    private function buildUniqueFileName(string $safeName): string
    {
        $targetPath = $this->pathFor($safeName);
        if (!file_exists($targetPath)) {
            return $safeName;
        }

        $nameOnly = pathinfo($safeName, PATHINFO_FILENAME);
        $extension = pathinfo($safeName, PATHINFO_EXTENSION);
        $stamp = date('Ymd_His') . '_' . random_int(1000, 9999);
        $suffix = $extension !== '' ? '.' . $extension : '';

        return $nameOnly . '_' . $stamp . $suffix;
    }

    private function isMimeAllowed(string $extension, string $mimeType): bool
    {
        $allowedForExtension = $this->allowedMimeTypes[$extension] ?? [];
        if ($allowedForExtension === []) {
            return false;
        }

        return in_array($mimeType, $allowedForExtension, true);
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas dari konfigurasi server.',
            UPLOAD_ERR_PARTIAL => 'File terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload diblokir oleh ekstensi PHP.',
            default => 'Terjadi kesalahan upload yang tidak diketahui.',
        };
    }
}
