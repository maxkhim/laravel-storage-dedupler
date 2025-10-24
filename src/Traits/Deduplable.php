<?php

namespace Maxkhim\Dedupler\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Maxkhim\Dedupler\Contracts\FileSourceInterface;
use Maxkhim\Dedupler\Facades\Dedupler;
use Maxkhim\Dedupler\Models\UniqueFile;
use Illuminate\Http\UploadedFile;
use Maxkhim\Dedupler\Models\Deduplicatable;

trait Deduplable
{
    /**
     * Полиморфная связь через промежуточную таблицу
     */
    public function uniqueFileUploads(): MorphMany
    {
        return $this->morphMany(Deduplicatable::class, 'deduplable');
    }

    /**
     * Получить все файлы через промежуточную таблицу
     */
    public function uniqueFiles()
    {
        return $this->belongsToMany(
            UniqueFile::class,
            Deduplicatable::class,
            'deduplable_id',
            'sha1_hash',
            'id',
            'id'
        )->withPivot('status', 'original_name', 'created_at', 'updated_at');
    }

    /**
     * Сохранить файл из любого источника
     */
    public function storeUniqueFile(FileSourceInterface $fileSource, array $options = []): ?UniqueFile
    {
        return Dedupler::store($fileSource, $this, $options);
    }

    /**
     * Сохранить UploadedFile
     */
    public function storeUploadedFile(UploadedFile $file, array $options = []): ?UniqueFile
    {
        return Dedupler::storeFromUploadedFile($file, $this, $options);
    }

    /**
     * Сохранить локальный файл
     */
    public function storeLocalFile(string $path, array $options = []): ?UniqueFile
    {
        return Dedupler::storeFromPath($path, $this, $options);
    }

    /**
     * Сохранить из потока
     */
    public function storeStreamFile($stream, string $filename, array $options = []): ?UniqueFile
    {
        return Dedupler::storeFromStream($stream, $filename, $this, $options);
    }

    /**
     * Сохранить из сырого контента
     */
    public function storeContentFile(string $content, string $filename, array $options = []): ?UniqueFile
    {
        return Dedupler::storeFromContent($content, $filename, $this, $options);
    }

    /**
     * Пакетное сохранение файлов
     */
    public function storeFilesBatch(array $fileSources, array $options = []): array
    {
        return Dedupler::storeBatch($fileSources, $this, $options);
    }

    /**
     * Прикрепить существующий файл по хэшу
     */
    public function attachUniqueFile(string $fileHash, array $pivotAttributes = []): ?Deduplicatable
    {
        return Dedupler::attach($fileHash, $this, $pivotAttributes);
    }

    /**
     * Открепить файл
     */
    public function detachUniqueFile(string $fileHash): bool
    {
        return Dedupler::detach($fileHash, $this);
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
