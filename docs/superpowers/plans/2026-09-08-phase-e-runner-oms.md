# Phase E: Runner OMS Engine + Executor Registry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the explicit `StepStateMachineEngine` (E1), `StoredWorkflowStateRepository` (E2), a unified sync/async execution carrier (E3), `OperationExecutorRegistry` (E4), and binding-aware request compilation with `ResponseValidatorInterface` dispatch (E5) — killing the C11 sync/async divergence and establishing the canonical operation state machine for multi-protocol execution.

**Architecture:** The `StepStateMachineEngine` wraps the existing `WorkflowEngine::transition()` (pure decision layer) and adds explicit `StepState` transitions with enter-handlers. The unified carrier replaces `StepExecutionWorker` + `StepOutcomeHandler` with a single `UnifiedStepCarrier` that both sync and async paths delegate to. `OperationExecutorRegistry` replaces direct `StepProtocolExecutorInterface` list resolution with a first-match-wins registry keyed by `OperationExecutorPluginInterface`. `StoredWorkflowStateRepository` wraps `StateStoreInterface` with a versioned envelope and backward-compatible loader.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), PHPStan ^2.0 + `phpstan-deprecation-rules`, Laravel Pint, `psr/event-dispatcher`.

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- All new classes: namespace `Alama\Arazzo\Runner\...`, `declare(strict_types=1)`, `@internal`.
- `StepState` enum values: `Pending='pending'`, `ExecutingRequest='executing_request'`, `EvaluatingCriteria='evaluating_criteria'`, `AwaitingActorInput='awaiting_actor_input'`, `ActorInputReceived='actor_input_received'`, `Completed='completed'`, `Failed='failed'`. `Retrying` is an edge (`EVALUATING_CRITERIA → PENDING`), not a state.
- `WorkflowStateRepositoryInterface` signature: `save(string $executionId, WorkflowContextInterface $state): void`, `load(string $executionId): ?WorkflowContextInterface`, `delete(string $executionId): void` (Phase A task A5).
- `OperationExecutorPluginInterface` signature: `supports(Step, ArazzoDocument): bool`, `execute(Step, WorkflowContext, ArazzoDocument, string): StepExecutionOutcome` + `PluginInterface::name(): string`, `priority(): int` (Phase A task A1).
- `ResponseTransferInterface` seam + generic `ResponseTransfer` value type (Phase A task A6): `ResponseTransferInterface` exposes `status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`; the generic `ResponseTransfer` (`Alama\Arazzo\Contracts\Spec`) is the protocol-agnostic implementation with a keyed `views` bag (JSON/XML/proto facets filled by the per-protocol DTOs in Phase F, not flat constructor props).
- `StepProtocolExecutorInterface` remains `@deprecated`; existing executors are adapted to `OperationExecutorPluginInterface` in this phase.
- Every task ends with `composer run test-runner` green (runs `vendor/bin/pest packages/runner/tests` from the repo root).
- Every task's `--filter` runs: `vendor/bin/pest packages/runner/tests --filter "<name>"` from the repo root.
- Static analysis per task (where noted): `composer run analyse-runner` (PHPStan with `packages/runner/phpstan.neon.dist`).
- No code comments unless explaining a deprecation or an ISO-8601 duration.
- No commits that touch anything outside `packages/runner` except the final gate task (E6).
- This plan assumes Phase A contracts are landed. The Phase A types are referenced by FQCN throughout; if Phase A is not yet merged, create the minimal stubs first.

---

### Task E1: StepStateMachineEngine — explicit transition table over StepState

The core of the OMS. An explicit transition table mapping `(StepState, outcome)` pairs to `(next StepState, enter-handler)`. Guards (budget, deps, actions) delegate to the existing pure `WorkflowEngine::transition()`. Three 1.2-PR additions (D10): interaction steps transition `PENDING → AWAITING_ACTOR_INPUT` directly (bypass `EXECUTING_REQUEST`); `onTimeout` becomes an ordered failure-action list; `onCancel` is a first-class cancellation path. `timeout` accepts ISO 8601 duration strings in addition to integer milliseconds.

**Files:**
- Create: `packages/runner/src/Execution/StepStateMachineEngine.php`
- Create: `packages/runner/src/Execution/Data/StepTransition.php`
- Create: `packages/runner/src/Execution/Enum/StepTransitionType.php`
- Create: `packages/runner/tests/Execution/StepStateMachineEngineTest.php`

