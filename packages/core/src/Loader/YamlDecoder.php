<?php

declare(strict_types=1);

namespace Alama\Arazzo\Loader;

interface YamlDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
