<?php

namespace Maxkhim\UniqueFileStorage\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Maxkhim\UniqueFileStorage\Contracts\FileSourceInterface;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFile;
use Illuminate\Http\UploadedFile;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFileToModel;

trait HasUniqueFiles
{
    /**
     * Полиморфная связь через промежуточную таблицу
     */
    public function uniqueFileUploads(): MorphMany
    {
        return $this->morphMany(UniqueUploadedFileToModel::class, 'uploadable');
    }

    /**
     * Получить все файлы через промежуточную таблицу
     */
    public function uniqueFiles()
    {
        return $this->belongsToMany(
            UniqueUploadedFile::class,
            'unique_uploaded_files_to_models',
            'uploadable_id',
            'sha1_hash',
            'id',
            'id'
        )->withPivot('status', 'original_name', 'created_at', 'updated_at');
    }

    /**
     * Сохранить файл из любого источника
     */
    public function storeUniqueFile(FileSourceInterface $fileSource, array $options = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->store($fileSource, $this, $options);
    }

    /**
     * Сохранить UploadedFile
     */
    public function storeUploadedFile(UploadedFile $file, array $options = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->storeFromUploadedFile($file, $this, $options);
    }

    /**
     * Сохранить локальный файл
     */
    public function storeLocalFile(string $path, array $options = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->storeFromPath($path, $this, $options);
    }

    /**
     * Сохранить из потока
     */
    public function storeStreamFile($stream, string $filename, array $options = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->storeFromStream($stream, $filename, $this, $options);
    }

    /**
     * Сохранить из сырого контента
     */
    public function storeContentFile(string $content, string $filename, array $options = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->storeFromContent($content, $filename, $this, $options);
    }

    /**
     * Пакетное сохранение файлов
     */
    public function storeFilesBatch(array $fileSources, array $options = []): array
    {
        return app('unique-file-storage')->storeBatch($fileSources, $this, $options);
    }

    /**
     * Прикрепить существующий файл по хэшу
     */
    public function attachUniqueFile(string $fileHash, array $pivotAttributes = []): ?UniqueUploadedFileToModel
    {
        return app('unique-file-storage')->attach($fileHash, $this, $pivotAttributes);
    }

    /**
     * Открепить файл
     */
    public function detachUniqueFile(string $fileHash): bool
    {
        return app('unique-file-storage')->detach($fileHash, $this);
    }

    /**
     * Получить файлы по статусу
     */
    public function uniqueFilesWithStatus(string $status)
    {
        return $this->uniqueFileUploads()->withStatus($status)->with('file')->get();
    }

    /**
     * Получить завершенные файлы
     */
    public function completedUniqueFiles()
    {
        return $this->uniqueFilesWithStatus('completed');
    }

    /**
     * Проверить, имеет ли модель файл с указанным хэшем
     */
    public function hasUniqueFile(string $fileHash): bool
    {
        return $this->uniqueFileUploads()
            ->where('sha1_hash', $fileHash)
            ->exists();
    }
}