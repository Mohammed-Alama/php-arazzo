### Task 19: Expression validation rules (8 rules)

**Files:**
- Create: `src/Validation/Rules/ExpressionReferencesResolveRule.php` (implements 7 codes at once: `expr.syntax`, `expr.unresolved_input_ref`, `expr.unresolved_step_ref`, `expr.unresolved_workflow_ref`, `expr.unresolved_source_ref`, `expr.unresolved_component_ref`, `expr.context_misuse`)
- Create: `src/Validation/Rules/ExpressionJsonPointerSyntaxRule.php` (`expr.jsonpointer_syntax`)
- Create: `src/Validation/Support/ExpressionSite.php` — helper struct describing where an expression appeared in the doc (for path + context checks)
- Create: `src/Validation/Support/ExpressionWalker.php` — visits every `Expression` in the document and yields `ExpressionSite`s
- Create: `tests/Validation/Rules/ExpressionRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable + Expression AST + AST error surface.
- Produces:
  - `ExpressionSite(string $pointer, Expression $expression, WorkflowSymbols $workflow, ?string $currentStepId, string $context)` — `context` is one of: `parameters | requestBody | criteria | outputs | onSuccess | onFailure | wf.parameters | wf.outputs | components`.
  - `ExpressionWalker::walk(ArazzoDocument $doc, SymbolTable $symbols): iterable<ExpressionSite>`.
  - `ExpressionReferencesResolveRule` — dispatches errors per-code based on AST node type. Its `code()` returns `'expr.references'` as an umbrella; individual emitted errors use their fine-grained codes so `disabled: ['expr.unresolved_step_ref']` works.
  - `ExpressionJsonPointerSyntaxRule` — walks response/request `body#/…` parts of the AST and validates each pointer per RFC 6901 (starts with `/`, no unescaped `~` outside `~0`/`~1`).
- Since `expr.syntax` and friends emit under different codes than the rule's own `code()`, the `RuleSet::activeRules()` filter is against `Rule::code()` only. Fine-grained disabling of a sub-code happens by tests reading the collector's outputs; this is an accepted limitation of the umbrella rule. If a spec-conformance user needs to silence a specific `expr.*` sub-code, they must disable the umbrella `expr.references` and re-implement the parts they need — documented in the CLI docstring.
- **Correction:** the design section 7 catalog lists each `expr.*` code as its own rule. To honor that, split into 7 separate rule classes that each delegate to a shared `ExpressionAnalyzer` helper. Implement as follows:

- [ ] **Step 1: Implement ExpressionSite + ExpressionWalker (no test yet)**

`src/Validation/Support/ExpressionSite.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Support;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\WorkflowSymbols;

final readonly class ExpressionSite
{
    /** @param 'parameters'|'requestBody'|'criteria'|'outputs'|'onSuccess'|'onFailure'|'wf.parameters'|'wf.outputs'|'components' $context */
    public function __construct(
        public string $pointer,
        public Expression $expression,
        public ?WorkflowSymbols $workflow,
        public ?string $currentStepId,
        public string $context,
    ) {}
}
```

