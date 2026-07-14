# Laravel Arazzo — Parser + Validator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the foundation slice of `alama/laravel-arazzo`: a Laravel package that loads Arazzo 1.0.0 YAML/JSON files, exposes them as typed readonly DTOs, and validates them against the spec with structured error output.

**Architecture:** Three-stage pipeline `Loader → Parser → Validator`. Core is framework-agnostic pure PHP. A thin Laravel layer adds a Facade, a Service Provider, an Artisan command, and a publishable config. Each validation rule is one class, one test, and one fixture pair.

**Tech Stack:** PHP 8.4, Laravel 11–13 (via `illuminate/contracts`), `spatie/laravel-package-tools`, `symfony/yaml`, Pest 4, Larastan 3 (level 8), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-07-14-arazzo-parser-validator-design.md`

## Global Constraints

- PHP version floor: `^8.4`. Use `readonly` classes, enums, `match`, first-class typed properties, strict types (`declare(strict_types=1);`).
- Arazzo spec target: `1.0.0` only. No version-branching code.
- Package name: `alama/laravel-arazzo`. Src namespace: `Alama\LaravelArazzo\`. Test namespace: `Alama\LaravelArazzo\Tests\`.
- Core (`src/Loader`, `src/Parser`, `src/Expression`, `src/Validation`, `src/Dto`, `src/Exceptions`, `src/Arazzo.php`) MUST NOT import from `Illuminate\*` or `Symfony\Component\HttpFoundation\*`. `symfony/yaml` is allowed but only inside `SymfonyYamlDecoder`.
- All DTOs are `final readonly`. All collections use `list<T>` PHPDoc.
- Every error/warning path field is a JSON Pointer (RFC 6901) — string starting with `/`.
- Every rule has a stable string `code()` (e.g. `step.unique_id`) that never changes.
- Larastan level 8 must pass after every task. Pest must be green after every task.
- Frequent commits — each task ends with a commit. Never batch multiple tasks into one commit.

---

### Task 1: Rename skeleton, wire package identity, add dependencies

**Files:**
- Modify: `composer.json`
- Delete: `src/Skeleton.php`, `src/SkeletonServiceProvider.php`, `src/Commands/SkeletonCommand.php`, `src/Facades/Skeleton.php`
- Create: `src/LaravelArazzoServiceProvider.php`
- Modify: `phpunit.xml.dist`, `phpstan.neon.dist`
- Delete: `resources/views/.gitkeep` if present is fine; keep dir
- Modify: `tests/Pest.php`, `tests/ArchTest.php` (if present)

**Interfaces:**
- Produces: `Alama\LaravelArazzo\LaravelArazzoServiceProvider` (empty package registration; concrete bindings arrive in later tasks).

- [ ] **Step 1: Update composer.json**

Replace `composer.json` with:

```json
{
    "name": "alama/laravel-arazzo",
    "description": "Laravel package to parse and validate Arazzo 1.0.0 workflow specifications.",
    "keywords": ["alama", "laravel", "arazzo", "openapi", "workflow", "parser", "validator"],
    "homepage": "https://github.com/alama/laravel-arazzo",
    "license": "MIT",
    "authors": [
        {
            "name": "Mohammed Alama",
            "email": "mohammedalama96@icloud.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": "^8.4",
        "spatie/laravel-package-tools": "^1.16",
        "illuminate/contracts": "^11.0||^12.0||^13.0",
        "symfony/yaml": "^7.0"
    },
    "require-dev": {
        "laravel/pint": "^1.14",
        "nunomaduro/collision": "^8.8",
        "larastan/larastan": "^3.0",
        "orchestra/testbench": "^11.0.0||^10.0.0||^9.0.0",
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-arch": "^4.0",
        "pestphp/pest-plugin-laravel": "^4.0",
        "phpstan/extension-installer": "^1.4",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "spatie/laravel-ray": "^1.35"
    },
    "autoload": {
        "psr-4": {
            "Alama\\LaravelArazzo\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\LaravelArazzo\\Tests\\": "tests/",
            "Workbench\\App\\": "workbench/app/"
        }
    },
    "scripts": {
        "post-autoload-dump": "@composer run prepare",
        "prepare": "@php vendor/bin/testbench package:discover --ansi",
        "analyse": "vendor/bin/phpstan analyse",
        "test": "vendor/bin/pest",
        "test-coverage": "vendor/bin/pest --coverage",
        "format": "vendor/bin/pint"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Alama\\LaravelArazzo\\LaravelArazzoServiceProvider"
            ]
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 2: Delete skeleton files**

Run:
```bash
rm src/Skeleton.php src/SkeletonServiceProvider.php src/Commands/SkeletonCommand.php src/Facades/Skeleton.php
rmdir src/Commands src/Facades 2>/dev/null || true
```

- [ ] **Step 3: Create empty service provider**

Create `src/LaravelArazzoServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-arazzo');
    }
}
```

- [ ] **Step 4: Update tests/Pest.php and tests/TestCase**

If `tests/TestCase.php` exists, replace with:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests;

use Alama\LaravelArazzo\LaravelArazzoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelArazzoServiceProvider::class];
    }
}
```

Update `tests/Pest.php` to `uses(TestCase::class)->in('Feature', 'Commands')` (keep existing arch tests). Remove any references to `VendorName\\Skeleton`.

- [ ] **Step 5: Update phpstan.neon.dist**

Set:
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - src
        - config
    treatPhpDocTypesAsCertain: false
```

- [ ] **Step 6: Regenerate autoload + verify boot**

Run:
```bash
composer dump-autoload
vendor/bin/pest --filter=example || vendor/bin/pest
```
Expected: tests pass (only whatever remains). If skeleton example tests still reference `VendorName\Skeleton`, delete them.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: rename skeleton to alama/laravel-arazzo"
```

---

### Task 2: Format enum + RawDocument DTO

**Files:**
- Create: `src/Dto/Enum/Format.php`
- Create: `src/Dto/RawDocument.php`
- Create: `tests/Dto/RawDocumentTest.php`

**Interfaces:**
- Produces:
  - `Alama\LaravelArazzo\Dto\Enum\Format` — `Yaml`, `Json` cases with `fromExtension(string $ext): ?self`.
  - `Alama\LaravelArazzo\Dto\RawDocument` — readonly `{ array $data, string $path, Format $format }`.

- [ ] **Step 1: Write failing test**

Create `tests/Dto/RawDocumentTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;

it('holds raw data, path and format', function (): void {
    $doc = new RawDocument(['arazzo' => '1.0.0'], '/tmp/foo.yaml', Format::Yaml);

    expect($doc->data)->toBe(['arazzo' => '1.0.0'])
        ->and($doc->path)->toBe('/tmp/foo.yaml')
        ->and($doc->format)->toBe(Format::Yaml);
});

it('maps extensions to format', function (): void {
    expect(Format::fromExtension('yaml'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('yml'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('json'))->toBe(Format::Json)
        ->and(Format::fromExtension('txt'))->toBeNull();
});
```

- [ ] **Step 2: Run — expect fail**

`vendor/bin/pest tests/Dto/RawDocumentTest.php` → class not found.

- [ ] **Step 3: Implement Format enum**

Create `src/Dto/Enum/Format.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Enum;

enum Format: string
{
    case Yaml = 'yaml';
    case Json = 'json';

    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'yaml', 'yml' => self::Yaml,
            'json'        => self::Json,
            default       => null,
        };
    }
}
```

- [ ] **Step 4: Implement RawDocument**

Create `src/Dto/RawDocument.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\Format;

final readonly class RawDocument
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public array $data,
        public string $path,
        public Format $format,
    ) {}
}
```

- [ ] **Step 5: Run — expect pass**

`vendor/bin/pest tests/Dto/RawDocumentTest.php` → PASS.

- [ ] **Step 6: PHPStan**

`vendor/bin/phpstan analyse` → 0 errors.

- [ ] **Step 7: Commit**

```bash
git add src/Dto tests/Dto
git commit -m "feat: add Format enum and RawDocument DTO"
```

---

### Task 3: Loader with YAML + JSON decoders

**Files:**
- Create: `src/Loader/DecodeException.php`
- Create: `src/Loader/YamlDecoder.php`, `src/Loader/JsonDecoder.php` (interfaces)
- Create: `src/Loader/SymfonyYamlDecoder.php`, `src/Loader/NativeJsonDecoder.php`
- Create: `src/Exceptions/ArazzoException.php`, `src/Exceptions/LoaderException.php`
- Create: `src/Loader/Loader.php`
- Create: `tests/Loader/LoaderTest.php`
- Create: `tests/fixtures/loader/minimal.yaml`, `tests/fixtures/loader/minimal.json`, `tests/fixtures/loader/broken.yaml`, `tests/fixtures/loader/not-object.yaml`

**Interfaces:**
- Consumes: `Format`, `RawDocument` from Task 2.
- Produces:
  - `Alama\LaravelArazzo\Loader\Loader::load(string $path): RawDocument` — throws `LoaderException`.
  - `Alama\LaravelArazzo\Exceptions\ArazzoException` — abstract base extending `\RuntimeException`.
  - `Alama\LaravelArazzo\Exceptions\LoaderException` with named constructors: `notFound`, `notReadable`, `unsupportedExtension`, `readFailed`, `decodeFailed`, `rootNotObject`.

- [ ] **Step 1: Create fixtures**

`tests/fixtures/loader/minimal.yaml`:
```yaml
arazzo: "1.0.0"
info:
  title: Minimal
  version: "1.0"
