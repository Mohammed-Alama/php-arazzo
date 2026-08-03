<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution;

use Alama\Arazzo\Resolution\Exceptions\UnresolvableReferenceException;
use cebe\openapi\spec\OpenApi;

final readonly class OpenApiResolvedSource implements ResolvedSource
{
    public function __construct(private OpenApi $openapi)
    {
    }

    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed
    {
        $current = $this->openapi->getSerializableData();

        $trimmed = trim($jsonPointer, '/');
        if ($trimmed === '') {
            return $current;
        }

        $parts = explode('/', $trimmed);

        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } elseif (is_object($current) && property_exists($current, $part)) {
                $current = $current->{$part};
            } else {
                throw new UnresolvableReferenceException("Path not found: {$jsonPointer}");
            }
        }

        return $current;
    }
}
