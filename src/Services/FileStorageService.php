<?php

namespace Maxkhim\UniqueFileStorage\Services;

use Maxkhim\UniqueFileStorage\Contracts\FileStorageInterface;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService implements FileStorageInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('unique-file-storage');
    }

    public function store(UploadedFile $file, Model $model, string $disk = null): ?UniqueUploadedFile
    {
        try {
            $disk = $disk ?: $this->config['default_disk'];

            // Оптимизация: используем потоковое чтение для больших файлов
            if ($this->config['optimization']['stream_upload'] && $file->getSize() > 1024 * 1024) {
                return $this->storeWithStream($file, $model, $disk);
            }

            return $this->storeWithHash($file, $model, $disk);

        } catch (\Exception $e) {
            \Log::error('File storage error: ' . $e->getMessage());
            return null;
        }
    }

    protected function storeWithHash(UploadedFile $file, Model $model, string $disk): ?UniqueUploadedFile
    {
        // Чтение файла и вычисление хэшей
        $content = $file->get();
        $sha1Hash = sha1($content);
        $md5Hash = md5($content);

        // Проверка существования файла
        $existingFile = UniqueUploadedFile::where('sha1_hash', $sha1Hash)->first();

        if ($existingFile) {
            return $this->attachExistingFile($existingFile, $model);
        }

        // Сохранение нового файла
        return $this->saveNewFile($file, $sha1Hash, $md5Hash, $model, $disk, $content);
    }

    protected function storeWithStream(UploadedFile $file, Model $model, string $disk): ?UniqueUploadedFile
    {
        // Открываем поток для вычисления хэша и сохранения
        $stream = fopen($file->getPathname(), 'r');

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

        // Возвращаемся к началу потока для сохранения
        rewind($stream);

        // Проверка существования файла
        $existingFile = UniqueUploadedFile::where('sha1_hash', $sha1Hash)->first();

        if ($existingFile) {
            fclose($stream);
            return $this->attachExistingFile($existingFile, $model);
        }

        // Сохранение через поток
        return $this->saveNewFileFromStream($stream, $file, $sha1Hash, $md5Hash, $model, $disk);
    }

    public function storeFromStream($stream, string $filename, Model $model, string $disk = null): ?UniqueUploadedFile
    {
        $disk = $disk ?: $this->config['default_disk'];

        try {
            // Вычисление хэша из потока
            $hashContext = hash_init('sha1');
            $md5Context = hash_init('md5');

            $tempStream = fopen('php://temp', 'w+b');
            rewind($stream);

            while (!feof($stream)) {
                $chunk = fread($stream, $this->config['optimization']['chunk_size']);
                hash_update($hashContext, $chunk);
                hash_update($md5Context, $chunk);
                fwrite($tempStream, $chunk);
            }

            $sha1Hash = hash_final($hashContext);
            $md5Hash = hash_final($md5Context);

            rewind($tempStream);

            // Проверка существования
            $existingFile = UniqueUploadedFile::where('sha1_hash', $sha1Hash)->first();

            if ($existingFile) {
                fclose($tempStream);
                return $this->attachExistingFile($existingFile, $model);
            }

            // Сохранение
            $path = $this->generateFilePathFromHash($sha1Hash, $filename);
            $storagePath = Storage::disk($disk)->put($path, $tempStream);

            fclose($tempStream);

            // Создание записи
            $uploadedFile = UniqueUploadedFile::create([
                'id' => $sha1Hash,
                'sha1_hash' => $sha1Hash,
                'md5_hash' => $md5Hash,
                'filename' => basename($path),
                'path' => $path,
                'mime_type' => Storage::disk($disk)->mimeType($path),
                'size' => Storage::disk($disk)->size($path),
                'status' => 'completed',
                'disk' => $disk,
                'original_name' => $filename,
            ]);

            $uploadedFile->uniqueFileStorage()->save($model);

            return $uploadedFile;

        } catch (\Exception $e) {
            \Log::error('Stream storage error: ' . $e->getMessage());
            return null;
        }
    }

    protected function saveNewFileFromStream(
        $stream,
        UploadedFile $file,
        string $sha1Hash,
        string $md5Hash,
        Model $model,
        string $disk
    ): UniqueUploadedFile {
        $path = $this->generateFilePath($file);

        // Сохранение через Flysystem
        Storage::disk($disk)->put($path, $stream);
        fclose($stream);

        // Получение метаданных через Flysystem
        $size = Storage::disk($disk)->size($path);
        $mimeType = Storage::disk($disk)->mimeType($path);

        // Создание записи
        $uploadedFile = UniqueUploadedFile::create([
            'id' => $sha1Hash,
            'sha1_hash' => $sha1Hash,
            'md5_hash' => $md5Hash,
            'filename' => basename($path),
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'status' => 'completed',
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
        ]);

        $uploadedFile->uniqueFileStorage()->save($model);

        return $uploadedFile;
    }

    protected function generateFilePath(UploadedFile $file): string
    {
        $algorithm = $this->config['path_generator'] ?? 'hash_based';

        if ($algorithm === 'date_based') {
            return $this->generateDateBasedPath($file);
        }

        return $this->generateHashBasedPath($file);
    }

    protected function generateHashBasedPath(UploadedFile $file): string
    {
        $hash = sha1_file($file->getPathname());
        $extension = $file->getClientOriginalExtension();

        // Структура: первые 2 символа хэша / следующие 2 / полный хэш + расширение
        return substr($hash, 0, 2) . '/' .
            substr($hash, 2, 2) . '/' .
            $hash . ($extension ? '.' . $extension : '');
    }

    protected function generateDateBasedPath(UploadedFile $file): string
    {
        $hash = sha1_file($file->getPathname());
        $extension = $file->getClientOriginalExtension();
        $date = now()->format('Y/m/d');

        return $date . '/' . $hash . ($extension ? '.' . $extension : '');
    }

    protected function generateFilePathFromHash(string $hash, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return substr($hash, 0, 2) . '/' .
            substr($hash, 2, 2) . '/' .
            $hash . ($extension ? '.' . $extension : '');
    }

    public function getUrl(string $fileHash): ?string
    {
        $file = UniqueUploadedFile::find($fileHash);

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
        $file = UniqueUploadedFile::find($fileHash);

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
        $file = UniqueUploadedFile::find($fileHash);

        if (!$file) {
            return null;
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name ?: $file->filename
        );
    }



    protected function attachExistingFile(UniqueUploadedFile $file, Model $model): UniqueUploadedFile
    {
        // Проверяем, не привязан ли уже файл к этой модели
        if (!$file->uniqueFileStorage()->where('id', $model->getKey())->exists()) {
            $file->uniqueFileStorage()->save($model);
        }

        return $file;
    }

    protected function saveNewFile(
        UploadedFile $file,
        string $sha1Hash,
        string $md5Hash,
        Model $model,
        string $disk,
        string $content
    ): UniqueUploadedFile {
        // Генерация пути и имени файла
        $path = $this->generateFilePath($file);
        $filename = $this->generateFilename($file);

        // Сохранение файла на диск
        Storage::disk($disk)->put($path, $content);

        // Создание записи в БД
        $uploadedFile = UniqueUploadedFile::create([
            'id' => $sha1Hash,
            'sha1_hash' => $sha1Hash,
            'md5_hash' => $md5Hash,
            'filename' => $filename,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'completed',
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
        ]);

        // Привязка к модели
        $uploadedFile->uniqueFileStorage()->save($model);

        return $uploadedFile;
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::random(40) . ($extension ? '.' . $extension : '');
    }

    public function attach(string $fileHash, Model $model): ?UniqueUploadedFile
    {
        $file = UniqueUploadedFile::find($fileHash);

        if ($file && !$file->uniqueFileStorage()->where('id', $model->getKey())->exists()) {
            $file->uniqueFileStorage()->save($model);
            return $file;
        }

        return $file;
    }

    public function delete(string $fileHash, bool $force = false): bool
    {
        $file = UniqueUploadedFile::find($fileHash);

        if (!$file) {
            return false;
        }

        // Если force = true или файл не используется другими моделями
        if ($force || $file->uniqueFileStorage()->count() <= 1) {
            // Удаляем физический файл
            Storage::disk($file->disk)->delete($file->path);
            // Удаляем запись из БД
            return $file->delete();
        }

        // Просто отвязываем от текущей модели
        return true;
    }

    public function exists(string $fileHash): bool
    {
        return UniqueUploadedFile::where('id', $fileHash)->exists();
    }
}