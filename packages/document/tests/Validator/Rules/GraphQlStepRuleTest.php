<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\GraphQlStepRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a whole-source schema with an operation', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step(
            's',
            graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: 'query GetPet'),
        )])],
        sources: [new SourceDescription('gql', '/schema.graphql', SourceType::Graphql)],
    );
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects a graphqlOperation without an operation', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: ''),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.graphql_step');
});

it('rejects a partial (operation-path) schema reference', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql.url#/operations/0', operation: 'query test'),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('rejects extensions together with extensionsSelector', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(
            schema: '$sourceDescriptions.gql',
            operation: 'query GetPet',
            extensions: ['contentType' => 'application/json'],
            extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/extensions', ExpressionType::JsonPointer),
        ),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('ignores non-graphql steps', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});
