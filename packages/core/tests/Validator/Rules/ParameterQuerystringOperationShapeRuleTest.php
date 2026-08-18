<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ParameterQuerystringOperationShapeRule;

function docWithQuerystring(string $operationId, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    $step = new Step(
        's', null, $operationId, null, null,
        [new Parameter('q', ParameterIn::Querystring, 'x')],
        null, [], [], [], [],
    );

    return new ArazzoDocument(
        arazzo: $sv->value, info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: $sv,
    );
}

it('warns without source info when querystring used', function () {
    $errors = new ErrorCollector();
    $doc = docWithQuerystring('getSomething');
    (new ParameterQuerystringOperationShapeRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    // With no source descriptor, rule emits a warning not an error
    expect($errors->warnings())->not->toBe([]);
});

it('skips on 1.0.0 documents', function () {
    $errors = new ErrorCollector();
    $doc = docWithQuerystring('getSomething', SpecVersion::V1_0);
    (new ParameterQuerystringOperationShapeRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->warnings())->toBe([]);
});
