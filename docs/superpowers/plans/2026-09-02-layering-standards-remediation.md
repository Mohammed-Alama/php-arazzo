# Layering Standards Remediation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the Expression→Evaluation hard violation and 4 judgement-call smells identified in the PR #50 standards review.

**Architecture (Option A — shared kernel):** Put a narrow read-only `WorkflowContextInterface` in the Shared Kernel (`Spec\Interfaces`, layer 0). `Execution\Data\WorkflowContext` implements it, and `Evaluation\Data\EvaluationContext` accepts it. Expression's resolvers type-hint a thin Expression-owned `EvaluationInputInterface` (which exposes `WorkflowContextInterface`) — so Expression depends only on interfaces it owns plus a Shared Kernel contract, and never on the concrete `Execution\Data\WorkflowContext` or the concrete `Evaluation\Data\EvaluationContext`. This eliminates the Expression→Evaluation upward edge (and the broader Expression→Execution class) at the root, because every module resolves through a contract instead of reaching into a sibling Vertical's concrete Data.

**Tech Stack:** PHP 8.4, PHPStan, Pint

**Spec:** `docs/superpowers/plans/2026-08-30-layering-architecture-implementation.md` (original layering plan, already applied)

**Updated:** Commits `cc32c1e` (EvaluationContext → Evaluation\Data) and earlier moved `WorkflowContext` to `Execution\Data`, which *created* the Expression→Evaluation and Expression→Execution violations being fixed here.

---

## Global Constraints

- PSR-12 coding standard (enforced by Pint)
- PHPStan level: existing baseline must not grow
- All existing tests must continue passing
- No BC breaks to public APIs

---

### Task 1: Extract WorkflowContextInterface into Spec (fix Expression→Evaluation + Expression→Execution)

**Files:**
- Create: `packages/core/src/Spec/Interfaces/WorkflowContextInterface.php`
- Modify: `packages/core/src/Execution/Data/WorkflowContext.php` (add `implements`)
- Modify: `packages/core/src/Expression/ExpressionEvaluator.php`
- Modify: `packages/core/src/Expression/SelectorEvaluator.php`
- Modify: `packages/core/src/Expression/Interfaces/ExpressionEvaluatorInterface.php`

**Interfaces:**
- Consumes: (none — first task)
- Produces: `Spec\Interfaces\WorkflowContextInterface` — the read-only projection of execution state that Expression's resolvers need.

**Why this coupling (DDD rationale):** In DDD terms, the Expression module is a low-level consumer that needs a *read-only projection* of runtime state. It must not reach into `Evaluation\Data` (a sibling Vertical) or `Execution\Data` (its concrete host). A narrow contract in the Shared Kernel (`Spec\Interfaces`, layer 0 — below every Vertical) lets Expression depend upward on an abstraction both `Execution\Data\WorkflowContext` and `Evaluation\Data\EvaluationContext` implement. This is the Dependency Inversion Principle applied at the module boundary: the implementer (`Execution`/`Evaluation`) depends on the interface, and the dependent (`Expression`) depends on the same abstraction.

- [ ] **Step 1: Derive the interface from Expression's actual usage**

Expression's resolvers only READ this state (from `ExpressionEvaluator.php` and `SelectorEvaluator.php`):

| Access | Used by | 
|---|---|
| `getInputs(): array` | inputs resolution |
| `getSteps(): array` | step/response/request/body resolution |
| `getComponents(): array` | component refs |
| `getWorkflows(): array` | sub-workflow data |
| `getStepStatus(string): ?StepStatus` | status lookups |
| `getWorkflowId(): ?string` | location enrichment |

Create `packages/core/src/Spec/Interfaces/WorkflowContextInterface.php` with only the READ methods Expression calls:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Interfaces;

use Alama\Arazzo\Spec\Enum\StepStatus;

interface WorkflowContextInterface
{
    /** @return array<string, mixed> */
    public function getInputs(): array;

    /** @return array<string, mixed> */
    public function getSteps(): array;

