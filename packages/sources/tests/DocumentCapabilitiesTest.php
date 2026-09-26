<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\SourceGraph;

function capabilityDocument(array $workflows, array $sourceDescriptions = []): ArazzoDocument
{
    $document = SourceGraph::document();

    return $document->parse(new RawDocument([
        'arazzo' => '1.0.0',
        'info' => ['title' => 'Capability', 'version' => '1.0'],
        'sourceDescriptions' => $sourceDescriptions,
        'workflows' => $workflows,
    ], 'inline://capability.json', Format::Json));
}

it('resolves a source description to a source document through the face', function (): void {
    $source = new SourceDescription('pets', __DIR__.'/fixtures/document/openapi30.yaml', SourceType::Openapi);

    $resolved = SourceGraph::loader()->resolveSource($source, __DIR__.'/fixtures/document');

    expect($resolved)->toBeInstanceOf(SourceDocument::class)
        ->and($resolved->name)->toBe('pets')
        ->and($resolved->type)->toBe(SourceType::Openapi)
        ->and($resolved->content)->toHaveKey('openapi');
});

it('caches resolved sources in the registry', function (): void {
    $source = new SourceDescription('pets', __DIR__.'/fixtures/document/openapi30.yaml', SourceType::Openapi);
    $document = SourceGraph::document();

    $first = SourceGraph::loader()->resolveSource($source, __DIR__.'/fixtures/document');
    $second = SourceGraph::loader()->resolveSource($source, __DIR__.'/fixtures/document');

    expect($second)->toBe($first);
});

it('detects OpenAPI versions through the face', function (): void {
    $loader = SourceGraph::loader();

    expect($loader->detectOpenApiVersion(['openapi' => '3.0.3']))->toBe('3.0')
        ->and($loader->detectOpenApiVersion(['openapi' => '3.1.1']))->toBe('3.1')
        ->and($loader->detectOpenApiVersion(['swagger' => '2.0']))->toBe('2.0');
});

it('rejects unsupported OpenAPI versions through the face', function (): void {
    SourceGraph::loader()->detectOpenApiVersion(['openapi' => '4.0.0']);
})->throws(InvalidArgumentException::class);

it('resolves an operation-targeted step through the face', function (): void {
    $runtime = SourceGraph::runtime();
    $arazzo = capabilityDocument(
        sourceDescriptions: [
            ['name' => 'pets', 'type' => 'openapi', 'url' => __DIR__.'/fixtures/document/openapi30.yaml'],
        ],
        workflows: [
            ['workflowId' => 'pets', 'steps' => [
                ['stepId' => 'list', 'operationId' => '$sourceDescriptions.pets.listPets'],
            ]],
        ],
    );

    $step = $arazzo->workflows[0]->steps[0];

    // The port yields the vendor-free model; the cebe handle comes from the
    // resolver, which is why the runtime hands out both.
    $resolved = $runtime->document->resolveOperation($step, $arazzo);
    $handle = $runtime->operations->resolve($step, $arazzo);

    expect($resolved)->toBeInstanceOf(ResolvedOperation::class)
        ->and($resolved->normalized->path)->toBe('/pets')
        ->and($resolved->normalized->method)->toBe('get')
        ->and($resolved->normalized->resolvedServerUrl)->toBeNull()
        ->and($handle->operation->normalized->path)->toBe($resolved->normalized->path)
        ->and($handle->cebeOperation->operationId)->toBe('listPets');
});

it('fails fast when a step declares no operation target', function (): void {
    $document = SourceGraph::document();
    $arazzo = capabilityDocument(workflows: [
        ['workflowId' => 'w', 'steps' => [
            ['stepId' => 'orphan', 'requestBody' => ['payload' => ['foo' => 'bar']]],
        ]],
    ]);

    $document->resolveOperation($arazzo->workflows[0]->steps[0], $arazzo);
})->throws(RuntimeException::class, 'must have either operationId or operationPath');

it('reports unknown workflows during input preflight', function (): void {
    $document = SourceGraph::document();
    $arazzo = capabilityDocument(workflows: [
        ['workflowId' => 'w', 'steps' => []],
    ]);

    $result = $document->preflightInputs($arazzo, 'ghost', []);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->code)->toBe('preflight.unknown_workflow');
});

it('accepts inputs matching the declared workflow schema', function (): void {
    $document = SourceGraph::document();
    $arazzo = capabilityDocument(workflows: [
        ['workflowId' => 'w', 'inputs' => [
            'type' => 'object',
            'properties' => ['rideId' => ['type' => 'integer']],
            'required' => ['rideId'],
        ], 'steps' => []],
    ]);

    $result = $document->preflightInputs($arazzo, 'w', ['rideId' => 42]);

    expect($result->isValid())->toBeTrue(json_encode($result->errors));
});

it('rejects inputs violating the declared workflow schema', function (): void {
    $document = SourceGraph::document();
    $arazzo = capabilityDocument(workflows: [
        ['workflowId' => 'w', 'inputs' => [
            'type' => 'object',
            'properties' => ['rideId' => ['type' => 'integer']],
            'required' => ['rideId'],
        ], 'steps' => []],
    ]);

    $result = $document->preflightInputs($arazzo, 'w', ['rideId' => 'not-an-int']);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->code)->toBe('preflight.inputs_schema');
});

it('treats documents without an inputs schema as unconstrained', function (): void {
    $document = SourceGraph::document();
    $arazzo = capabilityDocument(workflows: [
        ['workflowId' => 'w', 'steps' => []],
    ]);

    $result = $document->preflightInputs($arazzo, 'w', ['anything' => ['goes' => true]]);

    expect($result->isValid())->toBeTrue();
});
