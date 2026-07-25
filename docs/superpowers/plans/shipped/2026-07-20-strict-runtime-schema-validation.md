# Strict Runtime Schema Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an opt-in, fail-fast validator that checks an API response body against its declared OpenAPI schema right after the HTTP call, on both the sync (`StepExecutor`) and async (`HttpStepExecutor`) execution paths, plus a parse-time rule rejecting unsupported `xpath` version pins.

**Architecture:** A new stateless `SchemaValidator` walks a `cebe\openapi\spec\Schema` against a decoded JSON value and returns a list of violations (no dependency added — same hand-rolled style as `TypeCaster`/`JsonPointer`). A new `SchemaValidationException` wraps non-empty violations. `ExpressionResolverInterface` gains `validateResponseSchema()`, implemented in `ArazzoExpressionResolver` by reusing the response-schema lookup already inside `castOutputAgainstResponseSchema()`. Both `StepExecutor` and `HttpStepExecutor` call it, gated by a config-default-plus-per-step-override flag threaded in as a constructor `bool`, matching how `retry_ceiling`/`state_ttl` are already wired from `config()` in the service provider. Separately, `SuccessCriterion` gains a `version` field and a new validation rule rejects `xpath-30`/`xpath-31`.

**Tech Stack:** PHP 8.4, `cebe/php-openapi` (already a dependency, no new one added), Pest v4 for tests.

## Global Constraints

- No new Composer dependency — `SchemaValidator` is hand-rolled against `cebe\openapi\spec\Schema`.
- Response-body validation only — request bodies are never validated by this feature.
- A schema violation is a hard, uncaught `SchemaValidationException` — never converted into a soft step-failure routed through `onFailure`/retry.
- `exclusiveMinimum`/`exclusiveMaximum` are OAS 3.0 **booleans** modifying `minimum`/`maximum` — not standalone numeric bounds.
- Unrecognized `format` values are silently ignored (annotation, not a MUST-validate keyword) — never a violation.
- `oneOf` requires **exactly one** matching subschema; `anyOf` requires **at least one**. Don't conflate them.
- Validation is off by default (`config('arazzo.strict_schema_validation')` defaults to `false`); a step's `x-strict-validation` extension overrides the default when set.
- Both `StepExecutor::execute()` (sync) and `HttpStepExecutor::execute()` (async, live since 03 merged) must call the new validation — this is not sync-path-only.
- `Execution/` namespace classes are framework-agnostic: config values are read via `config()` **only inside `LaravelArazzoServiceProvider`** and passed into constructors as primitives (e.g. `(bool) config('arazzo.strict_schema_validation', false)`), never via a `config()` call inside `src/Execution/*.php`.

---

## Task 1: `SchemaValidator` — type, enum, nullable

**Files:**
- Create: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `cebe\openapi\spec\Schema` (already a project dependency).
- Produces: `SchemaValidator::validate(Schema $schema, mixed $value, string $path = ''): array` returning `list<array{path: string, message: string}>` — empty list means valid. Consumed by Task 2-6 (same method, extended) and Task 8 (`ArazzoExpressionResolver::validateResponseSchema()`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/SchemaValidatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Execution\SchemaValidator;
use cebe\openapi\spec\Schema;

it('passes a matching type with no violations', function (): void {
    $schema = new Schema(['type' => 'integer']);

    expect(SchemaValidator::validate($schema, 42))->toBe([]);
});

it('flags a type mismatch', function (): void {
    $schema = new Schema(['type' => 'integer']);

    $violations = SchemaValidator::validate($schema, 'not-an-int');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/')
        ->and($violations[0]['message'])->toContain('integer');
});

it('accepts number type for both int and float', function (): void {
    $schema = new Schema(['type' => 'number']);

    expect(SchemaValidator::validate($schema, 3))->toBe([]);
    expect(SchemaValidator::validate($schema, 3.14))->toBe([]);
});

it('flags a value outside the enum', function (): void {
    $schema = new Schema(['type' => 'string', 'enum' => ['a', 'b']]);

    expect(SchemaValidator::validate($schema, 'c'))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 'a'))->toBe([]);
});

it('rejects null when not nullable', function (): void {
    $schema = new Schema(['type' => 'string']);

    $violations = SchemaValidator::validate($schema, null);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['message'])->toContain('null');
});

it('accepts null when nullable is true', function (): void {
    $schema = new Schema(['type' => 'string', 'nullable' => true]);

    expect(SchemaValidator::validate($schema, null))->toBe([]);
});

it('accepts null when no type is declared at all', function (): void {
    $schema = new Schema([]);

    expect(SchemaValidator::validate($schema, null))->toBe([]);
});

