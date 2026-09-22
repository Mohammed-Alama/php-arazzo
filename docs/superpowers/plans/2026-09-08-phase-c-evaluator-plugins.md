# Phase C — Evaluator plugins + vendor isolation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `arazzo-expression` into reference-model + evaluation packages, keep `softcreatr/jsonpath` as a built-in default plugin behind the new evaluator registries, and make the `CriteriaEvaluator` hard-coded `match` plugin-extensible.

**Architecture:** The current `arazzo-expression` package (namespace `Alama\Arazzo\Expression`) is split: `arazzo-expression` keeps the zero-vendor lexer/parser/AST/reference-model and exposes a new `ExpressionInterface` parse/inspect seam implemented by a new parse-side `ExpressionInspector`; a new `arazzo-evaluation` package owns the engine facade, evaluator internals, interpolation, payload replacement, and all evaluation DTOs — requiring `arazzo-expression` and delegating parse/inspect/symbols to it. The evaluation package uses its own **flat** namespace `Alama\Arazzo\Evaluation\` (FQCNs change: `Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator` → `Alama\Arazzo\Evaluation\CriteriaEvaluator`; this is an intentional, one-time breaking change — see C0). `JsonPathEvaluator` + `softcreatr/jsonpath` do NOT move to a third package: they stay in `arazzo-evaluation` and are wired as **built-in default plugins** implementing the contracts plugin interfaces (`ExpressionEvaluatorPluginInterface`, `CriterionEvaluatorPluginInterface`). Two new registries — `ExpressionEvaluatorRegistry` and `CriterionEvaluatorRegistry` — provide priority-ordered, first-match plugin resolution. `CriteriaEvaluator` is refactored: `simple`/`regex`/`xpath` stay in-core (no vendor); `jsonpath` and future types delegate through the `CriterionEvaluatorRegistry`.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), Pest Arch (`pestphp/pest-plugin-arch`), PHPStan ^2.0 + `phpstan-deprecation-rules`, Laravel Pint, `psr/event-dispatcher` ^1.0, `psr/log` ^3.0, `softcreatr/jsonpath` ^0.10.0 (owned by `arazzo-evaluation`, built into the default registries).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- `arazzo-expression` keeps PSR-4 prefix `Alama\Arazzo\Expression\` for the parse side only (lexer/parser/AST/reference-model). No `softcreatr/jsonpath`.
- `arazzo-evaluation` uses flat PSR-4 prefix `Alama\Arazzo\Evaluation\`; requires `arazzo-expression` + `arazzo-contracts`; owns `softcreatr/jsonpath: ^0.10.0`.
- **Breaking namespace change is intentional and in-scope (C0):** every evaluation-side class moves from `Alama\Arazzo\Expression\…` to `Alama\Arazzo\Evaluation\…`; `Evaluation\` is flattened (no `Aliama\Arazzo\Evaluation\Evaluation\`). Consumers (`runner`, `cli`, `laravel`, `core`, `document` tests) update their imports in C4.
- `arazzo-expression` stays zero-vendor: `require` = `php: ^8.4` + `alama/arazzo-contracts: @dev` + `psr/event-dispatcher: ^1.0` + `psr/log: ^3.0` only.
- `supportedXPathVersions()` capability moves to the evaluation layer; `document` PreflightValidator no longer checks it (parse-only seam).
- Public faces that change FQCN (map accordingly): `ExpressionEngine`, `ExpressionEngineInterface`, `ExpressionEvaluator`, `ExpressionEvaluatorInterface`, `ExpressionResolverInterface`, `EvaluationInputInterface`, `CriteriaEvaluator`, `CriteriaEvaluatorInterface`, `SelectorEvaluator`, `StringInterpolator`, `JsonPointer`, `JsonPathEvaluator`, `XpathEvaluator`, `DomXpathEvaluator`, `EvaluationInput`, `EvaluationContext`, `ConditionNode`, `SelectorEvaluationException` → `Alama\Arazzo\Evaluation\…`.
- Every new interface/value-type file: `declare(strict_types=1)`, repo convention for `final readonly class` / `enum`.
- No code comments unless explaining a deprecation or a BC seam.
- Every task ends with the relevant composer script green from the repo root.
- All test commands run from the **repo root** (`vendor/bin/pest ...`).

---

### Task C0: Scaffold `arazzo-evaluation` package + move evaluation classes

The core of the split. Create `packages/evaluation/` with its own `composer.json`, then move every evaluation-side class from `packages/expression/src/Evaluation/`, `packages/expression/src/Interfaces/`, `packages/expression/src/Data/EvaluationInput.php`, `packages/expression/src/ExpressionEngine.php`, `packages/expression/src/ExpressionEngineInterface.php`, `packages/expression/src/ExpressionEvaluator.php`, `packages/expression/src/SelectorEvaluator.php`, `packages/expression/src/StringInterpolator.php`, `packages/expression/src/JsonPointer.php`, `packages/expression/src/JsonPathEvaluator.php`, and `packages/expression/src/Xpath/` into the new package. Evaluation classes get the **flat** namespace `Alama\Arazzo\Evaluation\` (see table below); `JsonPathEvaluator` stays in `arazzo-evaluation` (no third package). Every moved file's `namespace` statement and internal `use` references are updated to `Alama\Arazzo\Evaluation\…`.

**Files:**
- Create: `packages/expression/src/Interfaces/ExpressionInterface.php` (new parse/inspect seam — spec D11)
- Create: `packages/expression/src/ExpressionInspector.php` (parse-side concrete implementing `ExpressionInterface`)
- Create: `packages/evaluation/composer.json`
- Create: `packages/evaluation/phpstan.neon.dist`
- Create: `packages/evaluation/phpstan-baseline.neon` (empty baseline file)
- Move + rename namespace: all 23 evaluation-side classes listed below
- Modify: `packages/expression/composer.json` (remove `softcreatr/jsonpath` from `require`)
- Modify: `composer.json` (root — add repositories + require for `alama/arazzo-evaluation`)

**Interfaces:**
- Consumes: `ExpressionEngineInterface` (current, in `packages/expression/src/`), `ExpressionEngine` (current), all `Evaluation\*` classes, `ExpressionEvaluator`, `SelectorEvaluator`, `StringInterpolator`, `JsonPointer`, `JsonPathEvaluator`, `Xpath/DomXpathEvaluator`, `Xpath/XpathEvaluator`, `EvaluationInput`, `EvaluationContext`.
- Produces: `arazzo-evaluation` package with flat `Alama\Arazzo\Evaluation\` FQCNs for all moved classes; `packages/expression/` retains only parse-side classes.

**Classes that move from `packages/expression/src/` → `packages/evaluation/src/` (flat namespace):**

| File (relative to `src/`) | FQCN |
|---|---|
| `ExpressionEngine.php` | `Alama\Arazzo\Evaluation\ExpressionEngine` |
| `ExpressionEngineInterface.php` | `Alama\Arazzo\Evaluation\ExpressionEngineInterface` |
| `ExpressionEvaluator.php` | `Alama\Arazzo\Evaluation\ExpressionEvaluator` |
| `SelectorEvaluator.php` | `Alama\Arazzo\Evaluation\SelectorEvaluator` |
| `StringInterpolator.php` | `Alama\Arazzo\Evaluation\StringInterpolator` |
| `JsonPointer.php` | `Alama\Arazzo\Evaluation\JsonPointer` |
| `JsonPathEvaluator.php` | `Alama\Arazzo\Evaluation\JsonPathEvaluator` |
| `Interfaces/EvaluationInputInterface.php` | `Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface` |
| `Interfaces/ExpressionEvaluatorInterface.php` | `Alama\Arazzo\Evaluation\Interfaces\ExpressionEvaluatorInterface` |
| `Interfaces/ExpressionResolverInterface.php` | `Alama\Arazzo\Evaluation\Interfaces\ExpressionResolverInterface` |
| `Data/EvaluationInput.php` | `Alama\Arazzo\Evaluation\Data\EvaluationInput` |
| `Xpath/XpathEvaluator.php` | `Alama\Arazzo\Evaluation\Xpath\XpathEvaluator` |
| `Xpath/DomXpathEvaluator.php` | `Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator` |
| `Exceptions/SelectorEvaluationException.php` | `Alama\Arazzo\Evaluation\Exceptions\SelectorEvaluationException` |
| `Evaluation/CriteriaEvaluator.php` | `Alama\Arazzo\Evaluation\CriteriaEvaluator` |
| `Evaluation/ExpressionResolver.php` | `Alama\Arazzo\Evaluation\ExpressionResolver` |
| `Evaluation/PayloadReplacer.php` | `Alama\Arazzo\Evaluation\PayloadReplacer` |
| `Evaluation/InterpolationResolver.php` | `Alama\Arazzo\Evaluation\InterpolationResolver` |
| `Evaluation/Data/EvaluationContext.php` | `Alama\Arazzo\Evaluation\Data\EvaluationContext` |
| `Evaluation/Interfaces/CriteriaEvaluatorInterface.php` | `Alama\Arazzo\Evaluation\Interfaces\CriteriaEvaluatorInterface` |
| `Evaluation/Interfaces/ConditionNode.php` | `Alama\Arazzo\Evaluation\Interfaces\ConditionNode` |
| `Evaluation/Condition/*` (6 files) | `Alama\Arazzo\Evaluation\Condition\*` |
| `Evaluation/Enum/*` (3 files) | `Alama\Arazzo\Evaluation\Enum\*` |

**Flattening note:** files under `Evaluation/` lose that segment on the destination filesystem — `packages/expression/src/Evaluation/CriteriaEvaluator.php` → `packages/evaluation/src/CriteriaEvaluator.php` (namespace `Alama\Arazzo\Evaluation`), `Evaluation/Data/EvaluationContext.php` → `packages/evaluation/src/Data/EvaluationContext.php` (namespace `Alama\Arazzo\Evaluation\Data`), `Evaluation/Condition/*` → `packages/evaluation/src/Condition/*`, `Evaluation/Enum/*` → `packages/evaluation/src/Enum/*`, `Evaluation/Interfaces/*` → `packages/evaluation/src/Interfaces/*`.

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

use Alama\Arazzo\Evaluation\ExpressionEngine;
use Alama\Arazzo\Evaluation\ExpressionEngineInterface;

it('autoloads the evaluation package expression engine')
    ->expect(new ExpressionEngine())->toBeInstanceOf(ExpressionEngineInterface::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "PackageScaffold"` (repo root)

Expected: FAIL — `Class Alama\Arazzo\Evaluation\ExpressionEngine not found` (the class still lives in `packages/expression` at this point, but the evaluation package doesn't exist yet).

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

it('is implemented by the parse-side ExpressionInspector', function (): void {
    // After the split, ExpressionInspector (arazzo-expression) implements the seam;
    // the evaluation ExpressionEngine may also implement it for convenience.
    $rc = new ReflectionClass(ExpressionInterface::class);

    expect($rc->getMethods())->toHaveCount(3)
        ->and($rc->hasMethod('parseExpression'))->toBeTrue()
        ->and($rc->hasMethod('expressionReferences'))->toBeTrue()
        ->and($rc->hasMethod('buildSymbolTable'))->toBeTrue();
});
```

Run: `vendor/bin/pest packages/expression/tests --filter "ExpressionInterfaceTest"` (repo root)

Expected: PASS (interface is created in this step).

- [ ] **Step 4b: Create parse-side `ExpressionInspector` + failing test**

`ExpressionInspector` is the parse-side concrete that implements `ExpressionInterface`. It wraps the lexer/parser/symbol-table already in `arazzo-expression` — no evaluation classes. `document` constructs this instead of `new ExpressionEngine()`.

Create `packages/expression/src/ExpressionInspector.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

final class ExpressionInspector implements ExpressionInterface
{
    private readonly ExpressionParser $parser;

    public function __construct(?ExpressionParser $parser = null)
    {
        $this->parser = $parser ?? new ExpressionParser();
    }

    public function parseExpression(string $raw): ?ExpressionSyntaxException
    {
        $result = $this->parser->parseOrError($raw);

        return $result instanceof ExpressionSyntaxException ? $result : null;
    }

    public function expressionReferences(string $raw): ?ExpressionReference
    {
        // Projection lives in the parser/reference model (parse side).
        return $this->parser->parseOrError($raw) instanceof ExpressionSyntaxException
            ? null
            : $this->parser->projectReferences($raw);
    }

    public function buildSymbolTable(ArazzoDocument $document): SymbolTable
    {
        return SymbolTable::build($document);
    }
}
```

(Adjust method wiring to the actual parser API when implementing — the invariant is: `ExpressionInspector` never touches `ExpressionEvaluator`/engine internals.)

Create `packages/expression/tests/ExpressionInspectorTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\ExpressionInspector;
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;

it('implements the parse/inspect seam', function (): void {
    expect(new ExpressionInspector())->toBeInstanceOf(ExpressionInterface::class);
});

it('parses a valid expression without a syntax error', function (): void {
    $inspector = new ExpressionInspector();

    expect($inspector->parseExpression('$steps.get.pet.outputs.body'))->toBeNull();
});

it('builds a symbol table from a document', function (): void {
    $inspector = new ExpressionInspector();

    expect($inspector->buildSymbolTable(ArazzoDocument::fromArray([])))->toBeInstanceOf(\Alama\Arazzo\Expression\SymbolTable::class);
});
```

Run: `vendor/bin/pest packages/expression/tests --filter "ExpressionInspectorTest"` (repo root)

Expected: PASS.

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
        "psr/log": "^3.0",
        "softcreatr/jsonpath": "^0.10.0"
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
            "Alama\\Arazzo\\Evaluation\\": "src/"
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

- [ ] **Step 7: Move evaluation-side source files (and re-namespace to `Alama\Arazzo\Evaluation\`)**

Move each file. Because the evaluation package uses the flat `Alama\Arazzo\Evaluation\` prefix, files move up out of `Evaluation/` on the destination side, and the `namespace`/`use` statements inside every moved file change:

- `Alama\Arazzo\Expression\` → `Alama\Arazzo\Evaluation\` for top-level eval classes (`ExpressionEngine`, `ExpressionEngineInterface`, `ExpressionEvaluator`, `SelectorEvaluator`, `StringInterpolator`, `JsonPointer`, `JsonPathEvaluator`), the moved `Interfaces\*`, `Data\EvaluationInput`, `Xpath\*`, `Exceptions\SelectorEvaluationException`.
- `Alama\Arazzo\Expression\Evaluation\` → `Alama\Arazzo\Evaluation\` (drop the `Evaluation\` segment) for the `Evaluation\*` subtree.
- Parse-side references that stay (`SymbolTable`, `Parser`, `Lexer`, AST, `Data\{Token, StepSymbols, WorkflowSymbols, ExpressionReference}`, `Enum\{TokenKind, ReferenceKind}`, `Exceptions\ExpressionSyntaxException`) keep `Alama\Arazzo\Expression\…`.

```bash
# Create target directories (flat layout — no Evaluation/ segment)
mkdir -p packages/evaluation/src/Condition/Ast
mkdir -p packages/evaluation/src/Data
mkdir -p packages/evaluation/src/Enum
mkdir -p packages/evaluation/src/Interfaces
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

# Move Evaluation/* subtree — flatten: drop the Evaluation/ segment
mv packages/expression/src/Evaluation/CriteriaEvaluator.php packages/evaluation/src/
mv packages/expression/src/Evaluation/ExpressionResolver.php packages/evaluation/src/
mv packages/expression/src/Evaluation/PayloadReplacer.php packages/evaluation/src/
mv packages/expression/src/Evaluation/InterpolationResolver.php packages/evaluation/src/
mv packages/expression/src/Evaluation/Condition/* packages/evaluation/src/Condition/
mv packages/expression/src/Evaluation/Data/* packages/evaluation/src/Data/
mv packages/expression/src/Evaluation/Enum/* packages/evaluation/src/Enum/
mv packages/expression/src/Evaluation/Interfaces/* packages/evaluation/src/Interfaces/

# Clean up empty directories left behind
rmdir packages/expression/src/Xpath 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Condition/Ast 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Condition 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Data 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Enum 2>/dev/null || true
rmdir packages/expression/src/Evaluation/Interfaces 2>/dev/null || true
rmdir packages/expression/src/Evaluation 2>/dev/null || true
```

Then re-namespace every moved file. Replace `namespace Alama\Arazzo\Expression\Evaluation\` → `namespace Alama\Arazzo\Evaluation\` and `namespace Alama\Arazzo\Expression\` → `namespace Alama\Arazzo\Evaluation\` for the top-level eval classes, and update `use Alama\Arazzo\Expression\Evaluation\…` → `use Alama\Arazzo\Evaluation\…` plus `use Alama\Arazzo\Expression\{ExpressionEngine|ExpressionEvaluator|SelectorEvaluator|StringInterpolator|JsonPointer|JsonPathEvaluator|Data\EvaluationInput|Xpath\...|Interfaces\...}` → `use Alama\Arazzo\Evaluation\…`. A bulk sed over `packages/evaluation/src/` is acceptable:

```bash
# In packages/evaluation/src/** : drop the Expression\Evaluation and Evaluation\ prefix, and
# re-home eval-side top-level classes under Alama\Arazzo\Evaluation.
find packages/evaluation/src -name '*.php' -exec sed -i '' \
  -e 's/Alama\\Arazzo\\Expression\\Evaluation\\/Alama\\Arazzo\\Evaluation\\/g' \
  -e 's/Alama\\Arazzo\\Expression\\Data\\EvaluationInput/Alama\\Arazzo\\Evaluation\\Data\\EvaluationInput/g' \
  -e 's/Alama\\Arazzo\\Expression\\Interfaces\\(EvaluationInputInterface|ExpressionEvaluatorInterface|ExpressionResolverInterface)/Alama\\Arazzo\\Evaluation\\Interfaces\\\1/g' \
  -e 's/Alama\\Arazzo\\Expression\\Xpath\\/Alama\\Arazzo\\Evaluation\\Xpath\\/g' \
  -e 's/Alama\\Arazzo\\Expression\\Exceptions\\SelectorEvaluationException/Alama\\Arazzo\\Evaluation\\Exceptions\\SelectorEvaluationException/g' \
  -e 's/Alama\\Arazzo\\Expression\\(ExpressionEngine|ExpressionEngineInterface|ExpressionEvaluator|SelectorEvaluator|StringInterpolator|JsonPointer|JsonPathEvaluator)\b/Alama\\Arazzo\\Evaluation\\\1/g' \
  {} +
```

(BSD `sed -i ''` is macOS syntax; on Linux use `sed -i`. Verify with `rg -n 'namespace Alama' packages/evaluation/src` that every namespace is `Alama\Arazzo\Evaluation\…` after this pass.)

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

Expected: PASS — `ExpressionEngine` is now autoloaded from `packages/evaluation/src/` via the `Alama\Arazzo\Evaluation\` PSR-4 prefix mapped in the evaluation package.

- [ ] **Step 10: Run expression package tests to confirm parse-side still works**

Run: `composer run test-expression` (repo root)

Expected: PASS — parse-side classes (`Lexer`, `Parser`, `SymbolTable`, AST, `Token`, `TokenKind`, `ReferenceKind`, `ExpressionSyntaxException`, `ExpressionReference`) remain in `packages/expression` with their `Alama\Arazzo\Expression\…` FQCNs. Tests that previously tested `ExpressionEngine` capabilities must move to the evaluation test suite (Step 11) — fix any leftover stale eval imports there.

- [ ] **Step 11: Move evaluation-related test files**

The following test files test evaluation-side classes and must move from `packages/expression/tests/` to `packages/evaluation/tests/`, with their FQCN imports updated to `Alama\Arazzo\Evaluation\…`:

```bash
# Top-level evaluation tests
mv packages/expression/tests/ExpressionEngineTest.php packages/evaluation/tests/
mv packages/expression/tests/ExpressionEngineCapabilitiesTest.php packages/evaluation/tests/

# Evaluation/* test subtree (flatten)
mkdir -p packages/evaluation/tests/Condition
mkdir -p packages/evaluation/tests/Data
mkdir -p packages/evaluation/tests/Enum
mkdir -p packages/evaluation/tests/Interfaces
mv packages/expression/tests/Evaluation/CriteriaEvaluatorTest.php packages/evaluation/tests/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/PayloadReplacerTest.php packages/evaluation/tests/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/ExpressionResolverTest.php packages/evaluation/tests/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/InterpolationResolverTest.php packages/evaluation/tests/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/Condition/* packages/evaluation/tests/Condition/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/Data/* packages/evaluation/tests/Data/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/Enum/* packages/evaluation/tests/Enum/ 2>/dev/null || true
mv packages/expression/tests/Evaluation/Interfaces/* packages/evaluation/tests/Interfaces/ 2>/dev/null || true
rmdir packages/expression/tests/Evaluation 2>/dev/null || true
```

Run: `rg -l 'ExpressionEngine|ExpressionEvaluator|CriteriaEvaluator|SelectorEvaluator|StringInterpolator|JsonPointer|JsonPathEvaluator|DomXpathEvaluator|EvaluationInput|EvaluationContext' packages/expression/tests` and move/rewrite any remaining stale evaluation-side tests found.

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
git add packages/evaluation/ packages/expression/src/Interfaces/ExpressionInterface.php packages/expression/src/ExpressionInspector.php packages/expression/tests/ExpressionInterfaceTest.php packages/expression/tests/ExpressionInspectorTest.php packages/expression/src/ packages/expression/composer.json packages/expression/tests/ExpressionEngineTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php composer.json
git commit -m "feat(evaluation): split arazzo-expression — evaluation package owns engine, evaluator internals, and evaluation DTOs"
```

---

### Task C1: Register JsonPath as built-in default plugins (in `arazzo-evaluation`)

`JsonPathEvaluator` + `softcreatr/jsonpath` stay in `arazzo-evaluation` (no third package). This task implements them as the **built-in default plugins** that the registries (C2) and `ExpressionEngine` (C3) seed — so `jsonpath` criterion/selector types keep working out of the box, while remaining replaceable by a higher-priority plugin from a future vendor.

**Files:**
- Create: `packages/evaluation/src/JsonPathExpressionPlugin.php`
- Create: `packages/evaluation/src/JsonPathCriterionPlugin.php`
- Test: `packages/evaluation/tests/JsonPathExpressionPluginTest.php`
- Test: `packages/evaluation/tests/JsonPathCriterionPluginTest.php`

**Interfaces:**
- Consumes: `ExpressionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A task A2), `CriterionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A task A2), `PluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A task A1), `JsonPathEvaluator` (`Alama\Arazzo\Evaluation\JsonPathEvaluator`, moved in C0).
- Produces: `Alama\Arazzo\Evaluation\JsonPathExpressionPlugin` (implements `ExpressionEvaluatorPluginInterface`), `Alama\Arazzo\Evaluation\JsonPathCriterionPlugin` (implements `CriterionEvaluatorPluginInterface`).

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

- [ ] **Step 1: Write the failing test — JsonPathExpressionPlugin**

Create `packages/evaluation/tests/JsonPathExpressionPluginTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Evaluation\JsonPathExpressionPlugin;
use Alama\Arazzo\Evaluation\JsonPathEvaluator;

it('is a plugin')
    ->expect(new JsonPathExpressionPlugin())->toBeInstanceOf(PluginInterface::class);

it('supports jsonpath selector expressions')
    ->expect((new JsonPathExpressionPlugin())->name())->toBe('jsonpath-expression');

it('has priority below zero so built-in evaluators run first')
    ->expect((new JsonPathExpressionPlugin())->priority())->toBeLessThan(0);

it('evaluates a JSONPath selector', function (): void {
    $data = ['users' => [['name' => 'Alice'], ['name' => 'Bob']]];

    $result = (new JsonPathExpressionPlugin())->evaluate(new Expression('$.users[*].name'), $data);

    expect($result)->toBe(['Alice', 'Bob']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "JsonPathExpressionPluginTest"` (repo root)

Expected: FAIL (`JsonPathExpressionPlugin` does not exist yet).

- [ ] **Step 3: Create `JsonPathExpressionPlugin`**

Create `packages/evaluation/src/JsonPathExpressionPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

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

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests --filter "JsonPathExpressionPluginTest"` (repo root)

Expected: PASS.

- [ ] **Step 5: Write the failing test — JsonPathCriterionPlugin**

Create `packages/evaluation/tests/JsonPathCriterionPluginTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Evaluation\JsonPathCriterionPlugin;

it('is a plugin')
    ->expect(new JsonPathCriterionPlugin())->toBeInstanceOf(PluginInterface::class);

it('supports jsonpath criterion type')
    ->expect((new JsonPathCriterionPlugin())->supports(CriterionType::JsonPath))->toBeTrue();

it('rejects unsupported criterion types')
    ->expect((new JsonPathCriterionPlugin())->supports(CriterionType::Simple))->toBeFalse();
```

- [ ] **Step 6: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests --filter "JsonPathCriterionPluginTest"` (repo root)

Expected: FAIL.

- [ ] **Step 7: Create `JsonPathCriterionPlugin`**

Create `packages/evaluation/src/JsonPathCriterionPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
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

- [ ] **Step 8: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests --filter "JsonPathCriterionPluginTest"` (repo root)

Expected: PASS.

- [ ] **Step 9: Run full evaluation test suite**

Run: `vendor/bin/pest packages/evaluation/tests` (repo root)

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add packages/evaluation/src/JsonPathExpressionPlugin.php packages/evaluation/src/JsonPathCriterionPlugin.php packages/evaluation/tests/JsonPathExpressionPluginTest.php packages/evaluation/tests/JsonPathCriterionPluginTest.php
git commit -m "feat(evaluation): add built-in JsonPath expression and criterion plugin implementations"
```

---

### Task C2: Evaluator registries — `ExpressionEvaluatorRegistry` + `CriterionEvaluatorRegistry`

Priority-ordered, first-match registries that `ExpressionEngine` assembles and delegates to. Both registries ship pre-seeded with the built-in `JsonPathExpressionPlugin` / `JsonPathCriterionPlugin` (C1) so `jsonpath` works out of the box; external plugins register later with higher priority to override.

**Files:**
- Create: `packages/evaluation/src/ExpressionEvaluatorRegistry.php`
- Create: `packages/evaluation/src/CriterionEvaluatorRegistry.php`
- Test: `packages/evaluation/tests/ExpressionEvaluatorRegistryTest.php`
- Test: `packages/evaluation/tests/CriterionEvaluatorRegistryTest.php`

**Interfaces:**
- Consumes: `ExpressionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A), `CriterionEvaluatorPluginInterface` (`Alama\Arazzo\Contracts\Interfaces`, Phase A), `Expression` (`Alama\Arazzo\Contracts\Spec`), `CriterionType`, `SuccessCriterion`, `Step`, `WorkflowContextInterface`, `JsonPathExpressionPlugin`/`JsonPathCriterionPlugin` (C1).
- Produces: `ExpressionEvaluatorRegistry::register(ExpressionEvaluatorPluginInterface): void`, `::get(Expression): ?ExpressionEvaluatorPluginInterface`; `CriterionEvaluatorRegistry::register(CriterionEvaluatorPluginInterface): void`, `::get(CriterionType|SuccessCriterion): ?CriterionEvaluatorPluginInterface`. Constructors take the built-in plugin(s) as default parameters and register them on construction.

- [ ] **Step 1: Write the failing test — ExpressionEvaluatorRegistry**

Create `packages/evaluation/tests/ExpressionEvaluatorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Evaluation\ExpressionEvaluatorRegistry;

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

Expected: FAIL — `Class Alama\Arazzo\Evaluation\ExpressionEvaluatorRegistry not found`.

- [ ] **Step 3: Create `ExpressionEvaluatorRegistry`**

Create `packages/evaluation/src/ExpressionEvaluatorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;

class ExpressionEvaluatorRegistry
{
    /** @var list<ExpressionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function __construct(?ExpressionEvaluatorPluginInterface $builtIn = null)
    {
        if ($builtIn !== null) {
            $this->register($builtIn);
        }
    }

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

Create `packages/evaluation/tests/CriterionEvaluatorRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Evaluation\CriterionEvaluatorRegistry;

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

Create `packages/evaluation/src/CriterionEvaluatorRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

class CriterionEvaluatorRegistry
{
    /** @var list<CriterionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function __construct(?CriterionEvaluatorPluginInterface $builtIn = null)
    {
        if ($builtIn !== null) {
            $this->register($builtIn);
        }
    }

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
git add packages/evaluation/src/ExpressionEvaluatorRegistry.php packages/evaluation/src/CriterionEvaluatorRegistry.php packages/evaluation/tests/ExpressionEvaluatorRegistryTest.php packages/evaluation/tests/CriterionEvaluatorRegistryTest.php
git commit -m "feat(evaluation): add priority-ordered evaluator and criterion plugin registries"
```

---

### Task C3: Refactor `CriteriaEvaluator` — plugin-based criterion dispatch

Replace the hard-coded `match` in `CriteriaEvaluator::evaluateCriteria` with a plugin-delegating dispatch. `simple`/`regex`/`xpath` stay in-core (no vendor); `jsonpath` and future types go through `CriterionEvaluatorRegistry`.

**Files:**
- Modify: `packages/evaluation/src/CriteriaEvaluator.php` (refactor `evaluateCriteria`)
- Create: `packages/evaluation/src/CriterionTypeNotSupportedException.php`
- Test: `packages/evaluation/tests/CriteriaEvaluatorPluginDispatchTest.php`

**Interfaces:**
- Consumes: `CriterionEvaluatorRegistry` (C2), `CriterionEvaluatorPluginInterface` (Phase A contracts), `CriterionType`, `Expression`, `EvaluationContext`.
- Produces: `CriteriaEvaluator` with injectable `CriterionEvaluatorRegistry`; `CriterionTypeNotSupportedException`.

- [ ] **Step 1: Write the failing test — CriteriaEvaluator delegates JsonPath to registry**

Create `packages/evaluation/tests/CriteriaEvaluatorPluginDispatchTest.php`:

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
use Alama\Arazzo\Evaluation\CriterionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\CriterionTypeNotSupportedException;
use Alama\Arazzo\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionEvaluatorInterface;

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

Create `packages/evaluation/src/CriterionTypeNotSupportedException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

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

Edit `packages/evaluation/src/CriteriaEvaluator.php`:

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

Expected: PASS. Existing criteria tests that use `CriterionType::JsonPath` through `CriteriaEvaluator`'s default registry continue to pass because the default registry is seeded with the built-in `JsonPathCriterionPlugin` (C1). Tests that construct a bare `new CriterionEvaluatorRegistry()` and expect `JsonPath` to resolve must now call `new CriterionEvaluatorRegistry(new JsonPathCriterionPlugin())` or rely on the default. Check each failing test and add the registry wiring.

- [ ] **Step 7: Update `ExpressionEngine` to wire the `CriterionEvaluatorRegistry` through**

Edit `packages/evaluation/src/ExpressionEngine.php` — update the `criteria()` factory to pass a registry pre-seeded with the built-in `JsonPathCriterionPlugin`, and make `ExpressionEngine` implement `ExpressionInterface` (the parse/inspect seam) so downstream consumers can type-hint the seam and receive the engine:

First, add the import and declare the interface on the class:

```php
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;
use Alama\Arazzo\Evaluation\JsonPathCriterionPlugin;

final class ExpressionEngine implements ExpressionEngineInterface, ExpressionInterface
```

Then update the `criteria()` factory:

```php
private function criteria(): CriteriaEvaluator
{
    return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->evaluator, criterionRegistry: $this->criterionRegistry);
}
```

Add `criterionRegistry` as a constructor parameter with a default that seeds the built-in JsonPath plugin:

```php
public function __construct(
    private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
    private readonly ExpressionParser $parser = new ExpressionParser(),
    private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
    private readonly ?CriterionEvaluatorRegistry $criterionRegistry = null,
) {
    $this->criterionRegistry = $criterionRegistry ?? new CriterionEvaluatorRegistry(new JsonPathCriterionPlugin());
}
```

(The `ExpressionEngine` already implements the three `ExpressionInterface` methods — `parseExpression`, `expressionReferences`, `buildSymbolTable` — from the original seam; after C0 those methods live in the eval-side moved class and it implements the parse-side interface via the `Alama\Arazzo\Expression\Interfaces\ExpressionInterface` import. Adding the interface is a declaration-only change.)

- [ ] **Step 8: Run full evaluation test suite**

Run: `vendor/bin/pest packages/evaluation/tests` (repo root)

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add packages/evaluation/src/CriteriaEvaluator.php packages/evaluation/src/CriterionTypeNotSupportedException.php packages/evaluation/src/ExpressionEngine.php packages/evaluation/tests/CriteriaEvaluatorPluginDispatchTest.php
git commit -m "feat(evaluation): refactor CriteriaEvaluator to delegate non-core criterion types via plugin registry"
```

---

### Task C4: Update downstream packages — FQCN migration + document goes parse-only

Wire the split into the monorepo and migrate every consumer off the old `Alama\Arazzo\Expression\…` evaluation FQCNs (now `Alama\Arazzo\Evaluation\…`). `document` becomes **parse-only**: its validator/rule-set seam now depends on `ExpressionInterface` + `ExpressionInspector` (arazzo-expression) instead of `ExpressionEngine`/`ExpressionEngineInterface` (arazzo-evaluation), and its PreflightValidator drops the `supportedXPathVersions()` check (moves to the evaluation layer). `runner` gets `arazzo-evaluation`; `laravel`/`cli`/`core` get `arazzo-evaluation` too; **no `arazzo-evaluator-jsonpath` anywhere.**

**Files:**
- Modify: `packages/runner/composer.json` (add `alama/arazzo-evaluation: @dev`, keep `alama/arazzo-expression`)
- Modify: `packages/laravel/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: `packages/cli/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: `packages/core/composer.json` (add `alama/arazzo-evaluation: @dev`)
- Modify: `packages/document/composer.json` (keep `alama/arazzo-expression` only; do NOT add `alama/arazzo-evaluation`)
- Modify: `packages/document/src/*.php` (FQCN + seam swap, drop XPath version preflight) — see Step 6
- Move: `packages/document/tests/Resolver/SelectorEvaluatorTest.php` + `packages/document/tests/Resolver/Xpath/DomXpathEvaluatorTest.php` → evaluation test suite
- Modify: root `composer.json` autoload-dev (add evaluation tests) + scripts (`test-evaluation`, `analyse-evaluation`)

**FQCN remap for consumers (src + tests):**

| Old (Alama\Arazzo\Expression\…) | New (Alama\Arazzo\Evaluation\…) |
|---|---|
| `ExpressionEngine` | `Evaluation\ExpressionEngine` |
| `ExpressionEngineInterface` | `Evaluation\ExpressionEngineInterface` |
| `ExpressionEvaluator` | `Evaluation\ExpressionEvaluator` |
| `SelectorEvaluator` | `Evaluation\SelectorEvaluator` |
| `StringInterpolator` | `Evaluation\StringInterpolator` |
| `JsonPointer` | `Evaluation\JsonPointer` |
| `JsonPathEvaluator` | `Evaluation\JsonPathEvaluator` |
| `Data\EvaluationInput` | `Evaluation\Data\EvaluationInput` |
| `Evaluation\EvaluationContext` | `Evaluation\Data\EvaluationContext` |
| `Evaluation\CriteriaEvaluator` | `Evaluation\CriteriaEvaluator` |
| `Evaluation\ExpressionResolver` | `Evaluation\ExpressionResolver` |
| `Evaluation\PayloadReplacer` | `Evaluation\PayloadReplacer` |
| `Evaluation\InterpolationResolver` | `Evaluation\InterpolationResolver` |
| `Interfaces\EvaluationInputInterface` | `Evaluation\Interfaces\EvaluationInputInterface` |
| `Interfaces\ExpressionEvaluatorInterface` | `Evaluation\Interfaces\ExpressionEvaluatorInterface` |
| `Interfaces\ExpressionResolverInterface` | `Evaluation\Interfaces\ExpressionResolverInterface` |
| `Xpath\XpathEvaluator` | `Evaluation\Xpath\XpathEvaluator` |
| `Xpath\DomXpathEvaluator` | `Evaluation\Xpath\DomXpathEvaluator` |
| `Exceptions\SelectorEvaluationException` | `Evaluation\Exceptions\SelectorEvaluationException` |
| `Evaluation\…` (any remaining) | `Evaluation\…` (drop the `Evaluation\` segment) |

**Unchanged (still `Alama\Arazzo\Expression\…`):** `SymbolTable`, `Parser`, `Lexer`, `Ast\*`, `Data\{Token, StepSymbols, WorkflowSymbols, ExpressionReference}`, `Enum\{TokenKind, ReferenceKind}`, `Exceptions\ExpressionSyntaxException`, `Interfaces\ExpressionInterface`.

**Interfaces:**
- Consumes: all previous tasks (C0–C3).
- Produces: every sub-package's `composer.json` correctly wired; all `use` statements migrated; document parse-only; root scripts work.

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
"analyse-evaluation": "vendor/bin/phpstan analyse -c packages/evaluation/phpstan.neon.dist --memory-limit=1G",
```

Update the `test` script array:

```json
"test": [
    "@test-contracts",
    "@test-expression",
    "@test-evaluation",
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
    "@analyse-document",
    "@analyse-runner",
    "@analyse-cli",
    "@analyse-laravel"
],
```

- [ ] **Step 6: Make `document` parse-only**

Already wired in C0's split (Step 2/4b) are the parse-side seam (`ExpressionInterface`, `ExpressionInspector`). Now update `document/src`:

1. `packages/document/src/Document.php` — replace `use Alama\Arazzo\Expression\ExpressionEngine;` (and `ExpressionEngineInterface`) with `use Alama\Arazzo\Expression\ExpressionInspector;` and construct `$this->engine = new ExpressionInspector();` (line ~72).
2. `packages/document/src/Validator/Validator.php`, `packages/document/src/Validator/RuleSet.php`, `packages/document/src/Validator/PreflightValidator.php`, and the `Expression*Rule` files — replace the `ExpressionEngineInterface` type-hint with `Alama\Arazzo\Expression\Interfaces\ExpressionInterface`. The serialization/port-in/port-out via the 3-method seam is unchanged (`parseExpression`, `expressionReferences`, `buildSymbolTable`).
3. `packages/document/src/Validator/PreflightValidator.php` — **remove the `supportedXPathVersions()` preflight** (lines ~251–266). The capability check moves to the evaluation layer (e.g. surfaced by `ExpressionEngineInterface::supportedXPathVersions()` for executors). `document` must not touch it.
4. Leave all still-valid parse-side imports (`SymbolTable`, `Data\WorkflowSymbols`, `Enum\ReferenceKind`) untouched.

- [ ] **Step 7: Migrate `document`/`runner`/`ci`/`laravel`/`core` src `use` statements**

Apply the C0-era FQCN remap table to downstream source:

```bash
# Document is parse-only: only eval-side EXCEPTION of imports; run for runner/cli/laravel/core.
# Bullet-proof approach: run the same sed family used in C0 Step 7 over packages/runner/src,
# packages/cli/src, packages/laravel/src, packages/core/src (src only — NOT document/src,
# which uses the seam instead).
find packages/runner/src packages/cli/src packages/laravel/src packages/core/src -name '*.php' -exec sed -i '' \
  -e 's/Alama\\Arazzo\\Expression\\Evaluation\\/Alama\\Arazzo\\Evaluation\\/g' \
  -e 's/Alama\\Arazzo\\Expression\\Data\\EvaluationInput/Alama\\Arazzo\\Evaluation\\Data\\EvaluationInput/g' \
  -e 's/Alama\\Arazzo\\Expression\\Interfaces\\(EvaluationInputInterface|ExpressionEvaluatorInterface|ExpressionResolverInterface)/Alama\\Arazzo\\Evaluation\\Interfaces\\\1/g' \
  -e 's/Alama\\Arazzo\\Expression\\Xpath\\/Alama\\Arazzo\\Evaluation\\Xpath\\/g' \
  -e 's/Alama\\Arazzo\\Expression\\Exceptions\\SelectorEvaluationException/Alama\\Arazzo\\Evaluation\\Exceptions\\SelectorEvaluationException/g' \
  -e 's/Alama\\Arazzo\\Expression\\(ExpressionEngine|ExpressionEngineInterface|ExpressionEvaluator|SelectorEvaluator|StringInterpolator|JsonPointer|JsonPathEvaluator)\b/Alama\\Arazzo\\Evaluation\\\1/g' \
  {} +
```

(BSD `sed -i ''` on macOS; `sed -i` on Linux.) Then `rg -n 'use Alama\\Arazzo\\Expression\\' packages/runner/src packages/cli/src packages/laravel/src packages/core/src` should only show parse-side imports (`SymbolTable`, `ReferenceKind`, `WorkflowSymbols`, `Interfaces\ExpressionInterface`, etc.). Fix any remaining eval-side imports by hand.

- [ ] **Step 8: Relocate `document` tests that exercise evaluation classes**

Move to `packages/evaluation/tests/` (they test eval behavior, not validation):

```bash
mkdir -p packages/evaluation/tests/Resolver
mkdir -p packages/evaluation/tests/Resolver/Xpath
git mv packages/document/tests/Resolver/SelectorEvaluatorTest.php packages/evaluation/tests/Resolver/SelectorEvaluatorTest.php
git mv packages/document/tests/Resolver/Xpath/DomXpathEvaluatorTest.php packages/evaluation/tests/Resolver/Xpath/DomXpathEvaluatorTest.php
```

Then migrate their FQCN imports per the C4 remap table (`XpathEvaluator`/`DomXpathEvaluator` → `Alama\Arazzo\Evaluation\Xpath\…`, `EvaluationContext` → `Alama\Arazzo\Evaluation\Data\EvaluationContext`, etc.).

- [ ] **Step 9: Update code that constructs the engine via `new ExpressionEngine()` in tests**

Sweep all packages' tests for `new ExpressionEngine(` / `ExpressionEngine::` and replace the FQCN with `Alama\Arazzo\Evaluation\ExpressionEngine`. In `document` tests, engine construction should switch to `ExpressionInspector` wherever the seam is under test (`ExpressionSeamTest.php` etc.).

- [ ] **Step 10: Regenerate autoloader and run all gates**

Run: `composer dump-autoload && composer run test-expression && composer run test-evaluation && composer run test-document && composer run test-runner && composer run test-cli && composer run test-core && composer run test-laravel` (repo root)

Expected: PASS across all packages.

- [ ] **Step 11: Run static analysis on all packages**

Run: `composer run analyse-expression && composer run analyse-evaluation && composer run analyse-document && composer run analyse-runner && composer run analyse-cli && composer run analyse-core && composer run analyse-laravel` (repo root)

Expected: PASS. Update phpstan baselines where the split moved symbols; every moved class resolves under its new FQCN.

- [ ] **Step 12: Commit**

```bash
git add packages/document/composer.json packages/runner/composer.json packages/laravel/composer.json packages/cli/composer.json packages/core/composer.json packages/document/src composer.json packages/document/tests/Resolver packages/evaluation/tests
git commit -m "feat(evaluation): migrate downstream packages to Alama\Arazzo\Evaluation namespace; document opts into parse-only seam"
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
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEngine')
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEngineInterface')
    ->not->toUse('Alama\Arazzo\Evaluation\ExpressionEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\SelectorEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\StringInterpolator')
    ->not->toUse('Alama\Arazzo\Evaluation\JsonPointer')
    ->not->toUse('Alama\Arazzo\Evaluation\JsonPathEvaluator')
    ->not->toUse('Alama\Arazzo\Evaluation\Xpath');

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
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Illuminate');

arch('evaluation does not leak document/runner internals')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Document\Validator');

arch('evaluation consumes only the parse-side seam from arazzo-expression')
    ->expect('Alama\Arazzo\Evaluation')
    ->toUse('Alama\Arazzo\Expression\Interfaces\ExpressionInterface')
    ->not->toUse('Alama\Arazzo\Expression\Lexer')
    ->not->toUse('Alama\Arazzo\Expression\Parser')
    ->not->toUse('Alama\Arazzo\Expression\SymbolTable')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionInspector');
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

- [x] **Step 1: Run the full test suite**

Run: `composer run test` (repo root)

Expected: PASS (all packages: contracts, expression, evaluation, document, runner, cli, core, laravel).

- [x] **Step 2: Run static analysis**

Run: `composer run analyse` (repo root)

Expected: PASS (all packages). If PHPStan reports "Class X not found" in evaluation, check that `scanDirectories` in its `phpstan.neon.dist` includes the sibling package `src/` dirs (`../contracts/src`, `../expression/src`).

- [x] **Step 3: Run the formatter check**

Run: `vendor/bin/pint --test` (repo root)

Expected: PASS. If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [x] **Step 4: Run the repo-wide gate**

Run: `make verify` (repo root)

Expected: PASS — confirms the split does not break any composed workflow.

- [x] **Step 5: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [x] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Phase C section, and add:

```markdown
Phase C status: ✅ Implemented 2026-09-09 — see `plans/2026-09-08-phase-c-evaluator-plugins.md`.
```

- [x] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md docs/superpowers/plans/2026-09-08-phase-c-evaluator-plugins.md
git commit -m "docs: mark Phase C expression split + evaluator plugin registries complete"
```
