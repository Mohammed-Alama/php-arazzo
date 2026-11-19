<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\Conformance\ConformanceManifestBuilder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;

const SCRIPT = __DIR__.'/../../../../scripts/check-conformance-manifest.php';
const MANIFEST = __DIR__.'/../../../../docs/conformance/manifest.json';

/** @return array{exit: int, output: string} */
function runChecker(): array
{
    $output = [];
    exec('php '.escapeshellarg(SCRIPT).' 2>&1', $output, $exitCode);

    return ['exit' => (int) $exitCode, 'output' => implode("\n", $output)];
}

test('checker exits 0 when the manifest is in sync with the corpus', function () {
    $result = runChecker();

    expect($result['exit'])->toBe(0);
    expect($result['output'])->toContain('is in sync');
});

test('checker exits non-zero and prints a diff when the manifest drifted', function () {
    $backup = file_get_contents(MANIFEST);

    try {
        file_put_contents(MANIFEST, "{\"drifted\": true}\n");

        $result = runChecker();

        expect($result['exit'])->not->toBe(0);
        expect($result['output'])->toContain('DRIFT');
        expect($result['output'])->toContain('--- committed');
        expect($result['output'])->toContain('+++ rebuilt');
    } finally {
        file_put_contents(MANIFEST, $backup);
    }

    // The tree is back in sync after the drift probe.
    expect(runChecker()['exit'])->toBe(0);
});

test('checker rebuilds the same model the generator serializes', function () {
    $documents = OaiCorpusRunner::documents();
    $expected = ConformanceManifestBuilder::serialize(
        ConformanceManifestBuilder::build($documents),
    );

    expect(file_get_contents(MANIFEST))->toBe($expected);
});
