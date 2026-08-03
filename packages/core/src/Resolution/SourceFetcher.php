<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution;

interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}
