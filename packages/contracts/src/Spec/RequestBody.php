<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class RequestBody
{
    /** @param list<PayloadReplacement> $replacements */
    public function __construct(
        public ?string $contentType,
        public mixed $payload,
        public array $replacements,
    ) {}
}
