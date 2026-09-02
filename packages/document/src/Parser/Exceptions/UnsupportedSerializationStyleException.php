<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser\Exceptions;

use RuntimeException;

class UnsupportedSerializationStyleException extends RuntimeException
{
    public function __construct(string $style, string $location)
    {
        parent::__construct(sprintf('Unsupported serialization style "%s" for location "%s".', $style, $location));
    }
}
