---
name: falsification-testing
description: Comprehensive testing skill — falsification (Popper/Hume/Socrates/Descartes), coverage insights (Pest HTML), and V2 severity/grue/agon/demon. Use any time tests are written, reviewed, generated, coverage queried, or "is this well tested?" is asked. Forces falsifiable, severe, property-aware, demon-proof tests; provides queryable coverage (87.55% lines → which lines) and 12 scripts (detect-fake, hume-audit, audit-boundaries, scaffold, delete-fix, verify, query-coverage, generate-coverage, severity-audit, property-audit, socratic-fuzz, demon-sim) all with --json for agents.
---

# Falsification-Driven Test Writing — php-arazzo edition

## The one-sentence job

Don't write tests that confirm the code does what it does. Write tests that try, and fail, to prove the code is broken. A test that couldn't possibly have failed against a buggy implementation is not a test — it's decoration.

This skill operationalizes four philosophical moves (Popper, Hume, Socrates, Descartes) into a concrete workflow for writing or reviewing tests, whether the tests are for my own code or for a feature I'm handing to an AI to test. This edition is tailored to the `alama/php-arazzo` monorepo (`alama/arazzo-core` + `alama/laravel-arazzo`).

## Package context — read this before picking an approach

### Monorepo layout and where tests live

```
packages/core/       — alama/arazzo-core, PSR-only engine (no Illuminate)
  src/{Spec,Parser,Validator,Resolver,Runner,Expression,Generator,Support,...}
  tests/{Parser,Validator,Resolver,Runner,Expression,Generator,Dto,Events,Feature,Conformance,...}
  infection.json, phpunit.xml (executionOrder=random, failOnWarning/Risky true)
  Pest.php → pest()->extend(TestCase::class)->in(__DIR__)  (Alama\Arazzo\Tests\TestCase extends PHPUnit\Framework\TestCase)

packages/laravel/    — alama/laravel-arazzo, Laravel bridge
  src/{Persistence,Queue,Lock,State,Http,...} + LaravelArazzoServiceProvider
  tests/{Feature,Queue,Lock,Persistence,State,Resolver,Http,...}
  Pest.php → uses(TestCase::class)->in(__DIR__) + uses(RefreshDatabase::class)->in('Persistence')
  TestCase extends Orchestra\Testbench\TestCase, registers LaravelArazzoServiceProvider
```

**Rule:** `core` must never `use Illuminate\...`. If your test needs `Illuminate`, the production code belongs in `laravel`. If the test needs PSR-18/PSR-16/PSR-3, it belongs in `core`.

### Toolchain you must respect

- **Runner:** Pest 4 on PHPUnit 10.3, PHP `^8.4`, `declare(strict_types=1)` in every file.
- **Style:** `laravel/pint` with preset `laravel` (`pint.json` at repo root — single_quote, ordered imports, trailing commas). Run `vendor/bin/pint` / `make format`. CI checks `pint --test`.
- **Analysis:** PHPStan `level: max` (`phpstan-baseline.neon` + larastan for laravel). Run `composer analyse` / `composer analyse-core` / `composer analyse-laravel` / `make analyse`.
- **Gates:** `composer test` = `test-core` + `test-laravel`; `make verify` = `pint --test && composer analyse && composer test` (same as `.githooks/pre-push` and CI). Any suite that doesn't pass `make verify` is not done.
- **Mutation / Hume audit:** `infection/infection ^0.35.2`, config `testFramework: pest`, `mutators: @default`, `source.directories: ["src"]`. Run `make test-mutate` or `cd packages/core && vendor/bin/pest --mutate --covered-only` (same for `laravel`). A suite with high line coverage and high mutant survival is coverage theater — flag it.
- **Pest semantics:** `phpunit.xml` sets `executionOrder=random`, `failOnWarning=true`, `failOnRisky=true`, `beStrictAboutOutputDuringTests=true`. Don't rely on test ordering; don't emit output in tests; a risky test (no assertion) fails the suite.

### Domain model to falsify against

