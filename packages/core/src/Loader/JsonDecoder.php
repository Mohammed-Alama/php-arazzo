<?php

declare(strict_types=1);

namespace Alama\Arazzo\Loader;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
