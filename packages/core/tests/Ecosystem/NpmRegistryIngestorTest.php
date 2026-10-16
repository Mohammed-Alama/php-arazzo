<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\Ingestors\NpmRegistryIngestor;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Ingestors/NpmRegistryIngestor.php';

if (!function_exists('npm_rrmdir')) {
    function npm_rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}

function npm_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-npm-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/** @return array<string,mixed> */
function npm_registry_fixture(): array
{
    $path = dirname(__DIR__, 4).'/scripts/ecosystem/fixtures/npm/registry-usearazzo-parser.json';
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : [];
}

it('emits a package.version for every npm version newer than the watermark, not only dist-tags.latest', function (): void {
    // dist-tags.latest is 1.2.0; the watermark sits before 1.1.0, so 1.1.0, 1.1.1 and 1.2.0 are all new.
    $observations = NpmRegistryIngestor::observationsFromRegistry(
        '@usearazzo/parser',
        'npm.@usearazzo/parser',
        npm_registry_fixture(),
        '2025-06-01T00:00:00Z',
    );

    expect(array_column($observations, 'externalId'))->toBe([
        'npm:1.1.0',
        'npm:1.1.1',
        'npm:1.2.0',
    ]);

    $dir = npm_tmp_dir();
    $differ = new Differ();
    $events = $differ->diff($observations, new State($dir), '2026-10-09T06:00:00Z');

    expect($events)->toHaveCount(3)
        ->and(array_values(array_unique(array_map(fn ($e) => $e->kind, $events))))->toBe(['package.version'])
        ->and(array_column($events, 'entityId'))->toBe([
            'npm:npm.@usearazzo/parser#1.1.0',
            'npm:npm.@usearazzo/parser#1.1.1',
            'npm:npm.@usearazzo/parser#1.2.0',
        ]);

    npm_rrmdir($dir);
});

it('emits no observations when the watermark already covers every published version', function (): void {
    $observations = NpmRegistryIngestor::observationsFromRegistry(
        '@usearazzo/parser',
        'npm.@usearazzo/parser',
        npm_registry_fixture(),
        '2026-10-08T00:00:00Z', // exactly the newest publish time
    );

    expect($observations)->toHaveCount(0);
});

it('falls back to dist-tags.latest when the registry has no time map', function (): void {
    $observations = NpmRegistryIngestor::observationsFromRegistry(
        '@usearazzo/parser',
        'npm.@usearazzo/parser',
        [
            'dist-tags' => ['latest' => '2.0.0'],
            'versions' => ['2.0.0' => ['description' => 'Arazzo parser']],
        ],
    );

    expect($observations)->toHaveCount(1)
        ->and($observations[0]['externalId'])->toBe('npm:2.0.0');
});
