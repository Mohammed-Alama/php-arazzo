<?php

declare(strict_types=1);

namespace Tests\Validator;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\OfficialSchemaRule;
use Alama\Arazzo\Validator\Rules\DocumentSourceDescriptionsPresentRule;
use Alama\Arazzo\Validator\Rules\DocUnknownFieldRule;

function docWithRaw(array $raw, string $version = '1.1.0'): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $version,
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        rawRoot: $raw,
    );
}

it('flags documents without any sourceDescription', function (): void {
    $doc = Fx::doc(workflows: [Fx::wf('main', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();

    (new DocumentSourceDescriptionsPresentRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('document.source_descriptions_present');
});

it('accepts documents with at least one sourceDescription', function (): void {
    $doc = Fx::doc(workflows: [Fx::wf('main', [])], sources: [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $ec = new ErrorCollector();

    (new DocumentSourceDescriptionsPresentRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('treats root $self as a known field', function (): void {
    $raw = [
        'arazzo' => '1.1.0',
        '$self' => 'https://api.example.com/doc.arazzo.yaml',
    ];
    $doc = docWithRaw($raw);
    $ec = new ErrorCollector();

    (new DocUnknownFieldRule())->check($doc, SymbolTable::build($doc), $ec);

    $codes = array_map(fn ($e) => $e->code, $ec->errors());
    expect($codes)->not->toContain('doc.unknown_field');
});

it('structurally validates raw documents against the official 1.1 schema', function (): void {
    // Missing required "info" - caught by the official schema layer.
    $doc = docWithRaw([
        'arazzo' => '1.1.0',
        'workflows' => [['workflowId' => 'w', 'steps' => []]],
    ]);
    $ec = new ErrorCollector();

    (new OfficialSchemaRule())->check($doc, SymbolTable::build($doc), $ec);

    $messages = array_map(fn ($e) => $e->message, $ec->errors());
    expect($ec->errors())->not->toBeEmpty()
        ->and(implode(' | ', $messages))->toContain('info');
});

it('passes structurally valid raw documents through the schema layer', function (): void {
    $doc = docWithRaw([
        'arazzo' => '1.1.0',
        'info' => ['title' => 'T', 'version' => '1'],
        'sourceDescriptions' => [['name' => 'api', 'url' => 'https://api.test/openapi.json', 'type' => 'openapi']],
        'workflows' => [[
            'workflowId' => 'w',
            'steps' => [[
                'stepId' => 's1',
                'operationId' => 'api.doThing',
                'parameters' => [['name' => 'q', 'in' => 'query', 'value' => 'x']],
            ]],
        ]],
    ]);
    $ec = new ErrorCollector();

    (new OfficialSchemaRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});