it('path-qualifies violations at a nested path argument', function (): void {
    $schema = new Schema(['type' => 'integer']);

    $violations = SchemaValidator::validate($schema, 'x', '/user/age');

    expect($violations[0]['path'])->toBe('/user/age');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL with `Class "Alama\LaravelArazzo\Execution\SchemaValidator" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Execution/SchemaValidator.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

final class SchemaValidator
{
    /**
     * @return list<array{path: string, message: string}>
     */
    public static function validate(Schema $schema, mixed $value, string $path = ''): array
    {
        $at = $path === '' ? '/' : $path;

        if ($value === null) {
            if ($schema->nullable === true || $schema->type === null) {
                return [];
            }

            return [['path' => $at, 'message' => 'must not be null']];
        }

        $violations = [];

        if ($schema->type !== null && !self::matchesType($schema->type, $value)) {
            $violations[] = ['path' => $at, 'message' => "expected type '{$schema->type}', got " . get_debug_type($value)];
        }

        if ($schema->enum !== [] && !in_array($value, $schema->enum, true)) {
            $violations[] = ['path' => $at, 'message' => 'value is not one of the allowed enum values'];
        }

        return $violations;
    }

    private static function matchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            // json_decode(..., true) can't tell an empty object `{}` from an empty array
            // `[]` -- both decode to `[]`, so an empty array is accepted as either shape.
            'object' => is_array($value) && (!array_is_list($value) || $value === []),
            default => true,
        };
    }

    private static function resolveSchema(mixed $schemaOrRef): ?Schema
    {
        if ($schemaOrRef instanceof Reference) {
            $schemaOrRef = $schemaOrRef->resolve();
        }

        return $schemaOrRef instanceof Schema ? $schemaOrRef : null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: add SchemaValidator with type/enum/nullable checks"
```

---

## Task 2: `SchemaValidator` — required/properties (object) and items (array) recursion

**Files:**
- Modify: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `SchemaValidator::validate()` from Task 1, `resolveSchema()` (private helper from Task 1).
- Produces: recursive `properties`/`items` checking — no new public surface, extends `validate()`'s behavior.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/SchemaValidatorTest.php`:

```php
it('flags a missing required property', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'required' => ['id'],
        'properties' => ['id' => ['type' => 'integer']],
    ]);

    $violations = SchemaValidator::validate($schema, ['name' => 'x']);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/id')
        ->and($violations[0]['message'])->toContain('id');
});

it('recurses into a nested object property and path-qualifies the violation', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'properties' => [
            'user' => [
                'type' => 'object',
                'properties' => ['age' => ['type' => 'integer']],
            ],
        ],
    ]);

    $violations = SchemaValidator::validate($schema, ['user' => ['age' => 'old']]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/user/age');
});

it('does not check a property absent from the value', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'properties' => ['age' => ['type' => 'integer']],
    ]);

    expect(SchemaValidator::validate($schema, []))->toBe([]);
});

it('recurses into array items and path-qualifies by index', function (): void {
    $schema = new Schema([
        'type' => 'array',
        'items' => ['type' => 'integer'],
    ]);

    $violations = SchemaValidator::validate($schema, [1, 'two', 3]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/1');
});

it('checks required/minItems against an empty array, ambiguous with empty object', function (): void {
    $schema = new Schema([
        'required' => ['id'],
        'minItems' => 1,
    ]);

    $violations = SchemaValidator::validate($schema, []);

    $messages = array_column($violations, 'message');
    expect($messages)->toContain('missing required property \'id\'');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL — the last 5 tests fail (missing required-property/properties/items support); `minItems` assertion fails too (implemented in Task 5, so for now only assert the `required` message is present — the test as written only checks `required`, which is enough to drive this task; `minItems` itself isn't implemented until Task 5, that's fine, this test doesn't assert on it).

- [ ] **Step 3: Extend the implementation**

In `src/Execution/SchemaValidator.php`, replace the `validate()` method:

```php
    public static function validate(Schema $schema, mixed $value, string $path = ''): array
    {
        $at = $path === '' ? '/' : $path;

        if ($value === null) {
            if ($schema->nullable === true || $schema->type === null) {
                return [];
            }

            return [['path' => $at, 'message' => 'must not be null']];
        }

        $violations = [];

        if ($schema->type !== null && !self::matchesType($schema->type, $value)) {
            $violations[] = ['path' => $at, 'message' => "expected type '{$schema->type}', got " . get_debug_type($value)];
        }

        if ($schema->enum !== [] && !in_array($value, $schema->enum, true)) {
            $violations[] = ['path' => $at, 'message' => 'value is not one of the allowed enum values'];
        }

        if (is_array($value)) {
            if ($value === [] || !array_is_list($value)) {
                $violations = [...$violations, ...self::validateObject($schema, $value, $path)];
            }
            if ($value === [] || array_is_list($value)) {
                $violations = [...$violations, ...self::validateArray($schema, $value, $path)];
            }
        }

        return $violations;
    }

    /**
     * @param array<string,mixed> $value
     *
     * @return list<array{path: string, message: string}>
     */
    private static function validateObject(Schema $schema, array $value, string $path): array
    {
        $violations = [];

        foreach ($schema->required as $requiredName) {
            if (!array_key_exists($requiredName, $value)) {
                $violations[] = [
                    'path' => $path . '/' . $requiredName,
                    'message' => "missing required property '{$requiredName}'",
                ];
            }
        }

        foreach ($schema->properties as $name => $propSchema) {
            if (!array_key_exists($name, $value)) {
                continue;
            }
            $resolved = self::resolveSchema($propSchema);
            if ($resolved === null) {
                continue;
            }
            $violations = [...$violations, ...self::validate($resolved, $value[$name], $path . '/' . $name)];
        }

        return $violations;
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<array{path: string, message: string}>
     */
    private static function validateArray(Schema $schema, array $value, string $path): array
    {
        $violations = [];

        $itemSchema = self::resolveSchema($schema->items);
        if ($itemSchema !== null) {
            foreach (array_values($value) as $index => $item) {
                $violations = [...$violations, ...self::validate($itemSchema, $item, $path . '/' . $index)];
            }
        }

        return $violations;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (13 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: recurse SchemaValidator into object properties and array items"
```

---

## Task 3: `SchemaValidator` — `pattern` and `format`

**Files:**
- Modify: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `validate()`'s dispatch structure from Task 1-2.
- Produces: string-keyword checking, folded into `validate()`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/SchemaValidatorTest.php`:

```php
it('flags a string not matching pattern', function (): void {
    $schema = new Schema(['type' => 'string', 'pattern' => '^[a-z]+$']);

    expect(SchemaValidator::validate($schema, 'abc'))->toBe([]);
    expect(SchemaValidator::validate($schema, 'ABC'))->toHaveCount(1);
});

it('validates known formats: date, date-time, email, uuid, uri, ipv4, ipv6', function (): void {
    $format = fn (string $fmt) => new Schema(['type' => 'string', 'format' => $fmt]);

    expect(SchemaValidator::validate($format('date'), '2024-01-15'))->toBe([]);
    expect(SchemaValidator::validate($format('date'), '2024-02-30'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('date-time'), '2024-01-15T10:00:00+00:00'))->toBe([]);
    expect(SchemaValidator::validate($format('date-time'), 'not-a-datetime'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('email'), 'a@b.com'))->toBe([]);
    expect(SchemaValidator::validate($format('email'), 'not-an-email'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('uuid'), '550e8400-e29b-41d4-a716-446655440000'))->toBe([]);
    expect(SchemaValidator::validate($format('uuid'), 'not-a-uuid'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('uri'), 'https://example.com'))->toBe([]);
    expect(SchemaValidator::validate($format('uri'), 'not a uri'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('ipv4'), '192.168.1.1'))->toBe([]);
    expect(SchemaValidator::validate($format('ipv4'), 'not-an-ip'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('ipv6'), '::1'))->toBe([]);
    expect(SchemaValidator::validate($format('ipv6'), 'not-an-ip'))->toHaveCount(1);
});

it('ignores an unrecognized format rather than flagging a violation', function (): void {
    $schema = new Schema(['type' => 'string', 'format' => 'password']);

    expect(SchemaValidator::validate($schema, 'anything at all'))->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL — `pattern`/`format` cases fail (no checks implemented yet).

- [ ] **Step 3: Extend the implementation**

In `src/Execution/SchemaValidator.php`, add a string-keyword branch inside `validate()` — insert right after the `is_array($value)` block:

```php
        if (is_string($value)) {
            $violations = [...$violations, ...self::validateString($schema, $value, $path)];
        }
```

Then add the new private methods (place after `validateArray`):

```php
    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateString(Schema $schema, string $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->pattern !== null && @preg_match('/' . str_replace('/', '\/', $schema->pattern) . '/u', $value) !== 1) {
            $violations[] = ['path' => $at, 'message' => "does not match pattern '{$schema->pattern}'"];
        }

        if ($schema->format !== null && !self::matchesFormat($schema->format, $value)) {
            $violations[] = ['path' => $at, 'message' => "does not match format '{$schema->format}'"];
        }

        return $violations;
    }

    private static function matchesFormat(string $format, string $value): bool
    {
        return match ($format) {
            'date' => self::isValidDateTime($value, 'Y-m-d'),
            'date-time' => self::isValidDateTime($value, \DateTimeInterface::RFC3339_EXTENDED)
                || self::isValidDateTime($value, \DateTimeInterface::RFC3339),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'ipv4' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            // Unrecognized formats (password, byte, int64, vendor-specific, ...) are
            // annotations per the JSON Schema spec -- not validated, never a violation.
            default => true,
        };
    }

    private static function isValidDateTime(string $value, string $format): bool
    {
        $date = \DateTime::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (16 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: add pattern and format checks to SchemaValidator"
```

---

## Task 4: `SchemaValidator` — numeric bounds (`minimum`/`maximum`/`exclusiveMinimum`/`exclusiveMaximum`/`multipleOf`)

**Files:**
- Modify: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `validate()`'s dispatch structure.
- Produces: numeric-bound checking, folded into `validate()`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/SchemaValidatorTest.php`:

```php
it('enforces inclusive minimum/maximum by default', function (): void {
    $schema = new Schema(['type' => 'integer', 'minimum' => 1, 'maximum' => 10]);

    expect(SchemaValidator::validate($schema, 1))->toBe([]);
    expect(SchemaValidator::validate($schema, 10))->toBe([]);
    expect(SchemaValidator::validate($schema, 0))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 11))->toHaveCount(1);
});

it('enforces exclusiveMinimum/exclusiveMaximum as OAS 3.0 booleans', function (): void {
    $schema = new Schema([
        'type' => 'integer',
        'minimum' => 1,
        'exclusiveMinimum' => true,
        'maximum' => 10,
        'exclusiveMaximum' => true,
    ]);

    expect(SchemaValidator::validate($schema, 1))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 10))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 5))->toBe([]);
});

it('enforces multipleOf', function (): void {
    $schema = new Schema(['type' => 'integer', 'multipleOf' => 5]);

    expect(SchemaValidator::validate($schema, 15))->toBe([]);
    expect(SchemaValidator::validate($schema, 16))->toHaveCount(1);
});

it('tolerates float rounding drift in multipleOf', function (): void {
    $schema = new Schema(['type' => 'number', 'multipleOf' => 0.1]);

    expect(SchemaValidator::validate($schema, 0.3))->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL — bound checks not implemented yet.

- [ ] **Step 3: Extend the implementation**

In `src/Execution/SchemaValidator.php`, add a numeric branch inside `validate()`, right after the `is_string($value)` block:

```php
        if (is_int($value) || is_float($value)) {
            $violations = [...$violations, ...self::validateNumber($schema, $value, $path)];
        }
```

Add the new private method (after `validateString`/`matchesFormat`/`isValidDateTime`):

```php
    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateNumber(Schema $schema, int|float $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->minimum !== null) {
            $exclusive = $schema->exclusiveMinimum === true;
            if ($exclusive ? $value <= $schema->minimum : $value < $schema->minimum) {
                $bound = $exclusive ? 'greater than' : 'at least';
                $violations[] = ['path' => $at, 'message' => "value must be {$bound} {$schema->minimum}"];
            }
        }

        if ($schema->maximum !== null) {
            $exclusive = $schema->exclusiveMaximum === true;
            if ($exclusive ? $value >= $schema->maximum : $value > $schema->maximum) {
                $bound = $exclusive ? 'less than' : 'at most';
                $violations[] = ['path' => $at, 'message' => "value must be {$bound} {$schema->maximum}"];
            }
        }

        if ($schema->multipleOf !== null && (float) $schema->multipleOf !== 0.0) {
            $remainder = fmod((float) $value, (float) $schema->multipleOf);
            $nearZero = abs($remainder) < 1e-9;
            $nearDivisor = abs(abs($remainder) - abs((float) $schema->multipleOf)) < 1e-9;
            if (!$nearZero && !$nearDivisor) {
                $violations[] = ['path' => $at, 'message' => "value must be a multiple of {$schema->multipleOf}"];
            }
        }

        return $violations;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (20 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: add numeric bounds checks to SchemaValidator"
```

---

## Task 5: `SchemaValidator` — length/collection bounds (`minLength`/`maxLength`/`minItems`/`maxItems`/`uniqueItems`/`minProperties`/`maxProperties`)

**Files:**
- Modify: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `validateString()` (Task 3), `validateObject()`/`validateArray()` (Task 2).
- Produces: extends those three methods with the remaining bound keywords.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/SchemaValidatorTest.php`:

```php
it('enforces minLength/maxLength', function (): void {
    $schema = new Schema(['type' => 'string', 'minLength' => 2, 'maxLength' => 4]);

    expect(SchemaValidator::validate($schema, 'abc'))->toBe([]);
    expect(SchemaValidator::validate($schema, 'a'))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 'abcde'))->toHaveCount(1);
});

it('enforces minItems/maxItems on a non-empty array', function (): void {
    $schema = new Schema(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 2, 'maxItems' => 3]);

    expect(SchemaValidator::validate($schema, [1, 2]))->toBe([]);
    expect(SchemaValidator::validate($schema, [1]))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, [1, 2, 3, 4]))->toHaveCount(1);
});

it('enforces minItems against a genuinely empty array', function (): void {
    $schema = new Schema(['type' => 'array', 'minItems' => 1]);

    expect(SchemaValidator::validate($schema, []))->toHaveCount(1);
});

it('enforces uniqueItems', function (): void {
    $schema = new Schema(['type' => 'array', 'items' => ['type' => 'integer'], 'uniqueItems' => true]);

    expect(SchemaValidator::validate($schema, [1, 2, 3]))->toBe([]);
    expect(SchemaValidator::validate($schema, [1, 2, 2]))->toHaveCount(1);
});

it('enforces minProperties/maxProperties', function (): void {
    $schema = new Schema(['type' => 'object', 'minProperties' => 1, 'maxProperties' => 2]);

    expect(SchemaValidator::validate($schema, ['a' => 1]))->toBe([]);
    expect(SchemaValidator::validate($schema, []))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, ['a' => 1, 'b' => 2, 'c' => 3]))->toHaveCount(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL — bound checks not implemented yet.

- [ ] **Step 3: Extend the implementation**

In `src/Execution/SchemaValidator.php`, update `validateString()` to add length checks at the top of the method body (before the `pattern` check):

```php
        if ($schema->minLength !== null && mb_strlen($value) < $schema->minLength) {
            $violations[] = ['path' => $at, 'message' => "expected at least {$schema->minLength} characters, got " . mb_strlen($value)];
        }
        if ($schema->maxLength !== null && mb_strlen($value) > $schema->maxLength) {
            $violations[] = ['path' => $at, 'message' => "expected at most {$schema->maxLength} characters, got " . mb_strlen($value)];
        }
```

Update `validateArray()` to add bound checks before the `items` recursion:

```php
    private static function validateArray(Schema $schema, array $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->minItems !== null && count($value) < $schema->minItems) {
            $violations[] = ['path' => $at, 'message' => "expected at least {$schema->minItems} items, got " . count($value)];
        }
        if ($schema->maxItems !== null && count($value) > $schema->maxItems) {
            $violations[] = ['path' => $at, 'message' => "expected at most {$schema->maxItems} items, got " . count($value)];
        }
        if ($schema->uniqueItems === true && count($value) !== count(array_unique($value, SORT_REGULAR))) {
            $violations[] = ['path' => $at, 'message' => 'items must be unique'];
        }

        $itemSchema = self::resolveSchema($schema->items);
        if ($itemSchema !== null) {
            foreach (array_values($value) as $index => $item) {
                $violations = [...$violations, ...self::validate($itemSchema, $item, $path . '/' . $index)];
            }
        }

        return $violations;
    }
```

Replace `validateObject()` in full:

```php
    /**
     * @param array<string,mixed> $value
     *
     * @return list<array{path: string, message: string}>
     */
    private static function validateObject(Schema $schema, array $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        if ($schema->minProperties !== null && count($value) < $schema->minProperties) {
            $violations[] = ['path' => $at, 'message' => "expected at least {$schema->minProperties} properties, got " . count($value)];
        }
        if ($schema->maxProperties !== null && count($value) > $schema->maxProperties) {
            $violations[] = ['path' => $at, 'message' => "expected at most {$schema->maxProperties} properties, got " . count($value)];
        }

        foreach ($schema->required as $requiredName) {
            if (!array_key_exists($requiredName, $value)) {
                $violations[] = [
                    'path' => $path . '/' . $requiredName,
                    'message' => "missing required property '{$requiredName}'",
                ];
            }
        }

        foreach ($schema->properties as $name => $propSchema) {
            if (!array_key_exists($name, $value)) {
                continue;
            }
            $resolved = self::resolveSchema($propSchema);
            if ($resolved === null) {
                continue;
            }
            $violations = [...$violations, ...self::validate($resolved, $value[$name], $path . '/' . $name)];
        }

        return $violations;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (25 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: add length/collection bounds checks to SchemaValidator"
```

---

## Task 6: `SchemaValidator` — `allOf`/`oneOf`/`anyOf` composition

**Files:**
- Modify: `src/Execution/SchemaValidator.php`
- Test: `tests/Execution/SchemaValidatorTest.php`

**Interfaces:**
- Consumes: `validate()`, `resolveSchema()`.
- Produces: composition checking, folded into `validate()`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/SchemaValidatorTest.php`:

```php
it('allOf requires every subschema to pass', function (): void {
    $schema = new Schema([
        'allOf' => [
            ['type' => 'object', 'required' => ['id']],
            ['type' => 'object', 'required' => ['name']],
        ],
    ]);

    expect(SchemaValidator::validate($schema, ['id' => 1, 'name' => 'x']))->toBe([]);
    expect(SchemaValidator::validate($schema, ['id' => 1]))->toHaveCount(1);
});

it('anyOf requires at least one matching subschema', function (): void {
    $schema = new Schema([
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);

    expect(SchemaValidator::validate($schema, 'x'))->toBe([]);
    expect(SchemaValidator::validate($schema, 1))->toBe([]);
    expect(SchemaValidator::validate($schema, true))->toHaveCount(1);
});

it('oneOf requires exactly one matching subschema, not zero and not more than one', function (): void {
    $schema = new Schema([
        'oneOf' => [
            ['type' => 'integer', 'minimum' => 10],
            ['type' => 'integer', 'maximum' => 5],
        ],
    ]);

    // matches only the first branch (>= 10)
    expect(SchemaValidator::validate($schema, 20))->toBe([]);
    // matches only the second branch (<= 5)
    expect(SchemaValidator::validate($schema, 1))->toBe([]);
    // matches neither branch
    expect(SchemaValidator::validate($schema, 7))->toHaveCount(1);

    $ambiguous = new Schema([
        'oneOf' => [
            ['type' => 'integer'],
            ['type' => 'integer', 'minimum' => 1],
        ],
    ]);
    // matches both branches -- oneOf must reject this
    expect(SchemaValidator::validate($ambiguous, 5))->toHaveCount(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: FAIL — composition not implemented yet.

- [ ] **Step 3: Extend the implementation**

In `src/Execution/SchemaValidator.php`, add a composition call at the end of `validate()`, right before its final `return $violations;`:

```php
        $violations = [...$violations, ...self::validateComposition($schema, $value, $path)];

        return $violations;
```

Add the new private method (after `validateNumber`):

```php
    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateComposition(Schema $schema, mixed $value, string $path): array
    {
        $at = $path === '' ? '/' : $path;
        $violations = [];

        foreach ($schema->allOf as $sub) {
            $resolved = self::resolveSchema($sub);
            if ($resolved !== null) {
                $violations = [...$violations, ...self::validate($resolved, $value, $path)];
            }
        }

        if ($schema->anyOf !== []) {
            $matched = false;
            foreach ($schema->anyOf as $sub) {
                $resolved = self::resolveSchema($sub);
                if ($resolved !== null && self::validate($resolved, $value, $path) === []) {
                    $matched = true;

                    break;
                }
            }
            if (!$matched) {
                $violations[] = ['path' => $at, 'message' => 'value did not match any of ' . count($schema->anyOf) . ' anyOf schemas'];
            }
        }

        if ($schema->oneOf !== []) {
            $matchCount = 0;
            foreach ($schema->oneOf as $sub) {
                $resolved = self::resolveSchema($sub);
                if ($resolved !== null && self::validate($resolved, $value, $path) === []) {
                    $matchCount++;
                }
            }
            if ($matchCount === 0) {
                $violations[] = ['path' => $at, 'message' => 'value matched none of ' . count($schema->oneOf) . ' oneOf schemas'];
            } elseif ($matchCount > 1) {
                $violations[] = ['path' => $at, 'message' => "value matched {$matchCount} of " . count($schema->oneOf) . ' oneOf schemas, expected exactly one'];
            }
        }

        return $violations;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/SchemaValidatorTest.php`
Expected: PASS (28 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Execution/SchemaValidator.php tests/Execution/SchemaValidatorTest.php
git commit -m "feat: add allOf/oneOf/anyOf composition checks to SchemaValidator"
```

---

## Task 7: `SchemaValidationException`

**Files:**
- Create: `src/Execution/Exceptions/SchemaValidationException.php`
- Test: `tests/Execution/Exceptions/SchemaValidationExceptionTest.php`

**Interfaces:**
- Consumes: `list<array{path: string, message: string}>` shape from `SchemaValidator::validate()` (Task 1-6).
- Produces: `SchemaValidationException` with public readonly `stepId: string`, `statusCode: int`, `violations: list<array{path: string, message: string}>`. Consumed by Task 8 (`ArazzoExpressionResolver::validateResponseSchema()`).

- [ ] **Step 1: Write the failing test**

Create `tests/Execution/Exceptions/SchemaValidationExceptionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution\Exceptions;

use Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException;

it('builds a message from stepId, statusCode, and violations', function (): void {
    $exception = new SchemaValidationException('createOrder', 200, [
        ['path' => '/total', 'message' => "expected type 'number', got string"],
        ['path' => '/status', 'message' => "missing required property 'status'"],
    ]);

    expect($exception->stepId)->toBe('createOrder')
        ->and($exception->statusCode)->toBe(200)
        ->and($exception->violations)->toHaveCount(2)
        ->and($exception->getMessage())->toContain("Response for step 'createOrder' (200) failed schema validation")
        ->and($exception->getMessage())->toContain('/total')
        ->and($exception->getMessage())->toContain('/status');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Execution/Exceptions/SchemaValidationExceptionTest.php`
Expected: FAIL with `Class "Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Execution/Exceptions/SchemaValidationException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Exceptions;

use RuntimeException;

final class SchemaValidationException extends RuntimeException
{
    /**
     * @param list<array{path: string, message: string}> $violations
     */
    public function __construct(
        public readonly string $stepId,
        public readonly int $statusCode,
        public readonly array $violations,
    ) {
        parent::__construct(self::formatMessage($stepId, $statusCode, $violations));
    }

    /**
     * @param list<array{path: string, message: string}> $violations
     */
    private static function formatMessage(string $stepId, int $statusCode, array $violations): string
    {
        $parts = array_map(
            static fn (array $v): string => "{$v['path']} {$v['message']}",
            $violations,
        );

        return "Response for step '{$stepId}' ({$statusCode}) failed schema validation: " . implode('; ', $parts);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Execution/Exceptions/SchemaValidationExceptionTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/Exceptions/SchemaValidationException.php tests/Execution/Exceptions/SchemaValidationExceptionTest.php
git commit -m "feat: add SchemaValidationException"
```

---

## Task 8: `ExpressionResolverInterface::validateResponseSchema()` + `ArazzoExpressionResolver` implementation

**Files:**
- Modify: `src/Execution/Contracts/ExpressionResolverInterface.php`
- Modify: `src/Execution/ArazzoExpressionResolver.php`
- Modify: `tests/Execution/HttpStepExecutorTest.php` (mock resolver, line ~20)
- Modify: `tests/Execution/AsyncApiStepExecutorTest.php` (mock resolver, line ~61)
- Modify: `tests/Execution/StepOutcomeHandlerTest.php` (mock resolver, line ~87)
- Modify: `tests/Execution/StepExecutionWorkerTest.php` (mock resolver, line ~73)
- Modify: `tests/Execution/CorrelationResumerTest.php` (mock resolver, line ~89)
- Test: `tests/Execution/ArazzoExpressionResolverTest.php`

**Interfaces:**
- Consumes: `SchemaValidator::validate()` (Task 1-6), `SchemaValidationException` (Task 7), `OpenApiParser::findOperation()` (existing).
- Produces: `ExpressionResolverInterface::validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void`, throwing `SchemaValidationException` on violations, no-op if no schema is resolvable. Consumed by Task 10 (`StepExecutor`) and Task 11 (`HttpStepExecutor`).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/ArazzoExpressionResolverTest.php` (uses the existing `$this->makeResolver`/`$this->makeDocument` helpers and the `createUser` operation fixture already defined in `beforeEach`, whose `201` response schema is `{type: object, properties: {id: {type: integer}}}`):

```php
it('validateResponseSchema is a no-op for a response matching its schema', function () {
    $resolver = ($this->makeResolver)();
    $document = ($this->makeDocument)();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('create-user', [
        'statusCode' => 201,
        'headers' => [],
        'body' => ['id' => 123],
    ]);

    $resolver->validateResponseSchema($step, $context, $document);
})->throwsNoExceptions();

it('validateResponseSchema throws SchemaValidationException for a type mismatch', function () {
    $resolver = ($this->makeResolver)();
    $document = ($this->makeDocument)();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('create-user', [
        'statusCode' => 201,
        'headers' => [],
        'body' => ['id' => 'not-an-integer'],
    ]);

    try {
        $resolver->validateResponseSchema($step, $context, $document);
        $this->fail('Expected SchemaValidationException to be thrown.');
    } catch (\Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException $e) {
        expect($e->stepId)->toBe('create-user')
            ->and($e->statusCode)->toBe(201)
            ->and($e->violations)->toHaveCount(1)
            ->and($e->violations[0]['path'])->toBe('/id');
    }
});

it('validateResponseSchema is a no-op when the operation cannot be resolved', function () {
    $resolver = ($this->makeResolver)();
    $document = ($this->makeDocument)();

    $step = new Step('step1', null, 'unknownOperation', null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['anything' => 'goes'],
    ]);

    $resolver->validateResponseSchema($step, $context, $document);
})->throwsNoExceptions();

it('validateResponseSchema is a no-op without a document', function () {
    $resolver = ($this->makeResolver)();

    $step = new Step('step1', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['anything' => 'goes'],
    ]);

    $resolver->validateResponseSchema($step, $context);
})->throwsNoExceptions();
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/ArazzoExpressionResolverTest.php`
Expected: FAIL — `Call to undefined method ArazzoExpressionResolver::validateResponseSchema()`. Compilation will also fail across every test file with a hand-written `ExpressionResolverInterface` implementation until Step 3b below is done.

- [ ] **Step 3a: Add the method to the interface**

In `src/Execution/Contracts/ExpressionResolverInterface.php`, add after `evaluateCriteria`:

```php
    public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void;
```

- [ ] **Step 3b: Update the 5 hand-written test-double implementations so the suite still compiles**

Add this method to each of the following classes (identical no-op body in all five — these are test doubles, not the real implementation under test in this task):

```php
    public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void
    {
    }
```

- `tests/Execution/HttpStepExecutorTest.php`, class `HttpStepExecutorMockResolver`
- `tests/Execution/AsyncApiStepExecutorTest.php`, class `AsyncApiExecutorMockResolver`
- `tests/Execution/StepOutcomeHandlerTest.php`, class `StepOutcomeMockExpressionResolver`
- `tests/Execution/StepExecutionWorkerTest.php`, class `WorkerMockExpressionResolver`
- `tests/Execution/CorrelationResumerTest.php`, class `ResumerMockExpressionResolver`

- [ ] **Step 3c: Implement it in `ArazzoExpressionResolver`**

In `src/Execution/ArazzoExpressionResolver.php`, first extract the response-schema lookup that's currently inline in `castOutputAgainstResponseSchema()` into a shared helper. Replace this block inside `castOutputAgainstResponseSchema()`:

```php
        $statusCode = (string) ($context->getSteps()[$step->stepId]['response']['statusCode'] ?? '');
        $responses = $operation->responses;
        if (!$responses instanceof Responses) {
            return $value;
        }
        $response = $responses->getResponse($statusCode) ?? $responses->getResponse('default');
        if (!$response instanceof Response) {
            return $value;
        }

        $schema = $response->content['application/json']->schema ?? null;
        if ($schema instanceof Reference) {
            $schema = $schema->resolve();
        }
        $leafSchema = $this->resolveSchemaAtPointer($schema instanceof Schema ? $schema : null, $ast->part->jsonPointer);
```

with:

```php
        $statusCode = (string) ($context->getSteps()[$step->stepId]['response']['statusCode'] ?? '');
        $schema = $this->findResponseSchema($operation, $statusCode);
        if ($schema === null) {
            return $value;
        }
        $leafSchema = $this->resolveSchemaAtPointer($schema, $ast->part->jsonPointer);
```

Add the new shared helper (place it next to `findRequestBodySchema`):

```php
    private function findResponseSchema(Operation $operation, string $statusCode): ?Schema
    {
        $responses = $operation->responses;
        if (!$responses instanceof Responses) {
            return null;
        }
        $response = $responses->getResponse($statusCode) ?? $responses->getResponse('default');
        if (!$response instanceof Response) {
            return null;
        }

        $schema = $response->content['application/json']->schema ?? null;
        if ($schema instanceof Reference) {
            $schema = $schema->resolve();
        }

        return $schema instanceof Schema ? $schema : null;
    }
```

Now add the new public method (place it after `evaluateCriteria`, if present, or after `evaluateSuccessCriteria`):

```php
    public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void
    {
        if ($document === null || !$step->operationId) {
            return;
        }

        $sourceDesc = $document->sourceDescriptions[0] ?? null;
        if ($sourceDesc === null) {
            return;
        }

        $openApi = $this->resolveOpenApiDocument($sourceDesc);
        if ($openApi === null) {
            return;
        }

        $opId = str_contains($step->operationId, '.') ? explode('.', $step->operationId, 2)[1] : $step->operationId;

        try {
            [, , $operation] = OpenApiParser::findOperation($openApi, $opId);
        } catch (\RuntimeException) {
            return;
        }

        $statusCode = (string) ($context->getSteps()[$step->stepId]['response']['statusCode'] ?? '');
        $schema = $this->findResponseSchema($operation, $statusCode);
        if ($schema === null) {
            return;
        }

        $body = $context->getSteps()[$step->stepId]['response']['body'] ?? null;
        $violations = SchemaValidator::validate($schema, $body);

        if ($violations !== []) {
            throw new \Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException(
                $step->stepId,
                (int) $statusCode,
                $violations,
            );
        }
    }
```

- [ ] **Step 4: Run the full test suite to verify everything passes**

Run: `vendor/bin/pest`
Expected: PASS — all tests, including the new ones and the 5 updated mock-resolver files.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/Contracts/ExpressionResolverInterface.php src/Execution/ArazzoExpressionResolver.php tests/Execution/ArazzoExpressionResolverTest.php tests/Execution/HttpStepExecutorTest.php tests/Execution/AsyncApiStepExecutorTest.php tests/Execution/StepOutcomeHandlerTest.php tests/Execution/StepExecutionWorkerTest.php tests/Execution/CorrelationResumerTest.php
git commit -m "feat: add validateResponseSchema to ExpressionResolverInterface"
```

---

## Task 9: Config flag + `Step::$strictValidation` + `x-strict-validation` parsing

**Files:**
- Modify: `config/arazzo.php`
- Modify: `src/Dto/Step.php`
- Modify: `src/Parser/Parser.php` (`parseStep()`, ~line 306)
- Test: `tests/Unit/Parser/ParserTest.php` (create if it doesn't already cover `parseStep` — check first; if a `ParserTest.php` exists, add to it, otherwise create `tests/Parser/StepParsingTest.php`)

**Interfaces:**
- Consumes: nothing new.
- Produces: `Step::$strictValidation: ?bool` (`null` = "use global default"). Consumed by Task 10 (`StepExecutor`) and Task 11 (`HttpStepExecutor`).

- [ ] **Step 1: Check whether a step-parsing test file already exists**

Run: `find /Users/mohammedalama/Code/Me/laravel-arrazo/tests -iname "*Parser*"`

If a file testing `Parser::parseStep()` already exists, add the new test there following its existing conventions (fixture-building helpers, namespace). If none exists, create `tests/Parser/StepParsingTest.php` with the namespace `Alama\LaravelArazzo\Tests\Parser` (matching the `src/Parser` → `tests/Parser` convention already used by `Validation/Rules` → `tests/Validation/Rules`).

- [ ] **Step 2: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

it('parses x-strict-validation true on a step', function (): void {
    $parser = new Parser();
    $ctx = new ParseContext('test.yaml');

    $step = (function () use ($parser, $ctx) {
        $method = new \ReflectionMethod($parser, 'parseStep');
        $method->setAccessible(true);

        return $method->invoke($parser, [
            'stepId' => 's1',
            'operationId' => 'op',
            'x-strict-validation' => true,
        ], $ctx);
    })();

    expect($step->strictValidation)->toBeTrue();
});

it('parses x-strict-validation false on a step', function (): void {
    $parser = new Parser();
    $ctx = new ParseContext('test.yaml');

    $method = new \ReflectionMethod($parser, 'parseStep');
    $method->setAccessible(true);
    $step = $method->invoke($parser, [
        'stepId' => 's1',
        'operationId' => 'op',
        'x-strict-validation' => false,
    ], $ctx);

    expect($step->strictValidation)->toBeFalse();
});

it('defaults strictValidation to null when x-strict-validation is absent', function (): void {
    $parser = new Parser();
    $ctx = new ParseContext('test.yaml');

    $method = new \ReflectionMethod($parser, 'parseStep');
    $method->setAccessible(true);
    $step = $method->invoke($parser, [
        'stepId' => 's1',
        'operationId' => 'op',
    ], $ctx);

    expect($step->strictValidation)->toBeNull();
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Parser/StepParsingTest.php`
Expected: FAIL — `Step::$strictValidation` doesn't exist yet (undefined property error).

- [ ] **Step 4: Add the config key**

In `config/arazzo.php`, add a new top-level key (after `'events_table'`):

```php
    'strict_schema_validation' => env('ARAZZO_STRICT_SCHEMA_VALIDATION', false),
```

- [ ] **Step 5: Add the `Step` DTO field**

In `src/Dto/Step.php`, add `?bool $strictValidation = null` as the **last** constructor parameter (after `correlationId`), so every existing positional `new Step(...)` call across the test suite keeps compiling unchanged:

```php
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
        public ?bool $strictValidation = null,
```

- [ ] **Step 6: Parse `x-strict-validation` in `Parser::parseStep()`**

In `src/Parser/Parser.php`, in `parseStep()`, add a line reading the extension and pass it through:

```php
        $strictValidation = $this->optionalBool($obj, 'x-strict-validation', $ctx);
```

(place it next to the `$action`/`$channelPath`/`$correlationId` lines), then add `strictValidation: $strictValidation,` to the `new Step(...)` call's argument list.

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Parser/StepParsingTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS — appending a defaulted constructor parameter doesn't break any existing positional `new Step(...)` call.

- [ ] **Step 9: Commit**

```bash
git add config/arazzo.php src/Dto/Step.php src/Parser/Parser.php tests/Parser/StepParsingTest.php
git commit -m "feat: add strict_schema_validation config and x-strict-validation step extension"
```

---

## Task 10: Wire validation into `StepExecutor` (sync path)

**Files:**
- Modify: `src/Execution/StepExecutor.php`
- Modify: `src/LaravelArazzoServiceProvider.php` (~line 129, `StepExecutor::class` binding)
- Test: `tests/Execution/StepExecutorTest.php` (create — none exists yet)

**Interfaces:**
- Consumes: `ExpressionResolverInterface::validateResponseSchema()` (Task 8), `Step::$strictValidation` (Task 9), `config('arazzo.strict_schema_validation')` (Task 9).
- Produces: `StepExecutor` now throws `SchemaValidationException` when validation is enabled and the response fails schema checks.

- [ ] **Step 1: Write the failing tests**

Create `tests/Execution/StepExecutorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class StepExecutorMockResolver implements ExpressionResolverInterface
{
    public bool $validationCalled = false;

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return new Request('GET', 'http://localhost/thing');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void
    {
        $this->validationCalled = true;
        throw new SchemaValidationException($step->stepId, 200, [['path' => '/x', 'message' => 'bad']]);
    }
}

final class StepExecutorMockClient implements ClientInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

function stepExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
}

it('does not validate when disabled by default', function (): void {
    $resolver = new StepExecutorMockResolver();
    $executor = new StepExecutor(new StepExecutorMockClient(new Response(200)), $resolver, strictValidationDefault: false);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    [, $success] = $executor->execute($step, new WorkflowContext('def_1'), stepExecutorDocument());

    expect($resolver->validationCalled)->toBeFalse();
    expect($success)->toBeTrue();
});

it('validates and throws when enabled by global default', function (): void {
    $resolver = new StepExecutorMockResolver();
    $executor = new StepExecutor(new StepExecutorMockClient(new Response(200)), $resolver, strictValidationDefault: true);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    expect(fn () => $executor->execute($step, new WorkflowContext('def_1'), stepExecutorDocument()))
        ->toThrow(SchemaValidationException::class);
    expect($resolver->validationCalled)->toBeTrue();
});

it('a step-level x-strict-validation: true overrides a false global default', function (): void {
    $resolver = new StepExecutorMockResolver();
    $executor = new StepExecutor(new StepExecutorMockClient(new Response(200)), $resolver, strictValidationDefault: false);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], strictValidation: true);

    expect(fn () => $executor->execute($step, new WorkflowContext('def_1'), stepExecutorDocument()))
        ->toThrow(SchemaValidationException::class);
});

it('a step-level x-strict-validation: false overrides a true global default', function (): void {
    $resolver = new StepExecutorMockResolver();
    $executor = new StepExecutor(new StepExecutorMockClient(new Response(200)), $resolver, strictValidationDefault: true);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], strictValidation: false);
    [, $success] = $executor->execute($step, new WorkflowContext('def_1'), stepExecutorDocument());

    expect($resolver->validationCalled)->toBeFalse();
    expect($success)->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/StepExecutorTest.php`
Expected: FAIL — `StepExecutor`'s constructor doesn't accept `strictValidationDefault` yet.

- [ ] **Step 3: Update `StepExecutor`**

Replace the full contents of `src/Execution/StepExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Psr\Http\Client\ClientInterface;
use Throwable;

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
    ) {
    }

    /**
     * Executes a step and returns an array with the updated WorkflowContext and a boolean success flag.
     *
     * @return array{0: WorkflowContext, 1: bool}
     */
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
    {
        // 1. Compile Request
        $request = $this->expressionResolver->compileRequest($step, $context, $document);

        // Parse body back to array for context storage
        $bodyStream = $request->getBody();
        $bodyStream->rewind();
        $bodyData = json_decode($bodyStream->getContents(), true) ?? [];
        $bodyStream->rewind();

        // Convert query string back to array
        $queryParams = [];
        parse_str($request->getUri()->getQuery(), $queryParams);

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        // Add request to context immutably
        $context = $context->withStepRequest($step->stepId, [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'query' => $queryParams,
            'headers' => $headers,
            'body' => $bodyData,
        ]);

        // 2. Send HTTP Request
        try {
            $response = $this->httpClient->sendRequest($request);

            $statusCode = $response->getStatusCode();
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = implode(', ', $values);
            }

            $respBodyString = (string) $response->getBody();
            $respBody = json_decode($respBodyString, true) ?? [];

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => $statusCode,
                'headers' => $respHeaders,
                'body' => $respBody,
            ]);

            if ($this->shouldValidate($step)) {
                $this->expressionResolver->validateResponseSchema($step, $context, $document);
            }
        } catch (Throwable $e) {
            if ($e instanceof \Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException) {
                throw $e;
            }

            $context = $context->withStepResponse($step->stepId, [
                'statusCode' => 500,
                'headers' => [],
                'body' => ['error' => $e->getMessage()],
            ]);
        }

        // 3. Extract Outputs
        $outputs = $this->expressionResolver->extractOutputs($step, $context, $document);
        foreach ($outputs as $key => $val) {
            $context = $context->withStepOutput($step->stepId, $key, $val);
        }

        // 4. Evaluate Success
        $success = $this->expressionResolver->evaluateSuccessCriteria($step, $context, $document);

        return [$context, $success];
    }

    private function shouldValidate(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }
}
```

Note the `catch (Throwable $e)` block now re-throws `SchemaValidationException` rather than swallowing it into a synthetic 500 — `validateResponseSchema()` is called *inside* the same `try` as `sendRequest()` because it needs the response already stored on `$context`, so without this re-throw guard, a schema violation would incorrectly be caught and reported as a network failure instead of propagating as the hard exception this feature requires.

- [ ] **Step 4: Update the service provider binding**

In `src/LaravelArazzoServiceProvider.php`, replace the `StepExecutor::class` binding (~line 129):

```php
        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(ClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                strictValidationDefault: (bool) config('arazzo.strict_schema_validation', false),
            );
        });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/StepExecutorTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Execution/StepExecutor.php src/LaravelArazzoServiceProvider.php tests/Execution/StepExecutorTest.php
