<?php

namespace Maxkhim\UniqueFileStorage\Contracts;

interface FileSourceInterface
{
    public function getContent(): string;
    public function getStream();
    public function getSize(): int;
    public function getMimeType(): ?string;
    public function getOriginalName(): ?string;
    public function getPathname(): ?string;
    public function isValid(): bool;
}