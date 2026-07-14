### Task 4: Leaf DTOs — Info, SourceDescription, Parameter, PayloadReplacement, RequestBody, SuccessCriterion, Reusable, Expression

**Files:**
- Create: `src/Dto/Enum/SourceType.php`, `src/Dto/Enum/ParameterIn.php`, `src/Dto/Enum/CriterionType.php`
- Create: `src/Dto/Info.php`, `src/Dto/SourceDescription.php`, `src/Dto/Parameter.php`, `src/Dto/PayloadReplacement.php`, `src/Dto/RequestBody.php`, `src/Dto/SuccessCriterion.php`, `src/Dto/Reusable.php`, `src/Dto/Expression.php`
- Create: `tests/Dto/LeafDtoTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - Enums: `SourceType { Openapi, Arazzo }`, `ParameterIn { Path, Query, Header, Cookie, Body }`, `CriterionType { Simple, Regex, JsonPath, XPath }`. All backed by lowercase spec strings via `string`.
  - `Info(string $title, ?string $summary, ?string $description, string $version)`
  - `SourceDescription(string $name, string $url, SourceType $type)`
  - `Parameter(string $name, ?ParameterIn $in, Expression|string|int|float|bool|null $value)`
  - `PayloadReplacement(string $target, Expression|string|int|float|bool|null $value)`
  - `RequestBody(?string $contentType, mixed $payload, list<PayloadReplacement> $replacements)`
  - `SuccessCriterion(?string $context, string $condition, ?CriterionType $type)`
  - `Reusable(string $reference, mixed $value = null)`
  - `Expression(string $raw)` — stores raw `{$...}` string; AST cache arrives in Task 10.

- [ ] **Step 1: Write failing test**

Create `tests/Dto/LeafDtoTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

it('maps enum spec strings', function (): void {
    expect(SourceType::from('openapi'))->toBe(SourceType::Openapi)
        ->and(ParameterIn::from('query'))->toBe(ParameterIn::Query)
        ->and(CriterionType::from('jsonpath'))->toBe(CriterionType::JsonPath);
});

it('builds Info', function (): void {
    $info = new Info('T', null, 'd', '1.0');
    expect($info->title)->toBe('T')->and($info->version)->toBe('1.0');
});

it('builds SourceDescription', function (): void {
    $s = new SourceDescription('api', '/x.yaml', SourceType::Openapi);
    expect($s->name)->toBe('api');
});

it('builds Parameter with expression value', function (): void {
    $p = new Parameter('id', ParameterIn::Query, new Expression('{$inputs.id}'));
    expect($p->name)->toBe('id')
        ->and($p->value)->toBeInstanceOf(Expression::class);
});

it('builds RequestBody with replacements', function (): void {
    $rb = new RequestBody('application/json', ['a' => 1], [
        new PayloadReplacement('/a', 2),
    ]);
    expect($rb->replacements)->toHaveCount(1)
        ->and($rb->replacements[0]->target)->toBe('/a');
});

it('builds SuccessCriterion', function (): void {
    $c = new SuccessCriterion('$response.body', '$.id != null', CriterionType::JsonPath);
    expect($c->condition)->toBe('$.id != null');
});

it('builds Reusable', function (): void {
    $r = new Reusable('$components.parameters.foo');
    expect($r->reference)->toStartWith('$components.');
});

it('stores raw Expression string', function (): void {
    $e = new Expression('{$inputs.name}');
    expect($e->raw)->toBe('{$inputs.name}');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement enums**

`src/Dto/Enum/SourceType.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo  = 'arazzo';
}
```

`src/Dto/Enum/ParameterIn.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Enum;

enum ParameterIn: string
{
    case Path   = 'path';
    case Query  = 'query';
    case Header = 'header';
    case Cookie = 'cookie';
    case Body   = 'body';
}
```

`src/Dto/Enum/CriterionType.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto\Enum;

enum CriterionType: string
{
    case Simple   = 'simple';
    case Regex    = 'regex';
    case JsonPath = 'jsonpath';
    case XPath    = 'xpath';
}
```

- [ ] **Step 4: Implement leaf DTOs**

`src/Dto/Expression.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class Expression
{
    public function __construct(public string $raw) {}
}
```

`src/Dto/Info.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class Info
{
    public function __construct(
        public string $title,
        public ?string $summary,
        public ?string $description,
        public string $version,
    ) {}
}
```

`src/Dto/SourceDescription.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\SourceType;

final readonly class SourceDescription
{
    public function __construct(
        public string $name,
        public string $url,
        public SourceType $type,
    ) {}
}
```

`src/Dto/Parameter.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;

final readonly class Parameter
{
    public function __construct(
        public string $name,
        public ?ParameterIn $in,
        public Expression|string|int|float|bool|null $value,
    ) {}
}
```

`src/Dto/PayloadReplacement.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class PayloadReplacement
{
    public function __construct(
        public string $target,
        public Expression|string|int|float|bool|null $value,
    ) {}
}
```

`src/Dto/RequestBody.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class RequestBody
{
    /** @param list<PayloadReplacement> $replacements */
    public function __construct(
        public ?string $contentType,
        public mixed $payload,
        public array $replacements,
    ) {}
}
```

`src/Dto/SuccessCriterion.php`:

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
    ) {}
}
```

`src/Dto/Reusable.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Dto;

final readonly class Reusable
{
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {}
}
```

- [ ] **Step 5: Run — expect pass**

- [ ] **Step 6: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: add leaf DTOs and enums"
```

---

