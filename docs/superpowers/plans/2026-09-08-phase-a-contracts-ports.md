# Phase A: Contracts Ports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Land the zero-dependency contract SPI faces — `PluginInterface`, `OperationExecutorPluginInterface`, the evaluator/normalizer/replacer plugin interfaces, `StepState`, `WorkflowStateRepositoryInterface`, `ResponseTransferInterface` + generic `ResponseTransfer`, and the **breaking `Step` model decomposition** (`StepTarget`, `StepFlow`, `StepIo`, `StepFactory`) — in `alama/arazzo-contracts`, deprecating `StepProtocolExecutorInterface`.

**Architecture:** All new SPIs live in `packages/contracts` (namespace `Alama\Arazzo\Contracts\Interfaces`), keeping the contracts package PSR-only (root spec line 64: contracts depends only on `psr/event-dispatcher`). The design's "zero-vendor core preserved" invariant (D1) means every new face must use only types already in contracts (`Step`, `ArazzoDocument`, `StepExecutionOutcome`, `WorkflowContext`, `WorkflowContextInterface`, `SuccessCriterion`, `CriterionType`, `Expression`, `ExpressionType`, `SourceDescription`, `SourceType`, `PayloadReplacement`) plus the new `StepState` enum, `ResponseTransferInterface` seam, and the decomposed Step model (`StepTarget`, `StepFlow`, `StepIo`, `StepFactory`). The `Step` restructure (A7) is a **ratified breaking major**: the flat 20-param constructor is dropped; all consumers (parser, runner, test helpers, and 156 test call-sites) migrate to `StepFactory` named constructors in the same change. Domain model additions for Parameter/Components (A7: `valueMode`, `components.interactions`) remain additive.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), Pest Arch (`pestphp/pest-plugin-arch`), PHPStan ^2.0 + `phpstan-deprecation-rules`, Laravel Pint, `psr/event-dispatcher` (only runtime dep).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- New interface files: namespace `Alama\Arazzo\Contracts\Interfaces`, `declare(strict_types=1)`.
- New value types (enum / readonly class): namespace `Alama\Arazzo\Contracts\Spec` or `Alama\Arazzo\Contracts\Spec\Enum`, `declare(strict_types=1)`, `final readonly class` / `enum` (repo convention, see `Step.php`, `StepExecutionOutcome.php`).
- Contracts runtime deps stay PSR-only: `require` = `php: ^8.4` + `psr/event-dispatcher: ^1.0` only. No new composer `require` entries.
- Every new interface must be added to the corresponding test file (repo pattern: `tests/Contracts/SharedContractsTest.php` asserts `interface_exists`).
- Domain model edits: `Parameter`/`Components` additions are **additive only** — append new constructor params with defaults at the END of existing constructors (D11 — no signature changes to existing params). The `Step` model is the **one ratified breaking exception** (spec "Public API impact"): its constructor is decomposed into `StepTarget`/`StepFlow`/`StepIo` + `StepFactory`, and all consumers migrate in this plan (A7). Existing consumers that construct `Parameter`/`Components` via named args must keep working unchanged.
- `StepStatus` (existing, PascalCase cases) is untouched. `StepState` (new) is distinct — they coexist (spec line 233-242 uses uppercase for the MSM sketch; this repo's enum convention is PascalCase — the string values stay spec-exact, the cases are PascalCase to match `StepStatus`).
- Every task ends with `composer run test-contracts` green (runs `vendor/bin/pest packages/contracts/tests` from the repo root — the monorepo composer scripts are all root-run).
- Every task's `--filter` runs: `vendor/bin/pest packages/contracts/tests --filter "<name>"` from the repo root.
- Static analysis per task (where noted): `composer run analyse-contracts` (PHPStan with `packages/contracts/phpstan.neon.dist`).
- No code comments unless explaining a deprecation or an ISO-8601 duration.
- No commits that touch anything outside `packages/contracts` except the final doc checkmark (A8) **and** the Task A7 breaking-Step migration (A7 is the one ratified breaking major: contracts model + factory land together with the parser/runner/test-helper migration so the monorepo stays green; `make verify` is the A7 gate).

---

### Task A1: PluginInterface + OperationExecutorPluginInterface

The root of the plugin dispatcher (spec E1). Every protocol/criteria/expression plugin will extend `PluginInterface`. `OperationExecutorPluginInterface` is the deliberate successor to `StepProtocolExecutorInterface`, keeping the same `supports`/`execute` shape so existing `StepProtocolExecutor` implementations map mechanically.

**Files:**
- Create: `packages/contracts/src/Interfaces/PluginInterface.php`
- Create: `packages/contracts/src/Interfaces/OperationExecutorPluginInterface.php`
- Modify: `packages/contracts/src/Interfaces/StepProtocolExecutorInterface.php` (add `@deprecated` docblock)
- Test: `packages/contracts/tests/Contracts/SharedContractsTest.php`

**Interfaces:**
- Consumes: existing `Step`, `ArazzoDocument`, `StepExecutionOutcome` (all `Alama\Arazzo\Contracts\Spec`), `WorkflowContext` (`Alama\Arazzo\Contracts\State`).
- Produces: `PluginInterface::name(): string`, `PluginInterface::priority(): int`; `OperationExecutorPluginInterface extends PluginInterface` with `supports(Step, ArazzoDocument): bool` and `execute(Step, WorkflowContext, ArazzoDocument, string): StepExecutionOutcome`.

- [x] **Step 1: Write the failing test**

Open `packages/contracts/tests/Contracts/SharedContractsTest.php` and replace its content with:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Contracts\Interfaces\BackoffCalculatorInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;

it('declares the shared contracts consumers rely on')
    ->expect(interface_exists(BackoffCalculatorInterface::class))
    ->toBeTrue()
    ->and(interface_exists(AiClientInterface::class))
    ->toBeTrue()
    ->and(interface_exists(QueueDriverInterface::class))
    ->toBeTrue()
    ->and(interface_exists(StepProtocolExecutorInterface::class))
    ->toBeTrue();

it('declares the plugin base faces')
    ->expect(interface_exists(PluginInterface::class))
    ->toBeTrue()
    ->and(interface_exists(OperationExecutorPluginInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(OperationExecutorPluginInterface::class, PluginInterface::class))
    ->toBeTrue();
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter "plugin base faces"` (repo root)

Expected: FAIL with "interface_exists failed" for `PluginInterface::class`.

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Interfaces/PluginInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

/**
 * A named, priority-ordered extension unit behind a contracts SPI.
 */
interface PluginInterface
{
    public function name(): string;

    public function priority(): int;
}
```

Create `packages/contracts/src/Interfaces/OperationExecutorPluginInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;

/**
 * Executes an Arazzo Step's Operation for one protocol.
 *
 * Replaces StepProtocolExecutorInterface. Existing executors implement
 * this by typecasting the context: StepProtocolExecutor executes against
 * the concrete WorkflowContext; this face keeps the same shape (spec D1).
 */
interface OperationExecutorPluginInterface extends PluginInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
```

Modify `packages/contracts/src/Interfaces/StepProtocolExecutorInterface.php` — add a deprecation docblock directly above `interface StepProtocolExecutorInterface`:

```php
/**
 * @deprecated Use OperationExecutorPluginInterface instead.
 */
interface StepProtocolExecutorInterface
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS (existing 4 assertions + 3 new).

- [x] **Step 5: Verify the deprecation does not trip PHPStan**

The interface itself being `@deprecated` must not be flagged when only *declared* (deprecation rules flag *usage*, not declarations). Run:

`composer run analyse-contracts` (repo root)

Expected: PASS (0 errors).

- [x] **Step 6: Commit**

```bash
git add packages/contracts/src/Interfaces/PluginInterface.php packages/contracts/src/Interfaces/OperationExecutorPluginInterface.php packages/contracts/src/Interfaces/StepProtocolExecutorInterface.php packages/contracts/tests/Contracts/SharedContractsTest.php
git commit -m "feat(contracts): add plugin base and operation executor SPI faces"
```

---

### Task A2: CriterionEvaluatorPluginInterface + ExpressionEvaluatorPluginInterface + ReplacementTargetResolverInterface

The three eval-side plugin faces (spec E2/E3, contract block lines 202-218). They use only contracts types so the parser/evaluator split (Phases B/C) can implement them freely. **Signature note:** the spec's conceptual block passes `ExpressionReference` and `EvaluationInputInterface` to the expression plugin; those types belong to `arazzo-expression`/`arazzo-evaluation` (not contracts, so contracts cannot name them without a circular dependency — the plan argues from the spec but honors the PSR-only constraint). The contracts face therefore uses the contracts value types `Expression` and `mixed` root; Phase C wires `ExpressionReference` under that boundary.

**Files:**
- Create: `packages/contracts/src/Interfaces/CriterionEvaluatorPluginInterface.php`
- Create: `packages/contracts/src/Interfaces/ExpressionEvaluatorPluginInterface.php`
- Create: `packages/contracts/src/Interfaces/ReplacementTargetResolverInterface.php`
- Test: `packages/contracts/tests/Contracts/SharedContractsTest.php`

**Interfaces:**
- Consumes: `PluginInterface` (A1), `SuccessCriterion`, `CriterionType`, `Step`, `Expression`, `WorkflowContextInterface`, `EvaluationInputInterface` (deferred — not a contracts type).
- Produces: `CriterionEvaluatorPluginInterface::supports(CriterionType|SuccessCriterion): bool`, `::evaluate(SuccessCriterion, mixed $context, Step, WorkflowContextInterface): bool`; `ExpressionEvaluatorPluginInterface::supports(Expression): bool`, `::evaluate(Expression, mixed $context): mixed`; `ReplacementTargetResolverInterface::supports(string $targetType): bool`, `::resolve(mixed $container, string $target, mixed $value): mixed`.

- [x] **Step 1: Write the failing test**

Open `packages/contracts/tests/Contracts/SharedContractsTest.php` and append:

```php
use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;

it('declares the evaluator and replacer plugin faces')
    ->expect(interface_exists(CriterionEvaluatorPluginInterface::class))
    ->toBeTrue()
    ->and(interface_exists(ExpressionEvaluatorPluginInterface::class))
    ->toBeTrue()
    ->and(interface_exists(ReplacementTargetResolverInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(CriterionEvaluatorPluginInterface::class, PluginInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(ExpressionEvaluatorPluginInterface::class, PluginInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(ReplacementTargetResolverInterface::class, PluginInterface::class))
    ->toBeTrue();
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter "evaluator and replacer"` (repo root)

Expected: FAIL.

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Interfaces/CriterionEvaluatorPluginInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

interface CriterionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(CriterionType|SuccessCriterion $criterion): bool;

    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool;
}
```

Create `packages/contracts/src/Interfaces/ExpressionEvaluatorPluginInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Expression;

interface ExpressionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(Expression $expression): bool;

    public function evaluate(Expression $expression, mixed $context): mixed;
}
```

Create `packages/contracts/src/Interfaces/ReplacementTargetResolverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface ReplacementTargetResolverInterface extends PluginInterface
{
    /**
     * @param  string  $targetType  json-pointer | xpath | proto-field
     */
    public function supports(string $targetType): bool;

    public function resolve(mixed $container, string $target, mixed $value): mixed;
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/contracts/src/Interfaces/CriterionEvaluatorPluginInterface.php packages/contracts/src/Interfaces/ExpressionEvaluatorPluginInterface.php packages/contracts/src/Interfaces/ReplacementTargetResolverInterface.php packages/contracts/tests/Contracts/SharedContractsTest.php
git commit -m "feat(contracts): add criterion, expression and replacer SPI faces"
```

---

### Task A3: SourceNormalizerInterface + SourceNormalizerRegistry port

The schema-source normalizer port (spec D2). Two faces: the plugin and a registry that resolves a `SourceType` to a registered normalizer. **Signature note:** the spec's public-API impact (line 605) says the interface "returns the existing `ResolvedOperation` (document)". `ResolvedOperation` lives in `alama/arazzo-document`, which sits *above* contracts — contracts cannot reference it without a circular dependency. The plan therefore returns a normalized `array` shape from contracts (D2 reconciles the concrete `ResolvedOperation` two-axis value inside `document`/`arazzo-protocol-http` in Phase D/F1). Source types stay `SourceType` (the contracts enum) rather than the raw `string` the spec block sketches — contracts already owns `SourceType`.

**Files:**
- Create: `packages/contracts/src/Interfaces/SourceNormalizerInterface.php`
- Create: `packages/contracts/src/Interfaces/SourceNormalizerRegistryInterface.php`
- Test: `packages/contracts/tests/Contracts/SharedContractsTest.php`

**Interfaces:**
- Consumes: `PluginInterface` (A1), `SourceDescription`, `SourceType`, `ArazzoDocument`.
- Produces: `SourceNormalizerInterface::supports(SourceType): bool`, `::normalize(SourceDescription, string, ?ArazzoDocument): array`; `SourceNormalizerRegistryInterface::register(SourceNormalizerInterface): void`, `::get(SourceType): ?SourceNormalizerInterface`.

- [x] **Step 1: Write the failing test**

Open `packages/contracts/tests/Contracts/SharedContractsTest.php` and append:

```php
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;

it('declares the source normalizer faces')
    ->expect(interface_exists(SourceNormalizerInterface::class))
    ->toBeTrue()
    ->and(interface_exists(SourceNormalizerRegistryInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(SourceNormalizerInterface::class, PluginInterface::class))
    ->toBeTrue();
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter "source normalizer"` (repo root)

Expected: FAIL.

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Interfaces/SourceNormalizerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;

interface SourceNormalizerInterface extends PluginInterface
{
    public function supports(SourceType $type): bool;

    /**
     * Normalize a raw source document into an operation index.
     *
     * @return array<string, mixed>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array;
}
```

Create `packages/contracts/src/Interfaces/SourceNormalizerRegistryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

interface SourceNormalizerRegistryInterface
{
    public function register(SourceNormalizerInterface $normalizer): void;

    public function get(SourceType $type): ?SourceNormalizerInterface;
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/contracts/src/Interfaces/SourceNormalizerInterface.php packages/contracts/src/Interfaces/SourceNormalizerRegistryInterface.php packages/contracts/tests/Contracts/SharedContractsTest.php
git commit -m "feat(contracts): add source normalizer SPI and registry port"
```

---

### Task A4: StepState enum

The OMS states (spec lines 233-242, 245-273). `StepStatus` (existing) is untouched. `StepState` is distinct — the MSM runs on it; `Retrying` is a transition edge (`EVALUATING_CRITERIA → PENDING`), not a state. **Case convention:** the spec sketch uses UPPER_SNAKE cases and `pending`/… string values; this repo's enums use PascalCase (`StepStatus`). Keep the spec's exact string values and ordering; name the cases PascalCase.

**Files:**
- Create: `packages/contracts/src/Spec/Enum/StepState.php`
- Test: `packages/contracts/tests/Contracts/Spec/Enum/StepStateTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `enum StepState: string` — case order + values exactly per spec transition table: `Pending='pending'`, `ExecutingRequest='executing_request'`, `EvaluatingCriteria='evaluating_criteria'`, `AwaitingActorInput='awaiting_actor_input'`, `ActorInputReceived='actor_input_received'`, `Completed='completed'`, `Failed='failed'`. No `Retrying`.

- [x] **Step 1: Write the failing test**

Create `packages/contracts/tests/Contracts/Spec/Enum/StepStateTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\StepState;

it('models the workflow state machine states')

    ->expect(StepState::cases())
    ->toBe(
        [
            StepState::Pending,
            StepState::ExecutingRequest,
            StepState::EvaluatingCriteria,
            StepState::AwaitingActorInput,
            StepState::ActorInputReceived,
            StepState::Completed,
            StepState::Failed,
        ]
    );

it('backed by spec-exact string values')

    ->expect(StepState::Pending->value)->toBe('pending')
    ->and(StepState::ExecutingRequest->value)->toBe('executing_request')
    ->and(StepState::EvaluatingCriteria->value)->toBe('evaluating_criteria')
    ->and(StepState::AwaitingActorInput->value)->toBe('awaiting_actor_input')
    ->and(StepState::ActorInputReceived->value)->toBe('actor_input_received')
    ->and(StepState::Completed->value)->toBe('completed')
    ->and(StepState::Failed->value)->toBe('failed');

it('maps retrying to an edge, not a state')

    ->expect(defined('Alama\\Arazzo\\Contracts\\Spec\\Enum\\StepState::Retrying'))->toBeFalse();
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter StepStateTest` (repo root)

Expected: FAIL with "Class StepState not found".

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Spec/Enum/StepState.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

/**
 * The step lifecycle states of the operation state machine.
 *
 * Retrying is a transition (EvaluatingCriteria returning to Pending),
 * not a state — see the spec state diagram.
 */
enum StepState: string
{
    case Pending = 'pending';
    case ExecutingRequest = 'executing_request';
    case EvaluatingCriteria = 'evaluating_criteria';
    case AwaitingActorInput = 'awaiting_actor_input';
    case ActorInputReceived = 'actor_input_received';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/contracts/src/Spec/Enum/StepState.php packages/contracts/tests/Contracts/Spec/Enum/StepStateTest.php
git commit -m "feat(contracts): add StepState enum for the operation state machine"
```

---

### Task A5: WorkflowStateRepositoryInterface

Persist/reload an entire workflow run's state for safe pause/resume (spec D2/E2, contract block lines 226-231). It stores/loads a `WorkflowContextInterface` — the composite state of the run. The versioned envelope is an implementation detail (`StoredWorkflowStateRepository` over `StateStoreInterface`, Phase E2); the port itself only transports the context object.

**Files:**
- Create: `packages/contracts/src/Interfaces/WorkflowStateRepositoryInterface.php`
- Test: `packages/contracts/tests/Contracts/Interfaces/WorkflowStateRepositoryInterfaceTest.php`

**Interfaces:**
- Consumes: `WorkflowContextInterface` (`Alama\Arazzo\Contracts\Spec\Interfaces`).
- Produces: `WorkflowStateRepositoryInterface::save(string $executionId, WorkflowContextInterface $state): void`, `::load(string $executionId): ?WorkflowContextInterface`, `::delete(string $executionId): void`.

- [x] **Step 1: Write the failing test**

Create `packages/contracts/tests/Contracts/Interfaces/WorkflowStateRepositoryInterfaceTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;

it('declares the durable state repository port')
    ->expect(interface_exists(WorkflowStateRepositoryInterface::class))
    ->toBeTrue();

it('round-trips a context through a stub repository', function (): void {
    $context = new class implements \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface {
        public function getInputs(): array { return []; }

        public function getSteps(): array { return []; }

        public function getComponents(): array { return []; }

        public function getWorkflows(): array { return []; }

        public function getStepStatus(string $stepId): ?\Alama\Arazzo\Contracts\Spec\Enum\StepStatus { return null; }

        public function getWorkflowId(): ?string { return 'wf-1'; }
    };

    $repo = new class implements WorkflowStateRepositoryInterface {
        public function save(string $executionId, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $state): void {}

        public function load(string $executionId): ?\Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface
        {
            return null;
        }

        public function delete(string $executionId): void {}
    };

    $repo->save('exec-1', $context);

    expect($repo->load('exec-1'))->toBeNull();
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter WorkflowStateRepositoryInterfaceTest` (repo root)

Expected: FAIL with "Interface WorkflowStateRepositoryInterface not found".

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Interfaces/WorkflowStateRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;

/**
 * Stores and loads the serializable state of a workflow run, keyed by
 * execution id, for safe pause/resume (spec D2, E2).
 */
interface WorkflowStateRepositoryInterface
{
    public function save(string $executionId, WorkflowContextInterface $state): void;

    public function load(string $executionId): ?WorkflowContextInterface;

    public function delete(string $executionId): void;
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/contracts/src/Interfaces/WorkflowStateRepositoryInterface.php packages/contracts/tests/Contracts/Interfaces/WorkflowStateRepositoryInterfaceTest.php
git commit -m "feat(contracts): add workflow state repository port"
```

---

### Task A6: ResponseTransferInterface + generic ResponseTransfer

The protocol-agnostic response seam (spec "ResponseTransfer", D6): contracts owns only the interface plus one generic implementation with a keyed `views` bag. **No** `json`/`xml`/`proto`/`graphQlErrors` props — concrete facets live in per-protocol typed DTOs (Soap `F2`, Rpc `F2`, Http `F1`) implementing the same seam. `StepExecutionOutcome` (existing) is untouched — the seam is the carrier the executor plugin hot path will build on in Phase B2/E5.

**Files:**
- Create: `packages/contracts/src/Interfaces/ResponseTransferInterface.php`
- Create: `packages/contracts/src/Spec/ResponseTransfer.php`
- Test: `packages/contracts/tests/Contracts/Spec/ResponseTransferTest.php`
- Modify: `packages/contracts/tests/Contracts/SharedContractsTest.php` (assert `interface_exists`)

**Interfaces:**
- Consumes: nothing new (uses `mixed`, `array`, `int`, `string`).
- Produces: `ResponseTransferInterface` (`status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`); `ResponseTransfer` `final readonly class implements ResponseTransferInterface` with constructor params `mixed $status`, `array $headers`, `mixed $rawBody`, `array $views = []`, `array $meta = []`.

- [x] **Step 1: Write the failing test**

Create `packages/contracts/tests/Contracts/Spec/ResponseTransferTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;
use Alama\Arazzo\Contracts\Spec\ResponseTransfer;

it('carries status, headers and the raw protocol body', function (): void {
    $transfer = new ResponseTransfer(status: 200, headers: ['Content-Type' => 'text/xml'], rawBody: '<x/>');

    expect($transfer->status())->toBe(200)
        ->and($transfer->headers())->toBe(['Content-Type' => 'text/xml'])
        ->and($transfer->rawBody())->toBe('<x/>');
});

it('exposes views and the meta bag; absent views return null', function (): void {
    $transfer = new ResponseTransfer(status: 0, headers: [], rawBody: null, views: ['json' => ['ok' => true]]);

    expect($transfer->hasView('json'))->toBeTrue()
        ->and($transfer->view('json'))->toBe(['ok' => true])
        ->and($transfer->hasView('xml'))->toBeFalse()
        ->and($transfer->view('xml'))->toBeNull();
});

it('implements the contract seam', function (): void {
    $transfer = new ResponseTransfer(status: 0, headers: [], rawBody: null);

    expect($transfer)->toBeInstanceOf(ResponseTransferInterface::class)
        ->and($transfer->meta())->toBe([]);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter ResponseTransferTest` (repo root)

Expected: FAIL with "Class ResponseTransferInterface not found".

- [x] **Step 3: Write minimal implementation**

Create `packages/contracts/src/Interfaces/ResponseTransferInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

/**
 * Protocol-agnostic response seam (spec D6). Implemented by the generic
 * ResponseTransfer (contracts, A6) and by per-protocol typed DTOs (Phase F).
 * Expression/criteria resolution consumes only this seam (Phase B).
 */
interface ResponseTransferInterface
{
    /** mapped per protocol (HTTP status / RPC status object / SOAP fault status) */
    public function status(): mixed;

    /** HTTP headers / SOAP headers / RPC metadata */
    public function headers(): array;

    /** the transport body, undecoded */
    public function rawBody(): mixed;

    /** whether a named decoded facet is present (json/xml/proto/protocol-specific) */
    public function hasView(string $name): bool;

    /** the named decoded facet, or null when absent */
    public function view(string $name): mixed;

    /** protocol-specific keys (escape hatch) */
    public function meta(): array;
}
```

Create `packages/contracts/src/Spec/ResponseTransfer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;

/**
 * Generic response carrier (spec D6). Default implementation of the seam;
 * per-protocol packages ship their own typed DTOs implementing the same
 * interface with concrete facets exposed as typed accessors (Phase F).
 *
 * `meta` is the generic escape hatch for execution-environment extras the
 * Arazzo spec does not define (soap.faultcode, grpc-trailer, ...).
 */
final readonly class ResponseTransfer implements ResponseTransferInterface
{
    /**
     * @param  mixed  $status
     * @param  array<string,string>  $headers
     * @param  mixed  $rawBody
     * @param  array<string,mixed>  $views  decoded facets keyed by name
     * @param  array<string,mixed>  $meta  protocol-specific keys
     */
    public function __construct(
        private mixed $status,
        private array $headers,
        private mixed $rawBody,
        private array $views = [],
        private array $meta = [],
    ) {}

    public function status(): mixed
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): mixed
    {
        return $this->rawBody;
    }

    public function hasView(string $name): bool
    {
        return array_key_exists($name, $this->views);
    }

    public function view(string $name): mixed
    {
        return $this->views[$name] ?? null;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `composer run test-contracts` (repo root)

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add packages/contracts/src/Interfaces/ResponseTransferInterface.php packages/contracts/src/Spec/ResponseTransfer.php packages/contracts/tests/Contracts/Spec/ResponseTransferTest.php packages/contracts/tests/Contracts/SharedContractsTest.php
git commit -m "feat(contracts): add ResponseTransferInterface seam and generic transfer"
```

---

### Task A7: Step model decomposition (breaking major) + additive domain additions

Arazzo's 1.2 proposal fields (spec A7 / Public API impact lines 602-604) multiplex the protocol axis (`operationName` WSDL, `rpcMethod` + `rpcProtocol` grpc/grpc-web/twirp/connect, `graphqlOperation`, `interaction`), add control fields (`onTimeout`, `onCancel`, ISO-8601 `timeoutDuration`), and extend Parameter/Components (`in: metadata|variable`, `valueMode` literal/selector, `components.interactions`). The flagged 20-param `Step` cannot absorb a third axis without becoming unreadable, so this ticket performs the **one ratified breaking major** of the contract: `Step` is decomposed into three axes — `StepTarget` (operation/protocol), `StepFlow` (control/scheduling), `StepIo` (data) — plus a `StepFactory` of per-variant named constructors. The contracts model, the factory, and ALL consumers in the monorepo (document parser, runner executors, cli renderer, expression evaluators, the `Fx` test helper, the 156 test call-sites, and the `$step->` reads in every src package) migrate in this same ticket so the monorepo stays green at each gate (spec "Public API impact": the migration is one change; `composer run test` / `make verify` is the A7 gate). Parameter/Components additions stay **BC-additive** per D11 — appended as trailing constructor defaults.

**Files:**
- Create: `packages/contracts/src/Spec/Enum/RpcProtocol.php`
- Create: `packages/contracts/src/Spec/Enum/ValueMode.php`
- Create: `packages/contracts/src/Spec/Interaction.php`
- Create: `packages/contracts/src/Spec/StepTarget.php`
- Create: `packages/contracts/src/Spec/StepFlow.php`
- Create: `packages/contracts/src/Spec/StepIo.php`
- Create: `packages/contracts/src/Spec/StepFactory.php`
- Rewrite: `packages/contracts/src/Spec/Step.php` (5-param aggregate)
- Modify: `packages/contracts/src/Spec/Enum/ParameterIn.php` (add `Metadata`, `Variable` cases)
- Modify: `packages/contracts/src/Spec/Parameter.php` (append `$valueMode`)
- Modify: `packages/contracts/src/Spec/Components.php` (append `$interactions`)
- Modify: `packages/contracts/src/Dependency/DependencyGraph.php` (read `$step->flow->dependsOn`)
- Modify: `packages/contracts/src/Dependency/ImplicitDependencies.php` (io/target reads)
- Modify: `packages/document/src/Parser/Parser.php` (variant detection + StepFactory)
- Modify: `packages/document/src/Normalizer/OpenApiOperationResolver.php`
- Modify: `packages/document/src/Validator/PreflightValidator.php`
- Modify: `packages/document/src/Validator/Rules/SelectorTypeSupportedRule.php`
- Modify: `packages/document/src/Validator/Rules/ExpressionUnresolvedStepRefRule.php`
- Modify: `packages/document/src/Validator/Rules/SubWorkflowInvokeTargetResolvesRule.php`
- Modify: `packages/document/src/Validator/Rules/ParameterQuerystringOperationShapeRule.php`
- Modify: `packages/runner/src/Execution/StepParameterMerger.php`
- Modify: `packages/runner/src/Execution/RequestCompiler.php`
- Modify: `packages/runner/src/Execution/StepOutcomeHandler.php`
- Modify: `packages/runner/src/Execution/StepOutputExtractor.php`
- Modify: `packages/runner/src/Execution/StepExecutionWorker.php`
- Modify: `packages/runner/src/Execution/StepExecutor.php`
- Modify: `packages/runner/src/Execution/WorkflowEngine.php`
- Modify: `packages/runner/src/Execution/IdempotencyKeyInjector.php`
- Modify: `packages/runner/src/Protocol/HttpStepExecutor.php`
- Modify: `packages/runner/src/Protocol/AsyncApiStepExecutor.php`
- Modify: `packages/runner/src/Protocol/SubWorkflowExecutor.php`
- Modify: `packages/runner/src/Protocol/SubWorkflowStepExecutor.php`
- Modify: `packages/runner/src/Async/SuspensionHandler.php`
- Modify: `packages/expression/src/Evaluation/CriteriaEvaluator.php`
- Modify: `packages/expression/src/Evaluation/PayloadReplacer.php`
- Modify: `packages/cli/src/Console/Command/ListWorkflowsCommand.php`
- Modify: `packages/cli/src/Renderer/Renderer.php`
- Modify: `packages/core/tests/Support/Fx.php`
- Test: `packages/contracts/tests/Contracts/Spec/Enum/RpcProtocolTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/Enum/ValueModeTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/InteractionTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/StepTargetTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/StepFlowTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/StepIoTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/ParameterTest.php`
- Test: `packages/contracts/tests/Contracts/Spec/ComponentsTest.php`
- Modify: repo-wide `packages/*/{src,tests}` remaining `new Step(` call-sites and `$step->` flat-field reads

**Interfaces:**
- Consumes: existing `Step`, `Parameter`, `Components`, `Expression`, `ExpressionType`, `Selector`, `SuccessCriterion`, `CriterionType`, `SuccessAction`, `FailureAction`, `RequestBody`, `Reusable`, `ParameterIn`.
- Produces (additive): `RpcProtocol` enum (`Grpc='grpc'`, `GrpcWeb='grpc-web'`, `Twirp='twirp'`, `Connect='connect'`); `ValueMode` enum (`Literal='literal'`, `Selector='selector'`); two new `ParameterIn` cases (`Metadata='metadata'`, `Variable='variable'`); `Interaction` readonly DTO (`mixed $expectedPayload = null`, `?string $timeout = null`); `Parameter` gains `?ValueMode $valueMode = null`; `Components` gains `array $interactions = []`.
- Produces (breaking): `StepTarget` readonly value object (Step 15): all-null constructor plus static variant factories `http(?string $operationId, ?string $operationPath)` (throws `InvalidArgumentException` unless exactly one is set), `workflow(string $workflowId)`, `async(string $action, string $channelPath, ?Expression $correlationId = null)`, `wsdl(string $operationName)`, `rpc(string $rpcMethod, RpcProtocol $rpcProtocol)`, `graphql(string $graphqlOperation)`, `interaction(Interaction $interaction)`. `StepFlow` (Step 19) and `StepIo` (Step 22): all-default constructors. `StepFactory` (Step 25): `http(...)`/`workflow(...)`/`async(...)`/`rpc(...)`/`graphql(...)`/`interaction(...)`/`wsdl(...)` static constructors each returning the aggregate. `Step` becomes the 5-param aggregate `Step(string $stepId, ?string $description, StepTarget $target, StepFlow $flow, StepIo $io)`.
- Read-mapping for all migrated consumers (`$step->` in src and tests): `operationId`/`operationPath`/`workflowId`/`action`/`channelPath`/`correlationId`/`operationName`/`rpcMethod`/`rpcProtocol`/`graphqlOperation`/`interaction` → `$step->target->...`; `parameters`/`requestBody`/`successCriteria`/`outputs` → `$step->io->...`; `dependsOn`/`timeout`/`timeoutDuration`/`onSuccess`/`onFailure`/`onTimeout`/`onCancel`/`strictValidation`/`idempotencyKey`/`idempotencyHeader` → `$step->flow->...`; `stepId`/`description` → unchanged at `$step->...`.

### Part 1: Enumerations + Interaction (additive, Steps 1-12)

- [x] **Step 1: Write the failing test (RpcProtocol enum)**

Create `packages/contracts/tests/Contracts/Spec/Enum/RpcProtocolTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;

it('lists the RPC wire protocols')
    ->expect(RpcProtocol::cases())
    ->toBe(
        [
            RpcProtocol::Grpc,
            RpcProtocol::GrpcWeb,
            RpcProtocol::Twirp,
            RpcProtocol::Connect,
        ]
    );

it('uses spec-exact string values')
    ->expect(RpcProtocol::Grpc->value)->toBe('grpc')
    ->and(RpcProtocol::GrpcWeb->value)->toBe('grpc-web')
    ->and(RpcProtocol::Twirp->value)->toBe('twirp')
    ->and(RpcProtocol::Connect->value)->toBe('connect');
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter RpcProtocolTest` (repo root)

Expected: FAIL with "Class RpcProtocol not found".

- [x] **Step 3: Write minimal implementation (RpcProtocol)**

Create `packages/contracts/src/Spec/Enum/RpcProtocol.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum RpcProtocol: string
{
    case Grpc = 'grpc';
    case GrpcWeb = 'grpc-web';
    case Twirp = 'twirp';
    case Connect = 'connect';
}
```

- [x] **Step 4: Run test to verify RpcProtocol passes**

Run: `vendor/bin/pest packages/contracts/tests --filter RpcProtocolTest` (repo root)

Expected: PASS.

- [x] **Step 5: Write the failing test (ValueMode + ParameterIn cases)**

Create `packages/contracts/tests/Contracts/Spec/Enum/ValueModeTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;

it('lists the parameter value modes')
    ->expect(ValueMode::cases())
    ->toBe([ValueMode::Literal, ValueMode::Selector]);

it('uses spec-exact string values')
    ->expect(ValueMode::Literal->value)->toBe('literal')
    ->and(ValueMode::Selector->value)->toBe('selector');
```

- [x] **Step 6: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter ValueModeTest` (repo root)

Expected: FAIL with "Class ValueMode not found".

- [x] **Step 7: Write minimal implementation (ValueMode + ParameterIn)**

Create `packages/contracts/src/Spec/Enum/ValueMode.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum ValueMode: string
{
    case Literal = 'literal';
    case Selector = 'selector';
}
```

Edit `packages/contracts/src/Spec/Enum/ParameterIn.php` — append two cases before the closing brace:

```php
    case Metadata = 'metadata';
    case Variable = 'variable';
```

- [x] **Step 8: Run test to verify enums pass**

Run: `vendor/bin/pest packages/contracts/tests --filter "RpcProtocol|ValueMode"` (repo root)

Expected: PASS.

- [x] **Step 9: Write the failing Interaction test**

Create `packages/contracts/tests/Contracts/Spec/InteractionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Interaction;

it('models an actor interaction with expected payload and timeout', function (): void {
    $interaction = new Interaction(expectedPayload: 'anything', timeout: 'PT30S');

    expect($interaction->expectedPayload)->toBe('anything')
        ->and($interaction->timeout)->toBe('PT30S');
});

it('defaults to null payload and timeout', function (): void {
    $interaction = new Interaction();

    expect($interaction->expectedPayload)->toBeNull()
        ->and($interaction->timeout)->toBeNull();
});
```

- [x] **Step 10: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter InteractionTest` (repo root)

Expected: FAIL with "Class Interaction not found".

- [x] **Step 11: Write minimal implementation (Interaction)**

Create `packages/contracts/src/Spec/Interaction.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * Actor-in-the-loop Step target (1.2 proposal, D10).
 */
final readonly class Interaction
{
    public function __construct(
        public mixed $expectedPayload = null,
        public ?string $timeout = null,
    ) {}
}
```

- [x] **Step 12: Run test to verify Interaction passes**

Run: `vendor/bin/pest packages/contracts/tests --filter InteractionTest` (repo root)

Expected: PASS.

### Part 2: Step model decomposition (breaking, Steps 13-27)

The flat 20-param `Step` is replaced by `StepTarget` (operation/protocol axis), `StepFlow` (control axis), and `StepIo` (data axis), gathered by the 5-param `Step` aggregate and constructed through `StepFactory` named constructors. All four value files are `final readonly` and stay in the `Alama\Arazzo\Contracts\Spec` namespace, satisfying `packages/contracts/tests/ArchTest.php` (every `Spec` class `toBeReadonly()` + strict types). The public constructor of each axis defaults everything so degenerate steps (dependency-only fake steps in tests, target-less steps the resolver must reject later) remain constructible.

- [x] **Step 13: Write the failing StepTarget test**

Create `packages/contracts/tests/Contracts/Spec/StepTargetTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use InvalidArgumentException;

it('builds an http target from an operationId', function (): void {
    $target = StepTarget::http(operationId: 'getToken');

    expect($target->operationId)->toBe('getToken')
        ->and($target->operationPath)->toBeNull();
});

it('builds an http target from an operationPath', function (): void {
    $target = StepTarget::http(operationPath: '/pets');

    expect($target->operationPath)->toBe('/pets')
        ->and($target->operationId)->toBeNull();
});

it('throws when the http target declares no operation', function (): void {
    StepTarget::http();
})->throws(InvalidArgumentException::class);

it('throws when the http target declares both operations', function (): void {
    StepTarget::http(operationId: 'getToken', operationPath: '/pets');
})->throws(InvalidArgumentException::class);

it('builds a workflow target', function (): void {
    $target = StepTarget::workflow('checkout');

    expect($target->workflowId)->toBe('checkout');
});

it('builds an async target with action, channel and correlation', function (): void {
    $correlation = new Expression('{$request.body#/correlationId}');
    $target = StepTarget::async('receive', 'channels/rides/created', $correlation);

    expect($target->action)->toBe('receive')
        ->and($target->channelPath)->toBe('channels/rides/created')
        ->and($target->correlationId)->toBe($correlation);
});

it('builds a wsdl target', function (): void {
    $target = StepTarget::wsdl('GetToken');

    expect($target->operationName)->toBe('GetToken');
});

it('builds an rpc target with its wire protocol', function (): void {
    $target = StepTarget::rpc('GetToken', RpcProtocol::Grpc);

    expect($target->rpcMethod)->toBe('GetToken')
        ->and($target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('builds a graphql target', function (): void {
    $target = StepTarget::graphql('query GetToken');

    expect($target->graphqlOperation)->toBe('query GetToken');
});

it('builds an interaction target', function (): void {
    $interaction = new Interaction(expectedPayload: 'approve');
    $target = StepTarget::interaction($interaction);

    expect($target->interaction)->toBe($interaction);
});
```

- [x] **Step 14: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter StepTargetTest` (repo root)

Expected: FAIL with "Class StepTarget not found".

- [x] **Step 15: Write minimal implementation (StepTarget)**

Create `packages/contracts/src/Spec/StepTarget.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use InvalidArgumentException;

/**
 * The operation/protocol axis of a Step (D4). Exactly one variant is set
 * through the named static factories; the http factory enforces the 1.1
 * operationId/operationPath exclusivity at construction time.
 */
final readonly class StepTarget
{
    public function __construct(
        public ?string $operationId = null,
        public ?string $operationPath = null,
        public ?string $workflowId = null,
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
        public ?string $operationName = null,
        public ?string $rpcMethod = null,
        public ?RpcProtocol $rpcProtocol = null,
        public ?string $graphqlOperation = null,
        public ?Interaction $interaction = null,
    ) {}

    public static function http(?string $operationId = null, ?string $operationPath = null): self
    {
        if (($operationId === null) === ($operationPath === null)) {
            throw new InvalidArgumentException(
                'http target requires exactly one of operationId or operationPath',
            );
        }

        return new self(operationId: $operationId, operationPath: $operationPath);
    }

    public static function workflow(string $workflowId): self
    {
        return new self(workflowId: $workflowId);
    }

    public static function async(string $action, string $channelPath, ?Expression $correlationId = null): self
    {
        return new self(action: $action, channelPath: $channelPath, correlationId: $correlationId);
    }

    public static function wsdl(string $operationName): self
    {
        return new self(operationName: $operationName);
    }

    public static function rpc(string $rpcMethod, RpcProtocol $rpcProtocol): self
    {
        return new self(rpcMethod: $rpcMethod, rpcProtocol: $rpcProtocol);
    }

    public static function graphql(string $graphqlOperation): self
    {
        return new self(graphqlOperation: $graphqlOperation);
    }

    public static function interaction(Interaction $interaction): self
    {
        return new self(interaction: $interaction);
    }
}
```

- [x] **Step 16: Run test to verify StepTarget passes**

Run: `vendor/bin/pest packages/contracts/tests --filter StepTargetTest` (repo root)

Expected: PASS.

- [x] **Step 17: Write the failing StepFlow test**

Create `packages/contracts/tests/Contracts/Spec/StepFlowTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\StepFlow;

it('defaults every control field', function (): void {
    $flow = new StepFlow();

    expect($flow->dependsOn)->toBe([])
        ->and($flow->timeout)->toBeNull()
        ->and($flow->timeoutDuration)->toBeNull()
        ->and($flow->onSuccess)->toBe([])
        ->and($flow->onFailure)->toBe([])
        ->and($flow->onTimeout)->toBe([])
        ->and($flow->onCancel)->toBe([])
        ->and($flow->strictValidation)->toBeNull()
        ->and($flow->idempotencyKey)->toBeNull()
        ->and($flow->idempotencyHeader)->toBeNull();
});

it('carries dependency, timing and idempotency fields', function (): void {
    $flow = new StepFlow(
        dependsOn: ['a'],
        timeout: 30000,
        timeoutDuration: 'PT30S',
        strictValidation: true,
        idempotencyKey: true,
        idempotencyHeader: 'Idempotency-Key',
    );

    expect($flow->dependsOn)->toBe(['a'])
        ->and($flow->timeout)->toBe(30000)
        ->and($flow->timeoutDuration)->toBe('PT30S')
        ->and($flow->strictValidation)->toBeTrue()
        ->and($flow->idempotencyKey)->toBeTrue()
        ->and($flow->idempotencyHeader)->toBe('Idempotency-Key');
});
```

- [x] **Step 18: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter StepFlowTest` (repo root)

Expected: FAIL with "Class StepFlow not found".

- [x] **Step 19: Write minimal implementation (StepFlow)**

Create `packages/contracts/src/Spec/StepFlow.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;

/**
 * The control/scheduling axis of a Step (D4).
 */
final readonly class StepFlow
{
    /**
     * @param  list<string>  $dependsOn
     * @param  list<SuccessAction|Reusable>  $onSuccess
     * @param  list<FailureAction|Reusable>  $onFailure
     * @param  list<SuccessAction|Reusable>  $onTimeout
     * @param  list<FailureAction|Reusable>  $onCancel
     */
    public function __construct(
        public array $dependsOn = [],
        public ?int $timeout = null, // duration in milliseconds
        public ?string $timeoutDuration = null, // ISO-8601 duration (1.2 proposal)
        public array $onSuccess = [],
        public array $onFailure = [],
        public array $onTimeout = [],
        public array $onCancel = [],
        public ?bool $strictValidation = null,
        public ?bool $idempotencyKey = null,
        public ?string $idempotencyHeader = null,
    ) {}
}
```

Run: `vendor/bin/pest packages/contracts/tests --filter StepFlowTest` (repo root)

Expected: PASS.

- [x] **Step 20: Write the failing StepIo test**

Create `packages/contracts/tests/Contracts/Spec/StepIoTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

it('defaults every data field', function (): void {
    $io = new StepIo();

    expect($io->parameters)->toBe([])
        ->and($io->requestBody)->toBeNull()
        ->and($io->successCriteria)->toBe([])
        ->and($io->outputs)->toBe([]);
});

it('carries parameters, body, criteria and outputs', function (): void {
    $body = new RequestBody('application/json', ['ok' => true], []);
    $io = new StepIo(
        parameters: [new Parameter('limit', ParameterIn::Query, 25)],
        requestBody: $body,
        successCriteria: [new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple)],
        outputs: ['id' => new Expression('{$response.body#/id}')],
    );

    expect($io->parameters[0]->name)->toBe('limit')
        ->and($io->requestBody)->toBe($body)
        ->and($io->successCriteria[0]->condition)->toBe('$statusCode == 200')
        ->and($io->outputs['id'])->toBeInstanceOf(Expression::class);
});
```

- [x] **Step 21: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter StepIoTest` (repo root)

Expected: FAIL with "Class StepIo not found".

- [x] **Step 22: Write minimal implementation (StepIo)**

Create `packages/contracts/src/Spec/StepIo.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * The data axis of a Step (D4).
 */
final readonly class StepIo
{
    /**
     * @param  list<Parameter|Reusable>  $parameters
     * @param  list<SuccessCriterion>  $successCriteria
     * @param  array<string,Expression|Selector|scalar|array<mixed>|null>  $outputs
     */
    public function __construct(
        public array $parameters = [],
        public ?RequestBody $requestBody = null,
        public array $successCriteria = [],
        public array $outputs = [],
    ) {}
}
```

Run: `vendor/bin/pest packages/contracts/tests --filter StepIoTest` (repo root)

Expected: PASS.

- [x] **Step 23: Write the failing StepFactory test**

Create `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;

it('builds an http step', function (): void {
    $step = StepFactory::http('get-token', null, new StepFlow(), new StepIo(), operationId: 'GetToken');

    expect($step->stepId)->toBe('get-token')
        ->and($step->target->operationId)->toBe('GetToken');
});

it('builds a workflow step', function (): void {
    $step = StepFactory::workflow('invoke', null, new StepFlow(), new StepIo(), 'checkout');

    expect($step->target->workflowId)->toBe('checkout');
});

it('builds an async step with its correlation id', function (): void {
    $correlation = new Expression('{$request.body#/correlationId}');
    $step = StepFactory::async('receive', null, new StepFlow(), new StepIo(), 'receive', 'channels/rides/created', $correlation);

    expect($step->target->action)->toBe('receive')
        ->and($step->target->channelPath)->toBe('channels/rides/created')
        ->and($step->target->correlationId)->toBe($correlation);
});

it('builds an rpc step', function (): void {
    $step = StepFactory::rpc('charge', null, new StepFlow(), new StepIo(), 'Charge', RpcProtocol::Grpc);

    expect($step->target->rpcMethod)->toBe('Charge')
        ->and($step->target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('builds a graphql step', function (): void {
    $step = StepFactory::graphql('load', null, new StepFlow(), new StepIo(), 'query Load');

    expect($step->target->graphqlOperation)->toBe('query Load');
});

it('builds an interaction step', function (): void {
    $interaction = new Interaction(expectedPayload: 'approve');
    $step = StepFactory::interaction('confirm', null, new StepFlow(), new StepIo(), $interaction);

    expect($step->target->interaction)->toBe($interaction);
});

it('builds a wsdl step', function (): void {
    $step = StepFactory::wsdl('get-token', null, new StepFlow(), new StepIo(), 'GetToken');

    expect($step->target->operationName)->toBe('GetToken');
});

it('attaches the given flow and io to the aggregate', function (): void {
    $flow = new StepFlow(dependsOn: ['a'], timeout: 30000, strictValidation: true);
    $io = new StepIo(parameters: [new Parameter('limit', ParameterIn::Query, 25)]);
    $step = StepFactory::http('s', null, $flow, $io, operationPath: '/pets');

    expect($step->flow)->toBe($flow)
        ->and($step->io)->toBe($io)
        ->and($step->target->operationPath)->toBe('/pets');
});
```

- [x] **Step 24: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter StepFactoryTest` (repo root)

Expected: FAIL with "Class StepFactory not found" (the aggregate `Step` does not exist in its 5-param shape yet).

- [x] **Step 25: Write minimal implementation (StepFactory + Step rewrite)**

Create `packages/contracts/src/Spec/StepFactory.php`. Note: `StepFactory` is `final readonly` (static-only class) so it satisfies the `Spec` namespace `toBeReadonly()` Arch invariant; a readonly class may carry static methods in PHP 8.4:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;

/**
 * Named constructors for the decomposed Step model (D4).
 */
final readonly class StepFactory
{
    public static function http(
        string $stepId,
        ?string $description,
        StepFlow $flow,
        StepIo $io,
        ?string $operationId = null,
        ?string $operationPath = null,
    ): Step {
        return new Step($stepId, $description, StepTarget::http($operationId, $operationPath), $flow, $io);
    }

    public static function workflow(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $workflowId): Step
    {
        return new Step($stepId, $description, StepTarget::workflow($workflowId), $flow, $io);
    }

    public static function async(
        string $stepId,
        ?string $description,
        StepFlow $flow,
        StepIo $io,
        string $action,
        string $channelPath,
        ?Expression $correlationId = null,
    ): Step {
        return new Step($stepId, $description, StepTarget::async($action, $channelPath, $correlationId), $flow, $io);
    }

    public static function rpc(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $rpcMethod, RpcProtocol $rpcProtocol): Step
    {
        return new Step($stepId, $description, StepTarget::rpc($rpcMethod, $rpcProtocol), $flow, $io);
    }

    public static function graphql(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $graphqlOperation): Step
    {
        return new Step($stepId, $description, StepTarget::graphql($graphqlOperation), $flow, $io);
    }

    public static function interaction(string $stepId, ?string $description, StepFlow $flow, StepIo $io, Interaction $interaction): Step
    {
        return new Step($stepId, $description, StepTarget::interaction($interaction), $flow, $io);
    }

    public static function wsdl(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $operationName): Step
    {
        return new Step($stepId, $description, StepTarget::wsdl($operationName), $flow, $io);
    }
}
```

Rewrite `packages/contracts/src/Spec/Step.php` as the 5-param aggregate — replace the entire file body (the old 20-param constructor is deleted; no compatibility shim, this is the ratified breaking major):

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

final readonly class Step
{
    public function __construct(
        public string $stepId,
        public ?string $description,
        public StepTarget $target,
        public StepFlow $flow,
        public StepIo $io,
    ) {}
}
```

- [x] **Step 26: Run test to verify the decomposition passes**

Run: `vendor/bin/pest packages/contracts/tests --filter "StepTargetTest|StepFlowTest|StepIoTest|StepFactoryTest"` (repo root)

Expected: PASS (all four decomposition files green; the value objects, the factory, and the aggregate agree on the interfaces above).

- [x] **Step 27: Migrate contracts-internal consumers and verify the contracts suite**

`Step` now has 5 params, so the two contracts src files that read flat Step fields and the four contracts test files that construct `Step` directly must migrate in-package before `composer run test-contracts` can pass.

Edit `packages/contracts/src/Dependency/DependencyGraph.php` line 44 — the reads move onto the control axis:

```php
            $deps = $step->flow->dependsOn;
```

Edit `packages/contracts/src/Dependency/ImplicitDependencies.php` — parameter/body/criteria/output reads move onto the data axis and correlationId onto the target axis:

```php
        foreach ($step->io->parameters as $parameter) {
            $fragments[] = $parameter->value;
        }

        if ($step->io->requestBody !== null) {
            $fragments[] = $step->io->requestBody->payload;
            foreach ($step->io->requestBody->replacements as $replacement) {
                $fragments[] = $replacement->value;
            }
        }

        foreach ($step->io->successCriteria as $criterion) {
            $fragments[] = $criterion->context;
            $fragments[] = $criterion->condition;
        }

        if ($step->target->correlationId !== null) {
            $fragments[] = $step->target->correlationId;
        }

        foreach ($step->io->outputs as $expression) {
```

Migrate the four contracts test files that construct `Step` directly (`packages/contracts/tests/Dependency/DependencyGraphTest.php`, `packages/contracts/tests/Dependency/DependencyAnalyzerTest.php`, `packages/contracts/tests/Dependency/ImplicitDependenciesTest.php`, `packages/contracts/tests/Spec/ContainerDtoTest.php`):

- Dependency-only fake steps (all of `DependencyGraphTest`, `DependencyAnalyzerTest`, and the `DependencyGraph` fixture in `ImplicitDependenciesTest`) carry no target — build the aggregate directly with the all-default axes, e.g. `new Step('A', null, null, null, null, [], null, [], [], [], [], ['A', 'B'])` becomes:

```php
new Step('A', null, new StepTarget(), new StepFlow(dependsOn: ['A', 'B']), new StepIo()),
```

- Target-bearing steps use `StepFactory::http`. `packages/contracts/tests/Spec/ContainerDtoTest.php` line 16 becomes:

```php
    $step = StepFactory::http('s1', null, new StepFlow(), new StepIo(), operationId: 'getFoo');
```

- `packages/contracts/tests/Dependency/ImplicitDependenciesTest.php` lines 36-52 rebuild a step to add a correlationId. It now rebuilds on top of the `Fx::step()` result (which after Step 30 returns the aggregate) by swapping the target and keeping flow/io:

```php
    $step = new Step(
        stepId: $step->stepId,
        description: null,
        target: new StepTarget(
            operationId: $step->target->operationId,
            correlationId: new Expression('{$steps.load-cart.outputs.correlationId}'),
        ),
        flow: $step->flow,
        io: $step->io,
    );
```

Run: `composer run test-contracts` (repo root)

Expected: PASS. Migration (not breakage) is complete inside `packages/contracts`. The `document`, `runner`, `cli`, `expression`, and `laravel` packages are now RED — every failure is a `new Step(` construction or a `$step->flatField` read that Steps 28-31 migrate. The contracts-side direct constructions are exhausted, so Step 31's `rg 'new Step\(' packages` gate can now only match the other packages.

### Part 3: Consumer migration (Steps 28-31)

These steps rewrite the monorepo's Step consumers against the decomposed model. Steps 28-30 each migrate one concrete src file (the parser, the parameter merger, the `Fx` test helper). Step 31 then sweeps every remaining construction and read mechanically. Because the package test-suites still hold old-shape fixtures until Step 31, the "run" of each of Steps 28-30 is intentionally expected to surface only those residual failures; the suites turn green together at the Step 31 gate and Step 38's full run.

- [x] **Step 28: Migrate the document parser to variant detection + StepFactory**

In `packages/document/src/Parser/Parser.php`, the `parseStep` body already collects `$parameters`, `$requestBody`, `$criteria`, `$onSuccess`, `$onFailure`, `$outputs`, `$action`, `$channelPath`, `$correlationId`, `$strictValidation`, `$idempotencyKey`, `$idempotencyHeader`, `$dependsOn`, `stepId`, `description`. Replace the whole `return new Step(...)` block (line 338) with variant detection plus the 5-param aggregate, hoisting `operationId`, `operationPath`, `workflowId`, and `timeout` reads above the return:

```php
        $operationId = $this->optionalString($obj, 'operationId', $ctx);
        $operationPath = $this->optionalString($obj, 'operationPath', $ctx);
        $workflowId = $this->optionalString($obj, 'workflowId', $ctx);
        $timeout = $this->optionalInt($obj, 'timeout', $ctx);

        $target = match (true) {
            $workflowId !== null => StepTarget::workflow($workflowId),
            $action !== null && $channelPath !== null => StepTarget::async($action, $channelPath, $correlationId),
            $operationId !== null || $operationPath !== null => StepTarget::http(operationId: $operationId, operationPath: $operationPath),
            default => new StepTarget(),
        };

        return new Step(
            stepId: $this->requireString($obj, 'stepId', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            target: $target,
            flow: new StepFlow(
                dependsOn: $dependsOn,
                timeout: $timeout, // duration in milliseconds
                onSuccess: $onSuccess,
                onFailure: $onFailure,
                strictValidation: $strictValidation,
                idempotencyKey: $idempotencyKey,
                idempotencyHeader: $idempotencyHeader,
            ),
            io: new StepIo(
                parameters: $parameters,
                requestBody: $requestBody,
                successCriteria: $criteria,
                outputs: $outputs,
            ),
        );
```

Variant precedence: workflow → async (action + channelPath together) → http (operationId XOR operationPath) → bare `new StepTarget()`. The `default` branch preserves the parser's historical tolerance for target-less steps so the document layer's own `resolveOperation` "fails fast" path (`must have either operationId or operationPath` in `packages/document/tests/DocumentCapabilitiesTest.php`) still fires at resolution time, not parse time. `StepTarget::http` enforces the 1.1 operationId/operationPath exclusivity at construction (the validator still reports it as a validator error for hand-rolled documents).

Add imports to `Parser.php`:

```php
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
```

Run: `vendor/bin/pest packages/document/tests/Parser` (repo root)

Expected: FAIL — residual failures are confined to test fixtures that still read flat Step fields (`FullParserTest` line 47 `$s1->operationPath`, lines 93-94 `$step->action`/`$step->channelPath`) and to parser-independent tests that construct `new Step(...)` directly; these are fixed in Step 31. No failures originate from `Parser::parseStep` itself.

- [x] **Step 29: Migrate StepParameterMerger to rebuild via target + merged io**

In `packages/runner/src/Execution/StepParameterMerger.php`, the merge loop reads `$step->parameters` for the base step (`foreach ($step->parameters as $stepParam)` line 28) — that becomes `$step->io->parameters`. Replace the `return new Step(...)` reconstruction block (line 44) with the aggregate, forwarding `target` and `flow` unchanged and rebuilding only `io` with the merged parameters:

```php
        return new Step(
            stepId: $step->stepId,
            description: $step->description,
            target: $step->target,
            flow: $step->flow,
            io: new StepIo(
                parameters: $merged,
                requestBody: $step->io->requestBody,
                successCriteria: $step->io->successCriteria,
                outputs: $step->io->outputs,
            ),
        );
```

Add import:

```php
use Alama\Arazzo\Contracts\Spec\StepIo;
```

Run: `vendor/bin/pest packages/runner/tests/Execution/StepParameterMergerTest.php` (repo root)

Expected: FAIL — `StepParameterMergerTest` line 77 still constructs `new Step(...)` on the old shape and line 101 reads `$merged->outputs`, both fixed in Step 31. The merger src itself now compiles against the aggregate.

- [x] **Step 30: Migrate the Fx test helper to the decomposed model**

`packages/core/tests/Support/Fx.php` keeps its exact public signature (core tests call it positionally with `$id, null, $opId, $opPath, $wfId, $params, $body, $crit, $onSuccess, $onFailure, $outputs`), but its body builds the axes and dispatches to `StepFactory::workflow`/`StepFactory::http` based on `$wfId`, falling back to a bare target for degenerate fake steps (no workflow, no operation):

```php
    public static function step(
        string $id = 's',
        ?string $opId = 'op',
        ?string $opPath = null,
        ?string $wfId = null,
        array $params = [],
        ?RequestBody $body = null,
        array $crit = [],
        array $onSuccess = [],
        array $onFailure = [],
        array $outputs = [],
    ): Step {
        $flow = new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure);
        $io = new StepIo(parameters: $params, requestBody: $body, successCriteria: $crit, outputs: $outputs);

        return match (true) {
            $wfId !== null => StepFactory::workflow($id, null, $flow, $io, $wfId),
            $opId !== null || $opPath !== null => StepFactory::http($id, null, $flow, $io, operationId: $opId, operationPath: $opPath),
            default => new Step($id, null, new StepTarget(), $flow, $io),
        };
    }
```

Add imports to `Fx.php`:

```php
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
```

Run: `composer run test-core` (repo root)

Expected: FAIL — residual failures are core tests that read a flat field (e.g. `$step->parameters`, `$step->dependsOn`) on a step returned by `Fx::step()`; those reads migrate in Step 31. Every `Fx::step()` construction itself now returns the aggregate, so no construction-site failure remains in `packages/core`.

- [x] **Step 31: Repo-wide migration of remaining Step constructions and flat-field reads**

Every remaining `new Step(...)` call-site and every remaining `$step-><flatField>` read must be remapped. This is a mechanical sweep driven by the migration table — do not hand-edit each file from memory; let the suites tell you what remains.

Field remap for reads (identical to the Interfaces block above):

```
step->operationId|operationPath|workflowId|action|channelPath|correlationId|operationName|rpcMethod|rpcProtocol|graphqlOperation|interaction   ->  step->target->...
step->parameters|requestBody|successCriteria|outputs                          ->  step->io->...
step->dependsOn|timeout|timeoutDuration|onSuccess|onFailure|onTimeout|onCancel|strictValidation|idempotencyKey|idempotencyHeader ->  step->flow->...
step->stepId|description                                                       ->  unchanged
```

Construction remap — group each `new Step(...)` by its variant and rebuild with `StepFactory`:

```
workflowId (arg 5) set            ->  StepFactory::workflow($stepId, $description, $flow, $io, $workflowId)
action+channelPath (args 13-14)   ->  StepFactory::async($stepId, $description, $flow, $io, $action, $channelPath, $correlationId)
operationId/operationPath set     ->  StepFactory::http($stepId, $description, $flow, $io, operationId: ..., operationPath: ...)
nothing set (dependency-only)     ->  new Step($stepId, $description, new StepTarget(), $flow, $io)
action without channelPath        ->  new Step($stepId, $description, new StepTarget(action: $action), $flow, $io)
```

where `$flow = new StepFlow(dependsOn: $dependsOn, timeout: $timeout, onSuccess: $onSuccess, onFailure: $onFailure, strictValidation: ..., idempotencyKey: ..., idempotencyHeader: ...)` and `$io = new StepIo(parameters: $parameters, requestBody: $requestBody, successCriteria: $successCriteria, outputs: $outputs)`.

Enumerate the src reads first — the flat-field reads live in these files (sweep them all):

- `packages/runner/src/Async/SuspensionHandler.php` (`$step->action`, `$step->correlationId`, `$step->channelPath` → target)
- `packages/runner/src/Protocol/SubWorkflowStepExecutor.php` (`workflowId`, `action`, `parameters` → target/io)
- `packages/runner/src/Protocol/AsyncApiStepExecutor.php` (`action`, `correlationId`, `channelPath`, `timeout`, `parameters`, `requestBody` → target/flow/io)
- `packages/runner/src/Protocol/SubWorkflowExecutor.php` (`workflowId`, `operationPath`, `operationId`, `parameters` → target/io)
- `packages/runner/src/Protocol/HttpStepExecutor.php` (`action` → target; `timeout` → flow; `strictValidation` → flow)
- `packages/runner/src/Execution/RequestCompiler.php` (`parameters`, `requestBody` → io)
- `packages/runner/src/Execution/StepOutcomeHandler.php` (`outputs` → io; `onSuccess`, `onFailure` → flow)
- `packages/runner/src/Execution/WorkflowEngine.php` (`onSuccess`, `onFailure` → flow)
- `packages/runner/src/Execution/StepExecutor.php` (`timeout` → flow; `strictValidation` → flow)
- `packages/runner/src/Execution/StepOutputExtractor.php` (`outputs` → io)
- `packages/runner/src/Execution/StepExecutionWorker.php` (`action`, `correlationId`, `channelPath` → target)
- `packages/runner/src/Execution/IdempotencyKeyInjector.php` (`idempotencyKey`, `idempotencyHeader` → flow)
- `packages/expression/src/Evaluation/CriteriaEvaluator.php` (`successCriteria` → io; `operationId`, `operationPath` → target)
- `packages/expression/src/Evaluation/PayloadReplacer.php` (`requestBody` → io)
- `packages/runner/src/Execution/StepParameterMerger.php` (done in Step 29)
- `packages/cli/src/Console/Command/ListWorkflowsCommand.php` (`operationId`, `operationPath`, `workflowId`, `action` → target)
- `packages/cli/src/Renderer/Renderer.php` (`operationId`, `operationPath`, `workflowId`, `action` → target; `successCriteria`, `outputs` → io; `onSuccess`, `onFailure` → flow)
- `packages/document/src/Normalizer/OpenApiOperationResolver.php` (`operationId`, `operationPath` → target)
- `packages/document/src/Validator/PreflightValidator.php` (`onSuccess`, `onFailure` → flow; `operationPath`, `operationId` → target; `parameters`, `outputs` → io)
- `packages/document/src/Validator/Rules/SelectorTypeSupportedRule.php` (`parameters`, `requestBody`, `outputs` → io)
- `packages/document/src/Validator/Rules/ExpressionUnresolvedStepRefRule.php` (`dependsOn` → flow)
- `packages/document/src/Validator/Rules/SubWorkflowInvokeTargetResolvesRule.php` (`onSuccess`, `onFailure` → flow)
- `packages/document/src/Validator/Rules/ParameterQuerystringOperationShapeRule.php` (`parameters` → io)

Representative before/after (apply the same shape to every file above):

```php
// HttpStepExecutor
return $step->action === null;                               // -> return $step->target->action === null;
$step->timeout !== null ? $step->timeout / 1000 : null,      // -> $step->flow->timeout !== null ? $step->flow->timeout / 1000 : null,
return $step->strictValidation ?? $this->strictValidationDefault; // -> return $step->flow->strictValidation ?? $this->strictValidationDefault;

// CriteriaEvaluator
if ($this->hasOperationTarget($step) && $step->successCriteria === []) { ... }   // -> $step->io->successCriteria
return $step->operationId !== null || $step->operationPath !== null;             // -> $step->target->operationId ... $step->target->operationPath

// OpenApiOperationResolver
$opId = $step->operationId;       // -> $step->target->operationId
$opPath = $step->operationPath;   // -> $step->target->operationPath

// IdempotencyKeyInjector
$enabled = $step->idempotencyKey ?? $this->enabledDefault;             // -> $step->flow->idempotencyKey
$header = $step->idempotencyHeader ?? $this->headerDefault;            // -> $step->flow->idempotencyHeader

// SuspensionHandler
if ($step->action === 'receive' && $step->correlationId !== null && $step->channelPath !== null) {  // -> target->action / target->correlationId / target->channelPath
```

Then sweep the tests. Remaining direct `new Step(` constructions live in the runner, cli, laravel, expression, and document suites (e.g. `AsyncApiStepExecutorTest`, `SuspensionHandlerTest`, `SubWorkflowExecutorTest`, `CliRunnerTest`, `RunExecuteStepJobTest`, `LaravelQueueDriverTest`, `ExpressionEngineCapabilitiesTest`, `SymbolTableTest`, `RetryPolicyTest`, `AdapterParityTest`, `StepOutcomeHandlerTest`, plus nested `new Step(...)` inside `new Workflow(...)` in `RunnerTest` and `RunnerCapabilitiesTest`). Construction-site replacements that carry an async action but no channelPath (e.g. `SuspensionHandlerTest` line 126) use the degenerate `new StepTarget(action: 'send')` form above. Rewrite each with `StepFactory` per the construction remap; prefer `Fx::step()` where the surrounding file already imports the support helper.

Test assertions that read flat fields on a parsed/fixture step also migrate — e.g. `packages/document/tests/Parser/FullParserTest.php` line 47 `$s1->operationPath` → `$s1->target->operationPath`, lines 93-94 `$step->action`/`$step->channelPath` → `$step->target->action`/`$step->target->channelPath`; `StepParameterMergerTest` line 101 `$merged->outputs`/`$step->outputs` → `->io->outputs`; `ImplicitDependenciesTest` (already handled in Step 27).

Gate — after the sweep, all flat-construction must be gone from tests and src:

```bash
rg 'new Step\(' packages
# Expected: zero matches
```

Then run the migration loops, one package suite at a time, fixing whatever each run surfaces (this catches any flat-field read the enumerations above missed):

```bash
composer run test-contracts && composer run test-document && composer run test-runner && composer run test-expression && composer run test-cli && composer run test-core && composer run test-laravel
```

Expected: each suite PASS in turn. The monorepo is green again at the end of this step; Step 38 re-runs everything as the official gate.

### Part 4: Parameter + Components additions (additive, Steps 32-37)

The D11 additive extension that pairs with the decomposition. Both target files only append trailing constructor params with defaults — no existing call-site changes (global constraint lines 14-16).

- [x] **Step 32: Write the failing Parameter extension test**

Create `packages/contracts/tests/Contracts/Spec/ParameterTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;
use Alama\Arazzo\Contracts\Spec\Parameter;

it('supports metadata and variable locations plus an explicit value mode', function (): void {
    $parameter = new Parameter('operation', ParameterIn::Metadata, 'GetToken', ValueMode::Literal);

    expect($parameter->name)->toBe('operation')
        ->and($parameter->in)->toBe(ParameterIn::Metadata)
        ->and($parameter->value)->toBe('GetToken')
        ->and($parameter->valueMode)->toBe(ValueMode::Literal);
});

it('defaults valueMode to null', function (): void {
    $parameter = new Parameter('op', ParameterIn::Query, 1);

    expect($parameter->valueMode)->toBeNull();
});
```

- [x] **Step 33: Run test to verify it fails**

Run: `vendor/bin/pest packages/contracts/tests --filter ParameterTest` (repo root)

Expected: FAIL (missing `$valueMode` param or missing `ParameterIn::Metadata` case).

- [x] **Step 34: Write minimal Parameter extension**

Edit `packages/contracts/src/Spec/Parameter.php` — append `$valueMode` and add the `ValueMode` import:

```php
use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;

final readonly class Parameter
{
    public function __construct(
        public string $name,
        public ?ParameterIn $in,
        public mixed $value,
        public ?ValueMode $valueMode = null,
    ) {}
}
```

- [x] **Step 35: Run test to verify Parameter passes**

Run: `vendor/bin/pest packages/contracts/tests --filter ParameterTest` (repo root)

Expected: PASS.

- [x] **Step 36: Write the failing Components extension test and run it to verify it fails**

Create `packages/contracts/tests/Contracts/Spec/ComponentsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Interaction;

it('appends an interactions bag additively', function (): void {
    $interactions = ['confirm-payment' => new Interaction(expectedPayload: 'approve')];
    $components = new Components(
        inputs: [],
        parameters: [],
        successActions: [],
        failureActions: [],
        interactions: $interactions,
    );

    expect($components->interactions)->toBe($interactions);
});

it('defaults interactions to an empty bag', function (): void {
    $components = new Components(inputs: [], parameters: [], successActions: [], failureActions: []);

    expect($components->interactions)->toBe([]);
});
```

Run: `vendor/bin/pest packages/contracts/tests --filter ComponentsTest` (repo root)

Expected: FAIL with "Missing named parameter $interactions".

- [x] **Step 37: Write minimal Components extension and run it to verify it passes**

Edit `packages/contracts/src/Spec/Components.php` — append `$interactions` and add the `Interaction` import:

```php
use Alama\Arazzo\Contracts\Spec\Interaction;

final readonly class Components
{
    /**
     * @param  array<string,array<string,mixed>>  $inputs
     * @param  array<string,Parameter>  $parameters
     * @param  array<string,SuccessAction>  $successActions
     * @param  array<string,FailureAction>  $failureActions
     * @param  array<string,Interaction>  $interactions
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $successActions,
        public array $failureActions,
        public array $interactions = [],
    ) {}
}
```

Run: `vendor/bin/pest packages/contracts/tests --filter "ParameterTest|ComponentsTest"` (repo root)

Expected: PASS.

### Part 5: Final commit + verification (Steps 38-39)

- [x] **Step 38: Full repo verification**

Run: `composer run test` (repo root)

Expected: PASS — all seven suites green (`test-contracts`, `test-expression`, `test-document`, `test-runner`, `test-cli`, `test-core`, `test-laravel`). This is the A7 monorepo gate (global constraint line 25): the one ratified breaking major lands with every consumer migrated in the same change.

- [x] **Step 39: Commit**

```bash
git add packages/contracts/src/Spec/Step.php packages/contracts/src/Spec/StepTarget.php packages/contracts/src/Spec/StepFlow.php packages/contracts/src/Spec/StepIo.php packages/contracts/src/Spec/StepFactory.php packages/contracts/src/Spec/Interaction.php packages/contracts/src/Spec/Parameter.php packages/contracts/src/Spec/Components.php packages/contracts/src/Spec/Enum/RpcProtocol.php packages/contracts/src/Spec/Enum/ValueMode.php packages/contracts/src/Spec/Enum/ParameterIn.php packages/contracts/src/Dependency/DependencyGraph.php packages/contracts/src/Dependency/ImplicitDependencies.php packages/contracts/tests packages/document/src packages/document/tests packages/runner/src packages/runner/tests packages/expression/src packages/expression/tests packages/cli/src packages/cli/tests packages/laravel/tests packages/core/tests
git commit -m "feat(contracts): decompose Step into StepTarget/StepFlow/StepIo + StepFactory; migrate all consumers"
```

---

### Task A8: Contracts-wide verification + gate

Close out Phase A: full contracts quality gate and confirm zero drift in the shared contract surface (spec lines 596-628).

**Files:**
- None to modify (unless formatting requires).

**Interfaces:**
- Consumes: all tasks A1–A7.

- [x] **Step 1: Run the full contracts test suite**

Run: `composer run test-contracts` (repo root)

Expected: PASS (all tests, including Arch tests if registered).

- [x] **Step 2: Run static analysis**

Run: `composer run analyse-contracts` (repo root)

Expected: PASS (0 errors). If PHPStan reports "Call to an undefined method" on the anonymous-class contexts in A5/A6 tests, fix the test (the interface must be imported) — do not suppress.

- [x] **Step 3: Run the formatter check**

Run: `composer run format` or `vendor/bin/pint --test` (repo root)

Expected: PASS (no style violations). If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [x] **Step 4: Run the repo-wide gate (if the worker supports it)**

Run: `make verify` (repo root)

Expected: PASS — confirms contracts changes do not break `core`/`laravel` consumers: `Parameter`/`Components` appended defaulted params must not affect existing named-arg construction; the decomposed `Step`/`StepTarget`/`StepFlow`/`StepIo` + `StepFactory` must be consistent across every consumer that migrated in A7; the `ParameterIn` new cases must not break exhaustive-match code — the enum is not exhaustive-listed anywhere in tests.

- [x] **Step 5: Mark this plan's steps complete**

Flip every `- [x]` in this document to `- [x]`.

- [x] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the "Sequencing" table row for Phase A, and mark it done (e.g. append `✅` to the status column). If the row is not a table, add a line under the Phase A heading:

```markdown
Phase A status: ✅ Implemented 2026-09-08 — see `plans/2026-09-08-phase-a-contracts-ports.md`.
```

- [x] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase A contracts ports complete"
```