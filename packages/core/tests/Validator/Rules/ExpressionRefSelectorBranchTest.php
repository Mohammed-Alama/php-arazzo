<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ExpressionType;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedStepRefRule;

it('detects unresolved $steps ref inside a Selector context', function () {
    $sel = new Selector('$steps.does-not-exist.outputs.id', '$.foo', ExpressionType::JsonPath);
    $step = new Step('s', null, 'op', null, null, [], null, [], [], [], ['x' => $sel]);
    $doc = new ArazzoDocument(
        arazzo: '1.1.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: SpecVersion::V1_1,
    );

    $errors = new ErrorCollector();
    $symbols = SymbolTable::build($doc);

    (new ExpressionUnresolvedStepRefRule())->check($doc, $symbols, $errors);

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->message)->toContain("unknown step 'does-not-exist'");
});