    /** @return array<string, mixed> */
    public function getComponents(): array;

    /** @return array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}> */
    public function getWorkflows(): array;

    public function getStepStatus(string $stepId): ?StepStatus;

    public function getWorkflowId(): ?string;
}
```

> **Note:** Deliberately leaves out write methods (`with*`), persistence (`toArray`/`fromPersisted`/`reconciled`), builder statics (`forChildInvocation`), and budget/call-stack accessors — those are Execution-domain concerns, not what a resolver needs. Read-only projection = narrow contract = less surface to leak.

- [ ] **Step 2: Add `implements` to Execution\Data\WorkflowContext**

In `packages/core/src/Execution/Data/WorkflowContext.php`, add the import and implement the interface:

```php
// Before (line 10):
final readonly class WorkflowContext

// After:
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

final readonly class WorkflowContext implements WorkflowContextInterface
```

All six methods (`getInputs`, `getSteps`, `getComponents`, `getWorkflows`, `getStepStatus`, `getWorkflowId`) already exist on the class — confirm signatures match the interface (they do per lines 197–323 of the current file). No further body changes needed.

- [ ] **Step 3: Re-point ExpressionEvaluator to the interface**

In `packages/core/src/Expression/ExpressionEvaluator.php`, change line 7 and 27/34:

```php
// Before:
use Alama\Arazzo\Evaluation\Data\EvaluationContext;
...
public function evaluate(Expression $expression, EvaluationContext $context): mixed
...
private function evaluateAst(ExpressionAst $ast, EvaluationContext $context): mixed

// After:
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface; // (newly added below)
...
public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
...
private function evaluateAst(ExpressionAst $ast, EvaluationInputInterface $context): mixed
```

Replace every `$context->workflowContext->X` call inside the body with `$context->X` (i.e., `getInputs`, `getSteps`, `getComponents`, `getWorkflows`), and keep `$context->currentStepId` / `$context->document` reads.

> **Design decision:** Rather than forcing Expression to take three loose params (`WorkflowContextInterface`, `?currentStepId`, `?document`) — which would touch a dozen Protocol/Execution callers — introduce ONE thin input contract in `Expression\Interfaces` (the consumer-owned envelope). This keeps caller signatures stable and lets Expression depend only on interfaces it owns + the Shared Kernel contract. This is the pragmatic Option A hybrid: the *state* contract lives in the Shared Kernel; the *envelope* it is passed through is Expression-owned.

- [ ] **Step 3b: Create Expression\Interfaces\EvaluationInputInterface**

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

interface EvaluationInputInterface
{
    public function getWorkflowContext(): WorkflowContextInterface;

    public function getCurrentStepId(): ?string;

    public function getDocument(): ?ArazzoDocument;
}
```

- [ ] **Step 4: Make Evaluation\Data\EvaluationContext implement it**

In `packages/core/src/Evaluation/Data/EvaluationContext.php`, add:

```php
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

final readonly class EvaluationContext implements EvaluationInputInterface
{
    public function __construct(
        public WorkflowContextInterface $workflowContext,   // was concrete WorkflowContext
        public ?string $currentStepId = null,
        public ?ArazzoDocument $document = null,
    ) {}

    public function getWorkflowContext(): WorkflowContextInterface { return $this->workflowContext; }
    public function getCurrentStepId(): ?string { return $this->currentStepId; }
    public function getDocument(): ?ArazzoDocument { return $this->document; }
}
```

Type-hinting the constructor on `WorkflowContextInterface` (instead of concrete `WorkflowContext`) means any caller can pass any implementation — and the concrete `WorkflowContext` continues to satisfy it after Step 2.

- [ ] **Step 5: Re-point SelectorEvaluator**

