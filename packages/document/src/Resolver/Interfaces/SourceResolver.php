<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver\Interfaces;

use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): SourceDocument;
}
