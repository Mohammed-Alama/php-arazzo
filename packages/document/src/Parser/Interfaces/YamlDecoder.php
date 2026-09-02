<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser\Interfaces;

interface YamlDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
