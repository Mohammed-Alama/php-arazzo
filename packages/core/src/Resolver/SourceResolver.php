<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\SourceDocument;

interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): SourceDocument;
}
