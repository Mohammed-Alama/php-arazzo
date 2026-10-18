<?php

declare(strict_types=1);

use Ecosystem\Deliverer;
use Ecosystem\Event;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Scorer.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Deliverer.php';

/**
 * A v2 Act transition event, shaped like {@see Event::toArray()}.
 *
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function delivery_event(array $overrides = []): array
{
    return array_merge([
        'id' => 'evt_533merged',
        'entityId' => 'gh:OAI/Arazzo-Specification#533',
        'kind' => 'pr.state_changed',
        'source' => 'OAI/Arazzo-Specification',
        'occurredAt' => '2026-10-14T16:32:28Z',
        'observedAt' => '2026-10-16T06:17:00Z',
        'subject' => [
            'type' => 'pr',
            'title' => 'feat(spec): add SOAP support',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        ],
        'change' => ['field' => 'state', 'from' => 'ready', 'to' => 'merged', 'base' => 'v1.2-dev'],
        'topics' => ['wsdl'],
        'roadmapRefs' => ['plan:phase-f-protocol-packages'],
        'tier' => 'act',
        'score' => 120,
        'why' => ['watchlist-transition'],
        'breaking' => false,
    ], $overrides);
}

/**
 * The `gh issue list` row shape for an issue that a plan produced (so a "re-run" sees it).
 *
 * @param  array<string,mixed>  $auto
 * @return array<string,mixed>
 */
function delivery_issue_from_auto(array $auto, int $number): array
{
    return [
        'number' => $number,
        'title' => $auto['title'],
        'url' => "https://github.com/Mohammed-Alama/php-arazzo/issues/{$number}",
        'labels' => array_map(static fn (string $name): array => ['name' => $name], $auto['labels']),
        'body' => $auto['body'],
    ];
}

function delivery_config(bool $autoIssue = false): array
{
    $config = Deliverer::defaults();
    $config['autoIssue'] = $autoIssue;

    return $config;
}

it('labels the ISO week deterministically', function (): void {
    expect(Deliverer::isoWeek('2026-10-14T12:00:00Z'))->toBe('2026-W42')
        ->and(Deliverer::digestTitle('2026-10-14T12:00:00Z'))->toBe('ecosystem-digest: 2026-W42')
        ->and(Deliverer::weekWindow('2026-10-14T12:00:00Z'))->toBe(['2026-10-12T00:00:00Z', '2026-10-19T00:00:00Z'])
        ->and(Deliverer::inWeek('2026-10-12T00:00:00Z', '2026-10-14T12:00:00Z'))->toBeTrue()
        ->and(Deliverer::inWeek('2026-10-19T00:00:00Z', '2026-10-14T12:00:00Z'))->toBeFalse();
});

it('defaults to digest on, auto-issue off, project off', function (): void {
    $config = Deliverer::loadConfig('/nonexistent/delivery.json');

    expect(Deliverer::digestEnabled($config))->toBeTrue()
        ->and(Deliverer::autoIssueEnabled($config))->toBeFalse()
        ->and(Deliverer::projectEnabled($config))->toBeFalse();
});

it('plans the weekly digest and no auto-issue while the gate is off', function (): void {
    $now = '2026-10-14T12:00:00Z';
    $plan = Deliverer::plan([delivery_event()], [], delivery_config(false), $now);

    expect($plan['actCount'])->toBe(1)
        ->and($plan['autoIssues'])->toBe([])
        ->and($plan['digest']['title'])->toBe('ecosystem-digest: 2026-W42')
        ->and($plan['digest']['create'])->toBeTrue()
        ->and($plan['digest']['body'])->toContain('feat(spec): add SOAP support')
        ->toContain('https://github.com/OAI/Arazzo-Specification/pull/533');
});

it('creates exactly one deduped auto-issue per Act entity when opted in', function (): void {
    $now = '2026-10-14T12:00:00Z';
    $events = [
        delivery_event(),
        // A second transition for the same entity must not spawn a second issue.
        delivery_event(['id' => 'evt_533base', 'change' => ['field' => 'base', 'from' => 'main', 'to' => 'v1.2-dev']]),
    ];

    $plan = Deliverer::plan($events, [], delivery_config(true), $now);

    expect($plan['autoIssues'])->toHaveCount(1)
        ->and($plan['autoIssues'][0]['entityId'])->toBe('gh:OAI/Arazzo-Specification#533')
        ->and($plan['autoIssues'][0]['labels'])->toBe(['ecosystem', 'needs-triage'])
        ->and($plan['autoIssues'][0]['upstreamRef'])->toBe('OAI/Arazzo-Specification#533')
        ->and($plan['autoIssues'][0]['body'])->toContain('<!-- ecosystem-entity: gh:OAI/Arazzo-Specification#533 -->')
        ->toContain('### Source')
        ->toContain('### Upstream URL')
        ->toContain('### Impact on php-arazzo')
        ->toContain('### Severity')
        ->and($plan['autoIssues'][0]['body'])->not->toContain('Raw snapshot excerpt');
});

