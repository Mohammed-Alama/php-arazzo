# Phase F — Protocol packages + runner internal structure

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the vertical protocol packages that prove the "vertical slice, zero core edits" pattern (spec D4/D7/D9) — **F1 `alama/arazzo-protocol-http`** (the reference recipe every other protocol copies), **F4 `alama/arazzo-protocol-soap`**, **F5 `alama/arazzo-protocol-rpc`** — and, between them, restructure `packages/runner` itself (**F2**) into explicit `Sync\`/`Async\` namespaces with the facade at the root, then close the `laravel`/`cli` leak so the facade is the stable API (**F3** is the phase gate).

**Depends on Phase A–E.** Hard precondition — if A/B/C/D/E are not green on `main`, stop and flag it before starting F1.0.

**Tech Stack:** PHP ^8.4, Pest v5, PHPStan ^2.0 level max (root composite `analyse-*` scripts), Laravel Pint, `guzzlehttp/guzzle` + `cebe/php-openapi ^1.7` (F1 only), DOM/libxml + PSR-18 `psr/http-client`/`psr/http-message`/`psr/http-factory` (F4 SOAP, F5 RPC).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md` — Phase F rows (F1, F2), decision table (D4, D7, D9), Public API impact. Evidence: `docs/research/2026-09-26-runner-package-split-validation.md`, `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`.

---

## Revision note (rewritten against `phase-e-runner-oms` @ `1a0b8b4`)

This revision replaces the first draft, which was written against a tree where Phase E had
extracted four packages including `arazzo-request-pipeline`. **That extraction was reverted.**
Everything below is verified against the current worktree, not assumed. The facts that
invalidated the first draft:

| # | First draft assumed | Actual state | Consequence |
|---|---|---|---|
| 1 | `alama/arazzo-request-pipeline` is a package (E3) | **Gone.** Folded into `Alama\Arazzo\Runner\Execution` by `c2b03fb`. Zero refs remain in any manifest or namespace. | No protocol package may require it. Every `require`/`repositories`/`scanDirectories` entry for it is deleted. |
| 2 | `ExecutionEvaluationInput` is a runner type to promote to contracts | **Deleted** by `c2b03fb`; `Alama\Arazzo\Expression\Data\EvaluationContext` is the single input VO (it moved again, out of `evaluation`, with the expression-facade commits). `StepProtocolExecutorInterface::execute()` is now `(Step, WorkflowContext, ArazzoDocument, string $executionId): StepExecutionOutcome`. | The whole "promote `ExecutionEvaluationInput`" half of F1.2 is **deleted**, not amended. |
| 3 | Exactly 3 of 5 runner-internal seams were already dissolved; F1.2 promotes the last 2 | `ExecutionException` **never moved to engine** — it is still `Alama\Arazzo\Runner\Execution\Exceptions\ExecutionException` and `AsyncApiStepExecutor` imports it. | F1.2 promotes **two** seams, not one. See DF3. |
| 4 | `OutputExtractorInterface` is a contracts seam | **Deleted** by `c2b03fb` (single implementation, all consumers runner-internal); `StepOutputExtractor` is a concrete runner class. | Moving `StepOutputExtractor` to protocol-http dissolves that justification → the interface is **re-introduced**. See DF1. |
| 5 | `ExpressionValueResolver` / `ExecutionExpressionResolver` live in the pipeline | Both **gone**; absorbed into the expression facade (`cc3b69a`, `e40b3f2`). | Executors consume `EvaluationEngineInterface` directly. |
| 6 | The old dead `Async/` directory + `Protocol/SubWorkflowExecutor` + `Protocol/ProtocolExecutorRegistry` were removed "in E0" | True, and that commit is **`6e0a088`** (`refactor(runner): delete dead Async/ + Protocol classes`, 47 commits back, an ancestor of HEAD). | F2.0 must move the **live** async set, and must **not** try to restore the 6 deleted classes. See DF5. |
| 7 | `packages/sources` has no cebe, protocol-http gets it all | `alama-arazzo-sources` still requires `cebe/php-openapi`, and `OpenApiSourceNormalizer` imports `Sources\Resolver\Exceptions\UnsupportedSourceVersionException`. | protocol-http must also require `alama/arazzo-sources`. See DF4. |

### The `6e0a088` question, answered

`6e0a088` deleted six `Alama\Arazzo\Runner\Async\*` classes — `ExecutionStateBuilder`,
`PreflightGuard`, `StateReconciler`, `SuspensionHandler`, `TransitionApplier`, `WorkerEvents`
(~660 lines) — plus `Protocol/SubWorkflowExecutor` and `Protocol/ProtocolExecutorRegistry`,
and their seven tests.

**They were rot, not the async feature.** Their imports point at `Alama\Arazzo\Runner\State\Interfaces\*`
and `Alama\Arazzo\Runner\Events\*`, both of which stopped existing when E1/E2 moved state and
events into `arazzo-runtime` / `arazzo-events`. Every responsibility they held is live elsewhere
today — the suspension side effects are in `Execution/StepExecutionWorker.php:163-182` (persist
`Suspended`, `executionRegistry->start`, ledger `step.suspended`, dispatch `CorrelationPendingEvent`),
which is exactly what `Execution/StepOutcomeHandler.php:117` means by *"suspension handled by the
executor layer"*. So:

- **Do not restore them.** F2.0's job is to move the *surviving* async set into `Async\`.
- **One guard was lost in the port and must be restored** — see DF6. This is the single piece of
  6e0a088's behaviour that is not currently covered anywhere.

The live async set that F2.0 moves is exactly seven classes:

| From | To |
|---|---|
| `packages/runner/src/Execution/StepExecutionWorker.php` | `src/Async/StepExecutionWorker.php` |
| `packages/runner/src/Execution/StepOutcomeHandler.php` | `src/Async/StepOutcomeHandler.php` |
| `packages/runner/src/Execution/CorrelationResumer.php` | `src/Async/CorrelationResumer.php` |
| `packages/runner/src/Execution/SubWorkflowInvoker.php` | `src/Async/SubWorkflowInvoker.php` |
| `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php` | `src/Async/AsyncExecutionGraphAssembler.php` |
| `packages/runner/src/Jobs/ExecuteStepJob.php` | `src/Async/ExecuteStepJob.php` |
| `packages/runner/src/Jobs/ResumeCorrelationJob.php` | `src/Async/ResumeCorrelationJob.php` |

---

## Package model (source of truth, verified on this branch)

| Layer | Package | Created in | Depends on |
|---|---|---|---|
| 1 | `alama/arazzo-runtime` | E1 | contracts |
| 1 | `alama/arazzo-events` | E2 | contracts |
| 3 | `alama/arazzo-engine` | E4 | contracts, evaluation, runtime |
| — | `alama/arazzo-sources` | pre-E | contracts, document, expression (+ cebe until F1.1) |
| 5+6 | `alama/arazzo-runner` (`Sync\`/`Async\` + shared at root) | **F2** | contracts, document, expression, evaluation, engine, runtime, events, sources, protocol-* |
| vertical | `alama/arazzo-protocol-http` | **F1** | contracts, document, expression, evaluation, engine, sources |
| vertical | `alama/arazzo-protocol-soap` | **F4** | same as http, minus cebe/guzzle |
| vertical | `alama/arazzo-protocol-rpc` | **F5** | same as http |

**There is no layer-2 package any more.** `packages/request-pipeline` does not exist; its eight
surviving classes (`RequestCompiler`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`,
`IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver`,
`Data/InjectionResult`) are runner-internal helpers under `Alama\Arazzo\Runner\Execution`.

**Task map:** F1.0–F1.4 `protocol-http` · F2.0–F2.3 runner restructure · F3 phase gate · F4.0–F4.7 `protocol-soap` · F5.0–F5.2 `protocol-rpc`.

**Scope note (intentional deferral):** GraphQL stays deferred and needs its own phase plan written against the F1/F4 pattern. Phase H's RPC/GraphQL tasks only exercise document-level parsing/validation fixtures — already covered by Phase D's `RpcStepRule`/`GraphQlStepRule` — so deferring the GraphQL executor package does not block H.

**Actor-in-loop creates no new package.** It is an execution-model concern (suspension/resumption, correlation, handoff) and lands inside `arazzo-runner`'s `Async\` namespace (F2.1). The spec's `AwaitingActorInput`/`ActorInputReceived` states are **already modelled** — verified: `packages/engine/src/StepStateMachineEngine.php:52-53,140-151` dispatches both, and `fromPending` handles the interaction bypass at line 108. F2.1 supplies async side effects only.

---

## Decisions locked in this revision

- **DF1 — `StepOutputExtractor` moves to protocol-http, and `OutputExtractorInterface` returns to contracts.** `StepOutputExtractor` reads the cebe `Operation`'s `responses`, so it is OpenAPI-specific and cannot stay in vendor-free-agnostic runner. But unlike `ResponseSchemaValidator` (which already implements contracts' `ResponseValidatorInterface`), it has no interface, and its consumers — `StepExecutor` (sync), `CorrelationResumer` (async), `HttpStepExecutor` — span the sync/async split and, after the move, cross a package boundary. `c2b03fb` deleted `OutputExtractorInterface` on the explicit ground that *"it has exactly one implementation and every consumer is runner-internal, so no interface is warranted."* Moving the class out of runner **dissolves that ground**. So: re-create `Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface` with `extractOutputs(...)` copied verbatim from today's `StepOutputExtractor`, have the relocated class implement it, and type all three consumers against it. A SOAP-only or sync-only app then never resolves protocol-http. This is the same reasoning that kept `ResponseValidatorInterface` alive in `c2b03fb` decision 2.
- **DF2 — no `ExecutionEvaluationInput` promotion.** The type is deleted; `Alama\Arazzo\Expression\Data\EvaluationContext` is the single input VO. Nothing to move.
- **DF3 — exactly two DIP seam promotions.** `PendingCorrelationRegistryInterface`: `Runtime\State\Interfaces` → `Contracts\Interfaces` (needed by `AsyncApiStepExecutor`). `ExecutionException`: `Runner\Execution\Exceptions` → `Engine\Exceptions` (new dir; `AsyncApiStepExecutor` throws `messageFactoryMissing`/`unresolvableChannelTarget`, and `SubWorkflowStepExecutor` throws `subWorkflowNotFound`). `runner` gains an `alama/arazzo-engine` require; engine already depends on nothing that would cycle. Only 4 files reference `ExecutionException`, all in runner.
- **DF4 — protocol-http requires `alama/arazzo-sources`.** `OpenApiSourceNormalizer` imports `Sources\Resolver\Exceptions\UnsupportedSourceVersionException` and `OpenApiOperationResolver` uses the `Sources\Resolver\Interfaces\SourceResolver` seam. protocol → sources is legal (sources sits below it). In exchange, F1.1 drops `cebe/php-openapi` from `packages/sources/composer.json` — after the normalizers leave, sources has no vendor type left.
- **DF5 — do not resurrect 6e0a088's dead classes.** See the revision note. `packages/runner/tests/Protocol/` is now an empty directory; delete it as part of F2.0.
- **DF6 — restore the scalar guard (live bug).** `StepExecutionWorker.php:170` reads `$correlationIdValue = (string) $this->evaluationEngine->evaluate($step->target->correlationId, new EvaluationContext($context, $step->stepId));`. The deleted `Async/SuspensionHandler.php:47` guarded this with `is_scalar($evaluated) ? (string) $evaluated : ''`. `evaluate()` returns `mixed`, so a correlation-id expression resolving to a non-`Stringable` object throws `Error: Object of class … could not be converted to string` inside the async worker. F2.1 Step 2 restores the guard and adds the regression test.
- **DF7 — pipeline helpers stay unreachable from protocol packages.** No protocol package may reference `Alama\Arazzo\Runner\Execution\{RequestCompiler,ParameterSerializer,TypeCaster,SchemaValidator,IdempotencyKeyInjector,StepParameterMerger,ReusableParameterResolver}`: that would be a runner import. `SchemaValidator` is the pressure point — SOAP/RPC need response validation but not the cebe schema walker, so they use `ResponseValidatorInterface` implementations of their own.

---

## Global Constraints

