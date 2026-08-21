<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

use Alama\Arazzo\Support\Exceptions\NotImplementedException;

class OpenApi31Normalizer implements OpenApiNormalizerInterface
{
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation
    {
        throw new NotImplementedException('OpenAPI 3.1 normalization is not yet implemented.');
    }
}
