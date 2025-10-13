<?php

namespace Maxkhim\UniqueFileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UniqueUploadedFile extends Model
{
    protected $table = 'unique_uploaded_files';
    protected $keyType = 'string';
    public $incrementing = false;
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

    public function uniqueFileStorage(): MorphTo
    {
        return $this->morphTo('unique_file_storage');
    }
}