<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

interface SourceParser
{
    public function parse(string $content): ResolvedSource;
}
