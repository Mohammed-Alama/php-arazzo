<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Document\ResolvedOperation;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

/**
 * The cebe-facing view of a resolved operation. The three OpenAPI-specific
 * consumers in the runner receive this instead of the model type, which is
 * what lets alama/arazzo-document stay cebe-free.
 */
final readonly class OpenApiOperationHandle
{
    public function __construct(
        public ResolvedOperation $operation,
        public OpenApi $openApi,
        public Operation $cebeOperation,
    ) {}
}
