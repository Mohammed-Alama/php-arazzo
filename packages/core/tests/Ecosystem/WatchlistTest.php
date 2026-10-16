<?php

declare(strict_types=1);

use Ecosystem\Watchlist;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Watchlist.php';

function watchlist_config_path(): string
{
    return dirname(__DIR__, 4).'/config/ecosystem/watchlist.json';
}

if (!function_exists('watchlist_tmp_dir')) {
    function watchlist_tmp_dir(): string
    {
        $dir = sys_get_temp_dir().'/ecosystem-watchlist-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}

it('seeds the watchlist from the Sept research with corrected stances', function (): void {
    $items = Watchlist::load(watchlist_config_path());

    $byRef = [];
    foreach ($items as $item) {
        $byRef[$item['ref']] = $item;
    }

    expect($items)->toHaveCount(8)
        ->and($byRef)->toHaveKey('OAI/Arazzo-Specification#533')
        // #533 merged 2026-09-30 -> v1.2-dev, so it is shipped, not plan-to-support.
        ->and($byRef['OAI/Arazzo-Specification#533']['stance'])->toBe('shipped')
        ->and($byRef['OAI/Arazzo-Specification#533']['topic'])->toBe('wsdl')
        ->and($byRef['OAI/Arazzo-Specification#533']['roadmap'])
        ->toBe(['spec:plugin-stack-oms-multiprotocol', 'plan:phase-f-protocol-packages'])
        ->and($byRef['OAI/Arazzo-Specification#556']['topic'])->toBe('protobuf')
        ->and($byRef['OAI/Arazzo-Specification#567']['topic'])->toBe('graphql')
        ->and($byRef['OAI/Arazzo-Specification#568']['stance'])->toBe('evaluate')
        // #516 is a PR on v1.1-dev (base is live state, not authored config).
        ->and($byRef['OAI/Arazzo-Specification#516']['stance'])->toBe('watch')
        ->and($byRef['OAI/Arazzo-Specification#597']['topic'])->toBe('meeting');
});

it('parses refs and derives the shared entity id', function (): void {
    expect(Watchlist::parseRef('OAI/Arazzo-Specification#533'))
        ->toBe(['repo' => 'OAI/Arazzo-Specification', 'number' => 533])
        ->and(Watchlist::entityId('OAI/Arazzo-Specification#516'))->toBe('gh:OAI/Arazzo-Specification#516');

    expect(fn () => Watchlist::parseRef('not-a-ref'))->toThrow(RuntimeException::class);
});

it('rejects a watchlist item with an unknown stance', function (): void {
    $dir = watchlist_tmp_dir();
    $path = $dir.'/watchlist.json';
    file_put_contents($path, json_encode([
        'items' => [['ref' => 'OAI/Arazzo-Specification#1', 'stance' => 'whatever']],
    ]));

    expect(fn () => Watchlist::load($path))->toThrow(RuntimeException::class);
});

it('renders one status row per seeded item and joins live state', function (): void {
    $items = [
        ['ref' => 'OAI/Arazzo-Specification#533', 'topic' => 'wsdl', 'roadmap' => ['plan:phase-f-protocol-packages'], 'ourIssue' => null, 'stance' => 'shipped'],
        ['ref' => 'OAI/Arazzo-Specification#556', 'topic' => 'protobuf', 'roadmap' => [], 'ourIssue' => 151, 'stance' => 'plan-to-support'],
    ];

    $observations = [
        [
            'source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:533',
            'title' => 'feat(spec): add SOAP support', 'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
            'state' => 'closed', 'merged' => true, 'draft' => false, 'base' => 'v1.2-dev',
            'lastActivity' => '2026-09-30T17:12:00Z', 'publishedAt' => '2026-09-30T17:12:00Z',
        ],
    ];

    $rows = Watchlist::statusRows($items, $observations);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['ref'])->toBe('OAI/Arazzo-Specification#533')
        ->and($rows[0]['title'])->toBe('feat(spec): add SOAP support')
        ->and($rows[0]['state'])->toBe('merged')
        ->and($rows[0]['base'])->toBe('v1.2-dev')
        ->and($rows[0]['lastChange'])->toBe('2026-09-30')
        ->and($rows[0]['stance'])->toBe('shipped')
        ->and($rows[0]['live'])->toBeTrue()
        // A seeded item that could not be observed this run still gets a row.
        ->and($rows[1]['state'])->toBe('unknown')
        ->and($rows[1]['ourIssue'])->toBe(151)
        ->and($rows[1]['live'])->toBeFalse();
});

it('removing an item records a decision in decisions.json', function (): void {
    $dir = watchlist_tmp_dir();
    $watchlistPath = $dir.'/watchlist.json';
    $decisionsPath = $dir.'/decisions.json';
    file_put_contents($watchlistPath, json_encode([
        'version' => 1,
        'items' => [
            ['ref' => 'OAI/Arazzo-Specification#533', 'stance' => 'shipped'],
            ['ref' => 'OAI/Arazzo-Specification#556', 'stance' => 'plan-to-support'],
        ],
    ]));

    $decision = Watchlist::recordRemoval(
        $watchlistPath,
        $decisionsPath,
        'OAI/Arazzo-Specification#533',
        'shipped in 1.2; no longer a decision point',
        'human',
        '2026-10-16T00:00:00Z',
    );

    $remaining = json_decode((string) file_get_contents($watchlistPath), true);
    $decisions = json_decode((string) file_get_contents($decisionsPath), true);

    expect($remaining['items'])->toHaveCount(1)
        ->and($remaining['items'][0]['ref'])->toBe('OAI/Arazzo-Specification#556')
        ->and($decision['entityId'])->toBe('gh:OAI/Arazzo-Specification#533')
        ->and($decision['status'])->toBe('removed')
        ->and($decisions['decisions'])->toHaveCount(1)
        ->and($decisions['decisions'][0]['reason'])->toBe('shipped in 1.2; no longer a decision point');

    // A second removal appends rather than clobbers.
    Watchlist::recordRemoval($watchlistPath, $decisionsPath, 'OAI/Arazzo-Specification#556', 'consolidated', 'human', '2026-10-16T01:00:00Z');
    $decisions = json_decode((string) file_get_contents($decisionsPath), true);
    expect($decisions['decisions'])->toHaveCount(2);
});
