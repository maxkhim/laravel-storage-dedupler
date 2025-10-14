<?php

namespace Maxkhim\UniqueFileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniqueUploadedFileToModel extends Model
{
    protected $table = 'unique_uploaded_files_to_models';
    protected $connection = 'unique_file_storage';
    protected $fillable = [
        'sha1_hash',
        'uploadable_type',
        'uploadable_id',
        'status',
        'original_name',
    ];

    protected $casts = [
        'uploadable_id' => 'string',
    ];

    /**
     * Полиморфная связь с моделями
     */
    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Связь с файлом
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(UniqueUploadedFile::class, 'sha1_hash', 'id');
    }

    /**
     * Scope для фильтрации по типу модели
     */
    public function scopeForModelType($query, $modelType)
    {
        return $query->where('uploadable_type', $modelType);
    }

    /**
     * Scope для фильтрации по статусу
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
