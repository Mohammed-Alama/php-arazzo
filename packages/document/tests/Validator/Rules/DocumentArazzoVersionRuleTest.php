<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\DocumentArazzoVersionRule;

function docV(string $arazzo, SpecVersion $sv): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $arazzo,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('accepts 1.0.x, 1.1.x and 1.2.x', function (string $v, SpecVersion $sv) {
    $errors = new ErrorCollector();
    $doc = docV($v, $sv);
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
})->with([
    ['1.0.0', SpecVersion::V1_0],
    ['1.0.7', SpecVersion::V1_0],
    ['1.1.0', SpecVersion::V1_1],
    ['1.1.3', SpecVersion::V1_1],
    ['1.2.0', SpecVersion::V1_2],
    ['1.2.5', SpecVersion::V1_2],
]);

it('rejects an unsupported arazzo version', function (): void {
    $errors = new ErrorCollector();
    $doc = docV('2.0.0', SpecVersion::V1_2);
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $errors);

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('document.arazzo_version');
});
