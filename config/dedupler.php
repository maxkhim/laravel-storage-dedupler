<?php

declare(strict_types=1);

return [
    "enabled" => env("DEDUPLER_ENABLED", true),
    "db_connection" => env("DEDUPLER_CONNECTION", config('database.default')),
    'db' => [
        'host' => env('DEDUPLER_DB_HOST', '127.0.0.1'),
        'port' => env('DEDUPLER_DB_PORT', '3306'),
        'database' => env('DEDUPLER_DB_DATABASE', 'unique_files'),
        'username' => env('DEDUPLER_DB_USERNAME', 'unique_files_dbo'),
        'password' => env('DEDUPLER_DB_PASSWORD', ''),
        'driver' => env('DEDUPLER_DB_DRIVER', 'mariadb'),
    ],
    'default_disk' => env('DEDUPLER_DISK', 'deduplicated'),
    'disks' => [
        'deduplicated' => [
            'driver' => 'local',
            'root' => env("DEDUPLER_DISK_PATH", storage_path('app/deduplicated')),
            'visibility' => 'private',
            'url' => 'api/dedupler/v1/files',
        ],
    ],
    'path_generator' => 'hash_based', // или 'date_based'
    'optimization' => [
        'stream_upload' => true,
        'chunk_size' => 1024 * 1024, // 1MB chunks для больших файлов
    ],
    'api' => [
        'enabled' => env("DEDUPLER_REST_ENABLED", false),
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
