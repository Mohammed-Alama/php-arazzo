# Phase C — Evaluator plugins + vendor isolation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `arazzo-expression` into reference-model + evaluation packages, isolate `softcreatr/jsonpath` behind a new evaluator plugin, and introduce priority-ordered evaluator registries so the `CriteriaEvaluator` hard-coded `match` becomes plugin-extensible.

**Architecture:** The current `arazzo-expression` package (namespace `Alama\Arazzo\Expression`) is split per D11: `arazzo-expression` keeps the zero-vendor lexer/parser/AST/reference-model and exposes a new `ExpressionInterface` parse/inspect seam; a new `arazzo-evaluation` package owns the engine facade, evaluator internals, interpolation, payload replacement, and all evaluation DTOs — requiring `arazzo-expression` and delegating parse/inspect/symbols to it. `Alama\Arazzo\Expression\` PSR-4 prefix is mapped in both packages; every FQCN stays identical (D11 BC-safe). A second new package, `alama/arazzo-evaluator-jsonpath`, isolates `JsonPathEvaluator` + `softcreatr/jsonpath` behind the contracts plugin interfaces (`ExpressionEvaluatorPluginInterface`, `CriterionEvaluatorPluginInterface`). Two new registries — `ExpressionEvaluatorRegistry` and `CriterionEvaluatorRegistry` — provide priority-ordered, first-match plugin resolution. `CriteriaEvaluator` is refactored: `simple`/`regex`/`xpath` stay in-core (no vendor); `jsonpath` and future types delegate through the `CriterionEvaluatorRegistry`.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), Pest Arch (`pestphp/pest-plugin-arch`), PHPStan ^2.0 + `phpstan-deprecation-rules`, Laravel Pint, `psr/event-dispatcher` ^1.0, `psr/log` ^3.0, `softcreatr/jsonpath` ^0.10.0 (evaluator-jsonpath only).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- `Alama\Arazzo\Expression\` PSR-4 prefix mapped in both `arazzo-expression` and `arazzo-evaluation`; no FQCN changes anywhere (D11).
- `arazzo-expression` stays zero-vendor: `require` = `php: ^8.4` + `alama/arazzo-contracts: @dev` + `psr/event-dispatcher: ^1.0` + `psr/log: ^3.0` only. No `softcreatr/jsonpath`.
- `arazzo-evaluation` requires `arazzo-expression` + `arazzo-contracts`; drops `softcreatr/jsonpath`.
- `alama/arazzo-evaluator-jsonpath` owns `softcreatr/jsonpath: ^0.10.0`; requires `arazzo-expression` + `arazzo-contracts`.
- No breaking changes to existing public faces: `ExpressionEngineInterface`, `ExpressionEngine`, `ExpressionEvaluatorInterface`, `ExpressionResolverInterface`, `EvaluationInputInterface`, `CriteriaEvaluatorInterface`, `XpathEvaluator`, `SelectorEvaluationException` FQCNs all stay identical (D11 non-goal boundary).
- Every new interface/value-type file: `declare(strict_types=1)`, repo convention for `final readonly class` / `enum`.
- No code comments unless explaining a deprecation or a BC seam.
- Every task ends with the relevant composer script green from the repo root.
- All test commands run from the **repo root** (`vendor/bin/pest ...`).

---

### Task C0: Scaffold `arazzo-evaluation` package + move evaluation classes

The core of the D11 split. Create `packages/evaluation/` with its own `composer.json`, then move every evaluation-side class from `packages/expression/src/Evaluation/`, `packages/expression/src/Interfaces/`, `packages/expression/src/Data/EvaluationInput.php`, `packages/expression/src/ExpressionEngine.php`, `packages/expression/src/ExpressionEngineInterface.php`, `packages/expression/src/ExpressionEvaluator.php`, `packages/expression/src/SelectorEvaluator.php`, `packages/expression/src/StringInterpolator.php`, `packages/expression/src/JsonPointer.php`, `packages/expression/src/JsonPathEvaluator.php`, and `packages/expression/src/Xpath/` into the new package — preserving exact FQCNs and file-relative namespaces.

**Files:**
- Create: `packages/expression/src/Interfaces/ExpressionInterface.php` (new parse/inspect seam — spec D11)
- Create: `packages/evaluation/composer.json`
- Create: `packages/evaluation/phpstan.neon.dist`
- Create: `packages/evaluation/phpstan-baseline.neon` (empty baseline file)
- Move (FQCN unchanged): all 23 evaluation-side classes listed below
- Modify: `packages/expression/composer.json` (remove `softcreatr/jsonpath` from `require`)
- Modify: `composer.json` (root — add repositories + require for `alama/arazzo-evaluation`)

**Interfaces:**
- Consumes: `ExpressionEngineInterface` (current, in `packages/expression/src/`), `ExpressionEngine` (current), all `Evaluation\*` classes, `ExpressionEvaluator`, `SelectorEvaluator`, `StringInterpolator`, `JsonPointer`, `JsonPathEvaluator`, `Xpath/DomXpathEvaluator`, `Xpath/XpathEvaluator`, `EvaluationInput`, `EvaluationContext`.
- Produces: `arazzo-evaluation` package with identical FQCNs for all moved classes; `packages/expression/` retains only parse-side classes.

**Classes that move from `packages/expression/src/` → `packages/evaluation/src/` (FQCN unchanged):**

| File (relative to `src/`) | FQCN |
|---|---|
| `ExpressionEngine.php` | `Alama\Arazzo\Expression\ExpressionEngine` |
| `ExpressionEngineInterface.php` | `Alama\Arazzo\Expression\ExpressionEngineInterface` |
| `ExpressionEvaluator.php` | `Alama\Arazzo\Expression\ExpressionEvaluator` |
| `SelectorEvaluator.php` | `Alama\Arazzo\Expression\SelectorEvaluator` |
| `StringInterpolator.php` | `Alama\Arazzo\Expression\StringInterpolator` |
| `JsonPointer.php` | `Alama\Arazzo\Expression\JsonPointer` |
| `JsonPathEvaluator.php` | `Alama\Arazzo\Expression\JsonPathEvaluator` |
| `Interfaces/EvaluationInputInterface.php` | `Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface` |
| `Interfaces/ExpressionEvaluatorInterface.php` | `Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface` |
| `Interfaces/ExpressionResolverInterface.php` | `Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface` |
| `Data/EvaluationInput.php` | `Alama\Arazzo\Expression\Data\EvaluationInput` |
| `Xpath/XpathEvaluator.php` | `Alama\Arazzo\Expression\Xpath\XpathEvaluator` |
| `Xpath/DomXpathEvaluator.php` | `Alama\Arazzo\Expression\Xpath\DomXpathEvaluator` |
| `Exceptions/SelectorEvaluationException.php` | `Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException` |
| `Evaluation/CriteriaEvaluator.php` | `Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator` |
| `Evaluation/ExpressionResolver.php` | `Alama\Arazzo\Expression\Evaluation\ExpressionResolver` |
| `Evaluation/PayloadReplacer.php` | `Alama\Arazzo\Expression\Evaluation\PayloadReplacer` |
| `Evaluation/InterpolationResolver.php` | `Alama\Arazzo\Expression\Evaluation\InterpolationResolver` |
| `Evaluation/Data/EvaluationContext.php` | `Alama\Arazzo\Expression\Evaluation\Data\EvaluationContext` |
| `Evaluation/Interfaces/CriteriaEvaluatorInterface.php` | `Alama\Arazzo\Expression\Evaluation\Interfaces\CriteriaEvaluatorInterface` |
| `Evaluation/Interfaces/ConditionNode.php` | `Alama\Arazzo\Expression\Evaluation\Interfaces\ConditionNode` |
| `Evaluation/Condition/*` (6 files) | `Alama\Arazzo\Expression\Evaluation\Condition\*` |
| `Evaluation/Enum/*` (3 files) | `Alama\Arazzo\Expression\Evaluation\Enum\*` |

**Classes that STAY in `packages/expression/src/` (parse side):**

| File (relative to `src/`) | FQCN |
|---|---|
| `Lexer.php` | `Alama\Arazzo\Expression\Lexer` |
| `Parser.php` | `Alama\Arazzo\Expression\Parser` |
| `SymbolTable.php` | `Alama\Arazzo\Expression\SymbolTable` |
| `Ast/*` (15 files) | `Alama\Arazzo\Expression\Ast\*` |
| `Data/Token.php` | `Alama\Arazzo\Expression\Data\Token` |
| `Data/StepSymbols.php` | `Alama\Arazzo\Expression\Data\StepSymbols` |
| `Data/WorkflowSymbols.php` | `Alama\Arazzo\Expression\Data\WorkflowSymbols` |
| `Enum/TokenKind.php` | `Alama\Arazzo\Expression\Enum\TokenKind` |
| `Enum/ReferenceKind.php` | `Alama\Arazzo\Expression\Enum\ReferenceKind` |
| `Exceptions/ExpressionSyntaxException.php` | `Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException` |

**Note:** `ExpressionReference` (`Data/ExpressionReference.php`) belongs on the parse/inspect side because it is the cross-seam value type produced by `expressionReferences()`. It stays in `packages/expression/src/Data/ExpressionReference.php` — do NOT move it.

- [ ] **Step 1: Write the failing test — evaluation package autoloading**

Create `packages/evaluation/tests/PackageScaffoldTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEngineInterface;

it('autoloads the evaluation package expression engine')
    ->expect(new ExpressionEngine())->toBeInstanceOf(ExpressionEngineInterface::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "PackageScaffold"` (repo root)

Expected: FAIL — `Class Alama\Arazzo\Expression\ExpressionEngine not found` (the class still lives in `packages/expression` at this point, but the evaluation package doesn't exist yet).

- [ ] **Step 3: Create `ExpressionInterface` parse/inspect seam**

The spec D11 defines `ExpressionInterface` as the parse-side public face: `parseExpression`, `expressionReferences`, `buildSymbolTable`. This lets downstream packages (`document`) depend on `arazzo-expression` for static analysis without pulling in the evaluation engine.

Create `packages/expression/src/Interfaces/ExpressionInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * Parse/inspect seam for the expression package.
 *
 * Downstream packages that only need to parse expressions, inspect
 * references, or build symbol tables depend on this face — never
 * the evaluation engine. The ExpressionEngine facade implements this
 * interface in addition to its full evaluation surface.
 */
