# Core Package Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:
> executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the single `alama/arazzo-core` composer package into a set of coarse, internal-only monorepo composer
packages by first resolving the 9 layering violations and 11 framework-boundary violations, then assembling packages
along the acyclic dependency DAG.

**Architecture:** Keep the stable `Alama\Arazzo\` root namespace across all packages (per-package PSR-4 subpaths; no
mass class renames). Resolve the current layering violations by **promoting shared contracts and shared state DTOs down
into a lower `contracts` layer**, which simultaneously fixes the violations and establishes the first package seam. Then
create `expression`, `document`, `runner`, and `cli` packages along the existing `LAYER_ORDER`. Each operational package
exposes one **entry-point interface** (the seam) plus a thin **Facade** behind it, so packages cross boundaries only
through facades/interfaces (GoF Facade pattern; static Laravel facades belong only in `laravel`). All packages are
internal (not published to Packagist), so cross-package namespace sharing is safe.

**Tech Stack:** PHP 8.4, Composer path repositories, Symplify MonorepoBuilder, Pest, PHPStan, Pint, existing
generated-docs tooling (`scripts/generate-docs.php`).

**Spec:** This plan is the spec. Prior analysis documents: `docs/generated/layering.md`,
`docs/generated/coupling-metrics.md`, `docs/generated/boundaries-audit.md`, `docs/generated/extension-points.md`,
`docs/generated/subdomain-map.md`, `docs/generated/ubiquitous-language-audit.md`, and the existing
`docs/superpowers/plans/2026-08-25-runner-modularization-plan.md`.

## Global Constraints

- Internal-only packages. **Do not** publish to Packagist or add publish config beyond what the existing `core`/
  `laravel` packages have.
- Keep the `Alama\Arazzo\` root namespace. Do not rename classes or namespaces; only move files between `packages/*/src`
  trees and adjust `use` statements where imports previously crossed module boundaries.
- Preserve all existing public API signatures unless a task explicitly says otherwise (`public-api.md` + `bc-diff.md`
  guard this).
- No emojis, no comments added unless already present or explicitly required.
- Every task ends with gates green: `vendor/bin/pint --test`,
  `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`, and the relevant Pest suite.
- The architecture docs are **generated** (see `.githooks/pre-commit`). After any structural move, run
  `php scripts/generate-docs.php` and commit the regenerated docs with the change.
- `docs/generated/*.md` are auto-generated — never hand-edit them.

---

## File Structure (target)

```
packages/
  contracts/
    composer.json                  # alama/arazzo-contracts
    src/
      Spec/                        # moved from core/src/Spec
      Support/                     # moved from core/src/Support
      Contracts/                   # new: BackoffCalculatorInterface, AiClientInterface,
                                   #      QueueDriverInterface, StepProtocolExecutorInterface
      State/                       # new: shared WorkflowContext + ExecutionState DTOs
    tests/
  expression/
    composer.json                  # alama/arazzo-expression
    src/ Expression/  Evaluation/
         ExpressionEngineInterface.php  ExpressionEngine.php   # entry seam + facade
    tests/
  document/
    composer.json                  # alama/arazzo-document
    src/ Parser/  Resolver/  Normalizer/  Validator/
         DocumentInterface.php  Document.php                  # entry seam + facade
    tests/
  runner/
    composer.json                  # alama/arazzo-runner
    src/ Execution/  State/  Events/  Policy/  Jobs/  Protocol/
         Async/  Telemetry/  Infrastructure/  Dependency/
         RunnerInterface.php  Runner.php                      # entry seam + facade
    tests/
  cli/
    composer.json                  # alama/arazzo-cli
    src/ Console/  Generator/  Renderer/
    tests/
  laravel/                         # unchanged scope (existing adapters)
```

`packages/core` is **replaced** by the above; `packages/laravel` stays and gains path-repo deps on the new packages.

---

## Task 1: Scaffold the contracts package (Spec + Support)

**Files:**

- Create: `packages/contracts/composer.json`
- Move: `packages/core/src/Spec/` → `packages/contracts/src/Spec/`
- Move: `packages/core/src/Support/` → `packages/contracts/src/Support/`
- Move: `packages/core/tests/Spec*/`, `packages/core/tests/Support*/` → `packages/contracts/tests/`
- Modify: `composer.json` (root), `packages/core/composer.json`, `packages/laravel/composer.json`
- Modify: `scripts/generate-docs.php` (add contracts to the scan list)
- Modify: `packages/core/tests/ArchTest.php` (adjust `Alama\Arazzo\Spec` expectation location)

**Interfaces:**

- Consumes: existing `Spec\*` and `Support\*` classes (unchanged).
- Produces: package `alama/arazzo-contracts` with PSR-4 `Alama\Arazzo\` → `src/` covering `Spec/` and `Support/`.

- [ ] **Step 1: Create the package directory and composer.json**

```bash
mkdir -p packages/contracts/src packages/contracts/tests
```

Create `packages/contracts/composer.json`:

```json
{
    "name": "alama/arazzo-contracts",
    "description": "Arazzo domain model, shared contracts and state DTOs.",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Tests\\": "tests/"
        }
    },
    "require": {
        "php": "^8.4"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

> `Spec/` and `Support/` are pure domain DTOs + shared exceptions and import no external vendor code (their imports are
> PSR/stdlib only). Verify with `rg "use [A-Z]" packages/contracts/src` — if any external vendor `use` appears, add that
> package to `require`. Otherwise omit runtime deps. `require-dev` mirrors core's dev tooling (see Task 1 Step 2 note
> below for the exact dev block to copy).

- [ ] **Step 2: Move the source files (git mv preserves history)**

```bash
cd packages
git mv core/src/Spec contracts/src/Spec
git mv core/src/Support contracts/src/Support
```

Then add the standard dev tooling to `packages/contracts/composer.json` (copy from the current
`packages/core/composer.json` `require-dev` + `config`, i.e. `larastan/larastan`, `laravel/pint`, `mockery/mockery`,
`pestphp/pest`, `pestphp/pest-plugin-arch`, `phpstan/phpstan`, `phpstan/phpstan-deprecation-rules`,
`phpstan/phpstan-phpunit`, versions `^3.0`–`^2.0` as in core, plus `allow-plugins` for `pestphp/pest-plugin` and
`phpstan/extension-installer`). Every new package in Tasks 8–11 carries the same dev block.

- [ ] **Step 3: Move the corresponding test trees**

List what tests exist and move them:

```bash
ls core/tests
git mv core/tests/Spec contracts/tests/Spec 2>/dev/null || true
git mv core/tests/Support contracts/tests/Support 2>/dev/null || true
```

