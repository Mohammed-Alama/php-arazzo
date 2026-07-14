### Task 6: Container DTOs — Step, Workflow, Components, ArazzoDocument

**Files:**
- Create: `src/Dto/Step.php`, `src/Dto/Workflow.php`, `src/Dto/Components.php`, `src/Dto/ArazzoDocument.php`
- Create: `tests/Dto/ContainerDtoTest.php`

**Interfaces:**
- Consumes: all Task 4 + Task 5 DTOs.
- Produces:
  - `Step(string $stepId, ?string $description, ?string $operationId, ?string $operationPath, ?string $workflowId, list<Parameter> $parameters, ?RequestBody $requestBody, list<SuccessCriterion> $successCriteria, list<SuccessAction|Reusable> $onSuccess, list<FailureAction|Reusable> $onFailure, array<string,Expression> $outputs)`
  - `Workflow(string $workflowId, ?string $summary, ?string $description, ?array $inputs /* raw JSON Schema */, list<string> $dependsOn, list<Step> $steps, list<SuccessAction|Reusable> $successActions, list<FailureAction|Reusable> $failureActions, array<string,Expression> $outputs, list<Parameter> $parameters)`
  - `Components(array<string,array<string,mixed>> $inputs, array<string,Parameter> $parameters, array<string,SuccessAction> $successActions, array<string,FailureAction> $failureActions)`
  - `ArazzoDocument(string $arazzo, Info $info, list<SourceDescription> $sourceDescriptions, list<Workflow> $workflows, Components $components, array<string,mixed> $specificationExtensions)`

- [ ] **Step 1: Write failing test**

Create `tests/Dto/ContainerDtoTest.php`:

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

it('builds full document tree', function (): void {
    $step = new Step('s1', null, 'getFoo', null, null, [], null, [], [], [], []);
    $wf   = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
    $doc  = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    expect($doc->workflows[0]->steps[0]->stepId)->toBe('s1');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Dto/Step.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Step
{
    /**
     * @param list<Parameter>                        $parameters
     * @param list<SuccessCriterion>                 $successCriteria
     * @param list<SuccessAction|Reusable>           $onSuccess
     * @param list<FailureAction|Reusable>           $onFailure
     * @param array<string,Expression>               $outputs
     */
    public function __construct(
        public string $stepId,
        public ?string $description,
        public ?string $operationId,
        public ?string $operationPath,
        public ?string $workflowId,
        public array $parameters,
        public ?RequestBody $requestBody,
        public array $successCriteria,
        public array $onSuccess,
        public array $onFailure,
        public array $outputs,
    ) {}
}
```

`src/Dto/Workflow.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Workflow
{
    /**
     * @param array<string,mixed>|null            $inputs
     * @param list<string>                        $dependsOn
     * @param list<Step>                          $steps
     * @param list<SuccessAction|Reusable>        $successActions
     * @param list<FailureAction|Reusable>        $failureActions
     * @param array<string,Expression>            $outputs
     * @param list<Parameter>                     $parameters
     */
    public function __construct(
        public string $workflowId,
        public ?string $summary,
        public ?string $description,
        public ?array $inputs,
        public array $dependsOn,
        public array $steps,
        public array $successActions,
        public array $failureActions,
        public array $outputs,
        public array $parameters,
    ) {}
}
```

`src/Dto/Components.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Components
{
    /**
     * @param array<string,array<string,mixed>> $inputs
     * @param array<string,Parameter>           $parameters
     * @param array<string,SuccessAction>       $successActions
     * @param array<string,FailureAction>       $failureActions
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $successActions,
        public array $failureActions,
    ) {}
}
```

`src/Dto/ArazzoDocument.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class ArazzoDocument
{
    /**
     * @param list<SourceDescription> $sourceDescriptions
     * @param list<Workflow>          $workflows
     * @param array<string,mixed>     $specificationExtensions
     */
    public function __construct(
        public string $arazzo,
        public Info $info,
        public array $sourceDescriptions,
        public array $workflows,
        public Components $components,
        public array $specificationExtensions,
    ) {}
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: add container DTOs (Step, Workflow, Components, ArazzoDocument)"
```

---