git commit -m "feat: wire strict schema validation into StepExecutor"
```

---

## Task 11: Wire validation into `HttpStepExecutor` (async path)

**Files:**
- Modify: `src/Execution/HttpStepExecutor.php`
- Modify: `src/LaravelArazzoServiceProvider.php` (~line 206, `HttpStepExecutor::class` binding)
- Modify: `tests/Execution/HttpStepExecutorTest.php`

**Interfaces:**
- Consumes: `ExpressionResolverInterface::validateResponseSchema()` (Task 8), `Step::$strictValidation` (Task 9).
- Produces: `HttpStepExecutor` now throws `SchemaValidationException` when validation is enabled and the response fails schema checks.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Execution/HttpStepExecutorTest.php` (extend `HttpStepExecutorMockResolver` — already updated in Task 8 to have a no-op `validateResponseSchema` — with a way to make it throw for these specific tests):

```php
it('does not validate when disabled by default', function (): void {
    $response = new Response(200, [], json_encode(['id' => 1]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($client, $resolver, strictValidationDefault: false);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $outcome = $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
});

it('validates and throws when enabled by global default', function (): void {
    $response = new Response(200, [], json_encode(['id' => 1]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new class extends HttpStepExecutorMockResolver {
        public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void
        {
            throw new \Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException($step->stepId, 200, [['path' => '/id', 'message' => 'bad']]);
        }
    };
    $executor = new HttpStepExecutor($client, $resolver, strictValidationDefault: true);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    expect(fn () => $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1'))
        ->toThrow(\Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException::class);
});

it('a step-level x-strict-validation: true overrides a false global default', function (): void {
    $response = new Response(200, [], json_encode(['id' => 1]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new class extends HttpStepExecutorMockResolver {
        public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void
        {
            throw new \Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException($step->stepId, 200, [['path' => '/id', 'message' => 'bad']]);
        }
    };
    $executor = new HttpStepExecutor($client, $resolver, strictValidationDefault: false);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], strictValidation: true);
    $context = new WorkflowContext('def_1');

    expect(fn () => $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1'))
        ->toThrow(\Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: FAIL — `HttpStepExecutor`'s constructor doesn't accept `strictValidationDefault` yet.

- [ ] **Step 3: Update `HttpStepExecutor`**

Replace the full contents of `src/Execution/HttpStepExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\StepProtocolExecutorInterface;

