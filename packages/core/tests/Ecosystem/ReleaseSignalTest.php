<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\Ingestors\NpmRegistryIngestor;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Ingestors/NpmRegistryIngestor.php';

if (!function_exists('release_rrmdir')) {
    function release_rrmdir(string $dir): void
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

function release_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-release-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/**
 * A monorepo release observation, e.g. `@redocly/cli@2.64.0`.
 *
 * @return array<string,mixed>
 */
function monorepo_release(string $package, string $version, string $date = '2026-10-15T07:17:32Z'): array
{
    return [
        'source' => 'Redocly/redocly-cli',
        'type' => 'release',
        'externalId' => 'release:'.$package.'@'.$version,
        'title' => $package.'@'.$version,
        'url' => 'https://github.com/Redocly/redocly-cli/releases/tag/'.$package.'@'.$version,
        'publishedAt' => $date,
        'labels' => [],
        'prerelease' => false,
        'draft' => false,
    ];
}

it('A4: collapses six monorepo releases for one version into one event carrying packages[]', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $packages = ['@redocly/cli', '@redocly/openapi-core', '@redocly/recheck', '@redocly/respect-core', '@redocly/reunite-integration', '@redocly/x'];

    $events = $differ->diff(array_map(
        fn (string $package): array => monorepo_release($package, '2.64.0'),
        $packages,
    ), $state, '2026-10-16T06:17:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('release.published')
        ->and($events[0]->entityId)->toBe('gh:Redocly/redocly-cli#release:2.64.0')
        ->and($events[0]->change['to'])->toBe('2.64.0')
        ->and($events[0]->change['packages'])->toBe($packages) // sorted
        ->and($state->count())->toBe(1);

    release_rrmdir($dir);
});

it('A4: keeps distinct monorepo versions as separate events', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([
        monorepo_release('@redocly/cli', '2.64.0'),
        monorepo_release('@redocly/openapi-core', '2.64.0'),
        monorepo_release('@redocly/client-generator', '0.7.0'),
    ], $state, '2026-10-16T06:17:00Z');

    $ids = array_map(fn ($e) => $e->entityId, $events);
    sort($ids);

    expect($events)->toHaveCount(2)
        ->and($ids)->toBe([
            'gh:Redocly/redocly-cli#release:0.7.0',
            'gh:Redocly/redocly-cli#release:2.64.0',
        ]);

    release_rrmdir($dir);
});

it('A4: a tag whose normalized version has a release emits no tag event', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'release', 'externalId' => 'release:1.1.0', 'title' => 'Arazzo 1.1.0', 'url' => 'u', 'publishedAt' => '2026-05-17T00:00:00Z', 'labels' => []],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'tag', 'externalId' => 'tag:v1.1.0', 'title' => 'tag v1.1.0', 'url' => 'u2', 'publishedAt' => '2026-10-13T00:00:00Z', 'labels' => []],
    ], $state, '2026-10-21T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('release.published')
        ->and($events[0]->entityId)->toBe('gh:OAI/Arazzo-Specification#release:1.1.0')
        ->and($state->entity('gh:OAI/Arazzo-Specification#tag:1.1.0'))->toBeNull();

    release_rrmdir($dir);
});

it('A4: a tag-only repo (frankkilcommins/arazzo2openapi) gets a real occurredAt', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([[
        'source' => 'frankkilcommins/arazzo2openapi', 'type' => 'tag', 'externalId' => 'tag:v1.0.2',
        'tagName' => 'v1.0.2', 'title' => 'tag v1.0.2', 'url' => 'u',
        'publishedAt' => '2026-04-09T21:26:34Z', 'labels' => [],
    ]], $state, '2026-10-16T06:17:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->entityId)->toBe('gh:frankkilcommins/arazzo2openapi#tag:1.0.2')
        ->and($events[0]->occurredAt)->toBe('2026-04-09T21:26:34Z'); // provider date, never the run clock

    release_rrmdir($dir);
});

it('records the newest release tag and the npm version watermark', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $differ->diff([
        ['source' => 'Redocly/redocly-cli', 'type' => 'release', 'externalId' => 'release:@redocly/cli@2.63.0', 'title' => 'a', 'url' => 'u', 'publishedAt' => '2026-10-13T12:29:42Z', 'labels' => []],
        ['source' => 'Redocly/redocly-cli', 'type' => 'release', 'externalId' => 'release:@redocly/cli@2.64.0', 'title' => 'b', 'url' => 'u', 'publishedAt' => '2026-10-15T07:17:32Z', 'labels' => []],
        ['source' => 'speclynx/apidom', 'type' => 'release', 'externalId' => 'npm:1.9.0', 'title' => 'apidom@1.9.0', 'url' => 'u', 'publishedAt' => '2026-10-08T00:00:00Z', 'labels' => []],
        ['source' => 'speclynx/apidom', 'type' => 'release', 'externalId' => 'npm:1.10.0', 'title' => 'apidom@1.10.0', 'url' => 'u', 'publishedAt' => '2026-10-09T00:00:00Z', 'labels' => []],
    ], $state, '2026-10-16T06:17:00Z');

    expect($state->watermark('Redocly/redocly-cli', 'releases', 'lastTag'))->toBe('@redocly/cli@2.64.0')
        ->and($state->watermark('speclynx/apidom', 'npm', 'lastSeenVersion'))->toBe('1.10.0'); // semver, not lexical
    // Lexical ordering would have kept 1.9.0 (since "1.9.0" > "1.10.0").
    release_rrmdir($dir);
});

it('A4: a partial re-fetch of a monorepo train does not emit a spurious change', function (): void {
    $dir = release_tmp_dir();
    $differ = new Differ();

    $all = array_map(fn (string $p): array => monorepo_release($p, '2.64.0'), [
        '@redocly/cli', '@redocly/openapi-core', '@redocly/recheck', '@redocly/respect-core', '@redocly/reunite-integration',
    ]);

    $first = new State($dir);
    expect($differ->diff($all, $first, '2026-10-16T06:17:00Z'))->toHaveCount(1);
    $first->save();

    // Run 2 early-stops part-way through the train, so it only sees a subset.
    $second = new State($dir);
    $events = $differ->diff(array_slice($all, 0, 3), $second, '2026-10-17T06:17:00Z');

    expect($events)->toHaveCount(0) // merged with the stored group → byte-stable
        ->and($second->entity('gh:Redocly/redocly-cli#release:2.64.0')['fields']['packages'])->toHaveCount(5);

    release_rrmdir($dir);
});

it('npm watermark: only versions newer than the stored mark are emitted', function (): void {
    expect(NpmRegistryIngestor::shouldEmit('1.10.0', '1.9.0'))->toBeTrue()
        ->and(NpmRegistryIngestor::shouldEmit('1.9.0', '1.10.0'))->toBeFalse()
        ->and(NpmRegistryIngestor::shouldEmit('1.2.3', '1.2.3'))->toBeFalse()
        ->and(NpmRegistryIngestor::shouldEmit('1.2.3', null))->toBeTrue()
        ->and(NpmRegistryIngestor::shouldEmit('1.2.3', ''))->toBeTrue();
});
