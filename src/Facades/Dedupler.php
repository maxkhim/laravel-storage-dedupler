<?php

namespace Maxkhim\Dedupler\Facades;

use Illuminate\Support\Facades\Facade;
use Maxkhim\Dedupler\Models\UniqueFileToModel;
use Maxkhim\Dedupler\Services\DeduplerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 *
 * Фасад для работы с файлами в модуле dedupler.
 *
 * Предоставляет удобный способ доступа к функциям FileStorageInterface через статические вызовы.
 * @see DeduplerService
 * @method static UniqueFileToModel|null store(FileSourceInterface $fileSource, Model $model, array $options = [])
 * @method static UniqueFileToModel|null storeFromUploadedFile (UploadedFile $file, Model $model, array $options = [])
 * @method static UniqueFileToModel|null storeFromPath(string $path, Model $model, array $options = [])
 * @method static UniqueFileToModel|null storeFromStream($stream, string $filename, Model $model, array $options = [])
 * @method static UniqueFileToModel|null storeFromContent(string $content, string $filename, Model $model, array $options = [])
 * @method static UniqueFileToModel|null attach(string $fileHash, Model $model, array $pivotAttributes = [])
 * @method static bool detach(string $fileHash, Model $model)
 * @method static bool delete(string $fileHash, bool $force = false)
 * @method static bool exists(string $fileHash)
 * @method static string|null getUrl(string $fileHash)
 * @method static string|null getTemporaryUrl(string $fileHash, DateTimeInterface $expiration)
 * @method static StreamedResponse|null streamDownload(string $fileHash)
 * @method static bool updatePivotStatus(string $fileHash, Model $model, string $status)
 * @method static mixed getFileRelations(string $fileHash)
 * @method static array storeBatch(array $fileSources, Model $model, array $options = [])
 *
 * */

class Dedupler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dedupler';
    }
}
