<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

interface SourceParser
{
    public function parse(string $content): ResolvedSource;
}
