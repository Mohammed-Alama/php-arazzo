<?php

declare(strict_types=1);

use Ecosystem\Event;
use Ecosystem\Renderer;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Renderer.php';

/**
 * A v2 transition-event row, shaped like {@see Event::toArray()}.
 *
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function digest_event(array $overrides = []): array
{
    return array_merge([
        'id' => 'evt_test',
        'entityId' => 'gh:OAI/Arazzo-Specification#pr:533',
        'kind' => 'pr.state_changed',
        'source' => 'OAI/Arazzo-Specification',
        'occurredAt' => '2026-09-30T12:00:00Z',
        'observedAt' => '2026-10-16T06:17:00Z',
        'subject' => [
            'type' => 'pr',
            'title' => 'feat(spec): add SOAP support',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        ],
        'change' => ['field' => 'state', 'from' => 'open', 'to' => 'merged', 'base' => 'v1.2-dev'],
        'topics' => ['wsdl'],
        'roadmapRefs' => ['plan:phase-f-protocol-packages'],
        'tier' => 'act',
        'score' => 100,
        'why' => ['watchlist-transition'],
        'breaking' => false,
    ], $overrides);
}

/**
 * @return array<int,array<string,mixed>>
 */
function digest_watchlist_rows(): array
{
    return [
        [
            'ref' => 'OAI/Arazzo-Specification#533',
            'topic' => 'wsdl',
            'title' => 'feat(spec): add SOAP support',
            'state' => 'merged',
            'base' => 'v1.2-dev',
            'lastChange' => '2026-09-30',
            'roadmap' => ['plan:phase-f-protocol-packages', 'spec:plugin-stack-oms-multiprotocol'],
            'ourIssue' => null,
            'stance' => 'shipped',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
            'live' => true,
        ],
        [
            'ref' => 'OAI/Arazzo-Specification#597',
            'topic' => 'meeting',
            'title' => 'Meeting: Oct 14th, 2026',
            'state' => 'open',
            'base' => '—',
            'lastChange' => '2026-10-08',
            'roadmap' => [],
            'ourIssue' => 182,
            'stance' => 'watch',
            'url' => 'https://github.com/OAI/Arazzo-Specification/issues/597',
            'live' => true,
        ],
    ];
}

it('renders a watchlist status table with live state for every item', function (): void {
    $md = Renderer::renderDigest([], digest_watchlist_rows(), '2026-10-16T00:00:00Z');

    expect($md)->toContain('## Watchlist — tracked upstream items')
        ->toContain('| Ref | Title | State | Base | Last change | Roadmap | Our issue | Stance |')
        ->toContain('`OAI/Arazzo-Specification#533`')
        ->toContain('[feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533)')
        ->toContain('| merged | v1.2-dev | 2026-09-30 |')
        ->toContain('`OAI/Arazzo-Specification#597`')
        ->toContain('| 182 | watch |')
        // one row per item
        ->and(substr_count($md, '`OAI/Arazzo-Specification#'))->toBe(2);
});

it('notes an empty watchlist explicitly', function (): void {
    $md = Renderer::renderDigest([], [], '2026-10-16T00:00:00Z');

    expect($md)->toContain('## Watchlist — tracked upstream items')
        ->toContain('_No watchlist items configured._');
});

it('A7: renders every Act event since the last digest, each with a link and what changed', function (): void {
    $events = [];
    for ($i = 1; $i <= 20; $i++) {
        $events[] = digest_event([
            'id' => 'evt_'.$i,
            'occurredAt' => sprintf('2026-09-%02dT12:00:00Z', $i),
            'score' => 200 - $i,
            'subject' => [
                'type' => 'pr',
                'title' => 'Act item '.$i,
                'url' => 'https://example.com/act/'.$i,
            ],
        ]);
    }

    $md = Renderer::renderDigest($events, [], '2026-10-16T06:17:00Z');

    // No hard cap: all 20 Act items are present.
    expect($md)->toContain('## Act — since last digest (20)')
        ->toContain('state `open` → `merged`')
        ->toContain('topics `wsdl`')
        ->toContain('→ `plan:phase-f-protocol-packages`')
        ->and(substr_count($md, 'https://example.com/act/'))->toBe(20);
});