- **Prerequisites (hard preconditions).** Phase A (contracts ports A1–A6 incl. `PluginInterface`, `OperationExecutorPluginInterface`, `SourceNormalizerInterface`, `SourceNormalizerRegistryInterface`, `ResponseTransferInterface`, `StepState`), Phase B (step-scoped grammar + transfer views), Phase C (expression/evaluation split, JsonPath built-in), Phase D (`SourceNormalizerRegistry`, `OpenApiSourceNormalizer`, two-axis `ResolvedOperation`, WSDL step rules), Phase E (`OperationExecutorRegistry`, `ResponseValidatorDispatcher`, `StepStateMachineEngine`) must be green. Verified present on this branch. Where a registry differs at implementation time, use today's concrete surface and note the hand-off.
- **Per the spec's transfer seam (D6):** each protocol package ships a **typed transfer DTO implementing `ResponseTransferInterface`** (`status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`). HTTP keeps the generic `ResponseTransfer`, SOAP ships `SoapResponseTransfer`, RPC ships `RpcResponseTransfer`. Concrete facets live in the DTO's `views`/`meta` bags under documented keys, never as flat constructor props.
- **The plan is the boss.** Follow the exact task order; only deviate where the code forces you to, and note the deviation in the commit message. No code/spec edits outside the files each task lists (except root/umbrella/Laravel `composer.json` require+repositories).
- **The two DTOs are not relocated.** `ResolvedOperation` and `NormalizedOpenApiOperation` are pure model types owned by `alama/arazzo-document` at `Alama\Arazzo\Document\ResolvedOperation` / `…\NormalizedOpenApiOperation`. F1.1 does not move them. The cebe handles they used to expose travel on `Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle`, which **does** move to protocol-http as an `@internal` type.
- **No protocol import below runner (H2 invariant).** No `use Alama\Arazzo\Protocol\...` may appear under `packages/{contracts,expression,document,evaluation,runtime,events,sources,engine}/src`. `arazzo-runner` is the one package above them that legitimately does.
- **Direction of dependencies.** `arazzo-protocol-http` requires `contracts` + `document` + `expression` + `evaluation` + `engine` + `sources` — and **not** `arazzo-runner`. `arazzo-protocol-soap` and `arazzo-protocol-rpc` require the same set. No package under `alama/` requires `arazzo-runner` except the umbrella, `laravel` and `cli`.
- **Priority convention (locked with G1).** Higher `PluginInterface::priority()` = resolved earlier. All registries sort descending.
- **No new code comments** unless explaining a priority/BC decision. Existing relocated docblocks stay.
- Every task's `--filter` runs `vendor/bin/pest packages/<pkg>/tests --filter "<name>"` from the repo root. Every task ends with its package suite green. Static analysis per task via the package's `analyse-*` script. **There is no `test-pipeline` / `analyse-pipeline`** — that script pair died with the package.
- New-command plumbing (F1.0, F4.0, F5.0) is the ONLY place that edits root `composer.json` `scripts`; later tasks only add `require`/`repositories` entries.
- **Arch guards in this phase are regression guards, not red-first cycles.** Each is committed with the move that establishes the boundary and verified to bite by temporarily introducing a forbidden import, confirming RED, then reverting. Do not claim a red-first cycle that does not exist.
- **Preserve the folded-pipeline agnosticism guard.** `packages/runner/tests/ArchTest.php` has `arch('folded request pipeline stays transport-agnostic')` — a class-list `toUse()` guard over the eight folded classes (`not->toUse(['Alama\Arazzo\Engine', 'Illuminate', 'cebe\openapi', 'GuzzleHttp'])`). When F2.0 moves those classes' namespaces, update the `use` lines in that guard; do not delete it. Note `Alama\Arazzo\Engine` moves to `Alama\Arazzo\Runner\Engine` — a *class*, not a namespace — so that entry still means what it meant.

---

## Task F1.0: `arazzo-protocol-http` package scaffold + root plumbing

Create the package directory, composer manifest, Pest/PHPStan scaffolding, and root monorepo plumbing. This task only *declares* the package — no relocated classes yet, so `composer update` must succeed with a valid (empty) package.

**Files:**
- Create `packages/protocol-http/composer.json`, `phpstan.neon.dist`, `tests/Pest.php`, `tests/Architecture/ArchTest.php`, `src/.gitkeep`
- Modify `composer.json` (root): `repositories`, `require`, `autoload-dev`, `scripts`

**Interfaces:**
- Produces: package `alama/arazzo-protocol-http` installable as a path repository, analysable via `composer run analyse-http`, testable via `composer run test-http`.

- [ ] **Step 1: Create the composer manifest**

`packages/protocol-http/composer.json`:

```json
{
    "name": "alama/arazzo-protocol-http",
    "description": "HTTP/OpenAPI + AsyncAPI vertical slice for alama/arazzo-core: normalizers, operation executors, transport, response validation. Reference protocol package.",
    "keywords": ["alama", "arazzo", "openapi", "asyncapi", "http", "protocol"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"},
        {"type": "path", "url": "../document"},
        {"type": "path", "url": "../expression"},
        {"type": "path", "url": "../evaluation"},
        {"type": "path", "url": "../engine"},
        {"type": "path", "url": "../sources"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-engine": "@dev",
        "alama/arazzo-sources": "@dev",
        "cebe/php-openapi": "^1.7",
        "guzzlehttp/guzzle": "^7.8||^8.0",
        "illuminate/contracts": "^11.0||^12.0||^13.0",
        "illuminate/support": "^11.0||^12.0||^13.0",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.1",
        "psr/http-message": "^2.0",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0||^10.0||^11.0",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0"
    },
    "autoload": {"psr-4": {"Alama\\Arazzo\\Protocol\\Http\\": "src/"}},
    "autoload-dev": {"psr-4": {"Alama\\Arazzo\\Tests\\Protocol\\Http\\": "tests/"}},
    "extra": {"laravel": {"providers": ["Alama\\Arazzo\\Protocol\\Http\\Laravel\\ProtocolHttpServiceProvider"]}},
    "config": {"sort-packages": true, "allow-plugins": {"pestphp/pest-plugin": true, "phpstan/extension-installer": true}},
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Note what is **absent**: `alama/arazzo-runner`, `alama/arazzo-request-pipeline`, and `softcreatr/jsonpath` (moved under `expression` by `429a743`; it is no longer a protocol-http concern). That omission is the DIP boundary written as a manifest — the package cannot reach runner internals because it cannot resolve them. No dual PSR-4 map is needed: D0 left the two DTOs in `alama/arazzo-document`, which this package already requires, so their FQCNs are unchanged by construction. `illuminate/contracts` + `illuminate/support` are required so the provider can extend `Illuminate\Support\ServiceProvider` (the provider ships in F1.3; until then the `extra.laravel.providers` entry is inert).

- [ ] **Step 2: Create the PHPStan config**

`packages/protocol-http/phpstan.neon.dist` — mirror `packages/document/phpstan.neon.dist` exactly, including the shared core rules and scan directories (the relocated types import document/engine/sources code, so the same scan list those packages use must resolve them):

```neon
includes:
    - ../core/phpstan/rules/phpstan-custom.neon
    - ../core/phpstan/rules/phpstan-annotations.neon

parameters:
    level: max
    tmpDir: .phpstan
    paths:
        - src
    scanDirectories:
        - ../contracts/src
        - ../expression/src
        - ../evaluation/src
        - ../document/src
        - ../engine/src
        - ../sources/src
    excludePaths:
        - tests
