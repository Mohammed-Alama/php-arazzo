<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

final class SchemaValidator
{
    /**
     * @return list<array{path: string, message: string}>
     */
    public static function validate(Schema $schema, mixed $value, string $path = ''): array
    {
        $at = $path === '' ? '/' : $path;

        if ($value === null) {
            if ($schema->nullable === true || $schema->type === null) {
                return [];
            }

            return [['path' => $at, 'message' => 'must not be null']];
        }

        $violations = [];

        if ($schema->type !== null && !self::matchesType($schema->type, $value)) {
            $violations[] = ['path' => $at, 'message' => "expected type '{$schema->type}', got " . get_debug_type($value)];
        }

        if ($schema->enum !== null && $schema->enum !== [] && !in_array($value, $schema->enum, true)) {
            $violations[] = ['path' => $at, 'message' => 'value is not one of the allowed enum values'];
        }

        return $violations;
    }

    private static function matchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            // json_decode(..., true) can't tell an empty object `{}` from an empty array
            // `[]` -- both decode to `[]`, so an empty array is accepted as either shape.
            'object' => is_array($value) && (!array_is_list($value) || $value === []),
            default => true,
        };
    }

    private static function resolveSchema(mixed $schemaOrRef): ?Schema
    {
        if ($schemaOrRef instanceof Reference) {
            $schemaOrRef = $schemaOrRef->resolve();
        }

        return $schemaOrRef instanceof Schema ? $schemaOrRef : null;
    }
}
