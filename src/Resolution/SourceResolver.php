<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\Arazzo\Dto\SourceDescription;

interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): ResolvedSource;
}
