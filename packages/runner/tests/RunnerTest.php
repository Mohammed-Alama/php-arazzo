<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;

function runnerDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [new Workflow('w', null, null, null, [], [new Step('s', null, 'op', null, null, [], null, [], [], [], [])], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function runnerFacade(): RunnerFacade
{
    return new RunnerFacade(new Document());
}

it('exposes the RunnerFacadeInterface entry point', function () {
    $runner = runnerFacade();
    expect($runner)->toBeInstanceOf(RunnerFacadeInterface::class);
});

it('executes with only the document public face injected', function () {
    $runner = runnerFacade();
    expect($runner)->toBeInstanceOf(RunnerFacade::class);
});

it('throws on an unknown workflow id', function () {
    $runner = runnerFacade();
    $runner->run(runnerDocument(), 'missing', []);
})->throws(RuntimeException::class, "unknown workflow 'missing'");
