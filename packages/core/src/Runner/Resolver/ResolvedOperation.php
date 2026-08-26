<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Resolver;

use Alama\Arazzo\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Spec\SourceDescription;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

class ResolvedOperation
{
    /**
     * @param  array<string, mixed>  $rawDocument
     */
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
        public readonly OpenApi $openApi,
        public readonly array $rawDocument,
        public readonly Operation $cebeOperation,
    ) {}
}
