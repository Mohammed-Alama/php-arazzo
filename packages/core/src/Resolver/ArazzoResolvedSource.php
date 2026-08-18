<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;

final readonly class ArazzoResolvedSource implements ResolvedSource
{
    public function __construct(private ArazzoDocument $document)
    {
    }

    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed
    {
        $encoded = json_encode($this->document);
        if ($encoded === false) {
            throw new UnresolvableReferenceException('Failed to serialize Arazzo document for pointer traversal');
        }

        $data = json_decode($encoded, true);
        if (!is_array($data)) {
            throw new UnresolvableReferenceException('Serialized document is not an object');
        }

        $trimmed = trim($jsonPointer, '/');
        if ($trimmed === '') {
            return $data;
        }

        $parts = explode('/', $trimmed);
        $current = $data;

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
