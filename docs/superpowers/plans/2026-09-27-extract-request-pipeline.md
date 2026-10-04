# `alama/arazzo-request-pipeline` Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the shared request-compilation pipeline from `packages/runner` into a new `alama/arazzo-request-pipeline` package, without changing behaviour.

**Architecture:** Eleven classes move out of `Alama\Arazzo\Runner\Execution` into `Alama\Arazzo\RequestPipeline`, flattened out of `Execution/`. Eight runner classes that consumed them from the same namespace gain explicit `use` statements; seven files with existing imports get them rewritten. The new package depends on contracts, document, expression, evaluation and PSR-7 only — an arch guard with trailing-backslash namespace prefixes enforces the boundary and is proven to bite.

**Tech Stack:** PHP 8.4, Composer path repositories, Pest 5 + `pest-plugin-arch`, PHPStan level max with a per-package baseline, Pint.

**Spec:** `docs/superpowers/specs/2026-09-27-extract-request-pipeline-design.md` (read it first — it carries the evidence behind every decision here)

## Global Constraints

- PHP `^8.4`. Every file starts with `<?php`, `declare(strict_types=1);` and a blank line before `namespace`.
- Package name `alama/arazzo-request-pipeline`; PSR-4 `Alama\Arazzo\RequestPipeline\` → `src/`; autoload-dev `Alama\Arazzo\Tests\RequestPipeline\` → `tests/`.
- The package's only `alama/*` requirements are `alama/arazzo-contracts`, `alama/arazzo-document`, `alama/arazzo-expression`, `alama/arazzo-evaluation`. Plus `psr/http-client`, `psr/http-factory`, `psr/http-message`. No vendor OpenAPI library.
- The runner keeps its own `Alama\Arazzo\Runner\Execution` namespace. Phase F2 splits it into `Sync\` and `Async\`.
- **Never blanket-replace** `Alama\Arazzo\Runner\Execution` — every hit is classified by hand as moved or staying.
- Every moved class keeps its `final readonly` / `final` modifier, its `@internal` docblock line, and its method bodies byte-for-byte. This is a move, not a rewrite.
- `SchemaValidator`, `ResponseSchemaValidator`, `StepOutputExtractor`, `DefaultOpenApiExecutor` and `Interfaces/OpenApiExecutorInterface` **stay in the runner**.
- Do not edit anything under `docs/generated/` by hand — the pre-commit hook regenerates it.
- Do not use `git commit --no-verify`. Do not weaken a gate to make it pass.
- `make verify` must exit `0` before each commit.

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `packages/request-pipeline/composer.json` | Package manifest; declares the four `alama/*` deps + PSR-7 |
| `packages/request-pipeline/phpstan.neon.dist` | Level-max analysis; scans contracts/document/expression/evaluation |
| `packages/request-pipeline/phpstan-baseline.neon` | Generated; holds the one suppressed `IdempotencyKeyInjector` caller error |
| `packages/request-pipeline/tests/Architecture/ArchTest.php` | Boundary guard for Engine/Runner/Protocol |
| `packages/request-pipeline/src/RequestCompiler.php` | Parameters + payload → `OpenApiPayload`; canonical request/response records |
| `packages/request-pipeline/src/ParameterSerializer.php` | Serialization-style aware parameter formatting |
| `packages/request-pipeline/src/TypeCaster.php` | Scalar cast helpers, throwing on invalid input |
| `packages/request-pipeline/src/ExpressionValueResolver.php` | Single resolution path for step-level runtime values |
| `packages/request-pipeline/src/ExecutionExpressionResolver.php` | `ExpressionResolverInterface` implementation aggregating extractor + validator |
| `packages/request-pipeline/src/IdempotencyKeyInjector.php` | Deterministic idempotency key injection on mutating methods |
| `packages/request-pipeline/src/StepParameterMerger.php` | Applies workflow-level parameters onto a step |
| `packages/request-pipeline/src/ReusableParameterResolver.php` | Substitutes `{reference: ...}` reusable parameters |
| `packages/request-pipeline/src/Data/ExecutionEvaluationInput.php` | `EvaluationInputInterface` value object |
| `packages/request-pipeline/src/Data/InjectionResult.php` | Injected-request + key + header value object |
| `packages/request-pipeline/tests/{ParameterSerializer,TypeCaster,IdempotencyKeyInjector,StepParameterMerger,ReusableParameterResolver}Test.php` | Moved from `packages/runner/tests/Execution/` |
| `packages/request-pipeline/tests/{RequestCompiler,ExpressionValueResolver,ExecutionExpressionResolver,ExecutionEvaluationInput,InjectionResult}Test.php` | New direct coverage (Task 2) |

**Modified:**

| Path | Change |
| --- | --- |
| `composer.json` | Path repository, `require`, autoload-dev, `analyse-pipeline`/`test-pipeline`, entries in the `analyse` and `test` arrays |
| `packages/runner/composer.json` | Add `alama/arazzo-request-pipeline: @dev` |
| `packages/runner/phpstan-baseline.neon` | One entry's regex embeds the old callee FQCN — must be updated |
| `phpunit.xml.dist` | New testsuite + source directory |
| `packages/runner/src/Execution/{StepExecutor,DefaultOpenApiExecutor,StepOutputExtractor,AsyncExecutionGraphAssembler,ExecutionGraphFactory,StepExecutionWorker,WorkflowExecutor}.php` | Seven files gain eleven `use Alama\Arazzo\RequestPipeline\…` statements (they previously shared the runner's namespace, so no import was needed) |
| `packages/runner/src/Execution/{StepOutcomeHandler,StepOutputExtractor,SubWorkflowInvoker}.php` | Rewrite existing `Runner\Execution\Data\ExecutionEvaluationInput` import |
| `packages/runner/src/Protocol/{HttpStepExecutor,AsyncApiStepExecutor,SubWorkflowStepExecutor}.php` | Rewrite existing imports |
| `packages/runner/tests/Execution/StepExecutorTest.php` | Rewrite existing import |
| `scripts/generate-docs/Scanner.php`, `scripts/quality-gates.php` | Register the new package |
| `packages/core/tests/GeneratedDocsSnapshotTest.php` | Expected layer order gains the new layer |
| `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md` | Tick the four E3 checkboxes |

**Deleted:** `packages/core/tests/Unit/Execution/TypeCasterTest.php` — a redundant copy that imports `Alama\Arazzo\Runner\Execution\TypeCaster` from the *core* package (a backwards core→runner test dependency). `packages/runner/tests/Execution/TypeCasterTest.php`, which moves to the pipeline, is a strict superset: it asserts every case the core copy does plus string/array coverage the core copy lacks.

---

### Task 1: Move the pipeline into `alama/arazzo-request-pipeline`

The eleven classes reference each other, so the move is atomic — there is no intermediate state where the tree resolves. One commit.

**Files:**
- Create: the 15 files listed under "Created" in File Structure (minus the five new tests, which are Task 2)
- Modify: every path in the "Modified" table
- Delete: `packages/core/tests/Unit/Execution/TypeCasterTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks; this is the first task.
- Produces: namespace `Alama\Arazzo\RequestPipeline` with `RequestCompiler`, `ParameterSerializer`, `TypeCaster`, `ExpressionValueResolver`, `ExecutionExpressionResolver`, `IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver`, plus `Alama\Arazzo\RequestPipeline\Data\{ExecutionEvaluationInput,InjectionResult}`. Constructor and method signatures are unchanged from the runner versions, so Task 2 and all later phases call them identically.

- [ ] **Step 1: Create the package manifest**

Write `packages/request-pipeline/composer.json`:

```json
{
    "name": "alama/arazzo-request-pipeline",
    "description": "Request compilation pipeline for alama/arazzo-core: parameter resolution, payload routing, idempotency keys",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.0||^2.0"
    },
    "require-dev": {
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0",
        "phpstan/phpstan": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\RequestPipeline\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Tests\\RequestPipeline\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Create the PHPStan config**

Write `packages/request-pipeline/phpstan.neon.dist`:

```neon
includes:
    - ../core/phpstan/rules/phpstan-custom.neon
    - phpstan-baseline.neon

parameters:
    level: max
    paths:
        - src
    excludePaths:
        - tests
    scanDirectories:
        - ../contracts/src
        - ../document/src
        - ../expression/src
        - ../evaluation/src
    reportUnmatchedIgnoredErrors: false
```

Create an empty `packages/request-pipeline/phpstan-baseline.neon` so the `includes` resolves before it is generated:

```
parameters:
	ignoreErrors:
```

- [ ] **Step 3: Move the nine pipeline classes with `git mv`**

```bash
cd /Users/mohammedalama/Code/Me/php-arazzo/.worktrees/feat/phase-e-runner-oms
mkdir -p packages/request-pipeline/src/Data packages/request-pipeline/tests/Architecture
for c in RequestCompiler ParameterSerializer TypeCaster ExpressionValueResolver \
         ExecutionExpressionResolver IdempotencyKeyInjector StepParameterMerger \
         ReusableParameterResolver; do
  git mv "packages/runner/src/Execution/$c.php" "packages/request-pipeline/src/$c.php"
done
```

- [ ] **Step 4: Move the two value objects with `git mv`**

```bash
git mv packages/runner/src/Execution/Data/ExecutionEvaluationInput.php \
       packages/request-pipeline/src/Data/ExecutionEvaluationInput.php
git mv packages/runner/src/Execution/Data/InjectionResult.php \
       packages/request-pipeline/src/Data/InjectionResult.php
```

`packages/runner/src/Execution/Data/` still holds six other files, so the directory survives — do not delete it.

- [ ] **Step 5: Rewrite the namespace in the nine moved classes**

Each of the nine now declares `namespace Alama\Arazzo\RequestPipeline;` (was `Alama\Arazzo\Runner\Execution;`). They reference each other by bare name, so no `use` statements are needed for the intra-package references.

```bash
for c in RequestCompiler ParameterSerializer TypeCaster ExpressionValueResolver \
         ExecutionExpressionResolver IdempotencyKeyInjector StepParameterMerger \
         ReusableParameterResolver; do
  perl -0pi -e 's/^namespace Alama\\Arazzo\\Runner\\Execution;$/namespace Alama\\Arazzo\\RequestPipeline;/m' \
    "packages/request-pipeline/src/$c.php"
done
```

Verify all nine changed:

```bash
grep -l '^namespace Alama\\Arazzo\\RequestPipeline;$' packages/request-pipeline/src/*.php | wc -l
```

Expected: `9`

- [ ] **Step 6: Rewrite the namespace in the two value objects**

```bash
perl -0pi -e 's/^namespace Alama\\Arazzo\\Runner\\Execution\\Data;$/namespace Alama\\Arazzo\\RequestPipeline\\Data;/m' \
  packages/request-pipeline/src/Data/ExecutionEvaluationInput.php \
  packages/request-pipeline/src/Data/InjectionResult.php
```

- [ ] **Step 7: Fix the intra-package imports of the two value objects**

Three moved classes imported them by their old FQCN. Rewrite to the relative `Data\` form:

```bash
perl -0pi -e 's/use Alama\\Arazzo\\Runner\\Execution\\Data\\(ExecutionEvaluationInput|InjectionResult);/use Data\\$1;/' \
  packages/request-pipeline/src/ExpressionValueResolver.php \
  packages/request-pipeline/src/ExecutionExpressionResolver.php \
  packages/request-pipeline/src/IdempotencyKeyInjector.php
```

Confirm none survive:

```bash
grep -rn 'Runner\\Execution' packages/request-pipeline/src/ || echo "clean"
```

Expected: `clean`

- [ ] **Step 8: Correct the `ExecutionEvaluationInput` docblock**

Its docblock still claims runner ownership. In `packages/request-pipeline/src/Data/ExecutionEvaluationInput.php`, replace the block:

```php
/**
 * Runner-owned evaluation input.
 *
 * The runner reaches the expression package through its public face
 * ({@see EvaluationEngineInterface}), whose
 * `evaluate` requires an {@see EvaluationInputInterface}. This value object
 * is the runner's own implementation of that cross-seam contract, so the
 * runner never touches the expression package's internal `EvaluationContext`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
```

with:

```php
/**
 * Request-pipeline-owned evaluation input.
 *
 * The pipeline reaches the expression package through its public face
 * ({@see EvaluationEngineInterface}), whose
 * `evaluate` requires an {@see EvaluationInputInterface}. This value object
 * is the pipeline's own implementation of that cross-seam contract, so the
 * pipeline never touches the expression package's internal `EvaluationContext`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
```

- [ ] **Step 9: Move the five matching test files with `git mv`**

```bash
for t in ParameterSerializerTest TypeCasterTest IdempotencyKeyInjectorTest \
         StepParameterMergerTest ReusableParameterResolverTest; do
  git mv "packages/runner/tests/Execution/$t.php" "packages/request-pipeline/tests/$t.php"
done
```

Each declares `namespace Tests\Execution;` today. Rewrite all five to the package's test namespace:

```bash
for t in ParameterSerializerTest TypeCasterTest IdempotencyKeyInjectorTest \
         StepParameterMergerTest ReusableParameterResolverTest; do
  perl -0pi -e 's/^namespace Tests\\Execution;$/namespace Alama\\Arazzo\\Tests\\RequestPipeline;/m' \
    "packages/request-pipeline/tests/$t.php"
done
```

- [ ] **Step 10: Rewrite the moved tests' imports of the moved classes**

```bash
perl -0pi -e 's/use Alama\\Arazzo\\Runner\\Execution\\(RequestCompiler|ParameterSerializer|TypeCaster|ExpressionValueResolver|ExecutionExpressionResolver|IdempotencyKeyInjector|StepParameterMerger|ReusableParameterResolver);/use Alama\\Arazzo\\RequestPipeline\\$1;/g; s/use Alama\\Arazzo\\Runner\\Execution\\Data\\(ExecutionEvaluationInput|InjectionResult);/use Alama\\Arazzo\\RequestPipeline\\Data\\$1;/g' \
  packages/request-pipeline/tests/*.php
```

- [ ] **Step 11: Delete the redundant core TypeCaster test**

```bash
git rm packages/core/tests/Unit/Execution/TypeCasterTest.php
```

This removes a `core` → `runner` test dependency. The pipeline's `TypeCasterTest` covers every case the deleted file asserted.

- [ ] **Step 12: Add `use` statements to the seven same-namespace consumers**

These runner classes referenced the movers without an import because they shared `Alama\Arazzo\Runner\Execution`. Seven files need eleven `use` statements added. Insert each into the file's existing `use` block, keeping alphabetical order:

| File | Add |
| --- | --- |
| `packages/runner/src/Execution/StepExecutor.php` | `Alama\Arazzo\RequestPipeline\ExpressionValueResolver`, `Alama\Arazzo\RequestPipeline\IdempotencyKeyInjector`, `Alama\Arazzo\RequestPipeline\RequestCompiler` |
| `packages/runner/src/Execution/DefaultOpenApiExecutor.php` | `Alama\Arazzo\RequestPipeline\ParameterSerializer`, `Alama\Arazzo\RequestPipeline\TypeCaster` |
| `packages/runner/src/Execution/StepOutputExtractor.php` | `Alama\Arazzo\RequestPipeline\TypeCaster` |
| `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php` | `Alama\Arazzo\RequestPipeline\ExecutionExpressionResolver`, `Alama\Arazzo\RequestPipeline\IdempotencyKeyInjector` |
| `packages/runner/src/Execution/ExecutionGraphFactory.php` | `Alama\Arazzo\RequestPipeline\ExecutionExpressionResolver` |
| `packages/runner/src/Execution/StepExecutionWorker.php` | `Alama\Arazzo\RequestPipeline\StepParameterMerger` |
| `packages/runner/src/Execution/WorkflowExecutor.php` | `Alama\Arazzo\RequestPipeline\StepParameterMerger` |

Example, for `StepExecutor.php`, adding to its existing `use` block:

```php
use Alama\Arazzo\RequestPipeline\ExpressionValueResolver;
use Alama\Arazzo\RequestPipeline\IdempotencyKeyInjector;
use Alama\Arazzo\RequestPipeline\RequestCompiler;
```

- [ ] **Step 13: Rewrite the seven existing imports elsewhere in the runner**

```bash
grep -rlE 'Runner\\Execution\\(RequestCompiler|ParameterSerializer|TypeCaster|ExpressionValueResolver|ExecutionExpressionResolver|IdempotencyKeyInjector|StepParameterMerger|ReusableParameterResolver)\b|Runner\\Execution\\Data\\(ExecutionEvaluationInput|InjectionResult)\b' \
  --include='*.php' packages/runner/src packages/runner/tests
```

This returns exactly seven files:

```
packages/runner/src/Execution/StepOutcomeHandler.php
packages/runner/src/Execution/StepOutputExtractor.php
packages/runner/src/Execution/SubWorkflowInvoker.php
packages/runner/src/Protocol/AsyncApiStepExecutor.php
packages/runner/src/Protocol/HttpStepExecutor.php
packages/runner/src/Protocol/SubWorkflowStepExecutor.php
packages/runner/tests/Execution/StepExecutorTest.php
```

Rewrite the eleven class names across those files. Use literal string replacement, not a regex — nested backslash escaping in a shell-wrapped regex is where this step goes wrong:

```bash
cat > /tmp/rewire_pipeline.php <<'PHP'
<?php

declare(strict_types=1);

$classes = [
    'RequestCompiler', 'ParameterSerializer', 'TypeCaster',
    'ExpressionValueResolver', 'ExecutionExpressionResolver',
    'IdempotencyKeyInjector', 'StepParameterMerger', 'ReusableParameterResolver',
];

$roots = ['packages/runner/src', 'packages/runner/tests'];
$files = [];
foreach ($roots as $root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
}

$changed = 0;
foreach ($files as $file) {
    $src = $original = file_get_contents($file);

    foreach ($classes as $c) {
        $src = str_replace(
            "use Alama\\Arazzo\\Runner\\Execution\\{$c};",
            "use Alama\\Arazzo\\RequestPipeline\\{$c};",
            $src,
        );
    }

    foreach (['ExecutionEvaluationInput', 'InjectionResult'] as $d) {
        $src = str_replace(
            "use Alama\\Arazzo\\Runner\\Execution\\Data\\{$d};",
            "use Alama\\Arazzo\\RequestPipeline\\Data\\{$d};",
            $src,
        );
    }

    if ($src !== $original) {
        file_put_contents($file, $src);
        $changed++;
        echo "rewired: {$file}\n";
    }
}

echo "{$changed} file(s) changed\n";
PHP

php /tmp/rewire_pipeline.php
rm /tmp/rewire_pipeline.php
```

Expected output — exactly these seven files, and no more:

```
rewired: packages/runner/src/Execution/StepOutcomeHandler.php
rewired: packages/runner/src/Execution/StepOutputExtractor.php
rewired: packages/runner/src/Execution/SubWorkflowInvoker.php
rewired: packages/runner/src/Protocol/AsyncApiStepExecutor.php
rewired: packages/runner/src/Protocol/HttpStepExecutor.php
rewired: packages/runner/src/Protocol/SubWorkflowStepExecutor.php
rewired: packages/runner/tests/Execution/StepExecutorTest.php
```

If the count differs, a file outside the expected set was touched — stop and inspect before continuing.

Re-run the Step 13 grep. Expected: no output. Then confirm the `Data` imports landed correctly and were not double-prefixed:

```bash
grep -rn 'Alama\\Arazzo\\RequestPipeline\\Data\\' --include='*.php' packages/runner/src
```

Expected: `Data\ExecutionEvaluationInput` only, never `Data\Data\…`.

- [ ] **Step 14: Register the package in the root composer.json**

Add to `repositories` (keep the existing unsorted style — the file already mixes `packages/` and bare `packages/` entries):

```json
{"type": "path", "url": "packages/request-pipeline"}
```

Add to root `require`, after `"alama/arazzo-events": "@dev"`:

```json
"alama/arazzo-request-pipeline": "@dev"
```

Add to `autoload-dev.psr-4`, after the `Alama\\Arazzo\\Tests\\Events\\` entry:

```json
"Alama\\Arazzo\\Tests\\RequestPipeline\\": "packages/request-pipeline/tests/"
```

Add two scripts:

```json
"test-pipeline": "vendor/bin/pest packages/request-pipeline/tests",
"analyse-pipeline": "vendor/bin/phpstan analyse -c packages/request-pipeline/phpstan.neon.dist --memory-limit=1G"
```

Append `"@test-pipeline"` to the `test` array and `"@analyse-pipeline"` to the `analyse` array. Place `test-pipeline` immediately after `@test-runtime` in the `test` array, and `analyse-pipeline` immediately after `@analyse-events` in the `analyse` array.

- [ ] **Step 15: Add the package requirement to the runner**

In `packages/runner/composer.json`, add to `require`, after `"alama/arazzo-events": "@dev"`:

```json
"alama/arazzo-request-pipeline": "@dev"
```

- [ ] **Step 16: Install and regenerate the autoloader**

```bash
composer update alama/arazzo-request-pipeline --with-dependencies --no-interaction
composer dump-autoload
```

Expected: no errors. `composer.lock` changes only by adding the path package.

- [ ] **Step 17: Write the arch guard**

Create `packages/request-pipeline/tests/Architecture/ArchTest.php`:

```php
<?php

declare(strict_types=1);

// NOTE: the trailing backslashes are load-bearing. Pest's toUse() matches
// fully-qualified class names, not bare namespaces, so `not->toUse('Alama\Arazzo\Runner')`
// passes vacuously. `Alama\Arazzo\Runner\` prefix-matches properly and
// transitively covers Alama\Arazzo\Runner\Protocol\*.
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse([
        'Alama\Arazzo\Engine\\',
        'Alama\Arazzo\Runner\\',
    ]);

arch('request-pipeline is framework agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse('Illuminate');
```

The `Illuminate` guard names a real class family, so it bites.

- [ ] **Step 18: Prove the arch guard bites**

Add a forbidden import to any pipeline class, run the guard, confirm it fails, then revert.

```bash
perl -0pi -e 's/^use Alama\\Arazzo\\Contracts\\Spec\\Step;$/use Alama\\Arazzo\\Contracts\\Spec\\Step;\nuse Alama\\Arazzo\\Runner\\Execution\\StepExecutor;/m' \
  packages/request-pipeline/src/RequestCompiler.php
```

```bash
composer run test-pipeline
```

Expected: FAIL — `request-pipeline is protocol- and runner-agnostic` reports `Alama\Arazzo\RequestPipeline` uses `Alama\Arazzo\Runner\StepExecutor`.

Revert:

```bash
perl -0pi -e 's/^use Alama\\Arazzo\\Runner\\Execution\\StepExecutor;\n//m' \
  packages/request-pipeline/src/RequestCompiler.php
composer run test-pipeline
```

Expected: PASS.

If Step 18 passes while the forbidden import is present, the guard is vacuous — stop and fix the guard before continuing.

- [ ] **Step 19: Register the testsuite in phpunit.xml.dist**

Add a `<testsuite>` entry after the runner's, and add the source directory to the `<source>` block:

```xml
<testsuite name="request-pipeline">
    <directory>packages/request-pipeline/tests</directory>
</testsuite>
```

```xml
<directory suffix=".php">packages/request-pipeline/src</directory>
```

- [ ] **Step 20: Run the pipeline, runner and laravel suites**

```bash
composer run test-pipeline
composer run test-runner
composer run test-laravel
```

Expected: all pass. The runner's count drops by the five moved test files' cases and rises again as those files are now collected from the pipeline package.

- [ ] **Step 21: Fix the runner's PHPStan baseline entry**

`packages/runner/phpstan-baseline.neon` contains an entry whose regex embeds the *callee's* old FQCN, reported against `src/Execution/StepExecutor.php`:

```neon
-
    message: '#^Parameter \#1 \$request of method Alama\\Arazzo\\Runner\\Execution\\IdempotencyKeyInjector\:\:inject\(\) expects Psr\\Http\\Message\\RequestInterface, mixed given\.$#'
    identifier: argument.type
    count: 1
    path: src/Execution/StepExecutor.php
```

Left as-is it no longer matches, and `analyse-runner` goes red. Update the callee FQCN:

```bash
perl -0pi -e 's/Alama\\\\Arazzo\\\\Runner\\\\Execution\\\\IdempotencyKeyInjector/Alama\\\\Arazzo\\\\RequestPipeline\\\\IdempotencyKeyInjector/g' \
  packages/runner/phpstan-baseline.neon
```

Then generate the pipeline's own baseline and confirm the runner's no longer carries a stale entry:

```bash
vendor/bin/phpstan analyse -c packages/request-pipeline/phpstan.neon.dist --memory-limit=1G --generate-baseline
composer run analyse-runner
composer run analyse-pipeline
```

Expected: both report no errors. If `analyse-pipeline` reports the `IdempotencyKeyInjector` error from its own `src`, its generated baseline captures it — that is correct and expected.

- [ ] **Step 22: Register the package in the docs tooling**

In `scripts/generate-docs/Scanner.php`, add `packages/request-pipeline` to the scanned package list alongside the other packages, and map `Alama\Arazzo\RequestPipeline` to the `request-pipeline` package key the same way the other packages are mapped.

In `scripts/quality-gates.php`, add `request-pipeline` to the package list that the boundary and analysis gates iterate.

Read both files first and match the existing shape exactly — do not invent a new registration style.

- [ ] **Step 23: Run make verify and update the docs layer order**

```bash
make verify
```

`GeneratedDocsSnapshotTest` fails on the layer order, because the derived order now includes the new package. Read the actual order out of the failure message, then update the expected array in `packages/core/tests/GeneratedDocsSnapshotTest.php` to exactly that value — regenerate, do not guess.

Re-run:

```bash
make verify
```

Expected: exit `0`. The pre-commit hook regenerates `docs/generated/`; do not edit those files by hand.

- [ ] **Step 24: Tick the plan's E3 checkboxes**

In `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md`, change the four `- [ ]` items under Task E3's Steps 1–4 to `- [x]`. Do not touch the prose, which the spec records as containing known inaccuracies.

- [ ] **Step 25: Commit the move**

```bash
git add -A
git commit -F - <<'EOF'
refactor(request-pipeline): extract request compilation pipeline into arazzo-request-pipeline

Moves eleven classes out of Alama\Arazzo\Runner\Execution into the new
Alama\Arazzo\RequestPipeline, flattened out of Execution/:

  RequestCompiler, ParameterSerializer, TypeCaster, ExpressionValueResolver,
  ExecutionExpressionResolver, IdempotencyKeyInjector, StepParameterMerger,
  ReusableParameterResolver, Data\ExecutionEvaluationInput, Data\InjectionResult

The two Data value objects move too, though the plan's move list omits them:
three of the nine import ExecutionEvaluationInput or InjectionResult, so
leaving them behind would put Alama\Arazzo\Runner imports inside the new
package and fail its own boundary guard.

SchemaValidator stays in the runner. It operates on cebe\openapi\spec\Schema
and the plan requires this package to stay vendor-free; it is OpenAPI-specific
by the same test the plan already applies to ResponseSchemaValidator and
StepOutputExtractor, so it relocates to alama/protocol-http in F1.2.

The arch guard uses trailing-backslash namespace prefixes. Pest's toUse()
matches fully-qualified class names rather than bare namespaces, so the
plan's not->toUse(['Alama\Arazzo\Runner', ...]) form would have passed
vacuously. Verified to fail on a temporary forbidden import before passing.

Also deletes packages/core/tests/Unit/Execution/TypeCasterTest.php, a
redundant copy that made the core test suite depend on runner internals. The
pipeline's TypeCasterTest asserts every case it did, plus more.
EOF
```

---

### Task 2: Add direct tests for the five previously untested movers

The new package ships from Task 1 with five of its eleven classes untested — including `RequestCompiler`, the package's namesake. Task 2 closes that. One commit.

**Files:**
- Create: the five new test files in `packages/request-pipeline/tests/`
- Test: `packages/request-pipeline/tests/`

**Interfaces:**
- Consumes: the `Alama\Arazzo\RequestPipeline` namespace and unchanged signatures produced by Task 1. `RequestCompiler::__construct(ExpressionValueResolver $values, EvaluationEngineInterface $engine)`, `RequestCompiler::compile(Step $step, ArazzoDocument $document, WorkflowContext $context): array{payload: OpenApiPayload, resolvedInputs: array<string, mixed>}`, `RequestCompiler::requestRecord(?Psr7Request $captured, OpenApiPayload $payload): array<string, mixed>`, `RequestCompiler::decodeResponse(ResponseInterface $response): array`, `RequestCompiler::flattenHeaders(array $headers): array<string, string>`, `ExpressionValueResolver::__construct(EvaluationEngineInterface $engine)`, `ExpressionValueResolver::resolve(mixed $value, WorkflowContext $context, ?string $stepId = null): mixed`, `ExecutionExpressionResolver::__construct(EvaluationEngineInterface $engine, OutputExtractorInterface $outputExtractor, ResponseValidatorInterface $schemaValidator)`, `Data\InjectionResult::__construct(RequestInterface $request, ?string $key = null, ?string $header = null)`.
- Produces: nothing new; these tests only consume Task 1's surface.

- [ ] **Step 1: Test `RequestCompiler::compile` routing**

Create `packages/request-pipeline/tests/RequestCompilerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\RequestPipeline\ExpressionValueResolver;
use Alama\Arazzo\RequestPipeline\RequestCompiler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function requestCompilerDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function requestCompilerFor(EvaluationEngineInterface $engine): RequestCompiler
{
    return new RequestCompiler(new ExpressionValueResolver($engine), $engine);
}

function requestCompilerStep(array $parameters): Step
{
    return StepFactory::http(
        stepId: 'step-a',
        description: null,
        flow: new StepFlow(),
        io: new StepIo(parameters: $parameters),
        operationId: 'op',
    );
}

it('routes each parameter into the payload bucket its location names', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')->andReturnUsing(
        static fn (string $value): string => match ($value) {
            '{$inputs.p}' => 'path-value',
            '{$inputs.q}' => 'query-value',
            '{$inputs.h}' => 'header-value',
            '{$inputs.a}' => 'auto-value',
            default => $value,
        },
    );

    $step = requestCompilerStep([
        new Parameter('p', ParameterIn::Path, '{$inputs.p}'),
        new Parameter('q', ParameterIn::Query, '{$inputs.q}'),
        new Parameter('h', ParameterIn::Header, '{$inputs.h}'),
        new Parameter('a', ParameterIn::Body, '{$inputs.a}'),
    ]);

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['payload']->path)->toBe(['p' => 'path-value'])
        ->and($result['payload']->query)->toBe(['q' => 'query-value'])
        ->and($result['payload']->header)->toBe(['h' => 'header-value'])
        ->and($result['payload']->auto)->toBe(['a' => 'auto-value'])
        ->and($result['payload']->body)->toBeNull();
});

it('records every resolved value under its parameter name', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')->andReturnUsing(
        static fn (string $value): string => str_replace(['{$inputs.', '}'], '', $value),
    );

    $step = requestCompilerStep([
        new Parameter('first', ParameterIn::Query, '{$inputs.first}'),
        new Parameter('second', ParameterIn::Query, '{$inputs.second}'),
    ]);

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['resolvedInputs'])->toBe(['first' => 'first', 'second' => 'second']);
});

it('builds the canonical request record from the request and the payload', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges?expand=true', ['X-Trace' => 'abc'], '{"amount":100}');

    $record = RequestCompiler::requestRecord($request, new OpenApiPayload(
        path: ['id' => 'ch_1'],
        body: ['amount' => 100],
    ));

    expect($record['method'])->toBe('POST')
        ->and($record['url'])->toBe('https://api.example.com/charges?expand=true')
        ->and($record['query'])->toBe(['expand' => 'true'])
        ->and($record['path'])->toBe(['id' => 'ch_1'])
        ->and($record['headers'])->toBe(['X-Trace' => 'abc', 'Host' => 'api.example.com'])
        ->and($record['body'])->toBe(['amount' => 100]);
});

it('tolerates a null captured request in the canonical record', function (): void {
    $record = RequestCompiler::requestRecord(null, new OpenApiPayload());

    expect($record['method'])->toBeNull()
        ->and($record['url'])->toBe('')
        ->and($record['query'])->toBe([])
        ->and($record['body'])->toBe([]);
});

it('decodes a JSON response and reports its canonical fields', function (): void {
    $response = new Response(201, ['Content-Type' => 'application/json'], '{"id":"ch_1"}');

    $decoded = RequestCompiler::decodeResponse($response);

    expect($decoded['statusCode'])->toBe(201)
        ->and($decoded['contentType'])->toBe('application/json')
        ->and($decoded['body'])->toBe(['id' => 'ch_1'])
        ->and($decoded['rawBody'])->toBe('{"id":"ch_1"}')
        ->and($decoded['headers'])->toHaveKey('Content-Type');
});

it('falls back to an empty body when the response is not JSON', function (): void {
    $decoded = RequestCompiler::decodeResponse(new Response(500, [], 'upstream exploded'));

    expect($decoded['statusCode'])->toBe(500)
        ->and($decoded['body'])->toBe([])
        ->and($decoded['rawBody'])->toBe('upstream exploded');
});

it('flattens multi-value headers and skips non-string keys', function (): void {
    $flat = RequestCompiler::flattenHeaders([
        'X-Multi' => ['a', 'b'],
        'X-Single' => ['only'],
        'X-Skipped' => 'not-an-array',
    ]);

    expect($flat)->toBe(['X-Multi' => 'a, b', 'X-Single' => 'only']);
});
```

- [ ] **Step 2: Run the RequestCompiler tests**

```bash
composer run test-pipeline -- --filter=RequestCompiler
```

Expected: 7 passed.

- [ ] **Step 3: Test `ExpressionValueResolver` dispatch**

Create `packages/request-pipeline/tests/ExpressionValueResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\RequestPipeline\ExpressionValueResolver;

function valueResolverFor(EvaluationEngineInterface $engine): ExpressionValueResolver
{
    return new ExpressionValueResolver($engine);
}

function valueContext(): WorkflowContext
{
    return new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
}

it('passes non-string, non-expression values straight through', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('evaluate');
    $engine->shouldNotReceive('evaluateSelector');
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve(42, valueContext()))->toBe(42)
        ->and(valueResolverFor($engine)->resolve(null, valueContext()))->toBeNull()
        ->and(valueResolverFor($engine)->resolve(['a'], valueContext()))->toBe(['a'])
        ->and(valueResolverFor($engine)->resolve(true, valueContext()))->toBeTrue();
});

it('returns a plain string with no runtime expression untouched', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve('plain text', valueContext()))->toBe('plain text');
});

it('interpolates a string carrying the {$...} template form', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContext::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('{$inputs.name}', valueContext(), 'step-a'))->toBe('Ada');
});

it('normalises the bare $inputs.x spelling into the template form', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContext::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('$inputs.name', valueContext(), 'step-a'))->toBe('Ada');
});

it('normalises the ${inputs.x} spelling into the template form', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContext::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('${inputs.name}', valueContext(), 'step-a'))->toBe('Ada');
});

it('leaves a string containing whitespace alone even when it starts with a dollar', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve('$5 and $6', valueContext(), 'step-a'))->toBe('$5 and $6');
});

it('routes a Selector to evaluateSelector', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateSelector')
        ->once()
        ->andReturn('selected');

    $selector = new Selector('response', '$.id', ExpressionType::JsonPath);

    expect(valueResolverFor($engine)->resolve($selector, valueContext(), 'step-a'))->toBe('selected');
});

it('routes an Expression to evaluate', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluate')
        ->once()
        ->andReturn(7);

    expect(valueResolverFor($engine)->resolve(new Expression('$inputs.count'), valueContext(), 'step-a'))->toBe(7);
});

it('defaults the step id to an empty string when none is supplied', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContext::class), '')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('{$inputs.name}', valueContext()))->toBe('Ada');
});
```

- [ ] **Step 4: Run the ExpressionValueResolver tests**

```bash
composer run test-pipeline -- --filter=ExpressionValueResolver
```

Expected: 8 passed.

- [ ] **Step 5: Test `ExecutionExpressionResolver` delegation**

Create `packages/request-pipeline/tests/ExecutionExpressionResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\RequestPipeline\ExecutionExpressionResolver;

function resolverFor(
    EvaluationEngineInterface $engine,
    ?OutputExtractorInterface $extractor = null,
    ?ResponseValidatorInterface $validator = null,
): ExecutionExpressionResolver {
    return new ExecutionExpressionResolver(
        $engine,
        $extractor ?? \Mockery::mock(OutputExtractorInterface::class),
        $validator ?? \Mockery::mock(ResponseValidatorInterface::class),
    );
}

function resolverStep(): Step
{
    return StepFactory::http('step-a', null, new StepFlow(), new StepIo(), 'op');
}

function resolverDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [new Workflow('wf-1', null, null, null, [], [], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('delegates evaluate to the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluate')->once()->andReturn('result');

    $value = resolverFor($engine)->evaluate(new Expression('$inputs.x'), new WorkflowContext('def-1'), 'step-a');

    expect($value)->toBe('result');
});

it('delegates extractOutputs to the output extractor', function (): void {
    $extractor = \Mockery::mock(OutputExtractorInterface::class);
    $extractor->shouldReceive('extractOutputs')
        ->once()
        ->with(\Mockery::type(Step::class), \Mockery::type(WorkflowContext::class), \Mockery::type(ArazzoDocument::class))
        ->andReturn(['id' => 'ch_1']);

    $outputs = resolverFor(\Mockery::mock(EvaluationEngineInterface::class), $extractor)
        ->extractOutputs(resolverStep(), new WorkflowContext('def-1'), resolverDocument());

    expect($outputs)->toBe(['id' => 'ch_1']);
});

it('delegates evaluateSuccessCriteria to the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateSuccessCriteria')->once()->andReturnTrue();

    expect(resolverFor($engine)->evaluateSuccessCriteria(resolverStep(), new WorkflowContext('def-1')))->toBeTrue();
});

it('delegates evaluateCriteria to the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateCriteria')->once()->andReturnFalse();

    $result = resolverFor($engine)->evaluateCriteria(
        ['condition' => '{$inputs.ok}'],
        resolverStep(),
        new WorkflowContext('def-1'),
    );

    expect($result)->toBeFalse();
});

it('delegates validateResponseSchema to the response validator', function (): void {
    $validator = \Mockery::mock(ResponseValidatorInterface::class);
    $validator->shouldReceive('validateResponseSchema')
        ->once()
        ->with(
            \Mockery::type(Step::class),
            201,
            'application/json',
            ['id' => 'ch_1'],
            \Mockery::type(ArazzoDocument::class),
        );

    resolverFor(\Mockery::mock(EvaluationEngineInterface::class), null, $validator)
        ->validateResponseSchema(resolverStep(), 201, 'application/json', ['id' => 'ch_1'], resolverDocument());
});
```

- [ ] **Step 6: Run the ExecutionExpressionResolver tests**

```bash
composer run test-pipeline -- --filter=ExecutionExpressionResolver
```

Expected: 5 passed.

- [ ] **Step 7: Test `ExecutionEvaluationInput` and `InjectionResult`**

Create `packages/request-pipeline/tests/ExecutionEvaluationInputTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\RequestPipeline\Data\ExecutionEvaluationInput;

function evaluationInputDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('exposes the workflow context it was built with', function (): void {
    $context = new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
    $input = new ExecutionEvaluationInput($context, 'step-a');

    expect($input->getWorkflowContext())->toBe($context)
        ->and($input->workflowContext)->toBe($context);
});

it('defaults the current step id and document to null', function (): void {
    $input = new ExecutionEvaluationInput(new WorkflowContext('def-1'));

    expect($input->getCurrentStepId())->toBeNull()
        ->and($input->getDocument())->toBeNull();
});

it('carries the current step id and document when supplied', function (): void {
    $document = evaluationInputDocument();
    $input = new ExecutionEvaluationInput(new WorkflowContext('def-1'), 'step-a', $document);

    expect($input->getCurrentStepId())->toBe('step-a')
        ->and($input->getDocument())->toBe($document)
        ->and($input->currentStepId)->toBe('step-a');
});

it('satisfies the evaluation input contract', function (): void {
    expect(new ExecutionEvaluationInput(new WorkflowContext('def-1')))
        ->toBeInstanceOf(EvaluationInputInterface::class);
});
```

Create `packages/request-pipeline/tests/InjectionResultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\RequestPipeline\Data\InjectionResult;
use GuzzleHttp\Psr7\Request;

it('carries only the request when no key was injected', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges');
    $result = new InjectionResult($request);

    expect($result->request)->toBe($request)
        ->and($result->key)->toBeNull()
        ->and($result->header)->toBeNull();
});

it('carries the key and header name when a key was injected', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges');
    $result = new InjectionResult($request, 'abc123', 'Idempotency-Key');

    expect($result->request)->toBe($request)
        ->and($result->key)->toBe('abc123')
        ->and($result->header)->toBe('Idempotency-Key');
});

it('exposes the request with the idempotency header applied', function (): void {
    $result = new InjectionResult(
        (new Request('POST', 'https://api.example.com/charges'))->withHeader('Idempotency-Key', 'abc123'),
        'abc123',
        'Idempotency-Key',
    );

    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe('abc123');
});
```

- [ ] **Step 8: Run the value-object tests**

```bash
composer run test-pipeline -- --filter=ExecutionEvaluationInput
composer run test-pipeline -- --filter=InjectionResult
```

Expected: 4 passed and 3 passed.

- [ ] **Step 9: Run the full verification**

```bash
make verify
```

Expected: exit `0`. All eleven pipeline classes now have direct coverage.

- [ ] **Step 10: Commit the tests**

```bash
git add -A
git commit -F - <<'EOF'
test(request-pipeline): cover the previously untested pipeline classes

The new package shipped with five of its eleven classes untested, including
RequestCompiler, the package's namesake: the only repo-wide reference to that
class name was a regression test asserting an unrelated legacy class was
absent. A new package whose central class looks covered but is not is worse
than one that plainly is not.

Adds RequestCompilerTest, ExpressionValueResolverTest,
ExecutionExpressionResolverTest, ExecutionEvaluationInputTest and
InjectionResultTest.

Existing runner suites already covered the pipeline indirectly, so this widens
coverage rather than changing what the move had to prove.
EOF
```

---

## Self-Review

**Spec coverage**

| Spec section | Task |
| --- | --- |
| D1 — `SchemaValidator` stays | Task 1, Steps 3 and 25 (explicitly excluded from the move, rationale in the commit message) |
| D2 — the two `Data/` objects move | Task 1, Steps 4, 6, 7 |
| D3 — direct tests for the five | Task 2, Steps 1–8 |
| Package shape and dependencies | Task 1, Steps 1, 2, 14 |
| Moves (11) | Task 1, Steps 3–5 |
| Stays in runner | Task 1, Step 3 (only the eleven are moved) |
| Namespace rewrite, no blanket replace | Task 1, Steps 5–7, 10, 12, 13 |
| Tests flattened, 5 moved + 5 new + ArchTest | Task 1 Steps 9, 17; Task 2 Steps 1–8 |
| Arch guard, with bite verification | Task 1, Steps 17, 18 |
| Plumbing (composer, phpunit, docs, layer order) | Task 1, Steps 14, 15, 19, 22, 23 |
| Two commits | Task 1 Step 25, Task 2 Step 10 |
| Vacuous vendor guards deferred to F1.2 | Deliberately out of scope — recorded in the spec's "not resolved here" |

**Placeholder scan** — no TBD, no "similar to Task N", no "add error handling". Every step carries literal file contents, literal shell, or a literal expectation.

**Type consistency** — `RequestCompiler::compile`, `ExpressionValueResolver::resolve`, `ExecutionExpressionResolver::{evaluate,extractOutputs,evaluateSuccessCriteria,evaluateCriteria,validateResponseSchema}`, `InjectionResult::__construct` and `ExecutionEvaluationInput::__construct` signatures in Task 2's Interfaces block match the bodies read from the tree in Steps 5–8. `OpenApiPayload` named arguments (`path`, `body`) match its constructor. `Parameter(name, in, value)` matches. `StepIo(parameters:)` matches. `WorkflowContext(definitionId, inputs, steps, components, workflowId, executionId)` matches.

**Known risks stated in the plan** — Step 13's regex rewrite is the riskiest step and is followed by two verification greps; Step 21 is a trap that silently turns `analyse-runner` red if skipped; Step 18 can silently pass if the guard is written in the vacuous form; Steps 2, 6 and 23 each tell the executor to read the real signature or real output rather than guess.
