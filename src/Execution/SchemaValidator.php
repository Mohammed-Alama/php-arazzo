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

        if (is_string($value)) {
            $violations = [...$violations, ...self::validateString($schema, $value, $path)];
        }

        if (is_int($value) || is_float($value)) {
            $violations = [...$violations, ...self::validateNumber($schema, $value, $path)];
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

    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateString(Schema $schema, string $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->pattern !== null && @preg_match('/' . str_replace('/', '\/', $schema->pattern) . '/u', $value) !== 1) {
            $violations[] = ['path' => $at, 'message' => "does not match pattern '{$schema->pattern}'"];
        }

        if ($schema->format !== null && !self::matchesFormat($schema->format, $value)) {
            $violations[] = ['path' => $at, 'message' => "does not match format '{$schema->format}'"];
        }

        return $violations;
    }

    private static function matchesFormat(string $format, string $value): bool
    {
        return match ($format) {
            'date' => self::isValidDateTime($value, 'Y-m-d'),
            'date-time' => self::isValidDateTime($value, \DateTimeInterface::RFC3339_EXTENDED)
                || self::isValidDateTime($value, \DateTimeInterface::RFC3339),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'ipv4' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            // Unrecognized formats (password, byte, int64, vendor-specific, ...) are
            // annotations per the JSON Schema spec -- not validated, never a violation.
            default => true,
        };
    }

    private static function isValidDateTime(string $value, string $format): bool
    {
        $date = \DateTime::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateNumber(Schema $schema, int|float $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->minimum !== null) {
            $exclusive = $schema->exclusiveMinimum === true;
            if ($exclusive ? $value <= $schema->minimum : $value < $schema->minimum) {
                $bound = $exclusive ? 'greater than' : 'at least';
                $violations[] = ['path' => $at, 'message' => "value must be {$bound} {$schema->minimum}"];
            }
        }

        if ($schema->maximum !== null) {
            $exclusive = $schema->exclusiveMaximum === true;
            if ($exclusive ? $value >= $schema->maximum : $value > $schema->maximum) {
                $bound = $exclusive ? 'less than' : 'at most';
                $violations[] = ['path' => $at, 'message' => "value must be {$bound} {$schema->maximum}"];
            }
        }

        if ($schema->multipleOf !== null && (float) $schema->multipleOf !== 0.0) {
            $remainder = fmod((float) $value, (float) $schema->multipleOf);
            $nearZero = abs($remainder) < 1e-9;
            $nearDivisor = abs(abs($remainder) - abs((float) $schema->multipleOf)) < 1e-9;
            if (!$nearZero && !$nearDivisor) {
                $violations[] = ['path' => $at, 'message' => "value must be a multiple of {$schema->multipleOf}"];
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
