<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Async\CorrelationResumer;
use Alama\Arazzo\Runner\Async\StepOutcomeHandler;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

require_once __DIR__.'/../Execution/ResumerTestHelpers.php';

/**
 * CorrelationResumer type-hints the concrete StepOutcomeHandler, so the spy has
 * to extend it. It overrides handle() and never calls the parent constructor:
 * the parent's private collaborators stay uninitialised but are unreachable
 * because every inherited method that touches them is overridden away.
 */
class RecordingStepOutcomeHandler extends StepOutcomeHandler
{
    /** @var list<array{executionId: string, stepId: string, criteriaMet: bool, context: WorkflowContext}> */
    public array $handled = [];

    /**
     * Declared explicitly and deliberately empty: without it PHP would
     * implicitly call the parent constructor, which needs five collaborators.
     */
    public function __construct() {}

    public function handle(
        ArazzoDocument $document,
        Workflow $workflow,
        Step $step,
        WorkflowContext $context,
        string $executionId,
        bool $criteriaMet,
        bool $suspended = false,
    ): void {
        $this->handled[] = [
            'executionId' => $executionId,
            'stepId' => $step->stepId,
            'criteriaMet' => $criteriaMet,
            'suspended' => $suspended,
            'context' => $context,
        ];
    }
}

class ResumerMockPendingCorrelations implements PendingCorrelationRegistryInterface
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

class ResumerMockStateStore implements StateStoreInterface
{
    /** @var array<string, mixed>|null */
    public ?array $state = null;

    /** @var array<string, array<string, mixed>> */
    public array $saved = [];

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saved[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->state;
    }

    public function delete(string $executionId): void {}
}

class RecordingDefinitionRegistry implements DefinitionRegistryInterface
{
    public function __construct(private ?ArazzoDocument $document = null) {}

    public function register(ArazzoDocument $document): string
    {
        return 'test-def';
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        return $this->document;
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

class ResumerMockLockManager implements LockManagerInterface
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

it('does nothing when the correlation is not found', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = null;

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        new ResumerMockStateStore(),
        new RecordingDefinitionRegistry(),
        \Mockery::mock(OutputExtractorInterface::class),
        \Mockery::mock(EvaluationEngineInterface::class),
        $outcomeHandler,
        $eventLedger,
        new ResumerMockLockManager(),
    );

    $resumer->resume('missing', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBe([])
        ->and($pendingCorrelations->consumed)->toBe([])
        ->and($eventLedger->appended)->toBe([]);
});

it('logs and does nothing when persisted state is missing', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    // State store preloaded with nothing: load() returns null.
    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        new ResumerMockStateStore(),
        new RecordingDefinitionRegistry(),
        \Mockery::mock(OutputExtractorInterface::class),
        \Mockery::mock(EvaluationEngineInterface::class),
        $outcomeHandler,
        $eventLedger,
        new ResumerMockLockManager(),
    );

    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBe([])
        ->and($eventLedger->appended[0]['eventType'])->toBe('execution.state_missing')
        ->and($eventLedger->appended[0]['payload'])->toBe(['correlationId' => 'corr_1']);
});

it('reports a missing definition when the persisted definitionId is not scalar', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    $stateStore = new ResumerMockStateStore();
    $stateStore->state = ['definitionId' => ['not', 'a', 'string']];

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        $stateStore,
        new RecordingDefinitionRegistry(),
        \Mockery::mock(OutputExtractorInterface::class),
        \Mockery::mock(EvaluationEngineInterface::class),
        $outcomeHandler,
        $eventLedger,
        new ResumerMockLockManager(),
    );

    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBe([])
        ->and($eventLedger->appended[0]['eventType'])->toBe('execution.definition_missing')
        ->and($eventLedger->appended[0]['payload'])->toBe(['definitionId' => ['not', 'a', 'string']]);
});

it('reports a missing workflow when the persisted workflowId is not scalar', function (): void {
    [$registry, $definitionId] = resumerDocument();
    $document = $registry->get($definitionId);

    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    $stateStore = new ResumerMockStateStore();
    $stateStore->state = ['definitionId' => $definitionId, 'workflowId' => ['not', 'a', 'string']];

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        $stateStore,
        new RecordingDefinitionRegistry($document),
        \Mockery::mock(OutputExtractorInterface::class),
        \Mockery::mock(EvaluationEngineInterface::class),
        $outcomeHandler,
        $eventLedger,
        new ResumerMockLockManager(),
    );

    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($outcomeHandler->handled)->toBe([])
        ->and($eventLedger->appended[0]['eventType'])->toBe('execution.workflow_missing')
        ->and($eventLedger->appended[0]['payload'])->toBe(['workflowId' => ['not', 'a', 'string']]);
});

it('falls back to a 200 status when the resumption payload carries a non-int statusCode', function (): void {
    [$registry, $definitionId] = resumerDocument();
    $document = $registry->get($definitionId);

    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    $stateStore = new ResumerMockStateStore();
    $stateStore->state = ['definitionId' => $definitionId, 'workflowId' => 'wf_1'];

    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        $stateStore,
        new RecordingDefinitionRegistry($document),
        \Mockery::mock(OutputExtractorInterface::class)->shouldReceive('extractOutputs')->andReturn([])->getMock(),
        \Mockery::mock(EvaluationEngineInterface::class)->shouldReceive('evaluateSuccessCriteria')->andReturn(true)->getMock(),
        $outcomeHandler,
        new ResumerMockEventLedger(),
        new ResumerMockLockManager(),
    );

    $response = ['statusCode' => 'not-an-int', 'body' => ['x' => 1]];
    $resumer->resume('corr_1', $response);

    expect($stateStore->saved['exec_1']['steps']['wait-for-ride']['statusCode'])->toBe(200)
        ->and($stateStore->saved['exec_1']['steps']['wait-for-ride']['response'])->toBe($response)
        ->and($outcomeHandler->handled)->toHaveCount(1);
});
