<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted as EventStepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepStarted;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\WorkflowContext;
use Alama\Arazzo\Execution\WorkflowExecutor;

function createRecordingStepExec(bool $succeed = true, ?Throwable $throw = null): StepExecutor
{
    return new class($succeed, $throw) extends StepExecutor
    {
        public function __construct(private bool $succeed, private ?Throwable $throw)
        {
        }

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
    foreach ([RunStarted::class, RunCompleted::class, RunFailed::class,
        StepStarted::class, EventStepExecuted::class, StepFailed::class] as $cls) {
        $d->subscribe($cls, function ($e) use (&$log, $cls) {
            $log[] = basename(str_replace('\\', '/', $cls));
        });
    }
}

it('dispatches happy-path sequence RunStarted -> StepStarted -> StepExecuted -> RunCompleted', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(), null, $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStarted', 'StepStarted', 'StepExecuted', 'RunCompleted']);
});

it('dispatches StepFailed + RunFailed on step failure', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(createRecordingStepExec(succeed: false), null, $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStarted', 'StepStarted', 'StepFailed', 'RunFailed']);
});

it('dispatches RunFailed and rethrows on caught exception', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    $executor = new WorkflowExecutor(createRecordingStepExec(throw: new RuntimeException('crash')), null, $d);

    expect(fn () => $executor->execute($wf, docWithWorkflow($wf), []))
        ->toThrow(RuntimeException::class, 'crash');

    expect($log)->toBe(['RunStarted', 'StepStarted', 'RunFailed']);
});
