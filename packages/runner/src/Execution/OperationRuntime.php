<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;

/**
 * The pair the runner needs to reach an operation: the document supplies the
 * vendor-free model, the resolver supplies the transport-bound handle.
 *
 * They are always constructed together and never travel apart, so they travel
 * as one value instead of as two constructor parameters.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class OperationRuntime
{
    public function __construct(
        public DocumentInterface $document,
        public OpenApiOperationResolver $resolver,
    ) {}
}
