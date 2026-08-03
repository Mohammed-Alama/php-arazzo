# `DependencyGraph` — Topological Ordering + Cycle Detection — Design

Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/core-37-dependency-graph.md`](../roadmap/backend/phase-0-foundation/core-37-dependency-graph.md)
Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Closes known workflow-executor debt.

## Problem

`WorkflowExecutor::execute()` iterates `$workflow->steps` in array-declaration order. This
is wrong for any workflow whose steps declare out-of-order dependencies — either via
explicit `Step::$dependsOn` or via implicit `{$steps.<earlier>.outputs.*}` expression
references. The async path (`Engine::evaluate()` + `DependencyAnalyzer`) picks runnable
steps by explicit `dependsOn` only, missing implicit refs entirely. The sync path fails at
runtime with a missing-reference error, or worse, reads a stale value from a prior run.

No step-level cycle detection exists anywhere. `WorkflowDependsOnNoCycleRule` covers
workflow-to-workflow cycles only.

## Approach

New utility `Alama\LaravelArazzo\Execution\DependencyGraph` owns the per-workflow step-DAG.
It mines both explicit `dependsOn` and implicit expression refs, detects the first cycle
via DFS three-color (matching existing `WorkflowDependsOnNoCycleRule` style), and produces
a topological ordering.

Integration touches three sites:

- **Sync `WorkflowExecutor`** — builds graph before step loop, throws on cycle / unresolved
  ref, iterates topological order.
- **Async `Engine` + `DependencyAnalyzer`** — refactored to consult graph. Analyzer's
  `getRunnableSteps()` API preserved; implementation delegates. Async now honors implicit
  refs (bug fix side-effect).
- **Validator** — new rule `StepDependencyNoCycleRule` builds graph per workflow at parse
  time, reports cycles + unresolved refs. Parse-time gate + runtime safety net.

Expression mining reuses existing `Alama\LaravelArazzo\Expression\Lexer` + `Parser`. Small
new helper `StepRefExtractor::fromStep(Step)` walks step fields, parses each Expression /
Selector into the AST, extracts `$steps.<id>` symbols.

Zero changes to public DTOs. Zero changes to Arazzo document semantics.

## Architecture

Layer additions:

- **Execution**: `DependencyGraph`, `StepRefExtractor` (new); `WorkflowExecutor`,
  `DependencyAnalyzer`, `Engine` (modified).
- **Validation**: `StepDependencyNoCycleRule` (new).
- **Exceptions**: `CyclicDependencyException`, `UnresolvedReferenceException` (new; extend
  `ExecutionException`).

Total validator rule count: 40 → 41.

## API

```php
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;

final class DependencyGraph
{
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

    public static function fromWorkflow(Workflow $wf): self;

    /** @return list<Step> topological order: deps before dependents */
    public function topologicalOrder(): array;

    /** @return list<string>|null null when DAG; else path like ['A','B','C','A'] */
    public function firstCycle(): ?array;

    public function hasCycle(): bool;

    /** @return list<string> direct dep stepIds; empty if none */
    public function dependenciesOf(string $stepId): array;

    /** @return array<string, list<string>> stepId -> missing dep stepIds referenced but not defined */
    public function unresolvedReferences(): array;
}
```

The stub's original signature included an `ExpressionResolver` parameter. Removed: the
graph parses expressions for step-ref extraction, not for resolution against runtime
scope. `fromWorkflow(Workflow)` is sufficient.

**Helper** (`src/Execution/StepRefExtractor.php`):

```php
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;

final class StepRefExtractor
{
    /** @return list<string> deduped list of $steps.<id> references in this step */
    public static function fromStep(Step $step): array;
}
```

Fields scanned:

- `Step::$parameters[*]->value` — Expression, Selector, or scalar.
- `Step::$requestBody->payload` and `->replacements[*]->value`.
- `Step::$successCriteria[*]->condition` — parsed as Expression.
- `Step::$correlationId` — Expression.
- `Step::$outputs[*]` — Expression or Selector.
- `Step::$onSuccess[*]` / `$onFailure[*]` — for `SubWorkflow*Action::$parameters` values.

For each `Expression`, the extractor lexes → parses → walks AST for `$steps.<id>` symbol.
For each `Selector`, extracts refs from `Selector::$context` string (if not null) using the
same path. Returns deduped `list<string>`.

**Exceptions:**

```php
namespace Alama\LaravelArazzo\Exceptions;

final class CyclicDependencyException extends ExecutionException
{
    /** @param list<string> $path */
    public static function fromPath(string $workflowId, array $path): self;
}

final class UnresolvedReferenceException extends ExecutionException
{
    /** @param array<string, list<string>> $refs stepId -> missing dep stepIds */
    public static function fromRefs(string $workflowId, array $refs): self;
}
```

If `ExecutionException` doesn't yet exist in the codebase, create it under
`src/Exceptions/ExecutionException.php` extending `ArazzoException` with the same
`(message, pointer, code)` constructor shape used elsewhere.

## Algorithm

**Build** (`DependencyGraph::fromWorkflow`):

```
edges = []
stepsById = []
unresolvedRefs = []