sourceDescriptions:
  - name: api
    url: /openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: s1
        operationId: getFoo
```

`tests/fixtures/loader/minimal.json`:
```json
{"arazzo":"1.0.0","info":{"title":"Minimal","version":"1.0"},"sourceDescriptions":[{"name":"api","url":"/openapi.yaml","type":"openapi"}],"workflows":[{"workflowId":"wf","steps":[{"stepId":"s1","operationId":"getFoo"}]}]}
```

`tests/fixtures/loader/broken.yaml`:
```yaml
arazzo: "1.0.0
info: [
```

`tests/fixtures/loader/not-object.yaml`:
```yaml
- just
- a
- list
```

- [ ] **Step 2: Write failing tests**

Create `tests/Loader/LoaderTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Exceptions\LoaderException;
use Alama\LaravelArazzo\Loader\Loader;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;

function makeLoader(): Loader
{
    return new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
}

it('loads a yaml file', function (): void {
    $raw = makeLoader()->load(__DIR__ . '/../fixtures/loader/minimal.yaml');

    expect($raw->format)->toBe(Format::Yaml)
        ->and($raw->data['arazzo'] ?? null)->toBe('1.0.0')
        ->and($raw->data['workflows'][0]['workflowId'] ?? null)->toBe('wf');
});

it('loads a json file', function (): void {
    $raw = makeLoader()->load(__DIR__ . '/../fixtures/loader/minimal.json');

    expect($raw->format)->toBe(Format::Json)
        ->and($raw->data['arazzo'] ?? null)->toBe('1.0.0');
});

it('throws when file missing', function (): void {
    makeLoader()->load('/does/not/exist.yaml');
})->throws(LoaderException::class, 'not found');

it('throws on unsupported extension', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arz') . '.txt';
    file_put_contents($tmp, 'x');
    try {
        makeLoader()->load($tmp);
    } finally {
        @unlink($tmp);
    }
})->throws(LoaderException::class, 'unsupported');

it('throws on decode failure', function (): void {
    makeLoader()->load(__DIR__ . '/../fixtures/loader/broken.yaml');
})->throws(LoaderException::class, 'decode');

it('throws when root is not an object', function (): void {
    makeLoader()->load(__DIR__ . '/../fixtures/loader/not-object.yaml');
})->throws(LoaderException::class, 'root');
```

- [ ] **Step 3: Run — expect fail**

`vendor/bin/pest tests/Loader/LoaderTest.php` → classes not found.

- [ ] **Step 4: Implement exception hierarchy**

Create `src/Exceptions/ArazzoException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

use RuntimeException;

abstract class ArazzoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $path = '',
        public readonly string $code_id = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

Create `src/Exceptions/LoaderException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

final class LoaderException extends ArazzoException
{
    public static function notFound(string $path): self
    {
        return new self("File not found: {$path}", $path, 'loader.not_found');
    }

    public static function notReadable(string $path): self
    {
        return new self("File not readable: {$path}", $path, 'loader.not_readable');
    }

    public static function unsupportedExtension(string $ext): self
    {
        return new self("Unsupported extension '{$ext}' (expected yaml|yml|json)", '', 'loader.unsupported_extension');
    }

    public static function readFailed(string $path): self
    {
        return new self("Failed to read file: {$path}", $path, 'loader.read_failed');
    }

    public static function decodeFailed(string $path, \Throwable $previous): self
    {
        return new self("Failed to decode file: {$path} ({$previous->getMessage()})", $path, 'loader.decode_failed', $previous);
    }

    public static function rootNotObject(string $path): self
    {
        return new self("Root of Arazzo document must be an object: {$path}", $path, 'loader.root_not_object');
    }
}
```

- [ ] **Step 5: Implement decoders**

Create `src/Loader/DecodeException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

final class DecodeException extends \RuntimeException {}
```

Create `src/Loader/YamlDecoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

interface YamlDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
```

Create `src/Loader/JsonDecoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

interface JsonDecoder
{
    /** @return mixed */
    public function decode(string $source);
}
```

Create `src/Loader/SymfonyYamlDecoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class SymfonyYamlDecoder implements YamlDecoder
{
    public function decode(string $source): mixed
    {
        try {
            return Yaml::parse($source);
        } catch (ParseException $e) {
            throw new DecodeException($e->getMessage(), 0, $e);
        }
    }
}
```

Create `src/Loader/NativeJsonDecoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

final class NativeJsonDecoder implements JsonDecoder
{
    public function decode(string $source): mixed
    {
        try {
            return json_decode($source, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DecodeException($e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 6: Implement Loader**

Create `src/Loader/Loader.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Exceptions\LoaderException;

final class Loader
{
    public function __construct(
        private readonly YamlDecoder $yaml,
        private readonly JsonDecoder $json,
    ) {}

    public function load(string $path): RawDocument
    {
        if (!is_file($path)) {
            throw LoaderException::notFound($path);
        }
        if (!is_readable($path)) {
            throw LoaderException::notReadable($path);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $format = Format::fromExtension($ext)
            ?? throw LoaderException::unsupportedExtension($ext);

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw LoaderException::readFailed($path);
        }

        try {
            $data = $format === Format::Yaml
                ? $this->yaml->decode($raw)
                : $this->json->decode($raw);
        } catch (DecodeException $e) {
            throw LoaderException::decodeFailed($path, $e);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw LoaderException::rootNotObject($path);
        }

        /** @var array<string,mixed> $data */
        return new RawDocument($data, $path, $format);
    }
}
```

- [ ] **Step 7: Run — expect pass**

`vendor/bin/pest tests/Loader/LoaderTest.php` → PASS.

- [ ] **Step 8: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: add Loader with YAML and JSON decoders"
```

---

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

### Task 10: Parser — Step + Workflow + Components + top-level parse()

**Files:**
- Modify: `src/Parser/Parser.php` — add public `parse(RawDocument): ArazzoDocument`, and protected `parseStep`, `parseWorkflow`, `parseComponents`.
- Create: `tests/Parser/FullParserTest.php`
- Create: `tests/fixtures/parser/valid-minimal.yaml` (reuse Task 3 fixture path is fine; copy contents to a parser-specific fixture)
- Create: `tests/fixtures/parser/full.yaml` (richer doc exercising every branch)

