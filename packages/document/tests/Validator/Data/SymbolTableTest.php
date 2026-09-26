<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validator\Data;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;

it('builds symbol table from document', function (): void {
    $step = StepFactory::http(
        stepId: 'fetch',
        description: null,
        flow: new StepFlow(),
        io: new StepIo(outputs: ['user' => new Expression('{$response.body}')]),
        operationId: 'op',
    );
    $wf = new Workflow(
        workflowId: 'main',
        summary: null, description: null,
        inputs: ['type' => 'object', 'properties' => ['userId' => ['type' => 'string']]],
        dependsOn: [],
        steps: [$step],
        successActions: [], failureActions: [],
        outputs: ['user' => new Expression('{$steps.fetch.outputs.user}')],
        parameters: [],
    );
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $sym = SymbolTable::build($doc);

    expect($sym->sourceDescriptions)->toHaveKey('api')
        ->and($sym->workflows)->toHaveKey('main')
        ->and($sym->workflows['main']->inputs)->toHaveKey('userId')
        ->and($sym->workflows['main']->stepsById)->toHaveKey('fetch')
        ->and($sym->workflows['main']->stepsById['fetch']->outputs)->toHaveKey('user')
        ->and($sym->workflows['main']->stepsById['fetch']->index)->toBe(0)
        ->and($sym->workflows['main']->outputs)->toHaveKey('user');
});

it('does not throw on missing or malformed data', function (): void {
    // We instantiate ArazzoDocument with invalid types internally to simulate what
    // could happen before strict validation is run, since SymbolTable is defensive.
    $doc = (new \ReflectionClass(ArazzoDocument::class))->newInstanceWithoutConstructor();

    // Uninitialized properties shouldn't crash it
    $sym = SymbolTable::build($doc);

    expect($sym->workflows)->toBe([])
        ->and($sym->sourceDescriptions)->toBe([]);
});

it('extracts valid parts even when other parts are malformed or missing', function (): void {
    $doc = (new \ReflectionClass(ArazzoDocument::class))->newInstanceWithoutConstructor();

    // Give it valid workflows but NO source descriptions or components
    // Instantiate Workflow without constructor to leave its identifiers and collections uninitialized
    $wf = (new \ReflectionClass(Workflow::class))->newInstanceWithoutConstructor();

    // Set ONLY the workflowId to simulate a partially hydrated object
    $wfProp = new \ReflectionProperty(Workflow::class, 'workflowId');
    $wfProp->setValue($wf, 'partial_wf');

    $docProp = new \ReflectionProperty(ArazzoDocument::class, 'workflows');
    $docProp->setValue($doc, [$wf]);

    $sym = SymbolTable::build($doc);

    // Workflows should be extracted properly and safely ignore its uninitialized collections (steps, parameters, etc.)
    expect($sym->workflows)->toHaveKey('partial_wf')
        // And the rest should be empty arrays safely extracted
        ->and($sym->sourceDescriptions)->toBe([]);
});
