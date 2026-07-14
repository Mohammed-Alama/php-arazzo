### Task 8: Parser — leaf sub-parsers (Info, SourceDescription, Parameter, PayloadReplacement, RequestBody, SuccessCriterion, Reusable, Expression detection)

**Files:**
- Modify: `src/Parser/Parser.php` — add protected `parseInfo`, `parseSourceDescription`, `parseParameter`, `parsePayloadReplacement`, `parseRequestBody`, `parseSuccessCriterion`, `parseReusable`, `parseExpressionOrScalar`.
- Create: `tests/Parser/LeafParserTest.php`

**Interfaces:**
- Consumes: helpers from Task 7, DTOs from Task 4.
- Produces (all protected methods on `Parser`):
  - `parseInfo(mixed $node, ParseContext $ctx): Info`
  - `parseSourceDescription(mixed $node, ParseContext $ctx): SourceDescription`
  - `parseParameter(mixed $node, ParseContext $ctx): Parameter`
  - `parsePayloadReplacement(mixed $node, ParseContext $ctx): PayloadReplacement`
  - `parseRequestBody(mixed $node, ParseContext $ctx): RequestBody`
  - `parseSuccessCriterion(mixed $node, ParseContext $ctx): SuccessCriterion`
  - `parseReusable(array $node, ParseContext $ctx): Reusable`
  - `parseExpressionOrScalar(mixed $node): Expression|string|int|float|bool|null` — returns `Expression` if `$node` is a string matching `/^\{\$.+\}$/`, otherwise the scalar as-is; rejects arrays/objects.

- [ ] **Step 1: Write failing test**

