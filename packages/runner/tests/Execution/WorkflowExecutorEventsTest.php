<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Runner\Events\RunCompletedEvent;
use Alama\Arazzo\Runner\Events\RunFailedEvent;
use Alama\Arazzo\Runner\Events\RunStartedEvent;
use Alama\Arazzo\Runner\Events\StepExecutedEvent as EventStepExecuted;
use Alama\Arazzo\Runner\Events\StepFailedEvent;
use Alama\Arazzo\Runner\Events\StepStartedEvent;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;

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
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(), new WorkflowEngine(new TestExpressionResolver()), events: $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'StepExecutedEvent', 'RunCompletedEvent']);
});

it('dispatches StepFailedEvent + RunFailedEvent on step failure', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(succeed: false), new WorkflowEngine(new TestExpressionResolver()), events: $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'StepFailedEvent', 'RunFailedEvent']);
});

it('dispatches RunFailedEvent and rethrows on caught exception', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    $executor = new WorkflowExecutor(createRecordingStepExec(throw: new RuntimeException('crash')), new WorkflowEngine(new TestExpressionResolver()), events: $d);

    expect(fn () => $executor->execute($wf, docWithWorkflow($wf), []))
        ->toThrow(RuntimeException::class, 'crash');

    expect($log)->toBe(['RunStartedEvent', 'StepStartedEvent', 'RunFailedEvent']);
});
