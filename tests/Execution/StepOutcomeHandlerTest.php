<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\FailureEndAction;
use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\Exceptions\GotoTargetNotFoundException;
use Alama\LaravelArazzo\Execution\ExecutionStatus;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\SubWorkflowInvoker;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\SelectorEvaluator;
use Psr\Http\Message\RequestInterface;

class StepOutcomeMockExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<array{executionId: string, status: ExecutionStatus}> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void
    {
    }

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = ['executionId' => $executionId, 'status' => $status];
    }
}

class StepOutcomeMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class StepOutcomeMockPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    /** @var array<string, bool> */
    public array $outstanding = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->outstanding[$executionId] = true;
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return $this->outstanding[$executionId] ?? false;
    }
}

class StepOutcomeMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(\Alama\LaravelArazzo\Dto\Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new \LogicException('not used by StepOutcomeHandler tests');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    // Test convention: an empty criteria list always matches (unconditional action); a
    // non-empty list matches only when its first criterion's condition is the literal
    // string 'MATCH'. Keeps these tests independent of the real criterion evaluator.
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if ($criteria === []) {
            return true;
        }

        return $criteria[0]->condition === 'MATCH';
    }
}

/**
 * @param list<FailureAction|Reusable> $onFailure
 * @param list<SuccessAction|Reusable> $onSuccess
 * @param array<int|string, mixed> $dependsOn
 */
function stepOutcomeStep(string $id, array $onFailure = [], array $onSuccess = [], array $dependsOn = []): Step
{
    /** @phpstan-ignore argument.type */
    return new Step($id, null, null, null, null, [], null, [], array_values($onSuccess), array_values($onFailure), [], $dependsOn);
}

/**
 * @param list<Step> $steps
 * @param list<FailureAction|Reusable> $failureActions
 * @param list<SuccessAction|Reusable> $successActions
 */
function stepOutcomeWorkflow(string $id, array $steps, array $failureActions = [], array $successActions = []): Workflow
{
    return new Workflow($id, null, null, null, [], array_values($steps), array_values($successActions), array_values($failureActions), [], []);
}

/**
 * @param list<Workflow> $workflows
 */
function stepOutcomeDocument(array $workflows, ?Components $components = null): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: array_values($workflows),
        components: $components ?? new Components([], [], [], []),
        specificationExtensions: [],
    );
}

class StepOutcomeMockStateStore implements StateStoreInterface
{
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return null;
    }
}

/**
 * @return array{0: StepOutcomeHandler, 1: SyncQueueDriver, 2: StepOutcomeMockExecutionRegistry, 3: StepOutcomeMockEventLedger, 4: StepOutcomeMockPendingCorrelationRegistry, 5: StepOutcomeMockStateStore}
 */
function makeStepOutcomeHandler(int $maxRetryAttempts = 10, bool $pendingCorrelationOutstanding = false): array
{
    $queue = new SyncQueueDriver();
    $store = new StepOutcomeMockStateStore();
    $engine = new Engine($queue, $store);
    $executionRegistry = new StepOutcomeMockExecutionRegistry();
    $eventLedger = new StepOutcomeMockEventLedger();
    $pendingCorrelations = new StepOutcomeMockPendingCorrelationRegistry();
    if ($pendingCorrelationOutstanding) {
        $pendingCorrelations->outstanding['exec_1'] = true;
    }
    $resolver = new StepOutcomeMockExpressionResolver();

    $handler = new StepOutcomeHandler(
        $queue, $engine, $executionRegistry, $eventLedger, $pendingCorrelations, $resolver, $store,
        \Mockery::mock(SubWorkflowInvoker::class),
        \Mockery::mock(SelectorEvaluator::class),
        \Mockery::mock(ExpressionEvaluator::class),
        $maxRetryAttempts,
    );

    return [$handler, $queue, $executionRegistry, $eventLedger, $pendingCorrelations, $store];
}

it('continues normally and dispatches the next runnable step when criteria met and no actions match', function (): void {
    [$handler, $queue, , , , $store] = makeStepOutcomeHandler();

    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);

    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', true);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['job']->step->stepId)->toBe('B');
    expect($store->saves['exec_1']['steps']['A']['status'])->toBe(StepStatus::Succeeded);
});

