# DependencyGraph Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-workflow step DAG (`DependencyGraph`) that fixes the sync executor's array-order bug, exposes cycle + unresolved-ref detection, and becomes the single source of truth for both sync and async execution ordering.

**Architecture:** New `DependencyGraph` class mines explicit `Step::$dependsOn` + implicit `{$steps.X.outputs.Y}` refs from Expression / Selector fields via existing Expression AST (`StepRefExtractor` helper). Single DFS three-color pass yields both first-cycle path and topological order. Sync `WorkflowExecutor` iterates topological order; async `Engine` + `DependencyAnalyzer` refactored to consult the same graph. Validator gains one new rule with two error codes.

**Tech Stack:** PHP 8.4, Pest 4, PHPStan (larastan), Symfony YAML, Laravel package.

## Global Constraints

- PHP version: `^8.4`.
- Test framework: Pest 4 (`vendor/bin/pest`).
- Static analysis gate: PHPStan max level, must stay clean (`vendor/bin/phpstan analyse`).
- Formatter: Laravel Pint (`vendor/bin/pint`).
- Pre-push gate: `pint --test` + `phpstan` + `pest --ci` (run via `make verify`).
- Namespace root: `Alama\LaravelArazzo\` → `src/`. Test namespace: `Alama\LaravelArazzo\Tests\` → `tests/`.
- New / modified public APIs must be SemVer-minor safe except:
  - `DependencyAnalyzer` constructor becomes `__construct(DependencyGraph)` — internal engine class, no downstream consumer known but noted in CHANGELOG.
  - `WorkflowExecutor` gains new exceptions thrown before step loop — behavior change.
- Follow existing DFS three-color style used in `WorkflowDependsOnNoCycleRule` for coherence.
- Commit convention: Conventional Commits.
- Total validator rule count post-task: 40 → 41 (independent of any core-34 count changes).

---

## File Structure

**New files (source):**

- `src/Execution/StepRefExtractor.php` — static helper: `fromStep(Step): list<string>`
- `src/Execution/DependencyGraph.php` — per-workflow step DAG
- `src/Exceptions/CyclicDependencyException.php`
- `src/Exceptions/UnresolvedReferenceException.php`
- `src/Exceptions/ExecutionException.php` — create if missing; parent for the two above
- `src/Validation/Rules/StepDependencyNoCycleRule.php`

**Modified files (source):**

- `src/Execution/WorkflowExecutor.php` — build graph, throw on cycle/unresolved, iterate topological order
- `src/Execution/DependencyAnalyzer.php` — constructor takes `DependencyGraph`, delegates
- `src/Execution/Engine.php` — build + cache graph per workflow, inject into analyzer, throw on cycle/unresolved

**New fixtures:**

- `tests/fixtures/parser/out-of-order-refs.yaml`
- `tests/fixtures/parser/cyclic-step-deps.yaml`
- `tests/fixtures/parser/unresolved-step-ref.yaml`
- `tests/fixtures/parser/implicit-refs-across-fields.yaml`

**New test files** (one per new class + refactored analyzer + E2E acceptance).

---

### Task 1: `StepRefExtractor` — mine `$steps.<id>` from Expression / Selector fields

**Files:**
- Create: `src/Execution/StepRefExtractor.php`
- Test: `tests/Execution/StepRefExtractorTest.php`

**Interfaces:**
- Consumes: `Step`, `Expression`, `Selector`, `SubWorkflowSuccessAction`, `SubWorkflowFailureAction` (Selector / SubWorkflow types exist post core-34 land; if this stub lands first, the extractor should still handle `Expression` + string fields — flag any missing type import as todo and stub the Selector/SubWorkflow branches guarded by `class_exists`). AST classes: `Alama\LaravelArazzo\Expression\Ast\StepRef` (has `public string $stepId`).
- Produces: `StepRefExtractor::fromStep(Step $step): array<int, string>` — deduped list of `stepId` strings referenced by the step's Expression / Selector fields.

- [ ] **Step 1: Write failing test**

Create `tests/Execution/StepRefExtractorTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Execution\StepRefExtractor;

function bareStep(): array
{
    // Positional constructor arg helper to keep tests compact
    return [null, null, 'op', null, null, [], null, [], [], [], [], []];
}

it('returns empty for a step with no expressions', function () {
    $step = new Step('s', ...bareStep());
    expect(StepRefExtractor::fromStep($step))->toBe([]);
});

it('extracts a $steps ref from a parameter value expression', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [new Parameter('p', ParameterIn::Query, new Expression('{$steps.a.outputs.id}'))],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    expect(StepRefExtractor::fromStep($step))->toBe(['a']);
});

it('extracts refs from requestBody payload and replacement values', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: new RequestBody(
            'application/json',
            new Expression('{$steps.a.outputs.body}'),
            [new PayloadReplacement('/id', new Expression('{$steps.b.outputs.id}'))],
        ),
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    expect(StepRefExtractor::fromStep($step))->toEqualCanonicalizing(['a', 'b']);
});

it('extracts refs from success criterion conditions', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '{$steps.a.outputs.status}', null, null)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    expect(StepRefExtractor::fromStep($step))->toBe(['a']);
});

it('extracts refs from correlationId', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        dependsOn: [],
        action: 'receive',
        channelPath: 'ch',
        correlationId: new Expression('{$steps.a.outputs.id}'),
    );

    expect(StepRefExtractor::fromStep($step))->toBe(['a']);
});

it('extracts refs from step outputs (Expression form)', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['x' => new Expression('{$steps.a.outputs.id}')],
    );

    expect(StepRefExtractor::fromStep($step))->toBe(['a']);
});

it('dedupes repeated refs', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [
            new Parameter('p1', ParameterIn::Query, new Expression('{$steps.a.outputs.x}')),
            new Parameter('p2', ParameterIn::Query, new Expression('{$steps.a.outputs.y}')),
        ],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    expect(StepRefExtractor::fromStep($step))->toBe(['a']);
});

it('ignores non-expression string values', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [new Parameter('p', ParameterIn::Query, 'plain-string')],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    expect(StepRefExtractor::fromStep($step))->toBe([]);
});

