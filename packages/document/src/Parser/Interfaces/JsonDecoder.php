<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser\Interfaces;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
