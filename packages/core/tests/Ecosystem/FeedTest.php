<?php

declare(strict_types=1);

use Ecosystem\FeedEvent;
use Ecosystem\Normalizer;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/FeedEvent.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/RelevanceMapper.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Normalizer.php';

it('normalizes PR #533 SOAP to the wsdl topic', function (): void {
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

    expect($ev->source)->toBe('OAI/Arazzo-Specification')
        ->and($ev->type)->toBe('pr')
        ->and($ev->tags)->toContain('wsdl')
        ->and($ev->tags)->not->toContain('breaking')
        ->and($ev->severity)->toBe('watch')
        ->and($ev->relevance)->toBe('plan:phase-f-protocol-packages')
        ->and($ev->id)->toBe(FeedEvent::makeId('OAI/Arazzo-Specification', 'pr:533'));
});

it('normalizes issue #410 actor/loop/mcp topics', function (): void {
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
        ->and($ev->severity)->toBe('watch');
});

it('normalizes 1.1.0 release with xml/xpath', function (): void {
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
        ->and($ev->severity)->toBe('actionable'); // release severity is a legacy hint; tier comes from the Scorer
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

    expect($ev->tags)->toContain('criterion-regex')
        ->and($ev->tags)->not->toContain('spec')
        ->and($ev->relevance)->toBe('plan:expression-evaluation-parity-hardening')
        ->and($ev->severity)->toBe('watch');
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

it('does not scan bot/dep-bump bodies and never marks them breaking', function (): void {
    $ev = Normalizer::normalize([
        'source' => 'OAI/learn.openapis.org', 'type' => 'pr', 'externalId' => 'pr:12',
        'title' => 'build(deps): bump actions/checkout from 6 to 7',
        'url' => 'https://github.com/OAI/learn.openapis.org/pull/12',
        'publishedAt' => '2026-10-13T00:00:00Z',
        'body' => 'Bumps actions/checkout from 6 to 7. Release notes contain a breaking change and SHA a2a1b2c3.',
        'labels' => [], 'author' => 'dependabot[bot]', 'state' => 'open', 'merged' => false,
    ]);

    expect($ev->tags)->not->toContain('a2a')
        ->and($ev->tags)->not->toContain('breaking')
        ->and($ev->severity)->not->toBe('breaking');
});

it('retires breaking as a tag/severity — it is derived by the Scorer', function (): void {
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