it('ignores syntactically-invalid expressions gracefully', function () {
    $step = new Step(
        stepId: 's',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [new Parameter('p', ParameterIn::Query, new Expression('{$not-an-actual-thing}'))],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    // The graph is not the place to fail on malformed expressions.
    // Validator's ExpressionSyntaxRule catches that; extractor returns [].
    expect(StepRefExtractor::fromStep($step))->toBe([]);
});
```

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Execution/StepRefExtractorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `StepRefExtractor`**

Create `src/Execution/StepRefExtractor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;

final class StepRefExtractor
{
    /**
     * @return list<string> deduped list of stepIds referenced by this step's expression fields
     */
    public static function fromStep(Step $step): array
    {
        $refs = [];

        foreach ($step->parameters as $param) {
            self::collect($param->value, $refs);
        }

        if ($step->requestBody !== null) {
            self::collect($step->requestBody->payload, $refs);
            foreach ($step->requestBody->replacements as $repl) {
                if ($repl instanceof PayloadReplacement) {
                    self::collect($repl->value, $refs);
                }
            }
        }

        foreach ($step->successCriteria as $crit) {
            // condition is a plain string in the DTO; wrap if it looks like an expression.
            if (is_string($crit->condition) && str_starts_with($crit->condition, '{$')) {
                self::collect(new Expression($crit->condition), $refs);
            }
        }

        if ($step->correlationId !== null) {
            self::collect($step->correlationId, $refs);
        }

        foreach ($step->outputs as $out) {
            self::collect($out, $refs);
        }

        // Sub-workflow invoke parameter values — guarded so this class remains
        // usable even if the 1.1.0 substrate hasn't landed.
        foreach ([...$step->onSuccess, ...$step->onFailure] as $action) {
            $parameters = self::extractInvokeParameters($action);
            foreach ($parameters as $value) {
                self::collect($value, $refs);
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @param list<string> $refs
     */
    private static function collect(mixed $value, array &$refs): void
    {
        // Selector: descend into its context (which is an expression string) and selector.
        if (is_object($value) && str_ends_with($value::class, '\\Selector')) {
            /** @var object{context: ?string, selector: string} $value */
            if ($value->context !== null && str_starts_with($value->context, '{$')) {
                self::collect(new Expression($value->context), $refs);
            }
            return;
        }

        if (!$value instanceof Expression) {
            return;
        }

        $ast = $value->astOrError();
        if ($ast instanceof ExpressionSyntaxException) {
            return; // Extractor is not the enforcement point for syntax.
        }

        self::walk($ast, $refs);
    }

    /**
     * @param list<string> $refs
     */
    private static function walk(ExpressionAst $ast, array &$refs): void
    {
        if ($ast instanceof StepRef) {
            $refs[] = $ast->stepId;
        }
        // Current AST is a single node per parse — no children to recurse.
        // If ExpressionAst gains composite nodes later, extend here.
    }

    /**
     * @return list<mixed>
     */
    private static function extractInvokeParameters(object $action): array
    {
        // Duck-typed to avoid a hard dep on SubWorkflow*Action types
        // that only exist once core-34 lands.
        if (!property_exists($action, 'parameters') || !is_array($action->parameters ?? null)) {
            return [];
        }
        /** @var array<mixed> $parameters */
        $parameters = $action->parameters;
        return array_values($parameters);
    }
}
```

Note on `Selector`: string check `str_ends_with($value::class, '\\Selector')` avoids a hard import. When core-34 lands, replace with the concrete `instanceof \Alama\LaravelArazzo\Dto\Selector`. Left duck-typed here so this stub can land independently of core-34.

- [ ] **Step 4: Run test + PHPStan**

Run: `vendor/bin/pest tests/Execution/StepRefExtractorTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors. (If PHPStan flags the duck-typed Selector branch, add a targeted `@phpstan-ignore-next-line` with reason: "Selector type provided by core-34; duck-typed here to keep decoupled.")

- [ ] **Step 5: Commit**

```bash
git add src/Execution/StepRefExtractor.php tests/Execution/StepRefExtractorTest.php
git commit -m "feat(execution): StepRefExtractor mines \$steps refs from Expression/Selector fields"
```

---

### Task 2: Exceptions — `ExecutionException`, `CyclicDependencyException`, `UnresolvedReferenceException`

**Files:**
- Create: `src/Exceptions/ExecutionException.php` (skip if it already exists — see Step 1)
- Create: `src/Exceptions/CyclicDependencyException.php`
- Create: `src/Exceptions/UnresolvedReferenceException.php`
- Test: `tests/Exceptions/CyclicDependencyExceptionTest.php`
- Test: `tests/Exceptions/UnresolvedReferenceExceptionTest.php`

**Interfaces:**
- Consumes: `Alama\LaravelArazzo\Exceptions\ArazzoException` (existing base).
- Produces:
  - `ExecutionException` extends `ArazzoException` (base for execution-time errors).
  - `CyclicDependencyException::fromPath(string $workflowId, array $path): self` — `$path` is `list<string>`.
  - `UnresolvedReferenceException::fromRefs(string $workflowId, array $refs): self` — `$refs` is `array<string, list<string>>` (stepId → missing dep stepIds).
  - Both exceptions expose `->workflowId(): string`, `->details(): array` accessors for downstream reporting.

- [ ] **Step 1: Check if `ExecutionException` already exists**

Run: `test -f src/Exceptions/ExecutionException.php && echo EXISTS || echo MISSING`

If EXISTS: read it, note the parent class + constructor shape. Skip its creation step below and adapt the child exceptions to match its signature.

- [ ] **Step 2: Write failing tests**

Create `tests/Exceptions/CyclicDependencyExceptionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;

it('formats a cycle path into its message and details', function () {
    $ex = CyclicDependencyException::fromPath('ride-saga', ['A', 'B', 'C', 'A']);

    expect($ex->workflowId())->toBe('ride-saga')
        ->and($ex->details())->toBe(['path' => ['A', 'B', 'C', 'A']])
        ->and($ex->getMessage())->toContain('ride-saga')
        ->and($ex->getMessage())->toContain('A -> B -> C -> A');
});
```

Create `tests/Exceptions/UnresolvedReferenceExceptionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;

it('formats missing refs into its message and details', function () {
    $ex = UnresolvedReferenceException::fromRefs('ride-saga', [
        'stepC' => ['ghost', 'phantom'],
    ]);

    expect($ex->workflowId())->toBe('ride-saga')
        ->and($ex->details())->toBe(['refs' => ['stepC' => ['ghost', 'phantom']]])
        ->and($ex->getMessage())->toContain("stepC")
        ->and($ex->getMessage())->toContain('ghost')
        ->and($ex->getMessage())->toContain('phantom');
});
```

- [ ] **Step 3: Run to see them fail**

Run: `vendor/bin/pest tests/Exceptions/`
Expected: FAIL — classes not found.

- [ ] **Step 4: Create `ExecutionException` (if missing)**

Create `src/Exceptions/ExecutionException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

class ExecutionException extends ArazzoException
{
    private string $wfId = '';

    /** @var array<string, mixed> */
    private array $detail = [];

    public function workflowId(): string
    {
        return $this->wfId;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->detail;
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function withContext(string $workflowId, array $details): static
    {
        $this->wfId = $workflowId;
        $this->detail = $details;
        return $this;
    }
}
```

Verify `ArazzoException` constructor signature — check `src/Exceptions/ArazzoException.php`. Adapt the `parent::__construct(...)` call in `CyclicDependencyException` / `UnresolvedReferenceException` below to match.

- [ ] **Step 5: Create `CyclicDependencyException`**

Create `src/Exceptions/CyclicDependencyException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

final class CyclicDependencyException extends ExecutionException
{
    /**
     * @param list<string> $path
     */
    public static function fromPath(string $workflowId, array $path): self
    {
        $arrow = implode(' -> ', $path);
        $ex = new self(
            "Cyclic step dependency in workflow '{$workflowId}': {$arrow}",
            "/workflows[{$workflowId}]",
            'execution.cyclic_dependency',
        );
        return $ex->withContext($workflowId, ['path' => $path]);
    }
}
```

- [ ] **Step 6: Create `UnresolvedReferenceException`**

Create `src/Exceptions/UnresolvedReferenceException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

final class UnresolvedReferenceException extends ExecutionException
{
    /**
     * @param array<string, list<string>> $refs stepId -> missing dep stepIds
     */
    public static function fromRefs(string $workflowId, array $refs): self
    {
        $parts = [];
        foreach ($refs as $stepId => $missing) {
            $parts[] = "{$stepId} -> " . implode(', ', $missing);
        }
        $summary = implode('; ', $parts);

        $ex = new self(
            "Unresolved step references in workflow '{$workflowId}': {$summary}",
            "/workflows[{$workflowId}]",
            'execution.unresolved_reference',
        );
        return $ex->withContext($workflowId, ['refs' => $refs]);
    }
}
```

- [ ] **Step 7: Run tests + PHPStan**

Run: `vendor/bin/pest tests/Exceptions/`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 8: Commit**

```bash
git add src/Exceptions/ExecutionException.php src/Exceptions/CyclicDependencyException.php src/Exceptions/UnresolvedReferenceException.php tests/Exceptions/
git commit -m "feat(exceptions): ExecutionException base + CyclicDependency / UnresolvedReference"
```

---

### Task 3: `DependencyGraph` — build, DFS three-color, topological order, accessors

**Files:**
- Create: `src/Execution/DependencyGraph.php`
- Test: `tests/Execution/DependencyGraph/BuildTest.php`
- Test: `tests/Execution/DependencyGraph/TopologicalOrderTest.php`
- Test: `tests/Execution/DependencyGraph/CycleDetectionTest.php`
- Test: `tests/Execution/DependencyGraph/UnresolvedReferencesTest.php`

**Interfaces:**
- Consumes: `StepRefExtractor::fromStep` (Task 1), `Workflow`, `Step`.
- Produces:
  - `DependencyGraph::fromWorkflow(Workflow $wf): self` — factory.
  - `topologicalOrder(): array` — `list<Step>`, deps before dependents.
  - `firstCycle(): ?array` — `?list<string>` (path like `['A','B','C','A']`); `null` if DAG.
  - `hasCycle(): bool`.
  - `dependenciesOf(string $stepId): array` — `list<string>` direct dep stepIds; `[]` if none.
  - `unresolvedReferences(): array` — `array<string, list<string>>` stepId → missing dep stepIds.

- [ ] **Step 1: Write failing tests — Build**

Create `tests/Execution/DependencyGraph/BuildTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\DependencyGraph;

function stepWith(string $id, array $dependsOn = [], array $parameters = []): Step
{
    return new Step(
        stepId: $id,
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: $parameters,
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        dependsOn: $dependsOn,
    );
}

function wf(array $steps): Workflow
{
    return new Workflow('w', null, null, null, [], $steps, [], [], [], []);
}

it('records explicit dependsOn edges', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
    ]));

    expect($graph->dependenciesOf('A'))->toBe([])
        ->and($graph->dependenciesOf('B'))->toBe(['A']);
});

it('records implicit refs from expression fields', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', parameters: [
            new Parameter('p', ParameterIn::Query, new Expression('{$steps.A.outputs.x}')),
        ]),
    ]));

    expect($graph->dependenciesOf('B'))->toBe(['A']);
});

it('dedupes explicit + implicit for the same dep', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A'], parameters: [
            new Parameter('p', ParameterIn::Query, new Expression('{$steps.A.outputs.x}')),
        ]),
    ]));

    expect($graph->dependenciesOf('B'))->toBe(['A']);
});

it('silently drops self-references', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A', dependsOn: ['A']),
    ]));

    expect($graph->dependenciesOf('A'))->toBe([])
        ->and($graph->hasCycle())->toBeFalse();
});

it('handles an empty workflow', function () {
    $graph = DependencyGraph::fromWorkflow(wf([]));

    expect($graph->topologicalOrder())->toBe([])
        ->and($graph->hasCycle())->toBeFalse()
        ->and($graph->unresolvedReferences())->toBe([]);
});
```

Create `tests/Execution/DependencyGraph/TopologicalOrderTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\DependencyGraph;

// Reuse the same helpers as BuildTest — Pest auto-loads them if declared in the same dir.
// If Pest complains, inline the helpers here.

it('orders a linear chain A -> B -> C', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
        stepWith('C', dependsOn: ['B']),
    ]));

    $ids = array_map(fn (Step $s) => $s->stepId, $graph->topologicalOrder());
    expect($ids)->toBe(['A', 'B', 'C']);
});

it('places deps before dependents when declared out of order', function () {
    // Declaration order: C, A, B. Dep order: A -> B -> C.
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('C', parameters: [new Parameter('p', ParameterIn::Query, new Expression('{$steps.B.outputs.x}'))]),
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
    ]));

    $ids = array_map(fn (Step $s) => $s->stepId, $graph->topologicalOrder());
    expect(array_search('A', $ids, true))->toBeLessThan(array_search('B', $ids, true))
        ->and(array_search('B', $ids, true))->toBeLessThan(array_search('C', $ids, true));
});

it('handles a diamond A -> {B, C} -> D', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
        stepWith('C', dependsOn: ['A']),
        stepWith('D', dependsOn: ['B', 'C']),
    ]));

    $ids = array_map(fn (Step $s) => $s->stepId, $graph->topologicalOrder());
    expect($ids[0])->toBe('A')
        ->and(array_search('B', $ids, true))->toBeLessThan(array_search('D', $ids, true))
        ->and(array_search('C', $ids, true))->toBeLessThan(array_search('D', $ids, true));
});

it('returns single element for one-step workflow', function () {
    $graph = DependencyGraph::fromWorkflow(wf([stepWith('only')]));
    $ids = array_map(fn (Step $s) => $s->stepId, $graph->topologicalOrder());
    expect($ids)->toBe(['only']);
});
```

Create `tests/Execution/DependencyGraph/CycleDetectionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Execution\DependencyGraph;

it('reports null cycle for a DAG', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
    ]));

    expect($graph->firstCycle())->toBeNull()
        ->and($graph->hasCycle())->toBeFalse();
});

it('detects a 2-cycle', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A', dependsOn: ['B']),
        stepWith('B', dependsOn: ['A']),
    ]));

    expect($graph->hasCycle())->toBeTrue();
    $cycle = $graph->firstCycle();
    expect($cycle)->not->toBeNull()
        ->and($cycle[0])->toBe($cycle[array_key_last($cycle)])  // closed loop
        ->and(count(array_unique(array_slice($cycle, 0, -1))))->toBe(count($cycle) - 1); // no dup except endpoints
});

it('detects a 3-cycle', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A', dependsOn: ['C']),
        stepWith('B', dependsOn: ['A']),
        stepWith('C', dependsOn: ['B']),
    ]));

    expect($graph->hasCycle())->toBeTrue();
    $cycle = $graph->firstCycle();
    expect(count($cycle))->toBe(4) // ['A','C','B','A'] or a rotation
        ->and(array_unique(array_slice($cycle, 0, -1)))->toEqualCanonicalizing(['A', 'B', 'C']);
});
```

Create `tests/Execution/DependencyGraph/UnresolvedReferencesTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Execution\DependencyGraph;

it('flags refs to steps not defined in the workflow', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A', dependsOn: ['ghost']),
    ]));

    expect($graph->unresolvedReferences())->toBe(['A' => ['ghost']]);
});

it('separates missing refs from present ones', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A', 'phantom']),
    ]));

    expect($graph->unresolvedReferences())->toBe(['B' => ['phantom']])
        ->and($graph->dependenciesOf('B'))->toBe(['A']);
});

it('returns empty when all refs resolve', function () {
    $graph = DependencyGraph::fromWorkflow(wf([
        stepWith('A'),
        stepWith('B', dependsOn: ['A']),
    ]));

    expect($graph->unresolvedReferences())->toBe([]);
});
```

- [ ] **Step 2: Run all four failing test files**

Run: `vendor/bin/pest tests/Execution/DependencyGraph/`
Expected: all FAIL — `DependencyGraph` not found.

- [ ] **Step 3: Create `DependencyGraph`**

Create `src/Execution/DependencyGraph.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;

final class DependencyGraph
{
    /** @var array<string, list<Step>>|null cached topological order */
    private ?array $orderCache = null;

    /** @var list<string>|null cached first cycle path */
    private ?array $cycleCache = null;

    /** @var bool has the DFS pass run yet? */
    private bool $dfsRan = false;

    /**
     * @param array<string, list<string>> $edges           stepId -> list of dep stepIds
     * @param array<string, Step>         $stepsById
     * @param array<string, list<string>> $unresolvedRefs  stepId -> list of missing dep stepIds
     */
    private function __construct(
        private array $edges,
        private array $stepsById,
        private array $unresolvedRefs,
    ) {}

    public static function fromWorkflow(Workflow $wf): self
    {
        $stepsById = [];
        foreach ($wf->steps as $step) {
            $stepsById[$step->stepId] = $step;
        }

        $edges = [];
        $unresolved = [];

        foreach ($wf->steps as $step) {
            $edges[$step->stepId] = [];

            $depSet = [];
            // Explicit dependsOn — cast values to string; docblock says array<string, mixed>.
            foreach ($step->dependsOn as $depId) {
                if (is_string($depId)) {
                    $depSet[$depId] = true;
                }
            }
            // Implicit refs via extractor
            foreach (StepRefExtractor::fromStep($step) as $depId) {
                $depSet[$depId] = true;
            }

            $missing = [];
            foreach (array_keys($depSet) as $depId) {
                if ($depId === $step->stepId) {
                    continue; // silently drop self-ref
                }
                if (isset($stepsById[$depId])) {
                    $edges[$step->stepId][] = $depId;
                } else {
                    $missing[] = $depId;
                }
            }
            if ($missing !== []) {
                $unresolved[$step->stepId] = $missing;
            }
        }

        return new self($edges, $stepsById, $unresolved);
    }

    /** @return list<Step> */
    public function topologicalOrder(): array
    {
        $this->runDfsIfNeeded();
        return $this->orderCache ?? [];
    }

    /** @return list<string>|null */
    public function firstCycle(): ?array
    {
        $this->runDfsIfNeeded();
        return $this->cycleCache;
    }

    public function hasCycle(): bool
    {
        return $this->firstCycle() !== null;
    }

    /** @return list<string> */
    public function dependenciesOf(string $stepId): array
    {
        return $this->edges[$stepId] ?? [];
    }

    /** @return array<string, list<string>> */
    public function unresolvedReferences(): array
    {
        return $this->unresolvedRefs;
    }

    private function runDfsIfNeeded(): void
    {
        if ($this->dfsRan) {
            return;
        }
        $this->dfsRan = true;

        $WHITE = 0; $GREY = 1; $BLACK = 2;
        $color = [];
        foreach (array_keys($this->stepsById) as $id) {
            $color[$id] = $WHITE;
        }

        $stack = [];
        /** @var list<Step> $order */
        $order = [];
        /** @var list<string>|null $cyclePath */
        $cyclePath = null;

        $dfs = function (string $id) use (&$dfs, &$color, &$stack, &$order, &$cyclePath, $WHITE, $GREY, $BLACK): bool {
            if ($cyclePath !== null) {
                return true;
            }
            $color[$id] = $GREY;
            $stack[] = $id;

            foreach ($this->edges[$id] as $dep) {
                if (($color[$dep] ?? $WHITE) === $GREY) {
                    $idx = array_search($dep, $stack, true);
                    $slice = $idx === false ? [$dep] : array_slice($stack, $idx);
                    $cyclePath = [...$slice, $dep];
                    return true;
                }
                if (($color[$dep] ?? $WHITE) === $WHITE && $dfs($dep)) {
                    return true;
                }
            }

            $color[$id] = $BLACK;
            array_pop($stack);
            $order[] = $this->stepsById[$id];
            return false;
        };

        foreach (array_keys($this->stepsById) as $id) {
            if ($color[$id] === $WHITE && $dfs($id)) {
                break;
            }
        }

        $this->orderCache = $order;
        $this->cycleCache = $cyclePath;
    }
}
```

- [ ] **Step 4: Run all four test files**

Run: `vendor/bin/pest tests/Execution/DependencyGraph/`
Expected: all PASS.

Run: `vendor/bin/pest`
Expected: full suite green.

Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/DependencyGraph.php tests/Execution/DependencyGraph/
git commit -m "feat(execution): DependencyGraph with DFS three-color + topo order"
```

---

### Task 4: Wire `WorkflowExecutor` to use `DependencyGraph`

**Files:**
- Modify: `src/Execution/WorkflowExecutor.php`
- Test: `tests/Execution/WorkflowExecutorOrderingTest.php`

**Interfaces:**
- Consumes: `DependencyGraph::fromWorkflow`, `hasCycle`, `firstCycle`, `unresolvedReferences`, `topologicalOrder`; `CyclicDependencyException::fromPath`; `UnresolvedReferenceException::fromRefs`.
- Produces: no new public API; `WorkflowExecutor::execute()` now throws `CyclicDependencyException` and `UnresolvedReferenceException` before step execution; iterates steps in topological order.

- [ ] **Step 1: Write failing test**

Create `tests/Execution/WorkflowExecutorOrderingTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;
use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;

/**
 * Spy step executor: records the order steps were executed in.
 * Concrete class name may differ — check src/Execution/StepExecutor.php and adapt.
 */
class SpyStepExecutor extends \Alama\LaravelArazzo\Execution\StepExecutor
{
    /** @var list<string> */
    public array $orderExecuted = [];

    public function __construct() {}

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        $this->orderExecuted[] = $step->stepId;
        return [$context, true];
    }
}

function docWith(Workflow $wf): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('executes steps in topological order regardless of declaration order', function () {
    $stepC = new Step('C', null, 'op', null, null,
        [new Parameter('p', ParameterIn::Query, new Expression('{$steps.B.outputs.x}'))],
        null, [], [], [], [], []);
    $stepA = new Step('A', null, 'op', null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);

    $wf = new Workflow('w', null, null, null, [], [$stepC, $stepA, $stepB], [], [], [], []);
    $spy = new SpyStepExecutor();
    (new WorkflowExecutor($spy))->execute($wf, docWith($wf), []);

    expect($spy->orderExecuted)->toBe(['A', 'B', 'C']);
});

it('throws CyclicDependencyException on step cycles', function () {
    $stepA = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['B']);
    $stepB = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);
    $wf = new Workflow('w', null, null, null, [], [$stepA, $stepB], [], [], [], []);

    (new WorkflowExecutor(new SpyStepExecutor()))->execute($wf, docWith($wf), []);
})->throws(CyclicDependencyException::class);

it('throws UnresolvedReferenceException on missing step refs', function () {
    $stepA = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['ghost']);
    $wf = new Workflow('w', null, null, null, [], [$stepA], [], [], [], []);

    (new WorkflowExecutor(new SpyStepExecutor()))->execute($wf, docWith($wf), []);
})->throws(UnresolvedReferenceException::class);
```

Adapt `SpyStepExecutor` to the actual `StepExecutor` signature — inspect `src/Execution/StepExecutor.php` for the real return shape. Current shape per file read: `[$context, $success]` tuple; adjust if it differs.

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorOrderingTest.php`
Expected: FAIL — first test fails because current executor uses declaration order (`orderExecuted = ['C','A','B']`); cycle test does not throw; unresolved test does not throw.

