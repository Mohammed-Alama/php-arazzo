<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser\Decoders;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
