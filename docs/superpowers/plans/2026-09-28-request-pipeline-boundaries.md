# Request-pipeline boundaries: collapse the expression-resolver seam

> Date: 2026-09-28 · Branch: `phase-e-runner-oms` · Base: `fab433a` (green)

## Context

The evaluation package exposes an `@internal` adapter `ExpressionResolverInterface`
with five methods that downstream packages treat as a public seam:

| Method | Truth |
| --- | --- |
| `evaluate(Expression, WorkflowContextInterface, ?string)` | duplicates `EvaluationEngineInterface::evaluate`, **different signature** |
| `evaluateCriteria(array, Step, ctx, ?doc)` | duplicates `EvaluationEngineInterface::evaluateCriteria`, identical signature |
| `evaluateSuccessCriteria(Step, ctx, ?doc)` | duplicates `EvaluationEngineInterface::evaluateSuccessCriteria`, identical signature |
| `extractOutputs(...)` | delegate into `OutputExtractorInterface` |
| `validateResponseSchema(...)` | delegate into `ResponseValidatorInterface` |

`packages/request-pipeline` then ships a **second adapter** (`ExecutionExpressionResolver`)
that re-implements the same bundle on top of the engine, so two packages maintain one
duplicate contract. Runner/CLI/Laravel/engine service classes type against the resolver
instead of the engine, and the wiring factories (`ExecutionGraphFactory`,
`AsyncExecutionGraphAssembler`) construct the adapter to inject it. There are also two
byte-identical `EvaluationInputInterface` value objects (`Evaluation\Data\EvaluationContext`
and `RequestPipeline\Data\ExecutionEvaluationInput`).

This violates the agreed package rules: one public interface per package for its
functionality; a second interface only for genuine polymorphism; classes owned next to
their consumers; names matching the injected interface.

## Target architecture

- **Evaluation** exposes only `EvaluationEngineInterface` (public face). Internal plumbing
  is the concrete `ExpressionEvaluator` (no interface — single implementation; the
  `@internal` `ExpressionEvaluatorInterface` is deleted). Relationships to output
  extraction/schema validation are deleted from evaluation.
- **Schema validation** stays a contract face (`ResponseValidatorInterface`, contracts
  package): genuinely polymorphic — `ResponseSchemaValidator` plus the
  `ResponseValidatorDispatcher` plugin router (Phase F1). **Output extraction** collapses
  to the concrete runner class `StepOutputExtractor` — it has exactly one implementation
  and every consumer is runner-internal, so no interface is warranted.
- Consumers type directly against the interface they need and name the property after it:
  - `WorkflowEngine`, `StepExecutionWorker`, `CliRunner` → `EvaluationEngineInterface $engine`
  - `StepExecutor`, `HttpStepExecutor` → `EvaluationEngineInterface $engine` +
    `StepOutputExtractor $outputExtractor` (concrete) + `ResponseValidatorInterface $schemaValidator`
  - `CorrelationResumer` → `StepOutputExtractor $outputExtractor` +
    `EvaluationEngineInterface $engine`
- `evaluate()` calls wrap the context in `Evaluation\Data\EvaluationContext`
  (single standard input VO; `ExecutionEvaluationInput` is deleted).
- **`packages/request-pipeline` is removed.** Once the duplicated resolver/VO and helpers
  are gone, every remaining class (`RequestCompiler`, `ExpressionValueResolver`,
  `IdempotencyKeyInjector`, `ReusableParameterResolver`, `StepParameterMerger`,
  `Data/InjectionResult`, `ParameterSerializer`, `TypeCaster`) is consumed only by
  `packages/runner`, so it folds into `Alama\Arazzo\Runner\Execution` and the package +
  its composer wiring cease to exist.
- `AsyncGraphSeams` replaces the nullable `expressionResolver` port with a single
  nullable `ResponseValidatorInterface $schemaValidator` port (same host-override
  semantics as `openApiExecutor`). No extraction port — the assembler always resolves
  `new StepOutputExtractor(...)`, matching today's capabilities (hosts never overrode
  extraction independently). `AsyncExecutionGraph` drops its `expressionResolver()` accessor;
  Laravel re-exports `WorkflowEngine` from the container-bound `EvaluationEngineInterface`.
- `StringInterpolator` depends on the concrete internal `ExpressionEvaluator` and builds
  `EvaluationContext` itself (the `InterpolationResolver` job moves inside it).
