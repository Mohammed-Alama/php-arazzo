<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Interfaces;

interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}