interface ExpressionInterface
{
    /**
     * Parse an expression string.
     *
     * Returns the syntax error when the expression does not parse, or null
     * when it is valid.
     */
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    /**
     * Inspect what an expression references.
     *
     * Returns null when the expression does not parse.
     */
    public function expressionReferences(string $raw): ?ExpressionReference;

    /**
     * Build the symbol table describing a document's declared workflows,
     * source descriptions and components.
     */
    public function buildSymbolTable(ArazzoDocument $document): SymbolTable;
}
```

- [ ] **Step 4: Write a failing test for ExpressionInterface**

Create `packages/expression/tests/ExpressionInterfaceTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;

it('declares the parse/inspect seam interface')
    ->expect(interface_exists(ExpressionInterface::class))->toBeTrue();

it('is implemented by the ExpressionEngine', function (): void {
    // After the split, ExpressionEngine is in arazzo-evaluation.
    // This test verifies the interface exists and is subset-compatible.
    $rc = new ReflectionClass(ExpressionInterface::class);

    expect($rc->getMethods())->toHaveCount(3)
        ->and($rc->hasMethod('parseExpression'))->toBeTrue()
        ->and($rc->hasMethod('expressionReferences'))->toBeTrue()
        ->and($rc->hasMethod('buildSymbolTable'))->toBeTrue();
});
```

Run: `vendor/bin/pest packages/expression/tests --filter "ExpressionInterfaceTest"` (repo root)

Expected: PASS (interface is created in this step).

- [ ] **Step 5: Create `packages/evaluation/composer.json`**

Create `packages/evaluation/composer.json`:

```json
{
    "name": "alama/arazzo-evaluation",
    "description": "Arazzo expression evaluation engine: criteria, selectors, interpolation, payload replacement.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "psr/event-dispatcher": "^1.0",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.14",
        "mockery/mockery": "^1.6",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\Expression\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Tests\\": "tests/"
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

Create `packages/evaluation/phpstan.neon.dist`:

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
        - ../expression/src
    reportUnmatchedIgnoredErrors: false
```

Create `packages/evaluation/phpstan-baseline.neon`:

```neon
parameters:
    ignoreErrors: []
```

Create directories: `packages/evaluation/src/`, `packages/evaluation/tests/`.

- [ ] **Step 6: Add `alama/arazzo-evaluation` to root composer.json repositories and require**

Edit `composer.json` (root) — add to the `repositories` array (after the `expression` entry):

```json
{
    "type": "path",
    "url": "packages/evaluation"
},
```

Add to the `require` object (after `"alama/arazzo-expression": "@dev"`):

```json
"alama/arazzo-evaluation": "@dev",
```

- [ ] **Step 7: Move evaluation-side source files**

Move each file, preserving the directory structure relative to `src/`. Every FQCN is identical — only the filesystem home changes.

```bash
# Create target directories
mkdir -p packages/evaluation/src/Evaluation/Condition/Ast
mkdir -p packages/evaluation/src/Evaluation/Data
mkdir -p packages/evaluation/src/Evaluation/Enum
mkdir -p packages/evaluation/src/Evaluation/Interfaces
mkdir -p packages/evaluation/src/Interfaces
mkdir -p packages/evaluation/src/Data
mkdir -p packages/evaluation/src/Xpath
mkdir -p packages/evaluation/src/Exceptions

# Move top-level evaluation classes
mv packages/expression/src/ExpressionEngine.php packages/evaluation/src/
mv packages/expression/src/ExpressionEngineInterface.php packages/evaluation/src/
mv packages/expression/src/ExpressionEvaluator.php packages/evaluation/src/
mv packages/expression/src/SelectorEvaluator.php packages/evaluation/src/
mv packages/expression/src/StringInterpolator.php packages/evaluation/src/
mv packages/expression/src/JsonPointer.php packages/evaluation/src/
mv packages/expression/src/JsonPathEvaluator.php packages/evaluation/src/

# Move Interfaces (evaluation-side only)
mv packages/expression/src/Interfaces/EvaluationInputInterface.php packages/evaluation/src/Interfaces/
mv packages/expression/src/Interfaces/ExpressionEvaluatorInterface.php packages/evaluation/src/Interfaces/
mv packages/expression/src/Interfaces/ExpressionResolverInterface.php packages/evaluation/src/Interfaces/

# Move Data/EvaluationInput
mv packages/expression/src/Data/EvaluationInput.php packages/evaluation/src/Data/

# Move Xpath evaluators (DOM-based, zero vendor, but evaluation-side)
mv packages/expression/src/Xpath/XpathEvaluator.php packages/evaluation/src/Xpath/
mv packages/expression/src/Xpath/DomXpathEvaluator.php packages/evaluation/src/Xpath/

# Move SelectorEvaluationException (evaluation-side exception)
mv packages/expression/src/Exceptions/SelectorEvaluationException.php packages/evaluation/src/Exceptions/

# Move Evaluation/* subtree
mv packages/expression/src/Evaluation/CriteriaEvaluator.php packages/evaluation/src/Evaluation/
mv packages/expression/src/Evaluation/ExpressionResolver.php packages/evaluation/src/Evaluation/
mv packages/expression/src/Evaluation/PayloadReplacer.php packages/evaluation/src/Evaluation/
mv packages/expression/src/Evaluation/InterpolationResolver.php packages/evaluation/src/Evaluation/

# Move Evaluation subdirectories
mv packages/expression/src/Evaluation/Condition/* packages/evaluation/src/Evaluation/Condition/
mv packages/expression/src/Evaluation/Data/* packages/evaluation/src/Evaluation/Data/
mv packages/expression/src/Evaluation/Enum/* packages/evaluation/src/Evaluation/Enum/
mv packages/expression/src/Evaluation/Interfaces/* packages/evaluation/src/Evaluation/Interfaces/

# Clean up empty directories left behind
rmdir packages/expression/src/Xpath 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Condition/Ast 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Condition 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Data 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Enum 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Interfaces 2>/dev/null || true
rmdir packages/expression/src/Evaluation 2>/dev/null || true
```

- [ ] **Step 8: Remove `softcreatr/jsonpath` from `packages/expression/composer.json`**

Edit `packages/expression/composer.json` — remove the `"softcreatr/jsonpath": "^0.10.0"` line from `require`. The resulting `require` section becomes:

```json
"require": {
    "php": "^8.4",
    "alama/arazzo-contracts": "@dev",
    "psr/event-dispatcher": "^1.0",
    "psr/log": "^3.0"
}
```

- [ ] **Step 9: Regenerate autoloader and run the failing test**

Run: `composer dump-autoload && vendor/bin/pest packages/evaluation/tests --filter "PackageScaffold"` (repo root)

Expected: PASS — `ExpressionEngine` is now autoloaded from `packages/evaluation/src/` via the `Alama\Arazzo\Expression\` PSR-4 prefix mapped in the evaluation package.

- [ ] **Step 10: Run expression package tests to confirm parse-side still works**

Run: `composer run test-expression` (repo root)

Expected: PASS — parse-side classes (`Lexer`, `Parser`, `SymbolTable`, AST, `Token`, `TokenKind`, `ReferenceKind`, `ExpressionSyntaxException`, `ExpressionReference`) remain in `packages/expression` with identical FQCNs. Tests that previously tested `ExpressionEngine` capabilities may need to move to the evaluation test suite (see Step 9).

- [ ] **Step 11: Move evaluation-related test files**

The following test files test evaluation-side classes and must move from `packages/expression/tests/` to `packages/evaluation/tests/`:

```bash
mv packages/expression/tests/ExpressionEngineTest.php packages/evaluation/tests/
mv packages/expression/tests/ExpressionEngineCapabilitiesTest.php packages/evaluation/tests/
```

Update the root `composer.json` autoload-dev PSR-4 for `Alama\Arazzo\Tests\` to include the new evaluation tests directory:

```json
"Alama\\Arazzo\\Tests\\": [
    "packages/core/tests",
    "packages/contracts/tests",
    "packages/document/tests",
    "packages/expression/tests",
    "packages/evaluation/tests",
    "packages/runner/tests",
    "packages/cli/tests"
]
```

- [ ] **Step 12: Run evaluation tests**

Run: `vendor/bin/pest packages/evaluation/tests` (repo root)

Expected: PASS.

- [ ] **Step 13: Add `analyse-evaluation` script to root `composer.json`**

Add to the `scripts` object in root `composer.json`:

```json
"analyse-evaluation": "vendor/bin/phpstan analyse -c packages/evaluation/phpstan.neon.dist --memory-limit=1G",
```

Also update the `analyse` script array to include `@analyse-evaluation`:

```json
"analyse": [
    "@analyse-contracts",
    "@analyse-expression",
    "@analyse-evaluation",
    "@analyse-document",
    "@analyse-runner",
    "@analyse-cli",
    "@analyse-laravel"
],
```

- [ ] **Step 14: Run static analysis on both packages**

Run: `composer run analyse-expression && composer run analyse-evaluation` (repo root)

Expected: PASS. PHPStan resolves classes via the `scanDirectories` pointing at sibling package `src/` dirs.

- [ ] **Step 15: Commit**

```bash
git add packages/evaluation/ packages/expression/src/Interfaces/ExpressionInterface.php packages/expression/tests/ExpressionInterfaceTest.php packages/expression/src/ packages/expression/composer.json packages/expression/tests/ExpressionEngineTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php composer.json
git commit -m "feat(evaluation): split arazzo-expression per D11 — evaluation package owns engine, evaluator internals, and evaluation DTOs"
```

---

### Task C1: Create `arazzo-evaluator-jsonpath` package + isolate `JsonPathEvaluator`

Move `JsonPathEvaluator` + `softcreatr/jsonpath` out of the evaluation package into a new standalone evaluator package, exposing two plugin implementations.

**Files:**
- Create: `packages/evaluator-jsonpath/composer.json`
- Create: `packages/evaluator-jsonpath/phpstan.neon.dist`
- Create: `packages/evaluator-jsonpath/phpstan-baseline.neon`
- Create: `packages/evaluator-jsonpath/src/JsonPathEvaluator.php`
- Create: `packages/evaluator-jsonpath/src/JsonPathExpressionPlugin.php`
- Create: `packages/evaluator-jsonpath/src/JsonPathCriterionPlugin.php`
- Move: `packages/evaluation/src/JsonPathEvaluator.php` → `packages/evaluator-jsonpath/src/JsonPathEvaluator.php` (FQCN changes — see below)
- Modify: `packages/evaluation/composer.json` (remove `softcreatr/jsonpath` if present — it should already be gone after C0)
- Modify: `composer.json` (root — add repositories + require)
- Test: `packages/evaluator-jsonpath/tests/JsonPathEvaluatorTest.php`
- Test: `packages/evaluator-jsonpath/tests/JsonPathExpressionPluginTest.php`
- Test: `packages/evaluator-jsonpath/tests/JsonPathCriterionPluginTest.php`

**Interfaces:**
- Consumes: `ExpressionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, per Phase A plan task A2), `CriterionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, per Phase A plan task A2), `PluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, per Phase A plan task A1).
- Produces: `JsonPathEvaluator` (namespace `Alama\Arazzo\Evaluator\JsonPath`), `JsonPathExpressionPlugin` (implements `ExpressionEvaluatorPluginInterface`), `JsonPathCriterionPlugin` (implements `CriterionEvaluatorPluginInterface`).

**FQCN note:** `JsonPathEvaluator` changes its FQCN from `Alama\Arazzo\Expression\JsonPathEvaluator` to `Alama\Arazzo\Evaluator\JsonPath\JsonPathEvaluator`. This is intentional — it is `@internal` (not a public face) and was never part of the advertised API. The only callers are `ExpressionEngine`, `SelectorEvaluator`, and `CriteriaEvaluator`, all in `arazzo-evaluation`, which will reference the new FQCN after this task.

**Plugin signatures (from Phase A plan task A2):**

```php
// ExpressionEvaluatorPluginInterface (contracts)
interface ExpressionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(Expression $expression): bool;
    public function evaluate(Expression $expression, mixed $context): mixed;
}

// CriterionEvaluatorPluginInterface (contracts)
interface CriterionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(CriterionType|SuccessCriterion $criterion): bool;
    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool;
}
```

- [ ] **Step 1: Write the failing test — JsonPathEvaluator loads**

Create `packages/evaluator-jsonpath/tests/JsonPathEvaluatorTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Evaluator\JsonPath\JsonPathEvaluator;

it('evaluates a simple JSONPath expression', function (): void {
    $data = ['users' => [['name' => 'Alice'], ['name' => 'Bob']]];

    $result = JsonPathEvaluator::evaluate('$.users[*].name', $data);

    expect($result)->toBe(['Alice', 'Bob']);
});

it('normalizes RFC 9535 filter selectors', function (): void {
    $data = ['items' => [1, 2, 3, 4, 5]];

    $result = JsonPathEvaluator::evaluate('$.items[?@ > 3]', $data);

    expect($result)->toBe([4, 5]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathEvaluatorTest"` (repo root)

Expected: FAIL — `Class Alama\Arazzo\Evaluator\JsonPath\JsonPathEvaluator not found`.

- [ ] **Step 3: Create `packages/evaluator-jsonpath/composer.json`**

Create `packages/evaluator-jsonpath/composer.json`:

```json
{
    "name": "alama/arazzo-evaluator-jsonpath",
    "description": "JSONPath expression and criterion evaluator plugin for Arazzo.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "softcreatr/jsonpath": "^0.10.0"
    },
    "require-dev": {
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.14",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\Evaluator\\JsonPath\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Evaluator\\JsonPath\\Tests\\": "tests/"
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

Create `packages/evaluator-jsonpath/phpstan.neon.dist`:

```neon
parameters:
    level: max
    paths:
        - src
    excludePaths:
        - tests
    scanDirectories:
        - ../contracts/src
        - ../expression/src
    reportUnmatchedIgnoredErrors: false
```

Create `packages/evaluator-jsonpath/phpstan-baseline.neon`:

```neon
parameters:
    ignoreErrors: []
```

- [ ] **Step 4: Add `alama/arazzo-evaluator-jsonpath` to root composer.json**

Edit root `composer.json` — add to `repositories`:

```json
{
    "type": "path",
    "url": "packages/evaluator-jsonpath"
},
```

Add to `require`:

```json
"alama/arazzo-evaluator-jsonpath": "@dev",
```

- [ ] **Step 5: Remove `JsonPathEvaluator.php` from evaluation package and create the new one**

```bash
rm packages/evaluation/src/JsonPathEvaluator.php
mkdir -p packages/evaluator-jsonpath/src
```

Create `packages/evaluator-jsonpath/src/JsonPathEvaluator.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluator\JsonPath;

use Flow\JSONPath\JSONPath;

class JsonPathEvaluator
{
    /**
     * @param  array<array-key, mixed>|object  $data
     */
    public static function evaluate(string $expression, array|object $data): mixed
    {
        $normalized = self::normalizeFilters($expression);

        $isAssocObject = is_array($data) && $data !== [] && !array_is_list($data);
        $wrapped = $isAssocObject && preg_match('/^\$?\[\?/', $normalized) === 1;

        if ($wrapped) {
            $data = [$data];
        }

        $jsonPath = new JSONPath($data);
        $result = $jsonPath->find($normalized);
        $arrayResult = $result->getData();

        if (!$wrapped && count($arrayResult) === 1) {
            return $arrayResult[0];
        }

        return $arrayResult;
    }

    public static function normalizeFilters(string $expression): string
    {
        return (string) preg_replace('/\[\?([^\]]*)\]/', '[?($1)]', $expression);
    }
}
```

- [ ] **Step 6: Run the JsonPathEvaluator test**

Run: `composer dump-autoload && vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathEvaluatorTest"` (repo root)