In `packages/core/src/Expression/SelectorEvaluator.php`, change:
- Line 7: `use Alama\Arazzo\Evaluation\Data\EvaluationContext;` → `use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;`
- Line 8: `use Alama\Arazzo\Execution\Data\WorkflowContext;` → `use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;`
- Update `evaluate(Selector $sel, WorkflowContextInterface $wf, string $stepId)` and the internal `new EvaluationContext($wf, $stepId)` → `new EvaluationContext($wf, $stepId)` still works since the constructor now accepts the interface.

- [ ] **Step 6: Re-point ExpressionEvaluatorInterface**

In `packages/core/src/Expression/Interfaces/ExpressionEvaluatorInterface.php`, change line 7:

```php
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
...
public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;
```

- [ ] **Step 7: Run static analysis**

```bash
composer analyse
```

Expected: PASS. Note: `ExpressionEvaluatorInterface` is implemented by other classes (e.g. `Evaluation\ExpressionResolver` forwards to it, but does not implement it — verify with `rg "implements ExpressionEvaluatorInterface"`). Any implementer must type-hint `EvaluationInputInterface` in `evaluate()`.

- [ ] **Step 8: Run tests**

```bash
composer run test-core
```

Expected: PASS (all callers pass `EvaluationContext`, which now implements the interface).

- [x] **Step 9: Scope note — Expression→Execution left for follow-on**

After the above, `Evaluation\Data\EvaluationContext` remains the shared envelope for the three inputs. Do NOT delete it. The `Expression → Evaluation` edge is resolved. However, `Expression → Execution` (weight 2) and `Expression → Validator` (weight 1) remain: they come from `Expression\Interfaces\ExpressionResolverInterface` and `StringInterpolator` still type-hinting the concrete `Execution\Data\WorkflowContext` (and `ExpressionResolverInterface` importing `Validator\Exceptions\SchemaValidationException`). Re-pointing those triggers a signature cascade into the implementer `Evaluation\ExpressionResolver` and its forwarded collaborators (`OutputExtractorInterface`, `CriteriaEvaluatorInterface`, `ResponseValidatorInterface`), which in turn type-hint concrete `WorkflowContext` — a larger cross-module change. Marked as **Task 1.5 (follow-on)**, not part of the initial Expression→Evaluation fix.

- [x] **Step 10: Commit**

Committed the Expression→Evaluation fix:

```bash
git add packages/core/src/Spec/Interfaces/WorkflowContextInterface.php packages/core/src/Expression/Data/EvaluationInput.php packages/core/src/Expression/Interfaces/EvaluationInputInterface.php packages/core/src/Expression/Interfaces/ExpressionEvaluatorInterface.php packages/core/src/Expression/ExpressionEvaluator.php packages/core/src/Expression/SelectorEvaluator.php packages/core/src/Evaluation/Data/EvaluationContext.php packages/core/src/Execution/Data/WorkflowContext.php
git commit -m "refactor(expression): extract WorkflowContextInterface shared kernel to fix Expression→Evaluation layering"
```

---

### Task 1.5 (follow-on, not started): Re-point ExpressionResolverInterface & StringInterpolator to WorkflowContextInterface

Files:
- `packages/core/src/Expression/Interfaces/ExpressionResolverInterface.php` (lines 7, 16, 21, 23, 28)
- `packages/core/src/Expression/StringInterpolator.php` (lines 7, 15)
- `packages/core/src/Evaluation/ExpressionResolver.php` (lines 9, 27, 32, 37, 42) — implementer
- Downstream collaborators that already type-hint concrete `WorkflowContext` and would need the same treatment: `OutputExtractorInterface`, `CriteriaEvaluatorInterface`, `ResponseValidatorInterface`

Result: eliminates the remaining `Expression → Execution` (2) and `Expression → Validator` (1) edges.

---

### Task 2: Deduplicate ExpressionParser instantiation in validator rules

