<?php

declare(strict_types=1);

return [
    "enabled" => env("DEDUPLER_ENABLED", true),
    "connection" => env("DEDUPLER_CONNECTION", "default"),
    'db' => [
        'host' => env('DEDUPLER_DB_HOST', '127.0.0.1'),
        'port' => env('DEDUPLER_DB_PORT', '3306'),
        'database' => env('DEDUPLER_DB_DATABASE', 'unique_files'),
        'username' => env('DEDUPLER_DB_USERNAME', 'unique_files_dbo'),
        'password' => env('DEDUPLER_DB_PASSWORD', ''),
        'driver' => env('DEDUPLER_DB_DRIVER', 'mariadb'),
    ],
    'default_disk' => env('DEDUPLER_DISK', 'public'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/unique-files'),
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public/unique-files'),
            'url' => env('APP_URL') . '/storage/unique-files',
            'visibility' => 'public',
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'root' => 'unique-files',
        ],
    ],
    'hash_algorithm' => 'sha1', // или 'md5'
    'path_generator' => 'hash_based', // или 'date_based'
    'optimization' => [
        'stream_upload' => true,
        'chunk_size' => 1024 * 1024, // 1MB chunks для больших файлов
    ],
    'api' => [
        'enabled' => true,
        'middleware' => ['api'],
        'prefix' => 'api/dedupler/v1/files',
        'max_file_size' => 102400, // 100MB в килобайтах
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],
    'download' => [
        'chunk_size' => 1048576, // 1MB для потоковой передачи
        'cache_control' => 'public, max-age=31536000',
    ],
];