- [ ] **Step 3: Refactor `WorkflowExecutor::execute`**

Modify `src/Execution/WorkflowExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;
use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionLoggerInterface;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\Dto\StepResult;

class WorkflowExecutor
{
    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs): ExecutionResult
    {
        $graph = DependencyGraph::fromWorkflow($workflow);

        if ($graph->hasCycle()) {
            /** @var list<string> $cycle */
            $cycle = $graph->firstCycle() ?? [];
            throw CyclicDependencyException::fromPath($workflow->workflowId, $cycle);
        }

        if ($graph->unresolvedReferences() !== []) {
            throw UnresolvedReferenceException::fromRefs(
                $workflow->workflowId,
                $graph->unresolvedReferences(),
            );
        }

        $context = new WorkflowContext($workflow->workflowId, $inputs);
        $stepResults = [];

        foreach ($graph->topologicalOrder() as $step) {
            $stepId = $step->stepId;

            $this->logger?->logStepStarted($stepId);

            [$context, $success] = $this->stepExecutor->execute($step, $context, $document);

            $outputs = $context->getSteps()[$stepId]['outputs'] ?? [];
            $result = new StepResult($stepId, $success, $outputs);

            $stepResults[$stepId] = $result;

            if (!$success) {
                $this->logger?->logStepFailed($stepId, new \RuntimeException('Step failed'));
                break;
            }

            $this->logger?->logStepCompleted($workflow->workflowId, $stepId, $result->outputs);
        }

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
}
```

