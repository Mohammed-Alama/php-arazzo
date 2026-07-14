<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