Expected: PASS.

- [ ] **Step 7: Write the failing test — JsonPathExpressionPlugin**

Create `packages/evaluator-jsonpath/tests/JsonPathExpressionPluginTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Evaluator\JsonPath\JsonPathExpressionPlugin;
use Alama\Arazzo\Evaluator\JsonPath\JsonPathEvaluator;

it('is a plugin')
    ->expect(new JsonPathExpressionPlugin())->toBeInstanceOf(PluginInterface::class);

it('supports jsonpath selector expressions')
    ->expect((new JsonPathExpressionPlugin())->name())->toBe('jsonpath-expression');

it('has priority below zero so built-in evaluators run first')
    ->expect((new JsonPathExpressionPlugin())->priority())->toBeLessThan(0);
```

- [ ] **Step 8: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathExpressionPluginTest"` (repo root)

Expected: FAIL.

- [ ] **Step 9: Create `JsonPathExpressionPlugin`**

Create `packages/evaluator-jsonpath/src/JsonPathExpressionPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluator\JsonPath;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;

class JsonPathExpressionPlugin implements ExpressionEvaluatorPluginInterface
{
    public function name(): string
    {
        return 'jsonpath-expression';
    }

    public function priority(): int
    {
        return -100;
    }

    public function supports(Expression $expression): bool
    {
        return self::isJsonPath($expression->raw);
    }

    public function evaluate(Expression $expression, mixed $context): mixed
    {
        if (!is_array($context) && !is_object($context)) {
            return null;
        }

        return JsonPathEvaluator::evaluate($expression->raw, $context);
    }

    private static function isJsonPath(string $raw): bool
    {
        // A colon-prefixed Arazzo reference is not a JSONPath; a bare
        // `$...` path or a parenthesized RFC 9535 filter is.
        return str_starts_with($raw, '$')
            && !str_starts_with($raw, '{$')
            && !str_starts_with($raw, '${');
    }
}
```

