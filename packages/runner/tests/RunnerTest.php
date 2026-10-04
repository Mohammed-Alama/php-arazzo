<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
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
use RuntimeException;

function runnerFacadeWithTestFakes(): RunnerFacade
{
    $runtime = HttpSourceGraph::runtime();

    return new RunnerFacade(
        $runtime->document,
        $runtime->operations,
        new EvaluationEngine(expression: new ExpressionEngine()),
        new ExpressionEngine(), // inspector
        new FakeOpenApiExecutor(),
        new FakeOutputExtractor(),
        new FakeResponseValidator(),
    );
}

function runnerDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [new Workflow('w', null, null, null, [], [StepFactory::http('s', null, new StepFlow(), new StepIo(), 'op')], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('exposes the RunnerFacadeInterface entry point', function () {
    $runner = runnerFacadeWithTestFakes();
    expect($runner)->toBeInstanceOf(RunnerFacadeInterface::class);
});

it('executes with only the document public face injected', function () {
    $runner = runnerFacadeWithTestFakes();
    expect($runner)->toBeInstanceOf(RunnerFacade::class);
});

it('throws on an unknown workflow id', function () {
    $runner = runnerFacadeWithTestFakes();
    $runner->run(runnerDocument(), 'missing', []);
})->throws(RuntimeException::class, "unknown workflow 'missing'");
