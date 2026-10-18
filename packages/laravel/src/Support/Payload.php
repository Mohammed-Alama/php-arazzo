<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

/**
 * Narrowing for untrusted inbound data: decoded request bodies, cached state
 * and graph payloads. Everything here arrives as mixed from a transport or a
 * database, so the shapes the typed seams expect have to be established
 * rather than assumed. Non-string keys are dropped, which matches the
 * name-to-value maps these payloads are read as.
 */
final class Payload
{
    /**
     * @return array<string, mixed>
     */
    public static function record(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_filter($value, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Reads a list of records out of a container, skipping anything that is
     * not an array.
     *
     * @return list<array<string, mixed>>
     */
    public static function records(mixed $container, string $key): array
    {
        $items = self::record($container)[$key] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $records = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $records[] = self::record($item);
            }
        }

        return $records;
    }

    /**
     * Narrows scalars to strings; anything else falls back to the default.
     */
    public static function text(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
