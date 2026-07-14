### Task 14: Validation infrastructure — Rule, Error, Warning, ErrorCollector, ValidationResult, RuleSet, Validator

**Files:**
- Create: `src/Validation/Error.php`, `src/Validation/Warning.php`
- Create: `src/Validation/ErrorCollector.php`
- Create: `src/Validation/ValidationResult.php`
- Create: `src/Validation/Rule.php`
- Create: `src/Validation/RuleSet.php`
- Create: `src/Validation/Validator.php`
- Create: `src/Exceptions/ValidationException.php`
- Create: `tests/Validation/ValidatorTest.php`
- Create: `tests/Validation/RuleSetTest.php`

**Interfaces:**
- Produces:
  - `final readonly class Error(string $code, string $message, string $path, ?int $line = null)`
  - `final readonly class Warning(string $code, string $message, string $path, ?int $line = null)`
  - `ErrorCollector` — mutable during a `check()` call; `error(string $code, string $message, string $path, ?int $line = null): void`, `warning(...)`, `errors(): list<Error>`, `warnings(): list<Warning>`.
  - `interface Rule { public function code(): string; public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void; }`
  - `RuleSet::default(array $disabled = [], bool $strict = true): self` — factory returning empty list for now (tasks 15–20 populate it).
  - `RuleSet::withRule(Rule $r): self` (immutable), `RuleSet::rules(): list<Rule>`, `RuleSet::isStrict(): bool`.
  - `Validator(RuleSet $rules)` — `validate(ArazzoDocument $doc): ValidationResult`.
  - `ValidationResult(ArazzoDocument $document, list<Error> $errors, list<Warning> $warnings) { isValid(): bool; toArray(): array; }`
  - `ValidationException` (unused until Task 24 wires `assertValid`).

- [ ] **Step 1: Write failing test**

Create `tests/Validation/RuleSetTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\RuleSet;

class DummyRule implements Rule
{
    public function __construct(private readonly string $c) {}
    public function code(): string { return $this->c; }
    public function check(ArazzoDocument $d, SymbolTable $s, ErrorCollector $e): void
    {
        $e->error($this->c, 'boom', '/');
    }
}

it('is immutable — withRule returns new instance', function (): void {
    $a = new RuleSet([]);
    $b = $a->withRule(new DummyRule('x'));
    expect($a->rules())->toBe([])
        ->and($b->rules())->toHaveCount(1);
});

it('honours disabled list', function (): void {
    $set = RuleSet::default(disabled: ['x'], strict: false)
        ->withRule(new DummyRule('x'))
        ->withRule(new DummyRule('y'));
    $codes = array_map(fn(Rule $r) => $r->code(), $set->activeRules());
    expect($codes)->toBe(['y']);
});
```

Create `tests/Validation/ValidatorTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\RuleSet;
use Alama\LaravelArazzo\Validation\Validator;

class RecordingRule implements Rule
{
    public function code(): string { return 'r.a'; }
    public function check(ArazzoDocument $d, SymbolTable $s, ErrorCollector $e): void
    {
        $e->error('r.a', 'msg', '/foo');
        $e->warning('r.a.warn', 'wmsg', '/bar');
    }
}

it('collects errors and warnings', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $result = (new Validator(new RuleSet([new RecordingRule()])))->validate($doc);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('r.a')
        ->and($result->warnings)->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement value objects**

`src/Validation/Error.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final readonly class Error
{
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
        public ?int $line = null,
    ) {}

    /** @return array{code:string,message:string,path:string,line:?int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'path' => $this->path, 'line' => $this->line];
    }
}
```

`src/Validation/Warning.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final readonly class Warning
{
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
        public ?int $line = null,
    ) {}

    /** @return array{code:string,message:string,path:string,line:?int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'path' => $this->path, 'line' => $this->line];
    }
}
```

`src/Validation/ErrorCollector.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final class ErrorCollector
{
    /** @var list<Error> */
    private array $errors = [];
    /** @var list<Warning> */
    private array $warnings = [];

    public function error(string $code, string $message, string $path, ?int $line = null): void
    {
        $this->errors[] = new Error($code, $message, $path, $line);
    }

    public function warning(string $code, string $message, string $path, ?int $line = null): void
    {
        $this->warnings[] = new Warning($code, $message, $path, $line);
    }

    /** @return list<Error> */
    public function errors(): array { return $this->errors; }
    /** @return list<Warning> */
    public function warnings(): array { return $this->warnings; }
}
```

`src/Validation/ValidationResult.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

use Alama\LaravelArazzo\Dto\ArazzoDocument;

final readonly class ValidationResult
{
    /**
     * @param list<Error>   $errors
     * @param list<Warning> $warnings
     */
    public function __construct(
        public ArazzoDocument $document,
        public array $errors,
        public array $warnings,
    ) {}

    public function isValid(): bool { return $this->errors === []; }

    /** @return array{valid:bool,errors:list<array{code:string,message:string,path:string,line:?int}>,warnings:list<array{code:string,message:string,path:string,line:?int}>} */
    public function toArray(): array
    {
        return [
            'valid'    => $this->isValid(),
            'errors'   => array_map(fn(Error $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn(Warning $w) => $w->toArray(), $this->warnings),
        ];
    }
}
```

- [ ] **Step 4: Implement Rule + RuleSet + Validator**

`src/Validation/Rule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;

interface Rule
{
    public function code(): string;

    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
```

`src/Validation/RuleSet.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final readonly class RuleSet
{
    /**
     * @param list<Rule>    $rules
     * @param list<string>  $disabled
     */
    public function __construct(
        public array $rules,
        public array $disabled = [],
        public bool $strict = true,
    ) {}

    /**
     * @param list<string> $disabled
     */
    public static function default(array $disabled = [], bool $strict = true): self
    {
        return new self([], $disabled, $strict);
    }

    public function withRule(Rule $rule): self
    {
        return new self([...$this->rules, $rule], $this->disabled, $this->strict);
    }

    /** @return list<Rule> */
    public function rules(): array { return $this->rules; }

    /** @return list<Rule> */
    public function activeRules(): array
    {
        return array_values(array_filter(
            $this->rules,
            fn(Rule $r) => !in_array($r->code(), $this->disabled, true),
        ));
    }

    public function isStrict(): bool { return $this->strict; }
}
```

`src/Validation/Validator.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;

final class Validator
{
    public function __construct(private readonly RuleSet $rules) {}

    public function validate(ArazzoDocument $doc): ValidationResult
    {
        $symbols   = SymbolTable::build($doc);
        $collector = new ErrorCollector();

        foreach ($this->rules->activeRules() as $rule) {
            $rule->check($doc, $symbols, $collector);
        }

        return new ValidationResult($doc, $collector->errors(), $collector->warnings());
    }
}
```

`src/Exceptions/ValidationException.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Exceptions;

use Alama\LaravelArazzo\Validation\ValidationResult;

final class ValidationException extends ArazzoException
{
    public function __construct(public readonly ValidationResult $result)
    {
        $count = count($result->errors);
        parent::__construct("Arazzo document failed validation with {$count} error(s).", '', 'validation.failed');
    }
}
```

- [ ] **Step 5: Run — expect pass**

- [ ] **Step 6: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: validation infrastructure (Rule, RuleSet, Validator, ValidationResult)"
```

---

