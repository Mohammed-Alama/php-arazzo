<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;

/**
 * Builds the machine-readable conformance capability manifest from the
 * vendored OAI example corpus.
 *
 * The builder hides three concerns behind a single entry point:
 *
 * - corpus runner orchestration: tier 1 parse/validate, adapter selection,
 *   execution against the deterministic mock transport;
 * - capability->status mapping: each capability is scored from its evidence
 *   entries using the project's status vocabulary;
 * - JSON serialization with deterministic key ordering so the committed
 *   `docs/conformance/manifest.json` is byte-stable across runs.
 *
 * @see OaiCorpusRunner
 */
final class ConformanceManifestBuilder
{
    public const STATUS_SUPPORTED = 'supported';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_NOT_APPLICABLE = 'not-applicable';

    /** @var list<string> every status the manifest may emit, in vocabulary order. */
    public const VOCABULARY = [
        self::STATUS_SUPPORTED,
        self::STATUS_PARTIAL,
        self::STATUS_UNSUPPORTED,
        self::STATUS_NOT_APPLICABLE,
    ];

    /** Documents upstream ships without a companion source we can vendor. */
    private const MISSING_SOURCES = [
        'ExtendedParametersExample' => 'companion `animals.yaml` is not shipped upstream',
    ];

    /** Upstream defects reported honestly instead of being silently tolerated. */
    private const UPSTREAM_DEFECTS = [
        'FAPI-PAR' => 'arazzo references operationId "PAR" but companion OpenAPI declares "Par" (case mismatch)',
        'LoginAndRetrievePets' => 'operationPath `#/paths/~1pet~1findByStatus` omits the HTTP-method segment required to reach an Operation Object',
    ];

    private const CORPUS_TITLE = 'OAI Arazzo examples';

    private const CORPUS_SOURCE_URL = 'https://github.com/OAI/Arazzo-Specification/tree/main/examples';

    private const CORPUS_SNAPSHOT_DIR = 'packages/core/tests/Conformance/corpus/oai/';

    /**
     * Builds the in-memory manifest model from the corpus documents.
     *
     * @param  array<string, string>  $documents  document name => file path
     * @return array<string, mixed>
     */
    public static function build(array $documents): array
    {
        uksort($documents, 'strcasecmp');

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($documents as $name => $path) {
            $rows[] = self::documentRow($name, $path);
        }

        return [
            'corpus' => [
                'title' => self::CORPUS_TITLE,
                'sourceUrl' => self::CORPUS_SOURCE_URL,
                'snapshotDir' => self::CORPUS_SNAPSHOT_DIR,
                'documentCount' => count($rows),
            ],
            'statusVocabulary' => self::VOCABULARY,
            'capabilities' => self::capabilities($documents, $rows),
            'documents' => $rows,
        ];
    }

