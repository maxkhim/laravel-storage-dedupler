<?php

namespace Maxkhim\Dedupler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UniqueUploadedFile extends Model
{
    protected $table = 'unique_uploaded_files';

    protected $keyType = 'string';
    public $incrementing = false;
    protected $connection = 'dedupler';
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

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Связь с полиморфной таблицей
     */
    public function uploadableModels(): HasMany
    {
        return $this->hasMany(UniqueUploadedFileToModel::class, 'sha1_hash', 'id');
    }

    /**
     * Получить все связанные модели через полиморфную связь
     */
    public function getUploadableModels()
    {
        return $this->uploadableModels->map(function ($relation) {
            return $relation->uploadable;
        });
    }
}
