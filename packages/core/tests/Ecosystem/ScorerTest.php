<?php

declare(strict_types=1);

use Ecosystem\Scorer;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/TopicMatcher.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Scorer.php';

/**
 * @return array<int,array<string,mixed>>
 */
function appendix_b_fixtures(): array
{
    $path = __DIR__.'/fixtures/appendix-b.json';
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("Cannot read Appendix B fixtures at {$path}");
    }

    return $data;
}

it('A5: classifies every Appendix B regression fixture to the expected tier/topics', function (array $fixture): void {
    $result = Scorer::fromDefault()->score($fixture['observation'], (bool) ($fixture['watchlist'] ?? false));
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
})->with(appendix_b_datasets());

/**
 * @return array<string,array{0:array<string,mixed>}>
 */
function appendix_b_datasets(): array
{
    $sets = [];
    foreach (appendix_b_fixtures() as $fixture) {
        $sets[$fixture['name']] = [$fixture];
    }

    return $sets;
}

it('derives breaking only on Act items, never a keyword tag', function (): void {
    $scorer = Scorer::fromDefault();

    // A spec-text-only Act change without a schema touch is not breaking.
    $specText = $scorer->score([
        'source' => 'OAI/Arazzo-Specification', 'type' => 'spec', 'branch' => 'v1.2-dev',
        'title' => 'spec text edit', 'body' => 'reworded a paragraph', 'labels' => [],
    ]);
    expect($specText['tier'])->toBe('act')
        ->and($specText['breaking'])->toBeFalse();

    // The same repo + a schema touch is breaking.
    $schema = $scorer->score([
        'source' => 'OAI/Arazzo-Specification', 'type' => 'schema', 'branch' => 'v1.2-dev',
        'title' => 'schema.yaml changed', 'body' => '', 'labels' => [],
    ]);
    expect($schema['tier'])->toBe('act')
        ->and($schema['breaking'])->toBeTrue();

    // A Watch item is never breaking, even with a `breaking` label.
    $watch = $scorer->score([
        'source' => 'usearazzo/arazzo-toolkit', 'type' => 'issue', 'title' => 'docs request',
        'labels' => ['breaking'], 'state' => 'open',
    ]);
    expect($watch['tier'])->toBe('watch')
        ->and($watch['breaking'])->toBeFalse();
});

it('classifies the noise vocabulary as noise, never listed', function (array $observation, string $expectedWhy): void {
    $result = Scorer::fromDefault()->score($observation);

    expect($result['tier'])->toBe(Scorer::NOISE)
        ->and($result['why'])->toContain($expectedWhy)
        ->and($result['score'])->toBe(0)
        ->and($result['breaking'])->toBeFalse();
})->with([
    'bot author' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'title' => 'feat: soap support', 'author' => 'dependabot[bot]'],
        'bot-author',
    ],
    'our own repo' => [
        ['source' => 'Mohammed-Alama/php-arazzo', 'type' => 'pr', 'title' => 'feat: new thing', 'author' => 'alice'],
        'noise-source',
    ],
    'dep bump title' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'title' => 'chore(deps): bump axios', 'author' => 'alice'],
        'noise-title',
    ],
    'sync with dev' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'title' => 'v1.2-dev: sync with dev', 'author' => 'alice'],
        'noise-title',
    ],
    'docs typo' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'title' => 'docs: fix typo', 'author' => 'alice'],
        'noise-title',
    ],
    'ci only' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'title' => 'ci: pin actions', 'author' => 'alice'],
        'noise-title',
    ],
    'prerelease tag' => [
        ['source' => 'OAI/Arazzo-Specification', 'type' => 'tag', 'externalId' => 'tag:v1.2.0-rc.1', 'title' => 'v1.2.0-rc.1', 'author' => 'alice'],
        'prerelease-tag',
    ],
]);

it('adds the watchlist bonus and promotes a watchlist transition to Act', function (): void {
    $scorer = Scorer::fromDefault();

    $pr = [
        'source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:533',
        'title' => 'feat(spec): add SOAP support', 'body' => 'wsdl support', 'labels' => [],
        'state' => 'open', 'merged' => false,
    ];

    $plain = $scorer->score($pr);
    $watched = $scorer->score($pr, true);
    $transition = $scorer->score(array_merge($pr, ['state' => 'closed', 'merged' => true, 'base' => 'v1.2-dev']), true);

    expect($plain['tier'])->toBe('watch')
        ->and($watched['tier'])->toBe('watch')
        ->and($watched['score'])->toBe($plain['score'] + Scorer::WATCHLIST_BONUS)
        ->and($transition['tier'])->toBe('act')
        ->and($transition['why'])->toContain('watchlist-transition');
});
