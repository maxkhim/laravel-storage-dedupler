<?php

namespace Maxkhim\UniqueFileStorage\Traits;

use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFile;
use Illuminate\Http\UploadedFile;

trait HasUniqueFiles
{
    public function uniqueFiles()
    {
        return $this->morphMany(UniqueUploadedFile::class, 'unique_file_storage');
    }

    public function storeUniqueFile(UploadedFile $file, string $disk = 'public'): ?UniqueUploadedFile
    {
        return app('unique-file-storage')->store($file, $this, $disk);
    }

    public function attachUniqueFile(string $fileHash): ?UniqueUploadedFile
    {
        return app('unique-file-storage')->attach($fileHash, $this);
    }
}