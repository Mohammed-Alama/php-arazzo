<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Parser\Parser;

/** @param array<string,mixed> $data */
function rawWith(array $data): RawDocument
{
    return new RawDocument($data, '/x.yaml', Format::Yaml);
}

it('throws on missing info', function (): void {
    expect(fn () => (new Parser())->parse(rawWith(['arazzo' => '1.0.0', 'workflows' => []])))
        ->toThrow(ParserException::class);
});

it('throws on missing workflows', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
    ])))->toThrow(ParserException::class);
});

it('throws when optionalString value has wrong type', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1', 'summary' => 123],
        'workflows' => [],
    ])))->toThrow(ParserException::class);
});

it('throws on non-string dependsOn item', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [[
            'workflowId' => 'w', 'dependsOn' => [123], 'steps' => [[
                'stepId' => 's', 'operationId' => 'op',
            ]],
        ]],
    ])))->toThrow(ParserException::class);
});

it('throws when workflow is missing steps', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [['workflowId' => 'w']],
    ])))->toThrow(ParserException::class);
});

it('throws when parameter is missing value', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [[
            'workflowId' => 'w', 'steps' => [[
                'stepId' => 's', 'operationId' => 'op',
                'parameters' => [['name' => 'p']],
            ]],
        ]],
    ])))->toThrow(ParserException::class);
});

it('throws when payload replacement is missing value', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [[
            'workflowId' => 'w', 'steps' => [[
                'stepId' => 's', 'operationId' => 'op',
                'requestBody' => ['replacements' => [['target' => '/t']]],
            ]],
        ]],
    ])))->toThrow(ParserException::class);
});

it('throws when optionalInt value is not an int', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [[
            'workflowId' => 'w', 'steps' => [[
                'stepId' => 's', 'operationId' => 'op',
                'onFailure' => [['name' => 'r', 'type' => 'retry', 'retryAfter' => 'notInt']],
            ]],
        ]],
    ])))->toThrow(ParserException::class);
});

it('throws when outputs map value is not a string', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [[
            'workflowId' => 'w', 'steps' => [[
                'stepId' => 's', 'operationId' => 'op',
                'outputs' => ['o' => 123],
            ]],
        ]],
    ])))->toThrow(ParserException::class);
});

it('throws when components.inputs child is not array', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [],
        'components' => ['inputs' => ['x' => 'notObject']],
    ])))->toThrow(ParserException::class);
});

it('throws when components.successActions holds a Reusable', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [],
        'components' => ['successActions' => ['x' => ['reference' => '$components.successActions.y']]],
    ])))->toThrow(ParserException::class);
});

it('throws when components.failureActions holds a Reusable', function (): void {
    expect(fn () => (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [],
        'components' => ['failureActions' => ['x' => ['reference' => '$components.failureActions.y']]],
    ])))->toThrow(ParserException::class);
});

it('accepts a full doc with components, actions, criteria, outputs, extensions', function (): void {
    $doc = (new Parser())->parse(rawWith([
        'arazzo' => '1.0.0',
        'info' => ['title' => 't', 'version' => '1'],
        'sourceDescriptions' => [['name' => 's', 'url' => '/u', 'type' => 'openapi']],
        'workflows' => [[
            'workflowId' => 'w',
            'dependsOn' => [],
            'inputs' => ['type' => 'object'],
            'parameters' => [['name' => 'p', 'in' => 'query', 'value' => '{$inputs.x}']],
            'successActions' => [['name' => 'a', 'type' => 'end']],
            'failureActions' => [['name' => 'b', 'type' => 'retry', 'retryAfter' => 1, 'retryLimit' => 2]],
            'outputs' => ['o' => '{$inputs.x}'],
            'steps' => [[
                'stepId' => 'st',
                'description' => 'd',
                'operationId' => 'op',
                'parameters' => [['name' => 'p', 'value' => 'lit']],
                'requestBody' => ['contentType' => 'application/json', 'payload' => '{$inputs.x}', 'replacements' => [['target' => '/t', 'value' => 'v']]],
                'successCriteria' => [['condition' => '$statusCode == 200', 'type' => 'simple']],
                'onSuccess' => [['reference' => '$components.successActions.gotoRef'], ['name' => 'g', 'type' => 'goto', 'stepId' => 'st']],
                'onFailure' => [['name' => 'g', 'type' => 'goto', 'workflowId' => 'w']],
                'outputs' => ['o2' => '{$steps.st.response.body}'],
            ]],
        ]],
        'components' => [
            'inputs' => ['iX' => ['type' => 'object']],
            'parameters' => ['pX' => ['name' => 'pX', 'value' => 'v']],
            'successActions' => ['sA' => ['name' => 'sA', 'type' => 'end']],
            'failureActions' => ['fA' => ['name' => 'fA', 'type' => 'end']],
        ],
        'x-vendor' => 'ok',
    ]));
    expect($doc->workflows)->toHaveCount(1);
});
