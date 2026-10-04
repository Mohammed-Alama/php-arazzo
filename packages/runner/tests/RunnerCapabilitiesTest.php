<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Protocol\Http\HttpSourceGraph;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;
use Alama\Arazzo\Tests\Support\FakeOpenApiExecutor;
use Alama\Arazzo\Tests\Support\FakeOutputExtractor;
use Alama\Arazzo\Tests\Support\FakeResponseValidator;
use GuzzleHttp\Psr7\Response;

function runnerFacadeWithFakes(): RunnerFacade
{
    $runtime = HttpSourceGraph::runtime();
    $seams = [
        'openApiExecutor' => new FakeOpenApiExecutor(
            new Response(200, ['Content-Type' => 'application/json'], '{"pets":[{"id":1,"name":"Rex"}]}'),
        ),
        'outputExtractor' => new FakeOutputExtractor(),
        'schemaValidator' => new FakeResponseValidator(),
    ];

    return new RunnerFacade(
        $runtime->document,
        $runtime->operations,
        new EvaluationEngine(expression: new ExpressionEngine()),
        new ExpressionEngine(), // inspector
        $seams['openApiExecutor'],
        $seams['outputExtractor'],
        $seams['schemaValidator'],
    );
}

function runnerFixtureDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [new SourceDescription('pets', __DIR__.'/fixtures/petstore.yaml', SourceType::Openapi)],
        workflows: [new Workflow('find', null, null, null, [], [StepFactory::http('list', null, new StepFlow(), new StepIo(), 'listPets')], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('exposes the enriched runner facade entry point', function () {
    $runner = runnerFacadeWithFakes();
    expect($runner)->toBeInstanceOf(RunnerFacadeInterface::class);
});

it('runs a workflow whose operation resolves through the document face', function () {
    $result = runnerFacadeWithFakes()->run(runnerFixtureDocument(), 'find');
    expect($result['status'])->toBe('succeeded');
});

it('keeps the run output shape stable for existing consumers', function () {
    $result = runnerFacadeWithFakes()->run(runnerFixtureDocument(), 'find');
    expect(array_keys($result))->toBe(['workflowId', 'status', 'outputs', 'stepsSpent', 'workflowCallStack']);
});

it('execute exposes per-step verdicts for cli and laravel consumers', function () {
    $result = runnerFacadeWithFakes()->execute(runnerFixtureDocument(), 'find');

    expect($result['status'])->toBe('succeeded')
        ->and($result['steps'])->toBeArray()
        ->and(array_keys($result['steps']))->toBe(['list'])
        ->and($result['steps']['list']['success'])->toBeTrue()
        ->and($result['steps']['list']['outputs'])->toBeArray();
});
