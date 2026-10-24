<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Internal;

use Alama\Arazzo\Expression\JsonPointer;

/**
 * @internal stays out of the advertised contract; shared by the reference evaluators.
 */
final readonly class ValueReader
{
    public function __construct(private JsonPointer $jsonPointer) {}

    /**
     * Apply an optional `#/json-pointer` suffix to a value. An absent or empty
     * pointer is the value itself (RFC 6901 §3); a non-empty pointer addresses
     * structure, so a scalar value yields null.
     */
    public function withPointer(mixed $value, ?string $pointer): mixed
    {
        if ($pointer === null || $pointer === '') {
            return $value;
        }

        return is_array($value) ? $this->jsonPointer->resolve($value, $pointer) : null;
    }

    /**
     * Resolve a pointer against an HTTP body. Without a pointer the body is
     * returned as-is: bodies are not always JSON, and XPath/text criteria read
     * strings through `context: $response.body` (#274).
     */
    public function body(mixed $body, ?string $pointer): mixed
    {
        return $pointer === null ? $body : $this->withPointer($body, $pointer);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function map(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function pointer(array $data, ?string $pointer): mixed
    {
        return $this->jsonPointer->resolve($data, $pointer);
    }
}
