<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Exceptions;

use Alama\Arazzo\Contracts\Support\Exceptions\ArazzoException;
use Throwable;

final class ExpressionSyntaxException extends ArazzoException
{
    public function __construct(
        string $message,
        public readonly string $expression = '',
        public readonly int $offset = -1,
        string $path = '',
        string $codeId = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $path, $codeId, $previous);
    }
}
