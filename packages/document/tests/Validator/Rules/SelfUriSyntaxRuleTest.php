<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SelfUriSyntaxRule;

function docWithSelf(?string $self, SpecVersion $sv = SpecVersion::V1_1): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $sv->value, info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [], specVersion: $sv, self: $self,
    );
}

function errorsFor(?string $self, SpecVersion $sv = SpecVersion::V1_1): array
{
    $errors = new ErrorCollector();
    (new SelfUriSyntaxRule())->check(
        docWithSelf($self, $sv),
        SymbolTable::build(docWithSelf($self, $sv)), $errors,
    );

    return $errors->errors();
}

it('accepts an absolute $self on 1.0.0', function () {
    expect(errorsFor('https://example.com/spec.yaml', SpecVersion::V1_0))->toBe([]);
});

it('accepts an absolute $self on 1.1.0', function () {
    expect(errorsFor('https://example.com/spec.yaml'))->toBe([]);
});

it('accepts a relative $self on 1.0.0', function () {
    expect(errorsFor('./doc.arazzo', SpecVersion::V1_0))->toBe([]);
});

it('accepts a relative $self on 1.1.0', function () {
    expect(errorsFor('./doc.arazzo'))->toBe([]);
});

it('rejects a $self with a fragment on 1.0.0', function () {
    $errors = errorsFor('https://example.com/doc.arazzo#frag', SpecVersion::V1_0);
    expect($errors)->toHaveCount(1);
    expect($errors[0]->code)->toBe('document.self_uri_syntax');
    expect($errors[0]->path)->toBe('/$self');
    expect($errors[0]->message)->toBe("Invalid \$self URI: 'https://example.com/doc.arazzo#frag'");
});

it('rejects a $self with a fragment on 1.1.0', function () {
    $errors = errorsFor('https://example.com/doc.arazzo#frag');
    expect($errors)->toHaveCount(1);
    expect($errors[0]->code)->toBe('document.self_uri_syntax');
    expect($errors[0]->path)->toBe('/$self');
});

it('rejects an empty $self', function () {
    $errors = errorsFor('');
    expect($errors)->toHaveCount(1);
    expect($errors[0]->code)->toBe('document.self_uri_syntax');
    expect($errors[0]->path)->toBe('/$self');
    expect($errors[0]->message)->toBe("Invalid \$self URI: ''");
});

it('rejects a whitespace-only $self', function () {
    expect(errorsFor('   '))->toHaveCount(1);
});

it('rejects a garbage $self containing spaces', function () {
    expect(errorsFor('not a uri at all'))->toHaveCount(1);
});

it('is a no-op when $self is null', function () {
    expect(errorsFor(null))->toBe([]);
});
