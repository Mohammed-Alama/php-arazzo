<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\Ingestors\WatchlistIngestor;
use Ecosystem\Scorer;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Watchlist.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Scorer.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Ingestors/WatchlistIngestor.php';

if (!function_exists('watchlist_ingestor_tmp_dir')) {
    function watchlist_ingestor_tmp_dir(): string
    {
        $dir = sys_get_temp_dir().'/ecosystem-wl-ingestor-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}

if (!function_exists('watchlist_ingestor_rrmdir')) {
    function watchlist_ingestor_rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}

/** @param array<string,mixed> $overrides @return array<string,mixed> */
function watchlist_pr_payload(array $overrides = []): array
{
    return array_merge([
        'number' => 533,
        'title' => 'feat(spec): add SOAP support',
        'html_url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        'state' => 'open',
        'draft' => false,
        'merged_at' => null,
        'base' => ['ref' => 'v1.2-dev'],
        'user' => ['login' => 'earth2marsh'],
        'body' => 'Adds first-class WSDL/SOAP support.',
        'created_at' => '2026-07-27T15:46:00Z',
        'updated_at' => '2026-09-29T10:00:00Z',
        'comments' => 4,
        'mergeable' => true,
        'labels' => [['name' => 'enhancement']],
    ], $overrides);
}

it('maps a PR detail payload to a watchlist observation', function (): void {
    $item = [
        'ref' => 'OAI/Arazzo-Specification#533',
        'topic' => 'wsdl',
        'roadmap' => ['spec:plugin-stack-oms-multiprotocol', 'plan:phase-f-protocol-packages'],
        'stance' => 'shipped',
    ];
    $files = [
        ['filename' => 'versions/arazzo.md', 'status' => 'modified'],
        ['filename' => '_archive_/schemas/validation/schema.yaml', 'status' => 'modified'],
    ];

    $obs = WatchlistIngestor::observe($item, watchlist_pr_payload(['draft' => true]), $files, 'pr');

    expect($obs['source'])->toBe('OAI/Arazzo-Specification')
        ->and($obs['type'])->toBe('pr')
        ->and($obs['externalId'])->toBe('pr:533')
        ->and($obs['state'])->toBe('open')
        ->and($obs['draft'])->toBeTrue()
        ->and($obs['merged'])->toBeFalse()
        ->and($obs['base'])->toBe('v1.2-dev')
        ->and($obs['review'])->toBe('none')
        ->and($obs['labels'])->toBe(['enhancement'])
        ->and($obs['files'])->toBe(['_archive_/schemas/validation/schema.yaml', 'versions/arazzo.md'])
        ->and($obs['lastActivity'])->toBe('2026-09-29T10:00:00Z')
        ->and($obs['tags'])->toBe(['wsdl'])
        ->and($obs['roadmap'])->toBe(['spec:plugin-stack-oms-multiprotocol', 'plan:phase-f-protocol-packages'])
        ->and($obs['watchlist'])->toBe('OAI/Arazzo-Specification#533');
});

it('dates a merged PR by the merge and reports the review decision', function (): void {
    $item = ['ref' => 'OAI/Arazzo-Specification#533', 'topic' => 'wsdl', 'roadmap' => [], 'stance' => 'shipped'];

    $merged = WatchlistIngestor::observe($item, watchlist_pr_payload([
        'state' => 'closed',
        'merged_at' => '2026-09-30T17:12:00Z',
        'updated_at' => '2026-09-30T17:12:00Z',
    ]), [], 'pr');
    $approved = WatchlistIngestor::observe($item, watchlist_pr_payload(['reviewDecision' => 'APPROVED']), [], 'pr');

    expect($merged['merged'])->toBeTrue()
        ->and($merged['publishedAt'])->toBe('2026-09-30T17:12:00Z')
        ->and($merged['review'])->toBe('merged')
        ->and($approved['review'])->toBe('approved');
});

it('maps an issue payload (a Meeting: issue stays an issue)', function (): void {
    $item = ['ref' => 'OAI/Arazzo-Specification#597', 'topic' => 'meeting', 'roadmap' => [], 'stance' => 'watch'];

    $obs = WatchlistIngestor::observe($item, [
        'number' => 597,
        'title' => 'Meeting: Oct 14th, 2026',
        'html_url' => 'https://github.com/OAI/Arazzo-Specification/issues/597',
        'state' => 'open',
        'user' => ['login' => 'earth2marsh'],
        'created_at' => '2026-10-08T00:00:00Z',
        'updated_at' => '2026-10-08T12:00:00Z',
        'labels' => [],
    ], [], 'issue');

    expect($obs['type'])->toBe('issue')
        ->and($obs['externalId'])->toBe('issue:597')
        ->and($obs['title'])->toBe('Meeting: Oct 14th, 2026')
        ->and($obs['publishedAt'])->toBe('2026-10-08T12:00:00Z');
});

it('the recorded #533 fixture yields exactly the open -> ready -> merged transitions', function (): void {
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/ecosystem/fixtures/watchlist/533.json'), true);
    $item = [
        'ref' => $fixture['ref'],
        'topic' => $fixture['topic'],
        'roadmap' => $fixture['roadmap'],
        'ourIssue' => $fixture['ourIssue'],
        'stance' => $fixture['stance'],
    ];

    $dir = watchlist_ingestor_tmp_dir();
    $differ = new Differ();

    // Baseline: the draft PR is recorded silently.
    $state = new State($dir);
    $open = WatchlistIngestor::observe($item, $fixture['stages'][0]['payload'], $fixture['stages'][0]['files'], $fixture['stages'][0]['kind']);
    expect($differ->diff([$open], $state, $fixture['stages'][0]['observedAt'], true))->toHaveCount(0);
    $state->save();

    // Ready for review.
    $state = new State($dir);
    $ready = WatchlistIngestor::observe($item, $fixture['stages'][1]['payload'], $fixture['stages'][1]['files'], $fixture['stages'][1]['kind']);
    $readyEvents = $differ->diff([$ready], $state, $fixture['stages'][1]['observedAt']);
    $state->save();

    // Merged into v1.2-dev.
    $state = new State($dir);
    $merged = WatchlistIngestor::observe($item, $fixture['stages'][2]['payload'], $fixture['stages'][2]['files'], $fixture['stages'][2]['kind']);
    $mergedEvents = $differ->diff([$merged], $state, $fixture['stages'][2]['observedAt']);
    $state->save();

    $events = array_merge($readyEvents, $mergedEvents);

    expect($events)->toHaveCount(2)
        ->and($events[0]->kind)->toBe('pr.state_changed')
        ->and($events[0]->change)->toMatchArray(['field' => 'state', 'from' => 'draft', 'to' => 'ready'])
        ->and($events[0]->occurredAt)->toBe('2026-08-14T09:30:00Z')
        ->and($events[0]->topics)->toBe(['wsdl'])
        ->and($events[0]->roadmapRefs)->toBe(['plan:phase-f-protocol-packages', 'spec:plugin-stack-oms-multiprotocol'])
        ->and($events[1]->kind)->toBe('pr.state_changed')
        ->and($events[1]->change)->toMatchArray(['field' => 'state', 'from' => 'ready', 'to' => 'merged', 'base' => 'v1.2-dev'])
        ->and($events[1]->occurredAt)->toBe('2026-09-30T16:32:28Z')
        ->and($events[1]->observedAt)->toBe('2026-10-01T06:17:10Z');

    watchlist_ingestor_rrmdir($dir);
});

it('scores an unscored watchlist observation so its transition reaches Act (P0-1)', function (): void {
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/ecosystem/fixtures/watchlist/533.json'), true);
    $item = [
        'ref' => $fixture['ref'],
        'topic' => $fixture['topic'],
        'roadmap' => $fixture['roadmap'],
        'ourIssue' => $fixture['ourIssue'],
        'stance' => $fixture['stance'],
    ];

    $open = WatchlistIngestor::observe($item, $fixture['stages'][0]['payload'], $fixture['stages'][0]['files'], $fixture['stages'][0]['kind']);
    $merged = WatchlistIngestor::observe($item, $fixture['stages'][2]['payload'], $fixture['stages'][2]['files'], $fixture['stages'][2]['kind']);

    // `observe()` is pure and unscored — the poll pipeline must annotate it (§5.6).
    // Before the P0-1 fix the unscored observation reached `Differ` with a null tier, so the
    // merged transition collapsed into Watch instead of Act.
    expect($open)->not->toHaveKey('tier')
        ->and($merged)->not->toHaveKey('tier');

    $dir = watchlist_ingestor_tmp_dir();
    $differ = new Differ();
    $scorer = Scorer::fromDefault();

    // Baseline the draft silently, then diff the merge *scored with watchlist=true*, exactly
    // as `poll.php` now does.
    $state = new State($dir);
    $differ->diff([$scorer->annotate($open, true)], $state, $fixture['stages'][0]['observedAt'], true);
    $state->save();

    $state = new State($dir);
    $events = $differ->diff([$scorer->annotate($merged, true)], $state, $fixture['stages'][2]['observedAt']);

    watchlist_ingestor_rrmdir($dir);

    $transition = null;
    foreach ($events as $event) {
        if ($event->kind === 'pr.state_changed') {
            $transition = $event;
        }
    }

    expect($transition)->not->toBeNull()
        ->and($transition->tier)->toBe('act')
        ->and($transition->why)->toContain('watchlist-transition');
});

it('emits meeting.agenda for a watchlist Meeting: issue (#597)', function (): void {
    $item = ['ref' => 'OAI/Arazzo-Specification#597', 'topic' => 'meeting', 'roadmap' => [], 'stance' => 'watch'];
    $obs = WatchlistIngestor::observe($item, [
        'number' => 597,
        'title' => 'Meeting: Oct 14th, 2026',
        'html_url' => 'https://github.com/OAI/Arazzo-Specification/issues/597',
        'state' => 'open',
        'created_at' => '2026-10-08T00:00:00Z',
        'updated_at' => '2026-10-08T00:00:00Z',
        'labels' => [],
    ], [], 'issue');

    $dir = watchlist_ingestor_tmp_dir();
    $events = (new Differ())->diff([$obs], new State($dir), '2026-10-08T06:00:00Z');

    expect($events)->toHaveCount(1)
        ->and($events[0]->kind)->toBe('meeting.agenda')
        ->and($events[0]->entityId)->toBe('gh:OAI/Arazzo-Specification#597');

    watchlist_ingestor_rrmdir($dir);
});
