# Design — Extract `alama/arazzo-request-pipeline` (Plan Task E3)

Date: 2026-09-27
Status: approved section-by-section; implementation not yet planned
Issue: to be created during `writing-plans`
Related: `../plans/2026-09-08-phase-e-runner-oms.md` (Task E3),
`2026-09-26-document-package-split-design.md`,
`2026-09-23-expression-evaluation-package-separation-design.md`,
`2026-09-07-migrate-cli-laravel-public-faces-design.md`

## Context

Phase E splits `packages/runner` by volatility. Tasks E1 (`arazzo-runtime`)
and E2 (`arazzo-events`) have landed as `47c108a` and `4a8cf22`. E3 extracts
the shared request-compilation pipeline, which sits one layer above the
engine and below the protocol packages.

The plan's own framing is sound: the pipeline has consumers in both the sync
and async paths, and extracting it before the protocol packages prevents either
side from owning a shared concern. This spec records the design **after
validating the plan's Task E3 against the tree**, which surfaced four points
where the plan's literal instructions are wrong or vacuous. Three were decided
with the user; one is recorded as a follow-up.

## Evidence

Every claim below was read out of the tree or executed, not inferred.

### The plan names nine classes but says "eleven"

The plan's move list enumerates nine files, then instructs "move the eleven
classes above". The count is wrong, but the gap is real and load-bearing:

