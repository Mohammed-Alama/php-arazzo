### Task 16: Workflow dependsOn + inputs schema rules (3 rules)

**Files:**
- Create: `src/Validation/Rules/WorkflowDependsOnExistsRule.php`
- Create: `src/Validation/Rules/WorkflowDependsOnNoCycleRule.php`
- Create: `src/Validation/Rules/WorkflowInputsValidSchemaRule.php`
- Create: `tests/Validation/Rules/WorkflowDependsRulesTest.php`

**Interfaces:**
- Consumes: Task 14 infra, Task 13 `SymbolTable`.
- Produces: 3 rules with codes `workflow.dependson_exists`, `workflow.dependson_no_cycle`, `workflow.inputs_valid_schema`.
- Cycle detection: DFS coloring (white/grey/black). Any back-edge → single error at the edge site (do not enumerate the whole cycle).
- Inputs schema check is **structural only**: must be an object AND (if `type` set) `type == 'object'` AND (if `properties` set) `properties` is a map.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/WorkflowDependsRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnExistsRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnNoCycleRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowInputsValidSchemaRule;

function step(string $id): Step { return new Step($id, null, 'op', null, null, [], null, [], [], [], []); }

function wfDep(string $id, array $dep = [], ?array $inputs = null): Workflow
{
    return new Workflow($id, null, null, $inputs, $dep, [step('s')], [], [], [], []);
}

function docWf(array $wfs): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], $wfs, new Components([], [], [], []), []);
}

it('flags dependsOn to unknown workflow', function (): void {
    $doc = docWf([wfDep('a', ['ghost'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_exists');
});

it('flags cyclic dependsOn', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['a'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_no_cycle');
});

it('accepts acyclic chain', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['c']), wfDep('c')]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags inputs schema not being an object', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'string'])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('accepts inputs as object schema', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/WorkflowDependsOnExistsRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnExistsRule implements Rule
{
    public function code(): string { return 'workflow.dependson_exists'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->dependsOn as $j => $dep) {
                if (!isset($symbols->workflows[$dep])) {
                    $errors->error(
                        $this->code(),
                        "workflow '{$w->workflowId}' dependsOn '{$dep}' which is not declared.",
                        "/workflows/{$i}/dependsOn/{$j}",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/WorkflowDependsOnNoCycleRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnNoCycleRule implements Rule
{
    public function code(): string { return 'workflow.dependson_no_cycle'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        /** @var array<string,int> $color 0=white,1=grey,2=black */
        $color = [];
        foreach ($doc->workflows as $w) $color[$w->workflowId] = 0;
        $indexOf = [];
        foreach ($doc->workflows as $i => $w) $indexOf[$w->workflowId] = $i;

        $reported = false;
        $dfs = function (string $node) use (&$dfs, &$color, $symbols, $errors, $indexOf, &$reported): void {
            if ($reported) return;
            if (!isset($symbols->workflows[$node])) return;
            $color[$node] = 1;
            foreach ($symbols->workflows[$node]->dependsOn as $next => $_) {
                if (!isset($color[$next])) continue;
                if ($color[$next] === 1) {
                    $i = $indexOf[$node] ?? 0;
                    $errors->error(
                        $this->code(),
                        "workflow.dependsOn cycle detected involving '{$node}' -> '{$next}'.",
                        "/workflows/{$i}/dependsOn",
                    );
                    $reported = true;
                    return;
                }
                if ($color[$next] === 0) $dfs($next);
                if ($reported) return;
            }
            $color[$node] = 2;
        };

        foreach ($doc->workflows as $w) {
            if (($color[$w->workflowId] ?? 0) === 0) $dfs($w->workflowId);
            if ($reported) break;
        }
    }
}
```

`src/Validation/Rules/WorkflowInputsValidSchemaRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowInputsValidSchemaRule implements Rule
{
    public function code(): string { return 'workflow.inputs_valid_schema'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if ($w->inputs === null) continue;
            $path = "/workflows/{$i}/inputs";
            if (isset($w->inputs['type']) && $w->inputs['type'] !== 'object') {
                $errors->error($this->code(), "workflow inputs schema must be of type 'object'.", $path . '/type');
                continue;
            }
            if (isset($w->inputs['properties']) && !is_array($w->inputs['properties'])) {
                $errors->error($this->code(), "workflow inputs.properties must be an object.", $path . '/properties');
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
git commit -m "feat: workflow dependsOn existence + cycle + inputs schema rules"
```

---