- [ ] **Step 4: Run tests + full suite + PHPStan**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorOrderingTest.php`
Expected: PASS.

Run: `vendor/bin/pest`
Expected: all green. Any pre-existing `WorkflowExecutor` test that constructed a linear workflow will still work (topological order == declaration order in the linear case).

Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowExecutor.php tests/Execution/WorkflowExecutorOrderingTest.php
git commit -m "feat(executor): sync WorkflowExecutor iterates topological order + throws on cycle/unresolved"
```

---

### Task 5: Refactor `DependencyAnalyzer` + `Engine` async wiring

**Files:**
- Modify: `src/Execution/DependencyAnalyzer.php`
- Modify: `src/Execution/Engine.php`
- Test: `tests/Execution/DependencyAnalyzerTest.php`
- Test: `tests/Execution/EngineDependencyGraphTest.php`

**Interfaces:**
- Consumes: `DependencyGraph` (Task 3), same exceptions as Task 4.
- Produces:
  - `DependencyAnalyzer::__construct(DependencyGraph $graph)` — no other args.
  - `DependencyAnalyzer::getRunnableSteps(array $allSteps, WorkflowContext $context): array` — signature unchanged; implementation delegates to graph.
  - `Engine::evaluate()` throws `CyclicDependencyException` / `UnresolvedReferenceException` (matching sync). Builds + caches graph per `workflowId`.

