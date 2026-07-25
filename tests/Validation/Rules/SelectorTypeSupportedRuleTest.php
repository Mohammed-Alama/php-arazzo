<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ExpressionType;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\SelectorTypeSupportedRule;

function docWithOutputSelector(Selector $s, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [
            new Step('s', null, 'op', null, null, [], null, [], [], [], ['id' => $s]),
        ], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('accepts known pinned versions', function () {
    $errors = new ErrorCollector();
    (new SelectorTypeSupportedRule())->check(
        docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'rfc9535')),
        SymbolTable::build(docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'rfc9535'))), $errors,
    );
    expect($errors->errors())->toBe([]);
});

it('errors on unknown pinned version', function () {
    $errors = new ErrorCollector();
    (new SelectorTypeSupportedRule())->check(
        docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'draft-99')),
        SymbolTable::build(docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'draft-99'))), $errors,
    );
    expect($errors->errors())->not->toBe([]);
});

it('skips on 1.0.0 documents', function () {
    $errors = new ErrorCollector();
    (new SelectorTypeSupportedRule())->check(
        docWithOutputSelector(
            new Selector(null, '$.x', ExpressionType::JsonPath, 'draft-99'),
            SpecVersion::V1_0,
        ),
        SymbolTable::build(docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'draft-99'), SpecVersion::V1_0)), $errors,
    );
    expect($errors->errors())->toBe([]);
});
