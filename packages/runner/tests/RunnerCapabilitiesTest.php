<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use Psr\Http\Client\ClientInterface;

function runnerFixtureDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [new SourceDescription('pets', __DIR__.'/fixtures/petstore.yaml', SourceType::Openapi)],
        workflows: [new Workflow('find', null, null, null, [], [new Step('list', null, 'listPets', null, null, [], null, [], [], [], [])], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

/** @return ClientInterface&MockInterface */
function runnerStubClient(): ClientInterface
{
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturn(
        new Response(200, ['Content-Type' => 'application/json'], '{"pets":[{"id":1,"name":"Rex"}]}'),
    );

    return $client;
}

it('exposes the enriched runner facade entry point', function () {
    expect(runnerStubClient())->toBeInstanceOf(ClientInterface::class)
        ->and(runnerFixtureDocument())->toBeInstanceOf(ArazzoDocument::class);
});

it('accepts the document public face as the required seam dependency', function () {
    $runner = new RunnerFacade(new Document());

    expect($runner)->toBeInstanceOf(RunnerFacadeInterface::class);
});

it('runs a workflow whose operation resolves through the document face', function () {
    $result = (new RunnerFacade(new Document(), runnerStubClient()))->run(runnerFixtureDocument(), 'find');
    expect($result['status'])->toBe('succeeded');
});

it('keeps the run output shape stable for existing consumers', function () {
    $result = (new RunnerFacade(new Document(), runnerStubClient()))->run(runnerFixtureDocument(), 'find');
    expect(array_keys($result))->toBe(['workflowId', 'status', 'outputs', 'stepsSpent', 'workflowCallStack']);
});

it('execute exposes per-step verdicts for cli and laravel consumers', function () {
    $result = (new RunnerFacade(new Document(), runnerStubClient()))->execute(runnerFixtureDocument(), 'find');

    expect($result['status'])->toBe('succeeded')
        ->and($result['steps'])->toBeArray()
        ->and(array_keys($result['steps']))->toBe(['list'])
        ->and($result['steps']['list']['success'])->toBeTrue()
        ->and($result['steps']['list']['outputs'])->toBeArray();
});