**Files:**
- Modify: `packages/core/src/Validator/Support/ExpressionSite.php` (add `parseAst()` helper)
- Modify: `packages/core/src/Validator/Rules/ExpressionSyntaxRule.php:20`
- Modify: `packages/core/src/Validator/Rules/ExpressionJsonPointerSyntaxRule.php:23`
- Modify: `packages/core/src/Validator/Rules/ExpressionContextMisuseRule.php:26`
- Modify: `packages/core/src/Validator/Rules/ExpressionUnresolvedComponentRefRule.php:21`
- Modify: `packages/core/src/Validator/Rules/ExpressionUnresolvedInputRefRule.php:21`
- Modify: `packages/core/src/Validator/Rules/ExpressionUnresolvedSourceRefRule.php:21`
- Modify: `packages/core/src/Validator/Rules/ExpressionUnresolvedStepRefRule.php:23`
- Modify: `packages/core/src/Validator/Rules/ExpressionUnresolvedWorkflowRefRule.php:21`

**Interfaces:**
- Consumes: `Expression\Parser` (already exists)
- Produces: `ExpressionSite::parseAst()` returning `ExpressionAst|ExpressionSyntaxException`

- [ ] **Step 1: Add parseAst() to ExpressionSite**

In `packages/core/src/Validator/Support/ExpressionSite.php`, add after the constructor:

```php
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

// ... inside the class, after the constructor:

    public function parseAst(): ExpressionAst|ExpressionSyntaxException
    {
        return (new ExpressionParser())->parseOrError($this->expression->raw);
    }
```

- [ ] **Step 2: Update ExpressionSyntaxRule**

In `packages/core/src/Validator/Rules/ExpressionSyntaxRule.php`, replace:

```php
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = (new ExpressionParser())->parseOrError($site->expression->raw);
```

With:

```php
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->parseAst();
```

- [ ] **Step 3: Update the remaining 6 rules identically**

For each of these files, replace the same pattern:

```php
// Before (in each file):
$ast = (new ExpressionParser())->parseOrError($site->expression->raw);

// After:
$ast = $site->parseAst();
```

And remove the now-unused `use Alama\Arazzo\Expression\Parser as ExpressionParser;` import from each file.

Files to update:
- `ExpressionJsonPointerSyntaxRule.php`
- `ExpressionContextMisuseRule.php`
- `ExpressionUnresolvedComponentRefRule.php`
- `ExpressionUnresolvedInputRefRule.php`
- `ExpressionUnresolvedSourceRefRule.php`
- `ExpressionUnresolvedStepRefRule.php`
- `ExpressionUnresolvedWorkflowRefRule.php`

- [ ] **Step 4: Run static analysis**

```bash
composer analyse
```

Expected: PASS

- [ ] **Step 5: Run tests**

```bash
composer run test-core
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Validator/Support/ExpressionSite.php packages/core/src/Validator/Rules/Expression*.php
git commit -m "refactor: deduplicate ExpressionParser instantiation via ExpressionSite::parseAst()"
```

---

### Task 3: Rename LedgerEventListener to LedgerAppendingListener

**Files:**
- Rename: `packages/core/src/Events/Listener/LedgerEventListener.php` -> `LedgerAppendingListener.php`
- Modify: `packages/core/src/Events/Listener/LedgerAppendingListener.php` (class name)
- Grep for and update all references

**Interfaces:**
- Consumes: `Events\Interfaces\EventLedgerInterface` (unchanged)
- Produces: `Events\Listener\LedgerAppendingListener` (clearer name)

- [ ] **Step 1: Find all references**

```bash
rg "LedgerEventListener" packages/ --include "*.php" -l
```

- [ ] **Step 2: Rename class and file**

Rename the file:

```bash
mv packages/core/src/Events/Listener/LedgerEventListener.php packages/core/src/Events/Listener/LedgerAppendingListener.php
```

In the renamed file, change the class name:

```php
// Before:
final class LedgerEventListener

// After:
final class LedgerAppendingListener
```

- [ ] **Step 3: Update all references**

Search for every `use` statement and instantiation referencing `LedgerEventListener` and update to `LedgerAppendingListener`.

- [ ] **Step 4: Run static analysis**

```bash
composer analyse
```

Expected: PASS

- [ ] **Step 5: Run tests**

