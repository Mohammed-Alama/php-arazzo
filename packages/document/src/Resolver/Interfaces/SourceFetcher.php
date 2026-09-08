<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver\Interfaces;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}
