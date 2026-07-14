### Task 5: Action sum types

**Files:**
- Create: `src/Dto/Action/Action.php` (abstract base marker)
- Create: `src/Dto/Action/SuccessAction.php`, `src/Dto/Action/FailureAction.php` (abstract)
- Create: `src/Dto/Action/GotoAction.php`, `src/Dto/Action/EndAction.php`, `src/Dto/Action/RetryAction.php`
- Create: `src/Dto/Enum/ActionKind.php`
- Create: `tests/Dto/ActionTest.php`

**Interfaces:**
- Produces:
  - `ActionKind` enum: `Goto`, `End`, `Retry`.
  - Abstract `Action { public string $name; public ActionKind $kind; }` — internal marker.
  - Abstract `SuccessAction extends Action` (allows Goto, End).
  - Abstract `FailureAction extends Action` (allows Goto, End, Retry).
  - `GotoAction extends SuccessAction & FailureAction is not possible in PHP` — solved by two concrete classes: `SuccessGotoAction`, `FailureGotoAction`. Same for `EndAction` → `SuccessEndAction`, `FailureEndAction`. `RetryAction extends FailureAction`. Concrete constructors:
    - `SuccessGotoAction(string $name, ?string $stepId, ?string $workflowId, list<SuccessCriterion> $criteria)`
    - `SuccessEndAction(string $name, list<SuccessCriterion> $criteria)`
    - `FailureGotoAction(string $name, ?string $stepId, ?string $workflowId, list<SuccessCriterion> $criteria)`
    - `FailureEndAction(string $name, list<SuccessCriterion> $criteria)`
    - `RetryAction(string $name, ?int $retryAfter, ?int $retryLimit, ?string $stepId, ?string $workflowId, list<SuccessCriterion> $criteria)`
  - `SuccessAction::$kind` restricted to `Goto|End`; `FailureAction::$kind` restricted to `Goto|End|Retry`.

- [ ] **Step 1: Write failing test**

Create `tests/Dto/ActionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\FailureEndAction;
use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Enum\ActionKind;

it('builds success actions', function (): void {
    $g = new SuccessGotoAction('go', 'step2', null, []);
    $e = new SuccessEndAction('end', []);

    expect($g->kind)->toBe(ActionKind::Goto)
        ->and($g->stepId)->toBe('step2')
        ->and($e->kind)->toBe(ActionKind::End);
});

it('builds failure actions', function (): void {
    $r = new RetryAction('r', 500, 3, 'step1', null, []);
    expect($r->kind)->toBe(ActionKind::Retry)
        ->and($r->retryLimit)->toBe(3);

    $g = new FailureGotoAction('go', null, 'wfB', []);
    $e = new FailureEndAction('end', []);
    expect($g->workflowId)->toBe('wfB')
        ->and($e->kind)->toBe(ActionKind::End);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Dto/Enum/ActionKind.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Enum;

enum ActionKind: string
{
    case Goto  = 'goto';
    case End   = 'end';
    case Retry = 'retry';
}
```

`src/Dto/Action/SuccessAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

abstract readonly class SuccessAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        public string $name,
        public ActionKind $kind,
        public array $criteria,
    ) {}
}
```

`src/Dto/Action/FailureAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

abstract readonly class FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        public string $name,
        public ActionKind $kind,
        public array $criteria,
    ) {}
}
```

`src/Dto/Action/SuccessGotoAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class SuccessGotoAction extends SuccessAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Goto, $criteria);
    }
}
```

`src/Dto/Action/SuccessEndAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class SuccessEndAction extends SuccessAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(string $name, array $criteria)
    {
        parent::__construct($name, ActionKind::End, $criteria);
    }
}
```

`src/Dto/Action/FailureGotoAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class FailureGotoAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Goto, $criteria);
    }
}
```

`src/Dto/Action/FailureEndAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class FailureEndAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(string $name, array $criteria)
    {
        parent::__construct($name, ActionKind::End, $criteria);
    }
}
```

`src/Dto/Action/RetryAction.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class RetryAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?int $retryAfter,
        public ?int $retryLimit,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Retry, $criteria);
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: add action sum types"
```

---