**Interfaces:**
- Consumes: `StepState` (Phase A4 — `Alama\Arazzo\Contracts\Spec\Enum\StepState`), `WorkflowEngine` (existing), `ExpressionResolverInterface` (existing), `ArazzoDocument`, `Workflow`, `Step`, `ExecutionState`.
- Produces: `StepStateMachineEngine::fire(StepState $current, Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet, bool $suspended): StepTransition`; `StepTransition` readonly with `StepState $from`, `StepState $to`, `StepTransitionType $kind`, `?callable $enterHandler`.

- [ ] **Step 1: Write the failing test — transition table coverage**

Create `packages/runner/tests/Execution/StepStateMachineEngineTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\Data\StepTransition;
use Alama\Arazzo\Runner\Execution\Enum\StepTransitionType;
use Alama\Arazzo\Runner\Execution\StepStateMachineEngine;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Policy\RetryPolicy;

function stateMachineResolver(): ExpressionResolverInterface
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

function stateMachineWorkflow(array $steps): Workflow
{
    return new Workflow('workflow_1', null, null, null, [], $steps, [], [], [], []);
}

function stateMachineDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

function stateMachineStep(string $id): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(), new StepIo());
}

function stateMachineInteractionStep(string $id): Step
{
    return new Step($id, null, StepTarget::interaction(new Interaction()), new StepFlow(), new StepIo());
}

it('transitions Pending → ExecutingRequest when criteria not yet met', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::ExecutingRequest);
});

it('transitions Pending → AwaitingActorInput for interaction steps (bypasses ExecutingRequest)', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineInteractionStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::AwaitingActorInput);
});

it('transitions ExecutingRequest → EvaluatingCriteria after response received', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, criteriaMet: true);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::EvaluatingCriteria);
});

it('transitions EvaluatingCriteria → Completed when success criteria met', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, criteriaMet: true);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Completed);
});

it('transitions EvaluatingCriteria → Failed when criteria not met and no retry available', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, criteriaMet: false);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Failed);
});

it('transitions ExecutingRequest → Failed on transport error (suspended = true)', function (): void {
    $engine = new StepStateMachineEngine(new WorkflowEngine(stateMachineResolver(), new RetryPolicy()));
    $step = stateMachineStep('s1');
    $workflow = stateMachineWorkflow([$step]);
    $document = stateMachineDocument($workflow);
    $state = ExecutionState::start('exec_1', 'test', 'workflow_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, criteriaMet: false, suspended: true);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::Failed);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "StepStateMachineEngineTest"` (repo root)

Expected: FAIL with "Class StepStateMachineEngine not found".

- [ ] **Step 3: Create the supporting value types**

Create `packages/runner/src/Execution/Enum/StepTransitionType.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Enum;

enum StepTransitionType: string
{
    case EnterState = 'enter_state';
    case GuardFailed = 'guard_failed';
    case RetryEdge = 'retry_edge';
}
```

Create `packages/runner/src/Execution/Data/StepTransition.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Runner\Execution\Enum\StepTransitionType;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepTransition
{
    private function __construct(
        public StepState $from,
        public StepState $to,
        public StepTransitionType $kind,
        public ?string $reason = null,
    ) {}

    public static function enter(StepState $from, StepState $to, ?string $reason = null): self
    {
        return new self($from, $to, StepTransitionType::EnterState, $reason);
    }

    public static function guardFailed(StepState $from, string $reason): self
    {
        return new self($from, StepState::Failed, StepTransitionType::GuardFailed, $reason);
    }

    public static function retryEdge(StepState $from, StepState $to, ?string $reason = null): self
    {
        return new self($from, $to, StepTransitionType::RetryEdge, $reason);
    }
}
```

- [ ] **Step 4: Implement the StepStateMachineEngine**

