<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\DocumentArazzoVersionRule;
use Alama\LaravelArazzo\Validation\Rules\DocumentInfoRequiredRule;
use Alama\LaravelArazzo\Validation\Rules\SourceTypeMatchesRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUniqueNameRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUrlSyntaxRule;

function baseDoc(string $version = '1.0.0', string $title = 'T', string $ver = '1', array $sources = []): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $version,
        info: new Info($title, null, null, $ver),
        sourceDescriptions: $sources,
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('flags wrong arazzo version', function (): void {
    $doc = baseDoc('2.0.0');
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('document.arazzo_version');
});

it('accepts 1.0.0 version', function (): void {
    $doc = baseDoc();
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('requires info title and version', function (): void {
    $doc = baseDoc(title: '', ver: '');
    $ec = new ErrorCollector();
    (new DocumentInfoRequiredRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBe(2);
});

it('flags duplicate source names', function (): void {
    $sources = [
        new SourceDescription('api', '/a', SourceType::Openapi),
        new SourceDescription('api', '/b', SourceType::Openapi),
    ];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUniqueNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('source.unique_name');
});

it('flags empty source url', function (): void {
    $sources = [new SourceDescription('api', '', SourceType::Openapi)];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUrlSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('accepts valid absolute and relative source urls', function (): void {
    $sources = [
        new SourceDescription('api1', 'https://example.com/api.yaml', SourceType::Openapi),
        new SourceDescription('api2', 'api.yaml', SourceType::Openapi),
        new SourceDescription('api3', './specs/openapi.yaml', SourceType::Openapi),
    ];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUrlSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('SourceTypeMatchesRule passes (enum enforcement is at parser time)', function (): void {
    $doc = baseDoc(sources: [new SourceDescription('api', '/x', SourceType::Openapi)]);
    $ec = new ErrorCollector();
    (new SourceTypeMatchesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
