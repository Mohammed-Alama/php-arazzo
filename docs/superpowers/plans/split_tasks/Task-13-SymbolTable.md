### Task 13: SymbolTable

**Files:**
- Create: `src/Expression/SymbolTable.php`
- Create: `src/Expression/WorkflowSymbols.php`
- Create: `src/Expression/StepSymbols.php`
- Create: `tests/Expression/SymbolTableTest.php`

**Interfaces:**
- Consumes: `ArazzoDocument`, `Workflow`, `Step`, `Expression`.
- Produces:
  - `SymbolTable::build(ArazzoDocument $doc): self`
  - Properties (readonly):
    - `array<string,WorkflowSymbols> $workflows` — keyed by `workflowId`
    - `array<string,true> $sourceDescriptions` — set semantics
    - `array<string,array<string,true>> $components` — `type => { name => true }`
  - `WorkflowSymbols` readonly: `{ Set<string> inputs, Set<string> parameters, array<string,StepSymbols> stepsById, Set<string> outputs, Set<string> dependsOn }` (Sets modelled as `array<string,true>`).
  - `StepSymbols` readonly: `{ Set<string> outputs, int index }`.
- `SymbolTable::build` is **defensive** — malformed input yields empty sets; it never throws.

- [ ] **Step 1: Write failing test**

Create `tests/Expression/SymbolTableTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;

it('builds symbol table from document', function (): void {
    $step = new Step(
        stepId: 'fetch',
        description: null, operationId: 'op', operationPath: null, workflowId: null,
        parameters: [], requestBody: null, successCriteria: [],
        onSuccess: [], onFailure: [],
        outputs: ['user' => new Expression('{$response.body}')],
    );
    $wf = new Workflow(
        workflowId: 'main',
        summary: null, description: null,
        inputs: ['type'=>'object','properties'=>['userId'=>['type'=>'string']]],
        dependsOn: [],
        steps: [$step],
        successActions: [], failureActions: [],
        outputs: ['user' => new Expression('{$steps.fetch.outputs.user}')],
        parameters: [],
    );
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $sym = SymbolTable::build($doc);

    expect($sym->sourceDescriptions)->toHaveKey('api')
        ->and($sym->workflows)->toHaveKey('main')
        ->and($sym->workflows['main']->inputs)->toHaveKey('userId')
        ->and($sym->workflows['main']->stepsById)->toHaveKey('fetch')
        ->and($sym->workflows['main']->stepsById['fetch']->outputs)->toHaveKey('user')
        ->and($sym->workflows['main']->stepsById['fetch']->index)->toBe(0)
        ->and($sym->workflows['main']->outputs)->toHaveKey('user');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Expression/StepSymbols.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class StepSymbols
{
    /** @param array<string,true> $outputs */
    public function __construct(public array $outputs, public int $index) {}
}
```

`src/Expression/WorkflowSymbols.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class WorkflowSymbols
{
    /**
     * @param array<string,true>              $inputs
     * @param array<string,true>              $parameters
     * @param array<string,StepSymbols>       $stepsById
     * @param array<string,true>              $outputs
     * @param array<string,true>              $dependsOn
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $stepsById,
        public array $outputs,
        public array $dependsOn,
    ) {}
}
```

`src/Expression/SymbolTable.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Workflow;

final readonly class SymbolTable
{
    /**
     * @param array<string,WorkflowSymbols>          $workflows
     * @param array<string,true>                     $sourceDescriptions
     * @param array<string,array<string,true>>       $components
     */
    public function __construct(
        public array $workflows,
        public array $sourceDescriptions,
        public array $components,
    ) {}

    public static function build(ArazzoDocument $doc): self
    {
        $sources = [];
        foreach ($doc->sourceDescriptions as $s) $sources[$s->name] = true;

        $components = [
            'inputs'         => self::keysOf($doc->components->inputs),
            'parameters'     => self::keysOf($doc->components->parameters),
            'successActions' => self::keysOf($doc->components->successActions),
            'failureActions' => self::keysOf($doc->components->failureActions),
        ];

        $workflows = [];
        foreach ($doc->workflows as $wf) {
            $workflows[$wf->workflowId] = self::buildWorkflow($wf);
        }

        return new self($workflows, $sources, $components);
    }

    private static function buildWorkflow(Workflow $wf): WorkflowSymbols
    {
        $inputs = [];
        if (is_array($wf->inputs)
            && isset($wf->inputs['properties'])
            && is_array($wf->inputs['properties'])) {
            foreach ($wf->inputs['properties'] as $k => $_) {
                if (is_string($k)) $inputs[$k] = true;
            }
        }

        $params = [];
        foreach ($wf->parameters as $p) $params[$p->name] = true;

        $steps = [];
        foreach ($wf->steps as $i => $s) {
            $outs = [];
            foreach ($s->outputs as $k => $_) $outs[$k] = true;
            $steps[$s->stepId] = new StepSymbols($outs, $i);
        }

        $outputs = [];
        foreach ($wf->outputs as $k => $_) $outputs[$k] = true;

        $dependsOn = [];
        foreach ($wf->dependsOn as $d) $dependsOn[$d] = true;

        return new WorkflowSymbols($inputs, $params, $steps, $outputs, $dependsOn);
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<string,true>
     */
    private static function keysOf(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $_) $out[(string) $k] = true;
        return $out;
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: build SymbolTable from ArazzoDocument"
```

---

