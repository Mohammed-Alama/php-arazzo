<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Events\StepExecutedEvent as EventStepExecuted;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepStartedEvent;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Tests\Expression\Support\TestEvaluationEngine;

function createRecordingStepExec(bool $succeed = true, ?Throwable $throw = null): StepExecutor
{
    return new class($succeed, $throw) extends StepExecutor
    {
        public function __construct(private bool $succeed, private ?Throwable $throw) {}

        public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
        {
            if ($this->throw) {
                throw $this->throw;
            }

            return [$context->withStepResult($step->stepId, ['outputs' => ['x' => 1]]), $this->succeed];
        }
    };
}

function docWithWorkflow(Workflow $wf): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function captureEvents(SimpleEventDispatcher $d, array &$log): void
{
    foreach ([RunStartedEvent::class, RunCompletedEvent::class, RunFailedEvent::class,
        StepStartedEvent::class, EventStepExecuted::class, StepFailedEvent::class] as $cls) {
        $d->subscribe($cls, function ($e) use (&$log, $cls) {
            $log[] = basename(str_replace('\\', '/', $cls));
        });
    }
}

it('dispatches happy-path sequence RunStartedEvent -> StepStartedEvent -> StepExecutedEvent -> RunCompletedEvent', function () {
    $step = StepFactory::http('A', null, new StepFlow(), new StepIo(), 'op');
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(), new WorkflowEngine(new TestEvaluationEngine()), events: $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'StepExecutedEvent', 'RunCompletedEvent']);
});

it('dispatches StepFailedEvent + RunFailedEvent on step failure', function () {
    $step = StepFactory::http('A', null, new StepFlow(), new StepIo(), 'op');
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(succeed: false), new WorkflowEngine(new TestEvaluationEngine()), events: $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'StepFailedEvent', 'RunFailedEvent']);
});

it('dispatches RunFailedEvent and rethrows on caught exception', function () {
    $step = StepFactory::http('A', null, new StepFlow(), new StepIo(), 'op');
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    $executor = new WorkflowExecutor(createRecordingStepExec(throw: new RuntimeException('crash')), new WorkflowEngine(new TestEvaluationEngine()), events: $d);

    expect(fn () => $executor->execute($wf, docWithWorkflow($wf), []))
        ->toThrow(RuntimeException::class, 'crash');

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'RunFailedEvent']);
});
