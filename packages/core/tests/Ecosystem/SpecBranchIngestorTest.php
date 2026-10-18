<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\Ingestors\SpecBranchIngestor;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Ingestors/SpecBranchIngestor.php';

if (!function_exists('spec_rrmdir')) {
    function spec_rrmdir(string $dir): void
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

function spec_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-spec-branch-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/** @return array<int,array<string,mixed>> */
function spec_watch_files(): array
{
    return ['versions/*.md', 'examples/**', '_archive_/schemas/**'];
}

/** @return array<string,mixed> */
function spec_compare(string $name): array
{
    $path = dirname(__DIR__, 4).'/scripts/ecosystem/fixtures/spec/'.$name;
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : [];
}

it('yields exactly one schema.changed with file and line counts for a v1.2-dev schema commit', function (): void {
    $dir = spec_tmp_dir();
    $differ = new Differ();

    $observations = SpecBranchIngestor::observations(
        'OAI/Arazzo-Specification',
        'v1.2-dev',
        'aaa1111',
        'bbb2222',
        spec_watch_files(),
        spec_compare('compare-v1.2-dev-schema.json'),
    );

    // Only the schema.yaml change is watched; README.md is not a confirmed path.
    expect($observations)->toHaveCount(1)
        ->and($observations[0]['type'])->toBe('schema')
        ->and($observations[0]['files'])->toBe(['_archive_/schemas/schema.yaml']);

    $state = new State($dir);
    $events = $differ->diff($observations, $state, '2026-10-11T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('schema.changed')
        ->and($events[0]->occurredAt)->toBe('2026-10-10T09:12:00Z') // provider commit date
        ->and($events[0]->change['files'])->toBe(['_archive_/schemas/schema.yaml'])
        ->and($events[0]->change['additions'])->toBe(3)
        ->and($events[0]->change['deletions'])->toBe(1)
        ->and($events[0]->change['fileStats'][0]['path'])->toBe('_archive_/schemas/schema.yaml');

    spec_rrmdir($dir);
});

it('yields one spec.text_changed for a watched versions/*.md change, with commits and line counts', function (): void {
    $dir = spec_tmp_dir();
    $differ = new Differ();

    $observations = SpecBranchIngestor::observations(
        'OAI/Arazzo-Specification',
        'v1.2-dev',
        'ccc1111',
        'ddd2222',
        spec_watch_files(),
        spec_compare('compare-v1.2-dev-spec-text.json'),
    );

    // `src/arazzo.md` (no such dir) and CONTRIBUTING.md are filtered out; the two confirmed paths
    // (`versions/1.2.0.md`, `examples/**`) remain, sorted by path.
    expect($observations)->toHaveCount(1)
        ->and($observations[0]['type'])->toBe('spec')
        ->and($observations[0]['files'])->toBe([
            'examples/retry-output.arazzo.yaml',
            'versions/1.2.0.md',
        ]);

    $state = new State($dir);
    $events = $differ->diff($observations, $state, '2026-10-11T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('spec.text_changed')
        ->and($events[0]->change['additions'])->toBe(50) // 10 + 40
        ->and($events[0]->change['deletions'])->toBe(2)
        ->and($events[0]->change['commits'])->toBe([
            'docs(spec): clarify retry outputs',
            'docs(examples): add retry-output example',
        ]);

    spec_rrmdir($dir);
});

it('never lists a path outside the confirmed watch globs', function (): void {
    $observations = SpecBranchIngestor::observations(
        'OAI/Arazzo-Specification',
        'v1.2-dev',
        'ccc1111',
        'ddd2222',
        ['versions/*.md'],
        spec_compare('compare-v1.2-dev-spec-text.json'),
    );

    expect($observations)->toHaveCount(1)
        ->and($observations[0]['files'])->toBe(['versions/1.2.0.md']);
});

it('emits a spec.text_changed transition only when the stored headSha moved', function (): void {
    $dir = spec_tmp_dir();
    $differ = new Differ();

    $state = new State($dir);
    $first = SpecBranchIngestor::observations(
        'OAI/Arazzo-Specification',
        'v1.2-dev',
        'ccc1111',
        'ddd2222',
        spec_watch_files(),
        spec_compare('compare-v1.2-dev-spec-text.json'),
    );
    $differ->diff($first, $state, '2026-10-09T12:00:00Z');
    $state->save();

    // Replaying the same observation yields no new event (idempotent).
    $state = new State($dir);
    expect($differ->diff($first, $state, '2026-10-10T06:00:00Z'))->toHaveCount(0);

    spec_rrmdir($dir);
});
