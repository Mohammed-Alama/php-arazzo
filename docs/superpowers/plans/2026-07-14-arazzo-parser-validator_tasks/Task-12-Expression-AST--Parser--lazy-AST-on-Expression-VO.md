### Task 12: Expression AST + Parser + lazy AST on Expression VO

**Files:**
- Create: `src/Expression/Ast/ExpressionAst.php` (abstract)
- Create: `src/Expression/Ast/InputRef.php`, `OutputRef.php`, `StepRef.php`, `WorkflowRef.php`, `SourceRef.php`, `ComponentRef.php`, `HttpMetaRef.php`
- Create: `src/Expression/Ast/StepPart.php` (abstract) + concrete `OutputPart.php`, `InputPart.php`, `RequestPart.php`, `ResponsePart.php`
- Create: `src/Expression/Parser.php`
- Modify: `src/Dto/Expression.php` — add lazy `ast(): ExpressionAst|ExpressionSyntaxException` accessor (returns exception object rather than throwing, so validation collects it).
- Create: `tests/Expression/ParserTest.php`

**Interfaces:**
- Produces:
  - `ExpressionAst` — abstract marker.
  - `InputRef(string $name)`
  - `OutputRef(string $name)`
  - `StepRef(string $stepId, StepPart $part)`
  - `WorkflowRef(string $workflowId, string $partKind /* 'inputs'|'outputs' */, string $name)`
  - `SourceRef(string $name, ?string $subPath)` — `subPath` is raw remaining string after first dot.
  - `ComponentRef(string $type, string $name)`
  - `HttpMetaRef(string $field)` — `$url|$method|$statusCode`.
  - `StepPart` subclasses: `OutputPart(string $name)`, `InputPart(string $name)`, `RequestPart(?string $httpPart, ?string $headerName, ?string $jsonPointer)`, `ResponsePart(?string $httpPart, ?string $headerName, ?string $jsonPointer)`.
  - `Expression\Parser::parse(string $raw): ExpressionAst` — throws `ExpressionSyntaxException`.
  - `Dto\Expression::ast(): ExpressionAst` (cached). `Dto\Expression::astOrError(): ExpressionAst|ExpressionSyntaxException` for validation.

- [ ] **Step 1: Write failing test**

Create `tests/Expression/ParserTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\SourceRef;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\Ast\WorkflowRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\Parser as ExprParser;

it('parses $inputs.name', function (): void {
    $ast = (new ExprParser())->parse('{$inputs.userId}');
    expect($ast)->toBeInstanceOf(InputRef::class)
        ->and($ast->name)->toBe('userId');
});

it('parses $steps.s.outputs.o', function (): void {
    $ast = (new ExprParser())->parse('{$steps.fetch.outputs.user}');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->stepId)->toBe('fetch')
        ->and($ast->part)->toBeInstanceOf(OutputPart::class)
        ->and($ast->part->name)->toBe('user');
});

it('parses $steps.s.response.body#/x/0', function (): void {
    $ast = (new ExprParser())->parse('{$steps.s.response.body#/x/0}');
    expect($ast->part)->toBeInstanceOf(ResponsePart::class)
        ->and($ast->part->httpPart)->toBe('body')
        ->and($ast->part->jsonPointer)->toBe('/x/0');
});

it('parses $workflows.w.outputs.o', function (): void {
    $ast = (new ExprParser())->parse('{$workflows.main.outputs.token}');
    expect($ast)->toBeInstanceOf(WorkflowRef::class)
        ->and($ast->workflowId)->toBe('main')
        ->and($ast->partKind)->toBe('outputs')
        ->and($ast->name)->toBe('token');
});

it('parses $sourceDescriptions.api with subpath', function (): void {
    $ast = (new ExprParser())->parse('{$sourceDescriptions.api.workflows.x}');
    expect($ast)->toBeInstanceOf(SourceRef::class)
        ->and($ast->name)->toBe('api')
        ->and($ast->subPath)->toBe('workflows.x');
});

it('parses $components.parameters.name', function (): void {
    $ast = (new ExprParser())->parse('{$components.parameters.Trace}');
    expect($ast)->toBeInstanceOf(ComponentRef::class)
        ->and($ast->type)->toBe('parameters')
        ->and($ast->name)->toBe('Trace');
});

it('parses $statusCode', function (): void {
    $ast = (new ExprParser())->parse('{$statusCode}');
    expect($ast)->toBeInstanceOf(HttpMetaRef::class)
        ->and($ast->field)->toBe('statusCode');
});

it('rejects unknown root token', function (): void {
    (new ExprParser())->parse('{$foobar}');
})->throws(ExpressionSyntaxException::class);

it('caches ast on Expression VO', function (): void {
    $e = new \Alama\LaravelArazzo\Dto\Expression('{$inputs.x}');
    expect($e->ast())->toBe($e->ast());
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement AST classes**

`src/Expression/Ast/ExpressionAst.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