it('terminates the execution as failed when criteria not met and no failure action matches', function (): void {
    [$handler, , $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($executionRegistry->completed)->toHaveCount(1);
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.failed');
});

it('retries the same step with the configured delay and increments attempts', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 30, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['delaySeconds'])->toBe(30);
    $dispatchedJob = $queue->dispatched[0]['job'];
    expect($dispatchedJob->step->stepId)->toBe('A');
    expect($dispatchedJob->context->getStepAttempts('A'))->toBe(1);
    expect($dispatchedJob->context->getStepStatus('A'))->toBe(StepStatus::Retrying);
});

it('falls through to the next onFailure action once the retry limit is exhausted', function (): void {
    [$handler, $queue, $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 0, retryLimit: 1, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);

    // Simulate this being the step's 2nd failure -- attempts already at the limit (1).
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepAttemptIncremented('A');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toBeEmpty();
    expect($eventLedger->appended[0]['eventType'])->toBe('step.retry_exhausted');
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
});

it('honors a config-level retry ceiling even when the document retryLimit is higher', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler(maxRetryAttempts: 2);

    $retry = new RetryAction('retry-1', retryAfter: 0, retryLimit: 100, stepId: null, workflowId: null, criteria: []);
    $step = stepOutcomeStep('A', onFailure: [$retry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);

    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepAttemptIncremented('A')
        ->withStepAttemptIncremented('A');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toBeEmpty();
});

it('retries into a different target step, marking it Pending', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('retry-1', retryAfter: 5, retryLimit: 3, stepId: 'B', workflowId: null, criteria: []);
    $stepA = stepOutcomeStep('A', onFailure: [$retry]);
    $stepB = stepOutcomeStep('B');
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', false);

    $dispatchedJob = $queue->dispatched[0]['job'];
    expect($dispatchedJob->step->stepId)->toBe('B');
    expect($dispatchedJob->context->getStepStatus('B'))->toBe(StepStatus::Pending);
});

it('resolves a Reusable failure action from components before matching', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $retry = new RetryAction('shared-retry', retryAfter: 10, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $components = new Components([], [], [], ['sharedRetry' => $retry]);
    $reusable = new Reusable('$components.failureActions.sharedRetry');

    $step = stepOutcomeStep('A', onFailure: [$reusable]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow], $components);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    expect($queue->dispatched[0]['delaySeconds'])->toBe(10);
});

it('only matches an action whose own criteria evaluate true, skipping ones that do not', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $skippedRetry = new RetryAction('no-match', retryAfter: 1, retryLimit: 3, stepId: null, workflowId: null, criteria: [
        new SuccessCriterion(null, 'NO_MATCH', null),
    ]);
    $matchedRetry = new RetryAction('matches', retryAfter: 2, retryLimit: 3, stepId: null, workflowId: null, criteria: [
        new SuccessCriterion(null, 'MATCH', null),
    ]);
    $step = stepOutcomeStep('A', onFailure: [$skippedRetry, $matchedRetry]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($queue->dispatched[0]['delaySeconds'])->toBe(2);
});

it('goto jumps to a same-workflow step directly, bypassing DependencyAnalyzer ordering', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-c', 'C', null, []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $stepB = stepOutcomeStep('B');
    $stepC = stepOutcomeStep('C', dependsOn: ['B']); // C would not normally be runnable yet
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB, $stepC]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('C');
    expect($job->context->getStepStatus('C'))->toBe(StepStatus::Pending);
    expect($job->context->getStepStatus('A'))->toBe(StepStatus::Failed);
});

it('goto loop-back resets an already-succeeded target step to Pending so it re-runs', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-a', 'A', null, []);
    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', onFailure: [$goto], dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepResult('A', ['statusCode' => 200])
        ->withStepStatus('A', StepStatus::Succeeded);

    $handler->handle($document, $workflow, $stepB, $context, 'exec_1', false);

    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('A');
    expect($job->context->getStepStatus('A'))->toBe(StepStatus::Pending);
    expect($job->context->getStepStatus('B'))->toBe(StepStatus::Failed);
});

