<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';

if (!function_exists('differ_rrmdir')) {
    function differ_rrmdir(string $dir): void
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

function differ_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-differ-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/** @param array<string,mixed> $overrides */
function pr_obs(array $overrides = []): array
{
    return array_merge([
        'source' => 'OAI/Arazzo-Specification',
        'type' => 'pr',
        'externalId' => 'pr:533',
        'title' => 'feat(spec): add SOAP support',
        'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        'publishedAt' => '2026-07-27T15:46:00Z',
        'body' => '',
        'labels' => [],
        'state' => 'open',
        'draft' => false,
        'merged' => false,
        'base' => 'main',
        'tags' => ['wsdl'],
        'relevance' => 'plan:phase-f-protocol-packages',
    ], $overrides);
}

it('A2: emits nothing on a baseline run but records entities and watermarks', function (): void {
    $dir = differ_tmp_dir();
    $state = new State($dir);
    $differ = new Differ();

    $events = $differ->diff([pr_obs()], $state, '2026-10-21T06:17:10Z', true);
    $state->save();

    expect($events)->toHaveCount(0)
        ->and($state->count())->toBe(1)
        ->and(is_file($dir.'/watermarks.json'))->toBeTrue()
        ->and($state->watermarks()['sources']['OAI/Arazzo-Specification']['endpoints']['pulls']['lastSeenUpdatedAt'])->toBe('2026-07-27T15:46:00Z');

    differ_rrmdir($dir);
});

it('A1: is idempotent — a second run emits no events and changes no bytes', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();

    $first = new State($dir);
    $differ->diff([pr_obs()], $first, '2026-10-21T06:17:10Z');
    $first->save();
    $before = (string) file_get_contents($dir.'/entities.jsonl');

    $second = new State($dir);
    $events = $differ->diff([pr_obs()], $second, '2026-10-22T06:17:10Z');
    $second->save();

    expect($events)->toHaveCount(0)
        ->and((string) file_get_contents($dir.'/entities.jsonl'))->toBe($before);

    differ_rrmdir($dir);
});

it('A3: emits one pr.state_changed per state change (open -> draft -> ready -> merged)', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();

    $state = new State($dir);
    $differ->diff([pr_obs()], $state, '2026-10-01T00:00:00Z', true);
    $state->save();

    $state = new State($dir);
    $draft = $differ->diff([pr_obs(['draft' => true, 'publishedAt' => '2026-10-02T00:00:00Z'])], $state, '2026-10-02T06:00:00Z');
    expect($draft)->toHaveCount(1)
        ->and($draft[0]->kind)->toBe('pr.state_changed')
        ->and($draft[0]->change)->toMatchArray(['field' => 'state', 'from' => 'open', 'to' => 'draft']);
    $state->save();

    $state = new State($dir);
    $ready = $differ->diff([pr_obs(['publishedAt' => '2026-10-03T00:00:00Z'])], $state, '2026-10-03T06:00:00Z');
    expect($ready)->toHaveCount(1)
        ->and($ready[0]->kind)->toBe('pr.state_changed')
        ->and($ready[0]->change)->toMatchArray(['from' => 'draft', 'to' => 'ready']);
    $state->save();

    $state = new State($dir);
    $merged = $differ->diff([pr_obs(['state' => 'closed', 'merged' => true, 'publishedAt' => '2026-10-04T00:00:00Z'])], $state, '2026-10-04T06:00:00Z');
    expect($merged)->toHaveCount(1)
        ->and($merged[0]->change)->toMatchArray(['from' => 'ready', 'to' => 'merged'])
        ->and($merged[0]->occurredAt)->toBe('2026-10-04T00:00:00Z') // provider timestamp, never the run clock
        ->and($merged[0]->observedAt)->toBe('2026-10-04T06:00:00Z');
    $state->save();

    differ_rrmdir($dir);
});

it('A3: emits pr.retargeted when the base branch changes', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();

    $state = new State($dir);
    $differ->diff([pr_obs()], $state, '2026-10-01T00:00:00Z', true);
    $state->save();

    $state = new State($dir);
    $events = $differ->diff([pr_obs(['base' => 'v1.2-dev', 'publishedAt' => '2026-10-05T00:00:00Z'])], $state, '2026-10-05T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('pr.retargeted')
        ->and($events[0]->change)->toMatchArray(['field' => 'base', 'from' => 'main', 'to' => 'v1.2-dev']);

    differ_rrmdir($dir);
});

it('emits entity.created for an entity first seen after the baseline', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();

    $state = new State($dir);
    $differ->diff([pr_obs()], $state, '2026-10-01T00:00:00Z', true);
    $state->save();

    $state = new State($dir);
    $events = $differ->diff([
        pr_obs(),
        pr_obs([
            'externalId' => 'pr:556',
            'title' => 'feat(spec): add protobuf support',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/556',
            'publishedAt' => '2026-10-06T00:00:00Z',
        ]),
    ], $state, '2026-10-06T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('entity.created')
        ->and($events[0]->entityId)->toBe('gh:OAI/Arazzo-Specification#556');

    differ_rrmdir($dir);
});