abstract readonly class ExpressionAst {}
```

`src/Expression/Ast/StepPart.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

abstract readonly class StepPart {}
```

`src/Expression/Ast/InputRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class InputRef extends ExpressionAst
{
    public function __construct(public string $name) {}
}
```

`src/Expression/Ast/OutputRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class OutputRef extends ExpressionAst
{
    public function __construct(public string $name) {}
}
```

`src/Expression/Ast/StepRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class StepRef extends ExpressionAst
{
    public function __construct(public string $stepId, public StepPart $part) {}
}
```

`src/Expression/Ast/WorkflowRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class WorkflowRef extends ExpressionAst
{
    /** @param 'inputs'|'outputs' $partKind */
    public function __construct(
        public string $workflowId,
        public string $partKind,
        public string $name,
    ) {}
}
```

`src/Expression/Ast/SourceRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class SourceRef extends ExpressionAst
{
    public function __construct(public string $name, public ?string $subPath) {}
}
```

`src/Expression/Ast/ComponentRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class ComponentRef extends ExpressionAst
{
    public function __construct(public string $type, public string $name) {}
}
```

`src/Expression/Ast/HttpMetaRef.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class HttpMetaRef extends ExpressionAst
{
    /** @param 'url'|'method'|'statusCode' $field */
    public function __construct(public string $field) {}
}
```

`src/Expression/Ast/OutputPart.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class OutputPart extends StepPart
{
    public function __construct(public string $name) {}
}
```

`src/Expression/Ast/InputPart.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class InputPart extends StepPart
{
    public function __construct(public string $name) {}
}
```

`src/Expression/Ast/RequestPart.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class RequestPart extends StepPart
{
    public function __construct(
        public ?string $httpPart,
        public ?string $headerName,
        public ?string $jsonPointer,
    ) {}
}
```

`src/Expression/Ast/ResponsePart.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class ResponsePart extends StepPart
{
    public function __construct(
        public ?string $httpPart,
        public ?string $headerName,
        public ?string $jsonPointer,
    ) {}
}
```

- [ ] **Step 4: Implement Expression Parser**

`src/Expression/Parser.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\InputPart;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\OutputRef;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\SourceRef;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\Ast\WorkflowRef;

final class Parser
{
    public function __construct(private readonly Lexer $lexer = new Lexer()) {}

    public function parse(string $raw): ExpressionAst
    {
        $tokens = $this->lexer->tokenize($raw);
        if ($tokens === []) {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", '', 'expr.syntax');
        }
        $head = $tokens[0];
        if ($head->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Expression must start with a keyword: {$raw}", '', 'expr.syntax');
        }

        return match ($head->value) {
            'inputs'             => $this->parseSimpleRef($tokens, InputRef::class, $raw),
            'outputs'            => $this->parseSimpleRef($tokens, OutputRef::class, $raw),
            'url', 'method', 'statusCode' => $this->parseHttpMeta($tokens, $raw),
            'steps'              => $this->parseStepRef($tokens, $raw),
            'workflows'          => $this->parseWorkflowRef($tokens, $raw),
            'sourceDescriptions' => $this->parseSourceRef($tokens, $raw),
            'components'         => $this->parseComponentRef($tokens, $raw),
            'request', 'response' => throw new ExpressionSyntaxException(
                "Bare \${$head->value} must appear inside a \$steps.* expression: {$raw}", '', 'expr.syntax',
            ),
            default => throw new ExpressionSyntaxException("Unknown root '{$head->value}' in expression: {$raw}", '', 'expr.syntax'),
        };
    }