```bash
composer run test-core
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Events/Listener/
git commit -m "refactor: rename LedgerEventListener to LedgerAppendingListener for specificity"
```

---

### Task 4: Add OpenApiPayload::withAutoDispatched() to reduce feature envy

**Files:**
- Modify: `packages/core/src/Spec/OpenApiPayload.php` (add `withAutoDispatched()`)
- Modify: `packages/core/src/Execution/DefaultOpenApiExecutor.php:49-64`

**Interfaces:**
- Consumes: `Spec\OpenApiPayload` (existing)
- Produces: `OpenApiPayload::withAuto(array $path, array $query, array $header, array $cookie): self`

- [ ] **Step 1: Add withAuto() to OpenApiPayload**

In `packages/core/src/Spec/OpenApiPayload.php`, add before the closing brace:

```php
    /**
     * Returns a new payload with auto-dispatched parameters distributed
     * into the correct parameter bags by the operation's normalized spec.
     *
     * @param  array<string, mixed>  $path
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $cookie
     */
    public function withAuto(array $path, array $query, array $header, array $cookie): self
    {
        return new self(
            path: $path,
            query: $query,
            header: $header,
            cookie: $cookie,
            auto: $this->auto,
            body: $this->body,
            bodyMediaType: $this->bodyMediaType,
        );
    }
```

- [ ] **Step 2: Update DefaultOpenApiExecutor to use withAuto()**

In `packages/core/src/Execution/DefaultOpenApiExecutor.php`, replace lines 49-64:

```php
// Before:
        $path = $payload->path;
        $query = $payload->query;
        $header = $payload->header;
        $cookie = $payload->cookie;

        foreach ($payload->auto as $name => $value) {
            if (isset($operation->normalized->pathParameters[$name])) {
                $path[$name] = $value;
            } elseif (isset($operation->normalized->headerParameters[$name])) {
                $header[$name] = $value;
            } elseif (isset($operation->normalized->cookieParameters[$name])) {
                $cookie[$name] = $value;
            } else {
                $query[$name] = $value;
            }
        }
```

With:

```php
// After:
        $path = $payload->path;
        $query = $payload->query;
        $header = $payload->header;
        $cookie = $payload->cookie;

        foreach ($payload->auto as $name => $value) {
            if (isset($operation->normalized->pathParameters[$name])) {
                $path[$name] = $value;
            } elseif (isset($operation->normalized->headerParameters[$name])) {
                $header[$name] = $value;
            } elseif (isset($operation->normalized->cookieParameters[$name])) {
                $cookie[$name] = $value;
            } else {
                $query[$name] = $value;
            }
        }

        $payload = $payload->withAuto($path, $query, $header, $cookie);
```

Wait — the purpose was to *reduce* the destructuring. The better approach: move the auto-dispatch logic into `withAuto()` itself so the executor doesn't need to know about parameter bags:

**Revised Step 1: Add withAuto() with operation-aware dispatch**

In `packages/core/src/Spec/OpenApiPayload.php`, add:

```php
    /**
     * Returns a new payload with auto-dispatched parameters distributed
     * into the correct parameter bags based on the operation's normalized spec.
     *
     * @param  array<string, array<string, mixed>>  $pathParameters
     * @param  array<string, array<string, mixed>>  $headerParameters
     * @param  array<string, array<string, mixed>>  $cookieParameters
     */
    public function withAutoDispatched(
        array $pathParameters,
        array $headerParameters,
        array $cookieParameters,
    ): self {
        $path = $this->path;
        $query = $this->query;
        $header = $this->header;
        $cookie = $this->cookie;

        foreach ($this->auto as $name => $value) {
            if (isset($pathParameters[$name])) {
                $path[$name] = $value;
            } elseif (isset($headerParameters[$name])) {
                $header[$name] = $value;
            } elseif (isset($cookieParameters[$name])) {
                $cookie[$name] = $value;
            } else {
                $query[$name] = $value;
            }
        }

        return new self(
            path: $path,
            query: $query,
            header: $header,
            cookie: $cookie,
            auto: $this->auto,
            body: $this->body,
            bodyMediaType: $this->bodyMediaType,
        );
    }
```

