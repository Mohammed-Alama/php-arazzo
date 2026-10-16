<?php

declare(strict_types=1);

use Ecosystem\Event;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';

if (!function_exists('ecosystem_rrmdir')) {
    function ecosystem_rrmdir(string $dir): void
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

function state_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-state-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

it('persists entities and watermarks deterministically and byte-stably', function (): void {
    $dir = state_tmp_dir();

    $state = new State($dir);
    $state->putEntity([
        'entityId' => 'gh:X#1',
        'kind' => 'pr',
        'source' => 'X',
        'fingerprint' => 'abc',
        'fields' => ['state' => 'open', 'labels' => []],
        'firstSeenAt' => '2026-10-01T00:00:00Z',
        'lastChangedAt' => '2026-10-01T00:00:00Z',
    ]);
    $state->recordWatermark('X', 'pulls', 'lastSeenUpdatedAt', '2026-10-01T00:00:00Z');
    $state->save();

    $entities = (string) file_get_contents($dir.'/entities.jsonl');
    $watermarks = (string) file_get_contents($dir.'/watermarks.json');

    // Reload + save with no change must leave the bytes identical (no churn → no commit).
    $again = new State($dir);
    $again->save();

    expect((string) file_get_contents($dir.'/entities.jsonl'))->toBe($entities)
        ->and((string) file_get_contents($dir.'/watermarks.json'))->toBe($watermarks)
        ->and($entities)->toBe('{"entityId":"gh:X#1","fields":{"labels":[],"state":"open"},"fingerprint":"abc","firstSeenAt":"2026-10-01T00:00:00Z","kind":"pr","lastChangedAt":"2026-10-01T00:00:00Z","source":"X"}'."\n");

    ecosystem_rrmdir($dir);
});

it('appends events to monthly files losslessly and deduplicates by id', function (): void {
    $dir = state_tmp_dir();

    $state = new State($dir);
    $october = new Event(
        'evt_1', 'gh:X#1', 'pr.state_changed', 'X',
        '2026-10-20T09:12:00Z', '2026-10-21T06:00:00Z',
        ['type' => 'pr', 'title' => 't', 'url' => 'u'],
        ['field' => 'state', 'from' => 'open', 'to' => 'merged'],
    );
    $november = new Event(
        'evt_2', 'gh:X#2', 'release.published', 'X',
        '2026-11-02T00:00:00Z', '2026-11-03T06:00:00Z',
        ['type' => 'release', 'title' => 'r', 'url' => 'u2'],
        ['field' => 'version', 'from' => null, 'to' => '1.0.0'],
    );

    expect($state->appendEvents([$october, $november]))->toBe(2)
        ->and($state->appendEvents([$october, $november]))->toBe(0)
        ->and($state->eventsForMonth('2026-10'))->toHaveCount(1)
        ->and($state->eventsForMonth('2026-11'))->toHaveCount(1);

    ecosystem_rrmdir($dir);
});

it('rejects a malformed entities line', function (): void {
    $dir = state_tmp_dir();
    file_put_contents($dir.'/entities.jsonl', '{"entityId":"gh:X#1"}'."\n");

    expect(fn () => new State($dir))->toThrow(RuntimeException::class);

    ecosystem_rrmdir($dir);
});

it('loads human-authored decisions', function (): void {
    $dir = state_tmp_dir();
    file_put_contents($dir.'/decisions.json', json_encode([
        'version' => 1,
        'decisions' => [['entityId' => 'gh:X#1', 'status' => 'reviewed', 'by' => 'human']],
    ]));

    $state = new State($dir, $dir.'/decisions.json');

    expect($state->decisions())->toHaveCount(1)
        ->and($state->decisions()[0]['status'])->toBe('reviewed');

    ecosystem_rrmdir($dir);
});
