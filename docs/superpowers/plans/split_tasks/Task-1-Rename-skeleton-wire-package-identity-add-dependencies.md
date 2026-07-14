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

