### Task 20: Action rules + components + extensions + unknown-field rules (7 rules)

**Files:**
- Create: `src/Validation/Rules/ActionTypeValidRule.php`, `ActionGotoTargetResolvesRule.php`, `ActionRetryLimitsRule.php`, `ActionReusableRefResolvesRule.php`
- Create: `src/Validation/Rules/ComponentsUniqueNamesRule.php`
- Create: `src/Validation/Rules/ExtensionsXPrefixRule.php`
- Create: `src/Validation/Rules/DocUnknownFieldRule.php` (needs the raw array from parse; requires wiring — see below)
- Modify: `src/Dto/ArazzoDocument.php` — add optional `?array $rawRoot` field so unknown-field detection sees the raw top-level keys. Update Parser to fill it.
- Create: `tests/Validation/Rules/ActionAndComponentRulesTest.php`

**Interfaces:**
- `ArazzoDocument` gains: `public ?array $rawRoot = null` (nullable so hand-built docs in tests don't need it). Parser passes `$raw->data`.
- Rules produce codes: `action.type_valid`, `action.goto_target_resolves`, `action.retry_limits`, `action.reusable_ref_resolves`, `components.unique_names`, `extensions.x_prefix`, `doc.unknown_field`.
- `DocUnknownFieldRule` compares `rawRoot` keys against the known set: `arazzo, info, sourceDescriptions, workflows, components` plus `x-*`. Unknown top-level keys emit a `Warning` normally; when `RuleSet::isStrict()` is `true`, they emit an `Error` instead. Passed via constructor: `new DocUnknownFieldRule(strict: true)`.

- [ ] **Step 1: Extend ArazzoDocument + Parser**

Update `src/Dto/ArazzoDocument.php` — add `?array $rawRoot` param as last:

```php
    /**
     * @param list<SourceDescription>    $sourceDescriptions
     * @param list<Workflow>             $workflows
     * @param array<string,mixed>        $specificationExtensions
     * @param array<string,mixed>|null   $rawRoot
     */
    public function __construct(
        public string $arazzo,
        public Info $info,
        public array $sourceDescriptions,
        public array $workflows,
        public Components $components,
        public array $specificationExtensions,
        public ?array $rawRoot = null,
    ) {}
```

Update `src/Parser/Parser.php` `parse()` — pass `rawRoot: $d` in the constructor call.

Update `tests/Dto/ContainerDtoTest.php` — no change needed (default `null` works).

- [ ] **Step 2: Write failing test**

Create `tests/Validation/Rules/ActionAndComponentRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ActionGotoTargetResolvesRule;
use Alama\LaravelArazzo\Validation\Rules\ActionRetryLimitsRule;
use Alama\LaravelArazzo\Validation\Rules\ActionReusableRefResolvesRule;
use Alama\LaravelArazzo\Validation\Rules\ActionTypeValidRule;
use Alama\LaravelArazzo\Validation\Rules\DocUnknownFieldRule;
use Alama\LaravelArazzo\Validation\Rules\ExtensionsXPrefixRule;

function docWithSteps(array $steps, ?array $rawRoot = null): ArazzoDocument
{
    $w = new Workflow('w', null, null, null, [], $steps, [], [], [], []);
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), [], $rawRoot);
}

it('accepts valid action types (no-op passes)', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new SuccessGotoAction('g', 's', null, [])], [], []);
    $doc = docWithSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionTypeValidRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags goto with unknown stepId', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new SuccessGotoAction('g', 'ghost', null, [])], [], []);
    $doc = docWithSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionGotoTargetResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags negative retry limits', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [], [new RetryAction('r', -5, -1, 's', null, [])], []);
    $doc = docWithSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionRetryLimitsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});

it('flags unresolved reusable ref', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new Reusable('$components.successActions.ghost')], [], []);
    $doc = docWithSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionReusableRefResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('warns on extension without x- prefix (via extensions preprocessing)', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), ['x-good' => 1], ['x-good' => 1, 'y-bad' => 2]);
    $ec = new ErrorCollector();
    (new ExtensionsXPrefixRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toBe([])->and($ec->errors())->toBe([]);
});

it('flags unknown top-level field', function (): void {
    $raw = ['arazzo'=>'1.0.0','info'=>[],'workflows'=>[],'weird'=>true];
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], $raw);
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule(strict: false))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toHaveCount(1)->and($ec->errors())->toBe([]);

    $ec2 = new ErrorCollector();
    (new DocUnknownFieldRule(strict: true))->check($doc, SymbolTable::build($doc), $ec2);
    expect($ec2->errors())->toHaveCount(1)->and($ec2->warnings())->toBe([]);
});
```

- [ ] **Step 3: Run — expect fail**

- [ ] **Step 4: Implement rules**

`src/Validation/Rules/ActionTypeValidRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/** Enum + sum-type enforcement already at parse time; reserved code. */
final class ActionTypeValidRule implements Rule
{
    public function code(): string { return 'action.type_valid'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
```

`src/Validation/Rules/ActionGotoTargetResolvesRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Expression\WorkflowSymbols;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ActionGotoTargetResolvesRule implements Rule
{
    public function code(): string { return 'action.goto_target_resolves'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            $syms = $symbols->workflows[$w->workflowId] ?? null;
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->onSuccess, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->onFailure, $syms, $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }

    /** @param list<mixed> $actions */
    private function checkList(array $actions, ?WorkflowSymbols $syms, SymbolTable $global, ErrorCollector $errors, string $base): void
    {
        foreach ($actions as $i => $a) {
            $stepId = null; $workflowId = null;
            if ($a instanceof SuccessGotoAction || $a instanceof FailureGotoAction || $a instanceof RetryAction) {
                $stepId = $a->stepId; $workflowId = $a->workflowId;
            } else {
                continue;
            }
            if ($stepId !== null && ($syms === null || !isset($syms->stepsById[$stepId]))) {
                $errors->error($this->code(), "Action references unknown stepId '{$stepId}'.", "{$base}/{$i}/stepId");
            }
            if ($workflowId !== null && !isset($global->workflows[$workflowId])) {
                $errors->error($this->code(), "Action references unknown workflowId '{$workflowId}'.", "{$base}/{$i}/workflowId");
            }
        }
    }
}
```

`src/Validation/Rules/ActionRetryLimitsRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ActionRetryLimitsRule implements Rule
{
    public function code(): string { return 'action.retry_limits'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->onFailure as $i => $a) {
                    if (!$a instanceof RetryAction) continue;
                    $base = "/workflows/{$wi}/steps/{$si}/onFailure/{$i}";
                    if ($a->retryAfter !== null && $a->retryAfter < 0) {
                        $errors->error($this->code(), "retryAfter must be >= 0.", "{$base}/retryAfter");
                    }
                    if ($a->retryLimit !== null && $a->retryLimit < 0) {
                        $errors->error($this->code(), "retryLimit must be >= 0.", "{$base}/retryLimit");
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/ActionReusableRefResolvesRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ActionReusableRefResolvesRule implements Rule
{
    public function code(): string { return 'action.reusable_ref_resolves'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->onSuccess, 'successActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->onFailure, 'failureActions', $symbols, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }

    /** @param list<mixed> $items */
    private function checkList(array $items, string $componentType, SymbolTable $symbols, ErrorCollector $errors, string $base): void
    {
        foreach ($items as $i => $item) {
            if (!$item instanceof Reusable) continue;
            $prefix = "\$components.{$componentType}.";
            if (!str_starts_with($item->reference, $prefix)) {
                $errors->error($this->code(), "Reusable reference '{$item->reference}' does not target components.{$componentType}.", "{$base}/{$i}/reference");
                continue;
            }
            $name = substr($item->reference, strlen($prefix));
            if (!isset($symbols->components[$componentType][$name])) {
                $errors->error($this->code(), "Reusable reference '{$item->reference}' does not resolve.", "{$base}/{$i}/reference");
            }
        }
    }
}
```

`src/Validation/Rules/ComponentsUniqueNamesRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/** JSON/YAML collapse duplicate keys at load time; reserved for future strict pre-load checks. */
final class ComponentsUniqueNamesRule implements Rule
{
    public function code(): string { return 'components.unique_names'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
```

`src/Validation/Rules/ExtensionsXPrefixRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/**
 * The parser filters non-`x-` keys out of specificationExtensions and does not
 * treat them as extensions; unknown top-level keys are the domain of
 * DocUnknownFieldRule. This rule remains for symmetry and future use.
 */
final class ExtensionsXPrefixRule implements Rule
{
    public function code(): string { return 'extensions.x_prefix'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->specificationExtensions as $k => $_) {
            if (!str_starts_with((string) $k, 'x-')) {
                $errors->warning($this->code(), "Specification extension '{$k}' must start with 'x-'.", '/' . $k);
            }
        }
    }
}
```

`src/Validation/Rules/DocUnknownFieldRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocUnknownFieldRule implements Rule
{
    private const KNOWN = ['arazzo', 'info', 'sourceDescriptions', 'workflows', 'components'];

    public function __construct(public readonly bool $strict = true) {}

    public function code(): string { return 'doc.unknown_field'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->rawRoot === null) return;
        foreach ($doc->rawRoot as $k => $_) {
            if (!is_string($k)) continue;
            if (in_array($k, self::KNOWN, true) || str_starts_with($k, 'x-')) continue;
            $msg = "Unknown top-level field '{$k}'.";
            $path = '/' . str_replace(['~', '/'], ['~0', '~1'], $k);
            if ($this->strict) {
                $errors->error($this->code(), $msg, $path);
            } else {
                $errors->warning($this->code(), $msg, $path);
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
git commit -m "feat: action, components, extensions, and unknown-field validation rules"
```

---
