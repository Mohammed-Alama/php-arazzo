<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Normalizer\Interfaces;

use Alama\Arazzo\Document\Normalizer\NormalizedOpenApiOperation;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OpenApiNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $document  The full parsed OpenAPI document
     * @param  string  $path  The path to the operation
     * @param  string  $method  The HTTP method of the operation
     */
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation;
}
