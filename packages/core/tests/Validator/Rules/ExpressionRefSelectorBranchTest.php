<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
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
