<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

class ResumerMockLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return true;
    }

    public function release(string $key): void {}
}

class ResumerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void {}

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
        $this->consumed[] = $correlationId;
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class ResumerMockStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $preloaded = [];

    /** @var array<string, array<string, mixed>> */
    public array $saves = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saves[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->preloaded[$executionId] ?? null;
    }

    public function delete(string $executionId): void
    {
        unset($this->preloaded[$executionId]);
        unset($this->saves[$executionId]);
    }
}

class ResumerMockEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class ResumerMockExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}

class RecordingStepOutcomeHandler
{
    public array $handled = [];

    public function __construct(
        private StepOutcomeHandler $decorated,
    ) {}

    public function handle(string $executionId, Step $step, \Alama\Arazzo\Contracts\Spec\StepExecutionOutcome $outcome): void
    {
        $this->handled[] = [$executionId, $step->stepId, $outcome];
        $this->decorated->handle($executionId, $step, $outcome);
    }
}

class RecordingLockManager implements LockManagerInterface
{
    /** @var list<string> */
    public array $acquired = [];

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $this->acquired[] = $key;
        return $callback();
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        return true;
    }

    public function release(string $key): void {}
}

class RecordingPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->created[] = $correlationId;
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->toReturn;
    }

    public function consume(string $correlationId): void
    {
        $this->consumed[] = $correlationId;
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class RecordingDefinitionRegistry implements \Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface
{
    public function register(\Alama\Arazzo\Contracts\Spec\ArazzoDocument $document): string { return 'test-def'; }
    public function get(string $definitionId): ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument { return null; }
}

function resumerDocument(): array
{
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('wait-for-ride', null, StepTarget::async('receive', 'channels/rides/created'), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);
    $definitionId = $definitionRegistry->register($document);

    return [$definitionRegistry, $definitionId, $workflow, $step];
}

it('does nothing when the correlation is not found', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $stateStore = new ResumerMockStateStore();
    [$definitionRegistry] = resumerDocument();
    $outcomeHandler = new RecordingStepOutcomeHandler(new StepOutcomeHandler(
        new \Alama\Arazzo\Runner\Execution\RunPersistence(new ResumerMockStateStore(), new ResumerMockEventLedger(), new \Alama\Arazzo\Runtime\State\InMemoryExecutionRegistry()),
        new \Alama\Arazzo\Runner\Execution\Data\RunControlFlow(new \Alama\Arazzo\Runner\Execution\WorkflowEngine(new ResumerMockExpressionResolver(), 10, 1.0), new \Alama\Arazzo\Contracts\Interfaces\SyncQueueDriver()),
        pendingCorrelations: $pendingCorrelations,
        invoker: new \Alama\Arazzo\Runner\Execution\SubWorkflowInvoker($definitionRegistry, new \Alama\Arazzo\Runner\Execution\WorkflowExecutor(new \Alama\Arazzo\Runner\Execution\StepExecutor(new \Alama\Arazzo\Runner\Execution\Interfaces\DefaultOpenApiExecutor(), new ResumerMockExpressionResolver(), new \Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver(), new \Alama\Arazzo\Evaluation\EvaluationEngine()), new \Alama\Arazzo\Evaluation\EvaluationEngine()), new \Alama\Arazzo\Evaluation\EvaluationEngine()),
        engine: new \Alama\Arazzo\Evaluation\EvaluationEngine(),
        stateTtlSeconds: 86400,
    ));

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, new ResumerMockEventLedger(), new ResumerMockLockManager());

    $resumer->resume('missing', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBeEmpty()
        ->and($pendingCorrelations->consumed)->toBeEmpty();
});

it('logs and does nothing when persisted state is missing', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $stateStore = new ResumerMockStateStore(); // nothing preloaded
    [$definitionRegistry] = resumerDocument();
    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler(new StepOutcomeHandler(
        new \Alama\Arazzo\Runner\Execution\RunPersistence(new ResumerMockStateStore(), new ResumerMockEventLedger(), new \Alama\Arazzo\Runtime\State\InMemoryExecutionRegistry()),
        new \Alama\Arazzo\Runner\Execution\Data\RunControlFlow(new \Alama\Arazzo\Runner\Execution\WorkflowEngine(new ResumerMockExpressionResolver(), 10, 1.0), new \Alama\Arazzo\Contracts\Interfaces\SyncQueueDriver()),
        pendingCorrelations: $pendingCorrelations,
        invoker: new \Alama\Arazzo\Runner\Execution\SubWorkflowInvoker($definitionRegistry, new \Alama\Arazzo\Runner\Execution\WorkflowExecutor(new \Alama\Arazzo\Runner\Execution\StepExecutor(new \Alama\Arazzo\Runner\Execution\Interfaces\DefaultOpenApiExecutor(), new ResumerMockExpressionResolver(), new \Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver(), new \Alama\Arazzo\Evaluation\EvaluationEngine()), new \Alama\Arazzo\Evaluation\EvaluationEngine()), new \Alama\Arazzo\Evaluation\EvaluationEngine()),
        engine: new \Alama\Arazzo\Evaluation\EvaluationEngine(),
        stateTtlSeconds: 86400,
    ));

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $eventLedger, new ResumerMockLockManager());

    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBeEmpty()
        ->and($eventLedger->appended[0]['eventType'])->toBe('execution.state_missing');
});