it('never emits a tag event for a version that has a release', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $observations = [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'release', 'externalId' => 'release:1.1.0', 'title' => 'Arazzo 1.1.0', 'url' => 'u', 'publishedAt' => '2026-05-17T00:00:00Z', 'labels' => []],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'tag', 'externalId' => 'tag:1.1.0', 'title' => 'tag 1.1.0', 'url' => 'u2', 'publishedAt' => '2026-10-13T00:00:00Z', 'labels' => []],
    ];

    $events = $differ->diff($observations, $state, '2026-10-21T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('release.published')
        ->and($events[0]->occurredAt)->toBe('2026-05-17T00:00:00Z')
        ->and($state->entity('gh:OAI/Arazzo-Specification#release:1.1.0'))->not->toBeNull()
        ->and($state->entity('gh:OAI/Arazzo-Specification#tag:1.1.0'))->toBeNull();

    differ_rrmdir($dir);
});

it('uses a provider timestamp for a tag-only repo', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([[
        'source' => 'b-lab-io/pyarazzo', 'type' => 'tag', 'externalId' => 'tag:v1.0.0',
        'title' => 'tag v1.0.0', 'url' => 'u', 'publishedAt' => '2026-04-01T00:00:00Z', 'labels' => [],
    ]], $state, '2026-10-21T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('entity.created')
        ->and($events[0]->occurredAt)->toBe('2026-04-01T00:00:00Z');

    differ_rrmdir($dir);
});

it('emits issue.state_changed and meeting.agenda for issues', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();

    $issue = ['source' => 'OAI/Arazzo-Specification', 'type' => 'issue', 'externalId' => 'issue:410', 'title' => '1.2 - start of discussion', 'url' => 'u', 'publishedAt' => '2026-01-01T00:00:00Z', 'labels' => [], 'state' => 'open'];

    $state = new State($dir);
    $differ->diff([$issue], $state, '2026-10-01T00:00:00Z', true);
    $state->save();

    $state = new State($dir);
    $closed = $differ->diff([array_merge($issue, ['state' => 'closed', 'publishedAt' => '2026-10-07T00:00:00Z'])], $state, '2026-10-07T06:00:00Z');
    expect($closed)->toHaveCount(1)
        ->and($closed[0]->kind)->toBe('issue.state_changed')
        ->and($closed[0]->change)->toMatchArray(['from' => 'open', 'to' => 'closed']);
    $state->save();

    $state = new State($dir);
    $meeting = $differ->diff([[
        'source' => 'OAI/Arazzo-Specification', 'type' => 'issue', 'externalId' => 'issue:597',
        'title' => 'Meeting: Oct 14th, 2026', 'url' => 'u3', 'publishedAt' => '2026-10-08T00:00:00Z', 'labels' => [], 'state' => 'open',
    ]], $state, '2026-10-08T06:00:00Z');

    expect($meeting)->toHaveCount(1)
        ->and($meeting[0]->kind)->toBe('meeting.agenda');

    differ_rrmdir($dir);
});

it('emits package.version for a new npm release', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([[
        'source' => 'speclynx/apidom', 'type' => 'release', 'externalId' => 'npm:1.2.3',
        'title' => '@speclynx/apidom@1.2.3', 'url' => 'u', 'publishedAt' => '2026-10-09T00:00:00Z', 'labels' => [],
    ]], $state, '2026-10-09T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('package.version')
        ->and($events[0]->entityId)->toBe('npm:speclynx/apidom#1.2.3');

    differ_rrmdir($dir);
});

it('carries Scorer output (tier/score/why/breaking/roadmapRefs) onto events', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([pr_obs([
        'topics' => ['wsdl'],
        'roadmapRefs' => ['plan:phase-f-protocol-packages'],
        'tier' => 'act',
        'score' => 87,
        'why' => ['watchlist', 'spec-repo'],
        'breaking' => true,
    ])], $state, '2026-10-21T06:17:10Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('entity.created')
        ->and($events[0]->topics)->toBe(['wsdl'])
        ->and($events[0]->roadmapRefs)->toBe(['plan:phase-f-protocol-packages'])
        ->and($events[0]->tier)->toBe('act')
        ->and($events[0]->score)->toBe(87)
        ->and($events[0]->why)->toBe(['spec-repo', 'watchlist'])
        ->and($events[0]->breaking)->toBeTrue()
        ->and($events[0]->toArray()['breaking'])->toBeTrue();

    differ_rrmdir($dir);
});

it('skips draft releases', function (): void {
    $dir = differ_tmp_dir();
    $differ = new Differ();
    $state = new State($dir);

    $events = $differ->diff([[
        'source' => 'Redocly/redocly-cli', 'type' => 'release', 'externalId' => 'release:v9.0.0',
        'title' => 'redocly-cli v9.0.0', 'url' => 'u', 'publishedAt' => '2026-10-13T00:00:00Z',
        'labels' => [], 'draft' => true,
    ]], $state, '2026-10-13T06:00:00Z');

    expect($events)->toHaveCount(0)
        ->and($state->count())->toBe(0);

    differ_rrmdir($dir);
});