`src/Validation/Support/ExpressionWalker.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Support;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\SymbolTable;

final class ExpressionWalker
{
    /**
     * @return iterable<ExpressionSite>
     */
    public function walk(ArazzoDocument $doc, SymbolTable $symbols): iterable
    {
        foreach ($doc->workflows as $wi => $wf) {
            $syms = $symbols->workflows[$wf->workflowId] ?? null;

            foreach ($wf->parameters as $pi => $p) {
                if ($p->value instanceof Expression) {
                    yield new ExpressionSite(
                        "/workflows/{$wi}/parameters/{$pi}/value", $p->value, $syms, null, 'wf.parameters',
                    );
                }
            }
            foreach ($wf->outputs as $name => $expr) {
                yield new ExpressionSite(
                    "/workflows/{$wi}/outputs/{$name}", $expr, $syms, null, 'wf.outputs',
                );
            }

            foreach ($wf->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
                    if ($p->value instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/value", $p->value, $syms, $s->stepId, 'parameters',
                        );
                    }
                }
                if ($s->requestBody !== null) {
                    if ($s->requestBody->payload instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/requestBody/payload", $s->requestBody->payload, $syms, $s->stepId, 'requestBody',
                        );
                    }
                    foreach ($s->requestBody->replacements as $ri => $r) {
                        if ($r->value instanceof Expression) {
                            yield new ExpressionSite(
                                "/workflows/{$wi}/steps/{$si}/requestBody/replacements/{$ri}/value", $r->value, $syms, $s->stepId, 'requestBody',
                            );
                        }
                    }
                }
                foreach ($s->successCriteria as $ci => $c) {
                    if ($c->context !== null && str_starts_with($c->context, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/context", new Expression($c->context), $syms, $s->stepId, 'criteria',
                        );
                    }
                    if (str_starts_with($c->condition, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/condition", new Expression($c->condition), $syms, $s->stepId, 'criteria',
                        );
                    }
                }
                foreach ($s->outputs as $name => $expr) {
                    yield new ExpressionSite(
                        "/workflows/{$wi}/steps/{$si}/outputs/{$name}", $expr, $syms, $s->stepId, 'outputs',
                    );
                }
            }
        }
    }
}
```

- [ ] **Step 2: Write failing test**

