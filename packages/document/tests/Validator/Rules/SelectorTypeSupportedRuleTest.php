<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SelectorTypeSupportedRule;

function docWithOutputSelector(Selector $s, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [
            StepFactory::http('s', null, new StepFlow(), new StepIo(outputs: ['id' => $s]), operationId: 'op'),
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

function docWithVariousSelectors(Selector $s, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [
            StepFactory::http(
                's', null,
                new StepFlow(),
                new StepIo(
                    parameters: [new Parameter('p2', ParameterIn::Header, $s)],
                    requestBody: new RequestBody(null, null, [new PayloadReplacement('/a', $s)]),
                ),
                operationId: 'op',
            ),
        ], [], [], [], [new Parameter('p', ParameterIn::Header, $s)])],
        components: new Components([], [
            'p' => new Parameter('p3', ParameterIn::Header, $s),
        ], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('errors on unknown pinned version in parameters', function () {
    $errors = new ErrorCollector();
    $doc = docWithVariousSelectors(new Selector(null, '$.x', ExpressionType::JsonPath, 'draft-99'));
    (new SelectorTypeSupportedRule())->check($doc, SymbolTable::build($doc), $errors);
    expect(count($errors->errors()))->toBe(4);
});

it('skips reusable parameters (implementation-default path)', function () {
    $errors = new ErrorCollector();
    $reusable = new Reusable('$components.parameters.p');
    $doc = new ArazzoDocument(
        arazzo: SpecVersion::V1_1->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [
            StepFactory::http(
                's', null, new StepFlow(),
                new StepIo(parameters: [new Parameter('p', ParameterIn::Header, $reusable)]),
                operationId: 'op',
            ),
        ], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
    (new SelectorTypeSupportedRule(['rfc9535']))->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});

it('errors on spec-recognised version not implemented by the evaluator', function () {
    $errors = new ErrorCollector();
    $doc = docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, 'rfc9535'));
    // supportedVersions = [] => rfc9535 is spec-recognised but not implemented.
    (new SelectorTypeSupportedRule([]))->check($doc, SymbolTable::build($doc), $errors);
    expect(count($errors->errors()))->toBe(1);
    expect($errors->errors()[0]->code)->toBe('selector.type_supported');
    expect($errors->errors()[0]->message)->toContain('not implemented');
    expect($errors->errors()[0]->message)->toContain('rfc9535');
});

it('skips reusable workflow parameter', function () {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [], [], [], [], [
            new Reusable('$components.parameters.p'),
        ])],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: SpecVersion::V1_1,
    );
    (new SelectorTypeSupportedRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});

it('accepts selector with null version (implementation default)', function () {
    $errors = new ErrorCollector();
    $doc = docWithOutputSelector(new Selector(null, '$.x', ExpressionType::JsonPath, null));
    (new SelectorTypeSupportedRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});
