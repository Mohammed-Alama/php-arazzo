<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ValidationException;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator as JsonSchemaValidator;

/**
 * Structural validation of the raw document against the official Arazzo
 * JSON Schemas (draft 2020-12), layered *before* the semantic rules.
 *
 * Known limitation: workflow `inputs` schemas $ref the full 2020-12
 * metaschema over the network; structural checks for those subtrees are
 * skipped offline (the semantic rules still cover their shape).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OfficialSchemaRule implements Rule
{
    private const SCHEMA_FILES = [
        '1.0' => __DIR__.'/../../resources/schemas/arazzo-1.0.schema.json',
        '1.1' => __DIR__.'/../../resources/schemas/arazzo-1.1.schema.json',
        '1.2' => __DIR__.'/../../resources/schemas/arazzo-1.2.schema.json',
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $raw = $doc->rawRoot;
        if (!is_array($raw)) {
            return; // parser-built documents (tests) carry no raw payload
        }

        $schema = self::loadSchema($doc->arazzo);
        if ($schema === null) {
            return;
        }

        $data = json_decode(json_encode($raw) ?: '{}');
        if (!is_object($data) && !is_array($data)) {
            $errors->error('schema.invalid', 'Raw document is not a JSON object.', '/');

            return;
        }

        $validator = new JsonSchemaValidator();

        try {
            $validator->validate(
                $data,
                $schema,
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_DISABLE_FORMAT,
            );
        } catch (ValidationException) {
            return; // unsupported schema construct - defer to semantic rules
        }

        if ($validator->isValid()) {
            self::applyProseOverrides($raw, $errors, $doc->arazzo);

            return;
        }

        self::reportErrors($validator, $errors);
    }

    /**
     * Loads and prepares the official schema for the document's minor version,
     * returning null when the schema cannot be used offline.
     */
    private static function loadSchema(string $arazzo): ?object
    {
        $minor = match (true) {
            str_starts_with($arazzo, '1.0') => '1.0',
            str_starts_with($arazzo, '1.2') => '1.2',
            default => '1.1',
        };
        $schemaFile = self::SCHEMA_FILES[$minor];
        if (!is_file($schemaFile)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($schemaFile), true);
        if (!is_array($decoded) || !is_string($decoded['$id'] ?? null)) {
            return null;
        }

        $schemaId = $decoded['$id'];

        $defs = $decoded['$defs'] ?? null;
        if (!is_array($defs) || !is_array($defs['schema'] ?? null)) {
            return null;
        }

        // Neutralize remote refs into the 2020-12 metaschema (workflow inputs)
        // so validation works fully offline.
        $defs['schema'] = ['type' => 'object'];
        $decoded['$defs'] = $defs;

        // The official schemas are written for draft 2020-12, where $ref may
        // appear alongside sibling keywords. Justinrainbow treats a $ref as
        // exclusive (draft-7 style), which silently skips "required" checks.
        // Hoist every sibling $ref into allOf to restore correct semantics.
        $decoded = self::hoistSiblingRefs($decoded);

        $schemaData = json_decode((string) json_encode($decoded));
        if (!$schemaData instanceof \stdClass) {
            return null;
        }

        $storage = new SchemaStorage();
        $storage->addSchema($schemaId, $schemaData);

        $resolved = $storage->getSchema($schemaId);

        return is_object($resolved) ? $resolved : null;
    }

    /**
     * Apply prose-level overrides after schema validation passes.
     *
     * Inspects rawRoot for prose-level overrides based on the Arazzo version:
     *
     * 1. R1 (1.0): successCriteria/failureCriteria/criteria where `type` is not
     *    a string enum [simple|regex|jsonpath|xpath] per the Criterion Object spec
     *
     * 2. R2 (1.0): payload-replacement-object `value` that is an array -
     *    1.0 schema says `value` must be string only; prose confirms this direction
     *
     * 3. R3 (1.0): parameter-object `value` with selector-object branch -
     *    1.0 parameter `value` has no `selector-object` branch per prose
     *
     * 4. R4 (1.1): step `oneOf` requires `operationId` or `operationPath` -
     *    1.1 schema already enforces this; no additional override needed
     *
     * 5. R5 (all versions): reusable-parameter `value` typing contested between
     *    schema and prose - documented as unscored case (no code change)
     *
     * @param  array<array-key, mixed>  $raw  Raw document root (typically from $doc->rawRoot)
     */
    private static function applyProseOverrides(array $raw, ErrorCollector $errors, string $arazzo): void
    {
        $minor = match (true) {
            str_starts_with($arazzo, '1.0') => '1.0',
            str_starts_with($arazzo, '1.2') => '1.2',
            default => '1.1',
        };

        $criteriaPaths = self::findCriteriaWithNonStringType($raw, $minor);
        $replacementPaths = self::payloadArrayValues($raw, $minor);
        $parameterPaths = self::findParameterSelectorValues($raw, $minor);

        foreach ($criteriaPaths as $path => $message) {
            $errors->error(
                'schema.invalid',
                $message,
                $path,
            );
        }

        foreach ($replacementPaths as $path => $message) {
            $errors->error(
                'schema.invalid',
                $message,
                $path,
            );
        }

        foreach ($parameterPaths as $path => $message) {
            $errors->error(
                'schema.invalid',
                $message,
                $path,
            );
        }
    }

    /**
     * Find successCriteria/failureCriteria/criteria where `type` is not a string.
     *
     * For 1.0, the Criterion Object spec says `type` must be a string enum
     * of "simple|regex|jsonpath|xpath".  Objects or other non-string values
     * are invalid per prose even when the JSON Schema anyOf permits them.
     *
     * @param  array<array-key, mixed>  $raw  Raw document root (typically from $doc->rawRoot)
     * @param  string  $minor  Document minor version (e.g. "1.0", "1.1", "1.2")
     * @return array<array-key, string> pointer => error message
     */
    private static function findCriteriaWithNonStringType(
        array $raw,
        string $minor,
    ): array {
        $results = [];

        $steps = self::getStepsFromRaw($raw, $minor);
        foreach ($steps as $stepPath => $step) {
            self::checkSuccessCriteria($step, $stepPath, $results);
            self::checkOnFailureCriteria($step, $stepPath, $results);
        }

        return $results;
    }

    /**
     * Check successCriteria for non-string type.
     *
     * @param  array{successCriteria?: array, onFailure?: array}  $step
     */
    private static function checkSuccessCriteria(
        array $step,
        string $stepPath,
        array &$results,
    ): void {
        if (!isset($step['successCriteria']) || !is_array($step['successCriteria'])) {
            return;
        }
        foreach ($step['successCriteria'] as $idx => $criterion) {
            if (!isset($criterion['type']) || is_string($criterion['type'])) {
                continue;
            }
            $selfPath = $stepPath.'/successCriteria/'.$idx;
            $typedType = $criterion['type'];
            $results[$selfPath] = sprintf(
                '[%s] Criterion `type` MUST be a string enum [simple|regex|jsonpath|xpath], got %s',
                $selfPath,
                (string) $typedType,
            );
        }
    }

    /**
     * Check onFailure criteria for non-string type.
     *
     * @param  array{onFailure?: array}  $step
     */
    private static function checkOnFailureCriteria(
        array $step,
        string $stepPath,
        array &$results,
    ): void {
        if (!isset($step['onFailure']) || !is_array($step['onFailure'])) {
            return;
        }
        foreach ($step['onFailure'] as $ofIdx => $ofStep) {
            if (!isset($ofStep['criteria']) || !is_array($ofStep['criteria'])) {
                continue;
            }
            foreach ($ofStep['criteria'] as $critIdx => $criterion) {
                if (!isset($criterion['type']) || is_string($criterion['type'])) {
                    continue;
                }
                $selfPath = $stepPath.'/onFailure/'.$ofIdx.'/criteria/'.$critIdx;
                $typedType = $criterion['type'];
                $results[$selfPath] = sprintf(
                    '[%s] Criterion `type` MUST be a string enum [simple|regex|jsonpath|xpath], got %s',
                    $selfPath,
                    (string) $typedType,
                );
            }
        }
    }

    /**
     * Find payload-replacement-object values that are arrays (R2).
     *
     * 1.0 `payload-replacement-object` `value` is `string` only per schema.
     * Prose confirms this direction - arrays in `value` are invalid.
     *
     * @return array pointer => error message
     */
    private static function payloadArrayValues(
        array $raw,
        string $minor,
    ): array {
        $results = [];

        $workflows = $raw['workflows'] ?? [];
        foreach ($workflows as $wIdx => $workflow) {
            $steps = $workflow['steps'] ?? [];
            foreach ($steps as $sIdx => $step) {
                $requestBody = $step['requestBody'] ?? null;
                if (!$requestBody instanceof \stdClass) {
                    continue;
                }
                $replacements = $requestBody->replacements ?? [];
                if (!is_array($replacements)) {
                    continue;
                }
                foreach ($replacements as $rIdx => $replacement) {
                    if (!isset($replacement->value) || !is_array($replacement->value)) {
                        continue;
                    }
                    $selfPath = sprintf(
                        '/workflows/%d/steps/%d/requestBody/replacements/%d/value',
                        $wIdx,
                        $sIdx,
                        $rIdx,
                    );
                    $results[$selfPath] = sprintf(
                        '[%s] PayloadReplacement `value` MUST be a string, got array',
                        $selfPath,
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Find parameter-object values with selector-object branch (R3).
     *
     * 1.0 `parameter-object` `value` has no `selector-object` branch per prose.
     * The schema accepts `object` or `array` for `value`, but prose restricts
     * `value` to simple types (string, boolean, number, null) without selector
     * sub-structures for 1.0 documents.
     *
     * @param  array<array-key, mixed>  $raw  Raw document root (typically from $doc->rawRoot)
     * @param  string  $minor  Document minor version (e.g. "1.0", "1.1", "1.2")
     * @return array<array-key, string> pointer => error message
     */
    private static function findParameterSelectorValues(
        array $raw,
        string $minor,
    ): array {
        $results = [];

        $workflows = $raw['workflows'] ?? [];
        foreach ($workflows as $wIdx => $workflow) {
            $steps = $workflow['steps'] ?? [];
            foreach ($steps as $sIdx => $step) {
                $parameters = $step->parameters ?? [];
                if (!is_array($parameters)) {
                    continue;
                }
                foreach ($parameters as $pIdx => $param) {
                    if (!isset($param->value)) {
                        continue;
                    }
                    $value = $param->value;
                    // Check for selector-object structure: an object with $ref or
                    // a structure that looks like a selector
                    if (is_object($value) && property_exists($value, '$ref')) {
                        $selfPath = sprintf(
                            '/workflows/%d/steps/%d/parameters/%d/value',
                            $wIdx,
                            $sIdx,
                            $pIdx,
                        );
                        $results[$selfPath] = sprintf(
                            '[%s] Parameter `value` MUST not contain selector-object branch in 1.0',
                            $selfPath,
                        );
                    }
                    // Also check for array value with object elements that have $ref
                    if (is_array($value)) {
                        foreach ($value as $vIdx => $v) {
                            if (is_object($v) && property_exists($v, '$ref')) {
                                $selfPath = sprintf(
                                    '/workflows/%d/steps/%d/parameters/%d/value/%d',
                                    $wIdx,
                                    $sIdx,
                                    $pIdx,
                                    $vIdx,
                                );
                                $results[$selfPath] = sprintf(
                                    '[%s] Parameter `value` array element MUST not contain selector-object branch in 1.0',
                                    $selfPath,
                                );
                                break; // Only flag first occurrence per parameter
                            }
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Extract step objects from raw document, returning step path => step data.
     *
     * @return array<string, array{stepId?: string, successCriteria?: array, onFailure?: array}>
     */
    private static function getStepsFromRaw(array $raw, string $minor): array
    {
        $results = [];

        $workflows = $raw['workflows'] ?? [];
        foreach ($workflows as $wIdx => $workflow) {
            $steps = $workflow['steps'] ?? [];
            foreach ($steps as $sIdx => $step) {
                $stepPath = sprintf(
                    '/workflows/%d/steps/%d',
                    $wIdx,
                    $sIdx,
                );
                $results[$stepPath] = $step;
            }
        }

        return $results;
    }

    private static function reportErrors(JsonSchemaValidator $validator, ErrorCollector $errors): void
    {
        /** @var array{property: string, message: string, pointer: string} $error */
        foreach (array_slice($validator->getErrors(), 0, 25) as $error) {
            $errors->error(
                'schema.invalid',
                sprintf('[%s] %s', $error['property'] !== '' ? $error['property'] : '(root)', $error['message']),
                '/'.$error['pointer'],
            );
        }
    }

    public function code(): string
    {
        return 'schema.invalid';
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function hoistSiblingRefs(array $node): array
    {
        // $anchor-based local refs (#workflowId/#stepId) resolve to plain strings.
        if (($node['$ref'] ?? null) === '#workflowId' || ($node['$ref'] ?? null) === '#stepId') {
            return ['type' => 'string'];
        }

        if (isset($node['$ref']) && count($node) > 1) {
            $ref = ['$ref' => $node['$ref']];
            unset($node['$ref']);

            $hoisted = ['allOf' => [$ref]];
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $v = self::hoistSiblingRefs($v);
                }
                $hoisted[$k] = $v;
            }

            return $hoisted;
        }

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = self::hoistSiblingRefs($v);
            }
        }

        return $node;
    }
}
