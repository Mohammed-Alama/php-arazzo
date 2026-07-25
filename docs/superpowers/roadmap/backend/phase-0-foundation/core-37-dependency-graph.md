# `DependencyGraph` — Topological Ordering + Cycle Detection

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Known debt from `CHANGELOG.md` (workflow-executor plan promised, never built).

## Problem

`WorkflowExecutor::execute()` iterates `$workflow->steps` in array order. Wrong for any
workflow that declares out-of-order dependencies via `$steps.<earlier>.outputs.*`
references. The async path (`Engine::evaluate()` + `DependencyAnalyzer`) uses proper
dispatch, but the sync path silently runs steps in the wrong order and either fails at
runtime with a missing-reference error or (worse) reads a stale value from a prior run.

## Feature

Add `Alama\Arazzo\Execution\DependencyGraph`:

```php
final class DependencyGraph
{
    public static function fromWorkflow(Workflow $wf, ExpressionResolver $r): self;
    public function topologicalOrder(): iterable;   // Step[]
    public function detectCycles(): array;          // string[] cycle paths, empty if DAG
    public function dependenciesOf(string $stepId): array;
}
```

Wire it into `WorkflowExecutor::execute()` before the step loop. On cycle: throw
`CyclicDependencyException` with the cycle path. On unresolved reference: throw
`UnresolvedReferenceException` naming the step + expression.

Reuses `DependencyAnalyzer` logic already in the async engine — extract to a shared
utility rather than duplicate.

## Acceptance

- New fixture `tests/fixtures/parser/out-of-order-refs.yaml` — steps declared C, A, B
  where B depends on A, C depends on B — executes correctly.
- Cyclic fixture (A→B→A) fails with `CyclicDependencyException` naming both steps.
- Existing linear-order workflows still pass without behavior change.

## Out of scope

- Parallel execution of independent branches — separate stub (`exec-08-fan-out-in`).
- Visual graph rendering — `obs-15-graph-explorer` (frontend).
