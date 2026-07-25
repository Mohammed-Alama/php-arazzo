<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Validation\RuleSet;
use Alama\LaravelArazzo\Validation\Rules\AsyncApiFieldsRequire11Rule;
use Alama\LaravelArazzo\Validation\Rules\DocumentArazzoVersionRule;
use Alama\LaravelArazzo\Validation\Rules\ParameterQuerystringOperationShapeRule;
use Alama\LaravelArazzo\Validation\Rules\SelectorTypeSupportedRule;
use Alama\LaravelArazzo\Validation\Rules\SelfUriSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\StepParameterInValidRule;
use Alama\LaravelArazzo\Validation\Rules\SubWorkflowInvokeTargetResolvesRule;
use Alama\LaravelArazzo\Validation\Validator;
use Symfony\Component\Yaml\Yaml;

function loadFixture(string $filename): RawDocument
{
    $path = __DIR__ . '/../fixtures/parser/' . $filename;
    return new RawDocument(Yaml::parseFile($path), $path, Format::Yaml);
}

it('parses the comprehensive 1.1.0 cross-protocol saga fixture', function () {
    $doc = (new Parser())->parse(loadFixture('arazzo-1.1-cross-protocol-saga.yaml'));

    expect($doc->specVersion)->toBe(SpecVersion::V1_1)
        ->and($doc->self)->toBe('https://example.com/rides/spec.arazzo.yaml')
        ->and($doc->workflows)->toHaveCount(2);
});

it('validates the comprehensive fixture cleanly with the 1.1 ruleset', function () {
    $doc = (new Parser())->parse(loadFixture('arazzo-1.1-cross-protocol-saga.yaml'));

    $ruleset = new RuleSet([
        new DocumentArazzoVersionRule(),
        new StepParameterInValidRule(),
        new SelectorTypeSupportedRule(),
        new SubWorkflowInvokeTargetResolvesRule(),
        new SelfUriSyntaxRule(),
        new AsyncApiFieldsRequire11Rule(),
        new ParameterQuerystringOperationShapeRule(),
    ]);

    $result = (new Validator($ruleset))->validate($doc);
    expect($result->errors)->toBe([]);
});

it('preserves 1.0.0 parsing', function () {
    $doc = (new Parser())->parse(loadFixture('arazzo-1.0-still-parses.yaml'));
    expect($doc->specVersion)->toBe(SpecVersion::V1_0)
        ->and($doc->self)->toBeNull()
        ->and($doc->workflows[0]->steps[0]->outputs)->toHaveKey('id');
});

it('parses per-feature narrow fixtures', function (string $filename) {
    $doc = (new Parser())->parse(loadFixture($filename));
    expect($doc->specVersion)->toBe(SpecVersion::V1_1);
})->with([
    'arazzo-1.1-selector-only.yaml',
    'arazzo-1.1-invoke-only.yaml',
    'arazzo-1.1-querystring-only.yaml',
]);