final class HttpStepExecutor implements StepProtocolExecutorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
    ) {
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === null;
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $request = $this->expressionResolver->compileRequest($step, $context, $document);
        $response = $this->httpClient->sendRequest($request);

        $decodedBody = json_decode((string) $response->getBody(), true);
        $body = is_array($decodedBody) ? $decodedBody : [];

        $contextWithResponse = $context->withStepResponse($step->stepId, [
            'statusCode' => $response->getStatusCode(),
            'body' => $body,
        ]);

        if ($this->shouldValidate($step)) {
            $this->expressionResolver->validateResponseSchema($step, $contextWithResponse, $document);
        }

        $outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document);

        return StepExecutionOutcome::resolved($response->getStatusCode(), $outputs, $body);
    }

    private function shouldValidate(Step $step): bool
    {
        return $step->strictValidation ?? $this->strictValidationDefault;
    }
}
```

- [ ] **Step 4: Update the service provider binding**

In `src/LaravelArazzoServiceProvider.php`, replace the `HttpStepExecutor::class` binding (~line 206):

```php
        $this->app->singleton(HttpStepExecutor::class, function ($app) {
            return new HttpStepExecutor(
                $app->make(HttpClientInterface::class),
                $app->make(ExpressionResolverInterface::class),
                strictValidationDefault: (bool) config('arazzo.strict_schema_validation', false),
            );
        });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Execution/HttpStepExecutorTest.php`
Expected: PASS (original tests + 3 new ones).

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Execution/HttpStepExecutor.php src/LaravelArazzoServiceProvider.php tests/Execution/HttpStepExecutorTest.php
git commit -m "feat: wire strict schema validation into HttpStepExecutor"
```

