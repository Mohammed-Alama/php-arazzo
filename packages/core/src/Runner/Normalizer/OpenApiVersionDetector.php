<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

use InvalidArgumentException;

class OpenApiVersionDetector
{
    /**
     * @param array<string, mixed> $document
     */
    public function detect(array $document): string
    {
        if (isset($document['swagger']) && is_string($document['swagger']) && str_starts_with($document['swagger'], '2.')) {
            return '2.0';
        }

        if (isset($document['openapi']) && is_string($document['openapi'])) {
            $version = $document['openapi'];
            if (str_starts_with($version, '3.0.')) {
                return '3.0';
            }
            if (str_starts_with($version, '3.1.')) {
                return '3.1';
            }
        }

        throw new InvalidArgumentException('Unsupported or missing OpenAPI/Swagger version in document.');
    }
}
