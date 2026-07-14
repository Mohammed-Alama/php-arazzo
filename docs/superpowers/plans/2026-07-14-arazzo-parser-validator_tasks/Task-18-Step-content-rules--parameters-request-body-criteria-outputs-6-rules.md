### Task 18: Step content rules — parameters, request body, criteria, outputs (6 rules)

**Files:**
- Create: `src/Validation/Rules/StepParametersHaveNameRule.php`, `StepParameterInValidRule.php`, `StepRequestBodyReplacementsTargetRule.php`, `StepSuccessCriteriaConditionRule.php`, `StepCriteriaTypeContextRule.php`, `StepOutputsUniqueRule.php`
- Create: `tests/Validation/Rules/StepContentRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable.
- Produces: 6 rules with spec §7 codes.
- Notes:
  - `StepParametersHaveNameRule` — the DTO already requires `name`; this rule flags empty-string names.
  - `StepParameterInValidRule` — parser enforces enum; rule flags `parameters` at step level lacking `in` for non-body params. (Design decision: `in` optional means "body" by convention only when explicitly set; missing `in` on step params flags a warning.) v1: no-op (parser handles it) — reserve code for future.
  - `StepRequestBodyReplacementsTargetRule` — target must start with `/`.
  - `StepSuccessCriteriaConditionRule` — non-empty string (parser already enforces non-null; this checks for whitespace-only).
  - `StepCriteriaTypeContextRule` — when `type ∈ {jsonpath, xpath, regex}`, `context` must be set.
  - `StepOutputsUniqueRule` — parser uses assoc array; YAML dupes at load time collapse. In v1: no-op (reserve code). Same rationale as `SourceTypeMatchesRule`.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/StepContentRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepCriteriaTypeContextRule;
use Alama\LaravelArazzo\Validation\Rules\StepParametersHaveNameRule;
use Alama\LaravelArazzo\Validation\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\LaravelArazzo\Validation\Rules\StepSuccessCriteriaConditionRule;

function docFrom(Step $s): ArazzoDocument
{
    $w = new Workflow('w', null, null, null, [], [$s], [], [], [], []);
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('flags empty parameter name', function (): void {
    $step = new Step('x', null, 'op', null, null, [new Parameter('', ParameterIn::Query, 'v')], null, [], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepParametersHaveNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad replacement target', function (): void {
    $body = new RequestBody(null, [], [new PayloadReplacement('no-slash', 'v')]);
    $step = new Step('x', null, 'op', null, null, [], $body, [], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepRequestBodyReplacementsTargetRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags whitespace-only condition', function (): void {
    $crit = new SuccessCriterion(null, '   ', null);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags jsonpath criterion missing context', function (): void {
    $crit = new SuccessCriterion(null, '$.id != null', CriterionType::JsonPath);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepCriteriaTypeContextRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/StepParametersHaveNameRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepParametersHaveNameRule implements Rule
{
    public function code(): string { return 'step.parameters_have_name'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->parameters as $k => $p) {
                    if (trim($p->name) === '') {
                        $errors->error(
                            $this->code(),
                            "Parameter at index {$k} of step '{$s->stepId}' must have a non-empty name.",
                            "/workflows/{$i}/steps/{$j}/parameters/{$k}/name",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepParameterInValidRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/**
 * Enum enforcement lives at parse time. This rule reserves the code for
 * future semantic checks (e.g. warning when 'body' is used with non-POST ops).
 */
final class StepParameterInValidRule implements Rule
{
    public function code(): string { return 'step.parameter_in_valid'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1.
    }
}
```

`src/Validation/Rules/StepRequestBodyReplacementsTargetRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepRequestBodyReplacementsTargetRule implements Rule
{
    public function code(): string { return 'step.request_body_replacements_target'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->requestBody === null) continue;
                foreach ($s->requestBody->replacements as $k => $r) {
                    if ($r->target === '' || $r->target[0] !== '/') {
                        $errors->error(
                            $this->code(),
                            "PayloadReplacement target '{$r->target}' must be a JSON Pointer starting with '/'.",
                            "/workflows/{$i}/steps/{$j}/requestBody/replacements/{$k}/target",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepSuccessCriteriaConditionRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepSuccessCriteriaConditionRule implements Rule
{
    public function code(): string { return 'step.success_criteria_condition'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if (trim($c->condition) === '') {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}].condition must not be empty or whitespace.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepCriteriaTypeContextRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepCriteriaTypeContextRule implements Rule
{
    public function code(): string { return 'step.criteria_type_context'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $needsContext = [CriterionType::JsonPath, CriterionType::XPath, CriterionType::Regex];
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if ($c->type !== null && in_array($c->type, $needsContext, true) && ($c->context === null || trim($c->context) === '')) {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}] type '{$c->type->value}' requires a context expression.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/context",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOutputsUniqueRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/** YAML/JSON collapse duplicate keys at load time; reserved for future semantic checks. */
final class StepOutputsUniqueRule implements Rule
{
    public function code(): string { return 'step.outputs_unique'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: step content validation rules (parameters, body, criteria, outputs)"
```

---