---

## Task 12: `SuccessCriterion::$version` + `{type, version}` object parsing

**Files:**
- Modify: `src/Dto/SuccessCriterion.php`
- Modify: `src/Parser/Parser.php` (`parseSuccessCriterion()`, ~line 387)
- Test: `tests/Validation/Rules/StepContentRulesTest.php` is for rules, not the parser — create `tests/Parser/SuccessCriterionParsingTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `SuccessCriterion::$version: ?string`. Consumed by Task 13 (`SuccessCriteriaVersionSupportedRule`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Parser/SuccessCriterionParsingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

function parseSuccessCriterion(array $node)
{
    $parser = new Parser();
    $ctx = new ParseContext('test.yaml');
    $method = new \ReflectionMethod($parser, 'parseSuccessCriterion');
    $method->setAccessible(true);

    return $method->invoke($parser, $node, $ctx);
}

it('parses a bare string type with version null', function (): void {
    $criterion = parseSuccessCriterion(['condition' => '$statusCode == 200', 'type' => 'simple']);

    expect($criterion->type)->toBe(CriterionType::Simple)
        ->and($criterion->version)->toBeNull();
});

it('parses the {type, version} object form', function (): void {
    $criterion = parseSuccessCriterion([
        'condition' => "$.status == 'CREATED'",
        'type' => ['type' => 'jsonpath', 'version' => 'rfc9535'],
    ]);

    expect($criterion->type)->toBe(CriterionType::JsonPath)
        ->and($criterion->version)->toBe('rfc9535');
});

it('parses a criterion with no type at all', function (): void {
    $criterion = parseSuccessCriterion(['condition' => '$statusCode == 200']);

    expect($criterion->type)->toBeNull()
        ->and($criterion->version)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Parser/SuccessCriterionParsingTest.php`
