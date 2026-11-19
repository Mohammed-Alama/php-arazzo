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
     * @param  array<string, mixed>  $schema
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
     * @param  array<string, mixed>  $schema
     * @return list<mixed>
     */
    public static function arrayFromSchema(array $schema): array
    {
        $minItems = is_int($schema['minItems'] ?? null) ? $schema['minItems'] : 0;
        $items = $schema['items'] ?? null;

        if ($minItems < 1 || !is_array($items)) {
            return [];
        }

        return array_fill(0, $minItems, self::valueFromProperty($items));
    }

    /**
     * @param  array<string, mixed>  $property
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

        $type = self::typeOf($property);

        // Composition-only properties (`allOf: [$ref]`, ...) carry no type at
        // the top level; the first composed member that declares a shape
        // drives fabrication.
        if ($type === null) {
            foreach (['allOf', 'oneOf', 'anyOf'] as $composition) {
                foreach (is_array($property[$composition] ?? null) ? $property[$composition] : [] as $member) {
                    if (is_array($member) && self::typeOf($member) !== null) {
                        return self::valueFromProperty($member);
                    }
                }
            }
        }

        return match ($type) {
            'integer' => 1,
            'number' => 1.0,
            'boolean' => true,
            'string' => self::stringFor($property),
            'array' => self::arrayFromSchema($property),
            'object' => self::objectFromSchema($property),
            default => false,
        };
    }

    /**
     * The single named type a schema declares, or `object` when it only has
     * properties. JSON-Schema 2020-12 type arrays (`["string", "null"]`) use
     * the first non-null entry.
     *
     * @param  array<string, mixed>  $schema
     */
    private static function typeOf(array $schema): ?string
    {
        $type = $schema['type'] ?? null;

        if (is_string($type)) {
            return $type;
        }

        if (is_array($type)) {
            foreach ($type as $candidate) {
                if (is_string($candidate) && $candidate !== 'null') {
                    return $candidate;
                }
            }

            return null;
        }

        return is_array($schema['properties'] ?? null) ? 'object' : null;
    }

    /**
     * Deterministic string samples, checked against a declared pattern so the
     * fabricated body satisfies the same regex the validator enforces. The
     * corpus patterns are anchored codes (ISO currency, HTTP status).
     *
     * @var list<string>
     */
    private const PATTERN_SAMPLES = [
        'conformance',
        'AAA',
        '200',
        '000',
        '123',
        'a',
        '1',
        '2024-01-01',
        '2024-01-01T00:00:00Z',
        'user@example.com',
        '00000000-0000-4000-8000-000000000000',
        'https://example.com',
    ];

    /**
     * A string that satisfies the common declarative constraints.
     *
     * @param  array<string, mixed>  $property
     */
    private static function stringFor(array $property): string
    {
        $pattern = is_string($property['pattern'] ?? null) ? $property['pattern'] : null;

        if ($pattern !== null) {
            foreach (self::PATTERN_SAMPLES as $sample) {
                if (@preg_match('/'.str_replace('/', '\/', $pattern).'/u', $sample) === 1) {
                    return $sample;
                }
            }
        }

        $value = 'conformance';

        $minLength = is_int($property['minLength'] ?? null) ? $property['minLength'] : null;

        if ($minLength !== null && mb_strlen($value) < $minLength) {
            $value = str_pad($value, $minLength, 'x');
        }

        return $value;
    }
}
