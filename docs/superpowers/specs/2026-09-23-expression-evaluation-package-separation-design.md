# Architectural Specification: Expression and Evaluation Package Separation

- **Status**: Approved
- **Author**: Antigravity & Mohammed Alama
- **Date**: 2026-09-23
- **Topic**: Clear Domain Separation between `alama/arazzo-expression` and `alama/arazzo-evaluation`

---

## 1. Executive Summary & Problem Statement

During the modularization of `php-arazzo`, domain concepts between expression parsing/static analysis and runtime
evaluation were partially intertwined:

1. **Misplaced Facade & Domain Leak**: A class named `ExpressionEngine` was placed inside `packages/evaluation`,
   implementing an interface that exposed both static parsing/inspection (`parseExpression`, `expressionReferences`,
   `buildSymbolTable`) and runtime dynamic evaluation (`evaluate`, `evaluateCriteria`, `interpolate`, etc.).
2. **Duplicated AST Mapping**: `ExpressionEngine` inside `packages/evaluation` maintained a private `referenceFor()`
   method that duplicated AST reference extraction already present in `packages/expression`.
3. **Imbalanced Inbound Coupling**: `packages/document` (responsible for document loading, parsing, and static
   validation) imported and depended on `Alama\Arazzo\Evaluation\ExpressionEngineInterface` and
   `alama/arazzo-evaluation`, violating the diamond foundation where `document` has zero dependency on runtime
   evaluation.

This specification formalizes the architectural separation between static syntax/grammar analysis (
`alama/arazzo-expression`) and dynamic runtime evaluation (`alama/arazzo-evaluation`), ensuring symmetrical facade
naming, zero domain leakage, and clean dependency edges.

---

## 2. Package Topology & Boundaries

```
                         ┌───────────────────────────┐
                         │   alama/arazzo-contracts  │
                         │    Spec DTOs & Grammar    │
                         └─────────────▲─────────────┘
                                       │
                    ┌──────────────────┴──────────────────┐
                    │                                     │
         ┌──────────┴──────────┐               ┌──────────┴──────────┐
         │ alama/arazzo-document│               │alama/arazzo-expression│
         │ Parsing & Validation│               │  Syntax, AST, Lexer │
         │   (Static Domain)   │               │   (Static Domain)   │
         └──────────▲──────────┘               └──────────▲──────────┘
                    │                                     │
                    │                          ┌──────────┴──────────┐
                    │                          │alama/arazzo-evaluation
                    │                          │   Runtime Engine    │
                    │                          │  (Dynamic Domain)   │
                    │                          └──────────▲──────────┘
                    │                                     │
                    └──────────────────┬──────────────────┘
                                       │
                          ┌────────────┴────────────┐
                          │   alama/arazzo-runner   │
                          │   Execution Engine      │
                          └────────────▲────────────┘
                                       │
                         ┌─────────────┴─────────────┐
                         │      alama/arazzo-cli     │
                         │    alama/laravel-arazzo   │
                         └───────────────────────────┘
```

### Layer Rules:

- **`alama/arazzo-expression`**: Static syntax, Lexer, Parser, AST nodes, Symbol Table, and static reference inspection.
    - Allowed dependencies: `alama/arazzo-contracts`.
    - Disallowed dependencies: `alama/arazzo-evaluation`, `alama/arazzo-document`, `alama/arazzo-runner`.
- **`alama/arazzo-evaluation`**: Runtime dynamic evaluation of expressions against context, condition evaluation,
  criteria matching, selectors (JSONPath, XPath, JSON Pointer), string interpolation, and payload replacement.
    - Allowed dependencies: `alama/arazzo-contracts`, `alama/arazzo-expression`.
    - Disallowed dependencies: `alama/arazzo-document`, `alama/arazzo-runner`.
- **`alama/arazzo-document`**: Document parsing and static schema/expression validation.
    - Allowed dependencies: `alama/arazzo-contracts`, `alama/arazzo-expression`.
    - Disallowed dependencies: `alama/arazzo-evaluation`.
- **`alama/arazzo-runner`**: Workflow execution engine.
    - Allowed dependencies: `alama/arazzo-contracts`, `alama/arazzo-document`, `alama/arazzo-evaluation`.

---

## 3. Detailed Component & Interface Design

### 3.1 `packages/expression` (The Static Domain Seam)

#### Interface: `Alama\Arazzo\Expression\ExpressionEngineInterface`

```php
namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

interface ExpressionEngineInterface
{
    /**
     * Parse an expression string and return any syntax error, or null if valid.
     */
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    /**
     * Statically inspect what an expression references ($steps, $inputs, $workflows, etc.).
     */
    public function expressionReferences(string $raw): ?ExpressionReference;

    /**
     * Build the symbol table describing a document's declared workflows, steps, and components.
     */
    public function buildSymbolTable(ArazzoDocument $document): SymbolTable;
}
```

#### Facade: `Alama\Arazzo\Expression\ExpressionEngine`

* Implements `Alama\Arazzo\Expression\ExpressionEngineInterface`.
* Replaces / encapsulates `ExpressionInspector`.
* Directly delegates to internal `ExpressionParser` and `SymbolTable::build($document)`.