Create `tests/Parser/LeafParserTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

class LeafProbe extends Parser
{
    public function pInfo(mixed $n, ParseContext $c) { return $this->parseInfo($n, $c); }
    public function pSrc(mixed $n, ParseContext $c) { return $this->parseSourceDescription($n, $c); }
    public function pParam(mixed $n, ParseContext $c) { return $this->parseParameter($n, $c); }
    public function pRepl(mixed $n, ParseContext $c) { return $this->parsePayloadReplacement($n, $c); }
    public function pReq(mixed $n, ParseContext $c) { return $this->parseRequestBody($n, $c); }
    public function pCrit(mixed $n, ParseContext $c) { return $this->parseSuccessCriterion($n, $c); }
    public function pReu(array $n, ParseContext $c) { return $this->parseReusable($n, $c); }
    public function pExpr(mixed $n) { return $this->parseExpressionOrScalar($n); }
}

$ctx = fn() => new ParseContext('/x');

it('parses Info', function () use ($ctx): void {
    $i = (new LeafProbe())->pInfo(['title'=>'T','version'=>'1.0'], $ctx());
    expect($i->title)->toBe('T')->and($i->version)->toBe('1.0')->and($i->summary)->toBeNull();
});

it('rejects Info missing version', function () use ($ctx): void {
    (new LeafProbe())->pInfo(['title'=>'T'], $ctx());
})->throws(ParserException::class);

it('parses SourceDescription', function () use ($ctx): void {
    $s = (new LeafProbe())->pSrc(['name'=>'api','url'=>'/x','type'=>'openapi'], $ctx());
    expect($s->type)->toBe(SourceType::Openapi);
});

it('rejects bad source type', function () use ($ctx): void {
    (new LeafProbe())->pSrc(['name'=>'api','url'=>'/x','type'=>'graphql'], $ctx());
})->throws(ParserException::class);

it('parses Parameter with expression value', function () use ($ctx): void {
    $p = (new LeafProbe())->pParam(['name'=>'id','in'=>'query','value'=>'{$inputs.id}'], $ctx());
    expect($p->in)->toBe(ParameterIn::Query)
        ->and($p->value)->toBeInstanceOf(Expression::class);
});

it('parses scalar Parameter value', function () use ($ctx): void {
    $p = (new LeafProbe())->pParam(['name'=>'id','value'=>42], $ctx());
    expect($p->value)->toBe(42);
});

it('parses SuccessCriterion with type', function () use ($ctx): void {
    $c = (new LeafProbe())->pCrit(['condition'=>'$.id != null','type'=>'jsonpath','context'=>'$response.body'], $ctx());
    expect($c->type)->toBe(CriterionType::JsonPath);
});

it('parses Reusable', function () use ($ctx): void {
    $r = (new LeafProbe())->pReu(['reference'=>'$components.parameters.x'], $ctx());
    expect($r->reference)->toBe('$components.parameters.x');
});

it('detects expression strings', function (): void {
    expect((new LeafProbe())->pExpr('{$inputs.x}'))->toBeInstanceOf(Expression::class)
        ->and((new LeafProbe())->pExpr('plain'))->toBe('plain')
        ->and((new LeafProbe())->pExpr(5))->toBe(5)
        ->and((new LeafProbe())->pExpr(null))->toBeNull();
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Extend Parser with leaf methods**

Append to `src/Parser/Parser.php` (inside class):

```php
    protected function parseInfo(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Info
    {
        $obj = $this->requireObjectMap($node, $ctx);
        return new \Alama\LaravelArazzo\Dto\Info(
            title:       $this->requireString($obj, 'title', $ctx),
            summary:     $this->optionalString($obj, 'summary', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            version:     $this->requireString($obj, 'version', $ctx),
        );
    }

    protected function parseSourceDescription(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\SourceDescription
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $enum = \Alama\LaravelArazzo\Dto\Enum\SourceType::tryFrom($type)
            ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                $ctx->push('type'), 'openapi|arazzo', $type,
            );
        return new \Alama\LaravelArazzo\Dto\SourceDescription(
            name: $this->requireString($obj, 'name', $ctx),
            url:  $this->requireString($obj, 'url', $ctx),
            type: $enum,
        );
    }

    protected function parseParameter(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Parameter
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $in = null;
        if (($rawIn = $this->optionalString($obj, 'in', $ctx)) !== null) {
            $in = \Alama\LaravelArazzo\Dto\Enum\ParameterIn::tryFrom($rawIn)
                ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                    $ctx->push('in'), 'path|query|header|cookie|body', $rawIn,
                );
        }
        if (!array_key_exists('value', $obj)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'value');
        }
        return new \Alama\LaravelArazzo\Dto\Parameter(
            name:  $this->requireString($obj, 'name', $ctx),
            in:    $in,
            value: $this->parseExpressionOrScalar($obj['value']),
        );
    }

    protected function parsePayloadReplacement(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\PayloadReplacement
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (!array_key_exists('value', $obj)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'value');
        }
        return new \Alama\LaravelArazzo\Dto\PayloadReplacement(
            target: $this->requireString($obj, 'target', $ctx),
            value:  $this->parseExpressionOrScalar($obj['value']),
        );
    }

    protected function parseRequestBody(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\RequestBody
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $replacements = [];
        $rawRepl = $this->optionalArray($obj, 'replacements', $ctx);
        if ($rawRepl !== null) {
            foreach (array_values($rawRepl) as $i => $item) {
                $replacements[] = $this->parsePayloadReplacement($item, $ctx->push('replacements')->push($i));
            }
        }
        return new \Alama\LaravelArazzo\Dto\RequestBody(
            contentType:  $this->optionalString($obj, 'contentType', $ctx),
            payload:      array_key_exists('payload', $obj)
                ? (is_string($obj['payload']) ? $this->parseExpressionOrScalar($obj['payload']) : $obj['payload'])
                : null,
            replacements: $replacements,
        );
    }

    protected function parseSuccessCriterion(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\SuccessCriterion
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = null;
        if (($t = $this->optionalString($obj, 'type', $ctx)) !== null) {
            $type = \Alama\LaravelArazzo\Dto\Enum\CriterionType::tryFrom($t)
                ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                    $ctx->push('type'), 'simple|regex|jsonpath|xpath', $t,
                );
        }
        return new \Alama\LaravelArazzo\Dto\SuccessCriterion(
            context:   $this->optionalString($obj, 'context', $ctx),
            condition: $this->requireString($obj, 'condition', $ctx),
            type:      $type,
        );
    }

    /** @param array<string,mixed> $node */
    protected function parseReusable(array $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Reusable
    {
        return new \Alama\LaravelArazzo\Dto\Reusable(
            reference: $this->requireString($node, 'reference', $ctx),
            value:     $node['value'] ?? null,
        );
    }

    protected function parseExpressionOrScalar(mixed $node): \Alama\LaravelArazzo\Dto\Expression|string|int|float|bool|null
    {
        if (is_string($node) && preg_match('/^\{\$.+\}$/', $node) === 1) {
            return new \Alama\LaravelArazzo\Dto\Expression($node);
        }
        if ($node === null || is_string($node) || is_int($node) || is_float($node) || is_bool($node)) {
            return $node;
        }
        // Arrays/objects passed here mean caller misused this helper.
        throw new \InvalidArgumentException('parseExpressionOrScalar expects scalar or null, got ' . get_debug_type($node));
    }
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: parser leaf methods (info, source, parameter, request body, criterion, reusable, expression)"
```

---

