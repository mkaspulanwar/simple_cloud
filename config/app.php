<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Cloudify',
        'timezone' => 'Asia/Makassar',
    ],
    'storage' => [
        'upload_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads',
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
];