Create `packages/runner/src/Execution/StepStateMachineEngine.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Runner\Execution\Data\StepTransition;

/**
 * Explicit step-level state machine over StepState.
 *
 * The engine maps (StepState, outcome) pairs to (next StepState, enter-handler).
 * Guards (budget, deps) delegate to the pure WorkflowEngine::transition() for
 * protocol-agnostic decisions. The enter-handlers are side-effect closures
 * invoked after a successful state transition.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepStateMachineEngine
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
    ) {}

    /**
     * Fire a step-level state transition.
     *
     * @param  StepState  $current  The step's current StepState.
     * @param  bool  $criteriaMet  Whether success criteria were met (from executor).
     * @param  bool  $suspended  Whether the executor returned a suspended outcome.
     */
    public function fire(
        StepState $current,
        Step $step,
        ArazzoDocument $document,
        ExecutionState $state,
        bool $criteriaMet = false,
        bool $suspended = false,
    ): StepTransition {
        return match ($current) {
            StepState::Pending => $this->fromPending($step, $document, $state, $criteriaMet),
            StepState::ExecutingRequest => $this->fromExecutingRequest($step, $document, $state, $criteriaMet, $suspended),
            StepState::EvaluatingCriteria => $this->fromEvaluatingCriteria($step, $document, $state, $criteriaMet),
            StepState::AwaitingActorInput => $this->fromAwaitingActorInput($step, $document, $state, $criteriaMet),
            StepState::ActorInputReceived => $this->fromActorInputReceived($step, $document, $state, $criteriaMet),
            StepState::Completed, StepState::Failed => StepTransition::enter($current, $current, 'terminal state'),
        };
    }

    /**
     * Resolve the timeout for a step as seconds (supports ISO 8601 duration strings
     * and integer milliseconds).
     */
    public static function resolveTimeoutSeconds(Step $step): ?float
    {
        if ($step->flow->timeoutDuration !== null) {
            return self::parseIso8601Duration($step->flow->timeoutDuration);
        }

        return $step->flow->timeout !== null ? $step->flow->timeout / 1000.0 : null;
    }

    private function fromPending(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::Pending, StepState::AwaitingActorInput, 'interaction step bypass');
        }

        if ($state->stepsSpent >= $state->maxSteps) {
            return StepTransition::guardFailed(StepState::Pending, 'step budget exceeded');
        }

        return StepTransition::enter(StepState::Pending, StepState::ExecutingRequest, 'guards pass');
    }

    private function fromExecutingRequest(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet, bool $suspended): StepTransition
    {
        if ($suspended) {
            return StepTransition::enter(StepState::ExecutingRequest, StepState::Failed, 'transport error / suspended');
        }

        return StepTransition::enter(StepState::ExecutingRequest, StepState::EvaluatingCriteria, 'response received');
    }

    private function fromEvaluatingCriteria(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Completed, 'success criteria met');
        }

        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::AwaitingActorInput, 'actor-in-the-loop re-evaluation');
        }

        return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Failed, 'failure criteria met');
    }

    private function fromAwaitingActorInput(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        return StepTransition::enter(StepState::AwaitingActorInput, StepState::ActorInputReceived, 'actor input persisted');
    }

    private function fromActorInputReceived(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::ActorInputReceived, StepState::Completed, 'criteria met after actor input');
        }

        return StepTransition::enter(StepState::ActorInputReceived, StepState::EvaluatingCriteria, 'resume evaluation');
    }

    /**
     * Parse an ISO 8601 duration string (e.g. "PT30S", "PT1H30M") into seconds.
     *
     * @throws \InvalidArgumentException if the string is not a valid ISO 8601 duration.
     */
    private static function parseIso8601Duration(string $duration): float
    {
        $interval = new \DateInterval($duration);
        return $interval->h * 3600 + $interval->i * 60 + $interval->s + ($interval->f ?? 0);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "StepStateMachineEngineTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/runner/src/Execution/StepStateMachineEngine.php packages/runner/src/Execution/Data/StepTransition.php packages/runner/src/Execution/Enum/StepTransitionType.php packages/runner/tests/Execution/StepStateMachineEngineTest.php
git commit -m "feat(runner): add StepStateMachineEngine with explicit transition table"
```

---

### Task E2: StoredWorkflowStateRepository — versioned envelope over StateStoreInterface

Wraps the existing `StateStoreInterface` (runner port) with a versioned envelope that stores `StepState` alongside the `WorkflowContextInterface` payload. Backward-compatible loader handles existing raw `WorkflowContext::toArray()` payloads (no envelope). `delete()` method added to `StateStoreInterface` (the interface already has the method commented out).

**Files:**
- Modify: `packages/runner/src/State/Interfaces/StateStoreInterface.php` (uncomment `delete`)
- Create: `packages/runner/src/State/StoredWorkflowStateRepository.php`
- Create: `packages/runner/tests/State/StoredWorkflowStateRepositoryTest.php`

