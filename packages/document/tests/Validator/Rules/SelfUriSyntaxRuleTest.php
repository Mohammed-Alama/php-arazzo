<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SelfUriSyntaxRule;
use Alama\Arazzo\Expression\SymbolTable;

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
