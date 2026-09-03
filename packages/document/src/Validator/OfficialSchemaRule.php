<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;
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
 */
final class OfficialSchemaRule implements Rule
{
    private const SCHEMA_FILES = [
        '1.0' => __DIR__.'/../../resources/schemas/arazzo-1.0.schema.json',
        '1.1' => __DIR__.'/../../resources/schemas/arazzo-1.1.schema.json',
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $raw = $doc->rawRoot;
        if (!is_array($raw)) {
            return; // parser-built documents (tests) carry no raw payload
        }

        $minor = str_starts_with($doc->arazzo, '1.0') ? '1.0' : '1.1';
        $schemaFile = self::SCHEMA_FILES[$minor];
        if (!is_file($schemaFile)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($schemaFile), true);
        if (!is_array($decoded) || !is_string($decoded['$id'] ?? null)) {
            return;
        }

        $defs = $decoded['$defs'] ?? null;
        if (!is_array($defs)) {
            return;
        }

        if (!is_array($defs['schema'] ?? null)) {
            return;
        }

        $schemaId = $decoded['$id'];

        // Neutralize remote refs into the 2020-12 metaschema (workflow inputs)
        // so validation works fully offline.
        /** @var array<array-key, mixed> $schemaDefs */
        $schemaDefs = $defs;
        $schemaDefs['schema'] = ['type' => 'object'];
        $decoded['$defs'] = $schemaDefs;

        // The official schemas are written for draft 2020-12, where $ref may
        // appear alongside sibling keywords. Justinrainbow treats a $ref as
        // exclusive (draft-7 style), which silently skips "required" checks.
        // Hoist every sibling $ref into allOf to restore correct semantics.
        $decoded = self::hoistSiblingRefs($decoded);

        $schemaData = json_decode((string) json_encode($decoded));
        assert($schemaData instanceof \stdClass);

        $storage = new SchemaStorage();
        $storage->addSchema($schemaId, $schemaData);
        $validator = new JsonSchemaValidator();

        $data = json_decode(json_encode($raw) ?: '{}');
        if (!is_object($data) && !is_array($data)) {
            $errors->error('schema.invalid', 'Raw document is not a JSON object.', '/');

            return;
        }

        try {
            $validator->validate(
                $data,
                $storage->getSchema($schemaId),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_DISABLE_FORMAT,
            );
        } catch (ValidationException) {
            return; // unsupported schema construct - defer to semantic rules
        }

        if ($validator->isValid()) {
            return;
        }

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
