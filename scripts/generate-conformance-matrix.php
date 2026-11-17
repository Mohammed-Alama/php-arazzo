#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Generates docs/CONFORMANCE.md (the public conformance matrix) and
 * docs/conformance/manifest.json (the machine-readable conformance
 * capability manifest) from the same in-memory manifest model.
 *
 * Both outputs are produced by running the vendored official OAI example
 * corpus through the same conformance stack used by the Pest suite.
 *
 * Usage: php scripts/generate-conformance-matrix.php
 */

require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../packages/core/tests/Conformance/ConformanceHarness.php';
require_once __DIR__.'/../packages/core/tests/Conformance/ConformanceManifestBuilder.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiCorpusRunner.php';
require_once __DIR__.'/../packages/core/tests/Conformance/FakerOpenApiExecutor.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiFixtureRunner.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiQueueFixtureRunner.php';

use Alama\Arazzo\Tests\Conformance\ConformanceManifestBuilder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;

$documents = OaiCorpusRunner::documents();
$model = ConformanceManifestBuilder::build($documents);

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

foreach ($model['documents'] as $row) {
    $notes = $row['notes'] === [] ? '' : implode('<br>', array_map(
        fn (string $note): string => htmlspecialchars($note, ENT_QUOTES),
        $row['notes'],
    ));

    $out .= sprintf(
        "| `%s` | %s | %s | %s | %s | %s |\n",
        $row['name'],
        $row['version'],
        $row['parseValidate'],
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

$manifestDir = __DIR__.'/../docs/conformance';
if (!is_dir($manifestDir)) {
    mkdir($manifestDir, 0777, true);
}

file_put_contents($manifestDir.'/manifest.json', ConformanceManifestBuilder::serialize($model));

echo "Wrote docs/CONFORMANCE.md\n";
echo "Wrote docs/conformance/manifest.json\n";