- [ ] **Step 10: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathExpressionPluginTest"` (repo root)

Expected: PASS.

- [ ] **Step 11: Write the failing test — JsonPathCriterionPlugin**

Create `packages/evaluator-jsonpath/tests/JsonPathCriterionPluginTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Evaluator\JsonPath\JsonPathCriterionPlugin;

it('is a plugin')
    ->expect(new JsonPathCriterionPlugin())->toBeInstanceOf(PluginInterface::class);

it('supports jsonpath criterion type')
    ->expect((new JsonPathCriterionPlugin())->supports(CriterionType::JsonPath))->toBeTrue();

it('rejects unsupported criterion types')
    ->expect((new JsonPathCriterionPlugin())->supports(CriterionType::Simple))->toBeFalse();
```

- [ ] **Step 12: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathCriterionPluginTest"` (repo root)

Expected: FAIL.

- [ ] **Step 13: Create `JsonPathCriterionPlugin`**

Create `packages/evaluator-jsonpath/src/JsonPathCriterionPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluator\JsonPath;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

class JsonPathCriterionPlugin implements CriterionEvaluatorPluginInterface
{
    public function name(): string
    {
        return 'jsonpath-criterion';
    }

    public function priority(): int
    {
        return -100;
    }

    public function supports(CriterionType|SuccessCriterion $criterion): bool
    {
        if ($criterion instanceof SuccessCriterion) {
            return ($criterion->type ?? CriterionType::Simple) === CriterionType::JsonPath;
        }

        return $criterion === CriterionType::JsonPath;
    }

    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool
    {
        $root = is_array($context) ? $context : [];

        $result = JsonPathEvaluator::evaluate($criterion->condition, $root);

        return !empty($result);
    }
}
```

