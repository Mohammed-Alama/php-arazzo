<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\SelfUriSyntaxRule;

function docWithSelf(?string $self, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $sv->value, info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: $sv, self: $self,
    );
}

it('accepts a valid https URI', function () {
    $errors = new ErrorCollector();
    $doc = docWithSelf('https://example.com/spec.yaml');
    (new SelfUriSyntaxRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->toBe([]);
});

it('errors on garbage $self', function () {
    $errors = new ErrorCollector();
    $doc = docWithSelf('not a uri at all');
    (new SelfUriSyntaxRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->not->toBe([]);
});

it('no-op when $self is null', function () {
    $errors = new ErrorCollector();
    $doc = docWithSelf(null);
    (new SelfUriSyntaxRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->toBe([]);
});
