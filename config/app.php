<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Anwar Group Document Hub',
        'timezone' => 'Asia/Makassar',
    ],
    'storage' => [
        'upload_dir' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads',
        'max_file_size' => 20 * 1024 * 1024,
        'max_storage_capacity' => 1024 * 1024 * 1024,
        'allowed_extensions' => [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'csv',
            'txt',
            'png',
            'jpg',
            'jpeg',
            'zip',
            'rar',
            'ppt',
            'pptx',
        ],
        'allowed_mime_types' => [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
            'txt' => ['text/plain'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/octet-stream',
            ],
        ],
    ],
    'audit' => [
        'log_path' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit.log',
        'max_preview_events' => 8,
    ],
];
