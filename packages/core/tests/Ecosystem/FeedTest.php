<?php

declare(strict_types=1);

use Ecosystem\Differ;
use Ecosystem\Event;
use Ecosystem\FeedEvent;
use Ecosystem\Normalizer;
use Ecosystem\Renderer;
use Ecosystem\Scorer;
use Ecosystem\State;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/FeedEvent.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Event.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/RelevanceMapper.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Normalizer.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Scorer.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/State.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Differ.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Renderer.php';

/**
 * @return array<int,array<string,mixed>>
 */
function feed_appendix_b_fixtures(): array
{
    $path = __DIR__.'/fixtures/appendix-b.json';
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("Cannot read Appendix B fixtures at {$path}");
    }

    return $data;
}

/**
 * @return array<string,array{0:array<string,mixed>}>
 */
function feed_appendix_b_datasets(): array
{
    $sets = [];
    foreach (feed_appendix_b_fixtures() as $fixture) {
        $sets[$fixture['name']] = [$fixture];
    }

    return $sets;
}

/**
 * Run the full normalize → score pipeline, the way `poll.php` does.
 *
 * @param  array<string,mixed>  $observation
 * @return array<string,mixed>
 */
function feed_pipeline(array $observation, bool $watchlist = false): array
{
    $event = Normalizer::normalize($observation);

    return Scorer::fromDefault()->score(array_merge($event->raw, [
        'topics' => $event->tags,
    ]), $watchlist);
}

it('v2: classifies every Appendix B fixture to the expected tier/topics through Normalizer + Scorer', function (array $fixture): void {
    $event = Normalizer::normalize($fixture['observation']);
    $result = Scorer::fromDefault()->score(array_merge($event->raw, ['topics' => $event->tags]), (bool) ($fixture['watchlist'] ?? false));
    $expected = $fixture['expected'];

    expect($result['tier'])->toBe($expected['tier']);

    if (array_key_exists('topics', $expected)) {
        if ($expected['topics'] === []) {
            expect($result['topics'])->toBe([]);
        } else {
            foreach ($expected['topics'] as $topic) {
                expect($result['topics'])->toContain($topic);
            }
        }
    }
    foreach ($expected['roadmap_contains'] ?? [] as $ref) {
        expect($result['roadmapRefs'])->toContain($ref);
    }
    foreach ($expected['why_contains'] ?? [] as $reason) {
        expect($result['why'])->toContain($reason);
    }
    if (array_key_exists('breaking', $expected)) {
        expect($result['breaking'])->toBe($expected['breaking']);
    }
})->with(feed_appendix_b_datasets());

it('normalizes PR #533 SOAP to the wsdl topic and carries the Phase F ref through the pipeline', function (): void {
    $raw = [
        'source' => 'OAI/Arazzo-Specification',
        'type' => 'pr',
        'externalId' => 'pr:533',
        'title' => 'feat(spec): add SOAP support',
        'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
        'publishedAt' => '2026-07-27T15:46:00Z',
        'body' => 'Adds first-class WSDL support to Arazzo 1.2 via wsdl type WSDL 1.1 and 2.0. operationId reuse, MUST NOT operationPath',
        'labels' => ['enhancement'],
        'state' => 'open',
        'merged' => false,
    ];

    $ev = Normalizer::normalize($raw);
    $scored = feed_pipeline($raw);

    expect($ev->source)->toBe('OAI/Arazzo-Specification')
        ->and($ev->type)->toBe('pr')
        ->and($ev->tags)->toContain('wsdl')
        ->and($ev->tags)->not->toContain('breaking')
        ->and($ev->id)->toBe(FeedEvent::makeId('OAI/Arazzo-Specification', 'pr:533'))
        ->and($scored['tier'])->toBe(Scorer::WATCH)
        ->and($scored['topics'])->toContain('wsdl')
        ->and($scored['roadmapRefs'])->toContain('plan:phase-f-protocol-packages');
});

it('normalizes issue #410 actor/loop/mcp topics and scores it Watch', function (): void {
    $raw = [
        'source' => 'OAI/Arazzo-Specification',
        'type' => 'issue',
        'externalId' => 'issue:410',
        'title' => '1.2 - start of discussion/ideas/breaking changes',
        'url' => 'https://github.com/OAI/Arazzo-Specification/issues/410',
        'publishedAt' => '2025-11-27T00:00:00Z',
        'body' => 'kind discriminator human in the loop and mcp types transformation function type loops goto',
        'labels' => [],
        'state' => 'open',
    ];

    $ev = Normalizer::normalize($raw);

    expect($ev->tags)->toContain('mcp')
        ->and($ev->tags)->toContain('actor-in-loop')
        ->and($ev->tags)->toContain('loop')
        ->and($ev->tags)->not->toContain('a2a')
        ->and($ev->tags)->not->toContain('actor')
        ->and(feed_pipeline($raw)['tier'])->toBe(Scorer::WATCH);
});

