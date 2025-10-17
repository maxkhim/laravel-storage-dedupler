<?php

namespace Maxkhim\Dedupler\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Maxkhim\Dedupler\Models\Deduplicatable;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface FileStorageInterface
{
    // Основной метод для любого источника файлов
    public function store(
        FileSourceInterface $fileSource,
        Model $model,
        array $options = []
    ): ?Deduplicatable;

    // Удобные методы для различных источников
    public function storeFromUploadedFile(
        UploadedFile $file,
        Model $model,
        array $options = []
    ): ?Deduplicatable;
    public function storeFromPath(string $path, Model $model, array $options = []): ?Deduplicatable;
    public function storeFromStream(
        $stream,
        string $filename,
        Model $model,
        array $options = []
    ): ?Deduplicatable;
    public function storeFromContent(
        string $content,
        string $filename,
        Model $model,
        array $options = []
    ): ?Deduplicatable;

    // Методы для работы с существующими файлами
    public function attach(string $fileHash, Model $model, array $pivotAttributes = []): ?Deduplicatable;
    public function detach(string $fileHash, Model $model): bool;
    public function delete(string $fileHash, bool $force = false): bool;
    public function exists(string $fileHash): bool;

    // Методы для получения файлов
    public function getUrl(string $fileHash): ?string;
    public function getTemporaryUrl(string $fileHash, \DateTimeInterface $expiration): ?string;
    public function streamDownload(string $fileHash): ?StreamedResponse;

    // Методы для работы с полиморфной таблицей
    public function updatePivotStatus(string $fileHash, Model $model, string $status): bool;
    public function getFileRelations(string $fileHash);

    // Пакетные операции
    public function storeBatch(array $fileSources, Model $model, array $options = []): array;
}
