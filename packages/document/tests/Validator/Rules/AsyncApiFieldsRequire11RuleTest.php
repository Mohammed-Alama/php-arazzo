<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\AsyncApiFieldsRequire11Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