for each Step s in wf.steps:
    stepsById[s.stepId] = s
    edges[s.stepId] = []

for each Step s:
    deps = set()
    for id in s.dependsOn:
        deps.add(id)
    for id in StepRefExtractor::fromStep(s):
        deps.add(id)

    missing = []
    for id in deps:
        if id == s.stepId:
            continue                # self-ref silently dropped
        if id in stepsById:
            edges[s.stepId].push(id)
        else:
            missing.push(id)
    if missing != []:
        unresolvedRefs[s.stepId] = missing

return new self(edges, stepsById, unresolvedRefs)
```

Self-ref (`s depends on s`) silently dropped so cycle detection focuses on multi-node
cycles. If a "step depends on itself" surface diagnostic is wanted later, add a separate
rule; not in scope here.

**Cycle detect + topological order** (single DFS pass, computed lazily and cached):

```
color = { id => WHITE for id in stepsById }   # WHITE=0, GREY=1, BLACK=2
stack = []                                     # DFS path (for cycle path extraction)
order = []                                     # postorder = deps-before-dependents
cyclePath = null

function dfs(id):
    color[id] = GREY
    stack.push(id)
    for dep in edges[id]:
        if color[dep] == GREY:
            idx = stack.indexOf(dep)
            cyclePath = stack.slice(idx) + [dep]
            return true
        if color[dep] == WHITE and dfs(dep):
            return true
    color[id] = BLACK
    stack.pop()
    order.push(id)                              # postorder: deps first, dependent last
    return false

for id in stepsById.keys():                     # declaration order
    if color[id] == WHITE and dfs(id):
        break
