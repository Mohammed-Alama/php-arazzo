<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution;

interface SourceParser
{
    public function parse(string $content): ResolvedSource;
}
