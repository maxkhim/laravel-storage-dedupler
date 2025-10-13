<?php

declare(strict_types=1);

return [
    "enabled" => env("UNIQUE_FILE_STORAGE", true),
    'db' => [
        'host' => env('UNIQUE_FILE_STORAGE_DB_HOST', '127.0.0.1'),
        'port' => env('UNIQUE_FILE_STORAGE_DB_PORT', '3306'),
        'database' => env('UNIQUE_FILE_STORAGE_DB_DATABASE', 'unique_files'),
        'username' => env('UNIQUE_FILE_STORAGE_DB_USERNAME', 'unique_files_dbo'),
        'password' => env('UNIQUE_FILE_STORAGE_DB_PASSWORD', ''),
        'driver' => env('UNIQUE_FILE_STORAGE_DB_CONNECTION', 'mariadb'),
    ],
    'default_disk' => env('UNIQUE_FILE_STORAGE_DISK', 'public'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/unique-files'),
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public/unique-files'),
            'url' => env('APP_URL').'/storage/unique-files',
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
];