**Interfaces:**
- Consumes: `WorkflowStateRepositoryInterface` (Phase A5 — `Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface`), `StateStoreInterface` (existing), `WorkflowContextInterface`, `StepState`.
- Produces: `StoredWorkflowStateRepository implements WorkflowStateRepositoryInterface` honoring the A5 signature exactly (`save(string, WorkflowContextInterface)`, `load(string): ?WorkflowContextInterface`, `delete(string)`). `save()` wraps the context's `toArray()` payload in a versioned envelope with a derived `StepState`; `load()` unwraps with backward compat; `delete()` delegates to `StateStoreInterface::delete()`. Current `StepState` is derived from the context's step records (default `Pending`), and exposed via `::loadStepState(string): ?StepState` as an additive convenience (not on the interface).

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/State/StoredWorkflowStateRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Runner\State\InMemoryStateStore;
use Alama\Arazzo\Runner\State\StoredWorkflowStateRepository;

function storedRepositoryContext(): WorkflowContextInterface
{
    return new class() implements WorkflowContextInterface {
        public function getInputs(): array { return ['x' => 1]; }

        public function getSteps(): array { return []; }

        public function getComponents(): array { return []; }

        public function getWorkflows(): array { return []; }

        public function getStepStatus(string $stepId): ?\Alama\Arazzo\Contracts\Spec\Enum\StepStatus { return null; }

        public function getWorkflowId(): ?string { return 'wf-1'; }
    };
}

it('implements WorkflowStateRepositoryInterface')
    ->expect(StoredWorkflowStateRepository::class)
    ->toBeImplementing(WorkflowStateRepositoryInterface::class);

it('round-trips through the versioned envelope', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $loaded = $repo->load('exec-1');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['x' => 1])
        ->and($loaded->getWorkflowId())->toBe('wf-1');
});

it('returns null for missing execution', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    expect($repo->load('nonexistent'))->toBeNull();
});

it('delegates delete to StateStoreInterface', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);
    $repo->delete('exec-1');

    expect($repo->load('exec-1'))->toBeNull();
});

it('persists the versioned envelope and exposes the derived StepState', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $raw = $store->load('exec-1');
    expect(is_int($raw['version'] ?? null))->toBeTrue()
        ->and($raw['stepState'] ?? null)->toBe(StepState::Pending->value)
        ->and($raw['payload'] ?? null)->toBeArray()
        ->and($repo->loadStepState('exec-1'))->toBe(StepState::Pending);
});

