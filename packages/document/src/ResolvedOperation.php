<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\SourceDescription;

class ResolvedOperation
{
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
    ) {}
}