Spec types are `readonly` immutable value objects under `Spec\` (`ArazzoDocument`, `Workflow`, `Step`, `SourceDescription`, `Expression`, `Selector`, `Action` subclasses). Parsing is `Loader (SymfonyYamlDecoder/NativeJsonDecoder) → RawDocument → Parser → ArazzoDocument` with precise `ParseContext` paths. Validation is `Validator(RuleSet::default())` (~40 rules, e.g. cycle detection `step.dependson_no_cycle`, dangling refs, unsupported feature combos). Execution is `WorkflowExecutor` (sync) vs `WorkflowEngine + ExecutionState + StepExecutor` (durable, queue-driven), with `DependencyGraph` (explicit `dependsOn` + implicit expression deps), `ExpressionEvaluator (Lexer→Parser→AST)`, `ArazzoExpressionResolver / ArazzoCriteriaEvaluator / ArazzoOutputExtractor / ArazzoSchemaValidator`, `OpenApiOperationResolver + OpenApi30Normalizer/31Normalizer + OpenApiDocumentLoader + SourceRegistry (HttpFetcher/LocalFetcher/CachedFetcher)`, `IdempotencyKeyInjector`, and `Transition (next|retry|goto|suspend|end)`. Persisted state is `ExecutionState {executionId, definitionId, workflowId, currentStepId, inputs, stepAttempts, stepResults, stepsSpent/maxSteps, workflowCallStack/maxWorkflowDepth}` via `StateStoreInterface`/`ExecutionRegistryInterface` etc. Laravel implements those contracts with `LaravelQueueDriver`, `LaravelRedisLockManager`, `RedisHotStateStore`, `Database*Registry/Ledger`.

If you can't name which layer your claim belongs to, you don't know what to falsify.

## When to actually stop and use the full workflow

Use the full 4-pass workflow (below) for: new features, bug fixes going into regression suites, anything touching `Parser/Validator/Resolver/Runner`, workflow transitions (`onSuccess`/`onFailure`, `retry`/`goto`/`end`/`suspend`), expression evaluation, source resolution, idempotency, concurrency/locking, queue dispatch, or any test suite you are asked to review.

Skip straight to a lighter pass for: trivial pure helpers with no branching, throwaway scripts, prototype code explicitly marked as such.

## The Fake Test Detector (run this first, always)

Before writing anything, or when reviewing an existing suite, scan for these red flags. Each one means the test cannot fail against realistic broken code:

1. **No meaningful assertion.** `expect($result)->not->toBeNull()` when the bug space is "returns the wrong value / wrong transition", not "returns null."
2. **Mirrors the implementation.** The test recomputes the same formula/JSONPath/query the code uses, so a shared bug in reasoning passes both. Assert against an independently-derived expected value, not the algorithm.
3. **Only the happy path exists.** One test, valid Arazzo YAML, expected output. No empty input, no boundary, no invalid state.
4. **Asserts on a mock's call count, not on behavior.** Confirms "we called the method" without confirming the method's effect was correct. For `Mockery` in core, `shouldReceive(...)->once()` without asserting the resulting `WorkflowContext`/`ExecutionState`/`Transition` is decoration.
5. **Would still pass if you deleted the fix.** The single highest-signal check: comment out the line that fixes the bug. If every test still passes, none of them were testing that bug.
6. **Vague assertion.** `expect($response->status())->toBeLessThan(500)` instead of asserting the actual expected status, shape, and fields / `Transition` type / `ExecutionState.status`.
7. **Core test imports Illuminate.** If `packages/core/tests` imports `Illuminate\*`, the test is hiding a leak — the boundary is broken, not just the assertion.

If a test I'm about to write (or one I'm reviewing) matches any of these, it doesn't count — go back to the workflow.

## The 4-Pass Workflow

### Pass 1 — Popper: turn the requirement into a falsifiable claim

Before writing a single test, write down the claim the code is supposed to make true, in the form:

> "The system will `<behavior>` when `<condition>`. The claim is false if `<observable outcome>` occurs."

If I can't fill in `<observable outcome>` with something concrete and measurable, the requirement isn't ready to test yet — go back and pin it down (ask, or state the assumption I'm proceeding under).

Bad: "handles errors gracefully."
Good (core): "When the payment provider returns 504, `StepExecutor` marks the step `success=false`, `ArazzoCriteriaEvaluator` evaluates `false`, and `WorkflowEngine::transition()` returns a `RetryAction` transition (not `End`). False if `Transition::isRetry()` is false or `stepResults[stepId].success` is true."
Good (laravel): "When two `ExecuteStepJob` workers contend for the same `executionId`, only one acquires `LaravelRedisLockManager::acquire()` and the other requeues. False if both jobs report `status=succeeded` for the same step or the ledger contains duplicate `step.succeeded` events."

Each falsifiable claim becomes one or more tests — each test is an attempted falsification, not a confirmation.

### Pass 2 — Hume: audit the boundaries, don't trust coverage

Coverage percentage tells me nothing about whether the *right* behavior was tested. For each input/output involved in the claim, find the equivalence classes and test the **boundaries between them**, not just one value from the middle of each class. In this repo, the boundaries are spec-shaped:

- **Zero / empty / null:** empty `workflows: []`, empty `steps: []`, empty `inputs: {}`, `null` field, empty collection/zero rows (empty YAML doc, 0-step workflow, workflow with inputs schema but caller passes `[]`).
- **One (smallest non-empty):** single workflow, single step, single parameter, `dependsOn: [one]`. Off-by-one bugs live here (`maxSteps`, `maxWorkflowDepth`, retry counts).
- **Maximum / ceiling:** `maxSteps` / `stepsSpent` at budget, `maxWorkflowDepth` exactly at limit, `retryLimit` vs `retry_ceiling` (default 10), page-size / int overflow, pagination edge.
- **Exactly-equal boundary:** `stepsSpent == maxSteps`, `retryCount == retryLimit`, `timeout == deadline`, commission == fare, string exactly at max length, JSON Schema `const` equality.
- **Discontinuity / spec edge:** midnight/month-end/timezone offset/currency rounding, OpenAPI 3.0 vs 3.1 normalization, `operationId` vs `operationPath` resolution, `$steps.*` vs `$inputs.*` vs `$response.body` expression roots, `x-strict-validation` global vs per-step override, YAML `{$steps.foo.outputs.id}` vs literal `{$not-an-expression}`.
- **Structural validity:** valid YAML but invalid Arazzo (cycle `stepB -> stepC -> stepD -> stepE -> stepB`, dangling `dependsOn`, missing `sourceDescriptions`, mismatched `sourceDescriptions[].type`).

If `make test-mutate` / `infection` is available (it is), that's the direct Humean audit: mutate the code slightly and check the suite actually goes red. A suite with high line coverage and a high mutation-survival rate is coverage theater — call that out explicitly.

**Conformance as Hume sample:** the OAI corpus (`packages/core/tests/Conformance/corpus/oai/1.0.0/*.arazzo.yaml`, `1.1.0/pet-asyncapi.yaml`) plus golden fixtures (`tests/Conformance/fixtures/*.json`) are the shared boundary set. A fix that passes your new test but breaks `OaiConformanceTest` / `FixtureTest` parity (`FixtureRunner` vs `QueueFixtureRunner`) hasn't corroborated — it's regressed. Run the relevant `Conformance*` / `FixtureHarness` subset.

### Pass 3 — Socrates: write the adversarial test, not just the scripted one

For every feature, generate at least 3 questions from an adversarial persona — someone actively trying to break this, not use it correctly:

- What if the user does steps out of order, or repeats a step (double-submit, double-click, `dependsOn` ignored)?
- What if two requests for the same resource arrive at the same time (race condition — two `ExecuteStepJob`s, Redis lock contention, `Cache::lock()` expiry vs `state_ttl`)?
- What if the caller passes data that's valid-shaped but semantically hostile (someone else's `workflowId`, negative `maxSteps`, unicode in `stepId`, JSONPath injection `{$steps.foo.outputs['..']}`, XSS in an OpenAPI `url`, a 10 MB YAML file)?
- What if the network/dependency fails mid-operation, not before it (PSR-18 `HttpClient` timeout mid-stream, `SourceRegistry` fetches 404/timeout, `HttpFetcher` returns malformed OpenAPI, Redis unavailable during `RedisHotStateStore::save()`)?
- **Arazzo-specific:** What if `onSuccess: goto` jumps to a non-existent `stepId`? What if `retry` exhausts and then `onFailure: end` vs `goto`? What if a `successCriteria` expression throws or references `$steps.nonExistent.outputs.x`? What if a nested sub-workflow exceeds `maxWorkflowDepth` or a `receive` step is never correlated via `POST api/arazzo/webhooks/{correlationId}`?

Each question that survives becomes a test. Don't just script the expected flow — actively try to find the counterexample. If none is found after genuine effort, that's corroboration, not proof — say so rather than implying certainty.

### Pass 4 — Descartes: doubt every assumption the code makes

List every assumption the feature silently depends on:
- **Inputs:** type, range, presence, format, encoding (`inputs` JSON Schema, `Format::fromExtension()` yaml vs json, UTF-8, empty string vs missing key).
- **Dependencies:** availability, latency, response shape, error format (`SourceResolver`, `HttpFetcher`/`LocalFetcher`, `OpenApiDocumentLoader`, `cebe/php-openapi` shape, `softcreatr/jsonpath` evaluation errors, PSR-18 exceptions).
- **Environment:** timezone, locale, clock, concurrency (`state_ttl`, `retry_ceiling`, cache TTL, queue driver semantics, `executionOrder=random` hiding order dependence).
- **Identity/auth:** who's calling, what they're allowed to touch (which `executionId`/`definitionId` can a worker resume, `pending_correlations` ownership, builder endpoint auth on `api/arazzo`).
- **Parsing / types:** `Spec\Expression` vs literal string (`/^\{\$.+\}$/` heuristic), `Reusable` references resolved against `Components`, `readonly` immutability via `with*` not mutation.

For each assumption, write one test that violates it and states the expected behavior (`LoaderException`, `ValidationResult` with `code=step.dependson_no_cycle`, `SchemaValidationException`, `UnsupportedSourceVersionException`, `Transition::isEnd()` with failure, or a ledger entry). An assumption with no test is an untested claim about the world, not a fact. If I don't know what *should* happen when an assumption is violated, that's a design gap to flag, not something to silently skip.

## Writing the actual test (Pest house style for this repo)

Structure every test as **Arrange → Act → Assert**, `declare(strict_types=1)`, `single_quote`, ordered imports, and make the assertion specific and clear (Cartesian: "clear and distinct," not vague). Name each test after the falsifiable claim it's attempting to break, not after the method it calls.

### Core engine example (Mockery + WorkflowContext, no Illuminate)

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Spec\Step;

// Arrange — independently-derived expectation, not a mirror of the implementation
it('returns a Retry transition and does not advance context on provider timeout', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('validateResponseSchema')->never();
    $resolver->shouldReceive('extractOutputs')->once()->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->once()->andReturn(false);

    $executor = new StepExecutor(
        openApiExecutor: $openApiExecutor, // PSR-18 fake, not Http::fake
        expressionResolver: $resolver,
        operationResolver: createMockOperationResolver(),
        strictValidationDefault: false,
    );
    $step = new Step('charge-step', null, 'capturePayment', null, null, [], null, [], [], [], [], [], null, null, null, null);
    $context = new WorkflowContext('wf-1', ['amount' => 100], [], [], 'exec-1');

    // Act
    [$nextContext, $success] = $executor->execute($step, $context, createTestDocument());

    // Assert — specific, falsifiable, would fail if the fix were reverted
    expect($success)->toBeFalse();
    expect($nextContext->getSteps())->not->toHaveKey('charge-step');
});
```

Notes:
- Factory helpers (`createTestDocument()`, `createMockOperationResolver()`) should construct minimal `ArazzoDocument`/`ResolvedOperation` (`Info`, `SourceDescription`, `Components`, `NormalizedOpenApiOperation`) — don't reuse production YAML when a unit claim is cheaper.
- Prefer `expect($transition->isRetry())->toBeTrue()` / `expect($result->status)->toBe('failed')` over `toBeLessThan(500)` / `not->toBeNull()`.

### Laravel bridge example (Orchestra Testbench + fakes, in `packages/laravel/tests/...`)

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Alama\Arazzo\Laravel\Tests\TestCase;

uses(TestCase::class);

it('does not allow concurrent workers to double-execute the same step', function (): void {
    // Arrange
    Queue::fake();

    // Act — two workers contending for the same executionId
    $first = $this->acquireLock('exec-1');
    $second = $this->acquireLock('exec-1');

    // Assert — only one succeeds, the other requeues / returns false
    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
    Queue::assertNothingPushed(); // or assertPushed(ExecuteStepJob::class) depending on contract
});
```

Laravel rules:
- File lives under `packages/laravel/tests/{Feature,Queue,Persistence,...}` so Pest applies `RefreshDatabase` where needed (see `tests/Pest.php`).
- Don't mock what Testbench fakes better (`Queue::fake()`, `Http::fake()`, `Cache::fake()` when testing lock contracts via an interface, `Event::fake()` for ledger assertions).
- Assertions should check persisted side-effects (`DatabaseExecutionRegistry`, `DatabaseEventLedger`, `RedisHotStateStore`) not just mock call counts.

### Parser / Validator example (golden-file style)

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\Validator;

it('rejects a workflow with a cyclic dependsOn and reports the cycle path', function (): void {
    $raw = new RawDocument(
        (new SymfonyYamlDecoder())->decode(file_get_contents(__DIR__ . '/../fixtures/edge-cases/complex-cyclic-dependency.arazzo.yaml')),
        'memory://test',
        Format::Yaml,
    );
    $document = (new Parser())->parse($raw);

    $result = (new Validator(RuleSet::default()))->validate($document);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContainCondition(fn ($e) => $e->code === 'step.dependson_no_cycle' && str_contains($e->message, 'stepB -> stepC'));
});
```

## Scripts — automate the checks (V1 + coverage + V2)

All scripts live under `.agents/skills/falsification-testing/scripts/` and are safe to run locally. They mirror CI gates (`make verify`) so local parity is exact. Every script is dual-mode: human table + `--json` stable keys for agents (exit 0 pass /1 fail /2 usage). Use `make -s` for pure JSON.

### V1 — Popper/Hume/Socrates/Descartes

| Script | What it does | When to use | Command |
|--------|--------------|-------------|---------|
| `detect-fake-tests.php` | Static Fake Test Detector (FAKE-1..7, STYLE, BOUNDARY, NAMING). Scans Pest files for `not->toBeNull`, `toBeTruthy`, vague `toBeLessThan(500)`, mock-only, single-test files, `Illuminate` leak in `core`. | Before adding coverage; in review | `php .agents/skills/falsification-testing/scripts/detect-fake-tests.php --all` <br> `php .agents/skills/falsification-testing/scripts/detect-fake-tests.php packages/core/tests/Runner/StepExecutorTest.php --json` |
| `audit-boundaries.php` | Hume boundary checklist generator. For a src file or keyword, emits zero/one/max/equal/discontinuity classes tailored to php-arazzo (empty workflow, `maxSteps`/`maxWorkflowDepth`, `retryLimit` vs `retry_ceiling`, 3.0 vs 3.1, YAML vs JSON) and heuristically checks if tests mention them. | Pass 2 | `php .agents/skills/falsification-testing/scripts/audit-boundaries.php packages/core/src/Runner/Execution/StepExecutor.php` <br> `php .agents/skills/falsification-testing/scripts/audit-boundaries.php WorkflowEngine --json` |
| `hume-audit.sh` | Wraps `pest --mutate --covered-only` (Infection) and reports MSI / survival theater. Fails if MSI < threshold. | Pass 2 (mutation is the Hume audit) | `bash .agents/skills/falsification-testing/scripts/hume-audit.sh --all --threshold 80` <br> `bash .agents/skills/falsification-testing/scripts/hume-audit.sh --core --dry-run` |
| `scaffold-falsification-test.php` | Generates a Pest file with Popper/Hume/Socrates/Descartes placeholders, package-correct imports (`Mockery` for `core`, Testbench + `Queue::fake()` for `laravel`, `declare(strict_types=1)`, pint-friendly). | Pass 1 scaffold | `php .agents/skills/falsification-testing/scripts/scaffold-falsification-test.php core StepExecutorRetry "returns Retry when successCriteria false"` <br> `php .agents/skills/falsification-testing/scripts/scaffold-falsification-test.php laravel ConcurrentLock "only one worker acquires Redis lock" --dry-run` |
| `delete-fix-check.sh` | Delete-the-fix check (FAKE-5). Stashes the fix, runs Pest filtered to the claim, verifies suite goes RED without fix and GREEN with fix. | Pass 1 highest-signal | `bash .agents/skills/falsification-testing/scripts/delete-fix-check.sh --filter "marks pending_retry"` <br> `bash .agents/skills/falsification-testing/scripts/delete-fix-check.sh --filter "step.dependson_no_cycle" --path packages/core` |
| `verify-falsification.sh` | Orchestrator: `detect-fake` → `pint --test` → `phpstan max` → `pest` (random) → optional `hume-audit` → conformance. Same as `make verify` plus falsification. | Final gate before PR | `bash .agents/skills/falsification-testing/scripts/verify-falsification.sh` <br> `bash .agents/skills/falsification-testing/scripts/verify-falsification.sh --quick` (skip mutate) <br> `bash .agents/skills/falsification-testing/scripts/verify-falsification.sh --with-mutate --threshold 80` |
| `test-scripts.sh` | Self-test for all scripts (33 checks). Runs `bash -n`/`php -l` + fixture `fake vs real` + `audit` + `scaffold pint` + `hume dry`. | CI / after editing scripts | `bash .agents/skills/falsification-testing/scripts/test-scripts.sh` |

### Coverage — Pest HTML (from coverage-insights)

Reports live at `packages/{core,laravel}/coverage-report/index.html` (total 87.55% core 95.89% laravel) + `dashboard.html` + `.../File.php.html`. Generate via `phpdbg` (no pcov/xdebug): `make coverage`.

| Script | What it does | Command |
|--------|--------------|---------|
| `query-coverage.php` | Query coverage: `--overview`, `--file Runner/Execution/StepExecutor.php`, `--hotspots --limit 10`, `--uncovered --file WorkflowEngine.php`, `--dashboard`. Parses `index.html` total `2896/3308` + `dashboard.html` insufficient (`0% InputPart … 32% WorkflowEngine`) + per-file `69.57% 92 executable 28 uncovered [47,52…]`. | `php .agents/skills/falsification-testing/scripts/query-coverage.php --overview` <br> `php .agents/skills/falsification-testing/scripts/query-coverage.php --file Runner/Execution/StepExecutor.php --json | jq .file.uncovered` |
| `generate-coverage.sh` | Regenerate reports via `phpdbg -qrr pest --coverage --coverage-html --coverage-clover`. Supports `--core/--laravel/--all --json --open`. | `bash .agents/skills/falsification-testing/scripts/generate-coverage.sh --all` <br> `bash .agents/skills/falsification-testing/scripts/generate-coverage.sh --core --json` |

### V2 — Severe / Grue / Agon / Demon (from v2)

Adds Lakatos/Mayo severity, Goodman/Quine grue+property, Hegelian red-team fuzz, Cartesian demon simulation. Run *after* V1 green.

| Script | V2 move | What it does | Command |
|--------|---------|--------------|---------|
| `severity-audit.php` | V2-Popper → Lakatos/Mayo | Score `0-1` severity via `fake + MSI + delete-fix hint`. `severity = 0.5*fake + 0.5*MSI`. `<0.7 LOW` → decoration. | `php .agents/skills/falsification-testing/scripts/severity-audit.php --filter WorkflowEngine --json` → `{"severity":0.45,"label":"LOW"}` |
| `property-audit.php` | V2-Hume → Goodman/Quine | Lists 10 properties (`tests/Property/InvariantsTest.php` `round-trips JSON pointers`, `DAG acyclic`) vs grue gaps (`ExecutionState immutability`, `maxSteps at budget`). Checks `coverage-report` per-file `90% uncovered 1`. | `php .agents/skills/falsification-testing/scripts/property-audit.php --json` → `{"grue_gaps":7}` |
| `socratic-fuzz.php` | V2-Socrates → Hegelian agon | Mutate `LoginAndRetrievePets.arazzo.yaml` via 10 mutators (`duplicate_stepId`, `cycle_dependsOn`, `goto_missing`) → `50` iterations, run `Parser+Validator` → `killed/survived`. | `php .agents/skills/falsification-testing/scripts/socratic-fuzz.php --iterations 50 --json` → `{"killed":50,"kill_rate":1}` |
| `demon-sim.php` | V2-Descartes → evil demon | Deterministic simulation: `pest --random-order-seed` ×5, detect `order_dependence`/`flaky`, list `time-sensitive` files (`Carbon::now`). | `bash .agents/skills/falsification-testing/scripts/demon-sim.php --seeds 5 --json` → `{"flaky_runs":0}` |

**Recommended flow for a new feature / bug fix (comprehensive):**

```bash
# 1. Scaffold with a falsifiable claim
php .agents/skills/falsification-testing/scripts/scaffold-falsification-test.php core MyFeature "system will <behavior> when <condition>; false if <observable>"

# 2. Boundaries + coverage hotspots
php .agents/skills/falsification-testing/scripts/audit-boundaries.php packages/core/src/Runner/Execution/MyFeature.php
make coverage-hotspots && make coverage-query ARGS="--file Runner/Execution/MyFeature.php"

# 3. Fake + severity
php .agents/skills/falsification-testing/scripts/detect-fake-tests.php --all
php .agents/skills/falsification-testing/scripts/severity-audit.php --filter "my feature claim" --json | jq

# 4. Property grue
php .agents/skills/falsification-testing/scripts/property-audit.php --json | jq .uncovered_properties

# 5. Agon + demon
php .agents/skills/falsification-testing/scripts/socratic-fuzz.php --iterations 50 --json | jq
bash .agents/skills/falsification-testing/scripts/demon-sim.php --seeds 5 --json | jq

# 6. Full gates (V1 + coverage + V2)
bash .agents/skills/falsification-testing/scripts/verify-falsification.sh --with-mutate
make verify-falsification-v2
```

All scripts exit 0 on pass, 1 on violations, 2 on usage/infra error — so they compose in CI or `&&` chains. `make -s` suppresses `php ...` echo for pure `--json`.


## Communicating results (Popperian humility)

Never report a passing suite as "this proves it works." Report it as: "these N falsification attempts, covering [boundaries/adversarial cases/assumption violations], did not succeed — confidence is higher, not absolute." If asked "is this well tested," answer in terms of which of the 4 passes were actually done, not just pass/fail counts or coverage %.

In this repo specifically, "well tested" means: `make verify` green (`pint --test` + `phpstan max` + `pest` random-order) and — where relevant — `make test-mutate` shows mutants killed, plus `ConformanceTest` / `FixtureTest` (including `QueueFixtureRunner` parity) still green. Say which of those you actually ran.

## Quick checklist to run before calling a test suite done

- [ ] Every acceptance criterion rewritten as a falsifiable claim
- [ ] Boundaries tested for every input class (Pass 2) — including core edges: empty/null, single-step, `maxSteps`/`maxWorkflowDepth` at ceiling, `retryLimit` vs `retry_ceiling`, `equal-boundary`, `x-strict-validation` / `x-idempotency-key` overrides, YAML vs JSON
- [ ] At least 3 adversarial scenarios attempted (Pass 3) — including Arazzo transitions: `goto` to missing `stepId`, `retry` exhaustion, `suspend`/`receive` without correlation, out-of-order `dependsOn`
- [ ] Every listed assumption has a violation test or a flagged design gap (Pass 4) — inputs, `SourceResolver`/`HttpFetcher` failures, PSR-18 exceptions, OpenAPI 3.0/3.1 differences, `Expression` vs literal
- [ ] Ran the "delete the fix, does anything go red" check on the critical-path tests
- [ ] No test matches a Fake Test Detector red flag
- [ ] `packages/core` tests do not import `Illuminate\*`; `laravel` tests use Testbench where appropriate
- [ ] `vendor/bin/pint --test` clean, `composer analyse` (PHPStan max) clean, `composer test` (Pest random-order) green — ideally `make verify`; mutation `make test-mutate` / `infection` checked and surviving mutants triaged
- [ ] If touching execution/transition logic, `ConformanceTest` / `FixtureTest` (sync + `QueueFixtureRunner` parity) and relevant `Feature/EdgeCaseFixtures` green
- [ ] Results reported as corroboration, not proof
