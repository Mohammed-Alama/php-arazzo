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

        $trimmed = trim($jsonPointer, '/');
        if ($trimmed === '') {
            return $current;
        }

        $parts = explode('/', $trimmed);

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
