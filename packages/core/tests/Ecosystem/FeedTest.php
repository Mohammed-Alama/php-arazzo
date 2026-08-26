<?php

declare(strict_types=1);

use Ecosystem\FeedEvent;
use Ecosystem\Normalizer;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/FeedEvent.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/RelevanceMapper.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Normalizer.php';

it('normalizes PR #533 SOAP to breaking with wsdl tags', function (): void {
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
        ->and($ev->tags)->toContain('soap')
        ->and($ev->tags)->toContain('wsdl')
        ->and($ev->severity)->toBe('breaking')
        ->and($ev->relevance)->toBe('P0-6 source routing (wsdl type)')
        ->and($ev->id)->toBe(FeedEvent::makeId('OAI/Arazzo-Specification', 'pr:533'));
});

it('normalizes issue #410 actor/loop to mcp tags', function (): void {
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
        ->and($ev->tags)->toContain('actor')
        ->and($ev->tags)->toContain('loop')
        ->and($ev->severity)->toBe('breaking');
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
        ->and($ev->tags)->toContain('xpath');
});

it('deduplicates by id', function (): void {
    $a = ['source' => 'X', 'type' => 'pr', 'externalId' => 'pr:1', 'title' => 'soap wsdl', 'url' => 'https://example.com/1', 'publishedAt' => '2026-01-01T00:00:00Z', 'body' => '', 'labels' => []];
    $b = ['source' => 'X', 'type' => 'pr', 'externalId' => 'pr:1', 'title' => 'soap wsdl duplicate', 'url' => 'https://example.com/1', 'publishedAt' => '2026-01-01T00:00:00Z', 'body' => '', 'labels' => []];

    $events = Normalizer::normalizeMany([$a, $b]);

    expect($events)->toHaveCount(1);
});

it('maps spec tag to relevance', function (): void {
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

    expect($ev->tags)->toContain('spec')
        ->and($ev->severity)->toBe('watch');
});
