<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель, представляющая уникальный файл в системе дедупликации.
 *
 * Хранит метаданные о загруженном файле (хэши, MIME-тип, размер и т.д.)
 * и обеспечивает связь между файлом и несколькими моделями через полиморфную связь.
 *
 * @table dedupler_unique_files
 * @property string $id SHA1 хэш файла (первичный ключ)
 * @property string $sha1_hash SHA1 хэш файла
 * @property string $md5_hash MD5 хэш файла
 * @property string $filename Имя файла
 * @property string $path Путь к файлу
 * @property string $mime_type MIME-тип файла
 * @property int $size Размер файла в байтах
 * @property string $status Статус файла (например: 'pending','processing','completed','failed')
 * @property string $disk Диск, на котором хранится файл
 * @property string $original_name Оригинальное имя файла
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deduplicatable> $deduplableModels
 *
 * @package dedupler
 */
class UniqueFile extends Model
{
    use HasFactory;

    protected $table = 'dedupler_unique_files';
    protected $keyType = 'string';
    public $incrementing = false;
    //protected $connection = 'dedupler';
    protected $fillable = [
        'id', // SHA1 хэш как первичный ключ
        'sha1_hash',
        'md5_hash',
        'filename',
        'path',
        'mime_type',
        'size',
        'status',
        'disk',
        'original_name'
    ];

    public const STATUS_PENDING = 'pending';       // Ожидает обработки
    public const STATUS_PROCESSING = 'processing'; // В процессе обработки
    public const STATUS_COMPLETED = 'completed';   // Обработка завершена
    public const STATUS_FAILED = 'failed';         // Ошибка при обработке

    protected $casts = [
        'size' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        if (!isset($this->connection)) {
            $this->setConnection(config('dedupler.db_connection'));
        }

        parent::__construct($attributes);
    }

    /**
     * Связь с полиморфной таблицей
     */
    public function deduplableModels(): HasMany
    {
        return $this->hasMany(
            Deduplicatable::class,
            'sha1_hash',
            'id'
        );
    }

    /**
     * Получить все связанные модели через полиморфную связь
     */
    public function getDeduplableModels(): Collection|\Illuminate\Support\Collection
    {
        return $this->deduplableModels->map(function ($relation) {
            return $relation->deduplable;
        });
    }

    public function getLegacyMigratedFiles(): HasMany
    {
        return $this->hasMany(
            LegacyFileMigration::class,
            'sha1_hash',
            'id'
        );
    }
}