```

If the two `rules/*.neon` files do not both exist under `packages/core/phpstan/rules/` at implementation time, copy the `includes` block verbatim from `packages/document/phpstan.neon.dist` — that is the source of truth.

- [ ] **Step 3: Create the Pest bootstrap + a smoke test**

`packages/protocol-http/tests/Pest.php` — copy `packages/document/tests/Pest.php` verbatim (the same cebe PHP 8.4 deprecation silencing is needed here, since cebe lands in this package):

```php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
```

Create `packages/protocol-http/tests/Architecture/ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('protocol-http does not depend on the runner')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner');

arch('protocol-http does not depend on Laravel runtime types in the slice core')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Illuminate\Support')
    ->ignoring('Alama\Arazzo\Protocol\Http\Laravel');
```

The first rule is the DIP guard, asserted from the very first commit so F1.2 cannot regress it silently. Both rules pass vacuously on the empty `src`; F1.2 verifies the first one bites before committing the move.

- [ ] **Step 4: Wire the root monorepo**

Edit root `composer.json`:
- `repositories`: append `{"type": "path", "url": "packages/protocol-http"}`.
- `require`: append `"alama/arazzo-protocol-http": "@dev"`.
- `autoload-dev.psr-4`: append `"Alama\\Arazzo\\Tests\\Protocol\\Http\\": "packages/protocol-http/tests"` (alongside the existing `Alama\Arazzo\Tests\` map — protocol-http uses its *own* test namespace, mirroring `packages/evaluation`).
- `scripts`: append `"analyse-http": "vendor/bin/phpstan analyse -c packages/protocol-http/phpstan.neon.dist --memory-limit=1G"`, add `"@analyse-http"` to the `"analyse"` array (after `"@analyse-events"`), add `"test-http": "vendor/bin/pest packages/protocol-http/tests"`, add `"@test-http"` to the `"test"` array.

- [ ] **Step 5: Install and verify**

```bash
composer update alama/arazzo-protocol-http --with-dependencies --no-interaction
composer run analyse-http
composer run test-http
```

Expected: package symlinked into the root vendor from `packages/protocol-http`; analyse passes (0 errors on the empty `src`); Pest passes the arch smoke test.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock packages/protocol-http
git commit -m "build(protocol-http): scaffold arazzo-protocol-http package + root plumbing (F1.0)"
```

---

## Task F1.1: Relocate the OpenAPI normalizers out of `arazzo-sources` into `arazzo-protocol-http`

Move the normalizer classes out of `arazzo-sources` into protocol-http. Verified inventory — `packages/sources/src/Normalizer/` holds exactly nine files today:

| File (in `packages/sources/src/Normalizer/`) | Destination |
|---|---|
| `OpenApi30Normalizer.php` | `packages/protocol-http/src/Normalizer/` |
| `OpenApi31Normalizer.php` | `packages/protocol-http/src/Normalizer/` |
| `Swagger2Normalizer.php` | `packages/protocol-http/src/Normalizer/` |
| `OpenApiDocumentLoader.php` | `packages/protocol-http/src/Normalizer/` |
| `OpenApiOperationResolver.php` | `packages/protocol-http/src/Normalizer/` |
| `OpenApiOperationHandle.php` | `packages/protocol-http/src/Normalizer/` (`@internal`) |
| `OpenApiSourceNormalizer.php` | `packages/protocol-http/src/Normalizer/` |
| `OpenApiVersionDetector.php` | **stays** — see Step 2 |
| `Interfaces/OpenApiNormalizerInterface.php` | **stays** — see Step 2 |

`ResolvedOperation` and `NormalizedOpenApiOperation` **stay in `packages/document/src/`** untouched, so `packages/protocol-http/src/Document/Normalizer/` is never created.

**Interfaces:**
- Consumes (protocol-http): `SourceResolver` + `UnsupportedSourceVersionException` (`Alama\Arazzo\Sources\Resolver\…`, per DF4), the two document DTOs, `SourceNormalizerInterface` (contracts).
- Produces: `Alama\Arazzo\Protocol\Http\Normalizer\{OpenApi30Normalizer, OpenApi31Normalizer, Swagger2Normalizer, OpenApiDocumentLoader, OpenApiOperationResolver, OpenApiOperationHandle, OpenApiSourceNormalizer}`.

- [ ] **Step 1: `git mv` the seven files**

```bash
mkdir -p packages/protocol-http/src/Normalizer
for f in OpenApi30Normalizer OpenApi31Normalizer Swagger2Normalizer OpenApiDocumentLoader OpenApiOperationResolver OpenApiOperationHandle OpenApiSourceNormalizer; do
  git mv "packages/sources/src/Normalizer/$f.php" "packages/protocol-http/src/Normalizer/$f.php"
done
```

- [ ] **Step 2: Rewrite namespaces; keep the detector and the interface behind**

Namespace `Alama\Arazzo\Sources\Normalizer` → `Alama\Arazzo\Protocol\Http\Normalizer` for the seven moved classes, updating their internal imports:
- The two DTOs resolve from document with no `Normalizer` segment — `use Alama\Arazzo\Document\ResolvedOperation;` / `use Alama\Arazzo\Document\NormalizedOpenApiOperation;`.
- `OpenApiSourceNormalizer` imports `Sources\Normalizer\Interfaces\OpenApiNormalizerInterface` — that interface **stays in sources** (it is the normalizer contract `sources` owns and its registry consumes), so the import becomes `use Alama\Arazzo\Sources\Normalizer\Interfaces\OpenApiNormalizerInterface;`. Its sibling imports of `OpenApiDocumentLoader` / `OpenApi30Normalizer` / `OpenApi31Normalizer` are now in-package — drop the `use` lines.
- `OpenApiOperationResolver` keeps `use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;` and `use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;` unchanged (DF4 — that is why protocol-http requires `sources`).
- Keep the existing `@internal` docblocks; the "relocated in F1" notes in the D2/D3c docblocks are now accurate.

**Why `OpenApiVersionDetector` and `OpenApiNormalizerInterface` stay:** both are referenced from `sources`-side code that this task does not otherwise touch, and the detector is constructible without cebe (`PreflightValidator` news it up directly at `packages/sources/src/Validator/PreflightValidator.php:45`). Moving either would drag `PreflightValidator` and the sources registry into F1.1's blast radius for no DIP gain. If at implementation time the detector turns out to be unreferenced outside the moved set, move it too and note it.

- [ ] **Step 3: Move cebe out of `alazzo-sources`**

`packages/sources/composer.json`: drop `"cebe/php-openapi": "^1.7"` from `require`. After this step nothing under `packages/sources/src` touches a cebe type — verify with `rg -n "cebe\\\\openapi" packages/sources/src || echo "sources cebe-free"`. Then `composer update cebe/php-openapi --no-interaction` and `composer run analyse-sources && composer run test-sources`.

- [ ] **Step 4: Repoint `PreflightValidator`'s operation-resolver seam (sources side)**

Today `PreflightValidator::__construct` types the **concrete** `Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver` (line 42), which this task just moved — so without a seam, `sources` would import protocol-http (an H2 violation). Create `packages/sources/src/Normalizer/OpenApiOperationResolverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\ResolvedOperation;

/**
 * Document-side seam for operation resolution during preflight. Implemented
 * by the HTTP/OpenAPI protocol package (arazzo-protocol-http F1) so the
 * validator never imports protocol types.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OpenApiOperationResolverInterface
{
    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation;
}
```

(Verify the signature against the real `OpenApiOperationResolver::resolve()` before writing — copy it verbatim.)

Then in `packages/sources/src/Validator/PreflightValidator.php`: constructor param `private readonly OpenApiOperationResolver $operations` → `private readonly OpenApiOperationResolverInterface $operations`, dropping the `use` for the concrete class. `OpenApiVersionDetector` usage is unchanged (it stays in sources).

`packages/protocol-http/src/Normalizer/OpenApiOperationResolver.php` declares `implements OpenApiOperationResolverInterface` (importing `Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolverInterface` — legal, sources is below protocol-http).

- [ ] **Step 5: Relocate the normalizer-focused tests**

```bash
git mv packages/sources/tests/Normalizer/OpenApi30NormalizerTest.php      packages/protocol-http/tests/Normalizer/OpenApi30NormalizerTest.php
git mv packages/sources/tests/Normalizer/OtherNormalizersTest.php         packages/protocol-http/tests/Normalizer/OtherNormalizersTest.php
git mv packages/sources/tests/Resolver/Resolver/OpenApiOperationResolverVersionTest.php packages/protocol-http/tests/Normalizer/OpenApiOperationResolverVersionTest.php
```

Adjust namespaces to `Alama\Arazzo\Tests\Protocol\Http\Normalizer`. Two caveats worth stating rather than discovering mid-task:

- **`OtherNormalizersTest` is a mixed bag.** Check which classes it exercises; anything covering a class that stayed in `sources` (e.g. `OpenApiVersionDetector`) must be split into a remaining sources test rather than dragging a sources-only assertion into a package that cannot see the class.
- **Tests covering resolution through `DocumentInterface`** stay in sources (`DocumentCapabilitiesTest` and friends) and are re-pointed in F1.3.
- The stale tracked snapshot `phpstan-report.txt` is a known dead artifact — leave it unchanged.

- [ ] **Step 6: Run the gates**

```bash
composer update cebe/php-openapi --no-interaction
composer run test-http -- --filter=Normalizer
composer run analyse-http
composer run test-sources && composer run analyse-sources
composer run test-document && composer run analyse-document
```

Expected: `test-http` green on the relocated suites; `test-sources`/`analyse-sources` still green **without** cebe; `test-document`/`analyse-document` still green. `analyse-document` must NOT error on resolving `ResolvedOperation` — it resolves inside document's own tree. If it does error, you have wrongly moved a DTO; move it back rather than adding a `scanDirectories` entry.

- [ ] **Step 7: Commit**

```bash
git add -A packages/document packages/sources packages/protocol-http composer.json composer.lock
git commit -m "refactor(protocol-http): relocate OpenAPI normalizers out of arazzo-sources (F1.1)"
```

---

## Task F1.2: Relocate the HTTP/AsyncAPI executors into `arazzo-protocol-http`; promote the two remaining seam types

Move the HTTP execution stack out of `runner` into `protocol-http`, and finish the DIP inversion by promoting the last two runner-internal types the executors need.

**What is *not* moved here:** `RequestCompiler`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`, `IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver` are runner-internal helpers in `Alama\Arazzo\Runner\Execution` and **stay there** (`c2b03fb`). Per DF7 no protocol package may import them; the executors receive their effects through `StepOutputExtractor` / the executor interface instead. Do not move them again.

**The two exceptions**, both OpenAPI-specific because they read the cebe `Operation`:
- `StepOutputExtractor` reads `$operation->responses`.
- `ResponseSchemaValidator` reads the handle's cebe `Operation`.

**What stays in `runner` deliberately:** `Protocol/SubWorkflowStepExecutor` — it is not a protocol. It implements Arazzo recursive-workflow semantics and delegates to `WorkflowExecutor`, which the engine may not depend on, so it belongs above the engine with the runners. (Its dead siblings `Protocol/SubWorkflowExecutor` and `Protocol/ProtocolExecutorRegistry` were deleted in `6e0a088`.)

**The two seam promotions (DF3):**

| Type | Today | Becomes | Why |
|---|---|---|---|
| `ExecutionException` | `Alama\Arazzo\Runner\Execution\Exceptions\ExecutionException` | `Alama\Arazzo\Engine\Exceptions\ExecutionException` | `AsyncApiStepExecutor` throws `messageFactoryMissing()`/`unresolvableChannelTarget()`; `SubWorkflowStepExecutor` throws `subWorkflowNotFound()`. The first draft of this plan assumed E4 had already moved it — it had not. |
| `PendingCorrelationRegistryInterface` | `Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface` | `Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface` | A port. `AsyncApiStepExecutor` needs it to register a pending correlation; an executor should not depend on a store package to express "I am waiting". |

Phase E + `c2b03fb` dissolved the rest: `ReusableParameterResolver` → runner-internal, `HttpClientInterface` → contracts (E0), `ExecutionEvaluationInput` → deleted (DF2), `OutputExtractorInterface` → deleted then re-introduced by DF1.

**Files moved (git mv):**
- → `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php` (ns `Alama\Arazzo\Protocol\Http\Execution`)
- → `packages/protocol-http/src/Protocol/HttpStepExecutor.php` (ns `Alama\Arazzo\Protocol\Http\Protocol`)
- → `packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php` (same ns)
- → `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` (canonical, Step 3)
- `packages/runner/src/Execution/StepOutputExtractor.php` → `packages/protocol-http/src/Execution/StepOutputExtractor.php`
- `packages/runner/src/Execution/ResponseSchemaValidator.php` → `packages/protocol-http/src/Execution/ResponseSchemaValidator.php`

**Files created:**
- `packages/contracts/src/Interfaces/OutputExtractorInterface.php` (DF1) — `extractOutputs(...)` copied verbatim from today's `StepOutputExtractor`
- `packages/engine/src/Exceptions/ExecutionException.php` (DF3)

**Files moved into contracts (git mv):**
- `packages/runtime/src/State/Interfaces/PendingCorrelationRegistryInterface.php` → `packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php`

**Files kept in runner (edit only):**
- `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — becomes a `@deprecated` BC alias (Step 3)
- `packages/runner/src/Execution/ExecutionGraphFactory.php` — stop defaulting protocol-http classes (Step 4)
- `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php` — stop defaulting them in `assemble()` (Step 4)
- `packages/runner/src/Execution/StepExecutor.php` — type against the new `OutputExtractorInterface` (Step 4)
- `packages/runner/src/RunnerFacade.php`, `packages/runner/src/RunnerGraphBuilder.php` — forward the new executor seam (Step 4)
- `packages/runner/src/AsyncGraphSeams.php` — add `?OutputExtractorInterface $outputExtractor = null` (Step 4)
- `packages/runner/src/Execution/CorrelationResumer.php` — type against `OutputExtractorInterface`
- `packages/runtime/src/State/{InMemoryStateStore,FileStateStore}.php` — implement the relocated `PendingCorrelationRegistryInterface` (Step 1)
- `packages/runner/phpstan.neon.dist` — add `../protocol-http/src` to `scanDirectories` (Step 6). `document` deliberately does **not** — assert its absence instead:

```php
arch('document does not depend on protocol-http')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Protocol');
```

**Interfaces:**
- Produces (protocol-http): `Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface` (canonical signature of today's runner interface).
- Runner's `OpenApiExecutorInterface` FQCN is preserved as a deprecated alias so existing type-hints (`StepExecutor`, `AsyncGraphSeams`, `AsyncExecutionGraphAssembler`, tests) keep compiling without a core→protocol import.
- Produces (contracts): `OutputExtractorInterface`, `PendingCorrelationRegistryInterface`.
- Produces (engine): `Alama\Arazzo\Engine\Exceptions\ExecutionException`.

- [ ] **Step 1: Promote the two seam types**

```bash
mkdir -p packages/contracts/src/Execution packages/engine/src/Exceptions
git mv packages/runtime/src/State/Interfaces/PendingCorrelationRegistryInterface.php packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php
git mv packages/runner/src/Execution/Exceptions/ExecutionException.php packages/engine/src/Exceptions/ExecutionException.php
```

Rewrite namespaces: `Alama\Arazzo\Runtime\State\Interfaces` → `Alama\Arazzo\Contracts\Interfaces`; `Alama\Arazzo\Runner\Execution\Exceptions` → `Alama\Arazzo\Engine\Exceptions`. Then fix every importer:

```bash
rg -l 'Runtime\\State\\Interfaces\\PendingCorrelationRegistryInterface|Runner\\Execution\\Exceptions\\ExecutionException' packages/
```

`PendingCorrelationRegistryInterface` is implemented by the two state stores in `arazzo-runtime` and consumed by `StepOutcomeHandler`, `CorrelationResumer`, `StepExecutionWorker`, `AsyncApiStepExecutor`, `AsyncGraphSeams` and the Laravel bindings — update the `implements` clauses on both stores to the contracts FQCN. `ExecutionException` has exactly 4 referencing files, all in runner (`SubWorkflowStepExecutor`, `AsyncApiStepExecutor`, and the two that stay). Add `"alama/arazzo-engine": "@dev"` + the `../engine` path repository to `packages/runner/composer.json`, and add `../engine/src` to `packages/runner/phpstan.neon.dist` `scanDirectories` if not already present.

Also create `packages/contracts/src/Interfaces/OutputExtractorInterface.php` (DF1) from today's concrete signature.

Run: `composer run test-contracts && composer run test-runtime && composer run test-runner && composer run test-laravel && composer run test-engine`. Expected: PASS — behaviour-preserving, only namespaces change.

- [ ] **Step 2: `git mv` the executors and rewrite namespaces**

```bash
mkdir -p packages/protocol-http/src/Execution/Interfaces packages/protocol-http/src/Protocol
git mv packages/runner/src/Execution/DefaultOpenApiExecutor.php packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php
git mv packages/runner/src/Execution/StepOutputExtractor.php        packages/protocol-http/src/Execution/StepOutputExtractor.php
git mv packages/runner/src/Execution/ResponseSchemaValidator.php    packages/protocol-http/src/Execution/ResponseSchemaValidator.php
git mv packages/runner/src/Protocol/HttpStepExecutor.php            packages/protocol-http/src/Protocol/HttpStepExecutor.php
git mv packages/runner/src/Protocol/AsyncApiStepExecutor.php        packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php
```

Namespace changes: `Alama\Arazzo\Runner\Execution` → `Alama\Arazzo\Protocol\Http\Execution` for the three `Execution/` classes; `Alama\Arazzo\Runner\Protocol` → `Alama\Arazzo\Protocol\Http\Protocol` for the two executors.

Then re-point every import in the five moved files:
- `HttpStepExecutor` / `AsyncApiStepExecutor`: `ExecutionException` → `Alama\Arazzo\Engine\Exceptions\ExecutionException` (Step 1); `PendingCorrelationRegistryInterface` → `Alama\Arazzo\Contracts\Interfaces\…`; `HttpClientInterface` from `Alama\Arazzo\Contracts\Interfaces\…`; `EvaluationEngineInterface` from `Alama\Arazzo\Evaluation\EvaluationEngineInterface` (**not** `…\Evaluation\Interfaces\…`, and **not** the similarly-named `Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface` that `ExecutionGraphFactory`/`RunnerGraphBuilder` take as their separate `$inspector` param); `EvaluationContext` from `Alama\Arazzo\Expression\Data\EvaluationContext`. There is **no** `ExpressionValueResolver` and **no** `ExecutionEvaluationInput` any more (DF2). `StepOutputExtractor` is in-package now, so drop its `use`.
- `StepOutputExtractor`: add `implements Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface`.
- `DefaultOpenApiExecutor`: keeps its Guzzle/PSR imports; its operation types are `Alama\Arazzo\Document\ResolvedOperation` (the model, no `Normalizer` segment) and `OpenApiOperationHandle`, which F1.1 relocated in-package.
- Add/keep `@internal stays out of the advertised contract; not part of the public API surface` on all five.

**The DIP check — protocol-http must now have zero runner references:**
```bash
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src || echo "DIP clean"
```
Expected: `DIP clean`. If anything remains, it is a runner type this task missed — move it to contracts/engine rather than adding a `scanDirectories` entry.

- [ ] **Step 3: Split `OpenApiExecutorInterface` into canonical (protocol-http) + BC alias (runner)**

`packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — canonical. Copy the exact current method set/signatures from `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` verbatim, namespace `Alama\Arazzo\Protocol\Http\Execution\Interfaces`.

`packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — keep the file and identical method signatures so PHPStan/types line up, and mark:

```php
/**
 * @deprecated relocated to
 * Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface
 * in arazzo-protocol-http (Phase F1). Keep this FQCN as a BC alias.
 * @internal BC alias; register arazzo-protocol-http to obtain the canonical type.
 */
