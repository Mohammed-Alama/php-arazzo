<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Runner\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\CorrelationResumer;
use Alama\Arazzo\Runner\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\PendingCorrelation;
use Alama\Arazzo\Runner\StepOutcomeHandler;
use Alama\Arazzo\Runner\WorkflowContext;
use Psr\Http\Message\RequestInterface;

class ResumerMockLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }
}

class ResumerMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
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
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        throw new \LogicException('not used by resume');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return ['echo' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
    }
}

class RecordingStepOutcomeHandler extends StepOutcomeHandler
{
    /** @var list<array{document: ArazzoDocument, workflow: Workflow, step: Step, context: WorkflowContext, executionId: string, criteriaMet: bool}> */
    public array $calls = [];

    public function __construct()
    {
    }

    public function handle(ArazzoDocument $document, Workflow $workflow, Step $step, WorkflowContext $context, string $executionId, bool $criteriaMet): void
    {
        $this->calls[] = compact('document', 'workflow', 'step', 'context', 'executionId', 'criteriaMet');
    }
}

function resumerDocument(): array
{
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('wait-for-ride', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/rides/created');
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);
    $definitionId = $definitionRegistry->register($document);

    return [$definitionRegistry, $definitionId, $workflow, $step];
}

it('does nothing when the correlation is not found', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $stateStore = new ResumerMockStateStore();
    [$definitionRegistry] = resumerDocument();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, new ResumerMockEventLedger(), new ResumerMockLockManager());

    $resumer->resume('missing', ['body' => ['x' => 1]]);

    expect($outcomeHandler->calls)->toBeEmpty();
    expect($pendingCorrelations->consumed)->toBeEmpty();
});

it('logs and does nothing when persisted state is missing', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');
    $stateStore = new ResumerMockStateStore(); // nothing preloaded
    [$definitionRegistry] = resumerDocument();
    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $eventLedger, new ResumerMockLockManager());

    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($outcomeHandler->calls)->toBeEmpty();
    expect($eventLedger->appended[0]['eventType'])->toBe('execution.state_missing');
});

it('merges the payload, consumes the correlation, saves state, and calls StepOutcomeHandler', function (): void {
    $pendingCorrelations = new ResumerMockPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    [$definitionRegistry, $definitionId, $workflow, $step] = resumerDocument();

    $stateStore = new ResumerMockStateStore();
    $stateStore->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => [],
        'inputs' => [],
        'components' => [],
    ];

    $eventLedger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $eventLedger, new ResumerMockLockManager());

    $resumer->resume('corr_1', ['body' => ['rideId' => 'r_1']]);

    expect($pendingCorrelations->consumed)->toBe(['corr_1']);
    expect($stateStore->saves['exec_1']['steps']['wait-for-ride']['response']['body'])->toBe(['rideId' => 'r_1']);
    expect($eventLedger->appended)->toContainEqual([
        'executionId' => 'exec_1',
        'eventType' => 'step.resumed',
        'payload' => ['stepId' => 'wait-for-ride', 'correlationId' => 'corr_1'],
    ]);

    expect($outcomeHandler->calls)->toHaveCount(1);
    expect($outcomeHandler->calls[0]['executionId'])->toBe('exec_1');
    expect($outcomeHandler->calls[0]['criteriaMet'])->toBeTrue();
    expect($outcomeHandler->calls[0]['step']->stepId)->toBe('wait-for-ride');
});