- [ ] **Step 1: Inspect existing `Engine` constructor + call sites**

Run: `grep -rn "new DependencyAnalyzer\|new Engine" src/ tests/`

Any consumers of `DependencyAnalyzer` currently constructing it with no args (per file read) will break. Update every call site in the same task.

Similarly, check tests that construct `DependencyAnalyzer` directly.

- [ ] **Step 2: Write failing tests**

Create `tests/Execution/DependencyAnalyzerTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\DependencyGraph;
use Alama\LaravelArazzo\Execution\StepStatus;
use Alama\LaravelArazzo\Execution\WorkflowContext;

function makeSteps(): array
{
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], []);
    $B = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);
    $C = new Step('C', null, 'op', null, null,
        [new Parameter('p', ParameterIn::Query, new Expression('{$steps.B.outputs.x}'))],
        null, [], [], [], [], []);
    return ['A' => $A, 'B' => $B, 'C' => $C];
}

it('returns steps with all deps satisfied', function () {
    $steps = makeSteps();
    $wf = new Workflow('w', null, null, null, [], array_values($steps), [], [], [], []);
    $graph = DependencyGraph::fromWorkflow($wf);
    $analyzer = new DependencyAnalyzer($graph);

    $ctx = new WorkflowContext('w', []);
    $ctx->setStepStatus('A', StepStatus::Succeeded); // adapt setter name to real WorkflowContext API

    $runnable = $analyzer->getRunnableSteps(array_values($steps), $ctx);
    $ids = array_map(fn (Step $s) => $s->stepId, $runnable);

    // B's dep A is done -> runnable. C's dep B is not done -> not runnable.
    expect($ids)->toBe(['B']);
});

it('honors implicit expression refs (regression: previously ignored)', function () {
    // C depends on B implicitly (via expression). If A + B both succeeded, C is runnable.
    $steps = makeSteps();
    $wf = new Workflow('w', null, null, null, [], array_values($steps), [], [], [], []);
    $graph = DependencyGraph::fromWorkflow($wf);
    $analyzer = new DependencyAnalyzer($graph);

    $ctx = new WorkflowContext('w', []);
    $ctx->setStepStatus('A', StepStatus::Succeeded);
    $ctx->setStepStatus('B', StepStatus::Succeeded);

    $runnable = $analyzer->getRunnableSteps(array_values($steps), $ctx);
    $ids = array_map(fn (Step $s) => $s->stepId, $runnable);

    expect($ids)->toBe(['C']);
});
```

