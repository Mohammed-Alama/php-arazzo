<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

/**
 * Deterministic value fabrication from JSON-Schema fragments, shared by
 * the response faker and workflow-input synthesis in the OAI corpus runs.
 */
final class ConformanceFabricator
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function objectFromSchema(array $schema): array
    {
        foreach (['example', 'default'] as $key) {
            if (isset($schema[$key]) && is_array($schema[$key]) && $schema[$key] !== []) {
                return $schema[$key];
            }
        }

        $out = [];

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach ($properties as $name => $property) {
            if (!is_array($property)) {
                continue;
            }

            $out[(string) $name] = self::valueFromProperty($property);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $property
     *
     * @return mixed
     */
    public static function valueFromProperty(array $property)
    {
        if (array_key_exists('example', $property)) {
            return $property['example'];
        }

        if (array_key_exists('default', $property)) {
            return $property['default'];
        }

        if (isset($property['enum']) && is_array($property['enum']) && $property['enum'] !== []) {
            return $property['enum'][0];
        }

        $type = $property['type'] ?? null;

        // JSON-Schema 2020-12 type arrays: ["string", "null"] -> first
        // non-null entry drives fabrication.
        if (is_array($type)) {
            foreach ($type as $candidate) {
                if (is_string($candidate) && $candidate !== 'null') {
                    $type = $candidate;
                    break;
                }
            }
        }

        return match ($type) {
            'integer' => 1,
            'number' => 1.0,
            'boolean' => true,
            'string' => 'conformance',
            'array' => [],
            'object' => self::objectFromSchema($property),
            default => false,
        };
    }
}