    /**
     * Serializes the manifest model to deterministic, pretty-printed JSON.
     *
     * Keys are emitted in insertion order; callers build sub-arrays in a
     * fixed order so output is byte-stable across runs.
     *
     * @param  array<string, mixed>  $model
     *
     * @throws \JsonException
     */
    public static function serialize(array $model): string
    {
        return json_encode(
            $model,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n";
    }

    /**
     * Runs one corpus document through the conformance stack and returns its
     * manifest row.
     *
     * @return array<string, mixed>
     */
    private static function documentRow(string $name, string $path): array
    {
        $tier1 = OaiCorpusRunner::tier1($path);
        $subWorkflow = self::hasSubWorkflowSteps($path);

        $row = [
            'name' => $name,
            'version' => str_starts_with($name, 'pet-asyncapi') ? '1.1.0' : '1.0.0',
            'parseValidate' => $tier1->isValid() ? 'pass' : 'FAIL',
            'adapter' => '—',
            'execute' => 'skipped',
            'notes' => [],
        ];

        if (!$tier1->isValid()) {
            foreach ($tier1->errors as $error) {
                $row['notes'][] = '['.$error->code.'] '.$error->message;
            }

            return $row;
        }

        if (isset(self::MISSING_SOURCES[$name])) {
            $row['notes'][] = self::MISSING_SOURCES[$name];

            return $row;
        }

        if (array_key_exists($name, self::UPSTREAM_DEFECTS)) {
            $row['execute'] = 'n/a (upstream defect)';
            $row['notes'][] = self::UPSTREAM_DEFECTS[$name];

            return $row;
        }

        $fixture = [
            'name' => $name,
            'arazzoFile' => $path,
            'responses' => [],
            'inputs' => [],
        ];

        $runner = $subWorkflow ? new OaiQueueFixtureRunner() : new OaiFixtureRunner();

        $row['adapter'] = $runner instanceof OaiQueueFixtureRunner
            ? 'queued (sub-workflow)'
            : 'sync';

        try {
            $observed = $runner->run($fixture);
            $row['execute'] = $observed['status'];

            foreach ($observed['errors'] as $error) {
                $row['notes'][] = $error;
            }
        } catch (\Throwable $e) {
            $row['execute'] = 'ERROR';
            $row['notes'][] = get_class($e).': '.$e->getMessage();
        }

        return $row;
    }

    /**
     * Maps document rows to capabilities, each scored from its evidence.
     *
     * @param  array<string, string>  $documents  document name => file path
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function capabilities(array $documents, array $rows): array
    {
        $definitions = [
            [
                'id' => 'parse-and-validate',
                'name' => 'Parse + structural validation',
                'description' => 'Every corpus document parses and passes Tier 1 validation.',
                'evidence' => fn (array $rows): array => array_map(
                    fn (array $row): array => [
                        'document' => $row['name'],
                        'result' => $row['parseValidate'],
                        'ok' => $row['parseValidate'] === 'pass',
                    ],
                    $rows,
                ),
            ],
            [
                'id' => 'execute-sync',
                'name' => 'Synchronous execution',
                'description' => 'Documents executed through the sync adapter against the mock transport.',
                'evidence' => fn (array $rows): array => array_map(
                    fn (array $row): array => [
                        'document' => $row['name'],
                        'result' => $row['execute'],
                        'ok' => $row['adapter'] === 'sync' && $row['execute'] === 'succeeded',
                    ],
                    array_filter($rows, fn (array $row): bool => $row['adapter'] === 'sync'),
                ),
            ],
            [
                'id' => 'execute-queued',
                'name' => 'Queued (sub-workflow) execution',
                'description' => 'Documents containing sub-workflow steps, executed through the queued adapter.',
                'evidence' => fn (array $rows): array => array_map(
                    fn (array $row): array => [
                        'document' => $row['name'],
                        'result' => $row['execute'],
                        'ok' => $row['adapter'] === 'queued (sub-workflow)' && $row['execute'] === 'succeeded',
                    ],
                    array_filter($rows, fn (array $row): bool => $row['adapter'] === 'queued (sub-workflow)'),
                ),
            ],
            [
                'id' => 'source-resolution',
                'name' => 'OpenAPI source resolution',
                'description' => 'Every declared sourceDescription resolves to a vendored local companion.',
                'evidence' => function (array $rows) use ($documents): array {
                    return array_map(
                        fn (array $row): array => [
                            'document' => $row['name'],
                            'result' => self::sourceResult($documents[$row['name']]),
                            'ok' => self::sourceResolved($documents[$row['name']]),
                        ],
                        array_filter($rows, fn (array $row): bool => self::declaresSources($documents[$row['name']])),
                    );
                },
            ],
        ];

        $out = [];

        foreach ($definitions as $definition) {
            $evidence = $definition['evidence']($rows);
            usort($evidence, fn (array $a, array $b): int => strcmp($a['document'], $b['document']));

            $out[] = [
                'id' => $definition['id'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'status' => self::status($evidence),
                'evidence' => $evidence,
            ];
        }

        return $out;
    }

    /**
     * Scores a capability from its evidence entries.
     *
     * - no evidence -> not-applicable (the capability does not apply to this corpus)
     * - no evidence ok -> unsupported
     * - some but not all ok -> partial
     * - all ok (and at least one entry) -> supported
     *
     * `supported` therefore always requires at least one evidence entry.
     *
     * @param  list<array<string, mixed>>  $evidence
     */
    private static function status(array $evidence): string
    {
        if ($evidence === []) {
            return self::STATUS_NOT_APPLICABLE;
        }

        $ok = 0;

        foreach ($evidence as $entry) {
            if ($entry['ok'] === true) {
                $ok++;
            }
        }

        if ($ok === 0) {
            return self::STATUS_UNSUPPORTED;
        }

        return $ok < count($evidence) ? self::STATUS_PARTIAL : self::STATUS_SUPPORTED;
    }

    /**
     * Whether the document at `$path` declares at least one sourceDescription.
     */
    private static function declaresSources(string $path): bool
    {
        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($path));

        return is_array($decoded['sourceDescriptions'] ?? null) && $decoded['sourceDescriptions'] !== [];
    }

    /**
     * Human result for the document at `$path`'s source resolution.
     */
    private static function sourceResult(string $path): string
    {
        return self::sourceResolved($path) ? 'resolved' : 'missing-local-companion';
    }

    /**
     * Whether every sourceDescription declared by the document at `$path`
     * resolves to a vendored local companion.
     */
    private static function sourceResolved(string $path): bool
    {
        $local = OaiCorpusRunner::localSources($path);

        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($path));
        $declared = is_array($decoded['sourceDescriptions'] ?? null) ? count($decoded['sourceDescriptions']) : 0;

        return $declared > 0 && count($local) === $declared;
    }

    /**
     * Whether the document contains a sub-workflow step (step.workflowId).
     */
    private static function hasSubWorkflowSteps(string $path): bool
    {
        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($path));

        foreach ($decoded['workflows'] ?? [] as $workflow) {
            foreach ($workflow['steps'] ?? [] as $step) {
                if (isset($step['workflowId'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
