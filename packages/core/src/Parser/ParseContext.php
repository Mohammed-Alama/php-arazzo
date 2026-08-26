<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser;

final readonly class ParseContext
{
    /** @param list<string> $segments */
    public function __construct(
        private string $filePath,
        private array $segments = [],
    ) {}

    public function push(string|int $segment): self
    {
        $encoded = str_replace(['~', '/'], ['~0', '~1'], (string) $segment);

        return new self($this->filePath, [...$this->segments, $encoded]);
    }

    public function pointer(): string
    {
        return $this->segments === [] ? '' : '/'.implode('/', $this->segments);
    }

    public function path(): string
    {
        return $this->filePath;
    }
}
