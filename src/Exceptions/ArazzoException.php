<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

use RuntimeException;

abstract class ArazzoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $path = '',
        public readonly string $codeId = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
