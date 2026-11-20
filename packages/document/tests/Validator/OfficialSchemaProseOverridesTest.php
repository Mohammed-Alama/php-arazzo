<?php

declare(strict_types=1);

namespace Tests\Validator;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\OfficialSchemaRule;
use Alama\Arazzo\Expression\ExpressionEngine;

/**
 * @param  list<array<string, mixed>>  $steps
 * @param  array<string, mixed>|null  $components
 * @return array<string, mixed>
 */
function proseRaw(
    string $version,
    array $steps,
    ?array $components = null,
    string $sourceType = 'openapi',
): array {
    $raw = [
        'arazzo' => $version,
        'info' => ['title' => 'T', 'version' => '1'],
        'sourceDescriptions' => [[
            'name' => 'api',
            'url' => 'https://api.test/description.yaml',
            'type' => $sourceType,
        ]],
        'workflows' => [[
            'workflowId' => 'w',
            'steps' => $steps,
        ]],
    ];

    if ($components !== null) {
        $raw['components'] = $components;
    }

    return $raw;
}

function proseDoc(array $raw): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: (string) $raw['arazzo'],
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        rawRoot: $raw,
    );
}

/**
 * @param  array<string, mixed>  $raw
 */
function proseErrors(array $raw): ErrorCollector
{
    $doc = proseDoc($raw);
    $errors = new ErrorCollector();

    (new OfficialSchemaRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $errors);

    return $errors;
}

it('accepts a 1.0 criterion type given as a Criterion Expression Type Object', function (): void {
    $errors = proseErrors(proseRaw('1.0.1', [[
        'stepId' => 's',
        'operationId' => 'getPet',
        'successCriteria' => [[
            'context' => '$response.body',
            'condition' => '$.name',
            'type' => ['type' => 'jsonpath', 'version' => 'draft-goessner-dispatch-jsonpath-00'],
        ]],
    ]]));

    expect($errors->errors())->toBeEmpty();
});

it('rejects a 1.0 criterion type object that is not a Criterion Expression Type Object', function (): void {
    $errors = proseErrors(proseRaw('1.0.1', [[
        'stepId' => 's',
        'operationId' => 'getPet',
        'successCriteria' => [[
            'context' => '$response.body',
            'condition' => '$.name',
            'type' => ['kind' => 'body'],
        ]],
    ]]));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('rejects a 1.0 jsonpath condition that is not a JSONPath query', function (): void {
    $errors = proseErrors(proseRaw('1.0.1', [[
        'stepId' => 's',
        'operationId' => 'getToken',
        'successCriteria' => [[
            'context' => '$response.body',
            'condition' => '$.access_token != null',
            'type' => 'jsonpath',
        ]],
    ]]));

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('schema.invalid')
        ->and($errors->errors()[0]->message)->toContain('JSONPath')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/successCriteria/0/condition');
});

it('rejects a 1.1 jsonpath condition that is not a JSONPath query', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'getToken',
        'successCriteria' => [[
            'context' => '$response.body',
            'condition' => '$.access_token != null',
            'type' => 'jsonpath',
        ]],
    ]]));

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('schema.invalid')
        ->and($errors->errors()[0]->message)->toContain('JSONPath');
});

it('rejects a 1.0 reusable object value that is not a string', function (): void {
    $errors = proseErrors(proseRaw('1.0.1', [[
        'stepId' => 's',
        'operationId' => 'findPets',
        'parameters' => [['reference' => '$components.parameters.page', 'value' => 1]],
    ]], [
        'parameters' => ['page' => ['name' => 'page', 'in' => 'query', 'value' => '1']],
    ]));

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('schema.invalid')
        ->and($errors->errors()[0]->message)->toContain('string')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/parameters/0/value');
});

it('does not enforce the contested 1.1 reusable object value typing', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'findPets',
        'parameters' => [['reference' => '$components.parameters.page', 'value' => 1]],
    ]], [
        'parameters' => ['page' => ['name' => 'page', 'in' => 'query', 'value' => '1']],
    ]));

    expect($errors->errors())->toBeEmpty();
});

it('accepts literal values in 1.1 payload replacements', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'updateItem',
        'requestBody' => [
            'contentType' => 'application/json',
            'replacements' => [
                ['target' => '/count', 'value' => 42],
                ['target' => '/enabled', 'value' => true],
                ['target' => '/items', 'value' => [1, 'two', null]],
                ['target' => '/metadata', 'value' => ['label' => 'example', 'count' => 42]],
                ['target' => '/optional', 'value' => null],
            ],
        ],
    ]]));

    expect($errors->errors())->toBeEmpty();
});

it('accepts an async step that selects its operation with operationId', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'createUser',
        'action' => 'send',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->toBeEmpty();
});

it('accepts an async step that selects its channel with channelPath', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'channelPath' => 'createUser',
        'action' => 'receive',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->toBeEmpty();
});

it('rejects an async step with an unknown action', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'createUser',
        'action' => 'invalid',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('rejects an async step that declares both operationId and channelPath', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'operationId' => 'createUser',
        'channelPath' => 'createUser',
        'action' => 'send',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('rejects an async send step that declares correlationId', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'action' => 'send',
        'channelPath' => 'createUser',
        'correlationId' => 'id123',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('accepts an async receive step with correlationId', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'action' => 'receive',
        'channelPath' => 'createUser',
        'correlationId' => 'id123',
    ]], sourceType: 'asyncapi'));

    expect($errors->errors())->toBeEmpty();
});

it('rejects a workflow step that also declares an async action', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'workflowId' => 'child',
        'action' => 'send',
    ]]));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('rejects a workflow step that also declares a channelPath', function (): void {
    $errors = proseErrors(proseRaw('1.1.0', [[
        'stepId' => 's',
        'workflowId' => 'child',
        'channelPath' => 'orders',
    ]]));

    expect($errors->errors())->not->toBeEmpty()
        ->and($errors->errors()[0]->code)->toBe('schema.invalid');
});

it('applies the same step discrimination to 1.2 documents', function (): void {
    $accepted = proseErrors(proseRaw('1.2.0', [[
        'stepId' => 's',
        'operationId' => 'createUser',
        'action' => 'send',
    ]], sourceType: 'asyncapi'));

    $rejected = proseErrors(proseRaw('1.2.0', [[
        'stepId' => 's',
        'workflowId' => 'child',
        'action' => 'send',
    ]]));

    expect($accepted->errors())->toBeEmpty()
        ->and($rejected->errors())->not->toBeEmpty();
});
