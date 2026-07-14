### Task 7: Parser infrastructure — ParseContext, ParserException, primitive helpers

**Files:**
- Create: `src/Parser/ParseContext.php`
- Create: `src/Exceptions/ParserException.php`
- Create: `src/Parser/Parser.php` (skeleton with private helper methods only, no top-level `parse()` yet)
- Create: `tests/Parser/ParseContextTest.php`
- Create: `tests/Parser/ParserHelpersTest.php`

**Interfaces:**
- Produces:
  - `ParseContext` — immutable-style: `push(string|int $segment): ParseContext`, `pointer(): string` (RFC 6901 JSON Pointer), `path(): string` (file path).
  - `ParserException extends ArazzoException` with named constructors: `missingField(ParseContext, string $field)`, `wrongType(ParseContext, string $expected, mixed $actual)`, `invalidEnum(ParseContext, string $expected, string $actual)`, `invalidActionType(ParseContext, string $actual)`.
  - `Parser` private helpers exposed as `protected` for test subclass:
    - `requireString(array $arr, string $key, ParseContext $ctx): string`
    - `optionalString(array $arr, string $key, ParseContext $ctx): ?string`
    - `optionalInt(array $arr, string $key, ParseContext $ctx): ?int`
    - `optionalBool(array $arr, string $key, ParseContext $ctx): ?bool`
    - `requireArray(array $arr, string $key, ParseContext $ctx): array`
    - `optionalArray(array $arr, string $key, ParseContext $ctx): ?array`
    - `requireObjectMap(mixed $node, ParseContext $ctx): array` (asserts assoc, not list)
    - `requireList(mixed $node, ParseContext $ctx): array`

- [ ] **Step 1: Write failing test for ParseContext**

Create `tests/Parser/ParseContextTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Parser\ParseContext;

it('builds JSON Pointer from segments', function (): void {
    $ctx = new ParseContext('/tmp/x.yaml');
    $sub = $ctx->push('workflows')->push(0)->push('steps')->push(2)->push('stepId');

    expect($sub->pointer())->toBe('/workflows/0/steps/2/stepId')
        ->and($sub->path())->toBe('/tmp/x.yaml');
});

it('escapes ~ and / per RFC 6901', function (): void {
    $ctx = (new ParseContext('/x'))->push('a/b')->push('c~d');
    expect($ctx->pointer())->toBe('/a~1b/c~0d');
});

it('root pointer is empty string', function (): void {
    expect((new ParseContext('/x'))->pointer())->toBe('');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement ParseContext**

`src/Parser/ParseContext.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Parser;

final class ParseContext
{
    /** @param list<string> $segments */
    public function __construct(
        private readonly string $filePath,
        private readonly array $segments = [],
    ) {}

    public function push(string|int $segment): self
    {
        $encoded = str_replace(['~', '/'], ['~0', '~1'], (string) $segment);
        return new self($this->filePath, [...$this->segments, $encoded]);
    }

    public function pointer(): string
    {
        return $this->segments === '' || $this->segments === [] ? '' : '/' . implode('/', $this->segments);
    }

