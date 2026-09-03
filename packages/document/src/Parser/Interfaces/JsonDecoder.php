<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser\Interfaces;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
