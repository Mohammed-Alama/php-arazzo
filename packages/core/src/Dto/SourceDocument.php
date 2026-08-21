<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

use Alama\Arazzo\Dto\Enum\SourceType;

final readonly class SourceDocument
{
    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        public string $name,
        public SourceType $type,
        public string $canonicalUri,
        public array $content,
    ) {
    }
}
