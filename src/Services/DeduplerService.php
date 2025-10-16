<?php

namespace Maxkhim\Dedupler\Services;

use Illuminate\Support\Facades\Log;
use Maxkhim\Dedupler\Contracts\FileSourceInterface;
use Maxkhim\Dedupler\Contracts\FileStorageInterface;
use Maxkhim\Dedupler\FileSources\ContentAdapter;
use Maxkhim\Dedupler\FileSources\LocalFileAdapter;
use Maxkhim\Dedupler\FileSources\StreamAdapter;
use Maxkhim\Dedupler\FileSources\UploadedFileAdapter;
use Maxkhim\Dedupler\Models\UniqueFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maxkhim\Dedupler\Models\UniqueFileToModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeduplerService implements FileStorageInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('dedupler');
    }

    /**
     * Основной метод для хранения файла из любого источника
     */
    public function store(
        FileSourceInterface $fileSource,
        Model $model,
        array $options = []
    ): ?UniqueFileToModel {

        if (!$fileSource->isValid()) {
            Log::warning('Invalid file source provided');
            return null;
        }

        try {
            $disk = $options['disk'] ?? $this->config['default_disk'];
            $pivotAttributes = $options['pivot'] ?? [];

            // Используем потоковое чтение для больших файлов
            if ($this->config['optimization']['stream_upload'] && $fileSource->getSize() > 1024 * 1024) {
                return $this->storeWithStream($fileSource, $model, $disk, $pivotAttributes);
            }
            return $this->storeWithHash($fileSource, $model, $disk, $pivotAttributes);
        } catch (\Exception $e) {
            Log::error('File storage error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Удобный метод для UploadedFile
     */
    public function storeFromUploadedFile(
        UploadedFile $file,
        Model $model,
        array $options = []
    ): ?UniqueFileToModel {
        $fileSource = new UploadedFileAdapter($file);
        return $this->store($fileSource, $model, $options);
    }

    /**
     * Удобный метод для локальных файлов
     */
    public function storeFromPath(string $path, Model $model, array $options = []): ?UniqueFileToModel
    {
        $originalName = $options['original_name'] ?? basename($path);
        $fileSource = new LocalFileAdapter($path, $originalName);
        return $this->store($fileSource, $model, $options);
    }

    /**
     * Удобный метод для потоков
     */
    public function storeFromStream(
        $stream,
        string $filename,
        Model $model,
        array $options = []
    ): ?UniqueFileToModel {
        $mimeType = $options['mime_type'] ?? null;
        $size = $options['size'] ?? 0;

        $fileSource = new StreamAdapter($stream, $size, $filename, $mimeType);
        return $this->store($fileSource, $model, $options);
    }

    /**
     * Удобный метод для сырого контента
     */
    public function storeFromContent(
        string $content,
        string $filename,
        Model $model,
        array $options = []
    ): ?UniqueFileToModel {
        $mimeType = $options['mime_type'] ?? null;
        $fileSource = new ContentAdapter($content, $filename, $mimeType);
        return $this->store($fileSource, $model, $options);
    }

    /**
     * Пакетное сохранение файлов
     */
    public function storeBatch(array $fileSources, Model $model, array $options = []): array
    {
        $results = [];

        foreach ($fileSources as $key => $fileSource) {
            if ($fileSource instanceof UploadedFile) {
                $fileSource = new UploadedFileAdapter($fileSource);
            } elseif (is_string($fileSource) && file_exists($fileSource)) {
                $fileSource = new LocalFileAdapter($fileSource);
            }

            if ($fileSource instanceof FileSourceInterface) {
                $results[$key] = $this->store($fileSource, $model, $options);
            } else {
                $results[$key] = null;
                Log::warning("Invalid file source type for batch operation at key: {$key}");
            }
        }

        return $results;
    }

    protected function storeWithHash(
        FileSourceInterface $fileSource,
        Model $model,
        string $disk,
        array $pivotAttributes
    ): ?UniqueFileToModel {
        // Чтение файла и вычисление хэшей
        $content = $fileSource->getContent();
        $sha1Hash = sha1($content);
        $md5Hash = md5($content);

        // Проверка существования файла
        $existingFile = UniqueFile::query()
            ->find($sha1Hash);

        if ($existingFile) {
            return $this->attachExistingFile($existingFile, $model, array_merge([
                'original_name' => $fileSource->getOriginalName(),
                'status' => 'completed',
            ], $pivotAttributes));
        }

        // Сохранение нового файла
        $uploadedFile = $this->saveNewFile($fileSource, $sha1Hash, $md5Hash, $disk, $content);
        return $this->createFileModelLink($uploadedFile, $model, array_merge([
            'original_name' => $fileSource->getOriginalName(),
            'status' => 'completed',
        ], $pivotAttributes));
    }

    protected function storeWithStream(
        FileSourceInterface $fileSource,
        Model $model,
        string $disk,
        array $pivotAttributes
    ): ?UniqueFileToModel {
        $stream = $fileSource->getStream();

        try {
            // Вычисление хэша через поток
            $sha1Hash = hash_init('sha1');
            $md5Hash = hash_init('md5');

            while (!feof($stream)) {
                $chunk = fread($stream, $this->config['optimization']['chunk_size']);
                hash_update($sha1Hash, $chunk);
                hash_update($md5Hash, $chunk);
            }

            $sha1Hash = hash_final($sha1Hash);
            $md5Hash = hash_final($md5Hash);

            // Проверка существования файла
            $existingFile = UniqueFile::query()->find($sha1Hash);

            if ($existingFile) {
                fclose($stream);
                return $this->attachExistingFile($existingFile, $model, array_merge([
                    'original_name' => $fileSource->getOriginalName(),
                    'status' => 'completed',
                ], $pivotAttributes));
            }

            // Возвращаемся к началу потока для сохранения
            rewind($stream);

            // Сохранение через поток
            $uploadedFile = $this->saveNewFileFromStream($stream, $fileSource, $sha1Hash, $md5Hash, $disk);
            return $this->createFileModelLink($uploadedFile, $model, array_merge([
                'original_name' => $fileSource->getOriginalName(),
                'status' => 'completed',
            ], $pivotAttributes));
        } catch (\Exception $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw $e;
        }
    }

    protected function saveNewFile(
        FileSourceInterface $fileSource,
        string $sha1Hash,
        string $md5Hash,
        string $disk,
        string $content
    ): UniqueFile {
        $path = $this->generateFilePath($fileSource, $sha1Hash);
        $filename = $this->generateFilename($fileSource, $sha1Hash);

        // Сохранение через Flysystem
        Storage::disk($disk)->put($path, $content);

        // Создание записи
        return UniqueFile::create([
            'id' => $sha1Hash,
            'sha1_hash' => $sha1Hash,
            'md5_hash' => $md5Hash,
            'filename' => $filename,
            'path' => $path,
            'mime_type' => $fileSource->getMimeType(),
            'size' => $fileSource->getSize(),
            'status' => 'completed',
            'disk' => $disk,
            'original_name' => $fileSource->getOriginalName(),
        ]);
    }

    protected function saveNewFileFromStream(
        $stream,
        FileSourceInterface $fileSource,
        string $sha1Hash,
        string $md5Hash,
        string $disk
    ): UniqueFile {
        $path = $this->generateFilePath($fileSource, $sha1Hash);

        // Сохранение через Flysystem
        Storage::disk($disk)->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // Получение метаданных через Flysystem
        $size = Storage::disk($disk)->size($path);
        $mimeType = Storage::disk($disk)->mimeType($path) ?: $fileSource->getMimeType();

        // Создание записи
        return UniqueFile::create([
            'id' => $sha1Hash,
            'sha1_hash' => $sha1Hash,
            'md5_hash' => $md5Hash,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'status' => 'completed',
            'disk' => $disk,
            'original_name' => $fileSource->getOriginalName(),
        ]);
    }

    protected function generateFilePath(FileSourceInterface $fileSource, string $hash): string
    {
        $algorithm = $this->config['path_generator'] ?? 'hash_based';

        if ($algorithm === 'date_based') {
            return $this->generateDateBasedPath($fileSource, $hash);
        }

        return $this->generateHashBasedPath($fileSource, $hash);
    }

    protected function generateHashBasedPath(FileSourceInterface $fileSource, string $hash): string
    {
        $extension = pathinfo($fileSource->getOriginalName(), PATHINFO_EXTENSION);

        // Структура: первые 2 символа хэша / следующие 2 / полный хэш + расширение
        return substr($hash, 0, 2) . '/' .
            substr($hash, 2, 2) . '/' .
            $hash . ($extension ? '.' . $extension : '');
    }

    protected function generateDateBasedPath(FileSourceInterface $fileSource, string $hash): string
    {
        $extension = pathinfo($fileSource->getOriginalName(), PATHINFO_EXTENSION);
        $date = now()->format('Y/m/d');

        return $date . '/' . $hash . ($extension ? '.' . $extension : '');
    }

    protected function generateFilename(FileSourceInterface $fileSource, string $hash): string
    {
        $extension = pathinfo($fileSource->getOriginalName(), PATHINFO_EXTENSION);
        return $hash . ($extension ? '.' . $extension : '');
    }

    protected function attachExistingFile(
        UniqueFile $file,
        Model $model,
        array $pivotAttributes = []
    ): UniqueFileToModel {
        // Проверяем, нет ли уже такой связи
        $existingLink = UniqueFileToModel::query()
            ->where('sha1_hash', $file->id)
            ->where('deduplable_type', get_class($model))
            ->where('deduplable_id', $model->getKey())
            ->first();

        if ($existingLink) {
            // Обновляем атрибуты если нужно
            if (!empty($pivotAttributes)) {
                $existingLink->update($pivotAttributes);
            }
            return $existingLink;
        }

        // Создаем новую связь
        return UniqueFileToModel::create(array_merge([
            'sha1_hash' => $file->id,
            'deduplable_type' => get_class($model),
            'deduplable_id' => $model->getKey(),
            'status' => 'completed',
        ], $pivotAttributes));
    }

    protected function createFileModelLink(
        UniqueFile $file,
        Model $model,
        array $pivotAttributes = []
    ): UniqueFileToModel {
        return UniqueFileToModel::create(array_merge([
            'sha1_hash' => $file->id,
            'deduplable_type' => get_class($model),
            'deduplable_id' => $model->getKey(),
            'status' => 'completed',
        ], $pivotAttributes));
    }

    public function attach(string $fileHash, Model $model, array $pivotAttributes = []): ?UniqueFileToModel
    {
        $file = UniqueFile::query()->find($fileHash);

        if (!$file) {
            return null;
        }

        return $this->attachExistingFile($file, $model, array_merge([
            'status' => 'completed',
        ], $pivotAttributes));
    }

    public function detach(string $fileHash, Model $model): bool
    {
        $deleted = UniqueFileToModel::query()->where('sha1_hash', $fileHash)
            ->where('deduplable_type', get_class($model))
            ->where('deduplable_id', $model->getKey())
            ->delete();

        // Проверяем, остались ли другие связи с этим файлом
        $remainingRelations = UniqueFileToModel::query()->where('sha1_hash', $fileHash)->count();

        // Если связей больше нет, удаляем файл
        if ($remainingRelations === 0) {
            $this->deleteFileRecord($fileHash);
        }

        return $deleted > 0;
    }

    public function delete(string $fileHash, bool $force = false): bool
    {
        $file = UniqueFile::query()->find($fileHash);

        if (!$file) {
            return false;
        }

        // Получаем все связи
        $relations = UniqueFileToModel::query()->where('sha1_hash', $fileHash)->get();

        // Если force = true или нет активных связей
        if ($force || $relations->isEmpty()) {
            // Удаляем физический файл
            Storage::disk($file->disk)->delete($file->path);

            // Удаляем все связи
            UniqueFileToModel::query()->where('sha1_hash', $fileHash)->delete();

            // Удаляем запись о файле
            return $file->delete();
        }

        return false;
    }

    protected function deleteFileRecord(string $fileHash): bool
    {
        $file = UniqueFile::query()->find($fileHash);

        if ($file) {
            // Удаляем физический файл
            Storage::disk($file->disk)->delete($file->path);
            return $file->delete();
        }

        return false;
    }

    public function updatePivotStatus(string $fileHash, Model $model, string $status): bool
    {
        return UniqueFileToModel::query()->where('sha1_hash', $fileHash)
            ->where('deduplable_type', get_class($model))
            ->where('deduplable_id', $model->getKey())
            ->update(['status' => $status]);
    }

    public function getFileRelations(string $fileHash)
    {
        return UniqueFileToModel::query()->where('sha1_hash', $fileHash)
            ->with('deduplable')
            ->get();
    }

    public function getUrl(string $fileHash): ?string
    {
        $file = UniqueFile::query()->find($fileHash);

        if (!$file) {
            return null;
        }

        try {
            return Storage::disk($file->disk)->url($file->path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTemporaryUrl(string $fileHash, \DateTimeInterface $expiration): ?string
    {
        $file = UniqueFile::query()->find($fileHash);

        if (!$file) {
            return null;
        }

        try {
            return Storage::disk($file->disk)->temporaryUrl(
                $file->path,
                $expiration
            );
        } catch (\Exception $e) {
            // Если временные URL не поддерживаются, возвращаем обычный URL
            return $this->getUrl($fileHash);
        }
    }

    public function streamDownload(string $fileHash): ?StreamedResponse
    {
        $file = UniqueFile::query()->find($fileHash);

        if (!$file) {
            return null;
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name ?: $file->filename
        );
    }

    public function exists(string $fileHash): bool
    {
        return UniqueFile::query()->where('id', $fileHash)->exists();
    }
}
