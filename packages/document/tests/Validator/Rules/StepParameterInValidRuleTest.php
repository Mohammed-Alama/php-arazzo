<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepParameterInValidRule;

function docWithParams(array $params, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    $arazzo = $sv === SpecVersion::V1_1 ? '1.1.0' : '1.0.0';

    return new ArazzoDocument(
        arazzo: $arazzo,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [
            new Step('s', null, 'op', null, null, $params, null, [], [], [], []),
        ], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('accepts querystring on 1.1 docs', function () {
    $errors = new ErrorCollector();
    $doc = docWithParams([new Parameter('p', ParameterIn::Querystring, 'x')]);
    (new StepParameterInValidRule())->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );
    expect($errors->errors())->toBe([]);
});

it('rejects querystring on 1.0 docs', function () {
    $errors = new ErrorCollector();
    $doc = docWithParams([new Parameter('p', ParameterIn::Querystring, 'x')], SpecVersion::V1_0);
    (new StepParameterInValidRule())->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );
    $errs = $errors->errors();
    expect($errs)->toHaveCount(1)
        ->and($errs[0]->message)->toBe("Parameter 'in' value 'querystring' requires Arazzo 1.1.0.");
});

it('rejects querystring in component parameters on 1.0 docs', function () {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components(
            [],
            ['myParam' => new Parameter('p', ParameterIn::Querystring, 'x')],
            [],
            [],
        ),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_0,
    );
    (new StepParameterInValidRule())->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );
    $errs = $errors->errors();
    expect($errs)->toHaveCount(1)
        ->and($errs[0]->message)->toBe("Parameter 'in' value 'querystring' requires Arazzo 1.1.0.")
        ->and($errs[0]->path)->toBe('/components/parameters/myParam/in');
});