| Consumer | Imports from `Runner\Execution\Data\` |
| --- | --- |
| `ExpressionValueResolver` | `Data\ExecutionEvaluationInput` |
| `ExecutionExpressionResolver` | `Data\ExecutionEvaluationInput` |
| `IdempotencyKeyInjector` | `Data\InjectionResult` |

Three of the nine depend on value objects that the plan does not mention. Moving
the nine alone would leave the new package with `Alama\Arazzo\Runner` imports
and the arch guard would correctly fail. The intended count of eleven is right;
the list is incomplete by exactly these two.

`ExecutionEvaluationInput` is a pure value object implementing evaluation's own
`EvaluationInputInterface`, with no runner dependencies. It has five further
consumers that stay in the runner (`AsyncApiStepExecutor`,
`SubWorkflowStepExecutor`, `SubWorkflowInvoker`, `StepOutputExtractor`,
`StepOutcomeHandler`). `InjectionResult` has one consumer, `IdempotencyKeyInjector`.

### `SchemaValidator` contradicts the plan's own vendor-free rule

The plan states "alama/arazzo-request-pipeline stays vendor-free" and
simultaneously schedules `SchemaValidator` for the move. `SchemaValidator`
imports `cebe\openapi\spec\Reference` and `cebe\openapi\spec\Schema` and is a
static utility whose every public method takes a cebe `Schema`. Honouring the
move list would make the package non-vendor-free.

The same plan already resolves this class of problem the other way:

> `StepOutputExtractor` and `ResponseSchemaValidator` do **not** move here: both
> are OpenAPI-specific … Phase F's F1.2 relocates them into
> `alama/arazzo-protocol-http` instead.

`SchemaValidator` is indistinguishable from those two — it reads the cebe
`Operation` graph that D0 moved behind `OpenApiOperationHandle`. None of the
eleven movers reference it, so leaving it costs the extraction nothing.

### The plan's arch guard would pass vacuously

The plan proposes:

```php
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse(['Alama\Arazzo\Engine', 'Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol']);
```

Probing Pest's `toUse()` matcher against the live tree shows it matches
**fully-qualified class names, not namespace prefixes**. A bare namespace
resolves to nothing and the negative assertion passes for the wrong reason:

| Probe | Result | Meaning |
| --- | --- | --- |
| `expect('Alama\Arazzo\Runner')->toUse('cebe')` | fails | bare namespace matches nothing |
| `expect('Alama\Arazzo\Runner')->toUse('cebe\openapi\spec\Schema')` | passes | real class name is detected |
| `expect('Alama\Arazzo\Events\Listener')->toUse('Alama\Arazzo\Runner')` | fails | bare namespace matches nothing |
| `expect('Alama\Arazzo\Runner\Execution')->toUse('Alama\Arazzo\Runner\Execution\Data\\')` | passes | trailing backslash is a prefix match |
| `expect('Alama\Arazzo\Runner\Execution')->not->toUse('Alama\Arazzo\Runner\Execution\Data\\')` | fails, as intended | bites on real usage |
| `expect('Alama\Arazzo\Runner\Execution')->not->toUse('Alama\Arazzo\Engine\\')` | passes | clean namespace stays clean |

The trailing-backslash form bites for internal namespaces, so the guard is
written with it. `Alama\Arazzo\Runner\` transitively covers
`Alama\Arazzo\Runner\Protocol\*`, so no separate protocol entry is needed.

This also means the plan's Step 3 instruction — "verify it bites with a
temporary forbidden import, then revert" — is load-bearing, not ceremonial.

### The two-consumer invariant holds for one class of nine

Plan Step 3 expects only `StepExecutor`, `Protocol\HttpStepExecutor` and the
Laravel bindings to reference `RequestCompiler`. Measured src consumers:

| Class | Consumers outside itself |
| --- | --- |
| `RequestCompiler` | `Protocol\HttpStepExecutor`, `Execution\StepExecutor` |
| `ParameterSerializer` | `DefaultOpenApiExecutor` |
| `TypeCaster` | `StepOutputExtractor`, `DefaultOpenApiExecutor` |
| `ExpressionValueResolver` | `HttpStepExecutor`, `StepExecutor`, `RequestCompiler` |
| `ExecutionExpressionResolver` | `AsyncExecutionGraphAssembler`, `ExecutionGraphFactory` |
| `IdempotencyKeyInjector` | `HttpStepExecutor`, `StepExecutor`, `AsyncExecutionGraphAssembler` |
| `StepParameterMerger` | `StepExecutionWorker`, `WorkflowExecutor` |
| `ReusableParameterResolver` | `AsyncApiStepExecutor`, `SubWorkflowStepExecutor`, `RequestCompiler` |

Only `RequestCompiler` has exactly two, and there are no Laravel or CLI
references to any of the nine. The invariant that actually holds — and that
matters for the boundary — is **every consumer sits in `runner`**, which the
package's own dependencies already permit. The check is restated accordingly.

### Five of the eleven movers have no direct test

| Class | Dedicated test |
| --- | --- |
| `RequestCompiler` | none |
| `ExpressionValueResolver` | none |
| `ExecutionExpressionResolver` | none |
| `ExecutionEvaluationInput` | none |
| `InjectionResult` | none |
| `ParameterSerializer` | `ParameterSerializerTest` |
| `TypeCaster` | `TypeCasterTest` |
| `IdempotencyKeyInjector` | `IdempotencyKeyInjectorTest` |
| `StepParameterMerger` | `StepParameterMergerTest` |
| `ReusableParameterResolver` | `ReusableParameterResolverTest` |

`RequestCompiler` — the namesake of the package — is untested. The only
repo-wide reference to that name is `RegressionTest.php` asserting an unrelated
legacy class is absent. Existing runner suites (`HttpStepExecutorTest`,
`StepExecutorTest`, `AdapterParityTest`) cover the pipeline indirectly, which is
enough to prove a move preserves behaviour, but not enough to leave the new
package's own suite honest.

## Decisions

### D1 — `SchemaValidator` stays in the runner for F1.2

Vendor-free wins. It is OpenAPI-specific by the same test the plan already
applies to `ResponseSchemaValidator` and `StepOutputExtractor`, it is not
referenced by any mover, and moving it would contradict the plan's stated
constraint to satisfy a file list that is itself incomplete. Relocates to
`alama/protocol-http` in F1.2 with its two siblings.

### D2 — the two `Data/` value objects move into the pipeline

`ExecutionEvaluationInput` and `InjectionResult` become
`Alama\Arazzo\RequestPipeline\Data\*`. Required, not optional: leaving them
behind puts `Alama\Arazzo\Runner` imports inside the new package.

For `ExecutionEvaluationInput` the alternative was `arazzo/evaluation`, beside
the interface it implements. The pipeline was chosen because it is what owns the
evaluation seam after this move — `ExpressionValueResolver` and
`ExecutionExpressionResolver`, which both construct this value object, land
there. The five runner consumers import it from the pipeline, a direction the
runner already depends on. Its docblock's "Runner-owned evaluation input" claim
is corrected as part of the move.

### D3 — the move ships with direct tests for the five untested classes

`RequestCompilerTest`, `ExpressionValueResolverTest`,
`ExecutionExpressionResolverTest`, `ExecutionEvaluationInputTest` and
`InjectionResultTest` are added under `packages/request-pipeline/tests/`. This
widens E3 beyond a pure `refactor`, which is why the work is split across two
commits (below) rather than forced into the plan's single refactor message.

## Design

**Package.** `packages/request-pipeline/`, composer name
`alama/arazzo-request-pipeline`, PSR-4 `Alama\Arazzo\RequestPipeline\` → `src/`,
autoload-dev `Alama\Arazzo\Tests\RequestPipeline\` → `tests/`. Requires
`alama/arazzo-contracts`, `alama/arazzo-document`, `alama/arazzo-expression`,
`alama/arazzo-evaluation`, `psr/http-client`, `psr/http-factory`,
`psr/http-message`. Mirrors `packages/events` and `packages/runtime`, which
carry no local `repositories` block because the root path repositories cover
them.

**Moves (11).** Flattened out of `Execution/` into `src/`, plus `src/Data/` for
the two value objects: `RequestCompiler`, `ParameterSerializer`, `TypeCaster`,
`ExpressionValueResolver`, `ExecutionExpressionResolver`,
`IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver`,
`Data\ExecutionEvaluationInput`, `Data\InjectionResult`.

**Stays in runner.** `SchemaValidator`, `ResponseSchemaValidator`,
`StepOutputExtractor`, `DefaultOpenApiExecutor`, `Interfaces\OpenApiExecutorInterface`,
and the remainder of `Execution/`.

**Namespace rewrite.** `Alama\Arazzo\Runner\Execution` →
`Alama\Arazzo\RequestPipeline` on the eleven moved files only. The runner keeps
its own `Execution\` namespace; Phase F2 splits it into `Sync\` and `Async\`.
Every `Runner\Execution\...` hit is classified by hand — no blanket replace.

**Tests.** `tests/` flattened to mirror `src/`: five moved
(`ParameterSerializerTest`, `TypeCasterTest`, `IdempotencyKeyInjectorTest`,
`StepParameterMergerTest`, `ReusableParameterResolverTest`), five new per D3,
plus `tests/Architecture/ArchTest.php` and `tests/Pest.php`.

**Arch guard.**

```php
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse([
        'Alama\Arazzo\Engine\\',
        'Alama\Arazzo\Runner\\',
    ]);
