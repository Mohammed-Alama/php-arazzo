### Task 9: Parser — action sub-parsers

**Files:**
- Modify: `src/Parser/Parser.php` — add protected `parseSuccessAction`, `parseFailureAction`, `parseOutputsMap`.
- Create: `tests/Parser/ActionParserTest.php`

**Interfaces:**
- Consumes: Task 8 helpers, Task 5 action DTOs.
- Produces:
  - `parseSuccessAction(mixed $node, ParseContext $ctx): SuccessAction|Reusable` — returns `Reusable` if `reference` present; else discriminates on `type`: `goto|end`.
  - `parseFailureAction(mixed $node, ParseContext $ctx): FailureAction|Reusable` — same, plus `retry`.
  - `parseOutputsMap(mixed $node, ParseContext $ctx): array<string,Expression>` — every value must be an Expression string.

- [ ] **Step 1: Write failing test**

Create `tests/Parser/ActionParserTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

class ActionProbe extends Parser
{
    public function pSA(mixed $n, ParseContext $c) { return $this->parseSuccessAction($n, $c); }
    public function pFA(mixed $n, ParseContext $c) { return $this->parseFailureAction($n, $c); }
    public function pOut(mixed $n, ParseContext $c) { return $this->parseOutputsMap($n, $c); }
}

$c = fn() => new ParseContext('/x');

it('parses success goto action', function () use ($c): void {
    $a = (new ActionProbe())->pSA(['name'=>'go','type'=>'goto','stepId'=>'s2'], $c());
    expect($a)->toBeInstanceOf(SuccessGotoAction::class)
        ->and($a->stepId)->toBe('s2');
});

it('parses success end action', function () use ($c): void {
    expect((new ActionProbe())->pSA(['name'=>'stop','type'=>'end'], $c()))
        ->toBeInstanceOf(SuccessEndAction::class);
});

it('parses reusable ref for success', function () use ($c): void {
    expect((new ActionProbe())->pSA(['reference'=>'$components.successActions.x'], $c()))
        ->toBeInstanceOf(Reusable::class);
});

it('parses failure goto', function () use ($c): void {
    expect((new ActionProbe())->pFA(['name'=>'go','type'=>'goto','workflowId'=>'w'], $c()))
        ->toBeInstanceOf(FailureGotoAction::class);
});

it('parses retry action', function () use ($c): void {
    $r = (new ActionProbe())->pFA(['name'=>'r','type'=>'retry','retryAfter'=>500,'retryLimit'=>2], $c());
    expect($r)->toBeInstanceOf(RetryAction::class)
        ->and($r->retryAfter)->toBe(500);
});

it('rejects invalid success action type', function () use ($c): void {
    (new ActionProbe())->pSA(['name'=>'x','type'=>'retry'], $c());
})->throws(ParserException::class, 'Invalid action type');

it('parses outputs map of expressions', function () use ($c): void {
    $o = (new ActionProbe())->pOut(['total'=>'{$response.body#/total}'], $c());
    expect($o['total'])->toBeInstanceOf(Expression::class);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

Append to `src/Parser/Parser.php`:

```php
    protected function parseSuccessAction(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Action\SuccessAction|\Alama\LaravelArazzo\Dto\Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new \Alama\LaravelArazzo\Dto\Action\SuccessGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            'end' => new \Alama\LaravelArazzo\Dto\Action\SuccessEndAction($name, $criteria),
            default => throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    protected function parseFailureAction(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Action\FailureAction|\Alama\LaravelArazzo\Dto\Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new \Alama\LaravelArazzo\Dto\Action\FailureGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            'end' => new \Alama\LaravelArazzo\Dto\Action\FailureEndAction($name, $criteria),
            'retry' => new \Alama\LaravelArazzo\Dto\Action\RetryAction(
                name: $name,
                retryAfter: $this->optionalInt($obj, 'retryAfter', $ctx),
                retryLimit: $this->optionalInt($obj, 'retryLimit', $ctx),
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            default => throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    /**
     * @param array<string,mixed> $obj
     * @return list<\Alama\LaravelArazzo\Dto\SuccessCriterion>
     */
    private function parseCriteriaList(array $obj, ParseContext $ctx): array
    {
        $list = $this->optionalArray($obj, 'criteria', $ctx);
        if ($list === null) return [];
        $out = [];
        foreach (array_values($list) as $i => $item) {
            $out[] = $this->parseSuccessCriterion($item, $ctx->push('criteria')->push($i));
        }
        return $out;
    }

    /** @return array<string,\Alama\LaravelArazzo\Dto\Expression> */
    protected function parseOutputsMap(mixed $node, ParseContext $ctx): array
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $out = [];
        foreach ($obj as $k => $v) {
            if (!is_string($v)) {
                throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType($ctx->push((string) $k), 'string (expression)', $v);
            }
            $out[$k] = new \Alama\LaravelArazzo\Dto\Expression($v);
        }
        return $out;
    }
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: parser action sub-parsers and outputs map"
```

---

