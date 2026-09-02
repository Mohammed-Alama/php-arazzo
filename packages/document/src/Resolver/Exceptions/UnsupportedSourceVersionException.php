<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver\Exceptions;

use RuntimeException;

final class UnsupportedSourceVersionException extends RuntimeException
{
    public static function forVersion(string $version, string $sourceName): self
    {
        return new self(
            "Source '{$sourceName}' declares version '{$version}', which is not supported. "
            .'Supported: OpenAPI 3.0.x and 3.1.x. Swagger 2.0 documents are not supported yet.',
        );
    }
}
