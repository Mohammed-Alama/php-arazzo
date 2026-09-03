<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver\Interfaces;

use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;

interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): SourceDocument;
}
