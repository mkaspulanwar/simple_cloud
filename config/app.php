<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Cloudify',
        'timezone' => 'Asia/Makassar',
    ],
    'storage' => [
        'upload_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads',
        'trash_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'trash',
        'trash_metadata_path' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'trash_files.json',
        'metadata_path' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'file_owners.json',
        'max_file_size' => 10 * 1024 * 1024,
        'max_storage_capacity' => 1024 * 1024 * 1024,
        'allowed_extensions' => [
            'png',
            'jpg',
            'jpeg',
            'gif',
            'webp',
        ],
        'allowed_mime_types' => [
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
        ],
    ],
    'audit' => [
        'log_path' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit.log',
        'max_preview_events' => 8,
    ],
    'auth' => [
        'users_path' => __DIR__ . DIRECTORY_SEPARATOR . 'users.php',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'simple_cloud',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'backup' => [
        'local_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backup',
        'external_dir' => 'C:' . DIRECTORY_SEPARATOR . 'cloud_storage_backup',
        'source_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..',
        'exclude_dirs' => [
            '.git',
            'backup',
            'uploads',
            'trash',
        ],
    ],
];
