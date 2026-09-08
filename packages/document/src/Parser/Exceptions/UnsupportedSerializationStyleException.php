<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser\Exceptions;

use RuntimeException;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class UnsupportedSerializationStyleException extends RuntimeException
{
    public function __construct(string $style, string $location)
    {
        parent::__construct(sprintf('Unsupported serialization style "%s" for location "%s".', $style, $location));
    }
}