Create `tests/Validation/Rules/ExpressionRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExpressionContextMisuseRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedComponentRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedInputRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedSourceRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedStepRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedWorkflowRefRule;

function stepE(string $id, array $params = [], array $outs = []): Step {
    return new Step($id, null, 'op', null, null, $params, null, [], [], [], $outs);
}

function docE(array $params = [], array $outs = [], ?array $inputs = ['type'=>'object','properties'=>['userId'=>['type'=>'string']]], array $sources = [], array $deps = []): ArazzoDocument {
    $steps = [stepE('fetch', $params, $outs)];
    $wf = new Workflow('main', null, null, $inputs, $deps, $steps, [], [], [], []);
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, [$wf], new Components([], [], [], []), []);
}

it('flags syntactically bad expression', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$broken'))]);
    $ec = new ErrorCollector();
    (new ExpressionSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.syntax');
});

it('flags unresolved input ref', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$inputs.ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedInputRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.unresolved_input_ref');
});

it('flags forward step ref', function (): void {
    $s1 = new Step('first', null, 'op', null, null, [new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}'))], null, [], [], [], []);
    $s2 = new Step('second', null, 'op', null, null, [], null, [], [], [], ['y' => new Expression('{$response.body}')]);
    $wf = new Workflow('main', null, null, null, [], [$s1, $s2], [], [], [], []);
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$wf], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags workflow ref not in dependsOn', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$workflows.other.outputs.y}')]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedWorkflowRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved source ref', function (): void {
    $doc = docE(params: [new Parameter('u', ParameterIn::Header, new Expression('{$sourceDescriptions.ghost.url}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedSourceRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved component ref', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$components.parameters.Ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedComponentRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags $response used in parameters', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$response.body}'))]);
    $ec = new ErrorCollector();
    (new ExpressionContextMisuseRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad json pointer segment', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$response.body#/a~9}')]);
    $ec = new ErrorCollector();
    (new ExpressionJsonPointerSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 3: Run — expect fail**

- [ ] **Step 4: Implement rule classes**

`src/Validation/Rules/ExpressionSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionSyntaxRule implements Rule
{
    public function code(): string { return 'expr.syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                $errors->error($this->code(), $ast->getMessage(), $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedInputRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedInputRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_input_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof InputRef) continue;

            $inputs = $site->workflow?->inputs ?? [];
            $params = $site->workflow?->parameters ?? [];
            if (!isset($inputs[$ast->name]) && !isset($params[$ast->name])) {
                $errors->error(
                    $this->code(),
                    "Expression references unknown input '{$ast->name}'.",
                    $site->pointer,
                );
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedStepRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_step_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof StepRef) continue;

            $syms = $site->workflow;
            if ($syms === null) continue;
            $target = $syms->stepsById[$ast->stepId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown step '{$ast->stepId}'.", $site->pointer);
                continue;
            }
            if ($site->currentStepId !== null) {
                $currentIdx = $syms->stepsById[$site->currentStepId]->index ?? PHP_INT_MAX;
                if ($target->index >= $currentIdx) {
                    $errors->error($this->code(), "Expression references step '{$ast->stepId}' which is not before the current step.", $site->pointer);
                    continue;
                }
            }
            if ($ast->part instanceof OutputPart && !isset($target->outputs[$ast->part->name])) {
                $errors->error($this->code(), "Step '{$ast->stepId}' does not declare output '{$ast->part->name}'.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedWorkflowRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\WorkflowRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedWorkflowRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_workflow_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof WorkflowRef) continue;

            $target = $symbols->workflows[$ast->workflowId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown workflow '{$ast->workflowId}'.", $site->pointer);
                continue;
            }
            if ($site->workflow !== null && !isset($site->workflow->dependsOn[$ast->workflowId])) {
                $errors->error($this->code(), "Expression references workflow '{$ast->workflowId}' which is not in dependsOn.", $site->pointer);
                continue;
            }
            $bag = $ast->partKind === 'inputs' ? $target->inputs : $target->outputs;
            if (!isset($bag[$ast->name])) {
                $errors->error($this->code(), "Workflow '{$ast->workflowId}' has no {$ast->partKind}.{$ast->name}.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedSourceRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\SourceRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedSourceRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_source_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof SourceRef) continue;

            if (!isset($symbols->sourceDescriptions[$ast->name])) {
                $errors->error($this->code(), "Expression references unknown sourceDescription '{$ast->name}'.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedComponentRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedComponentRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_component_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof ComponentRef) continue;

            $bag = $symbols->components[$ast->type] ?? null;
            if ($bag === null || !isset($bag[$ast->name])) {
                $errors->error($this->code(), "Component reference '{$ast->type}.{$ast->name}' is not declared.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionContextMisuseRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionContextMisuseRule implements Rule
{
    public function code(): string { return 'expr.context_misuse'; }

    private const ALLOWED = ['criteria', 'outputs', 'onSuccess', 'onFailure'];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;

            $isRuntime = $ast instanceof HttpMetaRef
                || ($ast instanceof StepRef && ($ast->part instanceof RequestPart || $ast->part instanceof ResponsePart));

            if ($isRuntime && !in_array($site->context, self::ALLOWED, true)) {
                $errors->error(
                    $this->code(),
                    "Runtime reference (\$response/\$request/\$statusCode/\$url/\$method) is not valid in context '{$site->context}'.",
                    $site->pointer,
                );
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionJsonPointerSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionJsonPointerSyntaxRule implements Rule
{
    public function code(): string { return 'expr.jsonpointer_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof StepRef) continue;
            $part = $ast->part;
            if (!($part instanceof RequestPart) && !($part instanceof ResponsePart)) continue;
            $ptr = $part->jsonPointer;
            if ($ptr === null || $ptr === '') continue;

            // Per RFC 6901 an escaped ~ must be ~0 or ~1.
            $segments = explode('/', ltrim($ptr, '/'));
            foreach ($segments as $seg) {
                if (preg_match('/~(?![01])/', $seg) === 1) {
                    $errors->error($this->code(), "JSON Pointer '{$ptr}' contains illegal '~' escape.", $site->pointer);
                    break;
                }
            }
        }
    }
}
```

- [ ] **Step 5: Run — expect pass**

- [ ] **Step 6: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: expression validation rules (syntax, refs, context, json pointer)"
```

---

