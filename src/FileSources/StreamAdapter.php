<?php

namespace Maxkhim\UniqueFileStorage\FileSources;

use Maxkhim\UniqueFileStorage\Contracts\FileSourceInterface;

class StreamAdapter implements FileSourceInterface
{
    protected $stream;
    protected $size;
    protected $mimeType;
    protected $originalName;

    public function __construct($stream, int $size, string $originalName, string $mimeType = null)
    {
        $this->stream = $stream;
        $this->size = $size;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function getContent(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }

    public function getStream()
    {
        rewind($this->stream);
        return $this->stream;
    }

    public function getSize(): int
    {
        return $this->size;
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
        return is_resource($this->stream);
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}