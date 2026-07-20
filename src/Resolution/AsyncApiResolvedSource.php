<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

final readonly class AsyncApiResolvedSource implements ResolvedSource
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(private array $document)
    {
    }

    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed
    {
        $current = $this->document;

        if ($jsonPointer === '') {
            return $current;
        }

        if (!str_starts_with($jsonPointer, '/')) {
            throw new UnresolvableReferenceException("Invalid JSON Pointer: {$jsonPointer}");
        }

        $parts = explode('/', substr($jsonPointer, 1));

        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                throw new UnresolvableReferenceException("Path not found: {$jsonPointer}");
            }
        }

        return $current;
    }
}