```

If PHPStan flags the alias as deprecated/unused, add a `@phpstan-ignore` with a comment, or `extends` the canonical interface — whichever keeps the runner build green. Never remove the runner FQCN in this phase.

**Verify the DIP guard bites before committing:**
```bash
# temporarily add: use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
composer run test-http   # expect RED on arch('protocol-http does not depend on the runner')
# revert the import, re-run, expect GREEN
```

- [ ] **Step 4: Remove protocol-http defaults from the runner's sync path**

`ExecutionGraphFactory` today takes `(OperationRuntime $operations, Evaluation\EvaluationEngineInterface $evaluationEngine, Expression\Interfaces\ExpressionEngineInterface $inspector, ?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null)` and, in `createWorkflowExecutor()`, news a `Client`/`HttpFactory` (lines 38-39), a `StepOutputExtractor` (41), a `ResponseSchemaValidator` (42) and a `DefaultOpenApiExecutor` (46). All four constructions go. New shape — keep `$operations` and `$inspector` as they are, drop the two transport params, add the three value seams:

```php
public function __construct(
    private readonly OperationRuntime $operations,
    private readonly EvaluationEngineInterface $evaluationEngine,
    private readonly ExpressionEngineInterface $inspector,
    private readonly ?OpenApiExecutorInterface $openApiExecutor = null,
    private readonly ?OutputExtractorInterface $outputExtractor = null,
    private readonly ?ResponseValidatorInterface $schemaValidator = null,
) {}

public function createWorkflowExecutor(): WorkflowExecutor
{
    $openApiExecutor = $this->openApiExecutor
        ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
    $outputExtractor = $this->outputExtractor
        ?? throw new ExecutionException('No output extractor configured; register alama/arazzo-protocol-http (Phase F1).');
    $schemaValidator = $this->schemaValidator
        ?? throw new ExecutionException('No response validator configured; register alama/arazzo-protocol-http (Phase F1).');
    // build WorkflowExecutor(new StepExecutor($openApiExecutor, …, $outputExtractor, $schemaValidator, …), …)
    // — the same argument order StepExecutor has today after c2b03fb
}
```

`StepExecutor::__construct` keeps its shape from `c2b03fb` Task 2.1 but types `OutputExtractorInterface $outputExtractor` instead of the concrete `StepOutputExtractor`.

- `RunnerFacade`: forward the seams — `__construct` gains the three nullable params, passed to `ExecutionGraphFactory`. Callers who omit them get the throw *at execution time* (accepted: standalone document/runner consumers must register `arazzo-protocol-http`).
- `RunnerGraphBuilder`: the factory receives `$this->openApiExecutor` / `$this->outputExtractor` / `$this->schemaValidator` (nullable) in place of today's `?ClientInterface` / `?RequestFactoryInterface` (lines 21-22) — it currently forwards `$this->httpClient, $this->requestFactory` into `AsyncExecutionGraphAssembler`, so the same substitution applies on the async path. F1.3 feeds the real objects from Laravel.

- [ ] **Step 5: Remove protocol-http defaults from the async path**

`packages/runner/src/Execution/AsyncExecutionGraphAssembler.php::assemble()` currently does `new Client()`, `new HttpFactory()`, `new DefaultOpenApiExecutor(...)` and default-constructs `StepOutputExtractor`/`ResponseSchemaValidator`. After this task, `assemble()` must not construct any `Alama\Arazzo\Protocol\...` type:

```php
$openApiExecutor = $seams->openApiExecutor
    ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
$outputExtractor = $seams->outputExtractor
    ?? throw new ExecutionException('No output extractor configured; register alama/arazzo-protocol-http (Phase F1).');
$schemaValidator = $seams->schemaValidator ?? throw new ExecutionException('No response validator configured; …');
```

`AsyncGraphSeams` gains `public ?OutputExtractorInterface $outputExtractor = null` alongside the existing `$schemaValidator`; `$openApiExecutor` keeps its `?OpenApiExecutorInterface` type (the `@deprecated` alias — same FQCN, no signature churn). Note `c2b03fb` decision 4 deliberately made extraction **non**-overridable; DF1 reverses that only because the class now lives in another package, and the reversal is what lets a non-HTTP graph run without protocol-http installed. Leave a `// cleans up in F1.3` marker where a concrete protocol-http instance must flow down from Laravel.

**Sweep guard (must be empty after this task):**
```bash
rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor|StepOutputExtractor|ResponseSchemaValidator)\(" packages/runner/src packages/document/src
rg -n "Protocol\\\\Http" packages/runner/src packages/document/src packages/sources/src
```
Any hit in the first = a core→protocol construction; eliminate it. The second must be empty for `document` and `sources`; `runner` may still name `Alama\Arazzo\Protocol\Http` until F2.0 resolves the assembler, so note any hit and carry it into F2.0's checklist.

- [ ] **Step 6: PHPStan scan directories**

In `packages/runner/phpstan.neon.dist` add `../protocol-http/src` to `scanDirectories` (and keep `../engine/src` added in Step 1). Rationale: `runner` reads `OpenApiOperationHandle` and the relocated executor interface, so it must resolve them without a composer dependency. Analyse runs from repo root so `cebe/php-openapi` resolves via protocol-http's `require`.

Do **not** add `../protocol-http/src` to `packages/document/phpstan.neon.dist` or `packages/sources/phpstan.neon.dist` — scanning protocol-http from either would paper over exactly the layer-0→protocol import the guards forbid; assert the absence with the `arch('document does not depend on protocol-http')` rule above plus a sibling one for `Alama\Arazzo\Sources`.

- [ ] **Step 7: Move the tests that exercise relocated classes**

```bash
git mv packages/runner/tests/Execution/DefaultOpenApiExecutorTest.php packages/protocol-http/tests/Execution/DefaultOpenApiExecutorTest.php
git mv packages/runner/tests/Execution/HttpStepExecutorTest.php         packages/protocol-http/tests/Protocol/HttpStepExecutorTest.php
git mv packages/runner/tests/Execution/AsyncApiStepExecutorTest.php     packages/protocol-http/tests/Protocol/AsyncApiStepExecutorTest.php
git mv packages/runner/tests/Execution/AsyncApiSpecVersionBranchTest.php packages/protocol-http/tests/Protocol/AsyncApiSpecVersionBranchTest.php
```

Update namespaces in the moved tests to `Alama\Arazzo\Tests\Protocol\Http\Execution|Protocol`. The `Mockery::mock(StepOutputExtractor::class)` doubles from `c2b03fb` Task 2.2 now target a protocol-http class — switch them to `Mockery::mock(OutputExtractorInterface::class)` (this is exactly why DF1 re-introduces the interface). Test fixtures/HTTP mocks move with them. **Do not move** `RunnerFacadeTest`/`ExecutionGraphFactoryTest`/`AsyncExecutionGraphAssemblerTest` — rewrite those in place to inject small in-repo fakes of the three seams (runner tests may not require protocol-http; the real vertical slice is asserted under `test-laravel` + the final gate).

- [ ] **Step 8: Gates**

```bash
composer run test-http && composer run analyse-http
composer run test-runner && composer run analyse-runner
composer run test-sources && composer run analyse-sources
composer run test-document && composer run analyse-document
composer run test-contracts && composer run test-runtime && composer run test-engine
```

Expected: runner tests green using the fakes; the two protocol sweeps empty for `document`/`sources`; `rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src` empty.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(protocol-http): relocate HTTP/AsyncAPI executors, promote executor seam types (F1.2)"
```

---

## Task F1.3: Laravel default wiring + `ProtocolHttpServiceProvider` + registrar + plugin tags

Make the composed app (laravel + umbrella) work with zero config: bind the protocol-http classes into the container, register them into the D/E registries under the `arazzo.plugins.*` tag vocabulary (G1), and update the Laravel bindings that currently construct classes that no longer exist there.

**Files created:**
- `packages/protocol-http/src/Laravel/ProtocolHttpServiceProvider.php`
- `packages/protocol-http/src/Registrar/ProtocolHttpRegistrar.php`
- `packages/protocol-http/config/arazzo.php` (G3: `arazzo.plugins.tags` default `['arazzo.plugins.*']`, `arazzo.plugins.operation_executor` default `'arazzo.plugins.operation-executor'`)
- `packages/protocol-http/tests/Laravel/ProtocolHttpServiceProviderTest.php`

**Files edited:**
- `packages/laravel/composer.json` — require + repositories only (providers auto-discover from the package's own `extra.laravel.providers`, F1.0).
- `packages/laravel/src/Bindings/ResolverBindings.php` — construct protocol-http normalizer classes + bind `OpenApiOperationResolverInterface` → the protocol class.
- `packages/laravel/src/Bindings/FacadeBindings.php` — feed all three seams into `RunnerGraphBuilder`/`RunnerFacade`.
- `packages/laravel/src/Bindings/ExecutionBindings.php` + `packages/laravel/src/Support/AsyncGraphResolver.php` — feed the seams into `AsyncGraphSeams`.

**Interfaces:**
- Consumes: `SourceNormalizerRegistryInterface`, `OperationExecutorRegistry`, `ResponseValidatorDispatcher`, `PluginRegistry` (G1) — via the G3-located tag names, resolving whatever is on `main` today and noting the hand-off.
- Produces: `Alama\Arazzo\Protocol\Http\Laravel\ProtocolHttpServiceProvider`, `Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar`.

- [ ] **Step 1: Registrar (framework-agnostic)**

`packages/protocol-http/src/Registrar/ProtocolHttpRegistrar.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Http\Registrar;

/**
 * Framework-agnostic registration of the HTTP/OpenAPI vertical slice into the
 * core SPI registries. Used by the Laravel provider and by CLI/umbrella wiring.
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtocolHttpRegistrar
{
    public function __construct(/* registry dependencies resolved from constructor */) {}

    public function registerInto(
        ?SourceNormalizerRegistryInterface $sourceRegistry = null,
        ?OperationExecutorRegistry $operationExecutorRegistry = null,
        ?ResponseValidatorDispatcher $responseValidators = null,
    ): void
    {
        $sourceRegistry?->register(new OpenApiSourceNormalizer(/* loader + detector + normalizers */));
        // The step-protocol plugins, NOT DefaultOpenApiExecutor: the registry holds
        // OperationExecutorPluginInterface implementations keyed by protocol.
        $operationExecutorRegistry?->register(
            new HttpStepExecutor(/* openApiExecutor + engine collaborators */),
            new AsyncApiStepExecutor(/* openApiExecutor + pendingCorrelationRegistry */),
        );
        $responseValidators?->register(new ResponseSchemaValidator(/* ... */), /* priority */);
    }
}
```

Construct the exact concrete wiring from the D/E/G implementations on `main`; `priority` via class constants + descending sort per Global Constraints. If PHPStan complains the `?Type`s clash, annotate `@phpstan-ignore` + a `// TODO(G)` and wire to container callbacks.

- [ ] **Step 2: `ProtocolHttpServiceProvider`**

`packages/protocol-http/src/Laravel/ProtocolHttpServiceProvider.php`, extends `Illuminate\Support\ServiceProvider`:

```php
final class ProtocolHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/arazzo.php', 'arazzo');

        $this->app->singleton(DefaultOpenApiExecutor::class, fn () => /* Guzzle client (config default timeout), PSR factories, logger */);
        $this->app->singleton(StepOutputExtractor::class, fn ($app) => /* operation resolver */);

        // Tag the protocol plugins + normalizers + validators; the operation-executor
        // sub-tag is what OperationExecutorRegistry consumes (G3).
        $this->app->tag([/* normalizer + step-executor + validator services */], config('arazzo.plugins.tags.0', 'arazzo.plugins.*'));

        // These are *value* seams consumed by the graph, not registry entries,
        // so they bind directly to their protocol-http implementations.
        $this->app->singleton(OpenApiExecutorInterface::class, fn ($app) => $app->make(DefaultOpenApiExecutor::class));
        $this->app->singleton(OutputExtractorInterface::class, fn ($app) => $app->make(StepOutputExtractor::class));
    }

    public function boot(): void
    {
        $this->app->get(ProtocolHttpRegistrar::class)->registerInto(
            sourceRegistry: $this->app->make(SourceNormalizerRegistryInterface::class),
            operationExecutorRegistry: $this->app->make(OperationExecutorRegistry::class),
            responseValidators: $this->app->make(ResponseValidatorDispatcher::class),
        );
    }
}
```

