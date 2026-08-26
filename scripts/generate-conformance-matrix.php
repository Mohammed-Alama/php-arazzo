#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Generates docs/CONFORMANCE.md - the public conformance matrix - by
 * running the vendored official OAI example corpus through the same
 * stack used by the Pest suite.
 *
 * Usage: php scripts/generate-conformance-matrix.php
 */

require __DIR__.'/../packages/core/vendor/autoload.php';
require_once __DIR__.'/../packages/core/tests/Conformance/ConformanceHarness.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiCorpusRunner.php';
require_once __DIR__.'/../packages/core/tests/Conformance/FakerOpenApiExecutor.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiFixtureRunner.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiQueueFixtureRunner.php';

use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;
use Alama\Arazzo\Tests\Conformance\OaiFixtureRunner;
use Alama\Arazzo\Tests\Conformance\OaiQueueFixtureRunner;

const UPSTREAM_DEFECTS = [
    'FAPI-PAR' => 'arazzo references operationId "PAR" but companion OpenAPI declares "Par" (case mismatch)',
    'LoginAndRetrievePets' => 'operationPath `#/paths/~1pet~1findByStatus` omits the HTTP-method segment required to reach an Operation Object',
];

const MISSING_SOURCES = ['ExtendedParametersExample' => 'companion `animals.yaml` is not shipped upstream'];

function hasSubWorkflowSteps(string $path): bool
{
    $decoded = (new SymfonyYamlDecoder())->decode((string) file_get_contents($path));

    foreach ($decoded['workflows'] ?? [] as $workflow) {
        foreach ($workflow['steps'] ?? [] as $step) {
            if (isset($step['workflowId'])) {
                return true;
            }
        }
    }

    return false;
}

$rows = [];

foreach (OaiCorpusRunner::documents() as $name => $path) {
    $tier1 = OaiCorpusRunner::tier1($path);
    $row = [
        'document' => $name,
        'version' => str_starts_with($name, 'pet-asyncapi') ? '1.1.0' : '1.0.0',
        'parse_validate' => $tier1->isValid() ? 'pass' : 'FAIL',
        'adapter' => '—',
        'execute' => 'skipped',
        'notes' => [],
    ];

    if (!$tier1->isValid()) {
        foreach ($tier1->errors as $error) {
            $row['notes'][] = '['.$error->code.'] '.$error->message;
        }
        $rows[] = $row;

        continue;
    }

    if (isset(MISSING_SOURCES[$name])) {
        $row['notes'][] = MISSING_SOURCES[$name];
        $rows[] = $row;

        continue;
    }

    if (array_key_exists($name, UPSTREAM_DEFECTS)) {
        $row['execute'] = 'n/a (upstream defect)';
        $row['notes'][] = UPSTREAM_DEFECTS[$name];
        $rows[] = $row;

        continue;
    }

    $fixture = [
        'name' => $name,
        'arazzoFile' => $path,
        'responses' => [],
        'inputs' => [],
    ];

    $runner = hasSubWorkflowSteps($path) ? new OaiQueueFixtureRunner() : new OaiFixtureRunner();
    $row['adapter'] = $runner instanceof OaiQueueFixtureRunner ? 'queued (sub-workflow)' : 'sync';

    try {
        $observed = $runner->run($fixture);
        $row['execute'] = $observed['status'];

        foreach ($observed['errors'] as $error) {
            $row['notes'][] = $error;
        }
    } catch (Throwable $e) {
        $row['execute'] = 'ERROR';
        $row['notes'][] = get_class($e).': '.$e->getMessage();
    }

    $rows[] = $row;
}

$today = date('Y-m-d');
$out = "# Conformance Matrix\n"
    ."\n> Generated on {$today} by `php scripts/generate-conformance-matrix.php`.\n"
    ."> Corpus: official [OAI Arazzo examples](https://github.com/OAI/Arazzo-Specification/tree/main/examples)\n"
    ."> (vendored snapshot under `packages/core/tests/Conformance/corpus/oai/`).\n"
    .">\n"
    ."> **Tier 1** parses and structurally validates each document.\n"
    ."> **Execute** runs workflow(s) against a deterministic in-memory transport\n"
    ."> whose responses are fabricated from each operation's declared contract.\n"
    ."\n"
    ."| Document | Arazzo | Parse + validate | Adapter | Execute | Notes |\n"
    ."|---|---|---|---|---|---|\n";

foreach ($rows as $row) {
    $notes = $row['notes'] === [] ? '' : implode('<br>', array_map(
        fn (string $note): string => htmlspecialchars($note, ENT_QUOTES),
        $row['notes'],
    ));

    $out .= sprintf(
        "| `%s` | %s | %s | %s | %s | %s |\n",
        $row['document'],
        $row['version'],
        $row['parse_validate'],
        $row['adapter'],
        $row['execute'],
        $notes,
    );
}

$out .= "\n## Capability gaps surfaced by this harness\n\n"
    ."Bugs found and fixed while building this matrix (each pinned by tests):\n\n"
    ."- validator accepted raw `{\$sourceDescriptions.*}` strings instead of extracting the source name\n"
    .'- runtime resolver lacked the dotted `$sourceDescriptions.NAME.OPID` grammar'."\n"
    ."- multi-segment JSON Pointer paths (`~1a~1b`) rejected by operationPath resolution\n"
    ."- cebe references unresolvable (`readFromJson` provides no context)\n"
    ."- OpenAPI 3.1 normalizer was an unimplemented stub\n"
    .'- expression parser rejected the spec\'s `$steps.<id>.<output>` shortcut form'."\n"
    ."- JSONPath filter selectors `[?@...]` / `[?count(...)]` unsupported spelling\n";

file_put_contents(__DIR__.'/../docs/CONFORMANCE.md', $out);

echo "Wrote docs/CONFORMANCE.md\n";
