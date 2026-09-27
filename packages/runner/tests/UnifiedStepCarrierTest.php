<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Engine\Data\Transition;
use Alama\Arazzo\Engine\WorkflowEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\UnifiedStepCarrier;
use Alama\Arazzo\Runtime\Infrastructure\NullLockStrategy;
use Alama\Arazzo\Runtime\State\InMemoryStateStore;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface;

class UnifiedTestEventLedger implements EventLedgerInterface
{
    /** @var list<string> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = $eventType;
    }
}

class UnifiedTestExecutionRegistry implements ExecutionRegistryInterface
{
    /** @var list<string> */
    public array $completed = [];

    public function start(string $executionId, string $definitionId, string $workflowId): void {}

    public function complete(string $executionId, ExecutionStatus $status): void
    {
        $this->completed[] = $status->value;
    }
}

class UnifiedTestPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    public array $outstanding = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->outstanding[$executionId] = true;
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void {}

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class SuccessfulPlugin implements OperationExecutorPluginInterface, PluginInterface
{
    public function name(): string
    {
        return 'stub';
    }

    public function priority(): int
    {
        return 100;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return true;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, ['ok' => true], ['ok' => true]);
    }
}

function unifiedTestResolver(): ExpressionResolverInterface
{
    return new class() implements ExpressionResolverInterface
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
    };
}

function unifiedTestWorkflowEngine(): WorkflowEngineInterface
{
    return new class() implements WorkflowEngineInterface
    {
        public function transition(
            ArazzoDocument $document,
            Workflow $workflow,
            Step $step,
            ExecutionState $state,
            bool $criteriaMet,
        ): Transition {
            return new Transition($state, 'succeeded');
        }
    };
}

function unifiedTestDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

it('executes a step through the plugin and persists the outcome', function (): void {
    $store = new InMemoryStateStore();
    $ledger = new UnifiedTestEventLedger();
    $executionRegistry = new UnifiedTestExecutionRegistry();
    $pendingCorrelations = new UnifiedTestPendingCorrelationRegistry();
    $lockManager = new NullLockStrategy();
    $resolver = unifiedTestResolver();
    $workflowEngine = unifiedTestWorkflowEngine();

    $carrier = new UnifiedStepCarrier(
        stateStore: $store,
        workflowEngine: $workflowEngine,
        lockManager: $lockManager,
        executionRegistry: $executionRegistry,
        eventLedger: $ledger,
        pendingCorrelations: $pendingCorrelations,
        executorPlugins: [new SuccessfulPlugin()],
    );

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = unifiedTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $carrier->execute('exec-1', $step, $workflow, $document, $state);

    expect($store->load('exec-1'))->not->toBeNull();
});

it('throws when no plugin supports the step', function (): void {
    $store = new InMemoryStateStore();
    $ledger = new UnifiedTestEventLedger();
    $executionRegistry = new UnifiedTestExecutionRegistry();
    $pendingCorrelations = new UnifiedTestPendingCorrelationRegistry();
    $lockManager = new NullLockStrategy();
    $resolver = unifiedTestResolver();
    $workflowEngine = unifiedTestWorkflowEngine();

    $carrier = new UnifiedStepCarrier(
        stateStore: $store,
        workflowEngine: $workflowEngine,
        lockManager: $lockManager,
        executionRegistry: $executionRegistry,
        eventLedger: $ledger,
        pendingCorrelations: $pendingCorrelations,
        executorPlugins: [],
    );

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = unifiedTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $carrier->execute('exec-1', $step, $workflow, $document, $state);
})->throws(LogicException::class, 'No OperationExecutorPluginInterface supports');
