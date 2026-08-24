<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Exceptions;

use Throwable;

final class UnresolvableReferenceException extends SourceResolutionException
{
    public function __construct(
        string $message,
        public readonly string $sourceName = '',
        public readonly string $reference = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
