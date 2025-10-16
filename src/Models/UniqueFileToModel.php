<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniqueFileToModel extends Model
{
    protected $table = 'dedupler_unique_files_to_models';
    protected $connection = 'dedupler';
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
}