it('normalizes 1.1.0 release with xml/xpath and keeps release severity as a legacy hint', function (): void {
    $raw = [
        'source' => 'OAI/Arazzo-Specification',
        'type' => 'release',
        'externalId' => 'release:1.1.0',
        'title' => 'Arazzo 1.1.0 Released!',
        'url' => 'https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0',
        'publishedAt' => '2026-05-17T00:00:00Z',
        'body' => 'AsyncAPI sourceDescriptions, $self, in: querystring, ABNF runtime expressions, truthy/falsy, application/xml payload, targetSelectorType xpath',
        'labels' => [],
        'state' => 'published',
    ];

    $ev = Normalizer::normalize($raw);

    expect($ev->tags)->toContain('xml')
        ->and($ev->tags)->toContain('xpath')
        ->and($ev->severity)->toBe('actionable') // release severity is a legacy hint; the Scorer tier is authoritative
        ->and(feed_pipeline($raw)['tier'])->toBe(Scorer::ACT); // spec-repo release
});

it('deduplicates by id', function (): void {
    $a = ['source' => 'X', 'type' => 'pr', 'externalId' => 'pr:1', 'title' => 'soap wsdl', 'url' => 'https://example.com/1', 'publishedAt' => '2026-01-01T00:00:00Z', 'body' => '', 'labels' => []];
    $b = ['source' => 'X', 'type' => 'pr', 'externalId' => 'pr:1', 'title' => 'soap wsdl duplicate', 'url' => 'https://example.com/1', 'publishedAt' => '2026-01-01T00:00:00Z', 'body' => '', 'labels' => []];

    $events = Normalizer::normalizeMany([$a, $b]);

    expect($events)->toHaveCount(1);
});

it('maps the criterion-regex topic to the parity-hardening ref', function (): void {
    $raw = [
        'source' => 'OAI/Arazzo-Specification',
        'type' => 'pr',
        'externalId' => 'pr:516',
        'title' => 'fix(spec): specify ECMA-262 dialect for regex Criterion',
        'url' => 'https://github.com/OAI/Arazzo-Specification/pull/516',
        'publishedAt' => '2026-07-09T00:00:00Z',
        'body' => 'regex Criterion ECMA-262',
        'labels' => [],
        'state' => 'open',
        'merged' => false,
    ];

    $ev = Normalizer::normalize($raw);
    $scored = feed_pipeline($raw);

    expect($ev->tags)->toContain('criterion-regex')
        ->and($ev->tags)->not->toContain('spec')
        ->and($ev->relevance)->toBe('plan:expression-evaluation-parity-hardening')
        ->and($scored['tier'])->toBe(Scorer::WATCH)
        ->and($scored['topics'])->toContain('criterion-regex');
});

it('uses word boundaries so refactor/api-extractor do not match actor', function (): void {
    $refactor = Normalizer::normalize([
        'source' => 'Mohammed-Alama/php-arazzo', 'type' => 'commit', 'externalId' => 'commit:abc',
        'title' => 'refactor: extract framework-agnostic engine into arazzo-core (Plan A)',
        'url' => 'https://github.com/Mohammed-Alama/php-arazzo/commit/abc',
        'publishedAt' => '2026-10-13T00:00:00Z', 'body' => '', 'labels' => [],
    ]);
    $depbump = Normalizer::normalize([
        'source' => 'usearazzo/arazzo-toolkit', 'type' => 'pr', 'externalId' => 'pr:9',
        'title' => 'chore(deps-dev): bump @microsoft/api-extractor 7.59.2→7.59.3',
        'url' => 'https://github.com/usearazzo/arazzo-toolkit/pull/9',
        'publishedAt' => '2026-10-13T00:00:00Z', 'body' => '', 'labels' => [],
    ]);

    expect($refactor->tags)->not->toContain('actor')
        ->and($refactor->tags)->not->toContain('actor-in-loop')
        ->and($depbump->tags)->not->toContain('actor');
});

it('never matches keywords in the URL', function (): void {
    $ev = Normalizer::normalize([
        'source' => 'strefethen/arazzo-cli', 'type' => 'issue', 'externalId' => 'issue:99',
        'title' => 'Fix typo in README',
        'url' => 'https://github.com/strefethen/arazzo-cli/issues/99',
        'publishedAt' => '2026-10-13T00:00:00Z', 'body' => 'a small docs fix', 'labels' => [],
    ]);

    expect($ev->tags)->not->toContain('cli');
});

