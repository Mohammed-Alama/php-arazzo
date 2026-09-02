<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser\Interfaces;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
