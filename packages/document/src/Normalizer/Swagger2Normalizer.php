<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Normalizer;

use Alama\Arazzo\Contracts\Support\Exceptions\NotImplementedException;
use Alama\Arazzo\Document\Normalizer\Interfaces\OpenApiNormalizerInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class Swagger2Normalizer implements OpenApiNormalizerInterface
{
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation
    {
        throw new NotImplementedException('Swagger 2.0 normalization is not yet implemented.');
    }
}
