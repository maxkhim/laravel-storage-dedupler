# Модуль контроля уникальности загружаемых файлов (unique-file-storage)

Модуль для Laravel, который реализует загрузку файлов без дублирования

Пакет использует переменные окружения для настройки параметров. Все значения по умолчанию можно изменить, добавив соответствующие строки в файл `.env` вашего проекта.

## 🚀 Installation

```bash
composer maxkhim/unique-file-storage
```

```bash
php artisan migrate
```



## 📌 Доступные переменные окружения

| Переменная                          | Значение по умолчанию | Описание                                                                                   |
|-------------------------------------|-----------------------|--------------------------------------------------------------------------------------------|
| `UNIQUE_FILE_STORAGE`               | `true`                | Включение/отключение функционала пакета (значение `false` отключает проверку уникальности) |
| `UNIQUE_FILE_STORAGE_DB_HOST`       | `127.0.0.1`           | Хост базы данных для хранения информации о файлах                                          |
| `UNIQUE_FILE_STORAGE_DB_PORT`       | `3306`                | Порт базы данных                                                                           |
| `UNIQUE_FILE_STORAGE_DB_DATABASE`   | `unique_files`        | Имя базы данных                                                                            |
| `UNIQUE_FILE_STORAGE_DB_USERNAME`   | `unique_files_dbo`    | Имя пользователя БД                                                                        |
| `UNIQUE_FILE_STORAGE_DB_PASSWORD`   | `(пустая строка)`     | Пароль пользователя БД                                                                     |
| `UNIQUE_FILE_STORAGE_DB_CONNECTION` | `mariadb`             | Драйвер БД (например, `mysql`, `pgsql`, `sqlite`, `sqlsrv`)                                |

### 🧪 Пример .env

```env
UNIQUE_FILE_STORAGE=true
UNIQUE_FILE_STORAGE_DB_HOST=localhost
UNIQUE_FILE_STORAGE_DB_PORT=3306
UNIQUE_FILE_STORAGE_DB_DATABASE=my_unique_files_db
UNIQUE_FILE_STORAGE_DB_USERNAME=db_user
UNIQUE_FILE_STORAGE_DB_PASSWORD=your_password
UNIQUE_FILE_STORAGE_DB_CONNECTION=mysql
```

```
php artisan config:clear
```

## ⚙️ Configuration

Create and configure the package in `config/unique-file-storage.php`:

```php
return [
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
    
    'hash_algorithm' => 'sha1',
    'path_generator' => 'hash_based',
    
    'optimization' => [
        'stream_upload' => true,
        'chunk_size' => 1024 * 1024,
    ],
    
    'api' => [
        'enabled' => true,
        'middleware' => ['api'],
        'prefix' => 'api/v1/files',
        'max_file_size' => 102400,
    ],
];
```

## 🔌 Basic Usage

### Add Trait to Models

```php
use YourVendor\UniqueFileStorage\Traits\HasUniqueFiles;

class User extends Model
{
    use HasUniqueFiles;
}

class Post extends Model
{
    use HasUniqueFiles;
}
```

```php
$user = User::find(1);

// Upload from HTTP request
$fileRelation = $user->storeUploadedFile($request->file('avatar'), [
    'disk' => 's3',
    'pivot' => ['status' => 'completed']
]);

// Upload local file
$fileRelation = $user->storeLocalFile('/path/to/file.jpg');

// Upload from stream
$stream = fopen('https://example.com/file.pdf', 'r');
$fileRelation = $user->storeStreamFile($stream, 'document.pdf');

// Upload from content
$fileRelation = $user->storeContentFile('File content', 'note.txt');

// Batch upload
$files = [$request->file('avatar'), '/path/to/file.jpg'];
$results = $user->storeFilesBatch($files);
```

## 🌐 REST API

### Upload Single File
**POST** `/api/v1/files`

```http
POST /api/v1/files
Content-Type: multipart/form-data

file: <file>
model_type: App\Models\User
model_id: 1
disk: s3
```

```json
{
  "success": true,
  "data": {
    "hash": "a1b2c3d4e5f67890123456789012345678901234",
    "filename": "a1b2c3d4e5f6.jpg",
    "original_name": "profile.jpg",
    "mime_type": "image/jpeg",
    "size": 1048576,
    "size_human": "1 MB",
    "disk": "s3",
    "status": "completed",
    "url": "https://bucket.s3.amazonaws.com/...",
    "download_url": "http://localhost/api/v1/files/a1b2c3d4e5f6.../download",
    "stream_url": "http://localhost/api/v1/files/a1b2c3d4e5f6.../stream",
    "created_at": "2023-10-05T12:00:00.000000Z"
  }
}

```


### Upload Multiple File
**POST** `/api/v1/files`

```http
POST /api/v1/files/batch
Content-Type: multipart/form-data

files[]: <file1>
files[]: <file2>
model_type: App\Models\Post
model_id: 5
disk: public
```

### Get File Information
**GET** `/api/v1/files/{hash}`

```http
GET /api/v1/files/a1b2c3d4e5f67890123456789012345678901234
```

## 🛠️ FileStorage Facade

### Core Storage Methods

```php
use YourVendor\UniqueFileStorage\Facades\FileStorage;

// Store from any source
$fileRelation = FileStorage::store($fileSource, $model, $options);

// Convenience methods
$fileRelation = FileStorage::storeFromUploadedFile($uploadedFile, $model, $options);
$fileRelation = FileStorage::storeFromPath('/path/to/file.jpg', $model, $options);
$fileRelation = FileStorage::storeFromStream($stream, 'file.pdf', $model, $options);
$fileRelation = FileStorage::storeFromContent($content, 'note.txt', $model, $options);

// Batch operations
$results = FileStorage::storeBatch($fileSources, $model, $options);
```

### Core Storage Methods