it('goto to a workflowId-only target hands off to Engine::evaluate for the target workflow entry steps', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-wf2', null, 'wf_2', []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow1 = stepOutcomeWorkflow('wf_1', [$stepA]);
    $entryStep = stepOutcomeStep('entry');
    $workflow2 = stepOutcomeWorkflow('wf_2', [$entryStep]);
    $document = stepOutcomeDocument([$workflow1, $workflow2]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow1, $stepA, $context, 'exec_1', false);

    expect($queue->dispatched)->toHaveCount(1);
    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('entry');
    expect($job->context->getWorkflowId())->toBe('wf_2');
    expect($job->context->getStepStatus('A'))->toBe(StepStatus::Failed);
});

it('goto with both workflowId and stepId jumps directly to that step in the target workflow', function (): void {
    [$handler, $queue] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-wf2-mid', 'mid', 'wf_2', []);
    $stepA = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow1 = stepOutcomeWorkflow('wf_1', [$stepA]);
    $entryStep = stepOutcomeStep('entry');
    $midStep = stepOutcomeStep('mid', dependsOn: ['entry']);
    $workflow2 = stepOutcomeWorkflow('wf_2', [$entryStep, $midStep]);
    $document = stepOutcomeDocument([$workflow1, $workflow2]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow1, $stepA, $context, 'exec_1', false);

    $job = $queue->dispatched[0]['job'];
    expect($job->step->stepId)->toBe('mid');
    expect($job->context->getWorkflowId())->toBe('wf_2');
    expect($job->context->getStepStatus('A'))->toBe(StepStatus::Failed);
});

it('goto to an unknown workflowId throws GotoTargetNotFoundException', function (): void {
    [$handler] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-missing', null, 'does-not-exist', []);
    $step = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    expect(fn () => $handler->handle($document, $workflow, $step, $context, 'exec_1', false))
        ->toThrow(GotoTargetNotFoundException::class);
});

it('goto to an unknown stepId in the current workflow throws GotoTargetNotFoundException', function (): void {
    [$handler] = makeStepOutcomeHandler();

    $goto = new FailureGotoAction('goto-missing-step', 'nope', null, []);
    $step = stepOutcomeStep('A', onFailure: [$goto]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    expect(fn () => $handler->handle($document, $workflow, $step, $context, 'exec_1', false))
        ->toThrow(GotoTargetNotFoundException::class);
});

it('SuccessEndAction terminates the execution as succeeded', function (): void {
    [$handler, $queue, $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $end = new SuccessEndAction('end-ok', []);
    $step = stepOutcomeStep('A', onSuccess: [$end]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($queue->dispatched)->toBeEmpty();
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.succeeded');
});

it('FailureEndAction terminates the execution as failed', function (): void {
    [$handler, , $executionRegistry, $eventLedger] = makeStepOutcomeHandler();

    $end = new FailureEndAction('end-fail', []);
    $step = stepOutcomeStep('A', onFailure: [$end]);
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', false);

    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Failed);
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.failed');
});

it('auto-completes the execution as succeeded once no steps are runnable and nothing is suspended', function (): void {
    [$handler, , $executionRegistry] = makeStepOutcomeHandler();

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($executionRegistry->completed)->toHaveCount(1);
    expect($executionRegistry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded);
});

it('does not auto-complete while a PendingCorrelation is still outstanding for the execution', function (): void {
    [$handler, , $executionRegistry] = makeStepOutcomeHandler(pendingCorrelationOutstanding: true);

    $step = stepOutcomeStep('A');
    $workflow = stepOutcomeWorkflow('wf_1', [$step]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);

    expect($executionRegistry->completed)->toBeEmpty();
});

it('does not auto-complete while downstream steps are still runnable', function (): void {
    [$handler, $queue, $executionRegistry] = makeStepOutcomeHandler();

    $stepA = stepOutcomeStep('A');
    $stepB = stepOutcomeStep('B', dependsOn: ['A']);
    $workflow = stepOutcomeWorkflow('wf_1', [$stepA, $stepB]);
    $document = stepOutcomeDocument([$workflow]);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_1')->withExecutionId('exec_1');

    $handler->handle($document, $workflow, $stepA, $context, 'exec_1', true);

    expect($queue->dispatched)->toHaveCount(1);
    expect($executionRegistry->completed)->toBeEmpty();
});