it('A7: marks breaking Act items and orders by score', function (): void {
    $md = Renderer::renderDigest([
        digest_event(['id' => 'a', 'score' => 10, 'subject' => ['type' => 'release', 'title' => 'v1.0.0', 'url' => 'https://example.com/a']]),
        digest_event([
            'id' => 'b', 'score' => 99, 'breaking' => true,
            'kind' => 'release.published', 'source' => 'swaggerexpert/arazzo-runtime-expression',
            'subject' => ['type' => 'release', 'title' => 'v3.0.0', 'url' => 'https://example.com/b'],
            'change' => ['field' => 'version', 'from' => '2.0.0', 'to' => '3.0.0'],
        ]),
    ], [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('**breaking** [v3.0.0](https://example.com/b)')
        ->and(strpos($md, 'https://example.com/b'))->toBeLessThan(strpos($md, 'https://example.com/a'));
});

it('A7: collapses Watch items into a details block and keeps meetings out of it', function (): void {
    $md = Renderer::renderDigest([
        digest_event([
            'id' => 'w1', 'tier' => 'watch', 'score' => 9, 'why' => ['peer-event'],
            'kind' => 'entity.created', 'source' => 'usearazzo/arazzo-toolkit',
            'subject' => ['type' => 'issue', 'title' => 'embedded runtime expressions sent verbatim', 'url' => 'https://example.com/watch'],
            'change' => ['field' => 'state', 'from' => null, 'to' => 'open'],
            'topics' => [], 'roadmapRefs' => [],
        ]),
        digest_event([
            'id' => 'm1', 'tier' => 'watch', 'why' => ['meeting-agenda'],
            'kind' => 'meeting.agenda', 'source' => 'OAI/Arazzo-Specification',
            'subject' => ['type' => 'issue', 'title' => 'Meeting: Oct 14th, 2026', 'url' => 'https://example.com/meeting'],
            'change' => ['field' => 'state', 'from' => null, 'to' => 'open'],
            'topics' => [], 'roadmapRefs' => [],
        ]),
    ], [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('## Watch — 1 collapsed')
        ->toContain('<details>')
        ->toContain('<summary>1 topic-matched items — click to expand</summary>')
        ->toContain('[embedded runtime expressions sent verbatim](https://example.com/watch)')
        // the meeting has its own section and is not repeated in the collapsed Watch list
        ->toContain('## Meeting agenda')
        ->toContain('[Meeting: Oct 14th, 2026](https://example.com/meeting)')
        ->and(substr_count($md, 'https://example.com/meeting'))->toBe(1);
});

it('A7: groups peer releases per library inside collapsed blocks', function (): void {
    $md = Renderer::renderDigest([
        digest_event([
            'id' => 'r1', 'tier' => 'act', 'kind' => 'release.published', 'source' => 'swaggerexpert/arazzo-runtime-expression',
            'subject' => ['type' => 'release', 'title' => 'v3.0.0', 'url' => 'https://example.com/r1'],
            'change' => ['field' => 'version', 'from' => '2.0.0', 'to' => '3.0.0'],
            'topics' => [], 'roadmapRefs' => [],
        ]),
        digest_event([
            'id' => 'r2', 'tier' => 'watch', 'kind' => 'release.published', 'source' => 'strefethen/arazzo-cli',
            'subject' => ['type' => 'release', 'title' => 'v1.2.3', 'url' => 'https://example.com/r2'],
            'change' => ['field' => 'version', 'from' => '1.2.2', 'to' => '1.2.3'],
            'topics' => [], 'roadmapRefs' => [],
        ]),
    ], [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('## Peer releases')
        ->toContain('<summary><code>swaggerexpert/arazzo-runtime-expression</code> — 1</summary>')
        ->toContain('<summary><code>strefethen/arazzo-cli</code> — 1</summary>')
        ->toContain('version `1.2.2` → `1.2.3`');
});

it('A7: counts every suppressed noise category in one line', function (): void {
    $md = Renderer::renderDigest([
        digest_event(['id' => 'n1', 'tier' => 'noise', 'why' => ['bot-author', 'noise-title'], 'topics' => [], 'roadmapRefs' => []]),
        digest_event(['id' => 'n2', 'tier' => 'noise', 'why' => ['bot-author'], 'topics' => [], 'roadmapRefs' => []]),
        digest_event(['id' => 'n3', 'tier' => 'noise', 'why' => ['noise-source'], 'topics' => [], 'roadmapRefs' => []]),
        digest_event(['id' => 'n4', 'tier' => 'noise', 'why' => ['prerelease-release'], 'topics' => [], 'roadmapRefs' => []]),
    ], [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('suppressed: 2 bot PRs, 1 bulk commit, 1 prerelease')
        // noise is counted, never listed
        ->and(substr_count($md, 'feat(spec): add SOAP support'))->toBe(0);
});

it('A7: renders suppressed: none when there is no noise', function (): void {
    $md = Renderer::renderDigest([digest_event()], [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('suppressed: none');
});

it('A7: never fabricates a date for an event without a provider timestamp', function (): void {
    $md = Renderer::renderDigest([
        digest_event([
            'id' => 'no-date',
            'occurredAt' => '',
            'observedAt' => '2026-10-16T06:17:00Z',
            'subject' => ['type' => 'tag', 'title' => 'synthetic tag', 'url' => 'https://example.com/synthetic'],
        ]),
    ], [], '2026-11-01T06:17:00Z');

    expect($md)->toContain('## Act — since last digest (0)')
        ->not->toContain('synthetic tag')
        ->not->toContain('2026-10-16');
});

it('A10: reuses the previous Generated timestamp when the body is unchanged', function (): void {
    $events = [digest_event()];

    $first = Renderer::renderDigest($events, [], '2026-10-16T06:17:00Z');
    $second = Renderer::renderDigest($events, [], '2026-10-17T06:17:00Z', $first);

    expect($second)->toBe($first) // byte-identical: no timestamp-only diff → no commit
        ->toContain('2026-10-16T06:17:00Z')
        ->not->toContain('2026-10-17T06:17:00Z');
});

it('updates the Generated timestamp when the body changes', function (): void {
    $first = Renderer::renderDigest([digest_event()], [], '2026-10-16T06:17:00Z');
    $second = Renderer::renderDigest([
        digest_event(),
        digest_event(['id' => 'evt_new', 'occurredAt' => '2026-10-17T00:00:00Z', 'subject' => ['type' => 'pr', 'title' => 'new thing', 'url' => 'https://example.com/new']]),
    ], [], '2026-10-17T06:17:00Z', $first);

    expect($second)->toContain('2026-10-17T06:17:00Z')
        ->toContain('## Act — since last digest (2)')
        ->and($second)->not->toBe($first);
});
