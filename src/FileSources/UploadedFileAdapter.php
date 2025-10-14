<?php

namespace Maxkhim\UniqueFileStorage\FileSources;

use Illuminate\Http\UploadedFile;
use Maxkhim\UniqueFileStorage\Contracts\FileSourceInterface;

class UploadedFileAdapter implements FileSourceInterface
{
    protected $uploadedFile;

    public function __construct(UploadedFile $uploadedFile)
    {
        $this->uploadedFile = $uploadedFile;
    }

    public function getContent(): string
    {
        return $this->uploadedFile->get();
    }

    public function getStream()
    {
        return fopen($this->uploadedFile->getPathname(), 'r');
    }

    public function getSize(): int
    {
        return $this->uploadedFile->getSize();
    }

    public function getMimeType(): ?string
    {
        return $this->uploadedFile->getClientMimeType();
    }

    public function getOriginalName(): ?string
    {
        return $this->uploadedFile->getClientOriginalName();
    }

    public function getPathname(): ?string
    {
        return $this->uploadedFile->getPathname();
    }

    public function isValid(): bool
    {
        return $this->uploadedFile->isValid();
    }
}