- [ ] **Step 14: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests --filter "JsonPathCriterionPluginTest"` (repo root)

Expected: PASS.

- [ ] **Step 15: Run full evaluator-jsonpath test suite**

Run: `vendor/bin/pest packages/evaluator-jsonpath/tests` (repo root)

Expected: PASS.

- [ ] **Step 16: Commit**

```bash
git add packages/evaluator-jsonpath/ packages/evaluation/src/JsonPathEvaluator.php composer.json
git commit -m "feat(evaluator-jsonpath): isolate JsonPathEvaluator and softcreatr/jsonpath behind plugin interfaces"
```

---

### Task C2: Evaluator registries — `ExpressionEvaluatorRegistry` + `CriterionEvaluatorRegistry`

Priority-ordered, first-match registries that `ExpressionEngine` assembles and delegates to.

**Files:**
- Create: `packages/evaluation/src/Evaluation/ExpressionEvaluatorRegistry.php`
- Create: `packages/evaluation/src/Evaluation/CriterionEvaluatorRegistry.php`
- Test: `packages/evaluation/tests/Evaluation/ExpressionEvaluatorRegistryTest.php`
- Test: `packages/evaluation/tests/Evaluation/CriterionEvaluatorRegistryTest.php`

**Interfaces:**
- Consumes: `ExpressionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A), `CriterionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A), `Expression` (`Alama\Arazzo\Contracts\Spec`), `CriterionType`, `SuccessCriterion`, `Step`, `WorkflowContextInterface`.
- Produces: `ExpressionEvaluatorRegistry::register(ExpressionEvaluatorPluginInterface): void`, `::get(Expression): ?ExpressionEvaluatorPluginInterface`; `CriterionEvaluatorRegistry::register(CriterionEvaluatorPluginInterface): void`, `::get(CriterionType|SuccessCriterion): ?CriterionEvaluatorPluginInterface`.

- [ ] **Step 1: Write the failing test — ExpressionEvaluatorRegistry**

Create `packages/evaluation/tests/Evaluation/ExpressionEvaluatorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Evaluation\ExpressionEvaluatorRegistry;

it('resolves the highest-priority matching plugin', function (): void {
    $registry = new ExpressionEvaluatorRegistry();

    $low = new class implements \Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface {
        public function name(): string { return 'low'; }
        public function priority(): int { return -200; }
        public function supports(Expression $expression): bool { return true; }
        public function evaluate(Expression $expression, mixed $context): mixed { return 'low'; }
    };

    $high = new class implements \Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface {
        public function name(): string { return 'high'; }
        public function priority(): int { return -50; }
        public function supports(Expression $expression): bool { return true; }
        public function evaluate(Expression $expression, mixed $context): mixed { return 'high'; }
    };

    $registry->register($low);
    $registry->register($high);

    $expr = new Expression('$.foo');

    expect($registry->get($expr)?->name())->toBe('high');
});

