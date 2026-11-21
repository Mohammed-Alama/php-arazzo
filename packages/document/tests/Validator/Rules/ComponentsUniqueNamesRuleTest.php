<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ComponentsUniqueNamesRule;

function docWithComponents(array $components): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components(...$components),
        specificationExtensions: [],
    );
}

it('keeps the reserved rule code', function (): void {
    $r = new ComponentsUniqueNamesRule();
    expect($r->code())->toBe('components.unique_names');
});

it('rejects invalid components parameter key', function (): void {
    $doc = docWithComponents([
        'inputs' => [],
        'parameters' => ['bad key!' => ['name' => 'q', 'in' => 'query', 'value' => '1']],
        'successActions' => [],
        'failureActions' => [],
    ]);
    $ec = new ErrorCollector();
    (new ComponentsUniqueNamesRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('components.key_pattern')
        ->and($ec->errors()[0]->path)->toBe('/components/parameters/bad key!');
});

it('checks every components map', function (): void {
    $doc = docWithComponents([
        'inputs' => ['bad input!' => []],
        'parameters' => ['bad param!' => []],
        'successActions' => ['bad success!' => []],
        'failureActions' => ['bad failure!' => []],
    ]);
    $ec = new ErrorCollector();
    (new ComponentsUniqueNamesRule())->check($doc, SymbolTable::build($doc), $ec);

    $codes = array_map(fn ($e) => $e->code, $ec->errors());
    $paths = array_map(fn ($e) => $e->path, $ec->errors());
    expect($codes)->toHaveCount(4)
        ->and(array_unique($codes))->toBe(['components.key_pattern'])
        ->and($paths)->toBe([
            '/components/inputs/bad input!',
            '/components/parameters/bad param!',
            '/components/successActions/bad success!',
            '/components/failureActions/bad failure!',
        ]);
});

it('accepts valid dotted dashed underscored components keys', function (): void {
    $doc = docWithComponents([
        'inputs' => ['pets.page-size_2' => [], 'user.name' => []],
        'parameters' => ['pets.page-size_2' => []],
        'successActions' => ['pets.page-size_2' => []],
        'failureActions' => ['user.name' => []],
    ]);
    $ec = new ErrorCollector();
    (new ComponentsUniqueNamesRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBe([]);
});