If the tests live under a combined folder (e.g. `core/tests/Unit/Spec`), move the matching paths — inspect first, move
what corresponds to `Spec/` and `Support/` only.

- [ ] **Step 4: Wire the root monorepo**

Edit root `composer.json` `repositories`:

```json
"repositories": [
{"type": "path", "url": "packages/contracts"},
{"type": "path", "url": "packages/core"},
{"type": "path", "url": "packages/laravel"}
]
```

Add to root `require`: `"alama/arazzo-contracts": "@dev"`.

- [ ] **Step 5: Make core depend on contracts**

Edit `packages/core/composer.json`:

- Add `"alama/arazzo-contracts": "@dev"` to `require`.
- Add `{"type": "path", "url": "../contracts"}` to `repositories`.
- Collapse `autoload.psr-4` — `Alama\\Arazzo\\` must **no longer** map `src/Spec` and `src/Support` (they've moved).
  Keep the prefix for the remaining core modules.

- [ ] **Step 6: Make laravel depend on contracts**

Edit `packages/laravel/composer.json`: add `"alama/arazzo-contracts": "@dev"` to `require` and add
`{"type": "path", "url": "../contracts"}` to its `repositories`.

- [ ] **Step 7: Run composer install and verify resolution**

```bash
composer update alama/arazzo-contracts --with-all-dependencies
composer dump-autoload
```

Expected: no "class not found", packages resolve via path repositories.

- [ ] **Step 8: Run the architecture gates**

```bash
composer run test-core
composer run test-laravel
```

Expected: PASS. Any test that referenced `Spec`/`Support` classes now resolves them from the contracts package — PSR-4
makes the namespace identical, so tests should pass unchanged. If `ArchTest.php` asserts on `Alama\Arazzo\Spec`, update
it to keep the strictness checks (those classes still exist in the same namespace, just a different package root — see
Task 6 for per-package arch rules).

- [ ] **Step 9: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "build: extract alama/arazzo-contracts package (Spec + Support)"
```

Expected: `docs/generated/namespace-graph.md` now shows a `Contracts` package node; everything else stays consistent.

---

## Task 2: Promote shared contracts into the contracts package

This task resolves the majority of the 9 layering violations by moving the shared interfaces that (a) are defined in a
high module yet (b) implemented by lower modules, or (c) imported by modules that shouldn't reach up. After this task,
upper modules no longer depend on `Execution`/`Async`/`Protocol` merely to reference a contract.

**Files:**

- Create: `packages/contracts/src/Contracts/` with the four interfaces below.
- Modify: every file that imports the old interface FQCN.
- Test: `packages/contracts/tests/Contracts/SharedContractsTest.php`

**Interfaces:**

- Consumes: `Spec\Step`, `Spec\ArazzoDocument`, `Spec\StepExecutionOutcome` (from contracts).
- Produces: new interfaces in `Alama\Arazzo\Contracts\Interfaces\`:
    - `BackoffCalculatorInterface` — `calculate(float $baseDelay, int $attempt, float $multiplier): int`
    - `AiClientInterface` — `generate(string $systemPrompt, string $userPrompt): string`
    - `QueueDriverInterface` — `dispatch(object $job, int $delaySeconds = 0): void`
    - `StepProtocolExecutorInterface` —
      `supports(Step $step, ArazzoDocument $document): bool; execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome`

- [ ] **Step 1: Write the failing test**

Create `packages/contracts/tests/Contracts/SharedContractsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Contracts\Interfaces\BackoffCalculatorInterface;
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/contracts && vendor/bin/pest tests/Contracts/SharedContractsTest.php`
Expected: FAIL — interface not found.

- [ ] **Step 3: Create the four interfaces**

Create `packages/contracts/src/Contracts/Interfaces/BackoffCalculatorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface BackoffCalculatorInterface
{
    public function calculate(float $baseDelay, int $attempt, float $multiplier): int;
}
```

Create `packages/contracts/src/Contracts/Interfaces/AiClientInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface AiClientInterface
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}
```

Create `packages/contracts/src/Contracts/Interfaces/QueueDriverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void;
}
```

Create `packages/contracts/src/Contracts/Interfaces/StepProtocolExecutorInterface.php`. At this stage `WorkflowContext`
still lives at `Alama\Arazzo\Execution\Data\WorkflowContext` (it moves to `Contracts\State` in Task 3, which rewires
this import). `interface_exists` checks reference the interface only, so referencing the not-yet-moved `WorkflowContext`
type is fine for compilation of the interface file:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\StepExecutionOutcome;

interface StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
```

> In Task 3 Step 4, the sed rewiring `Arazzo\Execution\Data\WorkflowContext` → `Arazzo\Contracts\State\WorkflowContext`*
*must also run over `packages/contracts/src`** so this interface keeps compiling after the DTO moves.

- [ ] **Step 4: Update all implementers and importers to the new FQCNs**

Search core for the old FQCNs and update `use` statements to the new namespace. Run:

```bash
cd packages/core/src
rg -rl "Arazzo\\Execution\\Interfaces\\BackoffCalculatorInterface" . | xargs sed -i '' 's#Arazzo\\Execution\\Interfaces\\BackoffCalculatorInterface#Arazzo\\Contracts\\Interfaces\\BackoffCalculatorInterface#g'
rg -rl "Arazzo\\Execution\\Interfaces\\AiClientInterface" . | xargs sed -i '' 's#Arazzo\\Execution\\Interfaces\\AiClientInterface#Arazzo\\Contracts\\Interfaces\\AiClientInterface#g'
rg -rl "Arazzo\\Async\\Interfaces\\QueueDriverInterface" . | xargs sed -i '' 's#Arazzo\\Async\\Interfaces\\QueueDriverInterface#Arazzo\\Contracts\\Interfaces\\QueueDriverInterface#g'
rg -rl "Arazzo\\Protocol\\Interfaces\\StepProtocolExecutorInterface" . | xargs sed -i '' 's#Arazzo\\Protocol\\Interfaces\\StepProtocolExecutorInterface#Arazzo\\Contracts\\Interfaces\\StepProtocolExecutorInterface#g'
```