---

### 3.2 `packages/evaluation` (The Runtime Domain Seam)

#### Interface: `Alama\Arazzo\Evaluation\EvaluationEngineInterface`

```php
namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;

interface EvaluationEngineInterface
{
    /**
     * Evaluate an Arazzo expression against a runtime evaluation context.
     */
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;

    /**
     * Evaluate a list of success criteria against the current workflow step.
     *
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a step's declared success criteria (default 2xx when applicable).
     */
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a selector (JSONPath, JSON Pointer, XPath) against the workflow context.
     */
    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed;

    /**
     * Query an XPath selector against an XML string or DOM node.
     */
    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed;

    /**
     * @return list<string> Supported XPath versions, e.g. ['xpath-10'].
     */
    public function supportedXPathVersions(): array;

    /**
     * Interpolate {$...} expression references within a string against the workflow context.
     */
    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string;

    /**
     * Apply a step's payload replacements to an array-shaped body.
     *
     * @param array<array-key, mixed> $body
     * @param callable(PayloadReplacement): mixed|null $resolveValue
     * @return array<array-key, mixed>
     */
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array;

    /**
     * Evaluate a JSONPath expression against data.
     *
     * @param array<array-key, mixed>|object $data
     */
    public function jsonPath(string $expression, array|object $data): mixed;

    /**
     * Resolve a JSON Pointer against an array-shaped document.
     *
     * @param array<array-key, mixed> $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed;
}
```

#### Facade: `Alama\Arazzo\Evaluation\EvaluationEngine`

* Implements `Alama\Arazzo\Evaluation\EvaluationEngineInterface`.
* Renamed from previous `Alama\Arazzo\Evaluation\ExpressionEngine`.
* Coordinates:
    * `ExpressionEvaluator` & `ExpressionEvaluatorRegistry`
    * `CriteriaEvaluator` & `CriterionEvaluatorRegistry`
    * `SelectorEvaluator` (JsonPath, JsonPointer, DomXpathEvaluator)
    * `StringInterpolator`
    * `PayloadReplacer`
* **Removed**:
    * No longer implements `ExpressionInterface`.
    * Removed `parseExpression()`, `expressionReferences()`, and `buildSymbolTable()`.
    * Removed internal `referenceFor()` AST translation method.

---

### 3.3 Consumer Updates

1. **`packages/document`**:
    * Update `composer.json` to remove `"alama/arazzo-evaluation": "@dev"`.
    * Update `Document.php`, `Validator.php`, `RuleSet.php`, and validation rules (`ExpressionSyntaxRule`,
      `ExpressionUnresolved*Rule`, etc.) to typehint `Alama\Arazzo\Expression\ExpressionEngineInterface`.
2. **`packages/runner`**:
    * Update `RunnerFacade.php`, `StepExecutor.php`, `RequestCompiler.php`, `StepOutcomeHandler.php`, and protocol
      executors to typehint `Alama\Arazzo\Evaluation\EvaluationEngineInterface`.
3. **`packages/laravel`**:
    * In `FacadeBindings.php` and `ResolverBindings.php`:
        * Bind `Alama\Arazzo\Expression\ExpressionEngineInterface` -> `Alama\Arazzo\Expression\ExpressionEngine`.
        * Bind `Alama\Arazzo\Evaluation\EvaluationEngineInterface` -> `Alama\Arazzo\Evaluation\EvaluationEngine`.

---

## 4. Architectural Fitness Functions (Pest Arch)

Boundary enforcement tests to ensure no leakage:

### `packages/document/tests/ArchTest.php`

```php
arch('document does not depend on evaluation package')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Evaluation');

arch('document consumes expression engine interface')
    ->expect('Alama\Arazzo\Document\Validator\Rules')
    ->toUse('Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface')
    ->not->toUse('Alama\Arazzo\Expression\Lexer')
    ->not->toUse('Alama\Arazzo\Expression\Parser');
```

### `packages/expression/tests/ArchTest.php`

```php
arch('expression does not use evaluation classes')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Evaluation');
```

### `packages/evaluation/tests/ArchTest.php`

```php
arch('evaluation does not leak document or runner internals')
    ->expect('Alama\Arazzo\Evaluation')
    ->not->toUse('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Runner');
```

---

## 5. Migration Checklist

1. Create `Alama\Arazzo\Expression\ExpressionEngineInterface` and `Alama\Arazzo\Expression\ExpressionEngine`.
2. Rename `Alama\Arazzo\Evaluation\ExpressionEngineInterface` to `EvaluationEngineInterface`.
3. Rename `Alama\Arazzo\Evaluation\ExpressionEngine` to `EvaluationEngine`, stripping static parsing methods and
   redundant AST mapping.
4. Update `packages/document`: remove `evaluation` from `composer.json`, switch imports to `ExpressionEngineInterface`.
5. Update `packages/runner`: switch imports from `Evaluation\ExpressionEngineInterface` to
   `Evaluation\EvaluationEngineInterface`.
6. Update `packages/laravel`: register container bindings for both interfaces.
7. Run complete test suites (`pest`) and static analysis (`phpstan`) across all packages.