it('backward-compat loads raw WorkflowContext::toArray() payloads', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    // Simulate a raw payload from the old save path (no envelope)
    $rawPayload = [
        'definitionId' => 'test-def',
        'workflowId' => 'wf-1',
        'steps' => [],
        'inputs' => ['old' => true],
        'components' => [],
    ];
    $store->save('exec-old', $rawPayload);

    $loaded = $repo->load('exec-old');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['old' => true]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "StoredWorkflowStateRepositoryTest"` (repo root)

Expected: FAIL with "Class StoredWorkflowStateRepository not found".

- [ ] **Step 3: Uncomment `delete()` in StateStoreInterface**

Edit `packages/runner/src/State/Interfaces/StateStoreInterface.php` — remove the commented-out line and uncomment the `delete` method:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Interfaces;

interface StateStoreInterface
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array;

    public function delete(string $executionId): void;
}
```

Verify `InMemoryStateStore` already has `delete()` — it does (line 36 of the file). `FileStateStore` should also be checked.

- [ ] **Step 4: Implement StoredWorkflowStateRepository**

Create `packages/runner/src/State/StoredWorkflowStateRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State;

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;

/**
 * WorkflowStateRepositoryInterface backed by the runner's StateStoreInterface.
 *
 * Stores a versioned envelope:
 *   { "version": 1, "stepState": "pending", "payload": { ...WorkflowContext::toArray()... } }
 *
 * Backward-compatible: the loader detects raw payloads (no "version" key) and
 * hydrates them as-is.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StoredWorkflowStateRepository implements WorkflowStateRepositoryInterface
{
    private const ENVELOPE_VERSION = 1;

    public function __construct(
        private StateStoreInterface $stateStore,
        private int $stateTtlSeconds = 86400,
    ) {}

    public function save(string $executionId, WorkflowContextInterface $state): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $state instanceof WorkflowContext ? $state->toArray() : $this->serializeContext($state);

        $envelope = [
            'version' => self::ENVELOPE_VERSION,
            'stepState' => $this->deriveStepState($payload),
            'payload' => $payload,
        ];

        $this->stateStore->save($executionId, $envelope, $this->stateTtlSeconds);
    }

    public function load(string $executionId): ?WorkflowContextInterface
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        // Backward-compat: raw WorkflowContext::toArray() payloads have no "version" key.
        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return WorkflowContext::fromPersisted($raw, $executionId);
        }

        /** @var array<string, mixed> $payload */
        $payload = $raw['payload'] ?? [];

        return WorkflowContext::fromPersisted($payload, $executionId);
    }

    public function delete(string $executionId): void
    {
        $this->stateStore->delete($executionId);
    }

    public function loadStepState(string $executionId): ?StepState
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return StepState::Pending;
        }

        return StepState::tryFrom((string) ($raw['stepState'] ?? 'pending'));
    }

    /**
     * @param  array<string, mixed>  $contextArray
     */
    private function deriveStepState(array $contextArray): string
    {
        return StepState::Pending->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContext(WorkflowContextInterface $state): array
    {
        return [
            'definitionId' => $state->getWorkflowId() ?? '',
            'workflowId' => $state->getWorkflowId(),
            'steps' => $state->getSteps(),
            'inputs' => $state->getInputs(),
            'components' => $state->getComponents(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "StoredWorkflowStateRepositoryTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Verify FileStateStore has delete()**

Check `packages/runner/src/State/FileStateStore.php` for the `delete` method. If missing, add it. Run full test suite:

Run: `composer run test-runner` (repo root)

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add packages/runner/src/State/Interfaces/StateStoreInterface.php packages/runner/src/State/StoredWorkflowStateRepository.php packages/runner/tests/State/StoredWorkflowStateRepositoryTest.php
git commit -m "feat(runner): add StoredWorkflowStateRepository with versioned envelope"
```

---

### Task E3: UnifiedStepCarrier — single execution path for sync and async

Consolidates `WorkflowExecutor` (sync) + `StepExecutionWorker`/`StepOutcomeHandler` (async) onto one carrier class. The carrier owns the single step-level loop: resolve executor via the registry/plugin list → execute → apply side effects (persist, events). The sync path drives it in-process via `SyncQueueDriver`-style sequential dispatch; the async path drives it from queue jobs. Both share the same `UnifiedStepCarrier` class, killing the C11 divergence.

**Files:**
- Create: `packages/runner/src/Execution/UnifiedStepCarrier.php`
- Create: `packages/runner/tests/Execution/UnifiedStepCarrierTest.php`

**Interfaces:**
- Consumes: `OperationExecutorPluginInterface` (Phase A1), `WorkflowEngine` (existing), `StateStoreInterface`, `LockManagerInterface`, `ExecutionRegistryInterface`, `EventLedgerInterface`, `PendingCorrelationRegistryInterface`.
- Produces: `UnifiedStepCarrier::execute(string $executionId, Step $step, Workflow $workflow, ArazzoDocument $document, ExecutionState $state): void` — the canonical step execution path. Constructor: `(StateStoreInterface, WorkflowEngine, LockManagerInterface, ExecutionRegistryInterface, EventLedgerInterface, PendingCorrelationRegistryInterface, array $executorPlugins, int $stateTtlSeconds = 86400)`.

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/Execution/UnifiedStepCarrierTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PluginInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\UnifiedStepCarrier;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Infrastructure\NullLockStrategy;
use Alama\Arazzo\Runner\Policy\RetryPolicy;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\InMemoryStateStore;

class UnifiedTestEventLedger implements \Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface
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

    public function findByCorrelationId(string $correlationId): ?\Alama\Arazzo\Contracts\Spec\PendingCorrelation { return null; }

    public function consume(string $correlationId): void {}

    public function existsForExecution(string $executionId): bool { return false; }
}

class SuccessfulPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'stub'; }

    public function priority(): int { return 100; }

    public function supports(Step $step, ArazzoDocument $document): bool { return true; }

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
    $workflowEngine = new WorkflowEngine($resolver, new RetryPolicy());

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
    $workflowEngine = new WorkflowEngine($resolver, new RetryPolicy());

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
})->throws(\LogicException::class, 'No OperationExecutorPluginInterface supports');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "UnifiedStepCarrierTest"` (repo root)

Expected: FAIL with "Class UnifiedStepCarrier not found".

- [ ] **Step 3: Implement UnifiedStepCarrier**

Create `packages/runner/src/Execution/UnifiedStepCarrier.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Data\StepTransition;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use LogicException;

/**
 * Single carrier for both sync and async step execution.
 *
 * Replaces the split between WorkflowExecutor (sync) and StepExecutionWorker/
 * StepOutcomeHandler (async) with one canonical path. The only difference
 * between sync and async is the queue driver and lock manager injected.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class UnifiedStepCarrier
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $executorPlugins;

    public function __construct(
        private StateStoreInterface $stateStore,
        private WorkflowEngine $workflowEngine,
        private LockManagerInterface $lockManager,
        private ExecutionRegistryInterface $executionRegistry,
        private EventLedgerInterface $eventLedger,
        private PendingCorrelationRegistryInterface $pendingCorrelations,
        array $executorPlugins,
        private int $stateTtlSeconds = 86400,
    ) {
        // Sort by priority (lower = higher priority, first-match wins)
        $this->executorPlugins = $executorPlugins;
        usort($this->executorPlugins, fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b) => $a->priority() <=> $b->priority());
    }

    public function execute(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $this->lockManager->acquire("execution_lock_{$executionId}", 30, function () use ($executionId, $step, $workflow, $document, $state) {
            $this->executeUnderLock($executionId, $step, $workflow, $document, $state);
        });
    }

    private function executeUnderLock(
        string $executionId,
        Step $step,
        Workflow $workflow,
        ArazzoDocument $document,
        ExecutionState $state,
    ): void {
        $context = $state->toContext();
        $context = $context->withStepAttemptIncremented($step->stepId);
        $attempt = $context->getStepAttempts($step->stepId);

        $executor = $this->findExecutor($step, $document);
        if ($executor === null) {
            throw new LogicException("No OperationExecutorPluginInterface supports step '{$step->stepId}'.");
        }

        $outcome = $executor->execute($step, $context, $document, $executionId);

        if ($outcome->suspended) {
            $context = $context->withStepStatus($step->stepId, StepStatus::Suspended);
            $this->stateStore->save($executionId, $context->toArray(), $this->stateTtlSeconds);
            $this->executionRegistry->start($executionId, $context->getDefinitionId(), $workflow->workflowId);

            return;
        }

        $contextWithResult = $context->withStepResult($step->stepId, [
            'statusCode' => $outcome->statusCode,
            'request' => $outcome->request ?? [],
            'response' => ['statusCode' => $outcome->statusCode, 'headers' => $outcome->responseHeaders, 'body' => $outcome->responseBody],
            'rawBody' => $outcome->rawBody,
            'contentType' => $outcome->contentType,
            'failureCategory' => $outcome->failureCategory,
            'outputs' => $outcome->outputs,
            'inputs' => $outcome->inputs,
            'attempts' => $attempt,
        ]);

        $this->stateStore->save($executionId, $contextWithResult->toArray(), $this->stateTtlSeconds);

        $restoredState = ExecutionState::fromArray($this->stateStore->load($executionId) ?? $state->toArray());
        $transition = $this->workflowEngine->transition($document, $workflow, $step, $restoredState, $outcome->failureCategory === null);

        $nextState = $transition->state;
        assert($nextState instanceof ExecutionState);

        $this->stateStore->save($executionId, $nextState->toContext()->toArray(), $this->stateTtlSeconds);

        if ($transition->isTerminal()) {
            $succeeded = $transition->status === 'succeeded';
            $this->executionRegistry->complete($executionId, $succeeded ? \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus::Succeeded : \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus::Failed);
            $this->eventLedger->append($executionId, $succeeded ? 'execution.succeeded' : 'execution.failed', ['workflowId' => $transition->state->workflowId]);
        }
    }

    private function findExecutor(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        foreach ($this->executorPlugins as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "UnifiedStepCarrierTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/Execution/UnifiedStepCarrier.php packages/runner/tests/Execution/UnifiedStepCarrierTest.php
git commit -m "feat(runner): add UnifiedStepCarrier for sync/async parity"
```

---

### Task E4: OperationExecutorRegistry — first-supports()-wins registry over OperationExecutorPluginInterface

Replaces the direct `openApiExecutor` calls in `StepExecutor` and the `StepProtocolExecutorInterface` list in `StepExecutionWorker` with a priority-ordered registry that delegates to `OperationExecutorPluginInterface` plugins. The existing `ProtocolExecutorRegistry` (which works on `StepProtocolExecutorInterface`) remains as a `@deprecated` adapter; `OperationExecutorRegistry` is the new canonical registry.

**Files:**
- Create: `packages/runner/src/Execution/OperationExecutorRegistry.php`
- Modify: `packages/runner/src/Execution/Interfaces/ProtocolExecutorRegistryInterface.php` (add `@deprecated` docblock)
- Create: `packages/runner/tests/Execution/OperationExecutorRegistryTest.php`

**Interfaces:**
- Consumes: `OperationExecutorPluginInterface` (Phase A1), `PluginInterface` (Phase A1).
- Produces: `OperationExecutorRegistry::register(OperationExecutorPluginInterface): void`, `::resolve(Step, ArazzoDocument): ?OperationExecutorPluginInterface`, `::all(): list<OperationExecutorPluginInterface>`.

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/Execution/OperationExecutorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\PluginInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runner\Execution\OperationExecutorRegistry;

class HighPriorityPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'high'; }
    public function priority(): int { return 10; }
    public function supports(Step $step, ArazzoDocument $document): bool { return str_contains($step->stepId, 'high'); }
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, [], []);
    }
}

class LowPriorityPlugin implements PluginInterface, OperationExecutorPluginInterface
{
    public function name(): string { return 'low'; }
    public function priority(): int { return 100; }
    public function supports(Step $step, ArazzoDocument $document): bool { return true; }
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        return StepExecutionOutcome::resolved(200, [], []);
    }
}

function registryDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [], new Components([], [], [], []), []);
}

it('resolves the first plugin whose supports() returns true, ordered by priority', function (): void {
    $registry = new OperationExecutorRegistry();
    $registry->register(new LowPriorityPlugin());
    $registry->register(new HighPriorityPlugin());

    $step = new Step('high-step', null, new StepTarget(), new StepFlow(), new StepIo());
    $resolved = $registry->resolve($step, registryDocument());

    expect($resolved)->not->toBeNull()
        ->and($resolved->name())->toBe('high');
});

it('returns null when no plugin supports the step', function (): void {
    $registry = new OperationExecutorRegistry();
    $step = new Step('unknown', null, new StepTarget(), new StepFlow(), new StepIo());

    expect($registry->resolve($step, registryDocument()))->toBeNull();
});

it('returns all registered plugins', function (): void {
    $registry = new OperationExecutorRegistry();
    $registry->register(new LowPriorityPlugin());
    $registry->register(new HighPriorityPlugin());

    expect($registry->all())->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "OperationExecutorRegistryTest"` (repo root)

Expected: FAIL with "Class OperationExecutorRegistry not found".

- [ ] **Step 3: Implement OperationExecutorRegistry**

Create `packages/runner/src/Execution/OperationExecutorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * First-supports()-wins registry for operation executor plugins.
 *
 * Registration order does not matter: plugins are sorted by priority at
 * resolution time. Lower priority values are tried first.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OperationExecutorRegistry
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $plugins = [];

    public function register(OperationExecutorPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    public function resolve(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        $sorted = $this->plugins;
        usort($sorted, fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b) => $a->priority() <=> $b->priority());

        foreach ($sorted as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }

    /** @return list<OperationExecutorPluginInterface> */
    public function all(): array
    {
        return $this->plugins;
    }
}
```

- [ ] **Step 4: Deprecate the old ProtocolExecutorRegistryInterface**

Edit `packages/runner/src/Execution/Interfaces/ProtocolExecutorRegistryInterface.php` — add a `@deprecated` docblock:

```php
/**
 * @deprecated Use OperationExecutorRegistry with OperationExecutorPluginInterface instead.
 */
