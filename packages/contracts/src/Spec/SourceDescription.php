<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

final readonly class SourceDescription
{
    public function __construct(
        public string $name,
        public string $url,
        public SourceType $type,
    ) {}
}