Expected: FAIL — `SuccessCriterion::$version` doesn't exist; the object form of `type` isn't handled.

- [ ] **Step 3: Add the `version` field**

In `src/Dto/SuccessCriterion.php`, add a fourth constructor parameter:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\CriterionType;

final readonly class SuccessCriterion
{
    public function __construct(
        public ?string $context,
        public string $condition,
        public ?CriterionType $type,
        public ?string $version = null,
    ) {
    }
}
```

(defaulted, so every existing positional `new SuccessCriterion($context, $condition, $type)` call across the test suite keeps compiling unchanged.)

- [ ] **Step 4: Update `Parser::parseSuccessCriterion()`**

In `src/Parser/Parser.php`, replace the `parseSuccessCriterion()` method:

```php
    protected function parseSuccessCriterion(mixed $node, ParseContext $ctx): SuccessCriterion
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = null;
        $version = null;

        if (array_key_exists('type', $obj) && $obj['type'] !== null) {
            $rawType = $obj['type'];

            if (is_array($rawType)) {
                $typeCtx = $ctx->push('type');
                $t = $this->requireString($rawType, 'type', $typeCtx);
                $version = $this->optionalString($rawType, 'version', $typeCtx);
            } else {
                $t = $this->optionalString($obj, 'type', $ctx);
            }

            $type = CriterionType::tryFrom($t)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('type'), 'simple|regex|jsonpath|xpath', $t,
                );
        }

        return new SuccessCriterion(
            context: $this->optionalString($obj, 'context', $ctx),
            condition: $this->requireString($obj, 'condition', $ctx),
            type: $type,
            version: $version,
        );
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Parser/SuccessCriterionParsingTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest`
Expected: PASS — appending a defaulted constructor parameter doesn't break existing positional `new SuccessCriterion(...)` calls; the parser change is additive (string `type` still works exactly as before).

- [ ] **Step 7: Commit**

```bash
git add src/Dto/SuccessCriterion.php src/Parser/Parser.php tests/Parser/SuccessCriterionParsingTest.php
git commit -m "feat: parse {type, version} object form for success criteria"
```

---

## Task 13: `SuccessCriteriaVersionSupportedRule` — reject unsupported `xpath` version pins

**Files:**
- Create: `src/Validation/Rules/SuccessCriteriaVersionSupportedRule.php`
- Test: `tests/Validation/Rules/SuccessCriteriaVersionSupportedRuleTest.php`

**Interfaces:**
- Consumes: `SuccessCriterion::$version` (Task 12), `Rule` interface, `ErrorCollector` (both existing).
- Produces: `SuccessCriteriaVersionSupportedRule implements Rule` — standalone, not registered in any central list (this codebase has no "all rules" registry; each consumer assembles its own `RuleSet`, matching every other rule class).

- [ ] **Step 1: Write the failing tests**

Create `tests/Validation/Rules/SuccessCriteriaVersionSupportedRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\SuccessCriteriaVersionSupportedRule;

