<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

/**
 * Safe narrowing of mixed config() values so bindings never cast blind.
 */
final class ConfigValue
{
    public static function int(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function float(mixed $value, float $default): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public static function string(mixed $value, string $default): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    public static function bool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (bool) $value;
        }

        return $default;
    }
}
