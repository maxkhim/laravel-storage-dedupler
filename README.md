# Dedupler

### Stop storing duplicate files in your Laravel application.

Tired of seeing the same file stored multiple times? When users upload duplicates, your storage bloats, backups grow, and data consistency suffers.

**Dedupler** is an elegant Laravel package that solves this once and for all. It automatically prevents file duplicates using SHA-1 hashing and provides a beautiful polymorphic API to manage your attachments.

## ✨ Why Dedupler?

- 🚫 **Zero Duplicates** - Automatic deduplication using SHA-1 hashing
- 🔗 **Polymorphic Magic** - Attach files to any model with ease
- 💾 **Storage Efficient** - Save significant disk space
- 🎯 **Simple API** - Intuitive methods for attachment management
- ⚡  **Laravel Native** - Seamlessly integrates with Laravel's ecosystem

## 🚀 Quick Start

![dedupler-so-adapt.png](dedupler-so-adapt.png)

### 1. Install via Composer
```bash
composer require maxkhim/laravel-storage-dedupler
```

### 2. Init package

This command make all necessary migrations,
and verifies application configuration (storage, models, etc.) to ensure the package is ready to use
```bash
php artisan dedupler:install
```

## 🔧 How to use

### 1. Use Facade Dedupler to Store Deduplicated files

```php
/** @var \Illuminate\Http\UploadedFile $file */
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = Dedupler::storeFromUploadedFile($file);
```

```php
/** @var string $absolutePathToFile */
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = Dedupler::storeFromPath($absolutePathToFile);
```

```php
/** @var string $fileContent */
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = Dedupler::storeFromContent($content, 'direct_content_file.ext');
```

### 2. Add Trait to Your Model to keep deduplicated files attached to models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Maxkhim\Dedupler\Traits\Deduplable;

class Post extends Model
{
    use Deduplable;
}

$post = new Post([...]);
```

```php
/** @var \Illuminate\Http\UploadedFile $file */
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = $post->storeUploadedFile($file);
```

```php
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = $post->storeLocalFile($absolutePathToFile);
```

```php
/** @var \Maxkhim\Dedupler\Models\UniqueFile $uniqueFile */
$uniqueFile = $post->storeContentFile($content, 'direct_content_file.ext');
```

### 3. Detach unique files from models

```php
$post->detachUniqueFile($sha1_hash)
```

## About Deduplication

When you upload the same file multiple times:

```php
// First upload - file is stored
$file1 = $post->storeUploadedFile($sameFile);
OR
$file1 = Dedupler::storeFromUploadedFile($sameFile);
// Second upload - returns existing UniqueFile, no duplicate created
$file2 = $post->storeUploadedFile($sameFile);
OR
$file1 = Dedupler::storeFromUploadedFile($sameFile);

$file1->id === $file2->id; // true - same database record and same file in storage
```

## 🛣️ API Reference

### Enable RESTapi endpoint to check file existence

```dotenv
DEDUPLER_REST_ENABLED=true
```

```http request
GET http://localhost:8080/api/dedupler/v1/files/da39a3ee5e6b4b0d3255bfef95601890afd80709
```

```json
{
	"success": true,
	"data": {
		"hash": "da39a3ee5e6b4b0d3255bfef95601890afd80709",
		"sha1_hash": "da39a3ee5e6b4b0d3255bfef95601890afd80709",
		"md5_hash": "d41d8cd98f00b204e9800998ecf8427e",
		"exists": false,
		"filename": "da39a3ee5e6b4b0d3255bfef95601890afd80709.pdf",
		"path": "da\/39\/da39a3ee5e6b4b0d3255bfef95601890afd80709.pdf",
		"mime_type": "application\/pdf",
		"size": 102400,
		"size_human": "100 KB",
		"disk": "deduplicated",
		"status": "completed",
		"created_at": "2025-10-22T18:40:41.000000Z",
		"updated_at": "2025-10-22T18:40:41.000000Z",
		"links_count": 94
	}
}
```

## License

The MIT License (MIT).