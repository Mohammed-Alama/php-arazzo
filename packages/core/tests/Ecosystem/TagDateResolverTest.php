<?php

declare(strict_types=1);

use Ecosystem\Ingestors\TagDateResolver;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/GhCli.php';
require_once dirname(__DIR__, 4).'/scripts/ecosystem/Ingestors/TagDateResolver.php';

/**
 * A recorded GraphQL `refs` payload (annotated tag, lightweight tag, undated tag).
 *
 * @return array<string,mixed>
 */
function graphql_tags_payload(): array
{
    return [
        'data' => [
            'repository' => [
                'refs' => [
                    'nodes' => [
                        ['name' => 'v1.0.2', 'target' => ['tagger' => ['date' => '2026-04-09T22:26:34+01:00']]],
                        ['name' => 'v1.0.1', 'target' => ['committedDate' => '2026-03-13T18:10:38Z']],
                        ['name' => 'v1.0.0', 'target' => []],
                    ],
                ],
            ],
        ],
    ];
}

it('resolves annotated and lightweight tag dates from the GraphQL refs payload', function (): void {
    $nodes = TagDateResolver::nodes(graphql_tags_payload());
    $observations = TagDateResolver::toObservations('frankkilcommins/arazzo2openapi', $nodes, 20);

    expect($observations)->toHaveCount(2) // v1.0.0 has no date → dropped, never synthesized
        ->and($observations[0]['type'])->toBe('tag')
        ->and($observations[0]['externalId'])->toBe('tag:v1.0.2')
        ->and($observations[0]['publishedAt'])->toBe('2026-04-09T21:26:34Z') // normalized to UTC
        ->and($observations[1]['externalId'])->toBe('tag:v1.0.1')
        ->and($observations[1]['publishedAt'])->toBe('2026-03-13T18:10:38Z');
});

it('stops at the stored lastTag watermark and honours the limit', function (): void {
    $nodes = TagDateResolver::nodes(graphql_tags_payload());

    $stopped = TagDateResolver::toObservations('frankkilcommins/arazzo2openapi', $nodes, 20, [
        'sources' => ['frankkilcommins/arazzo2openapi' => ['endpoints' => ['tags' => ['lastTag' => 'v1.0.1']]]],
    ]);
    expect(array_column($stopped, 'externalId'))->toBe(['tag:v1.0.2']);

    $limited = TagDateResolver::toObservations('frankkilcommins/arazzo2openapi', $nodes, 1);
    expect($limited)->toHaveCount(1);
});

it('queries tags ordered by TAG_COMMIT_DATE', function (): void {
    $query = TagDateResolver::query();

    expect($query)->toContain('refPrefix: "refs/tags/"')
        ->and($query)->toContain('field: TAG_COMMIT_DATE')
        ->and($query)->toContain('direction: DESC')
        ->and($query)->toContain('tagger { date }');
});
