<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\AsyncApiFieldsRequire11Rule;

function docWithAsyncStep(?string $action, ?string $channelPath, ?Expression $corr, SpecVersion $sv): ArazzoDocument
{
    $step = new Step(
        'wait', null, null, null, null, [], null, [], [], [], [], [],
        $action, $channelPath, $corr,
    );

    return new ArazzoDocument(
        arazzo: $sv->value, info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: $sv,
    );
}

it('errors on 1.0 doc that uses channelPath', function () {
    $errors = new ErrorCollector();
    $doc = docWithAsyncStep(null, 'channels/x', null, SpecVersion::V1_0);
    (new AsyncApiFieldsRequire11Rule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->not->toBe([]);
});

it('accepts 1.1 doc that uses channelPath', function () {
    $errors = new ErrorCollector();
    $doc = docWithAsyncStep('receive', 'channels/x', new Expression('{$steps.x.outputs.id}'), SpecVersion::V1_1);
    (new AsyncApiFieldsRequire11Rule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->toBe([]);
});