- Test doubles implement the interface(s) they now feed. A shared double is renamed
  `TestExpressionResolver` → `TestEvaluationEngine`; per-file doubles convert in place.

### Deferred (out of scope, do not do here)

- `StepParameterMerger::merge()` static (runner-internal colocation candidate, unchanged).
- `IdempotencyInjectorInterface` — deliberately NOT created.
- Moving AST-node evaluation into `Expression\Ast\*` classes (the polymorphic evaluator
  idea): **blocked by a layering cycle** — `expression` would start depending on
  `evaluation` (`EvaluationInputInterface` + `JsonPointer` live there) while `evaluation`
  already compiles `expression` AST. Note: `ExpressionEvaluator` is the single
  `ExpressionEvaluatorInterface` implementor, and all its dependents are evaluation-internal.

## Design decisions (locked)

1. Delete: `ExpressionResolverInterface`, `ExpressionResolver`, `InterpolationResolver`,
   and `ExpressionEvaluatorInterface` (evaluation; all have a single implementation or are
   duplicated); `ExecutionExpressionResolver` + its test (request-pipeline).
2. Do NOT fold schema validation into `EvaluationEngineInterface`: `ResponseValidatorInterface`
   stays a separate seam (`ResponseSchemaValidator` + `ResponseValidatorDispatcher`, Phase F1
   plugin routing). `OutputExtractorInterface` is dropped in favor of the concrete runner
   `StepOutputExtractor` (single implementation; all consumers runner-internal).
3. Standardize on `Evaluation\Data\EvaluationContext`; delete
   `RequestPipeline\Data\ExecutionEvaluationInput` + `ExecutionEvaluationInputTest`.
4. `AsyncGraphSeams` port replacement: nullable `schemaValidator` only (preserves host-override
   for validation); no extraction port.
5. `TestExpressionResolver` (expression test support) renamed to `TestEvaluationEngine`
   implementing `EvaluationEngineInterface` (same stubbed behaviors).
6. `evaluate()` at engine call-sites wraps into `EvaluationContext`; `evaluateCriteria` /
   `evaluateSuccessCriteria` drop straight through (identical signatures).
7. `packages/request-pipeline` is folded into `packages/runner` (flat `Alama\Arazzo\Runner\Execution`):
   the only remaining classes are runner consumers' helpers; the package + its composer
   wiring are deleted. `ParameterSerializer`/`TypeCaster` become instance methods on the way.
   The flat `Runner\Execution` home matches the existing `phpmd.baseline.xml:110-111`
   entries (`packages/runner/src/Execution/ParameterSerializer.php`), so no baseline edit.
   The old transport-agnosticism guard survives as a targeted class-list `toUse()` arch test.
   `phpstan-report.txt` is a stale tracked snapshot with no generator — leave unchanged.
8. Regenerate generated docs (`composer run docs`); update the dead internal-exclusion
   entry in `scripts/generate-docs/PackageContractsDoc.php:72`.

## Verification (gates)

Run from the worktree root after each task; a task is done only when its suites pass.

```bash
composer run test          # core + runner + laravel suites
composer run format        # pint
composer run analyse       # phpstan (tracked baseline, do not delete)
composer run docs          # regenerate docs/generated/* (no hand-edits)
make verify                # full gate stack
.agents/skills/fix-ci/scripts/ci-failures.sh   # PR CI check (ℹ needs `gh`/1Password)
```

Nothing is committed or pushed until the user approves.

## Standard test-double recipe

Any file-local class currently `implements ExpressionResolverInterface` for an actor that
moved to `EvaluationEngineInterface` converts to (keeping its original stubbed behaviors
for `evaluate`/`evaluateCriteria`/`evaluateSuccessCriteria`):

```php
final class XMockEngine implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $expression->raw; // or `true` where the old double returned a constant
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        /* keep existing body (e.g. `return true;` or the MATCH convention) */
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed { return null; }
    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed { return null; }
    public function supportedXPathVersions(): array { return []; }
    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string { return $value; }
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?\Alama\Arazzo\Contracts\State\WorkflowContext $context = null): array { return $body; }
    public function jsonPath(string $expression, array|object $data): mixed { return null; }
    public function jsonPointer(array $data, ?string $pointer): mixed { return null; }
}
```

