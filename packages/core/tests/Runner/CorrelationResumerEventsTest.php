<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\PendingCorrelation;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\CorrelationResumed;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;

class CorrelationResumerEventsLockManager implements LockManagerInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }
}

class CorrelationResumerEventsPendingCorrelations implements PendingCorrelationRegistryInterface
{
    public ?PendingCorrelation $toReturn = null;

    /** @var list<string> */
    public array $consumed = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
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

class CorrelationResumerEventsStateStore implements StateStoreInterface
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

class CorrelationResumerEventsEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }
}

class CorrelationResumerEventsExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
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

class CorrelationResumerEventsRecordingStepOutcomeHandler extends StepOutcomeHandler
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

function correlationResumerEventsDocument(): array
{
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('wait-for-ride', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/rides/created');
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);
    $definitionId = $definitionRegistry->register($document);

    return [$definitionRegistry, $definitionId, $workflow, $step];
}

it('dispatches CorrelationResumed after successful consume', function () {
    $dispatcher = new SimpleEventDispatcher();
    /** @var list<CorrelationResumed> $dispatched */
    $dispatched = [];
    $dispatcher->subscribe(CorrelationResumed::class, function (CorrelationResumed $event) use (&$dispatched) {
        $dispatched[] = $event;
    });

    $pendingCorrelations = new CorrelationResumerEventsPendingCorrelations();
    $pendingCorrelations->toReturn = new PendingCorrelation('corr_1', 'exec_1', 'wait-for-ride', 'channels/rides/created');

    [$definitionRegistry, $definitionId, $workflow, $step] = correlationResumerEventsDocument();

    $stateStore = new CorrelationResumerEventsStateStore();
    $stateStore->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'steps' => [],
        'inputs' => [],
        'components' => [],
    ];

    $eventLedger = new CorrelationResumerEventsEventLedger();
    $outcomeHandler = new CorrelationResumerEventsRecordingStepOutcomeHandler();

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        $stateStore,
        $definitionRegistry,
        new CorrelationResumerEventsExpressionResolver(),
        $outcomeHandler,
        $eventLedger,
        new CorrelationResumerEventsLockManager(),
        $dispatcher,
    );

    $resumer->resume('corr_1', ['body' => ['rideId' => 'r_1']]);

    expect($dispatched)->toHaveCount(1);
    expect($dispatched[0]->executionId)->toBe('exec_1');
    expect($dispatched[0]->workflowId)->toBe('wf_1');
    expect($dispatched[0]->stepId)->toBe('wait-for-ride');
    expect($dispatched[0]->correlationId)->toBe('corr_1');
    expect($dispatched[0]->at)->toBeInstanceOf(\DateTimeImmutable::class);
});