> The original files (`BackoffCalculatorInterface`, `AiClientInterface` in `Execution/Interfaces`,`QueueDriverInterface`
> in `Async/Interfaces`, `StepProtocolExecutorInterface` in `Protocol/Interfaces`). Decide per interface: delete the old
> file and update its `use` sites (recommended) **or** keep it as a thin`interface X extends Contracts\Interfaces\X {}`
> alias for BC. Prefer deletion for internal-only packages, but if any public-api doc references these, keep the alias
> and
> delete it in Task 13.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd packages/contracts && vendor/bin/pest tests/Contracts/SharedContractsTest.php`
Expected: PASS.

- [ ] **Step 6: Run full suites**

```bash
composer run test-core
composer run test-laravel
```

Expected: PASS. Implementation classes (`ExponentialBackoffCalculator`, `OpenAiClient`, `SyncQueueDriver`, the protocol
executors) now implement interfaces resolved from contracts.

- [ ] **Step 7: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
```

Check `docs/generated/layering.md` — the `Execution→Async`, `Generator→Execution`, `Policy→Execution`,
`Execution→Protocol` violation rows should reduce or disappear as the seams move. Commit:

```bash
git add -A
git commit -m "refactor: promote shared contracts into arazzo-contracts"
```

---

## Task 3: Promote shared state DTOs into contracts

Resolves the remaining `State↔Execution`, `Dependency→Execution`, `Jobs→Execution`, and residual `Policy→Execution`,
`Protocol→Execution` violations by moving the shared mutable/state DTOs (`WorkflowContext`, `ExecutionState`) out of
`Execution\Data` into `Contracts\State`.

**Files:**

- Create: `packages/contracts/src/State/WorkflowContext.php`, `packages/contracts/src/State/ExecutionState.php`
- Move: `packages/core/src/Execution/Data/WorkflowContext.php`, `packages/core/src/Execution/Data/ExecutionState.php`
- Update: all importers across core.
- Test: `packages/contracts/tests/State/StateDtoTest.php`

**Interfaces:**

- Consumes: `Spec` types used by these DTOs (from contracts).
- Produces: `Alama\Arazzo\Contracts\State\WorkflowContext` and `Alama\Arazzo\Contracts\State\ExecutionState` with *
  *identical public API** to the originals (public API signatures frozen).

- [ ] **Step 1: Write the failing test**

Create `packages/contracts/tests/State/StateDtoTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;

it('declares the shared state DTOs')
    ->expect(class_exists(WorkflowContext::class))
    ->toBeTrue()
    ->and(class_exists(ExecutionState::class))
    ->toBeTrue();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/contracts && vendor/bin/pest tests/State/StateDtoTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Move the DTOs**

```bash
cd packages
git mv core/src/Execution/Data/WorkflowContext.php contracts/src/State/WorkflowContext.php
git mv core/src/Execution/Data/ExecutionState.php contracts/src/State/ExecutionState.php
```

Update each file's `namespace` to `Alama\Arazzo\Contracts\State`. Keep the class bodies and all public methods
byte-for-byte identical (only the namespace line changes).

- [ ] **Step 4: Rewire all importers**

```bash
cd packages/core/src
rg -rl "Arazzo\\Execution\\Data\\WorkflowContext" . | xargs sed -i '' 's#Arazzo\\Execution\\Data\\WorkflowContext#Arazzo\\Contracts\\State\\WorkflowContext#g'
rg -rl "Arazzo\\Execution\\Data\\ExecutionState" . | xargs sed -i '' 's#Arazzo\\Execution\\Data\\ExecutionState#Arazzo\\Contracts\\State\\ExecutionState#g'
```

Also rewire inside the contracts package (the `StepProtocolExecutorInterface` created in Task 2 references
`Execution\Data\WorkflowContext`):

```bash
cd packages/contracts/src
rg -rl "Arazzo\\Execution\\Data\\WorkflowContext" . | xargs sed -i '' 's#Arazzo\\Execution\\Data\\WorkflowContext#Arazzo\\Contracts\\State\\WorkflowContext#g'
```

Repeat in `packages/laravel/src` and in any test or doc code that references these FQCNs:

```bash
cd packages/laravel/src
rg -rl "Arazzo\\Execution\\Data\\WorkflowContext" . | xargs sed -i '' 's#Arazzo\\Execution\\Data\\WorkflowContext#Arazzo\\Contracts\\State\\WorkflowContext#g'
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd packages/contracts && vendor/bin/pest tests/State/StateDtoTest.php`
Expected: PASS.

- [ ] **Step 6: Run full suites**

```bash
composer run test-core
composer run test-laravel
```

Expected: PASS. This removes `State→Execution`, `Dependency→Execution`, `Jobs→Execution`, `Policy→Execution`, and the
`Protocol→Execution` half of the `Execution↔Protocol` cycle.

- [ ] **Step 7: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
```

Verify `docs/generated/layering.md` violation count drops (expect only `Infrastructure→State` to remain from the
original 9, plus any new intra-runtime findings). Commit:

```bash
git add -A
git commit -m "refactor: promote shared state DTOs into arazzo-contracts"
```

---

## Task 4: Resolve remaining layering violations

After Tasks 2–3, the remaining original violations should be at most `Infrastructure→State` and any that remain inside
the (still unsplit) core. This task clears the leftovers.

**Files:**

- Modify: `packages/core/src/Infrastructure/PessimisticLockStrategy.php` (or wherever `Infrastructure` imports `State`)
- Inspect: all remaining rows in `docs/generated/layering.md`
- Test: existing Infrastructure + State suites

**Interfaces:**

- Consumes: shared contracts/state from Tasks 2–3.
- Produces: a zero-violation `layering.md`.

- [ ] **Step 1: Inspect the remaining violations**

```bash
php scripts/generate-docs.php
rg -n "violation" docs/generated/layering.md
```

List the remaining `From → To` rows.

- [ ] **Step 2: Fix each remaining row**

For the known `Infrastructure→State` case, `PessimisticLockStrategy` wraps `State\LockManagerInterface`. Apply
dependency inversion: `PessimisticLockStrategy` should depend on the `LockManagerInterface` contract, and infra should
sit below state. If `LockManagerInterface` lives in `State`, move it down to `Contracts\Interfaces` (same technique as
Task 2) so `Infrastructure` stops importing `State`.

For any other remaining `From → To` rows, apply the identical pattern: if `To` is higher and `From` only needs a
contract/DTO from it, promote that contract/DTO to `Contracts`; if it needs real behaviour, that is an intentional
layering decision to document in `LayeringDoc.php` `LAYER_ORDER` (revise the order only for deliberate exceptions).

- [ ] **Step 3: Run full suites**

```bash
composer run test-core
composer run test-laravel
```

Expected: PASS.

- [ ] **Step 4: Verify zero violations**

```bash
php scripts/generate-docs.php
rg -n "violation" docs/generated/layering.md
```

