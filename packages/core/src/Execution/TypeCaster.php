<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

class TypeCaster
{
    public static function asInteger(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        throw new \InvalidArgumentException('Cannot cast to integer.');
    }

    public static function asFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        throw new \InvalidArgumentException('Cannot cast to float.');
    }

    public static function asBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }
        if (is_numeric($value)) {
            return (bool) $value;
        }
        throw new \InvalidArgumentException('Cannot cast to boolean.');
    }

    public static function asString(mixed $value): string
    {
        if (is_scalar($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        throw new \InvalidArgumentException('Cannot cast to string.');
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }
}