**Revised Step 2: Update DefaultOpenApiExecutor**

Replace lines 49-64 with:

```php
        $payload = $payload->withAutoDispatched(
            $operation->normalized->pathParameters,
            $operation->normalized->headerParameters,
            $operation->normalized->cookieParameters,
        );

        $path = $payload->path;
        $query = $payload->query;
        $header = $payload->header;
        $cookie = $payload->cookie;
```

This eliminates the manual loop in the executor — the payload knows how to dispatch its own auto parameters.

- [ ] **Step 3: Run static analysis**

```bash
composer analyse
```

Expected: PASS

- [ ] **Step 4: Run tests**

```bash
composer run test-core
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Spec/OpenApiPayload.php packages/core/src/Execution/DefaultOpenApiExecutor.php
git commit -m "refactor: move auto-dispatch into OpenApiPayload::withAutoDispatched() to reduce feature envy"
```

---

### Task 5: Fix PSR-12 use statement formatting in README

**Files:**
- Modify: `packages/core/README.md` (use statement formatting)

**Interfaces:**
- Consumes: (none)
- Produces: PSR-12 compliant code samples in README

- [ ] **Step 1: Identify and fix use statement groups**

In `packages/core/README.md`, locate all `use` statement blocks in code samples and add blank lines between PSR-12 groups (classes, functions, constants). Each group of related use statements (same vendor prefix or logical grouping) should have a blank line separating it from the next group.

- [ ] **Step 2: Verify formatting**

Read the README and confirm all code samples follow PSR-12 use statement grouping.

- [ ] **Step 3: Commit**

```bash
git add packages/core/README.md
git commit -m "style: fix PSR-12 use statement formatting in README code samples"
```

---

### Task 6: Separate ecosystem triage changes from layering refactor

**Files:**
- Review: `scripts/ecosystem/Normalizer.php`
- Review: `scripts/ecosystem/RelevanceMapper.php`

**Interfaces:**
- Consumes: (none — this is a git hygiene task)
- Produces: Clean separation of concerns in version control

- [ ] **Step 1: Identify ecosystem triage commits**

```bash
git log --oneline --all | grep -i "ecosystem\|triage\|relevance\|tag"
```

- [ ] **Step 2: Verify these are not mixed with layering commits**

Check if ecosystem triage changes are interleaved with layering namespace changes in the same commits. If so, they should be in a separate branch/PR.

- [ ] **Step 3: If mixed, document for follow-up**

If ecosystem changes are mixed into layering commits, note this as a follow-up item: the next PR should separate them into distinct commits. Do not attempt to rewrite history on an open PR.

- [ ] **Step 4: Commit (if separation is possible without history rewrite)**

If the changes are isolated to specific files and can be cleanly reverted from this PR:

```bash
git checkout HEAD -- scripts/ecosystem/Normalizer.php scripts/ecosystem/RelevanceMapper.php
git commit -m "chore: defer ecosystem triage improvements to separate PR"
```

---

### Task 7: Run full verification

**Files:**
- (none — verification only)

- [ ] **Step 1: Format codebase**

```bash
composer run format
```

- [ ] **Step 2: Run static analysis**

```bash
composer analyse
```

Expected: PASS, baseline not grown

- [ ] **Step 3: Run full test suite**

```bash
composer run test
```

Expected: PASS

- [ ] **Step 4: Verify layering docs are consistent**

```bash
composer run docs
```

Check that the generated layering diagram no longer shows `Expression` → `Evaluation` (should not be a red `M_Expression -.->|violation| M_Evaluation` edge) and no `Expression` → `Execution` edge via `WorkflowContext`.

- [ ] **Step 5: Final commit (if any formatting fixes needed)**

```bash
git add -A
git commit -m "chore: formatting and layering doc sync after standards remediation"
```