```

Verified to bite per the probe table above, by injecting a temporary forbidden
import and confirming the failure before reverting.

**Plumbing.** Root `composer.json` (path repository, `require`,
`Alama\Arazzo\Tests\RequestPipeline\` autoload-dev, `analyse-pipeline` /
`test-pipeline`, and entries in the `test` and `analyse` script arrays);
`phpunit.xml.dist` (testsuite + source directory);
`scripts/generate-docs/Scanner.php` and `scripts/quality-gates.php`;
`packages/core/tests/GeneratedDocsSnapshotTest.php` — its expected layer order
is **regenerated**, not hand-guessed, and shifts to include the new layer.

**Commits.** Two, so the mechanical move stays reviewable in isolation:

1. `refactor(request-pipeline): extract request compilation pipeline into arazzo-request-pipeline`
2. `test(request-pipeline): cover the previously untested pipeline classes`

Both are verified with `make verify` and committed through normal hooks — no
`--no-verify`.

## Deliberately not resolved here

- **Vacuous vendor guards in `packages/runner/tests/ArchTest.php`.**
  `not->toUse('cebe')` and `not->toUse('Illuminate')` are bare-namespace forms
  and pass vacuously. The runner genuinely uses cebe in `ResponseSchemaValidator`,
  `SchemaValidator` and `StepOutputExtractor`, so the `cebe` guard is green for
  the wrong reason. Repairing it means naming real classes
  (`cebe\openapi\spec\Operation`, `Illuminate\Support\Collection`) — which turns
  the cebe guard **red** until those three OpenAPI classes relocate to
  `protocol-http` in F1.2.
  Folded into F1.2, not into E3. Note the trailing-backslash form does **not**
  work for vendor namespaces either; real class names are required there.
- **`OpenApiExecutorInterface`'s home.** The plan defers it to F1.2 as a BC alias
  of the protocol-http canonical. Unchanged here.
- **`ExecutionExpressionResolver` vs `ExpressionValueResolver`.** Both move and
  both stay, despite overlapping responsibility — collapsing them is a
  simplification decision, not an extraction one.
- **The `WorkflowContext` / `WorkflowContextInterface` split.** Of the eleven
  movers, `RequestCompiler`, `ExpressionValueResolver` and
  `IdempotencyKeyInjector` type-hint `Contracts\State\WorkflowContext`, while
  `ExecutionExpressionResolver` and `ExecutionEvaluationInput` use
  `Contracts\Spec\Interfaces\WorkflowContextInterface`. Pre-existing; left
  alone to keep E3 a move. The move does make it more visible, since all five
  now sit in one namespace.
