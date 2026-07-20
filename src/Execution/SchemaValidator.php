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

        if (is_array($value)) {
            if ($value === [] || !array_is_list($value)) {
                $violations = [...$violations, ...self::validateObject($schema, $value, $path)];
            }
            if ($value === [] || array_is_list($value)) {
                $violations = [...$violations, ...self::validateArray($schema, $value, $path)];
            }
        }

        return $violations;
    }

    /**
     * @param array<string,mixed> $value
     *
     * @return list<array{path: string, message: string}>
     */
    private static function validateObject(Schema $schema, array $value, string $path): array
    {
        $violations = [];

        foreach ($schema->required ?? [] as $requiredName) {
            if (!array_key_exists($requiredName, $value)) {
                $violations[] = [
                    'path' => $path . '/' . $requiredName,
                    'message' => "missing required property '{$requiredName}'",
                ];
            }
        }

        foreach ($schema->properties ?? [] as $name => $propSchema) {
            if (!array_key_exists($name, $value)) {
                continue;
            }
            $resolved = self::resolveSchema($propSchema);
            if ($resolved === null) {
                continue;
            }
            $violations = [...$violations, ...self::validate($resolved, $value[$name], $path . '/' . $name)];
        }

        return $violations;
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<array{path: string, message: string}>
     */
    private static function validateArray(Schema $schema, array $value, string $path): array
    {
        $violations = [];

        $itemSchema = self::resolveSchema($schema->items);
        if ($itemSchema !== null) {
            foreach (array_values($value) as $index => $item) {
                $violations = [...$violations, ...self::validate($itemSchema, $item, $path . '/' . $index)];
            }
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