it('does not scan bot/dep-bump bodies, so they never become Act or breaking', function (): void {
    $raw = [
        'source' => 'OAI/learn.openapis.org', 'type' => 'pr', 'externalId' => 'pr:12',
        'title' => 'build(deps): bump actions/checkout from 6 to 7',
        'url' => 'https://github.com/OAI/learn.openapis.org/pull/12',
        'publishedAt' => '2026-10-13T00:00:00Z',
        'body' => 'Bumps actions/checkout from 6 to 7. Release notes contain a breaking change and SHA a2a1b2c3.',
        'labels' => [], 'author' => 'dependabot[bot]', 'state' => 'open', 'merged' => false,
    ];

    $ev = Normalizer::normalize($raw);
    $scored = feed_pipeline($raw);

    expect($ev->tags)->not->toContain('a2a')
        ->and($ev->tags)->not->toContain('breaking')
        ->and($scored['tier'])->toBe(Scorer::NOISE)
        ->and($scored['breaking'])->toBeFalse();
});

it('retires breaking as a tag/severity — it is derived on Act items only', function (): void {
    $bodyOnly = Normalizer::normalize([
        'source' => 'stoplightio/spectral', 'type' => 'pr', 'externalId' => 'pr:5',
        'title' => 'update ruleset docs',
        'url' => 'https://github.com/stoplightio/spectral/pull/5',
        'publishedAt' => '2026-10-13T00:00:00Z', 'body' => 'This is a breaking change to the ruleset.', 'labels' => [],
    ]);
    $labelled = Normalizer::normalize([
        'source' => 'Redocly/redocly-cli', 'type' => 'release', 'externalId' => 'release:v9.0.0',
        'title' => 'redocly-cli v9.0.0',
        'url' => 'https://github.com/Redocly/redocly-cli/releases/tag/v9.0.0',
        'publishedAt' => '2026-10-13T00:00:00Z', 'body' => '', 'labels' => ['breaking'],
    ]);

    expect($bodyOnly->severity)->not->toBe('breaking')
        ->and($bodyOnly->tags)->not->toContain('breaking')
        ->and($labelled->severity)->not->toBe('breaking')
        ->and($labelled->tags)->not->toContain('breaking');
});

it('drops events with no real timestamp', function (): void {
    $events = Normalizer::normalizeMany([
        ['source' => 'b-lab-io/pyarazzo', 'type' => 'tag', 'externalId' => 'tag:v1.0.0', 'title' => 'tag v1.0.0', 'url' => 'https://github.com/b-lab-io/pyarazzo/releases/tag/v1.0.0', 'publishedAt' => '', 'body' => '', 'labels' => []],
    ]);

    expect($events)->toHaveCount(0);
});

