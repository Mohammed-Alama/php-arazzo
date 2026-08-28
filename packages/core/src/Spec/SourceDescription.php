<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Spec\Enum\SourceType;

final readonly class SourceDescription
{
    public function __construct(
        public string $name,
        public string $url,
        public SourceType $type,
    ) {}
}
