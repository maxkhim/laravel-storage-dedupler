<?php

namespace Maxkhim\UniqueFileStorage\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;

interface FileStorageInterface
{
    public function store(UploadedFile $file, Model $model, string $disk = 'public');
    public function attach(string $fileHash, Model $model);
    public function delete(string $fileHash, bool $force = false);
    public function exists(string $fileHash): bool;
}