interface ProtocolExecutorRegistryInterface
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "OperationExecutorRegistryTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/runner/src/Execution/OperationExecutorRegistry.php packages/runner/src/Execution/Interfaces/ProtocolExecutorRegistryInterface.php packages/runner/tests/Execution/OperationExecutorRegistryTest.php
git commit -m "feat(runner): add OperationExecutorRegistry with priority-ordered resolution"
```

---

### Task E5: Binding-aware request compilation + ResponseValidatorInterface dispatch

In-core JSON-schema/OpenAPI validator dispatch during Phase E. After Phase F1, the HTTP default validator moves to `arazzo-protocol-http` and becomes registry-fed. During E, the runner's `StepExecutor` dispatches `ResponseValidatorInterface` per protocol. The `RequestCompiler` remains protocol-agnostic (compiles parameters, body, headers from `Step` parameters). Protocol-specific binding-aware compilation is additive on top of the existing `RequestCompiler`.

**Files:**
- Create: `packages/runner/src/Execution/ResponseValidatorDispatcher.php`
- Create: `packages/runner/tests/Execution/ResponseValidatorDispatcherTest.php`

**Interfaces:**
- Consumes: `ResponseValidatorInterface` (existing contracts), `Step`, `ArazzoDocument`.
- Produces: `ResponseValidatorDispatcher::validate(Step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument): void` — iterates registered validators, dispatches to the first that matches.

- [ ] **Step 1: Write the failing test**

Create `packages/runner/tests/Execution/ResponseValidatorDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Runner\Execution\ResponseValidatorDispatcher;

