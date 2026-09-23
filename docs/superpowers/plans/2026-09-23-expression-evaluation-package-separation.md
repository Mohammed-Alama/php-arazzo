# Expression and Evaluation Package Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish clean domain separation between `alama/arazzo-expression` (static syntax, parsing, AST, symbols) and `alama/arazzo-evaluation` (runtime dynamic evaluation against context), eliminating domain leaks, duplicate AST mapping, and decoupling `alama/arazzo-document` from `alama/arazzo-evaluation`.

**Architecture:** 
- `alama/arazzo-expression` exposes facade `Alama\Arazzo\Expression\ExpressionEngine` implementing `Alama\Arazzo\Expression\ExpressionEngineInterface` for syntax checks, symbol table compilation, and reference inspection.
- `alama/arazzo-evaluation` exposes facade `Alama\Arazzo\Evaluation\EvaluationEngine` implementing `Alama\Arazzo\Evaluation\EvaluationEngineInterface` for runtime evaluation, selectors, interpolation, and criteria.
- `alama/arazzo-document` drops `alama/arazzo-evaluation` dependency and consumes `Alama\Arazzo\Expression\ExpressionEngineInterface`.
- `alama/arazzo-runner` consumes `Alama\Arazzo\Evaluation\EvaluationEngineInterface`.
- `alama/laravel-arazzo` binds both interfaces in its container bindings.

**Tech Stack:** PHP 8.4, Pest PHP 5, PHPStan 2, Composer monorepo (path repositories).

---

### Task 1: Create `ExpressionEngineInterface` and `ExpressionEngine` in `packages/expression`

**Files:**
- Create: `packages/expression/src/ExpressionEngineInterface.php`
- Create: `packages/expression/src/ExpressionEngine.php`
- Test: `packages/expression/tests/ExpressionEngineTest.php`
- Modify: `packages/expression/tests/ArchTest.php`

- [ ] **Step 1: Write the failing unit test for `ExpressionEngine`**

Create `packages/expression/tests/ExpressionEngineTest.php`:
```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SpecVersion;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;
use Alama\Arazzo\Expression\SymbolTable;

it('implements ExpressionEngineInterface and ExpressionInterface', function () {
    $engine = new ExpressionEngine();

    expect($engine)->toBeInstanceOf(ExpressionEngineInterface::class)
        ->and($engine)->toBeInstanceOf(ExpressionInterface::class);
});

it('parses valid and invalid expressions', function () {
    $engine = new ExpressionEngine();

    expect($engine->parseExpression('$inputs.userId'))->toBeNull()
        ->and($engine->parseExpression('invalid expr'))->toBeInstanceOf(ExpressionSyntaxException::class);
});

it('projects expression references statically', function () {
    $engine = new ExpressionEngine();

    $ref = $engine->expressionReferences('$inputs.userId');
    expect($ref)->toBeInstanceOf(ExpressionReference::class)
        ->and($ref->kind)->toBe(ReferenceKind::Input)
        ->and($ref->name)->toBe('userId');

    expect($engine->expressionReferences('bad'))->toBeNull();
});

it('builds symbol table from document', function () {
    $engine = new ExpressionEngine();
    $doc = new ArazzoDocument(
        version: SpecVersion::V1_0_0,
        info: new Info('Test', '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
    );

    $table = $engine->buildSymbolTable($doc);
    expect($table)->toBeInstanceOf(SymbolTable::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
./vendor/bin/pest packages/expression/tests/ExpressionEngineTest.php
```
Expected: FAIL with "Class Alama\Arazzo\Expression\ExpressionEngine not found"

- [ ] **Step 3: Implement `ExpressionEngineInterface` and `ExpressionEngine`**

Create `packages/expression/src/ExpressionEngineInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

/**
 * Entry-point seam for static Arazzo expression parsing and analysis.
 */
interface ExpressionEngineInterface
{
    /**
     * Parse an expression string. Returns null if valid, or the syntax exception if invalid.
     */
    public function parseExpression(string $raw): ?ExpressionSyntaxException;

    /**
     * Statically inspect what an expression references. Returns null on syntax error.
     */
    public function expressionReferences(string $raw): ?ExpressionReference;

    /**
     * Build the symbol table describing a document's declared workflows, steps, and components.
     */
    public function buildSymbolTable(ArazzoDocument $document): SymbolTable;
}
```