Where an actor gained `extractOutputs`/`validateResponseSchema` (StepExecutor,
HttpStepExecutor, CorrelationResumer), split the double: `extractOutputs` becomes a
`Mockery::mock(StepOutputExtractor::class)` (non-final; Mockery does not invoke the ctor)
or a tiny anonymous subclass, the old `validateResponseSchema` body becomes a
`ResponseValidatorInterface` class, and the rest becomes the engine double above (or the
real `new EvaluationEngine()` where only criteria-free success matters). A
`Mockery::mock(ExpressionResolverInterface::class)` swaps to
`Mockery::mock(EvaluationEngineInterface::class)` (or the split mocks) with the same
`shouldReceive`s.

## Tasks

### Task 1 — Evaluation-internal: StringInterpolator drops the resolver bundle, evaluator drops its interface (green standalone)

- `packages/evaluation/src/StringInterpolator.php`: ctor type → concrete `ExpressionEvaluator`,
  interpolate builds `new EvaluationContext($context, $stepId)` and calls
  `$this->resolver->evaluate($expr, $ctx)`. Drop `ExpressionResolverInterface`/`ExpressionEvaluatorInterface` imports.
- `packages/evaluation/src/EvaluationEngine.php`: ctor param `ExpressionEvaluatorInterface $evaluator` →
  `ExpressionEvaluator $evaluator`; line 106 `new StringInterpolator($this->evaluator)`.
- `packages/evaluation/src/Condition/ConditionEvaluator.php` and
  `packages/evaluation/src/CriteriaEvaluator.php`: `ExpressionEvaluatorInterface $evaluator` → `ExpressionEvaluator $evaluator`.
- Delete `packages/evaluation/src/InterpolationResolver.php` and
  `packages/evaluation/src/Interfaces/ExpressionEvaluatorInterface.php` (single implementor, all dependents
  evaluation-internal; `ExpressionEvaluator` keeps its `@internal` docblock, drops `implements`).
- `packages/evaluation/tests/StringInterpolatorTest.php`: instantiate
  `new StringInterpolator(new ExpressionEvaluator())` instead of via `InterpolationResolver`.
- `scripts/generate-docs/PackageContractsDoc.php:72`: drop the dead `ExpressionEvaluatorInterface` entry
  (keep `ExpressionEvaluator`).
- Verify: `composer run test-core` (evaluation lives in core suite), `composer run format`.

### Task 2 — Rewire cross-package consumers to the seams (runner + cli + laravel src, then their tests)

**2.1 src**
- `packages/runner/src/Execution/StepExecutor.php`: ctor becomes
  `(OpenApiExecutorInterface $openApiExecutor, OpenApiOperationResolver $operationResolver,
  EvaluationEngineInterface $engine, StepOutputExtractor $outputExtractor,
  ResponseValidatorInterface $schemaValidator, bool $strictValidationDefault = false,
  ?IdempotencyKeyInjector $injector = null, ?EventDispatcherInterface $events = null)`.
  Body: `$this->schemaValidator->validateResponseSchema(...)` (line 93),
  `$this->outputExtractor->extractOutputs(...)` (line 120),
  `$this->engine->evaluateSuccessCriteria(...)` (line 125, identical sig).
  `RequestCompiler::decodeResponse()`/`requestRecord()` become calls on the
  `RequestCompiler` instance already built at line 52 (drop the static calls).
- `packages/runner/src/Protocol/HttpStepExecutor.php`: same ctor + body change;
  `decodeResponse`/`requestRecord` reuse the compiler built at line 43.
- `packages/runner/src/Execution/WorkflowEngine.php`: prop `ExpressionResolverInterface $expressions`
  → `EvaluationEngineInterface $engine`. Line 81 `evaluateCriteria` unchanged; line 121 →
  `$this->engine->evaluate($parameter->value, new EvaluationContext($gotoState->toContext(), $step->stepId))`;
  line 256 → `$this->engine->evaluate($expression, new EvaluationContext($context))`.
- `packages/runner/src/Execution/StepExecutionWorker.php`: ctor position 4 → `EvaluationEngineInterface $engine`.
  Line 170 → `$this->engine->evaluate($step->target->correlationId, new EvaluationContext($context, $step->stepId))`;
  line 196 `evaluateSuccessCriteria` unchanged.