if (!function_exists('feed_rrmdir')) {
    function feed_rrmdir(string $dir): void
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

function feed_tmp_dir(): string
{
    $dir = sys_get_temp_dir().'/ecosystem-feed-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

it('A7: a ~30-day replay renders a complete digest — all Act, Watch collapsed, noise counted', function (): void {
    $dir = feed_tmp_dir();
    $state = new State($dir);

    // A ~30-day window of observations (Sep 20 → Oct 13, 2026), pre-scored the way `poll.php` does.
    $observations = [
        // Act: watchlist transition, tracked-library release, spec release and spec-text change.
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:533', 'title' => 'feat(spec): add SOAP support', 'url' => 'https://example.com/act/533', 'publishedAt' => '2026-09-30T12:00:00Z', 'state' => 'closed', 'merged' => true, 'base' => 'v1.2-dev', 'labels' => [], 'tier' => 'act', 'why' => ['watchlist-transition'], 'topics' => ['wsdl'], 'roadmapRefs' => ['plan:phase-f-protocol-packages'], 'breaking' => false],
        ['source' => 'swaggerexpert/arazzo-runtime-expression', 'type' => 'release', 'externalId' => 'release:v3.0.0', 'title' => 'v3.0.0', 'url' => 'https://example.com/act/v3', 'publishedAt' => '2026-09-22T00:00:00Z', 'labels' => [], 'tier' => 'act', 'why' => ['tracked-library-release'], 'topics' => [], 'roadmapRefs' => [], 'breaking' => true],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'release', 'externalId' => 'release:1.2.0', 'title' => 'Arazzo 1.2.0', 'url' => 'https://example.com/act/1.2.0', 'publishedAt' => '2026-10-01T00:00:00Z', 'labels' => [], 'tier' => 'act', 'why' => ['spec-repo-release'], 'topics' => [], 'roadmapRefs' => [], 'breaking' => true],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'spec', 'externalId' => 'spec:v1.2-dev', 'title' => 'spec text changed on v1.2-dev', 'url' => 'https://example.com/act/spec', 'publishedAt' => '2026-10-05T00:00:00Z', 'branch' => 'v1.2-dev', 'headSha' => 'abcdef1234567890', 'files' => ['versions/1.2.0.md'], 'labels' => [], 'tier' => 'act', 'why' => ['spec-text-changed'], 'topics' => [], 'roadmapRefs' => [], 'breaking' => false],
        // Watch: a peer conformance-test idea and a criterion-regex proposal.
        ['source' => 'usearazzo/arazzo-toolkit', 'type' => 'issue', 'externalId' => 'issue:129', 'title' => 'embedded runtime expressions sent verbatim', 'url' => 'https://example.com/watch/129', 'publishedAt' => '2026-09-20T00:00:00Z', 'state' => 'open', 'labels' => [], 'tier' => 'watch', 'why' => ['peer-event'], 'topics' => [], 'roadmapRefs' => []],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:516', 'title' => 'specify ECMA-262 dialect for regex Criterion', 'url' => 'https://example.com/watch/516', 'publishedAt' => '2026-09-25T00:00:00Z', 'state' => 'open', 'labels' => [], 'tier' => 'watch', 'why' => ['topic-matched'], 'topics' => ['criterion-regex'], 'roadmapRefs' => ['plan:expression-evaluation-parity-hardening']],
        // Meeting agenda.
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'issue', 'externalId' => 'issue:597', 'title' => 'Meeting: Oct 14th, 2026', 'url' => 'https://example.com/meeting/597', 'publishedAt' => '2026-10-08T00:00:00Z', 'state' => 'open', 'labels' => [], 'tier' => 'watch', 'why' => ['meeting-agenda'], 'topics' => [], 'roadmapRefs' => []],
        // Noise: bot PR, bulk commit, prerelease and a dependency bump — counted, never listed.
        ['source' => 'OAI/learn.openapis.org', 'type' => 'pr', 'externalId' => 'pr:12', 'title' => 'build(deps): bump actions/checkout', 'url' => 'https://example.com/noise/bot', 'publishedAt' => '2026-10-10T00:00:00Z', 'labels' => [], 'author' => 'dependabot[bot]', 'tier' => 'noise', 'why' => ['bot-author'], 'topics' => [], 'roadmapRefs' => []],
        ['source' => 'jentic/jentic-public-apis', 'type' => 'commit', 'externalId' => 'commit:def', 'title' => 'Rebuild apis.json', 'url' => 'https://example.com/noise/bulk', 'publishedAt' => '2026-10-11T00:00:00Z', 'labels' => [], 'tier' => 'noise', 'why' => ['noise-source'], 'topics' => [], 'roadmapRefs' => []],
        ['source' => 'Redocly/redocly-cli', 'type' => 'release', 'externalId' => 'release:v1.0.0-beta.116', 'title' => 'v1.0.0-beta.116', 'url' => 'https://example.com/noise/beta', 'publishedAt' => '2026-10-12T00:00:00Z', 'labels' => [], 'prerelease' => true, 'tier' => 'noise', 'why' => ['prerelease-release'], 'topics' => [], 'roadmapRefs' => []],
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:8', 'title' => 'chore(deps): bump respec', 'url' => 'https://example.com/noise/bump', 'publishedAt' => '2026-10-13T00:00:00Z', 'labels' => [], 'tier' => 'noise', 'why' => ['noise-title'], 'topics' => [], 'roadmapRefs' => []],
    ];

    $events = (new Differ())->diff($observations, $state, '2026-10-16T06:17:00Z');
    $rows = array_map(static fn (Event $event): array => $event->toArray(), $events);
    $md = Renderer::renderDigest($rows, [], '2026-10-16T06:17:00Z');

    expect($md)->toContain('## Act — since last digest (4)')
        ->toContain('https://example.com/act/533')
        ->toContain('https://example.com/act/v3')
        ->toContain('https://example.com/act/1.2.0')
        ->toContain('https://example.com/act/spec')
        ->toContain('## Watch — 2 collapsed')
        ->toContain('<details>')
        ->toContain('## Meeting agenda')
        ->toContain('[Meeting: Oct 14th, 2026](https://example.com/meeting/597)')
        ->toContain('suppressed: 1 bot PR, 1 bulk commit, 1 prerelease, 1 dependency/CI noise')
        // noise is counted, never linked
        ->and($md)->not->toContain('https://example.com/noise/');

    feed_rrmdir($dir);
});
