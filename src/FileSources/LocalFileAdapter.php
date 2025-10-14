<?php

namespace Maxkhim\UniqueFileStorage\FileSources;

use Maxkhim\UniqueFileStorage\Contracts\FileSourceInterface;
use Illuminate\Support\Facades\File;
use finfo;
class LocalFileAdapter implements FileSourceInterface
{
    protected $path;
    protected $originalName;

    public function __construct(string $path, string $originalName = null)
    {
        $this->path = $path;
        $this->originalName = $originalName ?: basename($path);
    }

    public function getContent(): string
    {
        return File::get($this->path);
    }

    public function getStream()
    {
        return fopen($this->path, 'r');
    }

    public function getSize(): int
    {
        return File::size($this->path);
    }

    public function getMimeType(): ?string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($this->path) ?: null;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function getPathname(): ?string
    {
        return $this->path;
    }

    public function isValid(): bool
    {
        return File::exists($this->path) && File::isFile($this->path);
    }
}