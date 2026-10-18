<?php

declare(strict_types=1);

use Ecosystem\TopicMatcher;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';

function topic_matcher(): TopicMatcher
{
    return TopicMatcher::fromDefault();
}

it('never fires actor on refactor or api-extractor', function (): void {
    $matcher = topic_matcher();

    $refactor = $matcher->topics([
        'source' => 'Mohammed-Alama/php-arazzo', 'title' => 'refactor: extract framework-agnostic engine into arazzo-core (Plan A)',
        'body' => '', 'labels' => [],
    ]);
    $extractor = $matcher->topics([
        'source' => 'usearazzo/arazzo-toolkit', 'title' => 'chore(deps-dev): bump @microsoft/api-extractor 7.59.2→7.59.3',
        'body' => '', 'labels' => [],
    ]);

    expect($refactor)->not->toContain('actor')
        ->and($refactor)->not->toContain('actor-in-loop')
        ->and($extractor)->not->toContain('actor')
        ->and($extractor)->not->toContain('actor-in-loop');
});

it('matches actor-in-the-loop for a real phrase', function (): void {
    $topics = topic_matcher()->topics([
        'source' => 'OAI/Arazzo-Specification', 'title' => 'feat(spec): add actor-in-the-loop support',
        'body' => '', 'labels' => [],
    ]);

    expect($topics)->toContain('actor-in-loop');
});

it('requires an uppercase word boundary for a2a', function (): void {
    $matcher = topic_matcher();

    $upper = $matcher->topics([
        'source' => 'OAI/Arazzo-Specification', 'title' => 'Add A2A step type', 'body' => '', 'labels' => [],
    ]);
    $lower = $matcher->topics([
        'source' => 'OAI/Arazzo-Specification', 'title' => 'add a2a step type', 'body' => '', 'labels' => [],
    ]);
    $sha = $matcher->topics([
        'source' => 'OAI/learn.openapis.org', 'title' => 'build(deps): bump actions/checkout from 6 to 7',
        'body' => 'Release notes mention SHA a2a1b2c3.', 'labels' => [], 'author' => 'dependabot[bot]',
    ]);

    expect($upper)->toContain('a2a')
        ->and($lower)->not->toContain('a2a')
        ->and($sha)->not->toContain('a2a');
});

it('never matches the URL, so arazzo-cli in a URL does not fire cli', function (): void {
    $topics = topic_matcher()->topics([
        'source' => 'strefethen/arazzo-cli', 'title' => 'Fix typo in README',
        'url' => 'https://github.com/strefethen/arazzo-cli/issues/99',
        'body' => 'a small docs fix', 'labels' => [],
    ]);

    expect($topics)->not->toContain('cli');
});

it('does match cli when the title really names the CLI', function (): void {
    $topics = topic_matcher()->topics([
        'source' => 'strefethen/arazzo-cli', 'title' => 'arazzo-cli 1.2.0 released',
        'body' => '', 'labels' => [],
    ]);

    expect($topics)->toContain('cli');
});

it('scans the first 500 characters of a human body but never a bot body', function (): void {
    $matcher = topic_matcher();
    $within = str_repeat('x ', 100).'wsdl';            // wsdl inside the first ~500 chars
    $beyond = str_repeat('y ', 400).'wsdl';            // wsdl well past 500 chars

    $human = $matcher->topics([
        'source' => 'OAI/Arazzo-Specification', 'title' => 'add support', 'body' => $within, 'labels' => [],
    ]);
    $tooLate = $matcher->topics([
        'source' => 'OAI/Arazzo-Specification', 'title' => 'add support', 'body' => $beyond, 'labels' => [],
    ]);
    $bot = $matcher->topics([
        'source' => 'OAI/learn.openapis.org', 'title' => 'Bump dependency', 'body' => $within,
        'labels' => [], 'author' => 'dependabot[bot]',
    ]);

    expect($human)->toContain('wsdl')
        ->and($tooLate)->not->toContain('wsdl')
        ->and($bot)->not->toContain('wsdl');
});

it('matches a topic by source even without matching text (bare version tags)', function (): void {
    $topics = topic_matcher()->topics([
        'source' => 'OAI/sig-moonwalk', 'title' => 'tag v0.3.0', 'body' => '', 'labels' => [],
    ]);

    expect($topics)->toContain('moonwalk');
});
