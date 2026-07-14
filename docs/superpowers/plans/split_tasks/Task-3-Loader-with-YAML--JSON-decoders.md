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