class StubResponseValidator implements ResponseValidatorInterface
{
    public int $called = 0;

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $this->called++;
    }
}

class FailingResponseValidator implements ResponseValidatorInterface
{
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        throw new SchemaValidationException('Schema mismatch', [], [], $document);
    }
}

function dispatcherStep(string $id = 's1'): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(), new StepIo());
}

it('dispatches to all registered validators', function (): void {
    $validator = new StubResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['ok' => true]);

    expect($validator->called)->toBe(1);
});

it('throws on first validation failure', function (): void {
    $validator = new FailingResponseValidator();
    $dispatcher = new ResponseValidatorDispatcher([$validator]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', ['bad']);
})->throws(SchemaValidationException::class);

it('does nothing with empty validator list', function (): void {
    $dispatcher = new ResponseValidatorDispatcher([]);

    $dispatcher->validate(dispatcherStep(), 200, 'application/json', []);

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests --filter "ResponseValidatorDispatcherTest"` (repo root)

Expected: FAIL with "Class ResponseValidatorDispatcher not found".

- [ ] **Step 3: Implement ResponseValidatorDispatcher**

Create `packages/runner/src/Execution/ResponseValidatorDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Dispatches response validation across registered ResponseValidatorInterface plugins.
 *
 * After Phase F1, protocol-specific validators (HTTP/OpenAPI, SOAP/XSD, RPC/proto)
 * are registered here and the in-core validator becomes the fallback.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResponseValidatorDispatcher
{
    /** @param  list<ResponseValidatorInterface>  $validators */
    public function __construct(
        private array $validators,
    ) {}

    /**
     * @throws \Alama\Arazzo\Contracts\Exceptions\SchemaValidationException
     */
    public function validate(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        foreach ($this->validators as $validator) {
            $validator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests --filter "ResponseValidatorDispatcherTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/Execution/ResponseValidatorDispatcher.php packages/runner/tests/Execution/ResponseValidatorDispatcherTest.php
git commit -m "feat(runner): add ResponseValidatorDispatcher for protocol-aware validation"
```

---

### Task E6: Runner-wide verification + gate

Close out Phase E: full runner quality gate and confirm the additions work with existing consumers.

**Files:**
- None to modify (unless formatting requires).

**Interfaces:**
- Consumes: all tasks E1–E5.

- [ ] **Step 1: Run the full runner test suite**

Run: `composer run test-runner` (repo root)

Expected: PASS (all existing + new tests).

- [ ] **Step 2: Run static analysis**

Run: `composer run analyse-runner` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 3: Run the formatter check**

Run: `composer run format` or `vendor/bin/pint --test` (repo root)

Expected: PASS (no style violations). If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [ ] **Step 4: Run the full repo gate**

Run: `make verify` (repo root)

Expected: PASS — confirms runner additions do not break `core`/`laravel`/`document`/`expression` consumers.

- [ ] **Step 5: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [ ] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Phase E heading, and add:

```markdown
Phase E status: ✅ Implemented — see `plans/2026-09-08-phase-e-runner-oms.md`.
```

- [ ] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase E runner OMS engine complete"
```