- `packages/runner/src/Execution/CorrelationResumer.php`: ctor position 4 → two params
  `StepOutputExtractor $outputExtractor`, `EvaluationEngineInterface $engine`.
  Line 116 → `$this->outputExtractor->extractOutputs(...)`; line 130 → `$this->engine->evaluateSuccessCriteria(...)`.
- `packages/runner/src/Execution/ExecutionGraphFactory.php`: build `StepOutputExtractor` +
  `ResponseSchemaValidator` directly, pass with named args to `StepExecutor`, and
  `new WorkflowEngine($this->engine)`. Drop `ExecutionExpressionResolver` import/use.
- `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php`: as above; `$outputExtractor =
  new StepOutputExtractor(...)` unconditionally, `$schemaValidator = $seams->schemaValidator
  ?? new ResponseSchemaValidator(...)`; pass engine/outputExtractor/schemaValidator to
  StepExecutor/HttpStepExecutor/CorrelationResumer/StepExecutionWorker; `new AsyncExecutionGraph(...)`
  without `expressionResolver`.
- `packages/runner/src/AsyncGraphSeams.php`: replace
  `public ?ExpressionResolverInterface $expressionResolver = null,` with
  `public ?ResponseValidatorInterface $schemaValidator = null,` (import from `Alama\Arazzo\Contracts\Interfaces`).
- `packages/runner/src/AsyncExecutionGraph.php`: drop `expressionResolver` ctor param, property, and `expressionResolver()` accessor.
- `packages/cli/src/Console/Cli/CliRunner.php`: prop `$expressions` → `EvaluationEngineInterface $engine`;
  `drainUnderSpan()` passes `$this->engine` to `StepExecutionWorker` and `new WorkflowEngine($this->engine)`.
- `packages/laravel/src/Support/AsyncGraphResolver.php:54`: replace resolver line with the bound
  `ResponseValidatorInterface::class` check (extraction is not overridable, matching today's
  assembler default `StepOutputExtractor`); update imports.
- `packages/laravel/src/Bindings/ExecutionBindings.php:41`: `$app->make(EvaluationEngineInterface::class)`
  for the `WorkflowEngine` singleton; add import.

**2.2 tests (runner, cli, laravel)**
- Shared double: rename `packages/expression/tests/Support/TestExpressionResolver.php` →
  `TestEvaluationEngine` (`implements EvaluationEngineInterface`, same behaviors). Update imports/uses in
  `packages/runner/tests/Execution/WorkflowExecutorTest.php`,
  `packages/runner/tests/Execution/WorkflowExecutorEventsTest.php`,
  `packages/runner/tests/Execution/AdapterParityTest.php` (also `expressions: new TestExpressionResolver()`
  → `engine: new TestEvaluationEngine()` at line 140),
  `packages/runner/tests/Execution/SharedBudgetTest.php`, `packages/core/tests/Property/InvariantsTest.php`,
  `packages/cli/tests/Console/Cli/CliRunnerTest.php` (`expressions:` → `engine:` at line 62).
- Convert per-file doubles per the recipe:
  - `WorkflowEngineTest.php` `workflowEngineResolver()` → anonymous `EvaluationEngineInterface` (evaluate→raw, keep others).
  - `StepExecutionWorkerTest.php` `WorkerMockExpressionResolver` → engine double.
  - `StepExecutionWorkerEventsTest.php` `WorkerEventsMockExpressionResolver` → engine double.
  - `StepOutcomeHandlerTest.php` `StepOutcomeMockExpressionResolver` → engine double (keep MATCH convention); drop the
    old `validateResponseSchema`/`extractOutputs`.
  - `StepOutcomeHandlerEventsTest.php` `OutcomeEventsMockExpressionResolver` → engine double.
  - `CorrelationResumerEventsTest.php` `CorrelationResumerEventsExpressionResolver` → engine double + a
    `Mockery::mock(StepOutputExtractor::class)` returning `[]` for `extractOutputs`.
  - `HttpStepExecutorTest.php` `HttpStepExecutorMockResolver` → split: move `lastContextSeenByExtractOutputs`/echoedBody
    into a `Mockery::mock(StepOutputExtractor::class)` double (stubbed `extractOutputs`); engine built from
    `new EvaluationEngine()`; schema tests mock `ResponseValidatorInterface`.
  - `UnifiedStepCarrierTest.php`: delete unused `unifiedTestResolver()` + the two `$resolver = unifiedTestResolver();` lines.
  - `StepOutcomeSubWorkflowRoutingTest.php:46`, `StepOutcomeSelectorOutputsTest.php:32,48`,
    `ReceiveTimeoutTest.php:45`, `CorrelationResumerTest.php:166,191` → `Mockery::mock(EvaluationEngineInterface::class)`
    (split with `Mockery::mock(StepOutputExtractor::class)`/`Mockery::mock(ResponseValidatorInterface::class)` where
    extract/validate behavior is needed).