```

Notes:

- Edge semantics: `edges[dependent] = [deps]`. DFS visits deps first. Appending on postorder
  yields deps-before-dependents naturally (no reverse needed).
- Cycle path extraction: when we hit a grey neighbor `dep`, `dep` is on `stack`. Slice
  from `dep`'s index to end, append `dep` → `['A','B','C','A']`.
- Deterministic iteration order: `stepsById` iterates in insertion order (`wf.steps`
  declaration order). Output is stable across runs.
- `topologicalOrder()` and `firstCycle()` are pure accessors on cached results after the
  first DFS pass.

**Complexity:** O(V + E) where V = step count, E = total dep edges. Workflow step counts
are small (typically <50); performance is trivial.

**Edge cases:**

- Empty workflow → empty order, no cycle.
- Single step, no deps → single-element order.
- Cyclic doc → `topologicalOrder()` returns partial order (only BLACK-marked nodes).
  Callers must check `hasCycle()` first.
- Unresolved refs contribute zero edges — reported separately via
  `unresolvedReferences()`.

## Integration

**Sync `WorkflowExecutor::execute()`** — replace step loop preamble:

```php
public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs): ExecutionResult
{
    $graph = DependencyGraph::fromWorkflow($workflow);

    if ($graph->hasCycle()) {
        throw CyclicDependencyException::fromPath($workflow->workflowId, $graph->firstCycle());
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
        // existing per-step logic unchanged
    }

    return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
}
```

**Async `Engine::evaluate()`** — build graph once per workflow, cache, inject into
analyzer:

```php
public function evaluate(Workflow $workflow, WorkflowContext $context): void
{
    $graph = $this->graphs[$workflow->workflowId]
        ??= DependencyGraph::fromWorkflow($workflow);

    if ($graph->hasCycle()) {
        throw CyclicDependencyException::fromPath($workflow->workflowId, $graph->firstCycle());
    }
    if ($graph->unresolvedReferences() !== []) {
        throw UnresolvedReferenceException::fromRefs($workflow->workflowId, $graph->unresolvedReferences());
    }

    $analyzer = new DependencyAnalyzer($graph);
    $runnable = $analyzer->getRunnableSteps($workflow->steps, $context);
    // existing dispatch loop unchanged
}
```

**`DependencyAnalyzer`** — constructor gains `DependencyGraph`; body consults graph:

```php
final class DependencyAnalyzer
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
            foreach ($this->graph->dependenciesOf($step->stepId) as $depId) {
                if ($context->getStepStatus($depId) !== StepStatus::Succeeded) {
                    continue 2;
                }
            }
            $runnable[] = $step;
        }
        return $runnable;
    }
}
```

This is a **breaking internal API change** — any test that constructs `DependencyAnalyzer`
directly needs updating. Public engine API unchanged.

**Validator rule** (`src/Validation/Rules/StepDependencyNoCycleRule.php`):

```php
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

            if (($cycle = $graph->firstCycle()) !== null) {
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

Single rule, two error codes: `step.dependency_no_cycle` and
`step.dependency_unresolved`. Both emitted per-workflow when present.

## Testing

**Fixtures** (`tests/fixtures/parser/`):

- `out-of-order-refs.yaml` — steps declared C, A, B. B depends on A via `dependsOn`.
  C references B's output via `{$steps.B.outputs.x}` in a parameter. Executes in A→B→C
  order.
- `cyclic-step-deps.yaml` — steps A, B. A explicitly `dependsOn: [B]`. B implicitly
  depends on A via `{$steps.A.outputs.x}` expression. Cycle path `['A', 'B', 'A']` (or
  `['B', 'A', 'B']` depending on DFS start; test asserts the set, not the specific rotation).
- `unresolved-step-ref.yaml` — step A references `{$steps.ghost.outputs.x}` in a
  parameter. Reports unresolved ref for `ghost`.
- `implicit-refs-across-fields.yaml` — one step referencing another via each of:
  parameter value, request body payload, request body replacement, success criterion
  condition, correlationId, output expression, sub-workflow invoke parameters, Selector
  context. Proves extractor covers every scanned field.

**Test suites** (Pest):

- `tests/Execution/DependencyGraph/BuildTest.php` — explicit deps, implicit deps per
  field type, dedup, self-ref dropped, empty workflow.
- `tests/Execution/DependencyGraph/TopologicalOrderTest.php` — linear, diamond,
  out-of-order declaration, single node, empty.
- `tests/Execution/DependencyGraph/CycleDetectionTest.php` — no cycle, simple 2-cycle,
  3-cycle, self-loop-not-treated-as-cycle, cycle path shape.
- `tests/Execution/DependencyGraph/UnresolvedReferencesTest.php` — missing target step,
  mixed missing + present, no missing.
- `tests/Execution/StepRefExtractorTest.php` — each field type, Selector context,
  SubWorkflow invoke parameters, nested Expression, non-expression string.
- `tests/Execution/WorkflowExecutorOrderingTest.php` — `out-of-order-refs.yaml` runs
  A→B→C. Cyclic fixture throws `CyclicDependencyException` with expected path.
  Unresolved fixture throws `UnresolvedReferenceException`.
- `tests/Execution/DependencyAnalyzerTest.php` — refactored analyzer produces same
  runnable-step results as before for explicit deps; also honors implicit refs (new
  behavior).
- `tests/Validation/Rules/StepDependencyNoCycleRuleTest.php` — validates cyclic fixture
  → error with `step.dependency_no_cycle` code; unresolved fixture → error with
  `step.dependency_unresolved` code; out-of-order-refs → clean.

**Regression sweep:**

- Full existing Pest suite green.
- `WorkflowDependsOnNoCycleRule` (workflow-level) untouched.
- Existing `DependencyAnalyzer` tests updated to pass a `DependencyGraph` into the
  constructor (breaking test signature — internal API only, no public API impact).

## Migration + CHANGELOG

CHANGELOG under `## Unreleased`:

`### Added`

- `DependencyGraph` — topological ordering + cycle detection for workflow steps, honoring
  both explicit `dependsOn` and implicit `{$steps.X.outputs.Y}` expression refs.
- `StepRefExtractor` — mines step-id references from Expression / Selector fields via the
  existing Expression AST.
- `StepDependencyNoCycleRule` validator rule (codes: `step.dependency_no_cycle`,
  `step.dependency_unresolved`).
- `CyclicDependencyException`, `UnresolvedReferenceException`.

`### Changed`

- `WorkflowExecutor::execute()` now iterates steps in topological order derived from
  `DependencyGraph`, no longer array-order. Cyclic or unresolved-ref workflows throw
  before step execution begins.
- `DependencyAnalyzer` constructor now requires a `DependencyGraph`. Async `Engine` builds
  the graph per workflow and injects it. Async execution now honors implicit expression
  refs (previously only explicit `dependsOn`).

`### Fixed`

- Sync workflow execution: steps declared out-of-order no longer read stale or missing
  values from prior runs.

## Acceptance

Matches stub §Acceptance:

1. `tests/fixtures/parser/out-of-order-refs.yaml` executes correctly (A→B→C).
2. Cyclic fixture fails with `CyclicDependencyException` naming both steps in the path.
3. Existing linear-order workflows pass unchanged.
4. PHPStan max level clean (`phpstan.neon.dist`).
5. Full Pest suite green.

## Out of Scope

- Parallel execution of independent branches — `exec-08-fan-out-in`.
- Visual graph rendering — `obs-15-graph-explorer`.
- Cross-workflow dependencies (sub-workflow invoke chains) — graph is per-workflow only.

## References

- Stub: `docs/superpowers/roadmap/backend/phase-0-foundation/core-37-dependency-graph.md`
- Existing style reference: `src/Validation/Rules/WorkflowDependsOnNoCycleRule.php` (DFS
  three-color).
- Existing async wiring: `src/Execution/DependencyAnalyzer.php`, `src/Execution/Engine.php`.