If `WorkflowContext` exposes a different setter, adjust (`recordStepStatus`, etc.). Inspect `src/Execution/WorkflowContext.php` before finalizing.

Create `tests/Execution/EngineDependencyGraphTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;
use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\WorkflowContext;

// Spies matching QueueDriverInterface + StateStoreInterface — inspect real interfaces first
class NoopQueue implements \Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface {
    public array $dispatched = [];
    public function dispatch(object $job): void { $this->dispatched[] = $job; }
}
class NoopStateStore implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {}

it('throws on cycle before dispatching any job', function () {
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['B']);
    $B = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);
    $wf = new Workflow('w', null, null, null, [], [$A, $B], [], [], [], []);

    $queue = new NoopQueue();
    $engine = new Engine($queue, new NoopStateStore());  // adapt constructor
    $engine->evaluate($wf, new WorkflowContext('w', []));

    expect($queue->dispatched)->toBe([]);
})->throws(CyclicDependencyException::class);

it('throws on unresolved refs before dispatching', function () {
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['ghost']);
    $wf = new Workflow('w', null, null, null, [], [$A], [], [], [], []);

    $engine = new Engine(new NoopQueue(), new NoopStateStore());
    $engine->evaluate($wf, new WorkflowContext('w', []));
})->throws(UnresolvedReferenceException::class);
```

The `Engine` constructor before Task 5 takes `(DependencyAnalyzer, QueueDriverInterface, StateStoreInterface)`. After this task, the analyzer is built internally per workflow. Adapt the constructor signature in the code and the test to match.

- [ ] **Step 3: Run to see them fail**

Run: `vendor/bin/pest tests/Execution/DependencyAnalyzerTest.php tests/Execution/EngineDependencyGraphTest.php`
Expected: FAIL — `DependencyAnalyzer::__construct(DependencyGraph)` doesn't exist; `Engine` doesn't throw.

- [ ] **Step 4: Refactor `DependencyAnalyzer`**

Modify `src/Execution/DependencyAnalyzer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;

class DependencyAnalyzer
{
    public function __construct(private DependencyGraph $graph) {}

    /**
     * @param  Step[] $allSteps
     * @return Step[]
     */
    public function getRunnableSteps(array $allSteps, WorkflowContext $context): array
    {
        $runnable = [];

        foreach ($allSteps as $step) {
            $status = $context->getStepStatus($step->stepId);
            if ($status !== null && $status !== StepStatus::Pending) {
                continue;
            }

            $depsMet = true;
            foreach ($this->graph->dependenciesOf($step->stepId) as $depId) {
                if ($context->getStepStatus($depId) !== StepStatus::Succeeded) {
                    $depsMet = false;
                    break;
                }
            }
            if ($depsMet) {
                $runnable[] = $step;
            }
        }

        return $runnable;
    }
}
```

- [ ] **Step 5: Refactor `Engine`**

Modify `src/Execution/Engine.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;
use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;

class Engine
{
    /** @var array<string, DependencyGraph> */
    private array $graphs = [];

    public function __construct(
        private QueueDriverInterface $queueDriver,
        /** @phpstan-ignore property.onlyWritten */
        private StateStoreInterface $stateStore,
    ) {
    }

    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        if ($context->getWorkflowId() === null) {
            $context = $context->withWorkflowId($workflow->workflowId);
        }

        $graph = $this->graphs[$workflow->workflowId] ??= DependencyGraph::fromWorkflow($workflow);

        if ($graph->hasCycle()) {
            /** @var list<string> $cycle */
            $cycle = $graph->firstCycle() ?? [];
            throw CyclicDependencyException::fromPath($workflow->workflowId, $cycle);
        }
        if ($graph->unresolvedReferences() !== []) {
            throw UnresolvedReferenceException::fromRefs(
                $workflow->workflowId,
                $graph->unresolvedReferences(),
            );
        }

        $analyzer = new DependencyAnalyzer($graph);
        $runnableSteps = $analyzer->getRunnableSteps($workflow->steps, $context);

        if (empty($runnableSteps)) {
            return;
        }

        foreach ($runnableSteps as $step) {
            $job = new ExecuteStepJob($step, $context);
            $this->queueDriver->dispatch($job);
        }
    }
}
```

Note: `Engine` constructor no longer takes `DependencyAnalyzer`. Update every `new Engine(...)` call site (service provider, tests). Search and fix:

Run: `grep -rn "new Engine\b" src/ tests/`
For each hit, drop the `$analyzer` argument.

Also update `LaravelArazzoServiceProvider` if it binds `Engine`.

- [ ] **Step 6: Update any legacy `DependencyAnalyzer` construction**

Run: `grep -rn "new DependencyAnalyzer\b" src/ tests/`
For each hit, either pass a `DependencyGraph` or refactor the code to receive a graph from its own caller.

- [ ] **Step 7: Run tests + full suite + PHPStan**

Run: `vendor/bin/pest`
Expected: all green.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 8: Commit**

```bash
git add src/Execution/DependencyAnalyzer.php src/Execution/Engine.php src/LaravelArazzoServiceProvider.php tests/Execution/DependencyAnalyzerTest.php tests/Execution/EngineDependencyGraphTest.php
git commit -m "refactor(engine): DependencyAnalyzer + Engine consult DependencyGraph; async honors implicit refs"
```

---

### Task 6: Validator — `StepDependencyNoCycleRule`

**Files:**
- Create: `src/Validation/Rules/StepDependencyNoCycleRule.php`
- Test: `tests/Validation/Rules/StepDependencyNoCycleRuleTest.php`

**Interfaces:**
- Consumes: `DependencyGraph::fromWorkflow`, `firstCycle`, `unresolvedReferences`.
- Produces: `Rule` implementation with `code() = 'step.dependency_no_cycle'`. Emits two error codes: `step.dependency_no_cycle` (on cycles) and `step.dependency_unresolved` (on missing refs).

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/StepDependencyNoCycleRuleTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepDependencyNoCycleRule;

function docWithSteps(array $steps): ArazzoDocument
{
    $wf = new Workflow('w', null, null, null, [], $steps, [], [], [], []);
    return new ArazzoDocument(
        arazzo: '1.0.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('reports no errors for a DAG', function () {
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], []);
    $B = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);
    $errors = new ErrorCollector();

    (new StepDependencyNoCycleRule())->check(
        docWithSteps([$A, $B]), new SymbolTable(), $errors,
    );

    expect($errors->errors())->toBe([]);
});

it('reports step.dependency_no_cycle on cycle', function () {
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['B']);
    $B = new Step('B', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['A']);
    $errors = new ErrorCollector();

    (new StepDependencyNoCycleRule())->check(
        docWithSteps([$A, $B]), new SymbolTable(), $errors,
    );

    $codes = array_map(fn ($e) => $e->code, $errors->errors());
    expect($codes)->toContain('step.dependency_no_cycle');
});

