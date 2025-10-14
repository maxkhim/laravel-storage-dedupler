<?php

namespace Maxkhim\UniqueFileStorage\Services;

use Illuminate\Support\Facades\Log;
use Maxkhim\UniqueFileStorage\Contracts\FileSourceInterface;
use Maxkhim\UniqueFileStorage\Contracts\FileStorageInterface;
use Maxkhim\UniqueFileStorage\FileSources\ContentAdapter;
use Maxkhim\UniqueFileStorage\FileSources\LocalFileAdapter;
use Maxkhim\UniqueFileStorage\FileSources\StreamAdapter;
use Maxkhim\UniqueFileStorage\FileSources\UploadedFileAdapter;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFileToModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService implements FileStorageInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('unique-file-storage');
    }

    /**
     * Основной метод для хранения файла из любого источника
     */
    public function store(
        FileSourceInterface $fileSource,
        Model $model,
        array $options = []
    ): ?UniqueUploadedFileToModel {

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
    ): ?UniqueUploadedFileToModel {
        $fileSource = new UploadedFileAdapter($file);
        return $this->store($fileSource, $model, $options);
    }

    /**
     * Удобный метод для локальных файлов
     */
    public function storeFromPath(string $path, Model $model, array $options = []): ?UniqueUploadedFileToModel
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
    ): ?UniqueUploadedFileToModel {
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
    ): ?UniqueUploadedFileToModel {
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
    ): ?UniqueUploadedFileToModel {
        // Чтение файла и вычисление хэшей
        $content = $fileSource->getContent();
        $sha1Hash = sha1($content);
        $md5Hash = md5($content);

        // Проверка существования файла
        $existingFile = UniqueUploadedFile::query()
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
    ): ?UniqueUploadedFileToModel {
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
            $existingFile = UniqueUploadedFile::query()->find($sha1Hash);

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
    ): UniqueUploadedFile {
        $path = $this->generateFilePath($fileSource, $sha1Hash);
        $filename = $this->generateFilename($fileSource, $sha1Hash);

        // Сохранение через Flysystem
        Storage::disk($disk)->put($path, $content);

        // Создание записи
        return UniqueUploadedFile::create([
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
    ): UniqueUploadedFile {
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
        return UniqueUploadedFile::create([
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
        UniqueUploadedFile $file,
        Model $model,
        array $pivotAttributes = []
    ): UniqueUploadedFileToModel {
        // Проверяем, нет ли уже такой связи
        $existingLink = UniqueUploadedFileToModel::where('sha1_hash', $file->id)
            ->where('uploadable_type', get_class($model))
            ->where('uploadable_id', $model->getKey())
            ->first();

        if ($existingLink) {
            // Обновляем атрибуты если нужно
            if (!empty($pivotAttributes)) {
                $existingLink->update($pivotAttributes);
            }
            return $existingLink;
        }

        // Создаем новую связь
        return UniqueUploadedFileToModel::create(array_merge([
            'sha1_hash' => $file->id,
            'uploadable_type' => get_class($model),
            'uploadable_id' => $model->getKey(),
            'status' => 'completed',
        ], $pivotAttributes));
    }

    protected function createFileModelLink(
        UniqueUploadedFile $file,
        Model $model,
        array $pivotAttributes = []
    ): UniqueUploadedFileToModel {
        return UniqueUploadedFileToModel::create(array_merge([
            'sha1_hash' => $file->id,
            'uploadable_type' => get_class($model),
            'uploadable_id' => $model->getKey(),
            'status' => 'completed',
        ], $pivotAttributes));
    }

    public function attach(string $fileHash, Model $model, array $pivotAttributes = []): ?UniqueUploadedFileToModel
    {
        $file = UniqueUploadedFile::query()->find($fileHash);

        if (!$file) {
            return null;
        }

        return $this->attachExistingFile($file, $model, array_merge([
            'status' => 'completed',
        ], $pivotAttributes));
    }

    public function detach(string $fileHash, Model $model): bool
    {
        $deleted = UniqueUploadedFileToModel::where('sha1_hash', $fileHash)
            ->where('uploadable_type', get_class($model))
            ->where('uploadable_id', $model->getKey())
            ->delete();

        // Проверяем, остались ли другие связи с этим файлом
        $remainingRelations = UniqueUploadedFileToModel::where('sha1_hash', $fileHash)->count();

        // Если связей больше нет, удаляем файл
        if ($remainingRelations === 0) {
            $this->deleteFileRecord($fileHash);
        }

        return $deleted > 0;
    }

    public function delete(string $fileHash, bool $force = false): bool
    {
        $file = UniqueUploadedFile::query()->find($fileHash);

        if (!$file) {
            return false;
        }

        // Получаем все связи
        $relations = UniqueUploadedFileToModel::where('sha1_hash', $fileHash)->get();

        // Если force = true или нет активных связей
        if ($force || $relations->isEmpty()) {
            // Удаляем физический файл
            Storage::disk($file->disk)->delete($file->path);

            // Удаляем все связи
            UniqueUploadedFileToModel::where('sha1_hash', $fileHash)->delete();

            // Удаляем запись о файле
            return $file->delete();
        }

        return false;
    }

    protected function deleteFileRecord(string $fileHash): bool
    {
        $file = UniqueUploadedFile::query()->find($fileHash);

        if ($file) {
            // Удаляем физический файл
            Storage::disk($file->disk)->delete($file->path);
            return $file->delete();
        }

        return false;
    }

    public function updatePivotStatus(string $fileHash, Model $model, string $status): bool
    {
        return UniqueUploadedFileToModel::where('sha1_hash', $fileHash)
            ->where('uploadable_type', get_class($model))
            ->where('uploadable_id', $model->getKey())
            ->update(['status' => $status]);
    }

    public function getFileRelations(string $fileHash)
    {
        return UniqueUploadedFileToModel::where('sha1_hash', $fileHash)
            ->with('uploadable')
            ->get();
    }

    public function getUrl(string $fileHash): ?string
    {
        $file = UniqueUploadedFile::query()->find($fileHash);

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
        $file = UniqueUploadedFile::query()->find($fileHash);

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
        $file = UniqueUploadedFile::query()->find($fileHash);

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
        return UniqueUploadedFile::query()->where('id', $fileHash)->exists();
    }
}
