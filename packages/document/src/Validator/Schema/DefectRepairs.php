<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Schema;

/**
 * Repairs defects in the vendored official schemas where the specification
 * prose is the authority. Each repair cites the section that defines the
 * intended shape.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class DefectRepairs
{
    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    public static function apply(array $schema, string $minor): array
    {
        $defs = $schema['$defs'] ?? null;
        if (!is_array($defs)) {
            return $schema;
        }

        if ($minor === '1.0') {
            $defs = self::criterionExpressionType($defs);
        } else {
            $defs = self::stepObjectDiscrimination($defs);
            $defs = self::payloadReplacementValue($defs);
        }

        $schema['$defs'] = $defs;

        return $schema;
    }

    /**
     * Arazzo 1.0.1 §4.6.11.3 defines Criterion Object `type` as either the
     * simple/regex/jsonpath/xpath string enum or a Criterion Expression Type
     * Object (§4.6.12). The vendored 1.0 schema applies the expression-type
     * object to the Criterion Object itself instead of to its `type` property,
     * so the documented object form can never validate.
     *
     * @param  array<array-key, mixed>  $defs
     * @return array<array-key, mixed>
     */
    private static function criterionExpressionType(array $defs): array
    {
        $criterion = $defs['criterion-object'] ?? null;
        if (!is_array($criterion)) {
            return $defs;
        }

        $criterion['anyOf'] = [
            [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'description' => 'The type of condition to be applied',
                        'enum' => ['simple', 'regex', 'jsonpath', 'xpath'],
                        'default' => 'simple',
                    ],
                ],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['$ref' => '#/$defs/criterion-expression-type-object'],
                ],
            ],
        ];

        $defs['criterion-object'] = $criterion;

        return $defs;
    }

    /**
     * Arazzo 1.1 §5.8.5 makes operationId/operationPath (OpenAPI), action plus
     * operationId/channelPath (AsyncAPI), workflowId (workflow), and
     * operationName (WSDL, 1.2) mutually exclusive step targets. The vendored
     * schema relies on `unevaluatedProperties: false`, which justinrainbow does
     * not enforce, so an async step also matches the OpenAPI/workflow branches
     * and fails `oneOf`. Spell the exclusivity out with explicit `not` guards.
     *
     * @param  array<array-key, mixed>  $defs
     * @return array<array-key, mixed>
     */
    private static function stepObjectDiscrimination(array $defs): array
    {
        $defs = self::forbidFields($defs, 'openapi-step-object', [
            'action', 'channelPath', 'correlationId', 'workflowId', 'operationName',
        ]);
        $defs = self::forbidFields($defs, 'asyncapi-step-object', [
            'operationPath', 'workflowId', 'operationName',
        ]);
        $defs = self::forbidFields($defs, 'workflow-step-object', [
            'operationId', 'operationPath', 'action', 'channelPath', 'correlationId', 'operationName',
        ]);
        $defs = self::forbidFields($defs, 'wsdl-step-object', [
            'operationId', 'operationPath', 'action', 'channelPath', 'correlationId', 'workflowId',
        ]);

        return $defs;
    }

    /**
     * @param  array<array-key, mixed>  $defs
     * @param  list<string>  $fields
     * @return array<array-key, mixed>
     */
    private static function forbidFields(array $defs, string $name, array $fields): array
    {
        $definition = $defs[$name] ?? null;
        if (!is_array($definition)) {
            return $defs;
        }

        $allOf = $definition['allOf'] ?? null;
        $definition['allOf'] = is_array($allOf) ? $allOf : [];
        $definition['allOf'][] = [
            'not' => [
                'anyOf' => array_map(
                    static fn (string $field): array => ['required' => [$field]],
                    $fields,
                ),
            ],
        ];
        $defs[$name] = $definition;

        return $defs;
    }

    /**
     * Arazzo 1.1 §5.8.15.1 types Payload Replacement Object `value` as
     * "Any | {expression} | Selector Object"; the vendored schema only allows a
     * string or a Selector Object. A selector object is recognised by its three
     * required members, so everything else is accepted as a literal.
     *
     * @param  array<array-key, mixed>  $defs
     * @return array<array-key, mixed>
     */
    private static function payloadReplacementValue(array $defs): array
    {
        $replacement = $defs['payload-replacement-object'] ?? null;
        if (!is_array($replacement)) {
            return $defs;
        }

        $properties = $replacement['properties'] ?? null;
        if (!is_array($properties) || !isset($properties['value'])) {
            return $defs;
        }

        $properties['value'] = [
            'description' => 'The value to set at the location defined by the target. May be a literal, a runtime expression string, or a selector object.',
            'if' => [
                'type' => 'object',
                'required' => ['context', 'selector', 'type'],
            ],
            'then' => ['$ref' => '#/$defs/selector-object'],
        ];
        $replacement['properties'] = $properties;

        $defs['payload-replacement-object'] = $replacement;

        return $defs;
    }
}
