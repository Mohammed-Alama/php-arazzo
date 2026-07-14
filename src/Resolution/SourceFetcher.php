<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}
