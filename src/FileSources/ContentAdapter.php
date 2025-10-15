<?php

namespace Maxkhim\Dedupler\FileSources;

use Maxkhim\Dedupler\Contracts\FileSourceInterface;
class ContentAdapter implements FileSourceInterface
{
    protected $content;
    protected $originalName;
    protected $mimeType;

    public function __construct(string $content, string $originalName, string $mimeType = null)
    {
        $this->content = $content;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStream()
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->content);
        rewind($stream);
        return $stream;
    }

    public function getSize(): int
    {
        return strlen($this->content);
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function getPathname(): ?string
    {
        return null;
    }

    public function isValid(): bool
    {
        return !empty($this->content);
    }
}