it('returns null when no plugin matches', function (): void {
    $registry = new ExpressionEvaluatorRegistry();
    $expr = new Expression('$.foo');

    expect($registry->get($expr))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "ExpressionEvaluatorRegistryTest"` (repo root)

Expected: FAIL — `Class Alama\Arazzo\Expression\Evaluation\ExpressionEvaluatorRegistry not found`.

- [ ] **Step 3: Create `ExpressionEvaluatorRegistry`**

Create `packages/evaluation/src/Evaluation/ExpressionEvaluatorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;

class ExpressionEvaluatorRegistry
{
    /** @var list<ExpressionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function register(ExpressionEvaluatorPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
        usort($this->plugins, fn (ExpressionEvaluatorPluginInterface $a, ExpressionEvaluatorPluginInterface $b) => $b->priority() <=> $a->priority());
    }

    public function get(Expression $expression): ?ExpressionEvaluatorPluginInterface
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->supports($expression)) {
                return $plugin;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests --filter "ExpressionEvaluatorRegistryTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Write the failing test — CriterionEvaluatorRegistry**

Create `packages/evaluation/tests/Evaluation/CriterionEvaluatorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Expression\Evaluation\CriterionEvaluatorRegistry;

it('resolves by CriterionType enum', function (): void {
    $registry = new CriterionEvaluatorRegistry();

    $plugin = new class implements \Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface {
        public function name(): string { return 'jsonpath'; }
        public function priority(): int { return -100; }
        public function supports(CriterionType|SuccessCriterion $criterion): bool { return $criterion === CriterionType::JsonPath; }
        public function evaluate(SuccessCriterion $criterion, mixed $context, \Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $workflowContext): bool { return true; }
    };

    $registry->register($plugin);

    expect($registry->get(CriterionType::JsonPath)?->name())->toBe('jsonpath')
        ->and($registry->get(CriterionType::Simple))->toBeNull();
});

it('resolves by SuccessCriterion type field', function (): void {
    $registry = new CriterionEvaluatorRegistry();

    $plugin = new class implements \Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface {
        public function name(): string { return 'jsonpath'; }
        public function priority(): int { return -100; }
        public function supports(CriterionType|SuccessCriterion $criterion): bool { return $criterion instanceof SuccessCriterion && ($criterion->type ?? CriterionType::Simple) === CriterionType::JsonPath; }
        public function evaluate(SuccessCriterion $criterion, mixed $context, \Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface $workflowContext): bool { return true; }
    };

    $registry->register($plugin);

    $criterion = new SuccessCriterion(context: null, condition: '$.ok', type: CriterionType::JsonPath);

    expect($registry->get($criterion)?->name())->toBe('jsonpath');
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "CriterionEvaluatorRegistryTest"` (repo root)

Expected: FAIL.

- [ ] **Step 7: Create `CriterionEvaluatorRegistry`**

Create `packages/evaluation/src/Evaluation/CriterionEvaluatorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

class CriterionEvaluatorRegistry
{
    /** @var list<CriterionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function register(CriterionEvaluatorPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
        usort($this->plugins, fn (CriterionEvaluatorPluginInterface $a, CriterionEvaluatorPluginInterface $b) => $b->priority() <=> $a->priority());
    }

    public function get(CriterionType|SuccessCriterion $criterion): ?CriterionEvaluatorPluginInterface
    {
        foreach ($this->plugins as $plugin) {
            if ($plugin->supports($criterion)) {
                return $plugin;
            }
        }

        return null;
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests --filter "CriterionEvaluatorRegistryTest"` (repo root)

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add packages/evaluation/src/Evaluation/ExpressionEvaluatorRegistry.php packages/evaluation/src/Evaluation/CriterionEvaluatorRegistry.php packages/evaluation/tests/Evaluation/ExpressionEvaluatorRegistryTest.php packages/evaluation/tests/Evaluation/CriterionEvaluatorRegistryTest.php
git commit -m "feat(evaluation): add priority-ordered evaluator and criterion plugin registries"
```

---

### Task C3: Refactor `CriteriaEvaluator` — plugin-based criterion dispatch

Replace the hard-coded `match` in `CriteriaEvaluator::evaluateCriteria` with a plugin-delegating dispatch. `simple`/`regex`/`xpath` stay in-core (no vendor); `jsonpath` and future types go through `CriterionEvaluatorRegistry`.

**Files:**
- Modify: `packages/evaluation/src/Evaluation/CriteriaEvaluator.php` (refactor `evaluateCriteria`)
- Create: `packages/evaluation/src/Evaluation/CriterionTypeNotSupportedException.php`
- Test: `packages/evaluation/tests/Evaluation/CriteriaEvaluatorPluginDispatchTest.php`

**Interfaces:**
- Consumes: `CriterionEvaluatorRegistry` (C2), `CriterionEvaluatorPluginInterface` (Phase A contracts), `CriterionType`, `Expression`, `EvaluationContext`.
- Produces: `CriteriaEvaluator` with injectable `CriterionEvaluatorRegistry`; `CriterionTypeNotSupportedException`.

- [ ] **Step 1: Write the failing test — CriteriaEvaluator delegates JsonPath to registry**

Create `packages/evaluation/tests/Evaluation/CriteriaEvaluatorPluginDispatchTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Expression\Evaluation\CriterionEvaluatorRegistry;
use Alama\Arazzo\Expression\Evaluation\CriterionTypeNotSupportedException;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;

it('delegates jsonpath criteria to a registered plugin', function (): void {
    $evaluated = false;

    $plugin = new class ($evaluated) implements CriterionEvaluatorPluginInterface {
        public function __construct(private bool &$evaluated) {}

        public function name(): string { return 'jsonpath'; }
        public function priority(): int { return 0; }
        public function supports(CriterionType|SuccessCriterion $criterion): bool { return $criterion === CriterionType::JsonPath || ($criterion instanceof SuccessCriterion && ($criterion->type ?? CriterionType::Simple) === CriterionType::JsonPath); }
        public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool { $this->evaluated = true; return true; }
    };

    $registry = new CriterionEvaluatorRegistry();
    $registry->register($plugin);

    $expressionEvaluator = \Mockery::mock(ExpressionEvaluatorInterface::class);
    $evaluator = new CriteriaEvaluator($expressionEvaluator, criterionRegistry: $registry);

    $criterion = new SuccessCriterion(context: null, condition: '$.ok', type: CriterionType::JsonPath);
    $step = StepFactory::http('test', null, new StepFlow(), new StepIo(), operationId: 'op');
    $context = \Mockery::mock(WorkflowContextInterface::class);

    $result = $evaluator->evaluateCriteria([$criterion], $step, $context);

    expect($result)->toBeTrue()
        ->and($evaluated)->toBeTrue();
});

it('throws CriterionTypeNotSupportedException for unregistered criterion types', function (): void {
    $registry = new CriterionEvaluatorRegistry();
    $expressionEvaluator = \Mockery::mock(ExpressionEvaluatorInterface::class);
    $evaluator = new CriteriaEvaluator($expressionEvaluator, criterionRegistry: $registry);

    $criterion = new SuccessCriterion(context: null, condition: '$.ok', type: CriterionType::JsonPath);
    $step = StepFactory::http('test', null, new StepFlow(), new StepIo(), operationId: 'op');
    $context = \Mockery::mock(WorkflowContextInterface::class);

    $evaluator->evaluateCriteria([$criterion], $step, $context);
})->throws(CriterionTypeNotSupportedException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "CriteriaEvaluatorPluginDispatchTest"` (repo root)

Expected: FAIL — `CriterionTypeNotSupportedException` does not exist, and `CriteriaEvaluator` constructor doesn't accept `criterionRegistry`.

- [ ] **Step 3: Create `CriterionTypeNotSupportedException`**

Create `packages/evaluation/src/Evaluation/CriterionTypeNotSupportedException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Support\Exceptions\ArazzoException;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;

final class CriterionTypeNotSupportedException extends ArazzoException
{
    public function __construct(
        CriterionType $type,
        string $path = '',
    ) {
        parent::__construct(
            "Criterion type '{$type->value}' is not supported. Register a CriterionEvaluatorPluginInterface for this type.",
            $path,
            'criterion.unsupported_type',
        );
    }
}
```

- [ ] **Step 4: Refactor `CriteriaEvaluator`**

Edit `packages/evaluation/src/Evaluation/CriteriaEvaluator.php`:

Add the new constructor parameter and the `CriterionTypeNotSupportedException` import. The `evaluateCriteria` method is refactored to dispatch `JsonPath` through the registry while keeping `Simple`/`Regex`/`XPath` in-core.

Key changes to `CriteriaEvaluator`:
1. Add `private CriterionEvaluatorRegistry $criterionRegistry` constructor parameter (with default `new CriterionEvaluatorRegistry()`).
2. In `evaluateCriteria`, change the `CriterionType::JsonPath` arm of the `match` to delegate through the registry.

The resulting `evaluateCriteria` method:

```php
public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
{
    if (empty($criteria)) {
        return true;
    }

    $steps = $context->getSteps();
    $stepData = $steps[$step->stepId] ?? null;
    $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
    $responseBody = is_array($response) ? ($response['body'] ?? []) : [];

    foreach ($criteria as $criterion) {
        $type = $criterion->type ?? CriterionType::Simple;

        $passed = match ($type) {
            CriterionType::Simple => $this->evaluateSimple($criterion, $context, $step->stepId, $document),
            CriterionType::Regex => $this->evaluateRegex($criterion, $context, $step->stepId, $document),
            CriterionType::XPath => $this->evaluateXPath($criterion, $context, $step->stepId, $document),
            default => $this->evaluateViaPlugin($criterion, $type, $responseBody, $context, $step, $document),
        };

        if (!$passed) {
            return false;
        }
    }

    return true;
}
```

Add the new private method:

```php
private function evaluateViaPlugin(
    SuccessCriterion $criterion,
    CriterionType $type,
    mixed $responseBody,
    WorkflowContextInterface $context,
    Step $step,
    ?ArazzoDocument $document,
): bool {
    $plugin = $this->criterionRegistry->get($criterion);

    if ($plugin === null) {
        throw new CriterionTypeNotSupportedException($type);
    }

    $pluginContext = $criterion->context !== null
        ? ($this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $step->stepId, $document)) ?? $responseBody)
        : $responseBody;

    return $plugin->evaluate($criterion, $pluginContext, $step, $context);
}
```

Update the constructor signature:

```php
public function __construct(
    private ExpressionEvaluatorInterface $evaluator,
    ?ConditionEvaluator $conditionEvaluator = null,
    ?XpathEvaluator $xpathEvaluator = null,
    ?CriterionEvaluatorRegistry $criterionRegistry = null,
) {
    $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($evaluator);
    $this->xpathEvaluator = $xpathEvaluator;
    $this->criterionRegistry = $criterionRegistry ?? new CriterionEvaluatorRegistry();
}
```

Remove the old `evaluateJsonPath` private method (its logic is now in the plugin delegate path).

- [ ] **Step 5: Run the plugin dispatch test**

Run: `vendor/bin/pest packages/evaluation/tests --filter "CriteriaEvaluatorPluginDispatchTest"` (repo root)

Expected: PASS.

- [ ] **Step 6: Run full evaluation test suite to confirm no regressions**

Run: `composer run test-expression && vendor/bin/pest packages/evaluation/tests` (repo root)

Expected: PASS. Existing criteria tests that use `CriterionType::JsonPath` without a registered plugin will now throw `CriterionTypeNotSupportedException`. Tests that exercise `JsonPath` criteria must be updated to either register the jsonpath plugin or mock the registry. Check each failing test and add the registry wiring (injecting a mock `CriterionEvaluatorRegistry` or a real one with a stub plugin).

- [ ] **Step 7: Update `ExpressionEngine` to wire the `CriterionEvaluatorRegistry` through**

Edit `packages/evaluation/src/ExpressionEngine.php` — update the `criteria()` factory to pass the registry, and make `ExpressionEngine` implement `ExpressionInterface` (the parse/inspect seam) so downstream consumers can type-hint the seam and receive the engine:

First, add the import and declare the interface on the class:

```php
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;

final class ExpressionEngine implements ExpressionEngineInterface, ExpressionInterface
```

Then update the `criteria()` factory:

```php
private function criteria(): CriteriaEvaluator
{
    return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->evaluator, criterionRegistry: $this->criterionRegistry);
}
```

Add `criterionRegistry` as a constructor parameter with a default:

```php
public function __construct(
    private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
    private readonly ExpressionParser $parser = new ExpressionParser(),
    private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
    private readonly ?CriterionEvaluatorRegistry $criterionRegistry = null,
) {}
```

(The `ExpressionEngine` already implements the three `ExpressionInterface` methods — `parseExpression`, `expressionReferences`, `buildSymbolTable` — from the original `ExpressionEngineInterface`; adding the interface is a declaration-only change.)

- [ ] **Step 8: Run full evaluation test suite**

Run: `vendor/bin/pest packages/evaluation/tests` (repo root)

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add packages/evaluation/src/Evaluation/CriteriaEvaluator.php packages/evaluation/src/Evaluation/CriterionTypeNotSupportedException.php packages/evaluation/src/ExpressionEngine.php packages/evaluation/tests/Evaluation/CriteriaEvaluatorPluginDispatchTest.php
git commit -m "feat(evaluation): refactor CriteriaEvaluator to delegate non-core criterion types via plugin registry"
```

---

### Task C4: Update downstream package dependencies

Wire the split into the monorepo dependency graph: `document` keeps `arazzo-expression` only; `runner` gets `arazzo-evaluation`; `laravel`/`cli`/`core` get both.

**Files:**
- Modify: `packages/document/composer.json` (keep `alama/arazzo-expression`; ensure no `alama/arazzo-evaluation` added)
- Modify: `packages/runner/composer.json` (add `alama/arazzo-evaluation: @dev`, keep `alama/arazzo-expression`)
- Modify: `packages/laravel/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: `packages/cli/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: `packages/core/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: root `composer.json` autoload-dev (add evaluation tests, add evaluator-jsonpath tests)
- Modify: root `composer.json` scripts (add `test-evaluation`, `test-evaluator-jsonpath`, `analyse-evaluator-jsonpath`)

**Interfaces:**
- Consumes: all previous tasks (C0–C3).
- Produces: every sub-package's `composer.json` correctly wired; root scripts work.

- [ ] **Step 1: Update `packages/runner/composer.json`**

Edit `packages/runner/composer.json` — add to `require`:

```json
"alama/arazzo-evaluation": "@dev",
```

- [ ] **Step 2: Update `packages/laravel/composer.json`**

Edit `packages/laravel/composer.json` — add to `require`:

```json
"alama/arazzo-evaluation": "@dev",
```

- [ ] **Step 3: Update `packages/cli/composer.json`**

Edit `packages/cli/composer.json` — add to `require`:

```json
"alama/arazzo-evaluation": "@dev",
```

- [ ] **Step 4: Update `packages/core/composer.json`**

Edit `packages/core/composer.json` — add to `require`:

```json
"alama/arazzo-evaluation": "@dev",
```

- [ ] **Step 5: Add root composer.json scripts**

Add to root `composer.json` `scripts`:

```json
"test-evaluation": "vendor/bin/pest packages/evaluation/tests",
"test-evaluator-jsonpath": "vendor/bin/pest packages/evaluator-jsonpath/tests",
"analyse-evaluator-jsonpath": "vendor/bin/phpstan analyse -c packages/evaluator-jsonpath/phpstan.neon.dist --memory-limit=1G",
```

Update the `test` script array:

```json
"test": [
    "@test-contracts",
    "@test-expression",
    "@test-evaluation",
    "@test-evaluator-jsonpath",
    "@test-document",
    "@test-runner",
    "@test-cli",
    "@test-core",
    "@test-laravel"
],
```

Update the `analyse` script array:

```json
"analyse": [
    "@analyse-contracts",
    "@analyse-expression",
    "@analyse-evaluation",
    "@analyse-evaluator-jsonpath",
    "@analyse-document",
    "@analyse-runner",
    "@analyse-cli",
    "@analyse-laravel"
],
```

- [ ] **Step 6: Add evaluator-jsonpath tests to root autoload-dev**

Edit root `composer.json` autoload-dev — add the evaluator-jsonpath test path:

```json
"Alama\\Arazzo\\Evaluator\\JsonPath\\Tests\\": [
    "packages/evaluator-jsonpath/tests"
]
```

- [ ] **Step 7: Regenerate autoloader and run all gates**

Run: `composer dump-autoload && composer run test-expression && composer run test-evaluation && composer run test-evaluator-jsonpath` (repo root)

Expected: PASS across all three packages.

- [ ] **Step 8: Run static analysis on all three packages**

Run: `composer run analyse-expression && composer run analyse-evaluation && composer run analyse-evaluator-jsonpath` (repo root)

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add packages/document/composer.json packages/runner/composer.json packages/laravel/composer.json packages/cli/composer.json packages/core/composer.json composer.json
git commit -m "chore: wire split packages into monorepo dependency graph"
```

---

### Task C5: Update expression package test suite and arch tests

After the split, expression tests no longer test evaluation classes. Update `ArchTest.php` and remove/migrate evaluation-side tests.

**Files:**
- Modify: `packages/expression/tests/ArchTest.php` (remove evaluation-package references)
- Remove or migrate: any remaining evaluation-side test files in `packages/expression/tests/`
- Test: `packages/expression/tests/` (full suite green)

**Interfaces:**
- Consumes: C0–C4.
- Produces: expression package tests green; arch test reflects parse-only boundary.

- [ ] **Step 1: Inspect remaining expression tests**

Run: `ls packages/expression/tests/` (repo root)

Check each remaining test file. Tests that reference `ExpressionEngine`, `ExpressionEvaluator`, `CriteriaEvaluator`, `SelectorEvaluator`, `JsonPathEvaluator`, `JsonPointer`, `DomXpathEvaluator`, `StringInterpolator`, or `EvaluationInput` must have already moved to `packages/evaluation/tests/` in C0 Step 9. If any remain, move them now.

- [ ] **Step 2: Update `packages/expression/tests/ArchTest.php`**

The current arch test asserts that expression internals do not use the `ExpressionEngine` class (line 18). After the split, `ExpressionEngine` is in a different package entirely — the assertion is still valid but now also enforces that expression does not use ANY evaluation-side class. Update:

```php
<?php

declare(strict_types=1);

arch('expression does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Illuminate');

arch('expression does not use evaluation-side classes')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionEngine')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionEngineInterface')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionEvaluator')
    ->not->toUse('Alama\Arazzo\Expression\SelectorEvaluator')
    ->not->toUse('Alama\Arazzo\Expression\StringInterpolator')
    ->not->toUse('Alama\Arazzo\Expression\JsonPointer')
    ->not->toUse('Alama\Arazzo\Expression\JsonPathEvaluator')
    ->not->toUse('Alama\Arazzo\Expression\Xpath')
    ->not->toUse('Alama\Arazzo\Expression\Evaluation');

arch('expression does not leak document/runner internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Document\Validator');
```

- [ ] **Step 3: Run expression test suite + arch test**

Run: `composer run test-expression` (repo root)

Expected: PASS.

- [ ] **Step 4: Run evaluation arch test**

Create `packages/evaluation/tests/ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('evaluation does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Illuminate');

arch('evaluation does not leak document/runner internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console');
```

Run: `vendor/bin/pest packages/evaluation/tests --filter ArchTest` (repo root)

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/expression/tests/ArchTest.php packages/evaluation/tests/ArchTest.php
git commit -m "test: update arch tests for expression/evaluation split boundary"
```

---

### Task C6: Verification gate — full monorepo quality pass

Close out Phase C: full quality gate across all packages, confirming the split has no regressions.

**Files:**
- None to modify (unless formatting requires).

**Interfaces:**
- Consumes: all tasks C0–C5.

- [ ] **Step 1: Run the full test suite**

Run: `composer run test` (repo root)

Expected: PASS (all packages: contracts, expression, evaluation, evaluator-jsonpath, document, runner, cli, core, laravel).

- [ ] **Step 2: Run static analysis**

Run: `composer run analyse` (repo root)

Expected: PASS (all packages). If PHPStan reports "Class X not found" in evaluation or evaluator-jsonpath, check that `scanDirectories` in their `phpstan.neon.dist` includes the sibling package `src/` dirs.

- [ ] **Step 3: Run the formatter check**

Run: `vendor/bin/pint --test` (repo root)

Expected: PASS. If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [ ] **Step 4: Run the repo-wide gate**

Run: `make verify` (repo root)

Expected: PASS — confirms the split does not break any composed workflow.

- [ ] **Step 5: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [ ] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Phase C section, and add:

```markdown
Phase C status: ✅ Implemented 2026-09-09 — see `plans/2026-09-08-phase-c-evaluator-plugins.md`.
```

- [ ] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md docs/superpowers/plans/2026-09-08-phase-c-evaluator-plugins.md
git commit -m "docs: mark Phase C evaluator plugins + vendor isolation complete"
```