function versionRuleDoc(SuccessCriterion $c): ArazzoDocument
{
    $step = new Step('x', null, 'op', null, null, [], null, [$c], [], [], []);
    $w = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('rejects xpath-30', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-30');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('xpath-30');
});

it('rejects xpath-31', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-31');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('accepts xpath-10', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-10');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});

it('accepts xpath with no version pinned', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, null);
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});

it('ignores version on a non-xpath criterion type', function (): void {
    $c = new SuccessCriterion(null, '$.id', CriterionType::JsonPath, 'xpath-30');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Validation/Rules/SuccessCriteriaVersionSupportedRuleTest.php`
Expected: FAIL with `Class "Alama\LaravelArazzo\Validation\Rules\SuccessCriteriaVersionSupportedRule" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Validation/Rules/SuccessCriteriaVersionSupportedRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SuccessCriteriaVersionSupportedRule implements Rule
{
    /** @var array<string, list<string>> */
    private const UNSUPPORTED = [
        'xpath' => ['xpath-30', 'xpath-31'],
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if ($c->type !== CriterionType::XPath || $c->version === null) {
                        continue;
                    }

                    if (in_array($c->version, self::UNSUPPORTED['xpath'], true)) {
                        $errors->error(
                            $this->code(),
                            "criterion type 'xpath' version '{$c->version}' is not supported — DOMXPath only implements XPath 1.0 (use 'xpath-10' or omit version).",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/type/version",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.success_criteria_version_supported';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Validation/Rules/SuccessCriteriaVersionSupportedRuleTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Run the full suite one final time**

Run: `vendor/bin/pest`
Expected: PASS — entire suite green.

- [ ] **Step 6: Commit**

```bash
git add src/Validation/Rules/SuccessCriteriaVersionSupportedRule.php tests/Validation/Rules/SuccessCriteriaVersionSupportedRuleTest.php
git commit -m "feat: reject unsupported xpath-30/xpath-31 version pins on success criteria"
```
