<?php

declare(strict_types=1);

use Ecosystem\Decisions;
use Ecosystem\Triage;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Decisions.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Triage.php';

/**
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function triage_event(array $overrides = []): array
{
    return array_merge([
        'id' => 'evt_1',
        'entityId' => 'gh:OAI/Arazzo-Specification#533',
        'source' => 'OAI/Arazzo-Specification',
        'occurredAt' => '2026-10-14T16:32:28Z',
        'subject' => [
            'title' => 'feat(spec): add SOAP support',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        ],
        'topics' => ['wsdl'],
        'roadmapRefs' => ['plan:phase-f-protocol-packages'],
        'tier' => 'act',
        'score' => 120,
        'breaking' => false,
    ], $overrides);
}

function triage_topics_config(): array
{
    return [
        'roadmaps' => [
            'plan:phase-f-protocol-packages' => ['title' => 'Phase F — protocol packages'],
        ],
        'topics' => [
            'wsdl' => ['title' => 'Support WSDL/SOAP source type', 'roadmap' => ['plan:phase-f-protocol-packages']],
            'protobuf' => ['title' => 'Support protobuf source type', 'roadmap' => ['plan:phase-f-protocol-packages']],
        ],
    ];
}

function triage_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-triage-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

it('drops events settled in decisions.json so they do not resurface', function (): void {
    $events = [
        triage_event(),
        triage_event(['id' => 'evt_2', 'entityId' => 'gh:OAI/Arazzo-Specification#556', 'topics' => ['protobuf']]),
    ];
    $decisions = [['entityId' => 'gh:OAI/Arazzo-Specification#533', 'status' => 'reviewed']];

    $open = Triage::filterDecided($events, $decisions);

    expect($open)->toHaveCount(1)
        ->and($open[0]['entityId'])->toBe('gh:OAI/Arazzo-Specification#556');
});

it('groups one task per roadmap ref, not per relevance bucket', function (): void {
    $events = [
        triage_event(),
        triage_event(['id' => 'evt_2', 'entityId' => 'gh:OAI/Arazzo-Specification#556', 'topics' => ['protobuf']]),
        triage_event(['id' => 'evt_3', 'entityId' => 'gh:OAI/Arazzo-Specification#597', 'topics' => ['meeting'], 'roadmapRefs' => []]),
        triage_event(['id' => 'evt_4', 'entityId' => 'gh:strefethen/arazzo-cli#99', 'topics' => ['cli'], 'roadmapRefs' => []]),
    ];

    $groups = Triage::groupByRoadmap($events);

    expect($groups)->toHaveKeys(['plan:phase-f-protocol-packages', 'unmapped:meeting', 'unmapped:cli'])
        ->and($groups['plan:phase-f-protocol-packages'])->toHaveCount(2)
        ->and($groups['unmapped:meeting'])->toHaveCount(1)
        ->and($groups['unmapped:cli'])->toHaveCount(1);
});

it('sources task titles from topics.json and watchlist-status.json, never hard-coded', function (): void {
    $group = [triage_event()];

    // topics.json `roadmaps` title wins when there is no watchlist row.
    expect(Triage::titleFor('plan:phase-f-protocol-packages', $group, triage_topics_config(), []))
        ->toBe('Phase F — protocol packages');

    // A live watchlist title outranks the authored roadmap title.
    expect(Triage::titleFor('plan:phase-f-protocol-packages', $group, triage_topics_config(), [
        ['ref' => 'OAI/Arazzo-Specification#533', 'title' => 'feat(spec): add SOAP support', 'roadmap' => ['plan:phase-f-protocol-packages']],
    ]))->toBe('feat(spec): add SOAP support');

    // Falls back to the group's topic title, then a humanized ref.
    expect(Triage::titleFor('plan:custom-target', $group, triage_topics_config(), []))
        ->toBe('Support WSDL/SOAP source type')
        ->and(Triage::titleFor('unmapped:meeting', [triage_event(['topics' => ['meeting']])], triage_topics_config(), []))
        ->toBe('Meeting');
});

it('correlates via explicit ourIssue and decisions before keyword fallback', function (): void {
    $group = [triage_event()];

    $related = Triage::relatedFor(
        'plan:phase-f-protocol-packages',
        $group,
        [],
        [],
        [['ref' => 'OAI/Arazzo-Specification#533', 'roadmap' => ['plan:phase-f-protocol-packages'], 'ourIssue' => 151]],
    );

    expect($related)->toBe([['ref' => '#151', 'why' => 'watchlist ourIssue']]);
});

it('excludes arazzo/spec/openapi from the fallback keyword correlation', function (): void {
    $group = [triage_event()];

    // A PR whose only overlap is the generic words must not correlate.
    $generic = [['number' => 1, 'title' => 'arazzo spec openapi', 'body' => 'arazzo spec openapi', 'labels' => []]];
    expect(Triage::relatedFor('plan:phase-f-protocol-packages', $group, [], $generic, []))->toBe([]);

    // A PR that shares a real topic keyword does.
    $specific = [['number' => 42, 'title' => 'add WSDL support', 'body' => '', 'labels' => []]];
    $hits = Triage::relatedFor('plan:phase-f-protocol-packages', $group, [], $specific, []);

    expect($hits)->toHaveCount(1)
        ->and($hits[0]['ref'])->toBe('#42');

    expect(Triage::keywords('arazzo spec openapi wsdl'))->toBe(['wsdl']);
});

it('records a reviewed decision idempotently and retires the item', function (): void {
    $dir = triage_tmp_dir();
    $path = $dir.'/decisions.json';

    $first = Decisions::markReviewed($path, 'evt_1', 'gh:OAI/Arazzo-Specification#533', 'human', '2026-10-16T00:00:00Z', 'handled in #151');
    $second = Decisions::markReviewed($path, 'evt_1', 'gh:OAI/Arazzo-Specification#533', 'human', '2026-10-16T01:00:00Z');

    $rows = Decisions::load($path);

    expect($rows)->toHaveCount(1)
        ->and($first['status'])->toBe('reviewed')
        ->and($first['reason'])->toBe('handled in #151')
        ->and($second)->toBe($first)
        ->and(Decisions::isSettled($rows, 'evt_1'))->toBeTrue()
        ->and(Decisions::isSettled($rows, 'gh:OAI/Arazzo-Specification#533'))->toBeTrue();

    // The settled item no longer appears in triage.
    expect(Triage::filterDecided([triage_event()], $rows))->toBe([]);
});