Create `packages/expression/src/ExpressionEngine.php`:
```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

/**
 * Concrete expression facade for static parsing, reference projection, and symbol table generation.
 */
final class ExpressionEngine implements ExpressionEngineInterface, ExpressionInterface
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

- [ ] **Step 4: Update `packages/expression/tests/ArchTest.php`**

Ensure `ArchTest.php` permits `Alama\Arazzo\Expression\ExpressionEngine` while maintaining architectural boundaries.

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
./vendor/bin/pest packages/expression
```
Expected: PASS with all tests passing.

- [ ] **Step 6: Commit changes**

```bash
git add packages/expression/src/ExpressionEngineInterface.php packages/expression/src/ExpressionEngine.php packages/expression/tests/ExpressionEngineTest.php packages/expression/tests/ArchTest.php
git commit -m "feat(expression): add ExpressionEngine and ExpressionEngineInterface facade"
```

---

### Task 2: Refactor `packages/evaluation` to `EvaluationEngine`

**Files:**
- Create: `packages/evaluation/src/EvaluationEngineInterface.php`
- Create: `packages/evaluation/src/EvaluationEngine.php`
- Delete: `packages/evaluation/src/ExpressionEngineInterface.php`
- Delete: `packages/evaluation/src/ExpressionEngine.php`
- Test: `packages/evaluation/tests/EvaluationEngineTest.php` (renamed from `ExpressionEngineTest.php`)
- Test: `packages/evaluation/tests/EvaluationEngineCapabilitiesTest.php` (renamed from `ExpressionEngineCapabilitiesTest.php`)
- Modify: `packages/evaluation/tests/PackageScaffoldTest.php`
- Modify: `packages/evaluation/tests/ArchTest.php`

- [ ] **Step 1: Create `EvaluationEngineInterface.php`**

Create `packages/evaluation/src/EvaluationEngineInterface.php`:
```php
<?php

declare(strict_types=1);

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

/**
 * Entry-point seam for the runtime evaluation package.
 *
 * Downstream packages depend on this interface. It exposes dynamic evaluation:
 * evaluating expressions against state, criteria evaluation, selectors,
 * string interpolation, payload replacement, JSONPath, JSON Pointer, and XPath queries.
 */
interface EvaluationEngineInterface
{
    /**
     * Evaluate an Arazzo expression against a run context.
     */
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;

    /**
     * Evaluate a list of success criteria against the current workflow step.
     *
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a step's declared success criteria (2xx default when applicable).
     */
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a selector (JSONPath, JSON-pointer or XPath) against the workflow context.
     */
    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed;

    /**
     * Query an XPath selector against an XML string or DOM node.
     */
    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed;

    /**
     * @return list<string> Supported XPath version tokens, e.g. ['xpath-10'].
     */
    public function supportedXPathVersions(): array;

    /**
     * Interpolate {$...} expression references within a string against the workflow context.
     */
    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string;

    /**
     * Apply a step's payload replacements to an array-shaped body.
     *
     * @param  array<array-key, mixed>  $body
     * @param  callable(PayloadReplacement): mixed|null  $resolveValue
     * @return array<array-key, mixed>
     */
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array;

    /**
     * Evaluate a JSONPath expression against data.
     *
     * @param  array<array-key, mixed>|object  $data
     */
    public function jsonPath(string $expression, array|object $data): mixed;

    /**
     * Resolve a JSON Pointer against an array-shaped document.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed;
}
```

- [ ] **Step 2: Create `EvaluationEngine.php` with cleaned domain responsibilities**

