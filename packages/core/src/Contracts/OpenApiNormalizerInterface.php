<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Runner\Normalizer\NormalizedOpenApiOperation;

interface OpenApiNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $document  The full parsed OpenAPI document
     * @param  string  $path  The path to the operation
     * @param  string  $method  The HTTP method of the operation
     */
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation;
}
