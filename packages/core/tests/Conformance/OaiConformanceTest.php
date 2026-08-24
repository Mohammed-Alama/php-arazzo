<?php

declare(strict_types=1);

use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;
use Alama\Arazzo\Tests\Conformance\OaiFixtureRunner;
use Alama\Arazzo\Tests\Conformance\OaiQueueFixtureRunner;

dataset('oai_examples', fn (): array => array_map(
    fn (string $path, string $name): array => [$name, $path],
    OaiCorpusRunner::documents(),
    array_keys(OaiCorpusRunner::documents()),
));

/** Documents that reference a companion source which upstream does not ship. */
const OAI_MISSING_SOURCES = ['ExtendedParametersExample'];

/**
 * Upstream inconsistency: FAPI-PAR.arazzo.yaml references operationId
 * `PAR` while its companion OpenAPI declares `Par`. Operation ids are
 * case-sensitive; execution is therefore reported as an upstream defect
 * in the conformance matrix instead of being silently tolerated.
 */
const OAI_UPSTREAM_DEFECTS = [
    'FAPI-PAR' => 'arazzo references operationId "PAR" but companion OpenAPI declares "Par"',
    'LoginAndRetrievePets' => 'operationPath pointer "#/paths/~1pet~1findByStatus" omits the HTTP method segment required to reach an Operation Object',
];

function oaiHasSubWorkflowSteps(string $path): bool
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

/**
 * TIER 1 - every official example must parse and validate cleanly.
 */
it('parses and validates official OAI example', function (string $name, string $path) {
    $result = OaiCorpusRunner::tier1($path);

    expect($result->isValid())->toBeTrue(
        "Official example '{$name}' should be valid, errors: " . json_encode($result->errors),
    );
})->with('oai_examples');

/**
 * TIER 2 - executable examples run against a deterministic mock transport
 * (every request answered 200 {}). Sub-workflow documents run through the
 * queued adapter; the rest through the synchronous one.
 */
it('executes official OAI example against mock transport', function (string $name, string $path) {
    if (array_key_exists($name, OAI_UPSTREAM_DEFECTS)) {
        $this->markTestSkipped('upstream defect: ' . OAI_UPSTREAM_DEFECTS[$name]);
    }

    $sources = OaiCorpusRunner::localSources($path);

    if ($sources === []) {
        $this->markTestSkipped('companion OpenAPI source not shipped upstream');
    }

    $fixture = [
        'name' => 'oai-' . $name,
        'arazzoFile' => $path,
        'responses' => [], // FakePsr18Client default: 200 {}
        'inputs' => [],
    ];

    $observed = oaiHasSubWorkflowSteps($path)
        ? (new OaiQueueFixtureRunner())->run($fixture)
        : (new OaiFixtureRunner())->run($fixture);

    expect($observed['status'])->toBe('succeeded', json_encode([
        'steps' => $observed['steps'],
        'errors' => $observed['errors'],
    ]));
})->with('oai_examples');