Create `packages/evaluation/src/EvaluationEngine.php` (stripping `parseExpression`, `expressionReferences`, `buildSymbolTable`, and `referenceFor`):
```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Registries\ExpressionEvaluatorRegistry;
use Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator;

/**
 * Concrete evaluation facade.
 *
 * Hides runtime evaluation collaborators (expression evaluator, criteria evaluator,
 * selector evaluator, string interpolator, payload replacer) behind a single entry point.
 */
final class EvaluationEngine implements EvaluationEngineInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
        private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
        private readonly ExpressionEvaluatorRegistry $expressionRegistry = new ExpressionEvaluatorRegistry(),
        private readonly CriterionEvaluatorRegistry $criterionRegistry = new CriterionEvaluatorRegistry(),
    ) {}

    private ?CriteriaEvaluator $criteriaEvaluator = null;

    private ?SelectorEvaluator $selectorEvaluator = null;

    private ?StringInterpolator $interpolator = null;

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        $plugin = $this->expressionRegistry->resolve($expression);
        if ($plugin !== null) {
            return $plugin->evaluate($expression, $context);
        }

        return $this->evaluator->evaluate($expression, $context);
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateCriteria($criteria, $step, $context, $document);
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $this->criteria()->evaluateSuccessCriteria($step, $context, $document);
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return $this->selectors()->evaluate($selector, $context, $stepId);
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return $this->xpath->query($rootValue, $selector, $version);
    }

    public function supportedXPathVersions(): array
    {
        return $this->xpath->supportedVersions();
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $this->interpolator()->interpolate($value, $context, $stepId);
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return (new PayloadReplacer($this->selectors()))->replace($step, $body, $resolveValue, $context);
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return (new JsonPathEvaluator())->evaluate($expression, $data);
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return JsonPointer::resolve($data, $pointer);
    }

    private function criteria(): CriteriaEvaluator
    {
        return $this->criteriaEvaluator ??= new CriteriaEvaluator($this->selectors(), $this->criterionRegistry);
    }

    private function selectors(): SelectorEvaluator
    {
        return $this->selectorEvaluator ??= new SelectorEvaluator($this->xpath, $this->evaluator);
    }

    private function interpolator(): StringInterpolator
    {
        return $this->interpolator ??= new StringInterpolator(new InterpolationResolver($this->evaluator));
    }
}
```

- [ ] **Step 3: Delete legacy `ExpressionEngine.php` and `ExpressionEngineInterface.php` from `packages/evaluation`**

Remove:
- `packages/evaluation/src/ExpressionEngine.php`
- `packages/evaluation/src/ExpressionEngineInterface.php`

- [ ] **Step 4: Update evaluation tests and arch tests**

Rename and update:
- `packages/evaluation/tests/ExpressionEngineTest.php` -> `packages/evaluation/tests/EvaluationEngineTest.php`
- `packages/evaluation/tests/ExpressionEngineCapabilitiesTest.php` -> `packages/evaluation/tests/EvaluationEngineCapabilitiesTest.php`
- Update `packages/evaluation/tests/PackageScaffoldTest.php` to verify `EvaluationEngine`.
- Update `packages/evaluation/tests/ArchTest.php` to verify `EvaluationEngine` does not leak into document or runner.

- [ ] **Step 5: Run evaluation tests**

Run:
```bash
./vendor/bin/pest packages/evaluation
```
Expected: PASS with all tests passing.

- [ ] **Step 6: Commit changes**

```bash
git add packages/evaluation/
git commit -m "refactor(evaluation): rename to EvaluationEngine and decouple from static parsing"
```

---

### Task 3: Decouple `packages/document` from `alama/arazzo-evaluation`

**Files:**
- Modify: `packages/document/composer.json`
- Modify: `packages/document/src/Document.php`
- Modify: `packages/document/src/Validator/Validator.php`
- Modify: `packages/document/src/Validator/RuleSet.php`
- Modify: `packages/document/src/Validator/PreflightValidator.php`
- Modify: `packages/document/src/Validator/Rules/*.php` (8 expression rules)
- Modify: `packages/document/tests/Validator/*.php`
- Modify: `packages/document/tests/ArchTest.php`

- [ ] **Step 1: Remove `alama/arazzo-evaluation` from `packages/document/composer.json`**

In `packages/document/composer.json`, remove:
`"alama/arazzo-evaluation": "@dev",`

- [ ] **Step 2: Update `PreflightValidator.php`**

In `packages/document/src/Validator/PreflightValidator.php`:
Change constructor from receiving `ExpressionEngineInterface $engine` to `array $supportedXPathVersions = ['xpath-10']` (or optional parameter). Update line 255 to check against `$this->supportedXPathVersions`.

- [ ] **Step 3: Update `RuleSet.php`, `Validator.php`, and `Document.php`**

