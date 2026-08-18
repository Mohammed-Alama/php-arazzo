<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\DocumentArazzoVersionRule;

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

it('accepts 1.0.x and 1.1.x', function (string $v, SpecVersion $sv) {
    $errors = new ErrorCollector();
    $doc = docV($v, $sv);
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
})->with([
    ['1.0.0', SpecVersion::V1_0],
    ['1.0.7', SpecVersion::V1_0],
    ['1.1.0', SpecVersion::V1_1],
    ['1.1.3', SpecVersion::V1_1],
]);
