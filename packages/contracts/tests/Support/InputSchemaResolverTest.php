<?php

declare(strict_types=1);

use Alama\Arazzo\Support\InputSchemaResolver;

function componentInputs(): array
{
    return [
        'pageSchema' => [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer'],
            ],
        ],
        'idSchema' => ['type' => 'string'],
    ];
}

it('resolves a top-level local reference', function (): void {
    $schema = ['$ref' => '#/components/inputs/pageSchema'];

    expect(InputSchemaResolver::resolve($schema, componentInputs()))->toBe(componentInputs()['pageSchema']);
});

it('resolves references nested in properties and items', function (): void {
    $schema = [
        'type' => 'object',
        'properties' => [
            'pager' => ['$ref' => '#/components/inputs/pageSchema'],
            'ids' => ['items' => ['$ref' => '#/components/inputs/idSchema']],
        ],
    ];

    $resolved = InputSchemaResolver::resolve($schema, componentInputs());

    expect($resolved['properties']['pager'])->toBe(['type' => 'object', 'properties' => ['page' => ['type' => 'integer']]])
        ->and($resolved['properties']['ids']['items'])->toBe(['type' => 'string']);
});

it('leaves foreign or unresolvable refs untouched', function (): void {
    $schema = [
        '$ref' => '#/components/schemas/other',
        'nested' => ['$ref' => '#/components/inputs/ghost'],
    ];

    expect(InputSchemaResolver::resolve($schema, componentInputs()))->toBe($schema);
});

it('terminates on reference cycles instead of recursing forever', function (): void {
    $inputs = [
        'a' => ['$ref' => '#/components/inputs/b'],
        'b' => ['$ref' => '#/components/inputs/a'],
    ];

    $resolved = InputSchemaResolver::resolve(['$ref' => '#/components/inputs/a'], $inputs);

    expect($resolved)->toBeArray();
});
