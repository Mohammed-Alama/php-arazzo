<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Async\TransitionApplier;
use Alama\Arazzo\Runner\Async\WorkerEvents;
use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Execution\ExecutionStatus;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\Execution\Transition;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Tests\Support\RecordingEventLedger;
use Alama\Arazzo\Tests\Support\RecordingExecutionRegistry;
use Alama\Arazzo\Tests\Support\RecordingStateStore;

function applierStep(string $id): Step
{
    return new Step($id, null, null, null, null, [], null, [], [], [], []);
}

function applierWorkflow(): Workflow
{
    return new Workflow('wf', null, null, null, [], [
        applierStep('done'),
        applierStep('next'),
    ], [], [], ['final' => new Expression('{$outputs.x}')], []);
}

function applierFixtures(): array
{
    $store = new RecordingStateStore();
    $registry = new RecordingExecutionRegistry();
    $ledger = new RecordingEventLedger();
    $queue = new SyncQueueDriver();
    $dispatcher = new SimpleEventDispatcher();
    $events = new WorkerEvents($dispatcher);
    $engine = new WorkflowEngine(new class() implements ExpressionResolverInterface
    {
        public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
        {
            return 'output-x';
        }

        public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
        {
        }

        public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
        {
            return [];
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }
    });

    $applier = new TransitionApplier($store, $registry, $ledger, $queue, $engine, $events, 777);

    return [$applier, $store, $registry, $ledger, $queue, $dispatcher];
}

function applierDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('A', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [applierWorkflow()],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function applierState(string $executionId): ExecutionState
{
    return ExecutionState::start($executionId, 'def', 'wf');
}

it('persists the next context and dispatches the follow-up job on continue', function (): void {
    [$applier, $store, , , $queue] = applierFixtures();

    $workflow = applierWorkflow();
    $state = applierState('c1')->withCurrentStep('done');
    $transition = Transition::next($state->withCurrentStep('next'), 'next');

    $outcome = $applier->apply(applierDocument(), $workflow, $workflow->steps[0], $transition, 'c1');

    expect($outcome)->toBe(TransitionApplier::OUTCOME_CONTINUE)
        ->and(isset($store->saved['c1']))->toBeTrue()
        ->and(count($queue->dispatched))->toBe(1)
        ->and($queue->dispatched[0]['delaySeconds'])->toBe(0)
        ->and($queue->dispatched[0]['job']->step->stepId ?? null)->toBe('next');
});

it('completes the run as succeeded with workflow outputs on terminal success', function (): void {
    [$applier, , $registry, $ledger, , $dispatcher] = applierFixtures();
    $completed = [];
    $dispatcher->subscribe(RunCompleted::class, function (RunCompleted $e) use (&$completed) {
        $completed[] = $e;
    });

    $workflow = applierWorkflow();
    $transition = Transition::end(applierState('t1'), 'succeeded');

    $outcome = $applier->apply(applierDocument(), $workflow, $workflow->steps[0], $transition, 't1');

    expect($outcome)->toBe(TransitionApplier::OUTCOME_TERMINAL)
        ->and($registry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded)
        ->and($ledger->eventTypes())->toContain('execution.succeeded')
        ->and(count($completed))->toBe(1);
});

it('fails the run with a reason on terminal failure', function (): void {
    [$applier, , $registry, $ledger, , $dispatcher] = applierFixtures();
    $failed = [];
    $dispatcher->subscribe(RunFailed::class, function (RunFailed $e) use (&$failed) {
        $failed[] = $e;
    });

    $workflow = applierWorkflow();
    $transition = Transition::end(applierState('t2'), 'failed');

    $outcome = $applier->apply(applierDocument(), $workflow, $workflow->steps[0], $transition, 't2');

    expect($outcome)->toBe(TransitionApplier::OUTCOME_TERMINAL)
        ->and($registry->completed[0]['status'])->toBe(ExecutionStatus::Failed)
        ->and($ledger->eventTypes())->toContain('execution.failed')
        ->and($failed[0]->cause->getMessage())->toContain("failed at step 'done'");
});

it('aborts cleanly when the follow-up workflow is missing from the document', function (): void {
    [$applier, , , $ledger, $queue, $dispatcher] = applierFixtures();
    $failed = [];
    $dispatcher->subscribe(RunFailed::class, function (RunFailed $e) use (&$failed) {
        $failed[] = $e;
    });

    $workflow = applierWorkflow();
    $state = applierState('g1');
    $transition = Transition::goto($state->withWorkflow('ghost_wf'), 'whatever', 'ghost_wf');

    $outcome = $applier->apply(applierDocument(), $workflow, $workflow->steps[0], $transition, 'g1');

    expect($outcome)->toBe(TransitionApplier::OUTCOME_ABORTED)
        ->and($ledger->eventTypes())->toContain('execution.workflow_missing')
        ->and(count($failed))->toBe(1)
        ->and($queue->dispatched)->toBe([]);
});

it('suspension transitions only persist; side effects belong to SuspensionHandler', function (): void {
    [$applier, $store, $registry, $ledger, $queue] = applierFixtures();

    $workflow = applierWorkflow();
    $transition = Transition::suspend(applierState('s9'));

    $outcome = $applier->apply(applierDocument(), $workflow, $workflow->steps[0], $transition, 's9');

    expect($outcome)->toBe(TransitionApplier::OUTCOME_CONTINUE)
        ->and(isset($store->saved['s9']))->toBeTrue()
        ->and($queue->dispatched)->toBe([])
        ->and($registry->completed)->toBe([]);
});