Expected: empty (or only rows you consciously justified in `LAYER_ORDER`).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor: resolve remaining layering violations"
```

---

## Task 5: Define per-package entry-point interfaces + Facades

Each core package exposes one high-level **entry-point interface** (the seam downstream packages depend on) and one
concrete **Facade** implementation (the thin public object that hides the package's internal modules). This gives every
package a small, stable, testable surface and prevents cross-package coupling to internals. See the discussion in this
plan's addendum ("Facade pattern for package boundaries").

**Files:**

- Create (in each package): one entry-point interface + one Facade class.
- Create: `packages/runner/src/RunnerInterface.php`, `packages/runner/src/Runner.php`
- Create: `packages/document/src/DocumentInterface.php`, `packages/document/src/Document.php`
- Create: `packages/expression/src/ExpressionEngineInterface.php`, `packages/expression/src/ExpressionEngine.php`
- Modify: `packages/laravel/src/Bindings/*.php` (bind interface → facade)
- Test: `packages/runner/tests/RunnerTest.php`, `packages/document/tests/DocumentTest.php`,
  `packages/expression/tests/ExpressionEngineTest.php`

**Interfaces:**

- Consumes: shared contracts/state from Tasks 2–3.
- Produces: per-package entry-point interfaces (`RunnerInterface`, `DocumentInterface`, `ExpressionEngineInterface`)plus
  concrete facade classes. These become the **only** cross-package touch points in Tasks 8–12 + the Laravel bindings.

- [ ] **Step 1: Write the failing test for the Runner facade**

The facade must compose the internal runtime modules behind one interface. Create`packages/runner/tests/RunnerTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Runner;
use Alama\Arazzo\Runner\RunnerInterface;

it('exposes a single entry-point interface')
    ->expect(new Runner())->toBeInstanceOf(RunnerInterface::class);
```

> This reference test is illustrative. Implement the facade by composing the modules that exist in the runner package
> after Task 10. The exact constructor shape is decided by the implementer against the package's real surface; the *
*interface must remain small** (a handful of methods at most) and hide workflow-engine wiring.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/runner && vendor/bin/pest tests/RunnerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the interface and the facade**

Create `packages/runner/src/RunnerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Workflow;

interface RunnerInterface
{
    /**
     * Execute a workflow within a document and return the outcome.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array;
}
```

Create `packages/runner/src/Runner.php` — a thin facade that delegates to the internal `WorkflowExecutor`/
`WorkflowEngine` (both live in the runner package). The facade constructor assembles them with the default in-memory
state:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Spec\ArazzoDocument;

final class Runner implements RunnerInterface
{
    public function __construct(
        private readonly ExpressionResolverInterface $expressions,
        private readonly WorkflowExecutor $executor,
    ) {}

    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array
    {
        $workflow = $document->getWorkflow($workflowId);

        return $this->executor->execute($workflow, $document, $inputs)->toArray();
    }
}
```

> The exact collaborators (`ExpressionResolverInterface`, `WorkflowExecutor`) are what the initiator wires; the facade
> stays thin — it only delegates, it does not re-implement logic. Adjust the constructor to the runner package's real
> factories; the point is `Runner` is the one concrete object other packages/adapters reach for, behind
`RunnerInterface`.

- [ ] **Step 4: Repeat for the document and expression packages**

Apply the identical pattern (interface + thin facade + failing-then-passing test):

- `DocumentInterface` / `Document` in `packages/document/src` — wraps `Parser` → `Resolver` → `Normalizer` →`Validator`.
- `ExpressionEngineInterface` / `ExpressionEngine` in `packages/expression/src` — wraps the `ExpressionEvaluator` (+
  `XpathEvaluator`).

- [ ] **Step 5: Point the Laravel adapters at the interfaces**

Modify `packages/laravel/src/Bindings/*.php` so the container binds each `*Interface` to its concrete facade. Verify
with:

```bash
composer run test-laravel
```

Expected: PASS. Laravel code may also add a `Facades\Arazzo` static convenience *only at the Laravel layer* (never in
core — core stays framework-free; `ArchTest` forbids `Illuminate` in core).

- [ ] **Step 6: Run full suites**

```bash
composer run test-core
composer run test-laravel
```

Expected: PASS.

- [ ] **Step 7: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "feat: per-package entry-point interfaces and facades"
```

---

## Task 6: Add per-package arch tests

**Files:**

- Create: `packages/contracts/tests/ArchTest.php`
- Create: `packages/expression/tests/ArchTest.php`
- Create: `packages/document/tests/ArchTest.php`
- Create: `packages/runner/tests/ArchTest.php`
- Create: `packages/cli/tests/ArchTest.php`
- Modify: keep `packages/core/tests/ArchTest.php` if core still exists, else migrate its checks.

**Interfaces:**

- Consumes: the new package boundaries established in Tasks 1–4 and created in later tasks.
- Produces: arch tests that fail the build if any package imports a higher package.

> **Ordering note:** this template runs in task-order; but per-package test dirs exist only once each package is
> created (Tasks 8–11). If executing in strict order, do the `runner`/`cli`/etc. arch tests inside their creation tasks
> and keep only the `contracts` one here — or run this task last. Recommended: fold the per-package arch test into each
> package creation task (Tasks 8–11) and use this task to define the **shared contract** so all packages stay
> consistent.

- [ ] **Step 1: Define the shared arch-test pattern (reference)**

Each package's `ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('contracts does not depend on any other package')
    ->expect('Alama\Arazzo')
    ->not->toUse('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Parser')
    ->not->toUse('Alama\Arazzo\Resolver')
    ->not->toUse('Alama\Arazzo\Normalizer')
    ->not->toUse('Alama\Arazzo\Validator')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console');
```

Analogous patterns: `document` must not use `Execution`/`Console`; `expression` must not use `Parser`/`Execution`/
`Console`; `runner` must not use `Console`; `cli` may use any lower package. Reuse the existing
`packages/core/tests/ArchTest.php` conventions (`->each->not->toBeUsed()`, `->toUseStrictTypes()`).

In addition to the layer-order checks, each boundary test asserts the **facade seam** (from Task 5): a package must only
cross into another package through its entry-point interface — never its internal classes or its concrete Facade.
Reference the facade-facing seam (e.g. `RunnerInterface`, `DocumentInterface`, `ExpressionEngineInterface`) rather than
the internals, and assert `->not->toUse('Alama\Arazzo\Execution\WorkflowEngine')`-style negative checks where a package
would otherwise reach into internals.

- [ ] **Step 2: Add the checks to each package's ArchTest**

Fold into the relevant package-creation task (Tasks 8–11) so each package ships with its boundary test. For the
already-created contracts package, add `packages/contracts/tests/ArchTest.php` now with the "depend on nothing"
assertions.

- [ ] **Step 3: Run and commit**

```bash
composer run test-core
git add -A
git commit -m "test: add per-package architecture boundary tests"
```

---

## Task 7: Fix framework-boundary violations

**Files:**

- Modify: `packages/core/src/Execution/**`, `packages/core/src/Console/**`, `packages/core/src/Validator/**`,
  `packages/core/src/Expression/**`, `packages/core/src/Parser/**`
- Modify: `scripts/generate-docs/BoundariesAuditDoc.php` (`POLICY` constant)
- Test: existing suites + `packages/core/tests/ArchTest.php`

**Interfaces:**

- Consumes: shared contracts from Tasks 2–3.
- Produces: `packages/core` free of forbidden framework imports (per updated POLICY).

- [ ] **Step 1: Inspect current violations**

```bash
rg -n "Symfony|GuzzleHttp|OpenTelemetry|JsonSchema|cebe|Flow" packages/core/src
```

Match the table in `docs/generated/boundaries-audit.md`.

- [ ] **Step 2: Make deliberate POLICY decisions**

Edit `BoundariesAuditDoc.php` `POLICY` so the boundary audit reports what you intend:

- `Console` → `Symfony\*` (30 refs): **allow** — `Console` is the delivery CLI app (symfony/console is its framework).
  This is a conscious exception.
- `Telemetry` → `OpenTelemetry\*` (23 refs): **allow** — Telemetry is the dedicated OTel module.
- `Expression` → `Flow\*` (1 ref): decouple or mark unclassified-allowed.

- [ ] **Step 3: Fix the real leaks**

For `GuzzleHttp` in `Execution` (2) and `Console` (2): route through PSR-18 `HttpClientInterface`/PSR-7 factories
already behind `Contracts`, moving any direct Guzzle construction into an adapter.
For `cebe\*` in `Execution` (10): the OpenAPI model — wrap `cebe` types behind a `Normalizer`/`Execution` boundary so
`Execution` doesn't import cebe directly (delegate to `Normalizer` which is allowed to use cebe).
For `JsonSchema\*` in `Validator` (7) and `Symfony\*` in `Parser` (2): these are generic infra — either decouple behind
an interface in `Contracts` or mark unclassified-allowed in POLICY deliberately.

- [ ] **Step 4: Run suites + boundary audit**

```bash
composer run test-core
php scripts/generate-docs.php
rg -n "violation" docs/generated/boundaries-audit.md
```

Expected: only deliberately-allowed rows remain.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor: resolve framework-boundary violations in core"
```

---

## Task 8: Create the expression package

**Files:**

- Create: `packages/expression/composer.json`
- Move: `packages/core/src/Expression/`, `packages/core/src/Evaluation/` → `packages/expression/src/`
- Move: corresponding test trees → `packages/expression/tests/`
- Create: `packages/expression/tests/ArchTest.php`
- Modify: root + core + laravel composer files, `scripts/generate-docs.php`

**Interfaces:**

- Consumes: contracts (`Spec`, `Support`, `Contracts\*`, `Contracts\State`).
- Produces: `alama/arazzo-expression`, PSR-4 `Alama\Arazzo\` → `src/`, covering `Expression/` and `Evaluation/`.

- [ ] **Step 1: Scaffold package**

```bash
mkdir -p packages/expression/src packages/expression/tests
cat > packages/expression/composer.json <<'EOF'
{
    "name": "alama/arazzo-expression",
    "description": "Arazzo expression engine and criteria evaluation.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "softcreatr/jsonpath": "^0.10.0",
        "psr/event-dispatcher": "^1.0",
        "psr/log": "^3.0"
    },
    "autoload": { "psr-4": { "Alama\\Arazzo\\": "src/" } },
    "autoload-dev": { "psr-4": { "Alama\\Arazzo\\Tests\\": "tests/" } },
    "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true, "phpstan/extension-installer": true } },
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF
```

> `Expression/JsonPathEvaluator.php` imports `Flow\JSONPath\JSONPath`, which is provided by the `softcreatr/jsonpath`
> package. `psr/event-dispatcher` and `psr/log` are used by the evaluators. Verify the exact vendored `use` statements
> with `rg "^use [A-Z]" packages/expression/src` and trim/add `require` entries to match. Add the standard dev block (
> Task
> 1 Step 2 note) after this.

- [ ] **Step 2: Move source and tests**

```bash
cd packages
git mv core/src/Expression expression/src/Expression
git mv core/src/Evaluation expression/src/Evaluation
# move matching test dirs (inspect first)
git mv core/tests/Expression expression/tests/Expression 2>/dev/null || true
git mv core/tests/Evaluation expression/tests/Evaluation 2>/dev/null || true
```

- [ ] **Step 3: Wire the monorepo**

Root `composer.json`: add path repo `packages/expression` and `"alama/arazzo-expression": "@dev"` to `require`.
`packages/core/composer.json`: add `"alama/arazzo-expression": "@dev"` to `require`, path repo `../expression`.
`packages/laravel/composer.json`: add `"alama/arazzo-expression": "@dev"` and path repo `../expression`.

- [ ] **Step 4: Add the package arch test**

Create `packages/expression/tests/ArchTest.php` — expression must not depend on parser/runtime/console (see Task 6
reference).

- [ ] **Step 5: Regenerate autoload + run**

```bash
composer update alama/arazzo-expression --with-all-dependencies
composer dump-autoload
composer run test-core
```

Expected: PASS. Any residual `Evaluation` → `Execution`/`Validator` imports would surface here; re-point them to
`Contracts` per Tasks 2–3 if not already done.

- [ ] **Step 6: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "build: extract alama/arazzo-expression package"
```

---

## Task 9: Create the document package

**Files:**

- Create: `packages/document/composer.json`
- Move: `packages/core/src/Parser/`, `Resolver/`, `Normalizer/`, `Validator/` → `packages/document/src/`
- Move: matching test trees
- Create: `packages/document/tests/ArchTest.php`
- Modify: root/core/laravel composer, `scripts/generate-docs.php`

**Interfaces:**

- Consumes: contracts + expression.
- Produces: `alama/arazzo-document`, PSR-4 `Alama\Arazzo\` → `src/`, covering `Parser/`, `Resolver/`, `Normalizer/`,
  `Validator/`.

- [ ] **Step 1: Scaffold package**

```bash
mkdir -p packages/document/src packages/document/tests
cat > packages/document/composer.json <<'EOF'
{
    "name": "alama/arazzo-document",
    "description": "Arazzo document loading, source resolution, normalization and validation.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "cebe/php-openapi": "^1.7",
        "symfony/yaml": "^7.0",
        "softcreatr/jsonpath": "^0.10.0"
    },
    "autoload": { "psr-4": { "Alama\\Arazzo\\": "src/" } },
    "autoload-dev": { "psr-4": { "Alama\\Arazzo\\Tests\\": "tests/" } },
    "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true, "phpstan/extension-installer": true } },
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF
```

> `require` mirror the versions from `packages/core/composer.json`: cebe for `Normalizer/`, symfony/yaml for `Parser/`,
> softcreatr/jsonpath + cebe for `Validator`. `Validator` imports `JsonSchema\*` (vendor `justinrainbow/json-schema`) —
> if
`Validator` still references it after Task 7, add `"justinrainbow/json-schema": "^5.0"` to this package's `require`;
> otherwise leave it out. Add the standard dev block (as in Task 1 Step 2 note) after this.

- [ ] **Step 2: Move source and tests**

```bash
cd packages
git mv core/src/Parser document/src/Parser
git mv core/src/Resolver document/src/Resolver
git mv core/src/Normalizer document/src/Normalizer
git mv core/src/Validator document/src/Validator
git mv core/tests/Parser document/tests/Parser 2>/dev/null || true
git mv core/tests/Resolver document/tests/Resolver 2>/dev/null || true
git mv core/tests/Normalizer document/tests/Normalizer 2>/dev/null || true
git mv core/tests/Validator document/tests/Validator 2>/dev/null || true
```

- [ ] **Step 3: Wire the monorepo**

Root + core + laravel composer: add `alama/arazzo-document` path repo and `@dev` require (mirror Task 8 Step 3).

- [ ] **Step 4: Add arch test**

`packages/document/tests/ArchTest.php` — document must not depend on `Execution`/`Console`.

- [ ] **Step 5: Regenerate autoload + run**

```bash
composer update alama/arazzo-document --with-all-dependencies
composer dump-autoload
composer run test-core
```

Expected: PASS.

- [ ] **Step 6: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "build: extract alama/arazzo-document package"
```

---

## Task 10: Create the runner package

**Files:**

- Create: `packages/runner/composer.json`
- Move: `packages/core/src/Execution/`, `State/`, `Events/`, `Policy/`, `Jobs/`, `Protocol/`, `Async/`, `Telemetry/`,
  `Infrastructure/`, `Dependency/` → `packages/runner/src/`
- Move: matching test trees
- Create: `packages/runner/tests/ArchTest.php`
- Modify: root/core/laravel composer, `scripts/generate-docs.php`

**Interfaces:**

- Consumes: contracts + expression + document.
- Produces: `alama/arazzo-runner`, PSR-4 `Alama\Arazzo\` → `src/`, covering the runtime modules.

- [ ] **Step 1: Scaffold package**

```bash
mkdir -p packages/runner/src packages/runner/tests
cat > packages/runner/composer.json <<'EOF'
{
    "name": "alama/arazzo-runner",
    "description": "Arazzo workflow execution engine: state, events, policy, protocol, async, telemetry.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-document": "@dev",
        "guzzlehttp/guzzle": "^7.9",
        "open-telemetry/api": "^1.0",
        "open-telemetry/sdk": "^1.0",
        "open-telemetry/exporter-otlp": "^1.0",
        "psr/event-dispatcher": "^1.0",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.0||^2.0",
        "psr/log": "^3.0",
        "psr/simple-cache": "^3.0"
    },
    "autoload": { "psr-4": { "Alama\\Arazzo\\": "src/" } },
    "autoload-dev": { "psr-4": { "Alama\\Arazzo\\Tests\\": "tests/" } },
    "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true, "phpstan/extension-installer": true } },
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF
```

> Move the runtime-specific deps from `packages/core/composer.json` (guzzle, open-telemetry, psr http/event,
> psr/simple-cache) into this package's `require`. Mirror actual versions from core. `symfony/console` stays in the cli
> package (Task 11), symfony/yaml + softcreatr/jsonpath + cebe stay in document (Task 8). Add the standard dev block (
> Task
> 1 Step 2 note) after this.

- [ ] **Step 2: Move source and tests**

```bash
cd packages
for m in Execution State Events Policy Jobs Protocol Async Telemetry Infrastructure Dependency; do
  git mv core/src/$m runner/src/$m
  git mv core/tests/$m runner/tests/$m 2>/dev/null || true
done
```

- [ ] **Step 3: Wire the monorepo**

Root + core + laravel composer: add `alama/arazzo-runner` path repo + `@dev` require (mirror Task 8 Step 3). Note:
`packages/core` may now be empty of runtime code — decide whether core is removed entirely (Task 12).

- [ ] **Step 4: Add arch test**

`packages/runner/tests/ArchTest.php` — runner must not depend on `Console`.

- [ ] **Step 5: Regenerate autoload + run**

```bash
composer update alama/arazzo-runner --with-all-dependencies
composer dump-autoload
composer run test-core
composer run test-laravel
```

Expected: PASS. Laravel adapters (`Database*Registry`, `LaravelQueueDriver`, `RedisHotStateStore`, etc.) now depend on
runner contracts — verify their `use` statements resolve.

- [ ] **Step 6: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "build: extract alama/arazzo-runner package"
```

---

## Task 11: Create the cli package

**Files:**

- Create: `packages/cli/composer.json`
- Move: `packages/core/src/Console/`, `Generator/`, `Renderer/` → `packages/cli/src/`
- Move: matching test trees
- Create: `packages/cli/tests/ArchTest.php`
- Modify: root/core/laravel composer, `scripts/generate-docs.php`

**Interfaces:**

- Consumes: contracts + expression + document + runner.
- Produces: `alama/arazzo-cli`, PSR-4 `Alama\Arazzo\` → `src/`, covering `Console/`, `Generator/`, `Renderer/`.

- [ ] **Step 1: Scaffold package**

```bash
mkdir -p packages/cli/src packages/cli/tests
cat > packages/cli/composer.json <<'EOF'
{
    "name": "alama/arazzo-cli",
    "description": "Arazzo CLI and delivery adapters: console, AI generator, renderer.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-runner": "@dev",
        "symfony/console": "^7.0"
    },
    "autoload": { "psr-4": { "Alama\\Arazzo\\": "src/" } },
    "autoload-dev": { "psr-4": { "Alama\\Arazzo\\Tests\\": "tests/" } },
    "config": { "sort-packages": true, "allow-plugins": { "pestphp/pest-plugin": true, "phpstan/extension-installer": true } },
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF
```

> `Console` uses symfony/console (`^7.0` in core) and guzzle (`^7.9`) and OpenTelemetry (1 stray ref — ideally removed
> in Task 7, otherwise add the telemetry packages here). `Generator` needs `psr/http-client`, `psr/http-factory`. Add
> the
> standard dev block (Task 1 Step 2 note) after this.

- [ ] **Step 2: Move source and tests**

```bash
cd packages
git mv core/src/Console cli/src/Console
git mv core/src/Generator cli/src/Generator
git mv core/src/Renderer cli/src/Renderer
git mv core/tests/Console cli/tests/Console 2>/dev/null || true
git mv core/tests/Generator cli/tests/Generator 2>/dev/null || true
git mv core/tests/Renderer cli/tests/Renderer 2>/dev/null || true
```

- [ ] **Step 3: Wire the monorepo**

Root + core + laravel composer: add `alama/arazzo-cli` path repo + `@dev` require (mirror Task 8 Step 3).

- [ ] **Step 4: Add arch test**

`packages/cli/tests/ArchTest.php` — cli may depend on all lower packages but nothing higher.

- [ ] **Step 5: Regenerate autoload + run**

```bash
composer update alama/arazzo-cli --with-all-dependencies
composer dump-autoload
composer run test-core
composer run test-laravel
```

Expected: PASS.

- [ ] **Step 6: Regenerate docs and commit**

```bash
php scripts/generate-docs.php
git add -A
git commit -m "build: extract alama/arazzo-cli package"
```

---

## Task 12: Retire packages/core and finalize the tree

**Files:**

- Remove: `packages/core` (now empty of source)
- Modify: root `composer.json` (drop core path repo + require), `monorepo-builder.php`, `scripts/generate-docs.php`
- Run: full gates

**Interfaces:**

- Consumes: all packages from Tasks 1–11.
- Produces: a monorepo with exactly `contracts`, `expression`, `document`, `runner`, `cli`, `laravel`.

- [ ] **Step 1: Confirm core is empty of runtime source**

```bash
find packages/core/src -name '*.php' | wc -l
find packages/core/tests -name '*.php' | wc -l
```

If any source/tests remain, either re-home them to the correct package or note them. If everything moved, proceed.

- [ ] **Step 2: Remove core from the monorepo**

Edit root `composer.json`: remove the `packages/core` path repo and `"alama/arazzo-core": "@dev"` from `require`. Edit
`monorepo-builder.php` if it references core explicitly (it uses `packageDirectories(['packages'])`, so no change needed
unless a dependency list hardcodes core).

- [ ] **Step 3: Update generate-docs scan list**

Edit `scripts/generate-docs.php:94` — replace the single core scan with scans for each new package:

```php
$core = NamespaceGraphDoc\merge(
    Scanner::scan($root.'/packages/contracts/src', 'Alama\\Arazzo\\'),
    Scanner::scan($root.'/packages/expression/src', 'Alama\\Arazzo\\'),
    Scanner::scan($root.'/packages/document/src', 'Alama\\Arazzo\\'),
    Scanner::scan($root.'/packages/runner/src', 'Alama\\Arazzo\\'),
    Scanner::scan($root.'/packages/cli/src', 'Alama\\Arazzo\\'),
);
```

> The `Scanner::scan` groups by first directory under `src`, so scanning each package root individually and merging is
> consistent with how the tool already treats core + laravel.

- [ ] **Step 4: Update LAYER_ORDER in LayeringDoc.php**

Edit `scripts/generate-docs/LayeringDoc.php` `LAYER_ORDER` to reflect package ordering:
`Contracts < Expression < Document < Runner < Cli` (with Laravel on top).

- [ ] **Step 5: Update ArchTest expectations**

Remove/replace `packages/core/tests/ArchTest.php` (if core removed); ensure each surviving package has its arch test (
Tasks 6, 8–11).

- [ ] **Step 6: Run the full monorepo gates**

```bash
composer install
composer run test
make verify
```

Expected: 5/5 gates green (pint, phpstan core+laravel, pest core+laravel).

- [ ] **Step 7: Regenerate docs**

```bash
php scripts/generate-docs.php
```

Verify `namespace-graph.md`, `layering.md`, `coupling-metrics.md`, `boundaries-audit.md`, `extension-points.md`,
`subdomain-map.md` reflect the new package topology.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "build: retire packages/core; wire 6-package monorepo"
```

---

## Task 13: Documentation, glossary, and cleanup

**Files:**

- Create: `CONTEXT-MAP.md` at repo root
- Modify: `packages/core/CONTEXT.md` (or relocate to `packages/contracts/CONTEXT.md`)
- Modify: `docs/superpowers/plans/2026-08-25-runner-modularization-plan.md` (mark superseded if applicable)
- Modify: `CHANGELOG.md`
- Modify: `scripts/generate-docs/SubdomainMapDoc.php` (classify unclassified modules)

**Interfaces:**

- Consumes: the final package tree from Tasks 1–12.
- Produces: documentation artifacts per the domain-modeling skill (glossary, CONTEXT-MAP, ADR for the split).

- [ ] **Step 1: Create CONTEXT-MAP.md**

At repo root, create `CONTEXT-MAP.md` listing each bounded context (package) → where it lives:

```md
# Context Map

| Context (package) | Code root | Notes |
|---|---|---|
| contracts | `packages/contracts/src` | Domain model, shared contracts, state DTOs |
| expression | `packages/expression/src` | Expression engine, criteria evaluation |
| document | `packages/document/src` | Load, resolve, normalize, validate |
| runner | `packages/runner/src` | Workflow execution engine |
| cli | `packages/cli/src` | Console / delivery adapters |
| laravel | `packages/laravel/src` | Framework adapters |
```

- [ ] **Step 2: Reconcile the glossary**

Edit `packages/core/CONTEXT.md` (relocate to `packages/contracts/CONTEXT.md` if core is removed). Address the synonym
clusters from `docs/generated/ubiquitous-language-audit.md`:

- `run` vs `execute` vs `invoke` — pick canonical per package and note it.
- `parse` vs `load` vs `decode` — Parser is `parse`, Loader is `load`, Decoder is `decode`; document the distinction so
  future names don't blur them.
- `fetch` vs `resolve` — Resolver uses `resolve`; Fetcher uses `fetch`; document.

- [ ] **Step 3: Classify the unclassified subdomains**

Edit `SubdomainMapDoc.php` `SUBDOMAINS` to classify the 10 previously-unclassified modules (from
`docs/generated/subdomain-map.md`): classify `Execution`, `Protocol`, `Async`, `Dependency`, `Policy` as **core domain
** (the runner is the product); `Normalizer` as **supporting**; `Telemetry`, `Infrastructure`, `Jobs` as **generic**.
Regenerate docs.

- [ ] **Step 4: Write the split ADR**

Create `docs/adr/0001-core-package-split.md` capturing the decision: coarse internal packages, stable `Alama\Arazzo\`
namespace, violations-fixed-first sequencing, and the contracts-first dependency-inversion approach. (This meets all
three ADR criteria: hard to reverse, surprising without context, a real trade-off.)

- [ ] **Step 5: Update CHANGELOG + superseded plan**

Add a changelog entry for the package split. Mark the old `2026-08-25-runner-modularization-plan.md` as superseded by
this plan (or note its remaining tasks fold into the runner package).

- [ ] **Step 6: Final verification + commit**

```bash
composer run test
make verify
php scripts/generate-docs.php
git add -A
git commit -m "docs: CONTEXT-MAP, glossary reconciliation, ADR for package split"
```

---

## Dependencies

```
Task 1 (contracts package) ─┐
Task 2 (shared contracts) ──┼──► Task 5 (entry-point facades) ──► Task 7 (framework boundaries)
Task 3 (state DTOs) ───────┤         │
Task 4 (remaining violations)┘         │
        │                              │
        ▼                              ▼
Task 6 (arch tests) ◄────── facades define cross-package seams
        │
        ▼
Task 8 (expression) ─► Task 9 (document) ─► Task 10 (runner) ─► Task 11 (cli) ─► Task 12 (retire core) ─► Task 13 (docs)
        └──────────────────────────┴─────────────────────────┘
```

Tasks 1–4 are prerequisites (they make the DAG acyclic). Task 5 (entry-point interfaces + facades) depends on 2–3 and
must come before the package-creation tasks so downstream packages wire to the seams. Task 6 (arch tests) defines the
boundary contract and is folded into each package-creation task + enforced per package. Task 7 (framework boundaries) is
independent of package moves and can run anywhere after Task 1. Tasks 8–11 create packages sequentially (each depends on
earlier ones via path repos). Task 12 depends on all of 1–11. Task 13 depends on 12.

---

## Rollback

- Each task commits independently with green gates, so any single task can be reverted without affecting the rest.
- `git mv` preserves history; re-running reverse moves restores the single-package layout.
- The shared `Alama\Arazzo\` namespace means no public-API rename was performed — reverting a package split is purely a
  directory + composer.json change.

---

## Notes / Risks

- **Contracts package name** (`arazzo-contracts` vs `arazzo-spec`/`arazzo-domain`): recommended `arazzo-contracts`
  because it holds Spec DTOs **plus** shared ports and state DTOs. Rename is a one-line composer/name change early;
  decide before Task 8.
- **`Console`/`Symfony` and `Telemetry`/`OTel`** are deliberate exceptions in Task 7 — make the POLICY decision
  consciously.
- **Facade vs contract (the seam)**: the per-package **interface** is the seam other packages depend on; the **Facade**
  is the concrete implementation behind it. Never let one package depend on another package's concrete Facade — always
  on its `*Interface`. Static Laravel-style `Facades\X` proxies belong **only** in `packages/laravel`, never in core (
  core must stay framework-free; `ArchTest` forbids `Illuminate` in core).
- **`State\ExecutionContext` vs `Execution\ExecutionState`/`WorkflowContext` overlap** (the aggregate-map flags them):
  Tasks 3 promotes the DTOs; the plan does **not** unify `State\ExecutionContext` with `ExecutionState` (that is the
  FLATTEN-RUNNER concern, intentionally deferred). Watch for compile-time method-arity drift only — don't fold the two
  models here.
- **monorepo-builder with internal packages**: Symplify treats packages as publishable by default. For internal-only
  packages, confirm no `release` stage accidentally tags/publishes them; if needed, exclude internal packages from the
  MB `release` worker (add to `monorepo-builder.php` config).
- **Composer path repo + PSR-4 overlap**: multiple packages mapping `Alama\Arazzo\` to their own `src/` is fine because
  the subpaths are disjoint (`Spec/`, `Expression/`, `Parser/`, `Execution/`, `Console/`). `composer dump-autoload`
  builds one merged autoloader.
- **Empty `packages/core`** after Tasks 8–11: if any residual tests or support code remains, re-home it before Task 12,
  or retain `packages/core` as a thin facade package that re-exports (`replace`) the new packages to avoid breakage for
  existing internal consumers.

---

## Addendum: Facade pattern for package boundaries

**Decision (from design discussion):** each core package gets one high-level **entry-point interface** (the seam) and
one **thin concrete Facade** that implements it and hides the package's internals. This is the GoF Facade pattern used
for package-to-package communication — not a Laravel static facade.

**Shape per package:**

| Package      | Seam (interface)                 | Facade (concrete)                            | Hides                                                        |
|--------------|----------------------------------|----------------------------------------------|--------------------------------------------------------------|
| `contracts`  | (none needed — pure DTOs/ports)  | —                                            | —                                                            |
| `expression` | `ExpressionEngineInterface`      | `ExpressionEngine`                           | `ExpressionEvaluator`, `XpathEvaluator`, AST                 |
| `document`   | `DocumentInterface`              | `Document`                                   | `Parser`, `Resolver`, `Normalizer`, `Validator`              |
| `runner`     | `RunnerInterface`                | `Runner`                                     | `WorkflowEngine`, `WorkflowExecutor`, state, protocol, async |
| `cli`        | (already an `Application` entry) | `Application`                                | console commands                                             |
| `laravel`    | (framework adapters)             | `Facades\*` static proxies allowed here only | DI container wiring                                          |

**Rules enforced by arch tests (Task 6):**

1. A package may reference another package's **entry-point interface only** — not its internal classes and not its
   concrete Facade.
2. The concrete Facade lives inside its own package, next to the modules it composes (containment).
3. Cross-package edges in `namespace-graph.md` must point only at `*Interface`/Facade entry points, never at 10+
   internal classes.
4. `laravel` may add static `Facades\X` proxies as framework convenience, but core packages must not.

This gives each package **depth** (small interface over large behaviour), a **clean seam** (mockable interface), and *
*bounded coupling** (only entry points cross packages) — matching the deep-module design principles.
