<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

class TypeCaster
{
    public static function asInteger(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        throw new \InvalidArgumentException("Cannot cast to integer.");
    }
    
    public static function asString(mixed $value): string
    {
        if (is_scalar($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        }
        throw new \InvalidArgumentException("Cannot cast to string.");
    }
    
    public static function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return [$value];
    }
}
