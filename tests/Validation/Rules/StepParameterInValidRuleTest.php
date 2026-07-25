<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepParameterInValidRule;

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