Replace:
`use Alama\Arazzo\Evaluation\ExpressionEngineInterface;`
with:
`use Alama\Arazzo\Expression\ExpressionEngineInterface;`
and in `Document.php`:
`use Alama\Arazzo\Expression\ExpressionEngine;`

- [ ] **Step 4: Update the 8 expression validation rules**

In each of the following files, update imports from `Alama\Arazzo\Evaluation\ExpressionEngineInterface` to `Alama\Arazzo\Expression\ExpressionEngineInterface`:
- `packages/document/src/Validator/Rules/ExpressionSyntaxRule.php`
- `packages/document/src/Validator/Rules/ExpressionContextMisuseRule.php`
- `packages/document/src/Validator/Rules/ExpressionJsonPointerSyntaxRule.php`
- `packages/document/src/Validator/Rules/ExpressionUnresolvedComponentRefRule.php`
- `packages/document/src/Validator/Rules/ExpressionUnresolvedInputRefRule.php`
- `packages/document/src/Validator/Rules/ExpressionUnresolvedSourceRefRule.php`
- `packages/document/src/Validator/Rules/ExpressionUnresolvedStepRefRule.php`
- `packages/document/src/Validator/Rules/ExpressionUnresolvedWorkflowRefRule.php`

- [ ] **Step 5: Update document tests and add Pest Arch rule**

Update test files in `packages/document/tests/Validator/` to use `Alama\Arazzo\Expression\ExpressionEngine`.
Add arch test in `packages/document/tests/ArchTest.php`:
```php
arch('document does not depend on evaluation package')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Evaluation');
```

- [ ] **Step 6: Run document test suite**

Run:
```bash
./vendor/bin/pest packages/document
```
Expected: PASS with 0 dependencies on `alama/arazzo-evaluation`.

- [ ] **Step 7: Commit changes**

```bash
git add packages/document/
git commit -m "refactor(document): decouple document package from evaluation"
```

---

### Task 4: Update `packages/runner` to Consume `EvaluationEngineInterface`

**Files:**
- Modify: `packages/runner/src/RunnerFacade.php`
- Modify: `packages/runner/src/RunnerGraphBuilder.php`
- Modify: `packages/runner/src/Execution/ExecutionGraphFactory.php`
- Modify: `packages/runner/src/Execution/StepExecutor.php`
- Modify: `packages/runner/src/Execution/RequestCompiler.php`
- Modify: `packages/runner/src/Execution/StepOutcomeHandler.php`
- Modify: `packages/runner/src/Execution/StepOutputExtractor.php`
- Modify: `packages/runner/src/Execution/ExecutionExpressionResolver.php`
- Modify: `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php`
- Modify: `packages/runner/src/Execution/SubWorkflowInvoker.php`
- Modify: `packages/runner/src/Protocol/HttpStepExecutor.php`
- Modify: `packages/runner/src/Protocol/AsyncApiStepExecutor.php`
- Modify: `packages/runner/src/Protocol/SubWorkflowStepExecutor.php`
- Modify: `packages/runner/tests/*.php`

- [ ] **Step 1: Replace imports in `packages/runner/src/`**

Find all occurrences in `packages/runner/src/`:
`use Alama\Arazzo\Evaluation\ExpressionEngineInterface;`
Replace with:
`use Alama\Arazzo\Evaluation\EvaluationEngineInterface;`

Update property and parameter typehints from `ExpressionEngineInterface` to `EvaluationEngineInterface`.

- [ ] **Step 2: Update test doubles and mocks in `packages/runner/tests/`**

Update `packages/runner/tests/` where `ExpressionEngineInterface` or `ExpressionEngine` was mocked or instantiated to use `EvaluationEngineInterface` / `EvaluationEngine`.

- [ ] **Step 3: Run runner test suite**

Run:
```bash
./vendor/bin/pest packages/runner
```
Expected: PASS.

- [ ] **Step 4: Commit changes**

```bash
git add packages/runner/
git commit -m "refactor(runner): consume EvaluationEngineInterface instead of ExpressionEngineInterface"
```

---

### Task 5: Update `packages/laravel` Container Bindings

**Files:**
- Modify: `packages/laravel/src/Bindings/FacadeBindings.php`
- Modify: `packages/laravel/src/Bindings/ResolverBindings.php`
- Modify: `packages/laravel/tests/LaravelArazzoServiceProviderBindingsTest.php`
- Modify: `packages/laravel/tests/Bindings/FacadeBindingsTest.php` (if exists) / `ResolverBindingsTest.php`