Exact service names finalised against the G-plan's tag + `ProtocolDiscovery::tags()`/`operationExecutorTag()` (G3) once G lands; until then the provider registers under `arazzo.plugins.*` and `arazzo.plugins.operation-executor` as the default config, and the provider test asserts the tag keys exist. Keep the provider's `use Illuminate\...` limited to `ServiceProvider` + the tagged container (the F1.0 arch test ignores the `Laravel` sub-namespace).

- [ ] **Step 3: Update Laravel resolver bindings**

`packages/laravel/src/Bindings/ResolverBindings.php` — the `OpenApiDocumentLoader` / `OpenApiOperationResolver` / `PreflightValidator` singletons now point at:
- `Alama\Arazzo\Protocol\Http\Normalizer\OpenApiDocumentLoader`
- `Alama\Arazzo\Protocol\Http\Normalizer\OpenApiOperationResolver`
- `Alama\Arazzo\Sources\Validator\PreflightValidator` (its new `OpenApiOperationResolverInterface` param resolves to the protocol-http `OpenApiOperationResolver`).

Edit `packages/laravel/tests/Bindings/ResolverBindingsTest.php` accordingly (assert the resolver binding returns the protocol class, preflight still passes the existing fixture documents).

- [ ] **Step 4: Feed the seams into the async + sync graphs**

- `ExecutionBindings` / `AsyncGraphResolver`: `self::seams($app)` gains `$seams->openApiExecutor = $app->make(OpenApiExecutorInterface::class);` **and** `$seams->outputExtractor = $app->make(OutputExtractorInterface::class);`. The existing `$seams->schemaValidator` handling is unchanged.
- `FacadeBindings`: F1.2 Step 4 replaced the `RunnerGraphBuilder`/`RunnerFacade` `$httpClient` seam with three nullable seams, so this binding now passes `$app->make(OpenApiExecutorInterface::class)` + `$app->make(OutputExtractorInterface::class)` into the builder. If the `ClientInterface`/`RequestFactoryInterface` container bindings are left unused by that swap, leave them registered (they are public container bindings) and note them for a later cleanup — do not delete them in this task.
- The `OperationExecutorRegistry` filled in `boot()` already holds the two protocol plugins, so F2.0's assembler only has to *read* it; no extra wiring is needed here.

- [ ] **Step 5: Laravel provider + wiring tests**

