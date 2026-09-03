<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Persistence;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry;
use Alama\Arazzo\Runner\State\Exceptions\DefinitionHydrationException;
use Illuminate\Support\Facades\DB;

function definitionRawRoot(string $title = 'Test Doc'): array
{
    return [
        'arazzo' => '1.0.0',
        'info' => ['title' => $title, 'version' => '1.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            [
                'workflowId' => 'wf_1',
                'steps' => [
                    ['stepId' => 'step_1', 'operationId' => 'op_1'],
                ],
            ],
        ],
    ];
}

function definitionDocumentFor(array $rawRoot): ArazzoDocument
{
    return (new Parser())->parse(new RawDocument($rawRoot, 'memory://test', Format::Json));
}

it('registers and retrieves a document', function (): void {
    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());
    $document = definitionDocumentFor(definitionRawRoot());

    $id = $registry->register($document);
    $fetched = $registry->get($id);

    expect($fetched)->not->toBeNull();
    expect($fetched->info->title)->toBe('Test Doc');
    expect($fetched->workflows[0]->workflowId)->toBe('wf_1');
});

it('returns the same id when registering identical content twice', function (): void {
    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());
    $document = definitionDocumentFor(definitionRawRoot());

    $id1 = $registry->register($document);
    $id2 = $registry->register($document);

    expect($id2)->toBe($id1);
    expect(DB::table('arazzo_definitions')->count())->toBe(1);
});

it('produces different ids for different content', function (): void {
    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

    $id1 = $registry->register(definitionDocumentFor(definitionRawRoot('Doc A')));
    $id2 = $registry->register(definitionDocumentFor(definitionRawRoot('Doc B')));

    expect($id2)->not->toBe($id1);
});

it('returns null from get() for an unknown id', function (): void {
    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

    expect($registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();
});

it('throws a hydration exception on unparseable JSON', function (): void {
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Broken',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => 'not valid json',
        'created_at' => now(),
    ]);

    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

    expect(fn () => $registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV'))
        ->toThrow(DefinitionHydrationException::class);
});

it('throws a hydration exception when content no longer validates', function (): void {
    // Missing required "workflows" field -- Parser::parse() will reject this.
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Invalid',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['arazzo' => '1.0.0', 'info' => ['title' => 'x', 'version' => '1.0']]),
        'created_at' => now(),
    ]);

    $registry = new DatabaseDefinitionRegistry(DB::connection(), new Parser());

    expect(fn () => $registry->get('01ARZ3NDEKTSV4RRFFQ69G5FAV'))
        ->toThrow(DefinitionHydrationException::class);
});
