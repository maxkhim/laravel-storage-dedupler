<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель, представляющая связь между уникальным файлом и другой моделью через полиморфную связь.
 *
 * Используется для отслеживания, какие модели (например, пользователи, продукты) связаны с конкретным файлом,
 * а также для управления статусом этой связи (активна/удалена).
 *
 * @package Maxkhim\Dedupler\Models
 * @table dedupler_deduplicatables
 * @property string $sha1_hash Хэш файла (связь с таблицей `UniqueFile`)
 * @property string $deduplable_type Тип связанной модели (например, App\Models\User)
 * @property string $deduplable_id ID связанной модели
 * @property string $status Статус связи ( 'pending', 'processing', 'completed', 'failed')
 * @property string $original_name Оригинальное имя файла
 * @property-read UniqueFile $file Связанный файл
 * @property-read mixed $deduplable Связанная модель (полиморфная)
 */

class Deduplicatable extends Model
{
    protected $table = 'dedupler_deduplicatables';

    protected $fillable = [
        'sha1_hash',
        'deduplable_type',
        'deduplable_id',
        'status',
        'original_name',
    ];

    protected $casts = [
        'deduplable_id' => 'string',
    ];

    /**
     * Константы статусов для связи файла и модели
     */
    public const STATUS_PENDING = 'pending';       // Ожидает обработки
    public const STATUS_PROCESSING = 'processing'; // В процессе обработки
    public const STATUS_COMPLETED = 'completed';   // Обработка завершена
    public const STATUS_FAILED = 'failed';         // Ошибка при обработке

    public const STATUSES_LABELS = [
        self::STATUS_PENDING       => 'Waiting for processing',
        self::STATUS_PROCESSING    => 'In progress',
        self::STATUS_COMPLETED     => 'Processing completed',
        self::STATUS_FAILED        => 'Processing failed',
    ];

    public function __construct(array $attributes = [])
    {
        if (!isset($this->connection)) {
            $this->setConnection(config('dedupler.db_connection'));
        }
        parent::__construct($attributes);
    }

    /**
     * Полиморфная связь с моделями
     */
    public function deduplable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Связь с файлом
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(UniqueFile::class, 'sha1_hash', 'id');
    }

    /**
     * Scope для фильтрации по типу модели
     */
    public function scopeForModelType($query, $modelType)
    {
        return $query->where('deduplable_type', $modelType);
    }

    /**
     * Scope для фильтрации по статусу
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Возвращает ассоциативный массив: статус человеко понятное описание (на английском)
     *
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return self::STATUSES_LABELS;
    }
}
