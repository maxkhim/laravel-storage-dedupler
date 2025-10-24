<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Commands\Traits\CommonMigrationTrait;
use Maxkhim\Dedupler\Factories\LegacyFileMigrationFactory;
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
 * @property string $status Статус миграции ('pending','migrated','error','skipped')
 * @property string $migration_strategy Стратегия миграции (copy/move/link)
 * @property int $migration_batch Номер пакета миграции
 * @property string $error_message Сообщение об ошибке при миграции
 * @property bool $has_duplicates Флаг наличия дубликатов
 * @property Carbon $migrated_at Дата миграции
 * @property UniqueFile $uniqueFile Дедуплицированный файл
 *
 * @see UniqueFile Связанная модель уникальных файлов
 * @see Deduplable Трейт для работы с дедупликацией
 */

class LegacyFileMigration extends Model
{
    use HasFactory;
    use Deduplable;
    use CommonMigrationTrait;

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
        'file_modificated_at',
    ];


    protected $casts = [
        'file_modificated_at' => 'datetime',
    ];
    // Константы статусов
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_MIGRATED = 'migrated';
    public const STATUS_ERROR = 'error';


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

    public function copyLegacyFileToUniqueFile(): UniqueFile
    {
        $existingFile = UniqueFile::query()->find($this->sha1_hash);
        if ($existingFile) {
            $this->has_duplicates = true;
            $this->status = self::STATUS_PROCESSING;
            $this->attachUniqueFile($this->sha1_hash);
            LegacyFileMigration::query()
                ->where("sha1_hash", "=", $this->sha1_hash)
                ->update(["has_duplicates" => true]);
        } else {
            $existingFile = $this->storeLocalFile($this->original_path . "/" . $this->original_filename);
            if (!$existingFile) {
                $this->status = LegacyFileMigration::STATUS_ERROR;
            } else {
                $this->status = self::STATUS_PROCESSING;
            }
        }
        $this->save();
        return $existingFile;
    }

    public function replaceLegacyFileWithSymlinkToUniqueFile(): bool
    {
        if (!$this->uniqueFile) {
            return false;
        }

        $storageFile = $this->uniqueFile;
        $originalPath = $this->original_path . "/" . $this->original_filename;

        $result = false;
        try {
            $storagePath = Storage::disk($storageFile->disk)->path($storageFile->path);
            if (is_link($originalPath)) {
                throw new \Exception("Is already symlink: " . $originalPath);
            }

            if (!is_file($storagePath)) {
                throw new \Exception("File doesn't exists: " . $storagePath);
            }

            $unlinkResult = unlink($originalPath);
            $symlinkResult = symlink($storagePath, $originalPath);
            $result = $unlinkResult && $symlinkResult;

            if (!$result) {
                throw new \Exception("Could not replace legacy file");
            } else {
                $this->status = LegacyFileMigration::STATUS_MIGRATED;
                $this->migrated_at = Carbon::now();
            }
        } catch (\Throwable $e) {
            $result = false;
            $this->status = LegacyFileMigration::STATUS_ERROR;
            $this->error_message = $e->getMessage();
        }
        $this->save();
        return $result;
    }

    protected static function newFactory(): LegacyFileMigrationFactory
    {
        return LegacyFileMigrationFactory::new();
    }
}
