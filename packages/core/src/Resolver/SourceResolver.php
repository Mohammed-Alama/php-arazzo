<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\SourceDocument;

interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): SourceDocument;
}