it('honours the --auto-issue override even when the config gate is off', function (): void {
    $plan = Deliverer::plan([delivery_event()], [], delivery_config(false), '2026-10-14T12:00:00Z', true);

    expect($plan['autoIssues'])->toHaveCount(1);
});

it('creates nothing on a re-run once the issues exist', function (): void {
    $now = '2026-10-14T12:00:00Z';
    $first = Deliverer::plan([delivery_event()], [], delivery_config(true), $now);

    // Simulate `gh issue create`: the auto-issue and the digest issue now exist.
    $existing = [
        delivery_issue_from_auto($first['autoIssues'][0], 1840),
        [
            'number' => 1841,
            'title' => $first['digest']['title'],
            'url' => 'https://github.com/Mohammed-Alama/php-arazzo/issues/1841',
            'labels' => [['name' => 'ecosystem-digest']],
            'body' => $first['digest']['body'],
        ],
    ];

    $second = Deliverer::plan([delivery_event()], $existing, delivery_config(true), $now);

    expect($second['autoIssues'])->toBe([])
        ->and($second['digest']['create'])->toBeFalse();
});

it('links the created auto-issue from the weekly digest', function (): void {
    $event = delivery_event();
    $body = Deliverer::digestBody('2026-W42', [$event], [
        'gh:OAI/Arazzo-Specification#533' => ['number' => 1840],
    ]);

    expect($body)->toContain('issue #1840')
        ->toContain('feat(spec): add SOAP support');
});

it('excludes Act events that fall outside the ISO week', function (): void {
    $plan = Deliverer::plan(
        [delivery_event(['occurredAt' => '2026-10-05T12:00:00Z'])],
        [],
        delivery_config(true),
        '2026-10-14T12:00:00Z',
    );

    expect($plan['actCount'])->toBe(0)
        ->and($plan['autoIssues'])->toBe([]);
});

it('requires an owner and number before the project hook is enabled', function (): void {
    $config = Deliverer::defaults();
    $config['project']['enabled'] = true;

    // The `Upstream ref` field the hook stamps is named in config, not hard-coded (§5.7.4).
    expect($config['project']['upstreamRefField'])->toBe('Upstream ref')
        ->and($config['project']['status'])->toBe('Needs triage')
        ->and(Deliverer::projectEnabled($config))->toBeFalse();

    $config['project']['owner'] = 'OAI';
    $config['project']['number'] = 7;
    expect(Deliverer::projectEnabled($config))->toBeTrue();
});

it('deliver.php creates exactly one issue for a simulated Act event, and none on a re-run', function (): void {
    $root = dirname(__DIR__, 4);
    $eventsFixture = __DIR__.'/fixtures/act-events.json';

    $run = function (array $existing) use ($root, $eventsFixture): array {
        $issuesPath = sys_get_temp_dir().'/eco-issues-'.bin2hex(random_bytes(6)).'.json';
        file_put_contents($issuesPath, json_encode($existing));
        $cmd = sprintf(
            'php %s --events=%s --issues=%s --now=2026-10-14T12:00:00Z --auto-issue --json 2>&1',
            escapeshellarg($root.'/scripts/ecosystem/deliver.php'),
            escapeshellarg($eventsFixture),
            escapeshellarg($issuesPath),
        );
        $out = (string) shell_exec($cmd);
        @unlink($issuesPath);
        $decoded = json_decode($out, true);

        return is_array($decoded) ? $decoded : ['raw' => $out];
    };

    $first = $run([]);
    expect($first['autoIssues'] ?? [])->toHaveCount(1)
        ->and($first['digest']['create'] ?? null)->toBeTrue();

    // Re-run with the issues the first run "created": nothing new.
    $existing = [
        delivery_issue_from_auto($first['autoIssues'][0], 1840),
        [
            'number' => 1841,
            'title' => $first['digest']['title'],
            'url' => 'https://github.com/Mohammed-Alama/php-arazzo/issues/1841',
            'labels' => [['name' => 'ecosystem-digest']],
            'body' => $first['digest']['body'],
        ],
    ];
    $second = $run($existing);
    expect($second['autoIssues'] ?? [])->toHaveCount(0)
        ->and($second['digest']['create'] ?? null)->toBeFalse();
});
