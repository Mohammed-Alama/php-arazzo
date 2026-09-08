<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver\Exceptions;

use Throwable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