    public function path(): string
    {
        return $this->filePath;
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: Implement ParserException**

`src/Exceptions/ParserException.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Exceptions;

use Alama\LaravelArazzo\Parser\ParseContext;

final class ParserException extends ArazzoException
{
    public static function missingField(ParseContext $ctx, string $field): self
    {
        $pointer = $ctx->push($field)->pointer();
        return new self("Missing required field: {$pointer}", $pointer, 'parser.missing_field');
    }

    public static function wrongType(ParseContext $ctx, string $expected, mixed $actual): self
    {
        $type = get_debug_type($actual);
        $pointer = $ctx->pointer();
        return new self("Expected {$expected} at {$pointer}, got {$type}", $pointer, 'parser.wrong_type');
    }

    public static function invalidEnum(ParseContext $ctx, string $expected, string $actual): self
    {
        $pointer = $ctx->pointer();
        return new self("Invalid value '{$actual}' at {$pointer}; expected one of {$expected}", $pointer, 'parser.invalid_enum');
    }

    public static function invalidActionType(ParseContext $ctx, string $actual): self
    {
        $pointer = $ctx->pointer();
        return new self("Invalid action type '{$actual}' at {$pointer}", $pointer, 'parser.invalid_action_type');
    }
}
```

- [ ] **Step 6: Write failing tests for helpers**

Create `tests/Parser/ParserHelpersTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

/** Test double exposing protected helpers. */
class ParserProbe extends Parser
{
    /** @param array<string,mixed> $arr */
    public function reqStr(array $arr, string $k, ParseContext $c): string { return $this->requireString($arr, $k, $c); }
    /** @param array<string,mixed> $arr */
    public function optStr(array $arr, string $k, ParseContext $c): ?string { return $this->optionalString($arr, $k, $c); }
    /** @param array<string,mixed> $arr */
    public function reqArr(array $arr, string $k, ParseContext $c): array { return $this->requireArray($arr, $k, $c); }
    public function reqObj(mixed $n, ParseContext $c): array { return $this->requireObjectMap($n, $c); }
    public function reqList(mixed $n, ParseContext $c): array { return $this->requireList($n, $c); }
}

it('requireString returns value', function (): void {
    $p = new ParserProbe();
    expect($p->reqStr(['a' => 'x'], 'a', new ParseContext('/x')))->toBe('x');
});

it('requireString throws on missing', function (): void {
    (new ParserProbe())->reqStr([], 'a', new ParseContext('/x'));
})->throws(ParserException::class, 'Missing required field');

it('requireString throws on wrong type', function (): void {
    (new ParserProbe())->reqStr(['a' => 1], 'a', new ParseContext('/x'));
})->throws(ParserException::class, 'Expected string');

it('optionalString returns null when absent', function (): void {
    expect((new ParserProbe())->optStr([], 'a', new ParseContext('/x')))->toBeNull();
});

it('requireObjectMap rejects lists', function (): void {
    (new ParserProbe())->reqObj([1,2,3], new ParseContext('/x'));
})->throws(ParserException::class, 'Expected object');

it('requireList rejects assoc arrays', function (): void {
    (new ParserProbe())->reqList(['a' => 1], new ParseContext('/x'));
})->throws(ParserException::class, 'Expected list');
```

- [ ] **Step 7: Run — expect fail**

- [ ] **Step 8: Implement Parser skeleton with helpers**

`src/Parser/Parser.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Parser;

use Alama\LaravelArazzo\Exceptions\ParserException;

class Parser
{
    /** @param array<string,mixed> $arr */
    protected function requireString(array $arr, string $key, ParseContext $ctx): string
    {
        if (!array_key_exists($key, $arr)) {
            throw ParserException::missingField($ctx, $key);
        }
        $v = $arr[$key];
        if (!is_string($v)) {
            throw ParserException::wrongType($ctx->push($key), 'string', $v);
        }
        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalString(array $arr, string $key, ParseContext $ctx): ?string
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_string($v)) {
            throw ParserException::wrongType($ctx->push($key), 'string', $v);
        }
        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalInt(array $arr, string $key, ParseContext $ctx): ?int
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_int($v)) throw ParserException::wrongType($ctx->push($key), 'int', $v);
        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalBool(array $arr, string $key, ParseContext $ctx): ?bool
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_bool($v)) throw ParserException::wrongType($ctx->push($key), 'bool', $v);
        return $v;
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<int|string,mixed>
     */
    protected function requireArray(array $arr, string $key, ParseContext $ctx): array
    {
        if (!array_key_exists($key, $arr)) throw ParserException::missingField($ctx, $key);
        $v = $arr[$key];
        if (!is_array($v)) throw ParserException::wrongType($ctx->push($key), 'array', $v);
        return $v;
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<int|string,mixed>|null
     */
    protected function optionalArray(array $arr, string $key, ParseContext $ctx): ?array
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_array($v)) throw ParserException::wrongType($ctx->push($key), 'array', $v);
        return $v;
    }

    /** @return array<string,mixed> */
    protected function requireObjectMap(mixed $node, ParseContext $ctx): array
    {
        if (!is_array($node) || (array_is_list($node) && $node !== [])) {
            throw ParserException::wrongType($ctx, 'object', $node);
        }
        /** @var array<string,mixed> $node */
        return $node;
    }

    /** @return list<mixed> */
    protected function requireList(mixed $node, ParseContext $ctx): array
    {
        if (!is_array($node) || (!array_is_list($node) && $node !== [])) {
            throw ParserException::wrongType($ctx, 'list', $node);
        }
        return array_values($node);
    }
}
```

- [ ] **Step 9: Run — expect pass**

- [ ] **Step 10: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: add parser infrastructure (ParseContext, exception, helpers)"
```

---