    /**
     * @param list<Token> $tokens
     * @param class-string<InputRef|OutputRef> $refClass
     */
    private function parseSimpleRef(array $tokens, string $refClass, string $raw): InputRef|OutputRef
    {
        // keyword . name  (expect exactly 3 tokens)
        if (count($tokens) !== 3
            || $tokens[1]->kind !== TokenKind::Dot
            || $tokens[2]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Malformed reference: {$raw}", '', 'expr.syntax');
        }
        return new $refClass($tokens[2]->value);
    }

    /** @param list<Token> $tokens */
    private function parseHttpMeta(array $tokens, string $raw): HttpMetaRef
    {
        if (count($tokens) !== 1) {
            throw new ExpressionSyntaxException("Malformed HTTP meta reference: {$raw}", '', 'expr.syntax');
        }
        /** @var 'url'|'method'|'statusCode' $field */
        $field = $tokens[0]->value;
        return new HttpMetaRef($field);
    }

    /** @param list<Token> $tokens */
    private function parseStepRef(array $tokens, string $raw): StepRef
    {
        // steps . <name> . <sub>
        if (count($tokens) < 5
            || $tokens[1]->kind !== TokenKind::Dot
            || $tokens[2]->kind !== TokenKind::Name
            || $tokens[3]->kind !== TokenKind::Dot
            || $tokens[4]->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Malformed step reference: {$raw}", '', 'expr.syntax');
        }
        $stepId = $tokens[2]->value;
        $sub = $tokens[4]->value;
        $rest = array_slice($tokens, 5);

        return new StepRef($stepId, match ($sub) {
            'outputs' => $this->parseNamedPart($rest, OutputPart::class, $raw),
            'inputs'  => $this->parseNamedPart($rest, InputPart::class, $raw),
            'request' => $this->parseHttpPart($rest, RequestPart::class, $raw),
            'response' => $this->parseHttpPart($rest, ResponsePart::class, $raw),
            default   => throw new ExpressionSyntaxException("Unknown step part '{$sub}' in: {$raw}", '', 'expr.syntax'),
        });
    }

    /**
     * @param list<Token> $rest
     * @param class-string<OutputPart|InputPart> $cls
     */
    private function parseNamedPart(array $rest, string $cls, string $raw): OutputPart|InputPart
    {
        if (count($rest) !== 2 || $rest[0]->kind !== TokenKind::Dot || $rest[1]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Malformed reference: {$raw}", '', 'expr.syntax');
        }
        return new $cls($rest[1]->value);
    }

    /**
     * @param list<Token> $rest
     * @param class-string<RequestPart|ResponsePart> $cls
     */
    private function parseHttpPart(array $rest, string $cls, string $raw): RequestPart|ResponsePart
    {
        // rest may be empty (bare $steps.s.request), or ". body[#/ptr]", ". header . name", ". url|method|statusCode"
        if ($rest === []) {
            return new $cls(null, null, null);
        }
        if ($rest[0]->kind !== TokenKind::Dot) {
            throw new ExpressionSyntaxException("Expected '.' after part in: {$raw}", '', 'expr.syntax');
        }
        if (count($rest) < 2 || $rest[1]->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Expected keyword after '.' in: {$raw}", '', 'expr.syntax');
        }
        $kw = $rest[1]->value;
        $tail = array_slice($rest, 2);

        return match ($kw) {
            'body' => new $cls('body', null, $this->parseJsonPointer($tail, $raw)),
            'header' => new $cls('header', $this->parseHeaderName($tail, $raw), null),
            'url', 'method', 'statusCode' => (function () use ($cls, $kw, $tail, $raw) {
                if ($tail !== []) throw new ExpressionSyntaxException("Unexpected tokens after '{$kw}' in: {$raw}", '', 'expr.syntax');
                return new $cls($kw, null, null);
            })(),
            default => throw new ExpressionSyntaxException("Unknown http part '{$kw}' in: {$raw}", '', 'expr.syntax'),
        };
    }

    /** @param list<Token> $tail */
    private function parseJsonPointer(array $tail, string $raw): ?string
    {
        if ($tail === []) return null;
        if ($tail[0]->kind !== TokenKind::Hash) {
            throw new ExpressionSyntaxException("Expected '#' before JSON pointer in: {$raw}", '', 'expr.syntax');
        }
        $out = '';
        $i = 1;
        while ($i < count($tail)) {
            if ($tail[$i]->kind !== TokenKind::Slash) {
                throw new ExpressionSyntaxException("Expected '/' in JSON pointer at token {$i} in: {$raw}", '', 'expr.syntax');
            }
            $out .= '/';
            $i++;
            if ($i < count($tail) && $tail[$i]->kind === TokenKind::PointerSegment) {
                $out .= $tail[$i]->value;
                $i++;
            }
        }
        return $out;
    }