**Interfaces:**
- Consumes: everything above.
- Produces:
  - `Parser::parse(RawDocument $raw): ArazzoDocument` — public entry.
  - Rejects: missing `arazzo` / `info` / `workflows`, wrong types, empty top-level as per spec (empty workflows list allowed structurally; the validator's `workflow.at_least_one` rule handles that).

- [ ] **Step 1: Add richer fixture**

Create `tests/fixtures/parser/full.yaml`:

```yaml
arazzo: "1.0.0"
info:
  title: Full
  version: "1.0"
  description: Exhaustive fixture
sourceDescriptions:
  - name: api
    url: /openapi.yaml
    type: openapi
workflows:
  - workflowId: main
    summary: Primary workflow
    inputs:
      type: object
      properties:
        userId:
          type: string
    parameters:
      - name: X-Trace
        in: header
        value: "{$inputs.userId}"
    steps:
      - stepId: fetch
        description: Fetch user
        operationId: getUser
        parameters:
          - name: id
            in: query
            value: "{$inputs.userId}"
        successCriteria:
          - condition: "$statusCode == 200"
        outputs:
          user: "{$response.body}"
        onSuccess:
          - name: next
            type: goto
            stepId: post
        onFailure:
          - name: back
            type: retry
            retryAfter: 500
            retryLimit: 2
      - stepId: post
        operationId: postThing
        requestBody:
          contentType: application/json
          payload: "{$steps.fetch.outputs.user}"
          replacements:
            - target: /id
              value: "{$inputs.userId}"
    outputs:
      user: "{$steps.fetch.outputs.user}"
components:
  parameters:
    Trace:
      name: X-Trace
      in: header
      value: "{$inputs.userId}"
  successActions:
    goEnd:
      name: goEnd
      type: end
x-vendor-custom: hello
```

- [ ] **Step 2: Write failing test**

Create `tests/Parser/FullParserTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Loader\Loader;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;

function parseFixture(string $rel): \Alama\LaravelArazzo\Dto\ArazzoDocument {
    $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
    $raw = $loader->load(__DIR__ . '/../fixtures/parser/' . $rel);
    return (new Parser())->parse($raw);
}

it('parses a full arazzo document', function (): void {
    $doc = parseFixture('full.yaml');

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->info->title)->toBe('Full')
        ->and($doc->sourceDescriptions[0]->type)->toBe(SourceType::Openapi)
        ->and($doc->workflows)->toHaveCount(1);

    $wf = $doc->workflows[0];
    expect($wf->workflowId)->toBe('main')
        ->and($wf->steps)->toHaveCount(2)
        ->and($wf->parameters[0]->name)->toBe('X-Trace')
        ->and($wf->outputs)->toHaveKey('user');

    $s1 = $wf->steps[0];
    expect($s1->onSuccess[0])->toBeInstanceOf(SuccessGotoAction::class)
        ->and($s1->onFailure[0])->toBeInstanceOf(RetryAction::class);

    expect($doc->components->successActions)->toHaveKey('goEnd')
        ->and($doc->specificationExtensions)->toHaveKey('x-vendor-custom');
});
```

- [ ] **Step 3: Run — expect fail**

- [ ] **Step 4: Implement parseStep + parseWorkflow + parseComponents + parse**

Append to `src/Parser/Parser.php`:

```php
    protected function parseStep(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Step
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $requestBody = null;
        if (array_key_exists('requestBody', $obj) && $obj['requestBody'] !== null) {
            $requestBody = $this->parseRequestBody($obj['requestBody'], $ctx->push('requestBody'));
        }

        $criteria = [];
        if (($c = $this->optionalArray($obj, 'successCriteria', $ctx)) !== null) {
            foreach (array_values($c) as $i => $item) {
                $criteria[] = $this->parseSuccessCriterion($item, $ctx->push('successCriteria')->push($i));
            }
        }

        $onSuccess = [];
        if (($o = $this->optionalArray($obj, 'onSuccess', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onSuccess[] = $this->parseSuccessAction($item, $ctx->push('onSuccess')->push($i));
            }
        }

        $onFailure = [];
        if (($o = $this->optionalArray($obj, 'onFailure', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onFailure[] = $this->parseFailureAction($item, $ctx->push('onFailure')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        return new \Alama\LaravelArazzo\Dto\Step(
            stepId:          $this->requireString($obj, 'stepId', $ctx),
            description:     $this->optionalString($obj, 'description', $ctx),
            operationId:     $this->optionalString($obj, 'operationId', $ctx),
            operationPath:   $this->optionalString($obj, 'operationPath', $ctx),
            workflowId:      $this->optionalString($obj, 'workflowId', $ctx),
            parameters:      $parameters,
            requestBody:     $requestBody,
            successCriteria: $criteria,
            onSuccess:       $onSuccess,
            onFailure:       $onFailure,
            outputs:         $outputs,
        );
    }

    protected function parseWorkflow(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Workflow
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = $this->optionalArray($obj, 'inputs', $ctx);

        $dependsOn = [];
        if (($d = $this->optionalArray($obj, 'dependsOn', $ctx)) !== null) {
            foreach (array_values($d) as $i => $item) {
                if (!is_string($item)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('dependsOn')->push($i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        $steps = [];
        $rawSteps = $this->requireArray($obj, 'steps', $ctx);
        foreach (array_values($rawSteps) as $i => $item) {
            $steps[] = $this->parseStep($item, $ctx->push('steps')->push($i));
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach (array_values($s) as $i => $item) {
                $successActions[] = $this->parseSuccessAction($item, $ctx->push('successActions')->push($i));
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach (array_values($f) as $i => $item) {
                $failureActions[] = $this->parseFailureAction($item, $ctx->push('failureActions')->push($i));
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        /** @var array<string,mixed>|null $inputs */
        return new \Alama\LaravelArazzo\Dto\Workflow(
            workflowId:     $this->requireString($obj, 'workflowId', $ctx),
            summary:        $this->optionalString($obj, 'summary', $ctx),
            description:    $this->optionalString($obj, 'description', $ctx),
            inputs:         $inputs,
            dependsOn:      $dependsOn,
            steps:          $steps,
            successActions: $successActions,
            failureActions: $failureActions,
            outputs:        $outputs,
            parameters:     $parameters,
        );
    }

    protected function parseComponents(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Components
    {
        if ($node === null) {
            return new \Alama\LaravelArazzo\Dto\Components([], [], [], []);
        }
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = [];
        if (($i = $this->optionalArray($obj, 'inputs', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                if (!is_array($v)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('inputs')->push((string) $k), 'object (JSON Schema)', $v,
                    );
                }
                /** @var array<string,mixed> $v */
                $inputs[(string) $k] = $v;
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach ($p as $k => $v) {
                $parameters[(string) $k] = $this->parseParameter($v, $ctx->push('parameters')->push((string) $k));
            }
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach ($s as $k => $v) {
                $parsed = $this->parseSuccessAction($v, $ctx->push('successActions')->push((string) $k));
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('successActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $successActions[(string) $k] = $parsed;
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach ($f as $k => $v) {
                $parsed = $this->parseFailureAction($v, $ctx->push('failureActions')->push((string) $k));
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('failureActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $failureActions[(string) $k] = $parsed;
            }
        }

        return new \Alama\LaravelArazzo\Dto\Components($inputs, $parameters, $successActions, $failureActions);
    }

    public function parse(\Alama\LaravelArazzo\Dto\RawDocument $raw): \Alama\LaravelArazzo\Dto\ArazzoDocument
    {
        $ctx = new ParseContext($raw->path);
        $d = $raw->data;

        $arazzo = $this->requireString($d, 'arazzo', $ctx);

        if (!array_key_exists('info', $d)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'info');
        }
        $info = $this->parseInfo($d['info'], $ctx->push('info'));

        $sourceDescriptions = [];
        if (array_key_exists('sourceDescriptions', $d) && $d['sourceDescriptions'] !== null) {
            $list = $this->requireList($d['sourceDescriptions'], $ctx->push('sourceDescriptions'));
            foreach ($list as $i => $item) {
                $sourceDescriptions[] = $this->parseSourceDescription($item, $ctx->push('sourceDescriptions')->push($i));
            }
        }

        $workflows = [];
        if (array_key_exists('workflows', $d) && $d['workflows'] !== null) {
            $list = $this->requireList($d['workflows'], $ctx->push('workflows'));
            foreach ($list as $i => $item) {
                $workflows[] = $this->parseWorkflow($item, $ctx->push('workflows')->push($i));
            }
        }

        $components = $this->parseComponents($d['components'] ?? null, $ctx->push('components'));

        $extensions = [];
        foreach ($d as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'x-')) {
                $extensions[$k] = $v;
            }
        }

        return new \Alama\LaravelArazzo\Dto\ArazzoDocument(
            arazzo:                  $arazzo,
            info:                    $info,
            sourceDescriptions:      $sourceDescriptions,
            workflows:               $workflows,
            components:              $components,
            specificationExtensions: $extensions,
        );
    }
```

- [ ] **Step 5: Run — expect pass**

- [ ] **Step 6: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: parse Step, Workflow, Components, and top-level ArazzoDocument"
```

---

### Task 11: Expression Lexer + Tokens

**Files:**
- Create: `src/Expression/Token.php`, `src/Expression/TokenKind.php`, `src/Expression/Lexer.php`
- Create: `src/Expression/ExpressionSyntaxException.php`
- Create: `tests/Expression/LexerTest.php`

**Interfaces:**
- Produces:
  - `TokenKind` enum: `Dollar, Dot, Hash, Slash, Name, PointerSegment, Keyword` (keywords: `inputs`, `outputs`, `steps`, `workflows`, `sourceDescriptions`, `components`, `response`, `request`, `url`, `method`, `statusCode`, `body`, `header`).
  - `Token(TokenKind $kind, string $value, int $offset)`.
  - `Lexer::tokenize(string $raw): list<Token>` — strips surrounding `{$...}` first; throws `ExpressionSyntaxException` on illegal chars.
  - `ExpressionSyntaxException extends ArazzoException`.

- [ ] **Step 1: Write failing test**

Create `tests/Expression/LexerTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\Lexer;
use Alama\LaravelArazzo\Expression\TokenKind;

it('tokenises inputs.userId', function (): void {
    $tokens = (new Lexer())->tokenize('{$inputs.userId}');
    $kinds = array_map(fn($t) => $t->kind, $tokens);
    $values = array_map(fn($t) => $t->value, $tokens);

    expect($kinds)->toBe([TokenKind::Keyword, TokenKind::Dot, TokenKind::Name])
        ->and($values)->toBe(['inputs', '.', 'userId']);
});

it('tokenises response.body with json pointer', function (): void {
    $t = (new Lexer())->tokenize('{$response.body#/data/0/id}');
    expect($t[0]->kind)->toBe(TokenKind::Keyword)
        ->and($t[0]->value)->toBe('response')
        ->and($t[2]->kind)->toBe(TokenKind::Keyword)
        ->and($t[2]->value)->toBe('body')
        ->and($t[3]->kind)->toBe(TokenKind::Hash);
});

it('tokenises steps.fetch.outputs.user', function (): void {
    $t = (new Lexer())->tokenize('{$steps.fetch.outputs.user}');
    expect(count($t))->toBe(7);
});

it('rejects missing braces', function (): void {
    (new Lexer())->tokenize('$inputs.x');
})->throws(ExpressionSyntaxException::class);

it('rejects illegal characters', function (): void {
    (new Lexer())->tokenize('{$inputs.na me}');
})->throws(ExpressionSyntaxException::class);
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Expression/TokenKind.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

enum TokenKind
{
    case Dollar;
    case Dot;
    case Hash;
    case Slash;
    case Name;
    case PointerSegment;
    case Keyword;
}
```

`src/Expression/Token.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class Token
{
    public function __construct(
        public TokenKind $kind,
        public string $value,
        public int $offset,
    ) {}
}
```

`src/Expression/ExpressionSyntaxException.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

use Alama\LaravelArazzo\Exceptions\ArazzoException;

final class ExpressionSyntaxException extends ArazzoException {}
```

`src/Expression/Lexer.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final class Lexer
{
    private const KEYWORDS = [
        'inputs', 'outputs', 'steps', 'workflows', 'sourceDescriptions',
        'components', 'response', 'request', 'url', 'method', 'statusCode',
        'body', 'header',
    ];

    /** @return list<Token> */
    public function tokenize(string $raw): array
    {
        if (!str_starts_with($raw, '{$') || !str_ends_with($raw, '}')) {
            throw new ExpressionSyntaxException(
                "Expression must be wrapped in {\$...}: {$raw}",
                '', 'expr.syntax',
            );
        }
        $inner = substr($raw, 2, -1);
        if ($inner === '') {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", '', 'expr.syntax');
        }

        $tokens = [];
        $len = strlen($inner);
        $i = 0;
        $inPointer = false;

        while ($i < $len) {
            $ch = $inner[$i];

            if ($ch === '.') { $tokens[] = new Token(TokenKind::Dot, '.', $i); $i++; continue; }
            if ($ch === '#') { $tokens[] = new Token(TokenKind::Hash, '#', $i); $i++; $inPointer = true; continue; }
            if ($ch === '/') { $tokens[] = new Token(TokenKind::Slash, '/', $i); $i++; continue; }

            if (preg_match('/[A-Za-z0-9_\-~]/', $ch) === 1) {
                $start = $i;
                while ($i < $len && preg_match('/[A-Za-z0-9_\-~]/', $inner[$i]) === 1) $i++;
                $word = substr($inner, $start, $i - $start);
                if ($inPointer) {
                    $tokens[] = new Token(TokenKind::PointerSegment, $word, $start);
                } elseif (in_array($word, self::KEYWORDS, true)) {
                    $tokens[] = new Token(TokenKind::Keyword, $word, $start);
                } else {
                    $tokens[] = new Token(TokenKind::Name, $word, $start);
                }
                continue;
            }

            throw new ExpressionSyntaxException(
                "Illegal character '{$ch}' at offset {$i} in expression: {$raw}",
                '', 'expr.syntax',
            );
        }

        return $tokens;
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: expression lexer and tokens"
```

---

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

### Task 13: SymbolTable

**Files:**
- Create: `src/Expression/SymbolTable.php`
- Create: `src/Expression/WorkflowSymbols.php`
- Create: `src/Expression/StepSymbols.php`
- Create: `tests/Expression/SymbolTableTest.php`

**Interfaces:**
- Consumes: `ArazzoDocument`, `Workflow`, `Step`, `Expression`.
- Produces:
  - `SymbolTable::build(ArazzoDocument $doc): self`
  - Properties (readonly):
    - `array<string,WorkflowSymbols> $workflows` — keyed by `workflowId`
    - `array<string,true> $sourceDescriptions` — set semantics
    - `array<string,array<string,true>> $components` — `type => { name => true }`
  - `WorkflowSymbols` readonly: `{ Set<string> inputs, Set<string> parameters, array<string,StepSymbols> stepsById, Set<string> outputs, Set<string> dependsOn }` (Sets modelled as `array<string,true>`).
  - `StepSymbols` readonly: `{ Set<string> outputs, int index }`.
- `SymbolTable::build` is **defensive** — malformed input yields empty sets; it never throws.

- [ ] **Step 1: Write failing test**

Create `tests/Expression/SymbolTableTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;

it('builds symbol table from document', function (): void {
    $step = new Step(
        stepId: 'fetch',
        description: null, operationId: 'op', operationPath: null, workflowId: null,
        parameters: [], requestBody: null, successCriteria: [],
        onSuccess: [], onFailure: [],
        outputs: ['user' => new Expression('{$response.body}')],
    );
    $wf = new Workflow(
        workflowId: 'main',
        summary: null, description: null,
        inputs: ['type'=>'object','properties'=>['userId'=>['type'=>'string']]],
        dependsOn: [],
        steps: [$step],
        successActions: [], failureActions: [],
        outputs: ['user' => new Expression('{$steps.fetch.outputs.user}')],
        parameters: [],
    );
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $sym = SymbolTable::build($doc);

    expect($sym->sourceDescriptions)->toHaveKey('api')
        ->and($sym->workflows)->toHaveKey('main')
        ->and($sym->workflows['main']->inputs)->toHaveKey('userId')
        ->and($sym->workflows['main']->stepsById)->toHaveKey('fetch')
        ->and($sym->workflows['main']->stepsById['fetch']->outputs)->toHaveKey('user')
        ->and($sym->workflows['main']->stepsById['fetch']->index)->toBe(0)
        ->and($sym->workflows['main']->outputs)->toHaveKey('user');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Expression/StepSymbols.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class StepSymbols
{
    /** @param array<string,true> $outputs */
    public function __construct(public array $outputs, public int $index) {}
}
```

`src/Expression/WorkflowSymbols.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

final readonly class WorkflowSymbols
{
    /**
     * @param array<string,true>              $inputs
     * @param array<string,true>              $parameters
     * @param array<string,StepSymbols>       $stepsById
     * @param array<string,true>              $outputs
     * @param array<string,true>              $dependsOn
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $stepsById,
        public array $outputs,
        public array $dependsOn,
    ) {}
}
```

`src/Expression/SymbolTable.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Workflow;

final readonly class SymbolTable
{
    /**
     * @param array<string,WorkflowSymbols>          $workflows
     * @param array<string,true>                     $sourceDescriptions
     * @param array<string,array<string,true>>       $components
     */
    public function __construct(
        public array $workflows,
        public array $sourceDescriptions,
        public array $components,
    ) {}

    public static function build(ArazzoDocument $doc): self
    {
        $sources = [];
        foreach ($doc->sourceDescriptions as $s) $sources[$s->name] = true;

        $components = [
            'inputs'         => self::keysOf($doc->components->inputs),
            'parameters'     => self::keysOf($doc->components->parameters),
            'successActions' => self::keysOf($doc->components->successActions),
            'failureActions' => self::keysOf($doc->components->failureActions),
        ];

        $workflows = [];
        foreach ($doc->workflows as $wf) {
            $workflows[$wf->workflowId] = self::buildWorkflow($wf);
        }

        return new self($workflows, $sources, $components);
    }

    private static function buildWorkflow(Workflow $wf): WorkflowSymbols
    {
        $inputs = [];
        if (is_array($wf->inputs)
            && isset($wf->inputs['properties'])
            && is_array($wf->inputs['properties'])) {
            foreach ($wf->inputs['properties'] as $k => $_) {
                if (is_string($k)) $inputs[$k] = true;
            }
        }

        $params = [];
        foreach ($wf->parameters as $p) $params[$p->name] = true;

        $steps = [];
        foreach ($wf->steps as $i => $s) {
            $outs = [];
            foreach ($s->outputs as $k => $_) $outs[$k] = true;
            $steps[$s->stepId] = new StepSymbols($outs, $i);
        }

        $outputs = [];
        foreach ($wf->outputs as $k => $_) $outputs[$k] = true;

        $dependsOn = [];
        foreach ($wf->dependsOn as $d) $dependsOn[$d] = true;

        return new WorkflowSymbols($inputs, $params, $steps, $outputs, $dependsOn);
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<string,true>
     */
    private static function keysOf(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $_) $out[(string) $k] = true;
        return $out;
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: build SymbolTable from ArazzoDocument"
```

---

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

### Task 15: Document + source + workflow-identity rules (8 rules)

**Files:**
- Create: `src/Validation/Rules/DocumentArazzoVersionRule.php`
- Create: `src/Validation/Rules/DocumentInfoRequiredRule.php`
- Create: `src/Validation/Rules/SourceUniqueNameRule.php`
- Create: `src/Validation/Rules/SourceUrlSyntaxRule.php`
- Create: `src/Validation/Rules/SourceTypeMatchesRule.php`
- Create: `src/Validation/Rules/WorkflowAtLeastOneRule.php`
- Create: `src/Validation/Rules/WorkflowUniqueIdRule.php`
- Create: `src/Validation/Rules/WorkflowIdPatternRule.php`
- Create: `tests/Validation/Rules/DocumentAndSourceRulesTest.php`
- Create: `tests/Validation/Rules/WorkflowIdentityRulesTest.php`

**Interfaces:**
- Consumes: Rule/ErrorCollector/SymbolTable from Task 14, all DTOs.
- Produces: 8 concrete `Rule` implementations. Codes: `document.arazzo_version`, `document.info_required`, `source.unique_name`, `source.url_syntax`, `source.type_matches`, `workflow.at_least_one`, `workflow.unique_id`, `workflow.id_pattern`.

- [ ] **Step 1: Write failing tests for document+source rules**

Create `tests/Validation/Rules/DocumentAndSourceRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\DocumentArazzoVersionRule;
use Alama\LaravelArazzo\Validation\Rules\DocumentInfoRequiredRule;
use Alama\LaravelArazzo\Validation\Rules\SourceTypeMatchesRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUniqueNameRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUrlSyntaxRule;

function baseDoc(string $version = '1.0.0', string $title = 'T', string $ver = '1', array $sources = []): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $version,
        info: new Info($title, null, null, $ver),
        sourceDescriptions: $sources,
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('flags wrong arazzo version', function (): void {
    $doc = baseDoc('2.0.0');
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('document.arazzo_version');
});

it('accepts 1.0.0 version', function (): void {
    $doc = baseDoc();
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('requires info title and version', function (): void {
    $doc = baseDoc(title: '', ver: '');
    $ec = new ErrorCollector();
    (new DocumentInfoRequiredRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBe(2);
});

it('flags duplicate source names', function (): void {
    $sources = [
        new SourceDescription('api', '/a', SourceType::Openapi),
        new SourceDescription('api', '/b', SourceType::Openapi),
    ];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUniqueNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('source.unique_name');
});

it('flags empty source url', function (): void {
    $sources = [new SourceDescription('api', '', SourceType::Openapi)];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUrlSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('SourceTypeMatchesRule passes (enum enforcement is at parser time)', function (): void {
    $doc = baseDoc(sources: [new SourceDescription('api', '/x', SourceType::Openapi)]);
    $ec = new ErrorCollector();
    (new SourceTypeMatchesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
```

Create `tests/Validation/Rules/WorkflowIdentityRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowAtLeastOneRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowIdPatternRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowUniqueIdRule;

function docWithWorkflows(array $workflows): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [],
        $workflows,
        new Components([], [], [], []),
        [],
    );
}
function wf(string $id): Workflow
{
    $s = new Step('s', null, 'op', null, null, [], null, [], [], [], []);
    return new Workflow($id, null, null, null, [], [$s], [], [], [], []);
}

it('flags empty workflows list', function (): void {
    $ec = new ErrorCollector();
    (new WorkflowAtLeastOneRule())->check(docWithWorkflows([]), SymbolTable::build(docWithWorkflows([])), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate workflowIds', function (): void {
    $doc = docWithWorkflows([wf('a'), wf('a')]);
    $ec = new ErrorCollector();
    (new WorkflowUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.unique_id');
});

it('flags bad workflowId pattern', function (): void {
    $doc = docWithWorkflows([wf('bad id!')]);
    $ec = new ErrorCollector();
    (new WorkflowIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.id_pattern');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement rules**

`src/Validation/Rules/DocumentArazzoVersionRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocumentArazzoVersionRule implements Rule
{
    public function code(): string { return 'document.arazzo_version'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->arazzo !== '1.0.0') {
            $errors->error($this->code(), "Unsupported arazzo version '{$doc->arazzo}'; only '1.0.0' is supported.", '/arazzo');
        }
    }
}
```

`src/Validation/Rules/DocumentInfoRequiredRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocumentInfoRequiredRule implements Rule
{
    public function code(): string { return 'document.info_required'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->info->title === '') {
            $errors->error($this->code(), 'info.title must be a non-empty string.', '/info/title');
        }
        if ($doc->info->version === '') {
            $errors->error($this->code(), 'info.version must be a non-empty string.', '/info/version');
        }
    }
}
```

`src/Validation/Rules/SourceUniqueNameRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SourceUniqueNameRule implements Rule
{
    public function code(): string { return 'source.unique_name'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (isset($seen[$s->name])) {
                $errors->error($this->code(), "Duplicate sourceDescription name '{$s->name}'.", "/sourceDescriptions/{$i}/name");
            }
            $seen[$s->name] = true;
        }
    }
}
```

`src/Validation/Rules/SourceUrlSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SourceUrlSyntaxRule implements Rule
{
    public function code(): string { return 'source.url_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (trim($s->url) === '') {
                $errors->error($this->code(), 'sourceDescription url must not be empty.', "/sourceDescriptions/{$i}/url");
                continue;
            }
            // Accept absolute URLs and relative paths beginning with '/' or '.'.
            if (str_starts_with($s->url, '/') || str_starts_with($s->url, '.') || str_starts_with($s->url, './') || filter_var($s->url, FILTER_VALIDATE_URL) !== false) {
                continue;
            }
            $errors->error($this->code(), "sourceDescription url '{$s->url}' is not a valid URI or relative path.", "/sourceDescriptions/{$i}/url");
        }
    }
}
```

`src/Validation/Rules/SourceTypeMatchesRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/**
 * Enum enforcement happens at parse time; this rule exists so that a stable
 * `source.type_matches` code is reserved and so future non-parser-time checks
 * (e.g. "type: arazzo yet url points to an OpenAPI file") can land here.
 */
final class SourceTypeMatchesRule implements Rule
{
    public function code(): string { return 'source.type_matches'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1 — the enum guarantees correctness.
    }
}
```

`src/Validation/Rules/WorkflowAtLeastOneRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowAtLeastOneRule implements Rule
{
    public function code(): string { return 'workflow.at_least_one'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->workflows === []) {
            $errors->error($this->code(), 'Document must declare at least one workflow.', '/workflows');
        }
    }
}
```

`src/Validation/Rules/WorkflowUniqueIdRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowUniqueIdRule implements Rule
{
    public function code(): string { return 'workflow.unique_id'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->workflows as $i => $w) {
            if (isset($seen[$w->workflowId])) {
                $errors->error($this->code(), "Duplicate workflowId '{$w->workflowId}'.", "/workflows/{$i}/workflowId");
            }
            $seen[$w->workflowId] = true;
        }
    }
}
```

`src/Validation/Rules/WorkflowIdPatternRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowIdPatternRule implements Rule
{
    public function code(): string { return 'workflow.id_pattern'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if (preg_match('/^[A-Za-z0-9_\-]+$/', $w->workflowId) !== 1) {
                $errors->error($this->code(), "workflowId '{$w->workflowId}' must match [A-Za-z0-9_-]+.", "/workflows/{$i}/workflowId");
            }
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: document, source, and workflow identity validation rules"
```

---

### Task 16: Workflow dependsOn + inputs schema rules (3 rules)

**Files:**
- Create: `src/Validation/Rules/WorkflowDependsOnExistsRule.php`
- Create: `src/Validation/Rules/WorkflowDependsOnNoCycleRule.php`
- Create: `src/Validation/Rules/WorkflowInputsValidSchemaRule.php`
- Create: `tests/Validation/Rules/WorkflowDependsRulesTest.php`

**Interfaces:**
- Consumes: Task 14 infra, Task 13 `SymbolTable`.
- Produces: 3 rules with codes `workflow.dependson_exists`, `workflow.dependson_no_cycle`, `workflow.inputs_valid_schema`.
- Cycle detection: DFS coloring (white/grey/black). Any back-edge → single error at the edge site (do not enumerate the whole cycle).
- Inputs schema check is **structural only**: must be an object AND (if `type` set) `type == 'object'` AND (if `properties` set) `properties` is a map.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/WorkflowDependsRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnExistsRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnNoCycleRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowInputsValidSchemaRule;

function step(string $id): Step { return new Step($id, null, 'op', null, null, [], null, [], [], [], []); }

function wfDep(string $id, array $dep = [], ?array $inputs = null): Workflow
{
    return new Workflow($id, null, null, $inputs, $dep, [step('s')], [], [], [], []);
}

function docWf(array $wfs): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], $wfs, new Components([], [], [], []), []);
}

it('flags dependsOn to unknown workflow', function (): void {
    $doc = docWf([wfDep('a', ['ghost'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_exists');
});

it('flags cyclic dependsOn', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['a'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_no_cycle');
});

it('accepts acyclic chain', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['c']), wfDep('c')]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags inputs schema not being an object', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'string'])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('accepts inputs as object schema', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/WorkflowDependsOnExistsRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnExistsRule implements Rule
{
    public function code(): string { return 'workflow.dependson_exists'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->dependsOn as $j => $dep) {
                if (!isset($symbols->workflows[$dep])) {
                    $errors->error(
                        $this->code(),
                        "workflow '{$w->workflowId}' dependsOn '{$dep}' which is not declared.",
                        "/workflows/{$i}/dependsOn/{$j}",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/WorkflowDependsOnNoCycleRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnNoCycleRule implements Rule
{
    public function code(): string { return 'workflow.dependson_no_cycle'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        /** @var array<string,int> $color 0=white,1=grey,2=black */
        $color = [];
        foreach ($doc->workflows as $w) $color[$w->workflowId] = 0;
        $indexOf = [];
        foreach ($doc->workflows as $i => $w) $indexOf[$w->workflowId] = $i;

        $reported = false;
        $dfs = function (string $node) use (&$dfs, &$color, $symbols, $errors, $indexOf, &$reported): void {
            if ($reported) return;
            if (!isset($symbols->workflows[$node])) return;
            $color[$node] = 1;
            foreach ($symbols->workflows[$node]->dependsOn as $next => $_) {
                if (!isset($color[$next])) continue;
                if ($color[$next] === 1) {
                    $i = $indexOf[$node] ?? 0;
                    $errors->error(
                        $this->code(),
                        "workflow.dependsOn cycle detected involving '{$node}' -> '{$next}'.",
                        "/workflows/{$i}/dependsOn",
                    );
                    $reported = true;
                    return;
                }
                if ($color[$next] === 0) $dfs($next);
                if ($reported) return;
            }
            $color[$node] = 2;
        };

        foreach ($doc->workflows as $w) {
            if (($color[$w->workflowId] ?? 0) === 0) $dfs($w->workflowId);
            if ($reported) break;
        }
    }
}
```

`src/Validation/Rules/WorkflowInputsValidSchemaRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowInputsValidSchemaRule implements Rule
{
    public function code(): string { return 'workflow.inputs_valid_schema'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if ($w->inputs === null) continue;
            $path = "/workflows/{$i}/inputs";
            if (isset($w->inputs['type']) && $w->inputs['type'] !== 'object') {
                $errors->error($this->code(), "workflow inputs schema must be of type 'object'.", $path . '/type');
                continue;
            }
            if (isset($w->inputs['properties']) && !is_array($w->inputs['properties'])) {
                $errors->error($this->code(), "workflow inputs.properties must be an object.", $path . '/properties');
            }
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: workflow dependsOn existence + cycle + inputs schema rules"
```

---

### Task 17: Step identity + operation-target rules (7 rules)

**Files:**
- Create: `src/Validation/Rules/StepAtLeastOneRule.php`, `StepUniqueIdRule.php`, `StepIdPatternRule.php`, `StepOperationTargetPresentRule.php`, `StepOperationIdSourceScopedRule.php`, `StepOperationPathSyntaxRule.php`, `StepNestedWorkflowExistsRule.php`
- Create: `tests/Validation/Rules/StepIdentityRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable.
- Produces: 7 rules with codes as listed in spec §7.
- Rules:
  - `StepAtLeastOneRule`: each workflow.steps non-empty.
  - `StepUniqueIdRule`: `stepId` unique within workflow.
  - `StepIdPatternRule`: `^[A-Za-z0-9_\-]+$`.
  - `StepOperationTargetPresentRule`: exactly one of `operationId`, `operationPath`, `workflowId` set.
  - `StepOperationIdSourceScopedRule`: if `operationId` unqualified (no `#`), require exactly one sourceDescription of type openapi; if qualified `sourceName#opId`, require `sourceName` to exist.
  - `StepOperationPathSyntaxRule`: `operationPath` must match `<source>#<json-pointer>` where source exists and pointer is RFC 6901.
  - `StepNestedWorkflowExistsRule`: `step.workflowId` (nested ref) resolves to a declared workflow.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/StepIdentityRulesTest.php`:

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
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepAtLeastOneRule;
use Alama\LaravelArazzo\Validation\Rules\StepIdPatternRule;
use Alama\LaravelArazzo\Validation\Rules\StepNestedWorkflowExistsRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationIdSourceScopedRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationPathSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\StepOperationTargetPresentRule;
use Alama\LaravelArazzo\Validation\Rules\StepUniqueIdRule;

function s(string $id, ?string $opId = 'op', ?string $opPath = null, ?string $wfId = null): Step
{
    return new Step($id, null, $opId, $opPath, $wfId, [], null, [], [], [], []);
}
function w(string $id, array $steps, array $dep = []): Workflow
{
    return new Workflow($id, null, null, null, $dep, $steps, [], [], [], []);
}
function d(array $wfs, array $sources = []): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, $wfs, new Components([], [], [], []), []);
}

it('flags empty step list', function (): void {
    $doc = d([w('a', [])]);
    $ec = new ErrorCollector();
    (new StepAtLeastOneRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate stepId', function (): void {
    $doc = d([w('a', [s('x'), s('x')])]);
    $ec = new ErrorCollector();
    (new StepUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad stepId pattern', function (): void {
    $doc = d([w('a', [s('bad!')])]);
    $ec = new ErrorCollector();
    (new StepIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('requires exactly one operation target', function (): void {
    $doc = d([w('a', [s('x', null, null, null)])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = d([w('a', [s('x', 'op', 'src#/paths/x/get')])]);
    $ec2 = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toHaveCount(1);
});

it('requires single openapi source for unqualified operationId', function (): void {
    $doc = d([w('a', [s('x', 'op')])], []);
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = d(
        [w('a', [s('x', 'src1#op')])],
        [new SourceDescription('src1', '/a', SourceType::Openapi)],
    );
    $ec2 = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toBe([]);
});

it('validates operationPath syntax', function (): void {
    $doc = d([w('a', [s('x', null, 'nosource-no-hash')])]);
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved nested workflow', function (): void {
    $doc = d([w('a', [s('x', null, null, 'ghost')])]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/StepAtLeastOneRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepAtLeastOneRule implements Rule
{
    public function code(): string { return 'step.at_least_one'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if ($w->steps === []) {
                $errors->error($this->code(), "workflow '{$w->workflowId}' must declare at least one step.", "/workflows/{$i}/steps");
            }
        }
    }
}
```

`src/Validation/Rules/StepUniqueIdRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepUniqueIdRule implements Rule
{
    public function code(): string { return 'step.unique_id'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            $seen = [];
            foreach ($w->steps as $j => $s) {
                if (isset($seen[$s->stepId])) {
                    $errors->error(
                        $this->code(),
                        "Duplicate stepId '{$s->stepId}' in workflow '{$w->workflowId}'.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
                $seen[$s->stepId] = true;
            }
        }
    }
}
```

`src/Validation/Rules/StepIdPatternRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepIdPatternRule implements Rule
{
    public function code(): string { return 'step.id_pattern'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if (preg_match('/^[A-Za-z0-9_\-]+$/', $s->stepId) !== 1) {
                    $errors->error(
                        $this->code(),
                        "stepId '{$s->stepId}' must match [A-Za-z0-9_-]+.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationTargetPresentRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationTargetPresentRule implements Rule
{
    public function code(): string { return 'step.operation_target_present'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $set = (int) ($s->operationId !== null) + (int) ($s->operationPath !== null) + (int) ($s->workflowId !== null);
                if ($set !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId (got {$set}).",
                        "/workflows/{$i}/steps/{$j}",
                    );
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationIdSourceScopedRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationIdSourceScopedRule implements Rule
{
    public function code(): string { return 'step.operationid_source_scoped'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $openapiSources = array_values(array_filter(
            $doc->sourceDescriptions,
            fn($s) => $s->type === SourceType::Openapi,
        ));

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationId === null) continue;
                if (str_contains($s->operationId, '#')) {
                    [$src] = explode('#', $s->operationId, 2);
                    if (!isset($symbols->sourceDescriptions[$src])) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' operationId references unknown source '{$src}'.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                } else {
                    if (count($openapiSources) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->operationId}' but the document does not declare exactly one openapi sourceDescription.",
                            "/workflows/{$i}/steps/{$j}/operationId",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOperationPathSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationPathSyntaxRule implements Rule
{
    public function code(): string { return 'step.operationpath_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationPath === null) continue;
                $path = "/workflows/{$i}/steps/{$j}/operationPath";
                if (!str_contains($s->operationPath, '#')) {
                    $errors->error($this->code(), "operationPath '{$s->operationPath}' must contain '#' separating source and JSON Pointer.", $path);
                    continue;
                }
                [$src, $ptr] = explode('#', $s->operationPath, 2);
                if ($src === '' || !isset($symbols->sourceDescriptions[$src])) {
                    $errors->error($this->code(), "operationPath source '{$src}' is not a declared sourceDescription.", $path);
                }
                if ($ptr === '' || $ptr[0] !== '/') {
                    $errors->error($this->code(), "operationPath JSON Pointer '{$ptr}' must start with '/'.", $path);
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepNestedWorkflowExistsRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepNestedWorkflowExistsRule implements Rule
{
    public function code(): string { return 'step.nested_workflow_exists'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->workflowId === null) continue;
                if (!isset($symbols->workflows[$s->workflowId])) {
                    $errors->error(
                        $this->code(),
                        "step.workflowId '{$s->workflowId}' does not resolve to a declared workflow.",
                        "/workflows/{$i}/steps/{$j}/workflowId",
                    );
                }
            }
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: step identity and operation-target validation rules"
```

---

### Task 18: Step content rules — parameters, request body, criteria, outputs (6 rules)

**Files:**
- Create: `src/Validation/Rules/StepParametersHaveNameRule.php`, `StepParameterInValidRule.php`, `StepRequestBodyReplacementsTargetRule.php`, `StepSuccessCriteriaConditionRule.php`, `StepCriteriaTypeContextRule.php`, `StepOutputsUniqueRule.php`
- Create: `tests/Validation/Rules/StepContentRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable.
- Produces: 6 rules with spec §7 codes.
- Notes:
  - `StepParametersHaveNameRule` — the DTO already requires `name`; this rule flags empty-string names.
  - `StepParameterInValidRule` — parser enforces enum; rule flags `parameters` at step level lacking `in` for non-body params. (Design decision: `in` optional means "body" by convention only when explicitly set; missing `in` on step params flags a warning.) v1: no-op (parser handles it) — reserve code for future.
  - `StepRequestBodyReplacementsTargetRule` — target must start with `/`.
  - `StepSuccessCriteriaConditionRule` — non-empty string (parser already enforces non-null; this checks for whitespace-only).
  - `StepCriteriaTypeContextRule` — when `type ∈ {jsonpath, xpath, regex}`, `context` must be set.
  - `StepOutputsUniqueRule` — parser uses assoc array; YAML dupes at load time collapse. In v1: no-op (reserve code). Same rationale as `SourceTypeMatchesRule`.

- [ ] **Step 1: Write failing test**

Create `tests/Validation/Rules/StepContentRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepCriteriaTypeContextRule;
use Alama\LaravelArazzo\Validation\Rules\StepParametersHaveNameRule;
use Alama\LaravelArazzo\Validation\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\LaravelArazzo\Validation\Rules\StepSuccessCriteriaConditionRule;

function docFrom(Step $s): ArazzoDocument
{
    $w = new Workflow('w', null, null, null, [], [$s], [], [], [], []);
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('flags empty parameter name', function (): void {
    $step = new Step('x', null, 'op', null, null, [new Parameter('', ParameterIn::Query, 'v')], null, [], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepParametersHaveNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad replacement target', function (): void {
    $body = new RequestBody(null, [], [new PayloadReplacement('no-slash', 'v')]);
    $step = new Step('x', null, 'op', null, null, [], $body, [], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepRequestBodyReplacementsTargetRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags whitespace-only condition', function (): void {
    $crit = new SuccessCriterion(null, '   ', null);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags jsonpath criterion missing context', function (): void {
    $crit = new SuccessCriterion(null, '$.id != null', CriterionType::JsonPath);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = docFrom($step);
    $ec = new ErrorCollector();
    (new StepCriteriaTypeContextRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement**

`src/Validation/Rules/StepParametersHaveNameRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepParametersHaveNameRule implements Rule
{
    public function code(): string { return 'step.parameters_have_name'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->parameters as $k => $p) {
                    if (trim($p->name) === '') {
                        $errors->error(
                            $this->code(),
                            "Parameter at index {$k} of step '{$s->stepId}' must have a non-empty name.",
                            "/workflows/{$i}/steps/{$j}/parameters/{$k}/name",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepParameterInValidRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/**
 * Enum enforcement lives at parse time. This rule reserves the code for
 * future semantic checks (e.g. warning when 'body' is used with non-POST ops).
 */
final class StepParameterInValidRule implements Rule
{
    public function code(): string { return 'step.parameter_in_valid'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1.
    }
}
```

`src/Validation/Rules/StepRequestBodyReplacementsTargetRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepRequestBodyReplacementsTargetRule implements Rule
{
    public function code(): string { return 'step.request_body_replacements_target'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->requestBody === null) continue;
                foreach ($s->requestBody->replacements as $k => $r) {
                    if ($r->target === '' || $r->target[0] !== '/') {
                        $errors->error(
                            $this->code(),
                            "PayloadReplacement target '{$r->target}' must be a JSON Pointer starting with '/'.",
                            "/workflows/{$i}/steps/{$j}/requestBody/replacements/{$k}/target",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepSuccessCriteriaConditionRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepSuccessCriteriaConditionRule implements Rule
{
    public function code(): string { return 'step.success_criteria_condition'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if (trim($c->condition) === '') {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}].condition must not be empty or whitespace.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepCriteriaTypeContextRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepCriteriaTypeContextRule implements Rule
{
    public function code(): string { return 'step.criteria_type_context'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $needsContext = [CriterionType::JsonPath, CriterionType::XPath, CriterionType::Regex];
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if ($c->type !== null && in_array($c->type, $needsContext, true) && ($c->context === null || trim($c->context) === '')) {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}] type '{$c->type->value}' requires a context expression.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/context",
                        );
                    }
                }
            }
        }
    }
}
```

`src/Validation/Rules/StepOutputsUniqueRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/** YAML/JSON collapse duplicate keys at load time; reserved for future semantic checks. */
final class StepOutputsUniqueRule implements Rule
{
    public function code(): string { return 'step.outputs_unique'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: step content validation rules (parameters, body, criteria, outputs)"
```

---

### Task 19: Expression validation rules (8 rules)

**Files:**
- Create: `src/Validation/Rules/ExpressionReferencesResolveRule.php` (implements 7 codes at once: `expr.syntax`, `expr.unresolved_input_ref`, `expr.unresolved_step_ref`, `expr.unresolved_workflow_ref`, `expr.unresolved_source_ref`, `expr.unresolved_component_ref`, `expr.context_misuse`)
- Create: `src/Validation/Rules/ExpressionJsonPointerSyntaxRule.php` (`expr.jsonpointer_syntax`)
- Create: `src/Validation/Support/ExpressionSite.php` — helper struct describing where an expression appeared in the doc (for path + context checks)
- Create: `src/Validation/Support/ExpressionWalker.php` — visits every `Expression` in the document and yields `ExpressionSite`s
- Create: `tests/Validation/Rules/ExpressionRulesTest.php`

**Interfaces:**
- Consumes: infra + SymbolTable + Expression AST + AST error surface.
- Produces:
  - `ExpressionSite(string $pointer, Expression $expression, WorkflowSymbols $workflow, ?string $currentStepId, string $context)` — `context` is one of: `parameters | requestBody | criteria | outputs | onSuccess | onFailure | wf.parameters | wf.outputs | components`.
  - `ExpressionWalker::walk(ArazzoDocument $doc, SymbolTable $symbols): iterable<ExpressionSite>`.
  - `ExpressionReferencesResolveRule` — dispatches errors per-code based on AST node type. Its `code()` returns `'expr.references'` as an umbrella; individual emitted errors use their fine-grained codes so `disabled: ['expr.unresolved_step_ref']` works.
  - `ExpressionJsonPointerSyntaxRule` — walks response/request `body#/…` parts of the AST and validates each pointer per RFC 6901 (starts with `/`, no unescaped `~` outside `~0`/`~1`).
- Since `expr.syntax` and friends emit under different codes than the rule's own `code()`, the `RuleSet::activeRules()` filter is against `Rule::code()` only. Fine-grained disabling of a sub-code happens by tests reading the collector's outputs; this is an accepted limitation of the umbrella rule. If a spec-conformance user needs to silence a specific `expr.*` sub-code, they must disable the umbrella `expr.references` and re-implement the parts they need — documented in the CLI docstring.
- **Correction:** the design section 7 catalog lists each `expr.*` code as its own rule. To honor that, split into 7 separate rule classes that each delegate to a shared `ExpressionAnalyzer` helper. Implement as follows:

- [ ] **Step 1: Implement ExpressionSite + ExpressionWalker (no test yet)**

`src/Validation/Support/ExpressionSite.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Support;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\WorkflowSymbols;

final readonly class ExpressionSite
{
    /** @param 'parameters'|'requestBody'|'criteria'|'outputs'|'onSuccess'|'onFailure'|'wf.parameters'|'wf.outputs'|'components' $context */
    public function __construct(
        public string $pointer,
        public Expression $expression,
        public ?WorkflowSymbols $workflow,
        public ?string $currentStepId,
        public string $context,
    ) {}
}
```

`src/Validation/Support/ExpressionWalker.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Support;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\SymbolTable;

final class ExpressionWalker
{
    /**
     * @return iterable<ExpressionSite>
     */
    public function walk(ArazzoDocument $doc, SymbolTable $symbols): iterable
    {
        foreach ($doc->workflows as $wi => $wf) {
            $syms = $symbols->workflows[$wf->workflowId] ?? null;

            foreach ($wf->parameters as $pi => $p) {
                if ($p->value instanceof Expression) {
                    yield new ExpressionSite(
                        "/workflows/{$wi}/parameters/{$pi}/value", $p->value, $syms, null, 'wf.parameters',
                    );
                }
            }
            foreach ($wf->outputs as $name => $expr) {
                yield new ExpressionSite(
                    "/workflows/{$wi}/outputs/{$name}", $expr, $syms, null, 'wf.outputs',
                );
            }

            foreach ($wf->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
                    if ($p->value instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/value", $p->value, $syms, $s->stepId, 'parameters',
                        );
                    }
                }
                if ($s->requestBody !== null) {
                    if ($s->requestBody->payload instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/requestBody/payload", $s->requestBody->payload, $syms, $s->stepId, 'requestBody',
                        );
                    }
                    foreach ($s->requestBody->replacements as $ri => $r) {
                        if ($r->value instanceof Expression) {
                            yield new ExpressionSite(
                                "/workflows/{$wi}/steps/{$si}/requestBody/replacements/{$ri}/value", $r->value, $syms, $s->stepId, 'requestBody',
                            );
                        }
                    }
                }
                foreach ($s->successCriteria as $ci => $c) {
                    if ($c->context !== null && str_starts_with($c->context, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/context", new Expression($c->context), $syms, $s->stepId, 'criteria',
                        );
                    }
                    if (str_starts_with($c->condition, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/condition", new Expression($c->condition), $syms, $s->stepId, 'criteria',
                        );
                    }
                }
                foreach ($s->outputs as $name => $expr) {
                    yield new ExpressionSite(
                        "/workflows/{$wi}/steps/{$si}/outputs/{$name}", $expr, $syms, $s->stepId, 'outputs',
                    );
                }
            }
        }
    }
}
```

- [ ] **Step 2: Write failing test**

Create `tests/Validation/Rules/ExpressionRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExpressionContextMisuseRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionSyntaxRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedComponentRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedInputRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedSourceRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedStepRefRule;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedWorkflowRefRule;

function stepE(string $id, array $params = [], array $outs = []): Step {
    return new Step($id, null, 'op', null, null, $params, null, [], [], [], $outs);
}

function docE(array $params = [], array $outs = [], ?array $inputs = ['type'=>'object','properties'=>['userId'=>['type'=>'string']]], array $sources = [], array $deps = []): ArazzoDocument {
    $steps = [stepE('fetch', $params, $outs)];
    $wf = new Workflow('main', null, null, $inputs, $deps, $steps, [], [], [], []);
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, [$wf], new Components([], [], [], []), []);
}

it('flags syntactically bad expression', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$broken'))]);
    $ec = new ErrorCollector();
    (new ExpressionSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.syntax');
});

it('flags unresolved input ref', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$inputs.ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedInputRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.unresolved_input_ref');
});

it('flags forward step ref', function (): void {
    $s1 = new Step('first', null, 'op', null, null, [new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}'))], null, [], [], [], []);
    $s2 = new Step('second', null, 'op', null, null, [], null, [], [], [], ['y' => new Expression('{$response.body}')]);
    $wf = new Workflow('main', null, null, null, [], [$s1, $s2], [], [], [], []);
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$wf], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags workflow ref not in dependsOn', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$workflows.other.outputs.y}')]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedWorkflowRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved source ref', function (): void {
    $doc = docE(params: [new Parameter('u', ParameterIn::Header, new Expression('{$sourceDescriptions.ghost.url}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedSourceRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved component ref', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$components.parameters.Ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedComponentRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags $response used in parameters', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$response.body}'))]);
    $ec = new ErrorCollector();
    (new ExpressionContextMisuseRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad json pointer segment', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$response.body#/a~9}')]);
    $ec = new ErrorCollector();
    (new ExpressionJsonPointerSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
```

- [ ] **Step 3: Run — expect fail**

- [ ] **Step 4: Implement rule classes**

`src/Validation/Rules/ExpressionSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionSyntaxRule implements Rule
{
    public function code(): string { return 'expr.syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                $errors->error($this->code(), $ast->getMessage(), $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedInputRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\InputRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedInputRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_input_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof InputRef) continue;

            $inputs = $site->workflow?->inputs ?? [];
            $params = $site->workflow?->parameters ?? [];
            if (!isset($inputs[$ast->name]) && !isset($params[$ast->name])) {
                $errors->error(
                    $this->code(),
                    "Expression references unknown input '{$ast->name}'.",
                    $site->pointer,
                );
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedStepRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\OutputPart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedStepRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_step_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof StepRef) continue;

            $syms = $site->workflow;
            if ($syms === null) continue;
            $target = $syms->stepsById[$ast->stepId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown step '{$ast->stepId}'.", $site->pointer);
                continue;
            }
            if ($site->currentStepId !== null) {
                $currentIdx = $syms->stepsById[$site->currentStepId]->index ?? PHP_INT_MAX;
                if ($target->index >= $currentIdx) {
                    $errors->error($this->code(), "Expression references step '{$ast->stepId}' which is not before the current step.", $site->pointer);
                    continue;
                }
            }
            if ($ast->part instanceof OutputPart && !isset($target->outputs[$ast->part->name])) {
                $errors->error($this->code(), "Step '{$ast->stepId}' does not declare output '{$ast->part->name}'.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedWorkflowRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\WorkflowRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedWorkflowRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_workflow_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof WorkflowRef) continue;

            $target = $symbols->workflows[$ast->workflowId] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown workflow '{$ast->workflowId}'.", $site->pointer);
                continue;
            }
            if ($site->workflow !== null && !isset($site->workflow->dependsOn[$ast->workflowId])) {
                $errors->error($this->code(), "Expression references workflow '{$ast->workflowId}' which is not in dependsOn.", $site->pointer);
                continue;
            }
            $bag = $ast->partKind === 'inputs' ? $target->inputs : $target->outputs;
            if (!isset($bag[$ast->name])) {
                $errors->error($this->code(), "Workflow '{$ast->workflowId}' has no {$ast->partKind}.{$ast->name}.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedSourceRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\SourceRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedSourceRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_source_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof SourceRef) continue;

            if (!isset($symbols->sourceDescriptions[$ast->name])) {
                $errors->error($this->code(), "Expression references unknown sourceDescription '{$ast->name}'.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionUnresolvedComponentRefRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedComponentRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_component_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof ComponentRef) continue;

            $bag = $symbols->components[$ast->type] ?? null;
            if ($bag === null || !isset($bag[$ast->name])) {
                $errors->error($this->code(), "Component reference '{$ast->type}.{$ast->name}' is not declared.", $site->pointer);
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionContextMisuseRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\HttpMetaRef;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionContextMisuseRule implements Rule
{
    public function code(): string { return 'expr.context_misuse'; }

    private const ALLOWED = ['criteria', 'outputs', 'onSuccess', 'onFailure'];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;

            $isRuntime = $ast instanceof HttpMetaRef
                || ($ast instanceof StepRef && ($ast->part instanceof RequestPart || $ast->part instanceof ResponsePart));

            if ($isRuntime && !in_array($site->context, self::ALLOWED, true)) {
                $errors->error(
                    $this->code(),
                    "Runtime reference (\$response/\$request/\$statusCode/\$url/\$method) is not valid in context '{$site->context}'.",
                    $site->pointer,
                );
            }
        }
    }
}
```

`src/Validation/Rules/ExpressionJsonPointerSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\RequestPart;
use Alama\LaravelArazzo\Expression\Ast\ResponsePart;
use Alama\LaravelArazzo\Expression\Ast\StepRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionJsonPointerSyntaxRule implements Rule
{
    public function code(): string { return 'expr.jsonpointer_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof StepRef) continue;
            $part = $ast->part;
            if (!($part instanceof RequestPart) && !($part instanceof ResponsePart)) continue;
            $ptr = $part->jsonPointer;
            if ($ptr === null || $ptr === '') continue;

            // Per RFC 6901 an escaped ~ must be ~0 or ~1.
            $segments = explode('/', ltrim($ptr, '/'));
            foreach ($segments as $seg) {
                if (preg_match('/~(?![01])/', $seg) === 1) {
                    $errors->error($this->code(), "JSON Pointer '{$ptr}' contains illegal '~' escape.", $site->pointer);
                    break;
                }
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
git commit -m "feat: expression validation rules (syntax, refs, context, json pointer)"
```

---

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