- [ ] **Step 1: Update `FacadeBindings.php`**

In `packages/laravel/src/Bindings/FacadeBindings.php`:
- Import `Alama\Arazzo\Expression\ExpressionEngineInterface` and `Alama\Arazzo\Expression\ExpressionEngine`.
- Import `Alama\Arazzo\Evaluation\EvaluationEngineInterface` and `Alama\Arazzo\Evaluation\EvaluationEngine`.
- Bind both facades:
```php
$app->singleton(ExpressionEngineInterface::class, fn (): ExpressionEngine => new ExpressionEngine());
$app->singleton(EvaluationEngineInterface::class, fn (): EvaluationEngine => new EvaluationEngine());
$app->singleton(DocumentInterface::class, fn (): Document => new Document());
$app->singleton(RunnerFacadeInterface::class, fn (): RunnerFacade => new RunnerFacade(
    $app->make(DocumentInterface::class),
    $app->make(EvaluationEngineInterface::class),
));
$app->singleton(RunnerGraphBuilderInterface::class, function (Container $app): RunnerGraphBuilder {
    return new RunnerGraphBuilder(
        $app->make(DocumentInterface::class),
        $app->make(EvaluationEngineInterface::class),
        $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
    );
});
```

- [ ] **Step 2: Update `ResolverBindings.php`**

In `packages/laravel/src/Bindings/ResolverBindings.php`:
Update `PreflightValidator` resolution to pass `['xpath-10']` or use the updated constructor.

- [ ] **Step 3: Update Laravel tests**

Update `packages/laravel/tests/LaravelArazzoServiceProviderBindingsTest.php` and `ResolverBindingsTest.php` to verify `ExpressionEngineInterface` and `EvaluationEngineInterface` bindings.

- [ ] **Step 4: Run Laravel test suite**

Run:
```bash
./vendor/bin/pest packages/laravel
```
Expected: PASS.

- [ ] **Step 5: Commit changes**

```bash
git add packages/laravel/
git commit -m "refactor(laravel): bind ExpressionEngineInterface and EvaluationEngineInterface"
```

---

### Task 6: Add Architectural Fitness Functions & Update CONTEXT-MAP

**Files:**
- Modify: `CONTEXT-MAP.md`
- Modify: `packages/expression/tests/ArchTest.php`
- Modify: `packages/evaluation/tests/ArchTest.php`
- Modify: `packages/document/tests/ArchTest.php`

- [ ] **Step 1: Update `CONTEXT-MAP.md`**

Update package topology table and descriptions in `CONTEXT-MAP.md` reflecting:
- `alama/arazzo-expression`: Facade `Alama\Arazzo\Expression\ExpressionEngine`, Interface `Alama\Arazzo\Expression\ExpressionEngineInterface`.
- `alama/arazzo-evaluation`: Facade `Alama\Arazzo\Evaluation\EvaluationEngine`, Interface `Alama\Arazzo\Evaluation\EvaluationEngineInterface`.
- `alama/arazzo-document`: Zero dependency on `evaluation`.

- [ ] **Step 2: Enforce Pest Arch tests across packages**

Ensure all 3 arch tests enforce:
1. `Document` cannot use `Evaluation`.
2. `Expression` cannot use `Evaluation`.
3. `Evaluation` cannot leak into `Document` or `Runner`.

- [ ] **Step 3: Commit documentation & arch test updates**

```bash
git add CONTEXT-MAP.md packages/expression/tests/ArchTest.php packages/evaluation/tests/ArchTest.php packages/document/tests/ArchTest.php
git commit -m "docs: update context map and arch fitness functions for expression/evaluation split"
```

---

### Task 7: Full Monorepo Quality Gates Verification

**Files:**
- None (verification across entire monorepo)

- [ ] **Step 1: Run complete Pest test suite**

Run:
```bash
./vendor/bin/pest
```
Expected: PASS (all tests pass across all packages).

- [ ] **Step 2: Run Pint code style fixer**

Run:
```bash
./vendor/bin/pint --test
```
Expected: PASS.

- [ ] **Step 3: Run PHPStan static analysis**

Run:
```bash
./vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
```
Expected: PASS (0 errors).
