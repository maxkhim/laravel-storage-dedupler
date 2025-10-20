<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Maxkhim\Dedupler\Traits\Deduplable;

/**
 * Модель миграции файлов из legacy-системы
 *
 * Управляет процессом миграции файлов, включая обработку дубликатов,
 * выбор стратегии миграции и отслеживание статуса выполнения.
 *
 * @package Maxkhim\Dedupler\Models
 *
 * @property string $original_path Путь к оригинальному файлу
 * @property string $original_filename Имя оригинального файла
 * @property string $sha1_hash Хэш файла (SHA1)
 * @property int $file_size Размер файла в байтах
 * @property string $mime_type MIME-тип файла
 * @property string $migration_strategy Стратегия миграции (copy/move/link)
 * @property int $migration_batch Номер пакета миграции
 * @property string $error_message Сообщение об ошибке при миграции
 * @property bool $has_duplicates Флаг наличия дубликатов
 *
 * @see UniqueFile Связанная модель уникальных файлов
 * @see Deduplable Трейт для работы с дедупликацией
 */

class LegacyFileMigration extends Model
{
    use HasFactory;
    use Deduplable;

    protected $table = 'dedupler_legacy_file_migrations';

    protected $fillable = [
        'original_path',
        'original_filename',
        'sha1_hash',
        'file_size',
        'mime_type',
        'status',
        'migration_strategy',
        'migration_batch',
        'migrated_at',
        'error_message',
        'has_duplicates',
        'file_modification_time',
    ];

    protected $casts = [
        'file_modification_time' => 'datetime',
    ];
    // Константы статусов
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_MIGRATED = 'migrated';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_ERROR = 'error';
    public const STATUS_SKIPPED = 'skipped';

    // Константы стратегий
    public const STRATEGY_COPY = 'copy';
    public const STRATEGY_MOVE = 'move';
    public const STRATEGY_LINK = 'link';

    public function __construct(array $attributes = [])
    {
        if (!isset($this->connection)) {
            $this->setConnection(config('dedupler.db_connection'));
        }
        parent::__construct($attributes);
    }

    public function uniqueFile(): BelongsTo
    {
        return $this->belongsTo(UniqueFile::class, "sha1_hash", "id");
    }

    public static function findLegacyFileMigrationByOriginalPath(
        string $originalPath,
        string $originalName
    ): ?LegacyFileMigration {
        return LegacyFileMigration::query()
            ->where("original_path", $originalPath)
            ->where("original_filename", $originalName)
            ->first();
    }

    /**
     * Scope для быстрой фильтрации
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeMigrated($query)
    {
        return $query->where('status', self::STATUS_MIGRATED);
    }

    public function scopeDuplicates($query)
    {
        return $query->where('status', self::STATUS_DUPLICATE);
    }

    /**
     * Хелпер-методы для проверки статусов
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isMigrated(): bool
    {
        return $this->status === self::STATUS_MIGRATED;
    }

    public function hasDuplicates(): bool
    {
        return $this->has_duplicates;
    }

    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