- `StepExecutorTest.php`: replace resolver mock with `Mockery::mock(StepOutputExtractor::class)` +
  `Mockery::mock(ResponseValidatorInterface::class)`; construction sites move to the new ctor via named args;
  where the header is a real `new EvaluationEngine()`, drop `evaluateSuccessCriteria` mock expectations
  (real engine returns `true` for criteria-less steps).
- `AsyncGraphSeamsTest.php`: drop `expressionResolver:` + anonymous class; `$seams->expressionResolver` assertion →
  `$seams->schemaValidator` toBeNull.
- `AsyncExecutionGraphAssemblerTest.php`: `seams()` drops the `?ExpressionResolverInterface` param + anonymous class;
  drop the `->and($graph->expressionResolver())->not->toBeNull()` assertion (line 193).
- Laravel: `LaravelArazzoServiceProviderBindingsTest.php:80` drop the resolver bind (vestigial);
  `IdempotencyFeatureTest.php:49-52` and `RunExecuteStepJobTest.php:113-116` replace the resolver mock with
  `app()->instance(StepOutputExtractor::class, ...)` (extractOutputs → `[]`); drop `evaluateSuccessCriteria`
  mock (real engine). Remove now-unused `ExpressionResolverInterface` imports.
- Verify: `composer run test` (suite must be green), `composer run format`, `composer run analyse`.

### Task 3 — Delete the resolver bundle and rewire core tests

- Delete `packages/evaluation/src/Interfaces/ExpressionResolverInterface.php`,
  `packages/evaluation/src/ExpressionResolver.php`.
- `packages/core/tests/Conformance/ConformanceHarness.php`: rework `resolver()` → expose the pieces
  (`engine()`, `StepOutputExtractor`, `ResponseSchemaValidator`) directly; drop the 5-method resolver. Update
  `FixtureRunner.php:44,48`, `QueueFixtureRunner.php:45`, `OaiFixtureRunner.php:69,73`,
  `OaiQueueFixtureRunner.php:75` to use engine + outputExtractor + schemaValidator for
  `WorkflowEngine`/`StepExecutor`/`CorrelationResumer` construction.
- `packages/core/tests/Validator/PreflightValidatorTest.php:159-176` and `packages/core/tests/Validator/InputsPreValidationTest.php:72`:
  build `StepExecutor`/`WorkflowEngine` from `new EvaluationEngine()` +
  `StepOutputExtractor` + `ResponseSchemaValidator` directly (drop `new ExpressionResolver` + `CriteriaEvaluator`).
- `packages/core/tests/Unit/Execution/WorkerStubsTest.php:11`: assert `EvaluationEngineInterface::class` instead.
- `packages/engine/tests/StepStateMachineEngineTest.php`: delete unused `engineTestResolver()` + import.
- `packages/runner/tests/Execution/AdapterParityTest.php:86-97` and `WorkflowExecutorTest.php:503-514` (covered in Task 2):
  replace `new ExpressionResolver(...)` with direct engine/outputExtractor/schemaValidator wiring.
- `scripts/generate-docs/PackageContractsDoc.php:72`: drop the dead `ExpressionResolverInterface` entry.
- Note: `packages/core/tests/Feature/FalsificationScriptsTest.php:42` references the name only inside a
  fixtured *string* exercised by `detect-fake-tests.php` — no real class resolution; leave unchanged.
- Verify: `composer run test`, `composer run format`, `composer run analyse`, `composer run docs` (regenerate).

### Task 4 — Fold request-pipeline into the runner package

After Tasks 2-3 every remaining request-pipeline class is runner-only, so relocate it and
delete the package. `RequestCompiler` keeps taking `InjectionResult` return values from the
injector; nothing but runner and two root scripts reference any of these names.

