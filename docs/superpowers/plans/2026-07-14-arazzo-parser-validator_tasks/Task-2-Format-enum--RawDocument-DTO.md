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

