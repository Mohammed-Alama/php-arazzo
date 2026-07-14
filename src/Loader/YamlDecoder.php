<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

interface YamlDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
