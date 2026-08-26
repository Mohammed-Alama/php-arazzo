<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

use Alama\Arazzo\Contracts\OpenApiNormalizerInterface;
use Alama\Arazzo\Support\Exceptions\NotImplementedException;

class Swagger2Normalizer implements OpenApiNormalizerInterface
{
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation
    {
        throw new NotImplementedException('Swagger 2.0 normalization is not yet implemented.');
    }
}