    /** @param list<Token> $tail */
    private function parseHeaderName(array $tail, string $raw): string
    {
        if (count($tail) !== 2 || $tail[0]->kind !== TokenKind::Dot || $tail[1]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Expected '.name' after header in: {$raw}", '', 'expr.syntax');
        }
        return $tail[1]->value;
    }

    /** @param list<Token> $tokens */
    private function parseWorkflowRef(array $tokens, string $raw): WorkflowRef
    {
        // workflows . <name> . (inputs|outputs) . <name>
        if (count($tokens) !== 7
            || $tokens[1]->kind !== TokenKind::Dot
            || $tokens[2]->kind !== TokenKind::Name
            || $tokens[3]->kind !== TokenKind::Dot
            || $tokens[4]->kind !== TokenKind::Keyword
            || $tokens[5]->kind !== TokenKind::Dot
            || $tokens[6]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Malformed workflow reference: {$raw}", '', 'expr.syntax');
        }
        $kind = $tokens[4]->value;
        if ($kind !== 'inputs' && $kind !== 'outputs') {
            throw new ExpressionSyntaxException("Workflow part must be inputs or outputs in: {$raw}", '', 'expr.syntax');
        }
        /** @var 'inputs'|'outputs' $kind */
        return new WorkflowRef($tokens[2]->value, $kind, $tokens[6]->value);
    }

    /** @param list<Token> $tokens */
    private function parseSourceRef(array $tokens, string $raw): SourceRef
    {
        // sourceDescriptions . <name> [ . <raw tail> ]
        if (count($tokens) < 3
            || $tokens[1]->kind !== TokenKind::Dot
            || $tokens[2]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Malformed sourceDescriptions reference: {$raw}", '', 'expr.syntax');
        }
        $name = $tokens[2]->value;
        if (count($tokens) === 3) return new SourceRef($name, null);
        if ($tokens[3]->kind !== TokenKind::Dot) {
            throw new ExpressionSyntaxException("Expected '.' after source name in: {$raw}", '', 'expr.syntax');
        }
        // Reassemble the tail from token offsets.
        $tail = array_slice($tokens, 4);
        $out = '';
        foreach ($tail as $t) {
            $out .= $t->kind === TokenKind::Dot ? '.' : $t->value;
        }
        return new SourceRef($name, $out === '' ? null : $out);
    }

    /** @param list<Token> $tokens */
    private function parseComponentRef(array $tokens, string $raw): ComponentRef
    {
        // components . <type> . <name>
        if (count($tokens) !== 5
            || $tokens[1]->kind !== TokenKind::Dot
            || $tokens[2]->kind !== TokenKind::Name
            || $tokens[3]->kind !== TokenKind::Dot
            || $tokens[4]->kind !== TokenKind::Name) {
            throw new ExpressionSyntaxException("Malformed components reference: {$raw}", '', 'expr.syntax');
        }
        return new ComponentRef($tokens[2]->value, $tokens[4]->value);
    }
}
```

- [ ] **Step 5: Add lazy AST to Expression VO**

Replace `src/Dto/Expression.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\Parser as ExpressionParser;

final class Expression
{
    private ExpressionAst|ExpressionSyntaxException|null $cached = null;

    public function __construct(public readonly string $raw) {}

    public function ast(): ExpressionAst
    {
        $result = $this->astOrError();
        if ($result instanceof ExpressionSyntaxException) throw $result;
        return $result;
    }

    public function astOrError(): ExpressionAst|ExpressionSyntaxException
    {
        if ($this->cached !== null) return $this->cached;
        try {
            return $this->cached = (new ExpressionParser())->parse($this->raw);
        } catch (ExpressionSyntaxException $e) {
            return $this->cached = $e;
        }
    }
}
```

Note: `Expression` is no longer `readonly` class-wide because `$cached` mutates; the public `$raw` field is `readonly`. Update any test that relied on `readonly class Expression`.

- [ ] **Step 6: Run — expect pass**

- [ ] **Step 7: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: expression AST, recursive-descent parser, and cached ast on Expression VO"
```

---