- `git mv` (then namespace-rewrite to `Alama\Arazzo\Runner\Execution`):
  `packages/request-pipeline/src/{RequestCompiler,ExpressionValueResolver,IdempotencyKeyInjector,ReusableParameterResolver,StepParameterMerger,ParameterSerializer,TypeCaster}.php`
  and `packages/request-pipeline/src/Data/InjectionResult.php` →
  `packages/runner/src/Execution/`. `ParameterSerializer`/`TypeCaster` methods become
  instance (non-static) as part of the move.
- Delete `packages/request-pipeline/src/ExecutionExpressionResolver.php`,
  `packages/request-pipeline/src/Data/ExecutionEvaluationInput.php` and their tests, and
  `packages/contracts/src/Interfaces/OutputExtractorInterface.php` (its last reference,
  `ExecutionExpressionResolver`, is deleted here; `ResponseValidatorInterface` stays —
  genuine polymorphism: `ResponseSchemaValidator` + `ResponseValidatorDispatcher`).
- Switch `ExecutionEvaluationInput` references to `Evaluation\Data\EvaluationContext`
  (now all inside runner): `Execution/ExpressionValueResolver.php`,
  `Execution/StepOutputExtractor.php`, `Execution/StepOutcomeHandler.php`,
  `Execution/SubWorkflowInvoker.php`, `Protocol/SubWorkflowStepExecutor.php`,
  `Protocol/AsyncApiStepExecutor.php`; update `ExpressionValueResolverTest` expectation.
- Runner consumers update imports (same package now): `StepExecutor`, `HttpStepExecutor`,
  `AsyncApiStepExecutor`, `SubWorkflowStepExecutor`, `StepExecutionWorker`,
  `WorkflowExecutor`, `AsyncExecutionGraphAssembler`, `StepOutcomeHandler`,
  `SubWorkflowInvoker`, `StepOutputExtractor`, `DefaultOpenApiExecutor`.
- `git mv` tests → `packages/runner/tests/Execution/` (namespace `Alama\Arazzo\Tests\Runner\Execution`):
  `ExpressionValueResolverTest`, `IdempotencyKeyInjectorTest`, `InjectionResultTest`,
  `RequestCompilerTest`, `ReusableParameterResolverTest`, `StepParameterMergerTest`,
  `ParameterSerializerTest`, `TypeCasterTest` (the deleted-resolver and evaluation-input
  tests do not move).
- Delete `packages/request-pipeline/` (phpstan.neon.dist included) and its composer wiring:
  root `composer.json` drops the path-repo `url` (line ~53), `require` (line ~72), src +
  tests autoload entries, `analyse-pipeline` (line ~148), `test-pipeline` (line ~156); and
  `packages/runner/composer.json` drops `alama/arazzo-request-pipeline` (line 13).
  Update `scripts/manual_test.php` + `scripts/test_checkout.php` to the new
  `IdempotencyKeyInjector` home.
- Preserve the agnosticism guard: in the runner arch suite (e.g. `packages/runner/tests/ArchTest.php`)
  add `arch()->expect([RequestCompiler::class, ReusableParameterResolver::class, … folded classes])
  ->not->toUse(['Alama\Arazzo\Engine\\', 'Illuminate\\', 'cebe\\openapi\\', 'GuzzleHttp\\'])`
  (class-list guard, so `GuzzleHttp` remains legal for the rest of the runner). Delete the old
  request-pipeline `ArchTest`.
- Verify: `composer run test`, `composer run format`, `composer run analyse`,
  `composer run docs`, and `rg "Arazzo\\\\RequestPipeline" --glob '!*.md'
  --glob '!phpstan-baseline.neon' --glob '!phpstan-report.txt' --glob '!vendor' .` is empty.

### Task 5 — Final checkpoint (approval + commit)

1. Run all gates (see Verification) and confirm green + `git status`/`git diff` scoped to this work.
2. Present a change summary (files added/deleted/renamed, arch diff, gates output) to the user.
3. Commit in small logical commits (Task 1 … Task 4) with conventional messages; push only after
   explicit user approval (1Password approval required for the configured SSH).

## Rollback

We are in an isolated worktree on branch `phase-e-runner-oms` over green base `fab433a`.
Any failed step is reverted with `git restore .` + `git clean` of untracked stubs; the tracked
`phpstan-baseline.neon` is never deleted (restore from HEAD first if touched).