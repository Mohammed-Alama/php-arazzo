### Task 17: Step identity + operation-target rules (7 rules)

**Files:**
- Create: `src/Validation/Rules/StepAtLeastOneRule.php`, `StepUniqueIdRule.php`, `StepIdPatternRule.php`, `StepOperationTargetPresentRule.php`, `StepOperationIdSourceScopedRule.php`, `StepOperationPathSyntaxRule.php`, `StepNestedWorkflowExistsRule.php`
- Create: `tests/Validation/Rules/StepIdentityRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable.
- Produces: 7 rules with codes as listed in spec §7.
- Rules:
  - `StepAtLeastOneRule`: each workflow.steps non-empty.
  - `StepUniqueIdRule`: `stepId` unique within workflow.
  - `StepIdPatternRule`: `^[A-Za-z0-9_\-]+$`.
  - `StepOperationTargetPresentRule`: exactly one of `operationId`, `operationPath`, `workflowId` set.
  - `StepOperationIdSourceScopedRule`: if `operationId` unqualified (no `#`), require exactly one sourceDescription of type openapi; if qualified `sourceName#opId`, require `sourceName` to exist.
  - `StepOperationPathSyntaxRule`: `operationPath` must match `<source>#<json-pointer>` where source exists and pointer is RFC 6901.
  - `StepNestedWorkflowExistsRule`: `step.workflowId` (nested ref) resolves to a declared workflow.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/StepIdentityRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepAtLeastOneRule;
use Alama\LaravelArazzo\Validation\Rules\StepIdPatternRule;
use Alama\LaravelArazzo\Validation\Rules\StepNestedWorkflowExistsRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationIdSourceScopedRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationPathSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationTargetPresentRule;
use Alama\LaravelArazzo\Validation\Rules\StepUniqueIdRule;

function s(string $id, ?string $opId = 'op', ?string $opPath = null, ?string $wfId = null): Step
{
    return new Step($id, null, $opId, $opPath, $wfId, [], null, [], [], [], []);
}
function w(string $id, array $steps, array $dep = []): Workflow
{
    return new Workflow($id, null, null, null, $dep, $steps, [], [], [], []);
}
function d(array $wfs, array $sources = []): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, $wfs, new Components([], [], [], []), []);
}

it('flags empty step list', function (): void {
    $doc = d([w('a', [])]);
    $ec = new ErrorCollector();
    (new StepAtLeastOneRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate stepId', function (): void {
    $doc = d([w('a', [s('x'), s('x')])]);
    $ec = new ErrorCollector();
    (new StepUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad stepId pattern', function (): void {
    $doc = d([w('a', [s('bad!')])]);
    $ec = new ErrorCollector();
    (new StepIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('requires exactly one operation target', function (): void {
    $doc = d([w('a', [s('x', null, null, null)])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = d([w('a', [s('x', 'op', 'src#/paths/x/get')])]);
    $ec2 = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toHaveCount(1);
});

it('requires single openapi source for unqualified operationId', function (): void {
    $doc = d([w('a', [s('x', 'op')])], []);
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = d(
        [w('a', [s('x', 'src1#op')])],
        [new SourceDescription('src1', '/a', SourceType::Openapi)],
    );
    $ec2 = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toBe([]);
});

it('validates operationPath syntax', function (): void {
    $doc = d([w('a', [s('x', null, 'nosource-no-hash')])]);
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved nested workflow', function (): void {
    $doc = d([w('a', [s('x', null, null, 'ghost')])]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/StepAtLeastOneRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepAtLeastOneRule implements Rule
{
    public function code(): string { return 'step.at_least_one'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if ($w->steps === []) {
                $errors->error($this->code(), "workflow '{$w->workflowId}' must declare at least one step.", "/workflows/{$i}/steps");
            }
        }
    }
}
```

`src/Validation/Rules/StepUniqueIdRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepUniqueIdRule implements Rule
{
    public function code(): string { return 'step.unique_id'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            $seen = [];
            foreach ($w->steps as $j => $s) {
                if (isset($seen[$s->stepId])) {
                    $errors->error(
                        $this->code(),
                        "Duplicate stepId '{$s->stepId}' in workflow '{$w->workflowId}'.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
                $seen[$s->stepId] = true;
            }
        }
    }
}
```

`src/Validation/Rules/StepIdPatternRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepIdPatternRule implements Rule
{
    public function code(): string { return 'step.id_pattern'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if (preg_match('/^[A-Za-z0-9_\-]+$/', $s->stepId) !== 1) {
                    $errors->error(
                        $this->code(),
                        "stepId '{$s->stepId}' must match [A-Za-z0-9_-]+.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationTargetPresentRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationTargetPresentRule implements Rule
{
    public function code(): string { return 'step.operation_target_present'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $set = (int) ($s->operationId !== null) + (int) ($s->operationPath !== null) + (int) ($s->workflowId !== null);
                if ($set !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId (got {$set}).",
                        "/workflows/{$i}/steps/{$j}",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationIdSourceScopedRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationIdSourceScopedRule implements Rule
{
    public function code(): string { return 'step.operationid_source_scoped'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $openapiSources = array_values(array_filter(
            $doc->sourceDescriptions,
            fn($s) => $s->type === SourceType::Openapi,
        ));

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationId === null) continue;
                if (str_contains($s->operationId, '#')) {
                    [$src] = explode('#', $s->operationId, 2);
                    if (!isset($symbols->sourceDescriptions[$src])) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' operationId references unknown source '{$src}'.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                } else {
                    if (count($openapiSources) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->operationId}' but the document does not declare exactly one openapi sourceDescription.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationPathSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationPathSyntaxRule implements Rule
{
    public function code(): string { return 'step.operationpath_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationPath === null) continue;
                $path = "/workflows/{$i}/steps/{$j}/operationPath";
                if (!str_contains($s->operationPath, '#')) {
                    $errors->error($this->code(), "operationPath '{$s->operationPath}' must contain '#' separating source and JSON Pointer.", $path);
                    continue;
                }
                [$src, $ptr] = explode('#', $s->operationPath, 2);
                if ($src === '' || !isset($symbols->sourceDescriptions[$src])) {
                    $errors->error($this->code(), "operationPath source '{$src}' is not a declared sourceDescription.", $path);
                }
                if ($ptr === '' || $ptr[0] !== '/') {
                    $errors->error($this->code(), "operationPath JSON Pointer '{$ptr}' must start with '/'.", $path);
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepNestedWorkflowExistsRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepNestedWorkflowExistsRule implements Rule
{
    public function code(): string { return 'step.nested_workflow_exists'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->workflowId === null) continue;
                if (!isset($symbols->workflows[$s->workflowId])) {
                    $errors->error(
                        $this->code(),
                        "step.workflowId '{$s->workflowId}' does not resolve to a declared workflow.",
                        "/workflows/{$i}/steps/{$j}/workflowId",
                    );
                }
            }
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: step identity and operation-target validation rules"
```

---