it('reports step.dependency_unresolved on missing refs', function () {
    $A = new Step('A', null, 'op', null, null, [], null, [], [], [], [], [], dependsOn: ['ghost']);
    $errors = new ErrorCollector();

    (new StepDependencyNoCycleRule())->check(
        docWithSteps([$A]), new SymbolTable(), $errors,
    );

    $codes = array_map(fn ($e) => $e->code, $errors->errors());
    expect($codes)->toContain('step.dependency_unresolved');
});
```

`ErrorCollector`'s error accessor may return objects, arrays, or a specific DTO. Inspect `src/Validation/ErrorCollector.php` and `src/Validation/Error.php` to confirm the `->code` path.

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Validation/Rules/StepDependencyNoCycleRuleTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create rule**

Create `src/Validation/Rules/StepDependencyNoCycleRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Execution\DependencyGraph;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepDependencyNoCycleRule implements Rule
{
    public function code(): string
    {
        return 'step.dependency_no_cycle';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $wf) {
            $graph = DependencyGraph::fromWorkflow($wf);

            $cycle = $graph->firstCycle();
            if ($cycle !== null) {
                $path = implode(' -> ', $cycle);
                $errors->error(
                    $this->code(),
                    "Step dependency cycle in workflow '{$wf->workflowId}': {$path}",
                    "/workflows/{$wi}/steps",
                );
            }

            foreach ($graph->unresolvedReferences() as $stepId => $missing) {
                $missingList = implode(', ', $missing);
                $errors->error(
                    'step.dependency_unresolved',
                    "Step '{$stepId}' references undefined step(s): {$missingList}",
                    "/workflows/{$wi}/steps",
                );
            }
        }
    }
}
```

- [ ] **Step 4: Run tests + full suite + PHPStan**

Run: `vendor/bin/pest tests/Validation/Rules/StepDependencyNoCycleRuleTest.php`
Expected: PASS.
Run: `vendor/bin/pest && vendor/bin/phpstan analyse --no-progress`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Validation/Rules/StepDependencyNoCycleRule.php tests/Validation/Rules/StepDependencyNoCycleRuleTest.php
git commit -m "feat(validator): StepDependencyNoCycleRule (cycle + unresolved refs)"
```

---

### Task 7: Fixtures + E2E Acceptance

**Files:**
- Create: `tests/fixtures/parser/out-of-order-refs.yaml`
- Create: `tests/fixtures/parser/cyclic-step-deps.yaml`
- Create: `tests/fixtures/parser/unresolved-step-ref.yaml`
- Create: `tests/fixtures/parser/implicit-refs-across-fields.yaml`
- Create: `tests/Feature/DependencyGraphAcceptanceTest.php`

**Interfaces:**
- Consumes: everything.
- Produces: acceptance test proving the whole slice against the four new fixtures.

- [ ] **Step 1: Create fixtures**

`tests/fixtures/parser/out-of-order-refs.yaml`:

```yaml
arazzo: 1.0.0
info: { title: ooo, version: '1' }
workflows:
  - workflowId: w
    steps:
      - stepId: C
        operationId: opC
        parameters:
          - name: fromB
            in: query
            value: "{$steps.B.outputs.x}"
      - stepId: A
        operationId: opA
      - stepId: B
        operationId: opB
        dependsOn:
          - A
```

`tests/fixtures/parser/cyclic-step-deps.yaml`:

```yaml
arazzo: 1.0.0
info: { title: cyc, version: '1' }
workflows:
  - workflowId: w
    steps:
      - stepId: A
        operationId: opA
        dependsOn:
          - B
      - stepId: B
        operationId: opB
        parameters:
          - name: fromA
            in: query
            value: "{$steps.A.outputs.x}"
```

`tests/fixtures/parser/unresolved-step-ref.yaml`:

```yaml
arazzo: 1.0.0
info: { title: unres, version: '1' }
workflows:
  - workflowId: w
    steps:
      - stepId: A
        operationId: opA
        parameters:
          - name: fromGhost
            in: query
            value: "{$steps.ghost.outputs.x}"
```

`tests/fixtures/parser/implicit-refs-across-fields.yaml`:

```yaml
arazzo: 1.0.0
info: { title: implicit, version: '1' }
workflows:
  - workflowId: w
    steps:
      - stepId: source
        operationId: opSource
      - stepId: consumer
        operationId: opConsumer
        parameters:
          - name: p
            in: query
            value: "{$steps.source.outputs.x}"
        requestBody:
          contentType: application/json
          payload: "{$steps.source.outputs.body}"
          replacements:
            - target: /id
              value: "{$steps.source.outputs.id}"
        successCriteria:
          - condition: "{$steps.source.outputs.status}"
        outputs:
          out: "{$steps.source.outputs.derived}"
```

- [ ] **Step 2: Write acceptance test**

Create `tests/Feature/DependencyGraphAcceptanceTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Exceptions\CyclicDependencyException;
use Alama\LaravelArazzo\Exceptions\UnresolvedReferenceException;
use Alama\LaravelArazzo\Execution\DependencyGraph;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Validation\RuleSet;
use Alama\LaravelArazzo\Validation\Rules\StepDependencyNoCycleRule;
use Alama\LaravelArazzo\Validation\Validator;
use Symfony\Component\Yaml\Yaml;

function loadDepGraphFixture(string $filename): RawDocument
{
    $path = __DIR__ . '/../fixtures/parser/' . $filename;
    return new RawDocument(Yaml::parseFile($path), $path, Format::Yaml);
}

it('parses out-of-order-refs and topologically orders steps A -> B -> C', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('out-of-order-refs.yaml'));
    $wf = $doc->workflows[0];
    $graph = DependencyGraph::fromWorkflow($wf);

    $ids = array_map(fn ($s) => $s->stepId, $graph->topologicalOrder());
    expect(array_search('A', $ids, true))->toBeLessThan(array_search('B', $ids, true))
        ->and(array_search('B', $ids, true))->toBeLessThan(array_search('C', $ids, true))
        ->and($graph->hasCycle())->toBeFalse()
        ->and($graph->unresolvedReferences())->toBe([]);
});

it('detects the cyclic fixture with a valid cycle path', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('cyclic-step-deps.yaml'));
    $graph = DependencyGraph::fromWorkflow($doc->workflows[0]);

    expect($graph->hasCycle())->toBeTrue();
    $cycle = $graph->firstCycle();
    expect($cycle)->not->toBeNull()
        ->and(array_unique(array_slice($cycle, 0, -1)))->toEqualCanonicalizing(['A', 'B'])
        ->and($cycle[0])->toBe($cycle[array_key_last($cycle)]);
});

it('reports unresolved refs for the unresolved fixture', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('unresolved-step-ref.yaml'));
    $graph = DependencyGraph::fromWorkflow($doc->workflows[0]);

    expect($graph->unresolvedReferences())->toBe(['A' => ['ghost']]);
});

it('mines refs across every scanned field via implicit-refs fixture', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('implicit-refs-across-fields.yaml'));
    $graph = DependencyGraph::fromWorkflow($doc->workflows[0]);

    expect($graph->dependenciesOf('consumer'))->toBe(['source']);
});

it('validator rule flags the cyclic fixture', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('cyclic-step-deps.yaml'));
    $ruleset = new RuleSet([new StepDependencyNoCycleRule()]);
    $result = (new Validator($ruleset))->validate($doc);

    $codes = array_map(fn ($e) => $e->code, $result->errors());
    expect($codes)->toContain('step.dependency_no_cycle');
});

it('validator rule flags the unresolved fixture', function () {
    $doc = (new Parser())->parse(loadDepGraphFixture('unresolved-step-ref.yaml'));
    $ruleset = new RuleSet([new StepDependencyNoCycleRule()]);
    $result = (new Validator($ruleset))->validate($doc);

    $codes = array_map(fn ($e) => $e->code, $result->errors());
    expect($codes)->toContain('step.dependency_unresolved');
});
```