`packages/protocol-http/tests/Laravel/ProtocolHttpServiceProviderTest.php` (extends a testbench `TestCase`):
- assert `$app->make(OpenApiExecutorInterface::class)` resolves `DefaultOpenApiExecutor`;
- assert `$app->make(OutputExtractorInterface::class)` resolves `StepOutputExtractor`;
- assert the tagged services exist under `arazzo.plugins.operation-executor` when the config key is used;
- assert `PreflightValidator` still validates an OpenAPI fixture end-to-end through the container;
- assert an end-to-end workflow execute: run a `WorkflowExecutor` + `HttpStepExecutor` against a PSR-18 fixture client (an in-test fake HTTP client returning canned responses for the step's URL) — this is the F1 vertical-slice proof.

Laravel suite: add `"alama/arazzo-protocol-http": "@dev"` + the path repository to `packages/laravel/composer.json`, then:

```bash
composer update alama/arazzo-protocol-http --with-dependencies --no-interaction
composer run test-http && composer run test-laravel
composer run analyse-laravel && composer run analyse-http
```

- [ ] **Step 6: Root-level integration gate**

```bash
composer run test && composer run analyse && make verify
```

This exercises the `packages/core` and root test suites that construct the moved classes (`Conformance\ConformanceHarness`, `tests/Validator/PreflightValidatorTest.php`, `tests/Validator/InputsPreValidationTest.php`, runner graph tests) through root autoload-dev. `make verify` runs format+analyse+test; **no diffs may remain after `composer run format`**.

- [ ] **Step 7: Commit**

```bash
git add -A packages/laravel packages/protocol-http composer.json composer.lock
git commit -m "feat(protocol-http): Laravel default wiring, provider, registrar, plugin tags (F1.3)"
```

---

## Task F1.4: F1 verification slice

Confirm F1 delivers a working "HTTP protocol on a composed core" slice with zero core edits, and close any swept-up references.

**Files:**
- Sweep: `rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src` — must be empty.
- Sweep: `rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor|StepOutputExtractor|ResponseSchemaValidator)\(" packages/{document,runner,sources,contracts,expression,evaluation}/src` — must be empty: neither core nor runner constructs protocol-http transport or executors. (`RequestCompiler` and friends are constructed inside the runner package, which is correct.)
- Sweep: `rg -n "use Alama\\\\Arazzo\\\\Protocol\\\\" packages/{contracts,expression,document,evaluation,runtime,events,sources,engine}/src` — must be empty (H2 on-disk invariant). `packages/runner` is intentionally **not** in this list; F2.0 finishes removing the assembler's direct references.
- Sweep: `rg -n 'Arazzo\\\\(Runner|Protocol)\\\\(Execution|Protocol|Normalizer)' packages/{document,sources}/src` — must be empty.
- Verify `packages/core/tests/**` that reference the moved classes still pass via root autoload-dev.

**Interfaces:**
- Produces: end-to-end proof — `arazzo-protocol-http` executed through a composed `arazzo-core` + `laravel-arazzo` app (testbench) without touching any core package.

- [ ] **Step 1: Sweeps** — run the four `rg` sweeps above, fix any hits by re-issuing local wiring (not by importing protocol into core).
- [ ] **Step 2: Full F1 gate**

```bash
composer run format
composer run analyse
composer run test
make verify
```

`make verify` green; `composer run format` leaves zero diffs (run Pint first, then re-run). Confirm `analyse-http`, `analyse-document`, `analyse-sources`, `analyse-runner`, `analyse-laravel` all pass.

- [ ] **Step 3: Commit + report F1 state**

```bash
git add -A
git commit -m "chore(protocol-http): F1 verification slice green (F1.4)"
```

Report: `alama/arazzo-protocol-http` is the ONLY place Guzzle/cebe + OpenAPI normalizers + HTTP executors + output extraction exist; `protocol-http` has no `Alama\Arazzo\Runner` import; no package below runner imports `Alama\Arazzo\Protocol\`; `make verify` green.

---

## Task F2.0: Split `runner`'s flat `Execution/` into `Sync\` and `Async\`

Phase E deliberately left `runner` with one flat `Execution/` namespace because the package boundary mattered first. This task draws the internal line the package boundary made possible, and it is where actor-in-loop work lands.

**Why sync and async share a package but not a namespace:** the three coupling dimensions point to one package — integration strength is low (`StepExecutor` imports only `OpenApiExecutorInterface`; `WorkflowExecutor` imports only events/data/enum), distance is short (same vocabulary, no translation layer), and volatility is identical (both change on any step-lifecycle edit, so splitting into packages would duplicate the change rather than decouple it). Async adds no package dependency — the queue arrives via `QueueDriverInterface`, HTTP via `HttpClientInterface` — so a sync-only user pays download size, not dependency weight. What the split buys is a reviewable, test-enforced boundary, enforced with namespaces plus arch rules rather than more composer packages.

**Moves:**

| Target | Classes |
|---|---|
| `packages/runner/src/Sync/` | `Execution/StepExecutor.php`, `Execution/WorkflowExecutor.php`, `Execution/WorkflowEngine.php`, `Execution/ExecutionGraphFactory.php`, `Protocol/SubWorkflowStepExecutor.php` |
| `packages/runner/src/Async/` | `Execution/StepExecutionWorker.php`, `Execution/StepOutcomeHandler.php`, `Execution/CorrelationResumer.php`, `Execution/SubWorkflowInvoker.php`, `Execution/AsyncExecutionGraphAssembler.php`, `Jobs/ExecuteStepJob.php`, `Jobs/ResumeCorrelationJob.php` |
| `packages/runner/src/` (root, shared) | `Execution/UnifiedStepCarrier.php`, `OperationExecutorRegistry.php`, `ResponseValidatorDispatcher.php`, `RunnerFacade.php`, `RunnerGraphBuilder.php`, `AsyncGraphSeams.php`, `AsyncExecutionGraph.php`, `Execution/InMemoryDefinitionRegistry.php`, `Execution/SyncQueueDriver.php` |
| `packages/runner/src/Interfaces/` | `Execution/Interfaces/ProtocolExecutorRegistryInterface.php` — E9 deprecates it in favour of `OperationExecutorRegistry`, but the FQCN stays importable |

**The pipeline helpers stay flat in `Execution/`** (`RequestCompiler`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`, `IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver`, `Data/InjectionResult`). They are shared by both paths — `StepExecutor` and `StepExecutionWorker` both drive `RequestCompiler` — so putting them in `Sync\` would make async depend on sync. `Execution/Data/*` and `Execution/Enum/*` stay put for the same reason (`Transition`/`TransitionType` are consumed by both the engine bridge and the async outcome handler).

**Do not resurrect `6e0a088`'s deleted classes** (DF5). `packages/runner/tests/Protocol/` is now empty — `rmdir` it (and `packages/runner/src/Jobs/` once emptied).

**`SubWorkflowInvoker` (async) and `SubWorkflowStepExecutor` (sync) are near-duplicates by name.** They are *not* the same class and must not be merged — the dead `Protocol/SubWorkflowExecutor` was the redundant third one and `6e0a088` removed it. Move each to its namespace and leave a note if you find them genuinely redundant.

**Files:**
- Move: as tabled above, plus matching tests
- Modify: every importer in `packages/runner`, `packages/laravel`, `packages/cli`; the `folded request pipeline stays transport-agnostic` guard's `use` lines in `packages/runner/tests/ArchTest.php`

**Interfaces:**
- Namespaces `Alama\Arazzo\Runner\Sync\...` and `Alama\Arazzo\Runner\Async\...` are new; the facade classes stay at `Alama\Arazzo\Runner\...`.
- Arch guards (below) are the deliverable, not the moves.

- [ ] **Step 1: Move the classes and rewrite namespaces**

```bash
mkdir -p packages/runner/src/Sync packages/runner/src/Async

git mv packages/runner/src/Execution/StepExecutor.php              packages/runner/src/Sync/StepExecutor.php
git mv packages/runner/src/Execution/WorkflowExecutor.php          packages/runner/src/Sync/WorkflowExecutor.php
git mv packages/runner/src/Execution/WorkflowEngine.php            packages/runner/src/Sync/WorkflowEngine.php
git mv packages/runner/src/Execution/ExecutionGraphFactory.php     packages/runner/src/Sync/ExecutionGraphFactory.php
git mv packages/runner/src/Protocol/SubWorkflowStepExecutor.php    packages/runner/src/Sync/SubWorkflowStepExecutor.php

git mv packages/runner/src/Execution/StepExecutionWorker.php           packages/runner/src/Async/StepExecutionWorker.php
git mv packages/runner/src/Execution/StepOutcomeHandler.php           packages/runner/src/Async/StepOutcomeHandler.php
git mv packages/runner/src/Execution/CorrelationResumer.php           packages/runner/src/Async/CorrelationResumer.php
git mv packages/runner/src/Execution/SubWorkflowInvoker.php            packages/runner/src/Async/SubWorkflowInvoker.php
git mv packages/runner/src/Execution/AsyncExecutionGraphAssembler.php  packages/runner/src/Async/AsyncExecutionGraphAssembler.php
git mv packages/runner/src/Jobs/ExecuteStepJob.php                    packages/runner/src/Async/ExecuteStepJob.php
git mv packages/runner/src/Jobs/ResumeCorrelationJob.php              packages/runner/src/Async/ResumeCorrelationJob.php

rmdir packages/runner/src/Jobs packages/runner/tests/Protocol 2>/dev/null || true
```

Namespace declarations: `namespace Alama\Arazzo\Runner\Execution;` → `namespace Alama\Arazzo\Runner\Sync;` (five sync classes) or `namespace Alama\Arazzo\Runner\Async;` (five async classes), and `namespace Alama\Arazzo\Runner\Protocol;` → `namespace Alama\Arazzo\Runner\Sync;` for `SubWorkflowStepExecutor`. The root-namespace classes (`UnifiedStepCarrier`, the two dispatchers, the facade trio) lose their `use Alama\Arazzo\Runner\Execution\...` lines for moved classes and gain `use Alama\Arazzo\Runner\Sync\...` / `use Alama\Arazzo\Runner\Async\...`.

Then fix importers repo-wide:
```bash
rg -l 'Alama\\Arazzo\\Runner\\Execution\\(StepExecutor|WorkflowExecutor|WorkflowEngine|ExecutionGraphFactory|StepExecutionWorker|StepOutcomeHandler|CorrelationResumer|SubWorkflowInvoker|AsyncExecutionGraphAssembler)' packages/
rg -l 'Alama\\Arazzo\\Runner\\Jobs\\' packages/
```
Each match must be classified by hand — `Async\...` for async callers, `Sync\...` for sync callers, and a root-namespace consumer (the facade) may import either.

Move the matching tests:
```bash
git mv packages/runner/tests/Execution/StepExecutorTest.php              packages/runner/tests/Sync/StepExecutorTest.php
git mv packages/runner/tests/Execution/WorkflowExecutorTest.php          packages/runner/tests/Sync/WorkflowExecutorTest.php
git mv packages/runner/tests/Execution/WorkflowExecutorEventsTest.php    packages/runner/tests/Sync/WorkflowExecutorEventsTest.php
git mv packages/runner/tests/Execution/WorkflowEngineTest.php            packages/runner/tests/Sync/WorkflowEngineTest.php
git mv packages/runner/tests/Execution/SubWorkflowStepExecutorTest.php   packages/runner/tests/Sync/SubWorkflowStepExecutorTest.php
git mv packages/runner/tests/Execution/StepOutcomeSubWorkflowRoutingTest.php packages/runner/tests/Sync/StepOutcomeSubWorkflowRoutingTest.php

git mv packages/runner/tests/Execution/StepExecutionWorkerTest.php        packages/runner/tests/Async/StepExecutionWorkerTest.php
git mv packages/runner/tests/Execution/StepExecutionWorkerEventsTest.php  packages/runner/tests/Async/StepExecutionWorkerEventsTest.php
git mv packages/runner/tests/Execution/StepOutcomeHandlerTest.php         packages/runner/tests/Async/StepOutcomeHandlerTest.php
git mv packages/runner/tests/Execution/StepOutcomeHandlerEventsTest.php   packages/runner/tests/Async/StepOutcomeHandlerEventsTest.php
git mv packages/runner/tests/Execution/StepOutcomeRetrySemanticsTest.php   packages/runner/tests/Async/StepOutcomeRetrySemanticsTest.php
git mv packages/runner/tests/Execution/StepOutcomeSelectorOutputsTest.php  packages/runner/tests/Async/StepOutcomeSelectorOutputsTest.php
git mv packages/runner/tests/Execution/CorrelationResumerTest.php         packages/runner/tests/Async/CorrelationResumerTest.php
git mv packages/runner/tests/Execution/CorrelationResumerEventsTest.php   packages/runner/tests/Async/CorrelationResumerEventsTest.php
git mv packages/runner/tests/Execution/SubWorkflowInvokerTest.php          packages/runner/tests/Async/SubWorkflowInvokerTest.php
git mv packages/runner/tests/Execution/AsyncExecutionGraphAssemblerTest.php packages/runner/tests/Async/AsyncExecutionGraphAssemblerTest.php
```

Two things this list cannot express, so verify by hand:
- **Event-suffix variants are per-class, not per-file-count.** Every `*EventsTest` / `*SemanticsTest` / `*SelectorOutputsTest` belongs to whichever class its subject names, and the `StepOutcome*` ones go under `Async\`. Re-run `ls packages/runner/tests/Execution` after the moves and confirm nothing referencing a moved class was left behind. Note `StepOutcomeSubWorkflowRoutingTest` is listed under `Sync\` above because its subject is the sync `SubWorkflowStepExecutor` — verify against the class it actually exercises before committing.
- **There is no `tests/Async/` and no `tests/Protocol/` on this branch** — `6e0a088` removed the first and emptied the second. The `git mv` targets above therefore create them.

Leave in `tests/Execution/` everything whose subject did not move: the pipeline tests (`RequestCompilerTest`, `ParameterSerializerTest`, `TypeCasterTest`, `SchemaValidatorTest`, `IdempotencyKeyInjectorTest`, `InjectionResultTest`, `ReusableParameterResolverTest`, `StepParameterMergerTest`), plus `AdapterParityTest`, `ArazzoCriteriaEvaluatorTest`, `ArazzoOutputExtractorTest`, `ArazzoSchemaValidatorTest`, `AsyncGraphSeamsTest`, `ExecutionStateTest`, `LedgerRegressionTest`, `ReceiveTimeoutTest`, `RegressionTest`, `ResumerTestHelpers.php`, `RuntimeFailureClassificationTest`, `SharedBudgetTest`, `StateMergeTest`, `WorkflowContextTest`, and the `Validation/`+`Conformance/`+`State/`+`Support/` directories.

- [ ] **Step 2: Resolve the assembler's protocol imports through the registry**

`AsyncExecutionGraphAssembler` still hard-codes the protocol executor list (importing the two `Alama\Arazzo\Protocol\Http\Protocol\...` executors by name). `OperationExecutorRegistry` exists for exactly this; finish the job:

- Replace direct `new HttpStepExecutor(...)` / `new AsyncApiStepExecutor(...)` / `new SubWorkflowStepExecutor(...)` construction with construction done by whoever composes the graph, then `register()`ed into the injected `OperationExecutorRegistry`.
- `AsyncGraphSeams` gains `?OperationExecutorRegistry $executorRegistry = null` alongside the existing nullable seams; `RunnerFacade` and the Laravel `AsyncGraphResolver` populate it (F1.3 already builds these objects, so it is the natural home for the construction).
- If no registry is injected and the step needs a protocol executor, throw the same `ExecutionException` shape F1.2 uses for the missing `OpenApiExecutorInterface`, naming `arazzo-protocol-http` in the message.
- The async worker resolves its executor via `findExecutor($step, $document)` against the registry — verify that call site still type-hints `StepProtocolExecutorInterface` (contracts) and not a concrete class.

- [ ] **Step 3: Add the internal arch guards**

Extend `packages/runner/tests/ArchTest.php` (update the existing `runner does not depend on illuminate framework` / `does not leak expression internals` expectations from `Runner\Execution|Jobs|Protocol` to `Runner\Execution|Sync|Async|Jobs`, and drop the now-empty `Jobs`/`Protocol` entries):

```php
arch('sync path is queue-free')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse(['Alama\Arazzo\Runner\Async', 'Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface']);

arch('async path may compose the sync path')
    ->expect('Alama\Arazzo\Runner\Async')
    ->toUse('Alama\Arazzo\Runner\Sync');

arch('nothing below the facade depends on the facade')
    ->expect(['Alama\Arazzo\Runner\Sync', 'Alama\Arazzo\Runner\Async'])
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');
```

The first rule is the one that matters: it keeps the fast path free of queue concerns, which is the whole reason sync and async are distinguishable at all. Verify it bites by temporarily importing an async class into `Sync/StepExecutor.php`, confirming RED, then reverting. The second rule asserts `Async → Sync` is *allowed*; it documents the direction rather than forbidding it, so a future reader does not "fix" it.

- [ ] **Step 4: Gates + commit**

```bash
composer run test-runner && composer run analyse-runner
composer run test-laravel && composer run test-cli && composer run test-engine
composer run format
git add -A
git commit -m "refactor(runner): split Execution/ into Sync\\ and Async\\ namespaces, resolve protocol executors via registry (F2.0)"
```

---

## Task F2.1: Actor-in-loop seams in the `Async\` namespace — and restore the correlation-id guard

The spec's actor-in-loop work (interaction steps, `AwaitingActorInput` → `ActorInputReceived`, handoff to a human, resumption) is an execution-model concern. It lands here, in `Async\`, plus at most one contracts-level interface — **no new package**.

**Why it belongs in `Async\` and not a package of its own:** it changes when and where a step runs and how a suspended step is resumed, both already `Async\` concerns. A new package would have exactly one consumer and no independent volatility.

**This task also closes the one behavioural regression from `6e0a088`** (DF6): the scalar guard on the correlation id was lost when suspension moved from the deleted `Async/SuspensionHandler` into `Execution/StepExecutionWorker`.

**Files:**
- Modify: `packages/runner/src/Async/StepOutcomeHandler.php` — route the suspended interaction outcome to the handoff path instead of the retry path
- Modify: `packages/runner/src/Async/StepExecutionWorker.php` — restore the scalar guard (Step 2)
- Create (conditional): `packages/runner/src/Async/ActorHandoffRegistry.php` — only if the interaction flow needs a durable record of a handed-off step; prefer extending `PendingCorrelationRegistryInterface` (contracts, promoted in F1.2) over a parallel port
- Create: `packages/runner/tests/Async/ActorHandoffTest.php`, and the regression test in `packages/runner/tests/Async/StepExecutionWorkerTest.php`
- Modify: `packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php` only if the interaction flow genuinely reuses that port; add a *separate* narrow interface here if it does not

**Interfaces:**
- `StepStateMachineEngine` (in `arazzo-engine`) already owns the `AwaitingActorInput` / `ActorInputReceived` transitions — **verified present** at `packages/engine/src/StepStateMachineEngine.php:52-53,140-151`, with the interaction bypass in `fromPending` at line 108. This task supplies the async side effects, not new states.
- If a durable handoff record needs a new port, it goes in `contracts/Interfaces/` and the implementation in `arazzo-runtime`'s `State/`. Do not put a new port in the runner.

- [ ] **Step 1: Confirm the state machine covers the transitions**

Read `packages/engine/src/StepStateMachineEngine.php` and confirm `fromPending` (interaction bypass), `fromAwaitingActorInput` and `fromActorInputReceived` exist as E6 specified. **They do** (see Interfaces). If that is no longer true at implementation time, stop and flag it: the state machine is E6's deliverable, not this task's.

- [ ] **Step 2: Restore the correlation-id scalar guard (DF6)**

`packages/runner/src/Async/StepExecutionWorker.php:170` currently reads:

```php
$correlationIdValue = (string) $this->evaluationEngine->evaluate($step->target->correlationId, new EvaluationContext($context, $step->stepId));
```

`evaluate()` returns `mixed`, so a correlation-id expression resolving to a non-`Stringable` object throws `Error: Object of class … could not be converted to string` inside the worker. The deleted `6e0a088^:packages/runner/src/Async/SuspensionHandler.php:47` guarded it. Restore exactly that shape:

```php
$evaluated = $this->evaluationEngine->evaluate($step->target->correlationId, new EvaluationContext($context, $step->stepId));
$correlationIdValue = is_scalar($evaluated) ? (string) $evaluated : '';
```

Add to `packages/runner/tests/Async/StepExecutionWorkerTest.php`: a `receive` step whose `correlationId` expression resolves to a non-scalar asserts an empty correlation id on the dispatched `CorrelationPendingEvent` rather than throwing.

- [ ] **Step 3: Implement the async handoff path**

Extend `StepOutcomeHandler` so a suspended outcome on an interaction step registers the pending correlation (or handoff record) and emits the existing `CorrelationPendingEvent` instead of scheduling a retry. Note `StepOutcomeHandler:117` currently maps `TransitionType::Suspend => null, // suspension handled by the executor layer` — the executor layer (`StepExecutionWorker:163-182`) owns the persistence + event, so this task adds the *interaction* routing (actor handoff vs. retry), not a second suspension writer. Keep the scalar guard from Step 2 if this path evaluates a correlation id.

- [ ] **Step 4: Test the handoff and resumption**

Add `packages/runner/tests/Async/ActorHandoffTest.php`: an interaction step that suspends writes the pending record and emits the event; resuming with the actor's input completes the step; a non-scalar correlation id produces an empty id rather than a cast error.

- [ ] **Step 5: Gates + commit**

```bash
composer run test-runner && composer run analyse-runner
composer run test-engine && composer run test-runtime
composer run format
git add -A
git commit -m "feat(runner): actor-in-loop handoff in Async\\, restore correlation-id scalar guard (F2.1)"
```

---

## Task F2.2: Close the `laravel` / `cli` facade leak

`packages/laravel` and `packages/cli` import ~26 `Alama\Arazzo\Runner\...` symbols, and among them are concretes that should never be reachable from a consumer: the two protocol executors, `SubWorkflowStepExecutor`, the jobs, `StepExecutor`, `StepExecutionWorker`, `StepOutcomeHandler`, `CorrelationResumer`, `WorkflowEngine`, `WorkflowExecutor`, `HttpClientInterface` and the `State\Interfaces\*` ports.

That is the leak: the facade exists (`RunnerFacade`, `RunnerFacadeInterface`, `RunnerGraphBuilder`, `RunnerGraphBuilderInterface`) and is bypassed anyway, which is why a separate `arazzo-runner-facade` package was rejected — an abstraction nobody honours is indirection without control. This task makes the facade real.

**Files:**
- Modify: `packages/laravel/src/**` (the `Bindings/*` registrars and `Support/AsyncGraphResolver.php`), `packages/cli/src/**`
- Modify: `packages/laravel/tests/**`, `packages/cli/tests/**` as needed
- Modify: root `composer.json` only if `laravel`/`cli` need new `require` entries for the protocol packages (they legitimately do — they are composition roots)

**Interfaces:**
- Produces: a documented public surface for consumers — `RunnerFacadeInterface`, `RunnerGraphBuilderInterface`, the `SyncQueueDriver` seam, and the plugin/registry entry points. Everything else becomes `@internal`.

- [ ] **Step 1: Inventory the leak precisely**

```bash
rg -o 'Alama\\Arazzo\\Runner\\[A-Za-z\\]*' packages/laravel/src packages/cli/src | sed 's/.*://' | sort -u
```

Save the list. Every symbol is either (a) facade/interface — allowed, (b) a contracts type re-exported through the runner — allowed, or (c) a concrete internal — must go. Expect the names to have moved in F2.0 (`Runner\Execution\StepExecutor` → `Runner\Sync\StepExecutor`, etc.).

- [ ] **Step 2: Replace concrete construction with facade calls**

In the Laravel bindings, stop `new`-ing `HttpStepExecutor` / `AsyncApiStepExecutor` / `SubWorkflowStepExecutor` / `StepExecutor` / `StepExecutionWorker` / `StepOutcomeHandler` / `CorrelationResumer`. Instead resolve the protocol objects from the container (F1.3's `ProtocolHttpRegistrar` already registers them) and hand them to the runner through the seams the facade exposes — `RunnerFacade::__construct(?OpenApiExecutorInterface, ?OutputExtractorInterface, ?ResponseValidatorInterface, ?OperationExecutorRegistry, ?QueueDriverInterface, …)` and `AsyncGraphSeams`' nullable fields.

In the CLI, do the same: build the registry + normalizers + validators once, hand them to `RunnerGraphBuilder`, and stop reaching past it.

**Any consumer that genuinely needs a symbol not on the facade** gets it added to the facade (with a test) rather than imported directly. If a symbol resists that, keep it out of `packages/laravel/src`'s public surface and note it in the commit message.

- [ ] **Step 3: Guard the leak so it does not come back**

Add to `packages/laravel/tests/Architecture/ArchTest.php` (and the equivalent in `packages/cli`):

```php
arch('laravel does not reach past the runner facade')
    ->expect('Alama\Arazzo\Laravel')
    ->not->toUse([
        'Alama\Arazzo\Runner\Sync\StepExecutor',
        'Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor',
        'Alama\Arazzo\Runner\Async\StepExecutionWorker',
        'Alama\Arazzo\Runner\Async\StepOutcomeHandler',
        'Alama\Arazzo\Runner\Async\CorrelationResumer',
        'Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor',
        'Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor',
    ]);
```

The guard lists *specific* concretes rather than the whole `Alama\Arazzo\Protocol\Http` namespace: consumers are allowed to name protocol *interfaces* and the registrar, and a blanket namespace ban would forbid legitimate composition-root wiring. Verify it bites with a temporary import, then revert.

- [ ] **Step 4: Gates + commit**

```bash
composer run test-laravel && composer run analyse-laravel
composer run test-cli && composer run analyse-cli
composer run test-runner
make verify
git add -A
git commit -m "refactor(laravel,cli): consume the runner through its facade instead of concrete internals (F2.2)"
```

---

## Task F2.3: `AsyncGraphSeams` + facade documentation pass

With the namespaces settled and the leak closed, the composition surface is worth stating once so the next protocol package has a template.

**Files:**
- Create: `docs/architecture/07-runner-package-map.md` — the package/layer table from this plan's header, the Sync/Async namespace split, which package owns which seam, and the arch rules that protect it. Follow the existing `docs/architecture/` convention: next free number after `06-laravel-integration.md`, with a mermaid diagram like its siblings.
- Modify: `docs/architecture/README.md` — add the `07` row to the index table **and** an `README --> D07` edge in the mermaid flowchart.
- Create: `packages/runner/README.md` — the consumer-facing entry point: require `alama/arazzo-runner`, register a protocol package, get a `RunnerFacade`. Only `packages/core` and `packages/laravel` have READMEs today, so match `packages/laravel/README.md`'s shape.

**Interfaces:**
- None. This task is documentation and must not change behaviour.

- [ ] **Step 1: Write the package map**

Include the layer table, dependency direction per package, the Sync/Async rule with its rationale, the DIP rule (`protocol-*` must not depend on `runner`), and the arch tests that enforce each. Record explicitly that the pipeline helpers live *inside* runner rather than in a layer-2 package, and why (the `c2b03fb` fold-back), so the next reader does not re-propose the extraction. Link `docs/research/2026-09-26-runner-package-split-validation.md` and `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md` as evidence.

- [ ] **Step 2: Write the runner README**

Show the minimum wiring for a non-Laravel consumer, and for Laravel point at auto-discovery. Keep it short.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "docs: add docs/architecture/07 runner package map, index it, add runner README (F2.3)"
```

---

## Task F3: Phase F gate — HTTP slice + runner structure + leak closed

**Files:**
- None to modify unless a gate fails.

**Interfaces:**
- Consumes: F1.0–F1.4, F2.0–F2.3.

- [ ] **Step 1: Run every suite**

```bash
composer run test-contracts && composer run test-expression && composer run test-evaluation
composer run test-document && composer run test-sources && composer run test-runtime && composer run test-events
composer run test-engine
composer run test-runner && composer run test-http && composer run test-laravel
```

Expected: PASS. (There is no `test-pipeline` — that script died with the package.)

- [ ] **Step 2: Run static analysis everywhere**

```bash
composer run analyse
```

Expected: PASS (0 errors) across all registered `analyse-*` scripts.

- [ ] **Step 3: Verify the DIP boundary and the layer invariants by sweep**

```bash
# the DIP: no protocol package may reach into the runner
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-*/src || echo "DIP clean"
# layers below runner never import a protocol package
rg -n 'Alama\\Arazzo\\Protocol' packages/{contracts,expression,document,evaluation,runtime,events,sources,engine}/src || echo "layers clean"
# no protocol package may reach the folded pipeline helpers
rg -n 'Alama\\Arazzo\\Runner\\Execution' packages/protocol-*/src || echo "no pipeline reach-through"
# no package but the umbrella/laravel/cli requires arazzo-runner
rg -n '"alama/arazzo-runner"' packages/*/composer.json
# sync stays queue-free
rg -n 'QueueDriverInterface' packages/runner/src/Sync || echo "sync queue-free"
# no request-pipeline anywhere
rg -n 'arazzo-request-pipeline|Arazzo\\RequestPipeline' packages/ composer.json || echo "pipeline refs clean"
```

Expected: `DIP clean`, `layers clean`, `no pipeline reach-through`, `sync queue-free`, `pipeline refs clean`, and the `arazzo-runner` sweep naming only `packages/laravel`, `packages/cli` and the umbrella `packages/core`.

- [ ] **Step 4: Run the repo gate**

Run: `make verify` (repo root). Expected: PASS.

- [ ] **Step 5: Mark this plan's steps complete and record in the spec**

Flip every `- [ ]` for F1 and F2 tasks to `- [x]`. Add to `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md` under the Phase F heading:

```markdown
Phase F status (F1–F3): ✅ Implemented — `arazzo-protocol-http` extracted, `arazzo-runner` split into `Sync\`/`Async\`, `laravel`/`cli` facade leak closed. See `plans/2026-09-08-phase-f-protocol-packages.md`.
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: Phase F1-F3 gate green (protocol-http, runner structure, facade leak)"
```

---

## Task F4.0: `arazzo-protocol-soap` package scaffold + root plumbing

First brand-new protocol (spec #17/#16): SOAP over PSR-18 with DOM + libxml, zero vendor — proof that a protocol adds a vertical slice with no core edits. Scaffold exactly like F1.0 minus the transport deps.

**Files:**
- Create `packages/protocol-soap/composer.json`, `phpstan.neon.dist`, `tests/{Pest.php,Architecture/ArchTest.php}`, `src/.gitkeep`
- Modify root `composer.json` (repositories, require, autoload-dev, `scripts`)

**Interfaces:**
- Produces: installable `alama/arazzo-protocol-soap`, `composer run analyse-soap`, `composer run test-soap`.

- [ ] **Step 1: Composer manifest**

```json
{
    "name": "alama/arazzo-protocol-soap",
    "description": "SOAP vertical slice for alama/arazzo-core: WSDL normalizer, SOAP executor, SoapResponseTransfer mapper, XSD response validator. Zero vendor, DOM + PSR-18.",
    "keywords": ["alama", "arazzo", "soap", "wsdl", "xsd", "protocol"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"},
        {"type": "path", "url": "../document"},
        {"type": "path", "url": "../expression"},
        {"type": "path", "url": "../evaluation"},
        {"type": "path", "url": "../engine"},
        {"type": "path", "url": "../sources"}
    ],
    "require": {
        "php": "^8.4",
        "ext-dom": "*",
        "ext-libxml": "*",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-engine": "@dev",
        "alama/arazzo-sources": "@dev",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.1",
        "psr/http-message": "^2.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0||^10.0||^11.0",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0"
    },
    "autoload": {"psr-4": {"Alama\\Arazzo\\Protocol\\Soap\\": "src/"}},
    "autoload-dev": {"psr-4": {"Alama\\Arazzo\\Tests\\Protocol\\Soap\\": "tests/"}},
    "extra": {"laravel": {"providers": ["Alama\\Arazzo\\Protocol\\Soap\\Laravel\\SoapServiceProvider"]}},
    "config": {"sort-packages": true, "allow-plugins": {"pestphp/pest-plugin": true, "phpstan/extension-installer": true}},
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

No DTO-relocation concern → **single PSR-4 map** (unlike F1.0). No `illuminate` require until F4.6 (the `extra.laravel.providers` entry is inert until the class exists).

- [ ] **Step 2: `phpstan.neon.dist`** — mirror `packages/document/phpstan.neon.dist` (`includes` + `scanDirectories` for `../contracts/src`, `../expression/src`, `../evaluation/src`, `../document/src`, `../engine/src`, `../sources/src`, `../protocol-http/src`).
- [ ] **Step 3: Pest bootstrap + arch smoke test**

`packages/protocol-soap/tests/Pest.php` — copy `packages/protocol-http/tests/Pest.php`. `packages/protocol-soap/tests/Architecture/ArchTest.php`:

```php
arch('protocol-soap is vendor-free and does not depend on the HTTP protocol')
    ->expect('Alama\Arazzo\Protocol\Soap')
    ->not->toUse('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner')
    ->ignoring('Alama\Arazzo\Protocol\Soap\Laravel');
```

The third clause is the DIP guard (DF7) — SOAP must not reach the folded pipeline helpers either.

- [ ] **Step 4: Root plumbing** — same shape as F1.0 Step 4: repositories entry, root `require`, autoload-dev entry, scripts `analyse-soap`/`test-soap` (+ compositions).
- [ ] **Step 5: Install + verify + commit**

```bash
composer update alama/arazzo-protocol-soap --with-dependencies --no-interaction
composer run analyse-soap && composer run test-soap
git add composer.json composer.lock packages/protocol-soap
git commit -m "build(protocol-soap): scaffold arazzo-protocol-soap (F4.0)"
```

---

## Task F4.1: `WsdlSourceNormalizer`

Read a WSDL 1.1/2.0 document (DOM, no vendor) and produce `ResolvedOperation`s for the core registry — the SOAP sibling of F1.1's `OpenApiSourceNormalizer`.

**Files created:**
- `packages/protocol-soap/src/Normalizer/WsdlSourceNormalizer.php`
- `packages/protocol-soap/tests/Normalizer/WsdlSourceNormalizerTest.php`
- `packages/protocol-soap/tests/Fixtures/{calculator.wsdl, order-service.wsdl}`

**Interfaces:**
- Implements contracts `SourceNormalizerInterface`; consumes `Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver` + the document parser (per DF4, `sources` is a legal dependency) — no protocol-http types, no runner types.

- [ ] **Step 1:** Class skeleton implementing `supports($source): bool` (sniffs `definition` root + `wsdl:` namespace / `definitions`), `normalize($source): NormalizedOpenApiOperation` — the *document* DTO `Alama\Arazzo\Document\NormalizedOpenApiOperation`, reused by value for a shared shape.
- [ ] **Step 2:** `DOMDocument::loadXML` parse; extract `service` → `port` → `binding` (`@type` → portType/interface) → `operation` (`@name`) → `bindingOperation` (`soap:operation soapAction`; `soap:body use="literal"`). Build the `ResolvedOperation`'s `targetResolver`-compatible fields: target URL = `$port['location']` (+ optional per-step path), `method` = SOAP POST, headers `Content-Type: text/xml; charset=utf-8` (+ `SOAPAction`). Derive `bodySchema`/`responseSchema` from the XSD types referenced by `message` parts via `types`/`schema` `import`, best-effort; keep `null` when unresolvable and note it in code.
- [ ] **Step 3:** Fixture WSDLs; test `supports()` true for WSDL strings/`{url: ...}` values, false for plain JSON; `normalize()` returns expected target + soapAction + portType.
- [ ] **Step 4:** Register into the registrar (F4.6); for now instantiate + unit-test directly.
- [ ] **Step 5:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): WsdlSourceNormalizer (F4.1)"`.

---

## Task F4.2: `SoapOperationExecutor` + envelope builder + fault mapping

**Files created:**
- `packages/protocol-soap/src/Execution/SoapOperationExecutor.php`
- `packages/protocol-soap/src/Execution/SoapEnvelope.php` (`@internal`)
- `packages/protocol-soap/src/Execution/SoapFaultMapper.php`
- `packages/protocol-soap/tests/Execution/{SoapOperationExecutorTest,SoapEnvelopeTest,SoapFaultMapperTest}.php`

**Interfaces:**
- Implements `OperationExecutorPluginInterface` over `OperationExecutorRegistry`; consumes contracts `ResponseTransferInterface` + `StepState`, `HttpClientInterface` (contracts), `StepProtocolExecutorInterface`'s `execute(Step, WorkflowContext, ArazzoDocument, string $executionId): StepExecutionOutcome` signature (**not** the deleted `ExecutionEvaluationInput` — DF2), and throws `Alama\Arazzo\Engine\Exceptions\ExecutionException` for configuration failures (DF3).

- [ ] **Step 1:** `SoapEnvelope` — `DOMDocument` builder: `<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">`, `<Header>` (correlation id when provided), `<Body><tns:{op} …>` with params serialized from the step's resolved parameters (bool/string/number → text nodes; nested arrays → nested DOM). `__toString(): string` returns XML. Switch to SOAP 1.2 namespace when the resolved version says 1.2.
- [ ] **Step 2:** `SoapOperationExecutor::execute()` — resolve target URL + soapAction from the `ResolvedOperation` (or per-step overrides); build the request (`POST`, `Content-Type: text/xml`, `SOAPAction` or `action` for 1.2); send via `HttpClientInterface`; on non-2xx → `SoapFaultMapper` outcome. Map SOAP faults (HTTP 200 with `faultcode`/`faultstring`) into a failed `StepExecutionOutcome` carrying `{'soap.faultcode', 'soap.faultstring'}` in the transfer `meta` bag.
- [ ] **Step 3:** Tests with a fixture PSR-18 client. Assert envelope XML structure, soapAction header, fault mapping, success path returns a `SoapResponseTransfer` with the XML preserved in `view('xml')`.
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapOperationExecutor + envelope + fault mapping (F4.2)"`.

---

## Task F4.3: `SoapResponseTransfer` + `SoapResponseTransferMapper`

**Files created:**
- `packages/protocol-soap/src/Transfer/SoapResponseTransfer.php`
- `packages/protocol-soap/src/Transfer/SoapResponseTransferMapper.php`
- `packages/protocol-soap/tests/Transfer/{SoapResponseTransferTest,SoapResponseTransferMapperTest}.php`

**Interfaces:**
- Consumes contracts `ResponseTransferInterface`; produces `SoapResponseTransfer implements ResponseTransferInterface` with documented key homes — `view('xml')` = the parsed `DOMDocument` body envelope, `meta['soap.faultcode']` / `meta['soap.faultstring']` on faults.

- [ ] **Step 1:** Create `SoapResponseTransfer` — `final readonly`, six public methods returning constructor state (mirror the generic `ResponseTransfer` from A6; no flat `json`/`xml`/`proto` props — facets stay in `views`, metadata in `meta`).
- [ ] **Step 2:** `SoapResponseTransferMapper` — 2xx → transfer with `views: ['xml' => $dom]`, fault meta keys via XPath on the body, `headers` passthrough. Non-2xx / parse failure → transfer with the raw `status` and a `meta['error']` entry.
- [ ] **Step 3:** Tests — DTO satisfies the seam; success XML → `hasView('xml')` true, no fault keys; fault XML → both meta keys set; garbage body → `meta['error']` set.
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapResponseTransfer + SoapResponseTransferMapper (F4.3)"`.

---

## Task F4.4: `XPathReplacementTargetResolver`

SOAP steps resolve request replacements of the form `xpath:{expr}` against the pending SOAP envelope/response — resolving targets inside DOM rather than JSON path.

**Files created:**
- `packages/protocol-soap/src/Resolver/XPathReplacementTargetResolver.php`
- `packages/protocol-soap/tests/Resolver/XPathReplacementTargetResolverTest.php`

**Interfaces:**
- Implements contracts `ReplacementTargetResolverInterface` (verified present at `packages/contracts/src/Interfaces/ReplacementTargetResolverInterface.php`, extends `PluginInterface`). **Not** `RequestCompiler` — that class is runner-internal post-`c2b03fb` and importing it would violate DF7.

- [ ] **Step 1:** `resolve(string $target, ResponseTransferInterface $transfer): mixed` — match the `xpath:` prefix; `DOMXPath::query` over `view('xml')` (guard with `hasView('xml')`); return node text (or a node set as an array of texts) so expressions render `{{ $request.xpath:… }}`. Non-`xpath:` targets return `null` (chain to any JSON-path resolver, lowest priority).
- [ ] **Step 2:** Tests — `xpath:/Envelope/Body/…` on a fixture `SoapResponseTransfer`; fallthrough for non-xpath targets.
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XPathReplacementTargetResolver (F4.4)"`.

---

## Task F4.5: `XsdResponseValidator`

**Files created:**
- `packages/protocol-soap/src/Validation/XsdResponseValidator.php`
- `packages/protocol-soap/tests/Validation/XsdResponseValidatorTest.php`
- `packages/protocol-soap/tests/Fixtures/order.xsd`

**Interfaces:**
- Implements contracts `ResponseValidatorInterface` (the same seam `ResponseSchemaValidator` uses); consumed by `ResponseValidatorDispatcher` via priority.

- [ ] **Step 1:** `validate(...)` — pick the schema from the document's SOAP source (`ResolvedOperation` XSD reference) or the step's explicit `schemaUrl`; `DOMDocument::schemaValidateSource($xsd)`; throw a validation failure with `libxml_get_errors()` details.
- [ ] **Step 2:** Tests — valid response passes; invalid (missing element / wrong type) throws with a message; missing schema reference passes through (validation not applicable).
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XsdResponseValidator (F4.5)"`.

---

## Task F4.6: `SoapServiceProvider` + SOAP registrar + tags + Laravel test

**Files created:**
- `packages/protocol-soap/src/Laravel/SoapServiceProvider.php`
- `packages/protocol-soap/src/Registrar/SoapRegistrar.php` (`registerInto(SourceNormalizerRegistry|OperationExecutorRegistry|ResponseValidatorDispatcher)` — same shape as `ProtocolHttpRegistrar`)
- `packages/protocol-soap/config/arazzo.php` (SOAP gets its own tag key `arazzo.plugins.soap_operation_executor`, default `'arazzo.plugins.soap-operation-executor'`)
- `packages/protocol-soap/tests/Laravel/SoapServiceProviderTest.php`
- Modify `packages/protocol-soap/composer.json` — add `illuminate/contracts` + `illuminate/support`

- [ ] **Step 1:** `SoapRegistrar` — register `WsdlSourceNormalizer`, `SoapOperationExecutor`, `XsdResponseValidator` into the core registries (constructor-injected like F1.3 Step 1); priority descending.
- [ ] **Step 2:** `SoapServiceProvider` — `mergeConfigFrom`; bind `SoapOperationExecutor` + register the tag list; in `boot()` call `SoapRegistrar::registerInto(...)` with container-resolved registries. Keep only `Illuminate\Support\ServiceProvider` + container usage at the provider edge.
- [ ] **Step 3:** `SoapServiceProviderTest` (testbench): container resolves `SoapOperationExecutor`, tagged services exist, `PreflightValidator` accepts the WSDL fixture, end-to-end execute via a fake PSR-18 client (assert `xpath:` replacement worked + the transfer exposes `view('xml')`).
- [ ] **Step 4:** Wire as an optional peer — the composed app works with EITHER protocol installed. Confirm `rg "Protocol\\\\Soap" packages/laravel/src packages/document packages/runner packages/sources` is empty.
- [ ] **Step 5:** Gates: `composer run test-soap && composer run analyse-soap && composer run test-laravel && composer run test && make verify`.
- [ ] **Step 6:** `git add -A`, commit `feat(protocol-soap): SoapServiceProvider + registrar + tags (F4.6)`.

---

## Task F4.7: Final SOAP verification gate

Same final-gate routine as F1.4.

- [ ] **Step 1:** Sweeps — `rg -n "Protocol\\\\Soap" packages/{contracts,expression,document,evaluation,runtime,events,sources,engine}/src` empty; `rg -n 'Alama\\Arazzo\\Runner' packages/protocol-soap/src` empty (DF7); `composer run format` zero-diff.
- [ ] **Step 2:** `composer run analyse && composer run test && make verify` — all green.
- [ ] **Step 3:** `git add -A && git commit -m "chore(protocol-soap): F4 verification gate green (F4.7)"`.
- [ ] **Step 4:** Report: `alama/arazzo-protocol-soap` installed via one `composer require` with ZERO core edits; WSDL→SOAP→XSD vertical slice exercised by `test-soap` + root gate; both protocol packages coexist.

---

## Task F5.0: `arazzo-protocol-rpc` package scaffold + root plumbing

Create the third protocol package for Protocol-Buffer RPC (PR #556). One package, four internal protocol variants — the alternative (four packages) is rejected for the same reason `protocol-soap` is one package: they share `.proto` parsing and differ only in wire behaviour, so splitting would duplicate the parser and produce four near-identical packages with one consumer each. The `rpcProtocol` discriminator on the step object is the internal dispatch key, not a package boundary.

**The four variants this package must cover** (from `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`):

| `rpcProtocol` | Streaming | Content types | Status |
|---|---|---|---|
| `grpc` | unary, server, client, bidi | `application/grpc` (+ `+proto`/`+json`) | integer code |
| `grpc-web` | unary, server | `application/grpc-web*` | integer code |
| `twirp` | unary | `application/protobuf` or `application/json` | string error code |
| `connect` | unary, server, client, bidi | `application/proto`, `application/json`, `application/connect+proto`, `application/connect+json` | string error code |

**Files created:**
- `packages/protocol-rpc/composer.json`, `phpstan.neon.dist`, `src/` (empty `Normalizer/`, `Protocol/`, `Registrar/`, `Laravel/`), `tests/Architecture/ArchTest.php`, `tests/TestCase.php`, `tests/fixtures/`
- Modify root `composer.json` (`test-rpc` / `analyse-rpc` scripts + path repositories), root `phpstan.neon.dist` (if it enumerates packages), `.gitattributes` (if `export-ignore` is per-package)

**Interfaces:**
- Consumes: `SourceNormalizerRegistryInterface`, `OperationExecutorRegistry`, `ResponseValidatorDispatcher`, `HttpClientInterface` (contracts), `StepProtocolExecutorInterface` (contracts) — **not** `ExecutionEvaluationInput` (deleted, DF2), **not** the folded pipeline helpers (DF7).
- Produces: nothing yet — F5.1 produces the normalizer + executors.

- [ ] **Step 1:** `composer.json` — mirror F1.0's manifest shape with `alama/arazzo-protocol-rpc`, requiring `contracts`, `document`, `expression`, `evaluation`, `engine`, `sources` + the PSR HTTP packages and `illuminate/{contracts,support}` for the provider. No cebe, no guzzle unless the RPC transport needs one.
- [ ] **Step 2:** `phpstan.neon.dist` mirroring `packages/protocol-http/phpstan.neon.dist`.
- [ ] **Step 3:** `tests/Pest.php` + `tests/Architecture/ArchTest.php`:

```php
arch('protocol-rpc does not depend on the runner or the other protocols')
    ->expect('Alama\Arazzo\Protocol\Rpc')
    ->not->toUse(['Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol\Http', 'Alama\Arazzo\Protocol\Soap'])
    ->ignoring('Alama\Arazzo\Protocol\Rpc\Laravel');
```

- [ ] **Step 4:** Root plumbing + install + verify + commit: `composer update alama/arazzo-protocol-rpc --with-dependencies --no-interaction`, `composer run analyse-rpc && composer run test-rpc`, commit `build(protocol-rpc): scaffold arazzo-protocol-rpc (F5.0)`.

- [ ] **Step 5 (F5.1):** `ProtoSourceNormalizer` + `RpcOperationExecutor` with the four-variant dispatch table, plus `RpcResponseTransfer`. Assert per-variant: content-type header, status decoding (integer vs string error code), and that an unknown `rpcProtocol` value throws `ExecutionException` naming the four supported variants.
- [ ] **Step 6 (F5.2):** `RpcRegistrar` + `RpcServiceProvider` + tags + Laravel test (testbench resolves the executor, end-to-end execute against a fake PSR-18 client per variant), then the root gate.

---

## Appendix: what this revision deleted from the first draft

For anyone diffing against the previous version:

- The `arazzo-request-pipeline` row in the package table, and every `require` / `repositories` / `scanDirectories` entry for it.
- F1.2's `ExecutionEvaluationInput` promotion (both the `git mv` and the "two exceptions" framing that counted it).
- F1.2's `RequestPipelineFactory` escape hatch — there is no pipeline package to host it.
- The "construct the exact concrete wiring … from E3" hand-waving; replaced with named seams.
- `composer run test-pipeline` / `analyse-pipeline` from every gate list.
- The claim that Phase E dissolved `ExecutionException` into `arazzo-engine`, and the claim that `arazzo-request-pipeline` held `ReusableParameterResolver` at F1 time.
- The stale F1.1 references to `packages/sources/src/Resolver/SourceNormalizerRegistry.php` from "D1" and `packages/document/src/Validator/PreflightValidator.php` — both live in `packages/sources/` today.
- `softcreatr/jsonpath` from protocol-http's require (it moved under `expression`).
- F2.0's note that `tests/Async/` "already exists on `main`" — it does not; `6e0a088` removed it.