- [ ] **Step 3: Run acceptance test**

Run: `vendor/bin/pest tests/Feature/DependencyGraphAcceptanceTest.php`
Expected: all PASS.

Run: `make verify` (full pre-push gate)
Expected: pint clean, phpstan clean, pest green.

- [ ] **Step 4: Commit**

```bash
git add tests/fixtures/parser/out-of-order-refs.yaml tests/fixtures/parser/cyclic-step-deps.yaml tests/fixtures/parser/unresolved-step-ref.yaml tests/fixtures/parser/implicit-refs-across-fields.yaml tests/Feature/DependencyGraphAcceptanceTest.php
git commit -m "test(execution): DependencyGraph acceptance fixtures + E2E"
```

---

### Task 8: CHANGELOG + Ship

**Files:**
- Modify: `CHANGELOG.md`
- Delete via `ship-plan.sh`: `docs/superpowers/roadmap/backend/phase-0-foundation/core-37-dependency-graph.md`
- Move via `ship-plan.sh`: plan + spec to `shipped/`

**Interfaces:**
- Consumes: nothing.
- Produces: `## Unreleased` gets `### Added` / `### Changed` / `### Fixed` entries; roadmap stub deleted; plan/spec relocated.

- [ ] **Step 1: Add CHANGELOG entries**

Modify `CHANGELOG.md`. Under `## Unreleased`:

```markdown
### Added

- `DependencyGraph` (`Alama\LaravelArazzo\Execution\DependencyGraph`) — per-workflow step DAG with topological ordering + cycle + unresolved-ref detection. Honors both explicit `Step::$dependsOn` and implicit `{$steps.X.outputs.Y}` refs.
- `StepRefExtractor` — mines step-id references from Expression / Selector fields via the existing Expression AST.
- `StepDependencyNoCycleRule` validator rule (emits `step.dependency_no_cycle` and `step.dependency_unresolved`).
- `ExecutionException` base + `CyclicDependencyException`, `UnresolvedReferenceException`.

### Changed

- `WorkflowExecutor::execute()` now iterates steps in topological order derived from `DependencyGraph`, no longer in `$workflow->steps` array order. Cyclic or unresolved-ref workflows throw before step execution begins.
- `DependencyAnalyzer` constructor now requires a `DependencyGraph`. Async `Engine` builds and caches the graph per workflow and injects it. Async execution now honors implicit expression refs (previously only explicit `dependsOn`).
- `Engine::__construct` no longer accepts a `DependencyAnalyzer` (built internally per workflow). Downstream consumers constructing `Engine` directly must drop the analyzer argument.

### Fixed

- Sync workflow execution: steps declared out of order no longer read stale / missing values from prior runs.
```

- [ ] **Step 2: Commit CHANGELOG**

```bash
git add CHANGELOG.md
git commit -m "docs(changelog): DependencyGraph landed"
```

- [ ] **Step 3: Run pre-push gate one last time**

Run: `make verify`
Expected: pint clean, phpstan clean, pest green.

- [ ] **Step 4: Ship the plan**

Run: `scripts/ship-plan.sh core-37-dependency-graph`
Expected: plan + spec move to `shipped/`, roadmap stub deleted, `## Unreleased` → `### Shipped` bullet appended in `CHANGELOG.md`.

- [ ] **Step 5: Verify final state**

Run: `git status`
Expected: clean working tree.
Run: `git log --oneline -12`
Expected: task 1-8 commits + ship commit visible.

- [ ] **Step 6: Push branch + open PR**

(User decides when to push. Do not push automatically.)

---

## Self-Review

**Spec coverage:**

- Problem section: covered — Task 4 (sync fix) + Task 5 (async implicit-ref fix).
- Approach — vertical slice: covered — Task 1 (extractor) → Task 3 (graph) → Task 4 (sync wire) → Task 5 (async wire) → Task 6 (validator) → Task 7 (acceptance).
- Architecture: files listed match layer additions (new `Execution/StepRefExtractor`, `Execution/DependencyGraph`, `Exceptions/*`, `Validation/Rules/StepDependencyNoCycleRule`; modified `Execution/WorkflowExecutor`, `Execution/DependencyAnalyzer`, `Execution/Engine`).
- API: `fromWorkflow`, `topologicalOrder`, `firstCycle`, `hasCycle`, `dependenciesOf`, `unresolvedReferences` — all implemented in Task 3.
- Helper: `StepRefExtractor::fromStep` — Task 1.
- Exceptions: `CyclicDependencyException::fromPath`, `UnresolvedReferenceException::fromRefs`, `ExecutionException` base — Task 2.
- Algorithm: DFS three-color exactly as spec pseudocode — Task 3, Step 3.
- Integration: sync executor (Task 4), async engine + analyzer (Task 5), validator rule (Task 6).
- Testing: per-file suites (Tasks 1, 3, 4, 5, 6) + fixtures + E2E (Task 7).
- CHANGELOG + Migration: Task 8.
- Acceptance gates: run in Tasks 7-8.

**Placeholder scan:** searched for TBD / TODO / FIXME / XXX / "similar to Task N" / "implement later" — one intentional annotation in Task 1 Step 3 about the duck-typed Selector branch (documented rationale, not a placeholder). No plan-level TBDs.

**Type consistency:**

- `DependencyGraph` static factory + accessor method names — consistent across Tasks 3, 4, 5, 6, 7.
- `StepRefExtractor::fromStep(Step): list<string>` — consistent across Tasks 1, 3.
- Exception factories `fromPath` / `fromRefs` — consistent across Tasks 2, 4, 5, 6.
- `Engine` constructor drops `DependencyAnalyzer` parameter — flagged in Task 5 with a grep sweep for consumer updates.
- `DependencyAnalyzer` constructor takes `DependencyGraph` — flagged in Task 5 with a grep sweep.

**Gaps found + closed:**

- Task 1's duck-typed `Selector` branch: documented rationale (allows this stub to ship independent of core-34). Once core-34 lands, a follow-up ticket may harden it to a real `instanceof`.
- Task 5's spy classes for `QueueDriverInterface` / `StateStoreInterface`: reader must inspect real interface signatures — flagged in Step 1.
- `ErrorCollector`'s error-code accessor shape: reader must confirm in Task 6 Step 1 — flagged.

Every spec requirement traces to at least one task step.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-25-core-37-dependency-graph.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
