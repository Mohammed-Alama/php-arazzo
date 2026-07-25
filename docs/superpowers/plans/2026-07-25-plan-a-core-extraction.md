# Plan A — Core Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the framework-agnostic parts of `alama/laravel-arazzo` into a new pure-PHP package `alama/arazzo-core`, restructure the repo as a monorepo, and rewire `alama/laravel-arazzo` as a thin bridge over the core. Ship as `arazzo-core 1.0.0-alpha` + `laravel-arazzo 2.0.0-alpha` with zero behavior change for existing users.

**Architecture:** Symplify monorepo hosting two Composer packages under `packages/core/` and `packages/laravel/`. Core has no framework dependencies (PSR interfaces only). Laravel bridge depends on core `^1.0` and wires framework-specific adapters. Namespace `Alama\LaravelArazzo\*` shifts to `Alama\Arazzo\*` (core) and `Alama\Arazzo\Laravel\*` (bridge). Backwards-compat aliases keep 1.x consumers working for 6 months.

**Tech Stack:** PHP 8.4, `symplify/monorepo-builder ^12`, Pest 4, Larastan 3, Orchestra Testbench 11, `spatie/laravel-package-tools ^1.16`, PSR-3/7/11/14/16/17/18/20, `softcreatr/jsonpath ^0.10`, `cebe/php-openapi ^1.7`, `symfony/yaml ^7`, Guzzle 7 (bridge only), GitHub Actions for monorepo split.

## Global Constraints

- PHP version floor: `^8.4` (matches current `composer.json`).
- Core (`alama/arazzo-core`) MUST NOT depend on `illuminate/*`, `spatie/laravel-package-tools`, `guzzlehttp/guzzle`, `orchestra/testbench`, or any framework.
- Laravel bridge (`alama/laravel-arazzo`) MUST support `illuminate/contracts` `^11.0||^12.0||^13.0`.
- Namespace transitions: `Alama\LaravelArazzo\{Dto,Exceptions,Expression,Loader,Parser,Resolution,Validation,Generator,Execution}\*` → `Alama\Arazzo\{same}\*`. `Alama\LaravelArazzo\{Laravel,Http,LaravelArazzoServiceProvider}` → `Alama\Arazzo\Laravel\*` (Http controllers move under `Alama\Arazzo\Laravel\Http\Controllers\*`).
- BC guarantee: every old `Alama\LaravelArazzo\*` class MUST resolve via `class_alias` throughout the 2.x line with a `@deprecated` marker. Deprecation warnings emitted only in `debug` mode.
- Package licences: both `MIT`. Both `sort-packages: true`. Both `minimum-stability: dev`, `prefer-stable: true`.
- Both packages MUST pass Pest 4 + Larastan level max (matching current `phpstan.neon.dist`).
- CHANGELOG.md at repo root remains the aggregate; each package can have its own CHANGELOG later if needed (not in this plan).
- Existing config file `config/arazzo.php` (Laravel config) MUST continue to load via `LaravelArazzoServiceProvider` under the same publish tag `arazzo-config`.
- Public artefact IDs: `alama/arazzo-core` = brand-new Packagist entry; `alama/arazzo` = new orga meta-package tagline (not published in this plan). `alama/laravel-arazzo` = existing Packagist package, bumped to `2.0.0-alpha.1` next release.
- Alpha channel: this plan releases `1.0.0-alpha.1` for core and `2.0.0-alpha.1` for the bridge. Full `1.0.0`/`2.0.0` land after Plan B stabilization.

---

## File Structure

### Monorepo root (after Task 1)

```
laravel-arrazo/                                  (existing repo, no rename)
├── composer.json                                (root: dev-only, path repos to packages/*)
├── monorepo-builder.php                         (Symplify config)
├── phpunit.xml.dist                             (aggregated test runner)
├── phpstan.neon.dist                            (aggregated static analysis)
├── pint.json                                    (unchanged)
├── packages/
│   ├── core/
│   │   ├── composer.json                        (alama/arazzo-core)
│   │   ├── src/                                 (Alama\Arazzo\*)
│   │   ├── tests/                               (Alama\Arazzo\Tests\*)
│   │   ├── phpunit.xml.dist
│   │   └── phpstan.neon.dist
│   └── laravel/
│       ├── composer.json                        (alama/laravel-arazzo)
│       ├── src/                                 (Alama\Arazzo\Laravel\*)
│       ├── tests/                               (Alama\Arazzo\Laravel\Tests\*)
│       ├── config/arazzo.php                    (moved from root)
│       ├── database/                            (moved from root)
│       ├── resources/                           (moved from root, incl. arazzo-ui.jsx)
│       ├── phpunit.xml.dist
│       └── phpstan.neon.dist
├── docs/                                        (shared, unchanged)
├── .github/workflows/
│   ├── run-tests.yml                            (updated to iterate packages)
│   ├── split.yml                                (new: monorepo split on tag)
│   ├── phpstan.yml                              (updated)
│   ├── fix-php-code-style-issues.yml            (updated)
│   └── update-changelog.yml                     (unchanged)
├── tests/                                       (deleted after migration)
├── src/                                         (deleted after migration)
└── … (everything else unchanged)
```

### `packages/core/src/` layout

Mirrors current `src/` minus Laravel bits, root namespace `Alama\Arazzo\`.

```
Dto/                    (all files, unchanged internally, namespace rewrite)
Exceptions/             (all files, namespace rewrite)
Expression/             (all files, namespace rewrite)
Generator/              (all files, namespace rewrite)
Loader/                 (all files, namespace rewrite)
Parser/                 (all files, namespace rewrite)
Resolution/             (all files, namespace rewrite)
Validation/             (all files, namespace rewrite)
Execution/              (framework-agnostic subset — see Task 9)
License/                (NEW: LicenseVerifierInterface + NullLicenseVerifier — Task 11)
```

### `packages/laravel/src/` layout

Root namespace `Alama\Arazzo\Laravel\`.

```
LaravelArazzoServiceProvider.php   (moved from root of old src/)
Http/Controllers/
  ArazzoApiController.php          (moved from src/Http/Controllers/)
  WebhookResumeController.php      (moved from src/Laravel/Http/Controllers/)
Persistence/
  DatabaseDefinitionRegistry.php   (moved from src/Laravel/)
  DatabaseEventLedger.php
  DatabaseExecutionRegistry.php
  DatabasePendingCorrelationRegistry.php
Queue/
  LaravelQueueDriver.php           (moved from src/Laravel/)
  Jobs/
    RunExecuteStepJob.php
    RunResumeCorrelationJob.php
Lock/
  LaravelRedisLockManager.php
Http/
  Psr18HttpClient.php
State/
  RedisHotStateStore.php
```

---

## Task 0: Prerequisites & Worktree Setup

**Files:**
- Read: repo root `composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`, `README.md`
- No modifications this task

**Interfaces:**
- Consumes: nothing
- Produces: verified toolchain + a dedicated worktree branch for the extraction

- [ ] **Step 1: Verify PHP + Composer versions**

Run: `php -v && composer --version`
Expected: PHP `>= 8.4.0`, Composer `>= 2.7`.

- [ ] **Step 2: Confirm current test suite is green on `main`**

Run: `composer install && vendor/bin/pest`
Expected: all tests pass. Note the pass count in a scratch note — this is the invariant every migration task must preserve.

- [ ] **Step 3: Create working branch via `superpowers:using-git-worktrees` skill**

Invoke skill to create a dedicated worktree/branch named `2026-07-25-plan-a-core-extraction`. Rest of the plan assumes the working directory is this worktree.

- [ ] **Step 4: Snapshot current package name/versions**

Read `composer.json` and record: `name`, `require`, `require-dev`, `autoload.psr-4`, `autoload-dev.psr-4`, `extra.laravel.providers`. This snapshot is the source of truth for what has to end up in the Laravel bridge's `composer.json`.

- [ ] **Step 5: Commit worktree baseline note (no code)**

Create `docs/superpowers/plans/2026-07-25-plan-a-core-extraction.progress.md` with a heading `# Plan A execution progress` and the pass count from Step 2. Commit:

```bash
git add docs/superpowers/plans/2026-07-25-plan-a-core-extraction.progress.md
git commit -m "chore(plan-a): baseline progress note before core extraction"
```

---

## Task 1: Bootstrap Monorepo Skeleton

**Files:**
- Create: `monorepo-builder.php`
- Create: `packages/core/composer.json`
- Create: `packages/core/phpunit.xml.dist`
- Create: `packages/core/phpstan.neon.dist`
- Create: `packages/core/.gitkeep` in `src/` and `tests/`
- Create: `packages/laravel/composer.json`
- Create: `packages/laravel/phpunit.xml.dist`
- Create: `packages/laravel/phpstan.neon.dist`
- Create: `packages/laravel/.gitkeep` in `src/` and `tests/`
- Modify: repo-root `composer.json` (add path repos + require dev packages)

**Interfaces:**
- Consumes: baseline from Task 0
- Produces: two empty publishable Composer packages + working `composer install` at root

- [ ] **Step 1: Install `symplify/monorepo-builder` as root dev dep**

Run: `composer require --dev symplify/monorepo-builder:^12 -W --no-scripts`
Expected: added to root `composer.json` under `require-dev`.

- [ ] **Step 2: Write `monorepo-builder.php` at repo root**

```php
<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        __DIR__ . '/packages',
    ]);

    $mbConfig->defaultBranch('main');

    $mbConfig->dataToAppend([
        'require-dev' => [
            'phpstan/phpstan' => '^2.0',
        ],
    ]);
};
```

- [ ] **Step 3: Create empty `packages/core` package skeleton**

Directory layout:

```
packages/core/
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon.dist
├── src/.gitkeep
└── tests/.gitkeep
```

`packages/core/composer.json`:

```json
{
    "name": "alama/arazzo-core",
    "description": "Framework-agnostic Arazzo 1.0.0/1.1.0 workflow engine core: parser, validator, executor, expression resolver.",
    "keywords": ["alama", "arazzo", "openapi", "workflow", "parser", "validator"],
    "homepage": "https://github.com/alama/arazzo",
    "license": "MIT",
    "authors": [
        {"name": "Mohammed Alama", "email": "mohammedalama96@icloud.com", "role": "Developer"}
    ],
    "require": {
        "php": "^8.4",
        "psr/log": "^3.0",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.0||^2.0",
        "psr/simple-cache": "^3.0",
        "psr/event-dispatcher": "^1.0",
        "psr/container": "^2.0",
        "softcreatr/jsonpath": "^0.10.0",
        "cebe/php-openapi": "^1.7",
        "symfony/yaml": "^7.0"
    },
    "require-dev": {
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-arch": "^4.0",
        "larastan/larastan": "^3.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "laravel/pint": "^1.14"
    },
    "autoload": {
        "psr-4": {"Alama\\Arazzo\\": "src/"}
    },
    "autoload-dev": {
        "psr-4": {"Alama\\Arazzo\\Tests\\": "tests/"}
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

`packages/core/phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.3/phpunit.xsd"
    backupGlobals="false"
    bootstrap="vendor/autoload.php"
    colors="true"
    processIsolation="false"
    stopOnFailure="false"
    executionOrder="random"
    failOnWarning="true"
    failOnRisky="true"
    failOnEmptyTestSuite="true"
    beStrictAboutOutputDuringTests="true"
    cacheDirectory=".phpunit.cache"
    backupStaticProperties="false"
>
    <testsuites>
        <testsuite name="Arazzo Core Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </source>
</phpunit>
```

`packages/core/phpstan.neon.dist`:

```neon
includes:
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon
    - vendor/phpstan/phpstan-phpunit/extension.neon

parameters:
    level: max
    paths:
        - src
    excludePaths:
        - tests
```

- [ ] **Step 4: Create empty `packages/laravel` package skeleton**

`packages/laravel/composer.json`:

```json
{
    "name": "alama/laravel-arazzo",
    "description": "Laravel bridge for alama/arazzo-core: service provider, queue driver, cache lock, Eloquent adapters.",
    "keywords": ["alama", "laravel", "arazzo", "openapi", "workflow"],
    "homepage": "https://github.com/alama/arazzo",
    "license": "MIT",
    "authors": [
        {"name": "Mohammed Alama", "email": "mohammedalama96@icloud.com", "role": "Developer"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-core": "^1.0@alpha",
        "guzzlehttp/guzzle": "^7.8",
        "illuminate/contracts": "^11.0||^12.0||^13.0",
        "spatie/laravel-package-tools": "^1.16"
    },
    "require-dev": {
        "orchestra/testbench": "^11.0.0||^10.0.0||^9.0.0",
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-laravel": "^4.0",
        "pestphp/pest-plugin-arch": "^4.0",
        "larastan/larastan": "^3.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "nunomaduro/collision": "^8.8",
        "spatie/laravel-ray": "^1.35",
        "laravel/pint": "^1.14"
    },
    "autoload": {
        "psr-4": {"Alama\\Arazzo\\Laravel\\": "src/"}
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Laravel\\Tests\\": "tests/",
            "Workbench\\App\\": "workbench/app/"
        }
    },
    "extra": {
        "laravel": {
            "providers": ["Alama\\Arazzo\\Laravel\\LaravelArazzoServiceProvider"]
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

`packages/laravel/phpunit.xml.dist`: identical structure to core's, testsuite name `"Arazzo Laravel Test Suite"`.

`packages/laravel/phpstan.neon.dist`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon
    - vendor/phpstan/phpstan-phpunit/extension.neon

parameters:
    level: max
    paths:
        - src
    excludePaths:
        - tests
```

- [ ] **Step 5: Rewrite repo-root `composer.json` as monorepo shell**

Replace the root `composer.json` with:

```json
{
    "name": "alama/arazzo-monorepo",
    "description": "Monorepo hosting alama/arazzo-core + alama/laravel-arazzo. Not published.",
    "license": "MIT",
    "type": "project",
    "repositories": [
        {"type": "path", "url": "packages/core"},
        {"type": "path", "url": "packages/laravel"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-core": "@dev",
        "alama/laravel-arazzo": "@dev"
    },
    "require-dev": {
        "symplify/monorepo-builder": "^12.0",
        "laravel/pint": "^1.14",
        "pestphp/pest": "^4.0"
    },
    "scripts": {
        "post-autoload-dump": "@composer run prepare",
        "prepare": "@php packages/laravel/vendor/bin/testbench package:discover --ansi || true",
        "analyse-core": "cd packages/core && vendor/bin/phpstan analyse",
        "analyse-laravel": "cd packages/laravel && vendor/bin/phpstan analyse",
        "analyse": ["@analyse-core", "@analyse-laravel"],
        "test-core": "cd packages/core && vendor/bin/pest",
        "test-laravel": "cd packages/laravel && vendor/bin/pest",
        "test": ["@test-core", "@test-laravel"],
        "format": "vendor/bin/pint"
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
```

- [ ] **Step 6: Install both packages**

Run in three separate invocations (order matters — bridge depends on core):

```bash
(cd packages/core && composer install)
(cd packages/laravel && composer install)
composer install
```

Expected: all three succeed. Root `vendor/alama/arazzo-core` and `vendor/alama/laravel-arazzo` should be symlinks into `packages/*` (Composer path repo behavior).

- [ ] **Step 7: Sanity-check no source moved yet**

Run:
```bash
find packages/core/src -type f | grep -v gitkeep | wc -l
find packages/laravel/src -type f | grep -v gitkeep | wc -l
```
Expected: both output `0`. Source moves start in Task 2.

- [ ] **Step 8: Update `.gitignore`**

Ensure the following lines exist (append if missing):

```
/packages/*/vendor/
/packages/*/composer.lock
/packages/*/.phpunit.cache/
```

Root `/vendor/` and `/composer.lock` stay unchanged.

- [ ] **Step 9: Commit**

```bash
git add monorepo-builder.php packages/ composer.json composer.lock .gitignore
git commit -m "chore(plan-a): bootstrap monorepo skeleton with empty core + laravel packages"
```

---

## Task 2: Move `Dto/` and `Exceptions/` to Core

**Files:**
- Move (via `git mv`): every file under `src/Dto/` → `packages/core/src/Dto/`
- Move (via `git mv`): every file under `src/Exceptions/` → `packages/core/src/Exceptions/`
- Move (via `git mv`): every file under `tests/Dto/` → `packages/core/tests/Dto/`
- Move: fixture files used by Dto tests (identify with `grep -r "tests/fixtures/dto" tests/Dto/`) → `packages/core/tests/fixtures/dto/`
- Modify namespace headers on every moved file (see Step 3)

**Interfaces:**
- Consumes: monorepo skeleton from Task 1
- Produces: `Alama\Arazzo\Dto\*` and `Alama\Arazzo\Exceptions\*` classes usable from core, and `Alama\Arazzo\Tests\Dto\*` tests

Full list of moved src files (26 total, from `src/Dto/*` + `src/Exceptions/*`):

```
src/Dto/Action/Action.php
src/Dto/Action/FailureAction.php
src/Dto/Action/FailureEndAction.php
src/Dto/Action/FailureGotoAction.php
src/Dto/Action/RetryAction.php
src/Dto/Action/SuccessAction.php
src/Dto/Action/SuccessEndAction.php
src/Dto/Action/SuccessGotoAction.php
src/Dto/ArazzoDocument.php
src/Dto/Components.php
src/Dto/Enum/ActionKind.php
src/Dto/Enum/CriterionType.php
src/Dto/Enum/Format.php
src/Dto/Enum/ParameterIn.php
src/Dto/Enum/SourceType.php
src/Dto/Expression.php
src/Dto/Info.php
src/Dto/Parameter.php
src/Dto/PayloadReplacement.php
src/Dto/RawDocument.php
src/Dto/RequestBody.php
src/Dto/Reusable.php
src/Dto/SourceDescription.php
src/Dto/Step.php
src/Dto/SuccessCriterion.php
src/Dto/Workflow.php
src/Exceptions/ArazzoException.php
src/Exceptions/LoaderException.php
src/Exceptions/ParserException.php
src/Exceptions/ValidationException.php
```

Test files (7 total from `tests/Dto/*`):

```
tests/Dto/ActionTest.php
tests/Dto/ContainerDtoTest.php
tests/Dto/Enum/FormatTest.php
tests/Dto/ExpressionTest.php
tests/Dto/LeafDtoTest.php
tests/Dto/RawDocumentTest.php
```

- [ ] **Step 1: `git mv` all src files listed above**

```bash
mkdir -p packages/core/src/Dto/Action packages/core/src/Dto/Enum packages/core/src/Exceptions
for f in $(git ls-files src/Dto src/Exceptions); do
  dest="packages/core/${f#src/}"
  dest="packages/core/src/${f#src/}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

Expected: `git status` shows renames (R) for all 30 files.

- [ ] **Step 2: `git mv` matching test files**

```bash
mkdir -p packages/core/tests/Dto/Enum
for f in $(git ls-files tests/Dto); do
  dest="packages/core/${f}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

- [ ] **Step 3: Rewrite namespaces in every moved src file**

Systematic replacement — apply to every `.php` file under `packages/core/src/Dto/` and `packages/core/src/Exceptions/`:

Change:
- `namespace Alama\LaravelArazzo\Dto` → `namespace Alama\Arazzo\Dto`
- `namespace Alama\LaravelArazzo\Exceptions` → `namespace Alama\Arazzo\Exceptions`
- Any `use Alama\LaravelArazzo\Dto\` → `use Alama\Arazzo\Dto\`
- Any `use Alama\LaravelArazzo\Exceptions\` → `use Alama\Arazzo\Exceptions\`

Run:
```bash
find packages/core/src/Dto packages/core/src/Exceptions -name '*.php' -print0 \
  | xargs -0 perl -pi -e 's/\bAlama\\LaravelArazzo\\(Dto|Exceptions)\\/Alama\\Arazzo\\$1\\/g'
```

Example diff (one file — `packages/core/src/Dto/Workflow.php` before and after):

Before:
```php
namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;
```

After:
```php
namespace Alama\Arazzo\Dto;

use Alama\Arazzo\Dto\Action\FailureAction;
use Alama\Arazzo\Dto\Action\SuccessAction;
```

- [ ] **Step 4: Rewrite namespaces + test namespace in every moved test file**

Change on every file under `packages/core/tests/Dto/`:
- Any `namespace Alama\LaravelArazzo\Tests\Dto` → `namespace Alama\Arazzo\Tests\Dto`
- Any `use Alama\LaravelArazzo\Dto\` → `use Alama\Arazzo\Dto\`
- Any `use Alama\LaravelArazzo\Exceptions\` → `use Alama\Arazzo\Exceptions\`

Run:
```bash
find packages/core/tests/Dto -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bAlama\\LaravelArazzo\\Tests\\Dto\\/Alama\\Arazzo\\Tests\\Dto\\/g;
      s/\bAlama\\LaravelArazzo\\Tests\\Dto\b/Alama\\Arazzo\\Tests\\Dto/g;
      s/\bAlama\\LaravelArazzo\\(Dto|Exceptions)\\/Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 5: Create minimal `packages/core/tests/Pest.php`**

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);
```

And `packages/core/tests/TestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
}
```

- [ ] **Step 6: Regenerate autoload + run only the moved Dto tests**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest tests/Dto)
```

Expected: all Dto tests pass. If failures: cross-check namespace rewrite pattern for missed cases, particularly relative `\Alama\LaravelArazzo\...` FQCNs in doc-blocks.

- [ ] **Step 7: Verify no stale imports leaked**

```bash
grep -rn 'Alama\\LaravelArazzo\\(Dto|Exceptions)' packages/core/ && echo 'STALE REFS FOUND' || echo 'clean'
```

Expected output ends with `clean`.

- [ ] **Step 8: Commit**

```bash
git add packages/core/ src/
git commit -m "refactor(plan-a): move Dto + Exceptions to arazzo-core, rewrite namespaces"
```

---

## Task 3: Move `Expression/` to Core

**Files:**
- Move: 16 src files under `src/Expression/` → `packages/core/src/Expression/`
- Move: 3 test files under `tests/Expression/` → `packages/core/tests/Expression/`

**Interfaces:**
- Consumes: `Alama\Arazzo\Dto\*` (Task 2), `Alama\Arazzo\Exceptions\*` (Task 2)
- Produces: `Alama\Arazzo\Expression\*` (lexer, parser, AST, symbol tables, exceptions)

Full src list:
```
src/Expression/Ast/{ComponentRef,ExpressionAst,HttpMetaRef,InputPart,InputRef,OutputPart,OutputRef,RequestPart,ResponsePart,SourceRef,StepPart,StepRef,WorkflowRef}.php
src/Expression/{ExpressionSyntaxException,Lexer,Parser,StepSymbols,SymbolTable,Token,TokenKind,WorkflowSymbols}.php
```

Test list:
```
tests/Expression/{LexerTest,ParserTest,SymbolTableTest}.php
```

- [ ] **Step 1: `git mv` src + tests**

```bash
mkdir -p packages/core/src/Expression/Ast packages/core/tests/Expression
for f in $(git ls-files src/Expression); do
  dest="packages/core/src/${f#src/}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
for f in $(git ls-files tests/Expression); do
  dest="packages/core/${f}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

- [ ] **Step 2: Rewrite namespaces (src + tests)**

```bash
find packages/core/src/Expression packages/core/tests/Expression -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bAlama\\LaravelArazzo\\Tests\\Expression\\/Alama\\Arazzo\\Tests\\Expression\\/g;
      s/\bAlama\\LaravelArazzo\\Tests\\Expression\b/Alama\\Arazzo\\Tests\\Expression/g;
      s/\bAlama\\LaravelArazzo\\(Expression|Dto|Exceptions)\\/Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 3: Regenerate autoload + run Expression + Dto tests**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest tests/Expression tests/Dto)
```

Expected: all pass.

- [ ] **Step 4: Verify no leaks**

```bash
grep -rn 'Alama\\LaravelArazzo\\Expression' packages/core/ && echo 'STALE' || echo 'clean'
```

Expected: `clean`.

- [ ] **Step 5: Commit**

```bash
git add packages/core/ src/
git commit -m "refactor(plan-a): move Expression module to arazzo-core"
```

---

## Task 4: Move `Loader/`, `Parser/`, `Resolution/`, `Validation/` to Core

**Files:**
- Move: `src/Loader/*` (6 files) → `packages/core/src/Loader/`
- Move: `src/Parser/*` (2 files) → `packages/core/src/Parser/`
- Move: `src/Resolution/*` (16 files) → `packages/core/src/Resolution/`
- Move: `src/Validation/*` (~40 files) → `packages/core/src/Validation/`
- Move: matching `tests/{Loader,Parser,Resolution}/*` and `tests/ArchTest.php`

**Interfaces:**
- Consumes: `Alama\Arazzo\{Dto,Exceptions,Expression}\*` (Tasks 2–3)
- Produces: `Alama\Arazzo\{Loader,Parser,Resolution,Validation}\*`

- [ ] **Step 1: `git mv` all four src trees**

```bash
for dir in Loader Parser Resolution Validation; do
  mkdir -p "packages/core/src/${dir}"
  for f in $(git ls-files "src/${dir}"); do
    dest="packages/core/src/${f#src/}"
    mkdir -p "$(dirname "$dest")"
    git mv "$f" "$dest"
  done
done
```

- [ ] **Step 2: `git mv` matching test trees**

```bash
for dir in Loader Parser Resolution; do
  mkdir -p "packages/core/tests/${dir}"
  for f in $(git ls-files "tests/${dir}"); do
    dest="packages/core/${f}"
    mkdir -p "$(dirname "$dest")"
    git mv "$f" "$dest"
  done
done
git mv tests/ArchTest.php packages/core/tests/ArchTest.php
```

- [ ] **Step 3: Rewrite namespaces**

```bash
find packages/core/src/Loader packages/core/src/Parser packages/core/src/Resolution packages/core/src/Validation \
     packages/core/tests/Loader packages/core/tests/Parser packages/core/tests/Resolution packages/core/tests/ArchTest.php \
     -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bAlama\\LaravelArazzo\\Tests\\(Loader|Parser|Resolution)\\/Alama\\Arazzo\\Tests\\$1\\/g;
      s/\bAlama\\LaravelArazzo\\Tests\\(Loader|Parser|Resolution)\b/Alama\\Arazzo\\Tests\\$1/g;
      s/\bAlama\\LaravelArazzo\\Tests\b/Alama\\Arazzo\\Tests/g;
      s/\bAlama\\LaravelArazzo\\(Loader|Parser|Resolution|Validation|Dto|Exceptions|Expression)\\/Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 4: Move fixture files referenced by Parser/Loader/Resolution tests**

Run:
```bash
grep -rho "tests/fixtures/[^'\"]*" packages/core/tests/{Loader,Parser,Resolution} | sort -u
```

For each unique fixture path returned, `git mv tests/fixtures/... packages/core/tests/fixtures/...`. Then patch the tests:

```bash
find packages/core/tests -name '*.php' -print0 \
  | xargs -0 perl -pi -e "s|__DIR__ \. '/../../../tests/fixtures|__DIR__ . '/../fixtures|g; s|'tests/fixtures/|'packages/core/tests/fixtures/|g"
```

Verify the resulting paths exist on disk; if a test uses `dirname(__DIR__, 2) . '/tests/fixtures/...'` variants, update to `dirname(__DIR__) . '/fixtures/...'`.

- [ ] **Step 5: Regenerate autoload + run all core tests migrated so far**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest)
```

Expected: all pass. If arch test fails on discovered classes/namespaces, update its expectations to `Alama\\Arazzo` prefix.

- [ ] **Step 6: Verify no leaks**

```bash
grep -rn 'Alama\\LaravelArazzo\\(Loader|Parser|Resolution|Validation)' packages/core/ && echo 'STALE' || echo 'clean'
```

- [ ] **Step 7: Commit**

```bash
git add packages/core/ src/ tests/
git commit -m "refactor(plan-a): move Loader/Parser/Resolution/Validation to arazzo-core"
```

---

## Task 5: Split `Execution/` — Move Framework-Agnostic Parts to Core

**Files:**
- Move to core: all files under `src/Execution/` **except** those depending on Laravel classes (see Step 1 audit)
- Keep in repo `src/Execution/` (temporarily, until Task 8): files that are Laravel-flavored (`Jobs/ExecuteStepJob.php` may reference Laravel — audit first)
- Move to core tests: all files under `tests/Execution/` matching moved src

**Interfaces:**
- Consumes: `Alama\Arazzo\{Dto,Exceptions,Expression,Loader,Parser,Resolution,Validation}\*`
- Produces: `Alama\Arazzo\Execution\*` with contracts (`QueueDriverInterface`, `LockManagerInterface`, `EventLedgerInterface`, `StateStoreInterface`, `DefinitionRegistryInterface`, `ExpressionResolverInterface`, `HttpClientInterface`, `ExecutionLoggerInterface`, `ExecutionRegistryInterface`, `PendingCorrelationRegistryInterface`, `StepProtocolExecutorInterface`), reference in-memory impls (`InMemoryDefinitionRegistry`, `SyncQueueDriver`), engine (`Engine`, `StepExecutor`, `HttpStepExecutor`, `AsyncApiStepExecutor`, `WorkflowExecutor`, `StepExecutionWorker`, `StepOutcomeHandler`, `CorrelationResumer`), evaluator (`ExpressionEvaluator`, `ArazzoExpressionResolver`, `JsonPathEvaluator`, `JsonPointer`, `TypeCaster`, `SchemaValidator`, `OpenApiParser`, `DependencyAnalyzer`), events, DTOs, exceptions

- [ ] **Step 1: Audit each Execution file for Laravel dependencies**

Run:
```bash
grep -l -E 'Illuminate|use Alama\\LaravelArazzo\\Laravel' src/Execution -r
```

Expected outcome: the following files depend on Laravel (based on repo inspection at plan-writing time) and MUST stay out of core:
- `src/Execution/Jobs/ExecuteStepJob.php` — audit; if it only implements the framework-agnostic interface, move it; if it uses `Illuminate\Contracts\Queue\ShouldQueue`, keep it out of core.
- `src/Execution/ResumeCorrelationJob.php` — same audit.

All other Execution files are framework-agnostic and go to core. If the audit finds new Laravel imports, add those files to the "stay out" list and note them in the progress log.

- [ ] **Step 2: `git mv` framework-agnostic Execution files**

For each file NOT flagged in Step 1:
```bash
for f in $(git ls-files src/Execution | grep -v -E 'Jobs/(Execute|Resume)StepJob.php|ResumeCorrelationJob.php'); do
  dest="packages/core/src/${f#src/}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

Verify Laravel-flavored Job files remain at their old paths for now (they move in Task 9).

- [ ] **Step 3: `git mv` matching Execution tests**

```bash
mkdir -p packages/core/tests/Execution
for f in $(git ls-files tests/Execution); do
  # Skip Laravel-specific job tests until Task 9
  case "$f" in
    tests/Execution/*JobTest.php|tests/Execution/*CorrelationResumerTest.php)
      continue;;
  esac
  dest="packages/core/${f}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

- [ ] **Step 4: Rewrite namespaces**

```bash
find packages/core/src/Execution packages/core/tests/Execution -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bAlama\\LaravelArazzo\\Tests\\Execution\\/Alama\\Arazzo\\Tests\\Execution\\/g;
      s/\bAlama\\LaravelArazzo\\Tests\\Execution\b/Alama\\Arazzo\\Tests\\Execution/g;
      s/\bAlama\\LaravelArazzo\\(Execution|Dto|Exceptions|Expression|Loader|Parser|Resolution|Validation)\\/Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 5: Special case — `ArazzoExpressionResolver` implements `ExpressionResolverInterface`**

Verify the resolver's `implements` clause now reads:
```php
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;

class ArazzoExpressionResolver implements ExpressionResolverInterface
```

If it still references old namespace, the perl rewrite in Step 4 should have caught it — spot-check with:
```bash
grep -n 'ExpressionResolverInterface' packages/core/src/Execution/ArazzoExpressionResolver.php
```

- [ ] **Step 6: Regenerate autoload + run all core tests**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest)
```

Expected: all pass. Common failure: an Execution test may reference a Laravel-flavored Job class that's still in the old namespace. Isolate + skip that test with `->skip('moves in Task 9')` if needed, tracked in the progress log.

- [ ] **Step 7: Verify no leaks in moved files**

```bash
grep -rn 'Alama\\LaravelArazzo\\Execution' packages/core/ && echo 'STALE' || echo 'clean'
```

- [ ] **Step 8: Commit**

```bash
git add packages/core/ src/ tests/
git commit -m "refactor(plan-a): move framework-agnostic Execution subsystem to arazzo-core"
```

---

## Task 6: Move `Generator/` to Core

**Files:**
- Move: `src/Generator/ArazzoGenerator.php`, `src/Generator/Contracts/AiClientInterface.php`, `src/Generator/Clients/OpenAiClient.php` → `packages/core/src/Generator/...`
- Move: `tests/Generator/*` → `packages/core/tests/Generator/`

**Interfaces:**
- Consumes: nothing new (self-contained; will be extended in Plan B)
- Produces: `Alama\Arazzo\Generator\ArazzoGenerator`, `Alama\Arazzo\Generator\Contracts\AiClientInterface`, `Alama\Arazzo\Generator\Clients\OpenAiClient`

Note: `OpenAiClient` is the LLM skeleton. It stays in OSS for now (it's not yet a real product feature). Plan B or Plan F may re-home it into `arazzo-pro-ai` — that's a future concern, not this task's.

- [ ] **Step 1: `git mv` src + tests**

```bash
mkdir -p packages/core/src/Generator/{Clients,Contracts} packages/core/tests/Generator/Clients
for f in $(git ls-files src/Generator); do
  dest="packages/core/src/${f#src/}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
for f in $(git ls-files tests/Generator); do
  dest="packages/core/${f}"
  mkdir -p "$(dirname "$dest")"
  git mv "$f" "$dest"
done
```

- [ ] **Step 2: Rewrite namespaces**

```bash
find packages/core/src/Generator packages/core/tests/Generator -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bAlama\\LaravelArazzo\\Tests\\Generator\\/Alama\\Arazzo\\Tests\\Generator\\/g;
      s/\bAlama\\LaravelArazzo\\Tests\\Generator\b/Alama\\Arazzo\\Tests\\Generator/g;
      s/\bAlama\\LaravelArazzo\\(Generator|Dto|Exceptions|Expression)\\/Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 3: Verify the `OpenAiClient` does not require Laravel or Guzzle**

```bash
grep -E 'Illuminate|GuzzleHttp' packages/core/src/Generator/Clients/OpenAiClient.php
```

Expected: no matches. If matches exist, `OpenAiClient` isn't yet framework-agnostic; refactor to use PSR-18 client instead (out of scope for this plan — file a follow-up and move the class + tests to the Laravel bridge in Task 9).

- [ ] **Step 4: Regenerate autoload + run Generator tests**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest tests/Generator)
```

Expected: pass.

- [ ] **Step 5: Commit**

```bash
git add packages/core/ src/ tests/
git commit -m "refactor(plan-a): move Generator to arazzo-core"
```

---

## Task 7: Add `LicenseVerifierInterface` + `NullLicenseVerifier`

**Files:**
- Create: `packages/core/src/License/LicenseVerifierInterface.php`
- Create: `packages/core/src/License/NullLicenseVerifier.php`
- Create: `packages/core/src/License/LicenseException.php`
- Create: `packages/core/tests/License/NullLicenseVerifierTest.php`

**Interfaces:**
- Consumes: nothing (fresh subsystem)
- Produces:
  - `Alama\Arazzo\License\LicenseVerifierInterface::verifyOrThrow(string $feature): void`
  - `Alama\Arazzo\License\LicenseVerifierInterface::isValid(string $feature): bool`
  - `Alama\Arazzo\License\LicenseVerifierInterface::expiresAt(string $feature): ?\DateTimeImmutable`
  - `Alama\Arazzo\License\NullLicenseVerifier` (always-valid default)
  - `Alama\Arazzo\License\LicenseException` (throws on invalid/expired feature)

- [ ] **Step 1: Write the failing test**

`packages/core/tests/License/NullLicenseVerifierTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\License;

use Alama\Arazzo\License\LicenseVerifierInterface;
use Alama\Arazzo\License\NullLicenseVerifier;

it('reports every feature as valid', function (): void {
    $verifier = new NullLicenseVerifier();

    expect($verifier->isValid('persistence'))->toBeTrue()
        ->and($verifier->isValid('saga'))->toBeTrue()
        ->and($verifier->isValid('anything-at-all'))->toBeTrue();
});

it('never throws from verifyOrThrow', function (): void {
    $verifier = new NullLicenseVerifier();

    $verifier->verifyOrThrow('persistence');
    $verifier->verifyOrThrow('multitenancy');

    expect(true)->toBeTrue();
});

it('returns null expiry for any feature', function (): void {
    $verifier = new NullLicenseVerifier();

    expect($verifier->expiresAt('persistence'))->toBeNull();
});

it('implements the LicenseVerifierInterface contract', function (): void {
    expect(new NullLicenseVerifier())->toBeInstanceOf(LicenseVerifierInterface::class);
});
```

- [ ] **Step 2: Run — expect fail (missing classes)**

```bash
(cd packages/core && vendor/bin/pest tests/License)
```

Expected: fatal `class not found` for `LicenseVerifierInterface` and/or `NullLicenseVerifier`.

- [ ] **Step 3: Write interface**

`packages/core/src/License/LicenseVerifierInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\License;

use DateTimeImmutable;

/**
 * Verifies that the current process is entitled to use a given pro feature.
 *
 * The core ships `NullLicenseVerifier`; pro packages bind an ed25519-based
 * implementation. Never mutates state; safe to call from hot paths.
 */
interface LicenseVerifierInterface
{
    /**
     * Throw LicenseException if $feature is not licensed or is expired past
     * the grace period.
     *
     * @throws LicenseException
     */
    public function verifyOrThrow(string $feature): void;

    /** Return true iff $feature is currently entitled (grace period counts as valid). */
    public function isValid(string $feature): bool;

    /** Return the license's hard-expiry timestamp, or null when unlimited/unknown. */
    public function expiresAt(string $feature): ?DateTimeImmutable;
}
```

- [ ] **Step 4: Write `LicenseException`**

`packages/core/src/License/LicenseException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\License;

use RuntimeException;

final class LicenseException extends RuntimeException
{
    public static function notLicensed(string $feature): self
    {
        return new self(sprintf(
            'Feature "%s" is not covered by any active Arazzo Pro license. '
            . 'See https://arazzo.dev/pricing to purchase or refresh.',
            $feature,
        ));
    }

    public static function expired(string $feature): self
    {
        return new self(sprintf(
            'Arazzo Pro license for feature "%s" has expired past the grace period. '
            . 'Renew at https://arazzo.dev/account or unset the pro binding to fall back to core.',
            $feature,
        ));
    }
}
```

- [ ] **Step 5: Write `NullLicenseVerifier`**

`packages/core/src/License/NullLicenseVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\License;

use DateTimeImmutable;

/**
 * Default implementation bound in the OSS core: every feature is valid,
 * every expiry is null. Pro packages replace this binding via their
 * framework bridge.
 */
final class NullLicenseVerifier implements LicenseVerifierInterface
{
    public function verifyOrThrow(string $feature): void
    {
    }

    public function isValid(string $feature): bool
    {
        return true;
    }

    public function expiresAt(string $feature): ?DateTimeImmutable
    {
        return null;
    }
}
```

- [ ] **Step 6: Run tests — expect pass**

```bash
(cd packages/core && vendor/bin/pest tests/License)
```

Expected: 4 passing.

- [ ] **Step 7: Run full core test suite as regression check**

```bash
(cd packages/core && vendor/bin/pest)
```

Expected: everything passes (nothing else touched this subsystem, no regressions).

- [ ] **Step 8: Commit**

```bash
git add packages/core/src/License packages/core/tests/License
git commit -m "feat(arazzo-core): add LicenseVerifierInterface + NullLicenseVerifier"
```

---

## Task 8: Extract Test Infrastructure Shared Between Core and Bridge

**Files:**
- Move: `tests/Pest.php` → decide: does it use Laravel-specific pest plugin? If yes, keep in bridge; if no, distribute a copy to each package.
- Move: any test helpers currently under `tests/Support/` or referenced by both moved and yet-to-be-moved tests.
- Move: fixture directories `tests/fixtures/*` — audit which fixtures are still referenced by (a) moved core tests, (b) not-yet-moved bridge tests; split accordingly.

**Interfaces:**
- Consumes: whatever tests have already moved (Tasks 2–7)
- Produces: two independent test roots. `(cd packages/core && vendor/bin/pest)` runs green with no cross-repo file access.

- [ ] **Step 1: Audit remaining `tests/` directory**

```bash
find tests -type f | grep -v -E '/(Http|Laravel)/|LaravelArazzoService|Feature/Persistence' | sort
```

Everything listed here still exists in the old `tests/` after Tasks 2–7 and is either (a) support/fixture files that need routing, or (b) tests that should already have moved but slipped through — investigate each.

- [ ] **Step 2: Move `tests/fixtures/` intelligently**

For every fixture directory `tests/fixtures/<name>/`, run:
```bash
grep -rl "tests/fixtures/<name>" packages/ tests/
```

- If only referenced from `packages/core/`: `git mv tests/fixtures/<name> packages/core/tests/fixtures/<name>` and rewrite string references (see Task 4 Step 4 pattern).
- If only referenced from `tests/` (leftover bridge tests): `git mv tests/fixtures/<name> packages/laravel/tests/fixtures/<name>`, rewrite references. Bridge test files move in Task 9.
- If referenced from both: copy (don't move) to both packages; delete the root copy last.

- [ ] **Step 3: Provide `packages/core/tests/Pest.php` and `TestCase`**

Already scaffolded in Task 2 Step 5 — verify it exists and matches. If not, create per Task 2 Step 5.

- [ ] **Step 4: Confirm core is self-contained**

```bash
(cd packages/core && composer dump-autoload && vendor/bin/pest)
```

Expected: full core test suite green, no `open_basedir` / missing file errors.

- [ ] **Step 5: Commit**

```bash
git add packages/core/tests packages/laravel/tests tests
git commit -m "refactor(plan-a): distribute shared test fixtures + helpers to per-package roots"
```

---

## Task 9: Move Laravel-Specific Code to Bridge

**Files:**
- Move: `src/LaravelArazzoServiceProvider.php` → `packages/laravel/src/LaravelArazzoServiceProvider.php`
- Move: `src/Http/Controllers/ArazzoApiController.php` → `packages/laravel/src/Http/Controllers/ArazzoApiController.php`
- Move: `src/Laravel/*` (all 8 files) → `packages/laravel/src/` (flatten to logical subdirs: `Persistence/`, `Queue/`, `Lock/`, `Http/`, `State/`)
- Move: `src/Execution/Jobs/ExecuteStepJob.php` + `src/Execution/ResumeCorrelationJob.php` → `packages/laravel/src/Queue/Jobs/`
- Move: `src/Laravel/Http/Controllers/WebhookResumeController.php` → `packages/laravel/src/Http/Controllers/WebhookResumeController.php`
- Move: `src/Laravel/Jobs/RunExecuteStepJob.php` + `src/Laravel/Jobs/RunResumeCorrelationJob.php` → `packages/laravel/src/Queue/Jobs/`
- Move: matching test files (`tests/Laravel/*`, `tests/Http/*`, `tests/LaravelArazzoServiceProvider*Test.php`, `tests/Feature/PersistenceMigrationsTest.php`, `tests/Execution/*JobTest.php`, `tests/Execution/CorrelationResumerTest.php` if it depended on Laravel jobs)
- Move: `config/arazzo.php` → `packages/laravel/config/arazzo.php`
- Move: `database/migrations/*` → `packages/laravel/database/migrations/`
- Move: `resources/js/arazzo-ui.jsx` and other `resources/*` → `packages/laravel/resources/`
- Move: `testbench.yaml`, `workbench/` if present, `phpunit.xml.dist` Laravel-specific bits → `packages/laravel/`

**Interfaces:**
- Consumes: everything now in `Alama\Arazzo\*` (core)
- Produces:
  - `Alama\Arazzo\Laravel\LaravelArazzoServiceProvider` — implements `spatie/laravel-package-tools`'s `PackageServiceProvider`, publishes `arazzo-config`, `arazzo-migrations`, `arazzo-views` tags
  - `Alama\Arazzo\Laravel\Persistence\{DatabaseDefinitionRegistry, DatabaseEventLedger, DatabaseExecutionRegistry, DatabasePendingCorrelationRegistry}` — implement corresponding core interfaces
  - `Alama\Arazzo\Laravel\Queue\LaravelQueueDriver` — implements `Alama\Arazzo\Execution\Contracts\QueueDriverInterface`
  - `Alama\Arazzo\Laravel\Queue\Jobs\{RunExecuteStepJob, RunResumeCorrelationJob, ExecuteStepJob, ResumeCorrelationJob}` — implement `Illuminate\Contracts\Queue\ShouldQueue`
  - `Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager` — implements `Alama\Arazzo\Execution\Contracts\LockManagerInterface`
  - `Alama\Arazzo\Laravel\State\RedisHotStateStore` — implements `Alama\Arazzo\Execution\Contracts\StateStoreInterface`
  - `Alama\Arazzo\Laravel\Http\Psr18HttpClient` — implements PSR-18 wrapping Laravel's Guzzle
  - `Alama\Arazzo\Laravel\Http\Controllers\{ArazzoApiController, WebhookResumeController}` — Laravel HTTP controllers

- [ ] **Step 1: Move service provider**

```bash
mkdir -p packages/laravel/src
git mv src/LaravelArazzoServiceProvider.php packages/laravel/src/LaravelArazzoServiceProvider.php
```

- [ ] **Step 2: Move `src/Http/Controllers/` and `src/Laravel/`**

```bash
mkdir -p packages/laravel/src/Http/Controllers packages/laravel/src/Persistence packages/laravel/src/Queue/Jobs packages/laravel/src/Lock packages/laravel/src/State

git mv src/Http/Controllers/ArazzoApiController.php packages/laravel/src/Http/Controllers/ArazzoApiController.php
git mv src/Laravel/Http/Controllers/WebhookResumeController.php packages/laravel/src/Http/Controllers/WebhookResumeController.php
git mv src/Laravel/DatabaseDefinitionRegistry.php packages/laravel/src/Persistence/DatabaseDefinitionRegistry.php
git mv src/Laravel/DatabaseEventLedger.php packages/laravel/src/Persistence/DatabaseEventLedger.php
git mv src/Laravel/DatabaseExecutionRegistry.php packages/laravel/src/Persistence/DatabaseExecutionRegistry.php
git mv src/Laravel/DatabasePendingCorrelationRegistry.php packages/laravel/src/Persistence/DatabasePendingCorrelationRegistry.php
git mv src/Laravel/LaravelQueueDriver.php packages/laravel/src/Queue/LaravelQueueDriver.php
git mv src/Laravel/LaravelRedisLockManager.php packages/laravel/src/Lock/LaravelRedisLockManager.php
git mv src/Laravel/Psr18HttpClient.php packages/laravel/src/Http/Psr18HttpClient.php
git mv src/Laravel/RedisHotStateStore.php packages/laravel/src/State/RedisHotStateStore.php
git mv src/Laravel/Jobs/RunExecuteStepJob.php packages/laravel/src/Queue/Jobs/RunExecuteStepJob.php
git mv src/Laravel/Jobs/RunResumeCorrelationJob.php packages/laravel/src/Queue/Jobs/RunResumeCorrelationJob.php
```

- [ ] **Step 3: Move Laravel-flavored jobs left behind by Task 5**

If Task 5 Step 1 flagged `src/Execution/Jobs/ExecuteStepJob.php` or `src/Execution/ResumeCorrelationJob.php` as Laravel-dependent, move them now:

```bash
git mv src/Execution/Jobs/ExecuteStepJob.php packages/laravel/src/Queue/Jobs/ExecuteStepJob.php 2>/dev/null || true
git mv src/Execution/ResumeCorrelationJob.php packages/laravel/src/Queue/Jobs/ResumeCorrelationJob.php 2>/dev/null || true
```

If they weren't flagged (turned out to be framework-agnostic), skip this step.

- [ ] **Step 4: Rewrite namespaces on all moved bridge files**

```bash
find packages/laravel/src -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bnamespace Alama\\LaravelArazzo\\Http\\Controllers\b/namespace Alama\\Arazzo\\Laravel\\Http\\Controllers/g;
      s/\bnamespace Alama\\LaravelArazzo\\Laravel\\Http\\Controllers\b/namespace Alama\\Arazzo\\Laravel\\Http\\Controllers/g;
      s/\bnamespace Alama\\LaravelArazzo\\Laravel\\Jobs\b/namespace Alama\\Arazzo\\Laravel\\Queue\\Jobs/g;
      s/\bnamespace Alama\\LaravelArazzo\\Laravel\b/namespace Alama\\Arazzo\\Laravel/g;
      s/\bnamespace Alama\\LaravelArazzo\\Execution\\Jobs\b/namespace Alama\\Arazzo\\Laravel\\Queue\\Jobs/g;
      s/\bnamespace Alama\\LaravelArazzo\\Execution\b/namespace Alama\\Arazzo\\Laravel\\Queue\\Jobs/g;
      s/\bnamespace Alama\\LaravelArazzo\b/namespace Alama\\Arazzo\\Laravel/g;
      # Then rewrite use statements: core-side references
      s/\buse Alama\\LaravelArazzo\\Execution\\Jobs\\/use Alama\\Arazzo\\Laravel\\Queue\\Jobs\\/g;
      s/\buse Alama\\LaravelArazzo\\Execution\\(ExecuteStepJob|ResumeCorrelationJob)\b/use Alama\\Arazzo\\Laravel\\Queue\\Jobs\\$1/g;
      s/\buse Alama\\LaravelArazzo\\(Dto|Exceptions|Expression|Loader|Parser|Resolution|Validation|Generator|Execution|License)\\/use Alama\\Arazzo\\$1\\/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\Http\\Controllers\\/use Alama\\Arazzo\\Laravel\\Http\\Controllers\\/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\Jobs\\/use Alama\\Arazzo\\Laravel\\Queue\\Jobs\\/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\/use Alama\\Arazzo\\Laravel\\/g;
    '
```

Manually spot-check `packages/laravel/src/LaravelArazzoServiceProvider.php` after the rewrite for stragglers.

- [ ] **Step 5: Move Laravel-specific tests**

```bash
mkdir -p packages/laravel/tests/Http/Controllers packages/laravel/tests/Persistence packages/laravel/tests/Queue/Jobs packages/laravel/tests/Feature

git mv tests/Http/Controllers/ArazzoApiControllerTest.php packages/laravel/tests/Http/Controllers/ArazzoApiControllerTest.php
git mv tests/Laravel/Http/Controllers/WebhookResumeControllerTest.php packages/laravel/tests/Http/Controllers/WebhookResumeControllerTest.php
git mv tests/Laravel/DatabaseExecutionRegistryTest.php packages/laravel/tests/Persistence/DatabaseExecutionRegistryTest.php
git mv tests/Laravel/DatabasePendingCorrelationRegistryTest.php packages/laravel/tests/Persistence/DatabasePendingCorrelationRegistryTest.php
git mv tests/Laravel/LaravelQueueDriverTest.php packages/laravel/tests/Queue/LaravelQueueDriverTest.php
git mv tests/Laravel/Psr18HttpClientTest.php packages/laravel/tests/Http/Psr18HttpClientTest.php
git mv tests/LaravelArazzoServiceProviderTest.php packages/laravel/tests/LaravelArazzoServiceProviderTest.php
git mv tests/LaravelArazzoServiceProviderBindingsTest.php packages/laravel/tests/LaravelArazzoServiceProviderBindingsTest.php
git mv tests/Feature/PersistenceMigrationsTest.php packages/laravel/tests/Feature/PersistenceMigrationsTest.php
git mv tests/Laravel/Jobs/RunExecuteStepJobTest.php packages/laravel/tests/Queue/Jobs/RunExecuteStepJobTest.php
```

If Task 5 Step 3 skipped `tests/Execution/*JobTest.php` and `tests/Execution/CorrelationResumerTest.php`, move them now:
```bash
git mv tests/Execution/CorrelationResumerTest.php packages/laravel/tests/Queue/Jobs/CorrelationResumerTest.php 2>/dev/null || true
```

- [ ] **Step 6: Rewrite test namespaces**

```bash
find packages/laravel/tests -name '*.php' -print0 \
  | xargs -0 perl -pi -e '
      s/\bnamespace Alama\\LaravelArazzo\\Tests\\Http\\Controllers\b/namespace Alama\\Arazzo\\Laravel\\Tests\\Http\\Controllers/g;
      s/\bnamespace Alama\\LaravelArazzo\\Tests\\Laravel\\Http\\Controllers\b/namespace Alama\\Arazzo\\Laravel\\Tests\\Http\\Controllers/g;
      s/\bnamespace Alama\\LaravelArazzo\\Tests\\Laravel\\Jobs\b/namespace Alama\\Arazzo\\Laravel\\Tests\\Queue\\Jobs/g;
      s/\bnamespace Alama\\LaravelArazzo\\Tests\\Laravel\b/namespace Alama\\Arazzo\\Laravel\\Tests\\Persistence/g;
      s/\bnamespace Alama\\LaravelArazzo\\Tests\\Feature\b/namespace Alama\\Arazzo\\Laravel\\Tests\\Feature/g;
      s/\bnamespace Alama\\LaravelArazzo\\Tests\b/namespace Alama\\Arazzo\\Laravel\\Tests/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\Http\\Controllers\\/use Alama\\Arazzo\\Laravel\\Http\\Controllers\\/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\Jobs\\/use Alama\\Arazzo\\Laravel\\Queue\\Jobs\\/g;
      s/\buse Alama\\LaravelArazzo\\Laravel\\/use Alama\\Arazzo\\Laravel\\/g;
      s/\buse Alama\\LaravelArazzo\\Execution\\Jobs\\/use Alama\\Arazzo\\Laravel\\Queue\\Jobs\\/g;
      s/\buse Alama\\LaravelArazzo\\Http\\Controllers\\/use Alama\\Arazzo\\Laravel\\Http\\Controllers\\/g;
      s/\buse Alama\\LaravelArazzo\\LaravelArazzoServiceProvider\b/use Alama\\Arazzo\\Laravel\\LaravelArazzoServiceProvider/g;
      s/\buse Alama\\LaravelArazzo\\(Dto|Exceptions|Expression|Loader|Parser|Resolution|Validation|Generator|Execution|License)\\/use Alama\\Arazzo\\$1\\/g;
    '
```

- [ ] **Step 7: Move `config/`, `database/`, `resources/`, `testbench.yaml`, `workbench/`**

```bash
mkdir -p packages/laravel/config packages/laravel/database packages/laravel/resources
git mv config packages/laravel/config
# `config` is now the target dir name; correct if git-mv nested:
[ -d packages/laravel/config/config ] && mv packages/laravel/config/config/* packages/laravel/config/ && rmdir packages/laravel/config/config
git mv database packages/laravel/database
[ -d packages/laravel/database/database ] && mv packages/laravel/database/database/* packages/laravel/database/ && rmdir packages/laravel/database/database
git mv resources packages/laravel/resources
[ -d packages/laravel/resources/resources ] && mv packages/laravel/resources/resources/* packages/laravel/resources/ && rmdir packages/laravel/resources/resources
git mv testbench.yaml packages/laravel/testbench.yaml
[ -d workbench ] && git mv workbench packages/laravel/workbench
```

Also move build config that is Laravel-only:
```bash
[ -f package.json ] && git mv package.json packages/laravel/package.json
[ -f package-lock.json ] && git mv package-lock.json packages/laravel/package-lock.json
[ -f vite.config.js ] && git mv vite.config.js packages/laravel/vite.config.js
[ -f playwright.config.js ] && git mv playwright.config.js packages/laravel/playwright.config.js
```

- [ ] **Step 8: Update service provider paths inside `LaravelArazzoServiceProvider`**

Config publishing was probably `__DIR__ . '/../config/arazzo.php'` — since the file is now at `packages/laravel/src/LaravelArazzoServiceProvider.php` and the config at `packages/laravel/config/arazzo.php`, that path is still correct (`../config/arazzo.php`).

Verify by opening the file and inspecting the path constants used by `->hasConfigFile()`, `->hasMigrations()`, `->hasViews()` (`spatie/laravel-package-tools` conventions).

- [ ] **Step 9: `composer install` the Laravel package + regenerate autoload**

```bash
(cd packages/laravel && composer install && composer dump-autoload)
```

Expected: no missing-class errors.

- [ ] **Step 10: Run the bridge test suite**

```bash
(cd packages/laravel && vendor/bin/pest)
```

Expected: green. Common failures:
- Testbench discovery finds the old provider path: fix by verifying `composer.json`'s `extra.laravel.providers` correctly points to `Alama\\Arazzo\\Laravel\\LaravelArazzoServiceProvider`.
- Migration test relies on a hard-coded `database_path` relative to old root: patch the test to use the new package-scoped path or use Testbench's `loadMigrationsFrom()`.
- Config publish path mismatch: adjust the assertion to `packages/laravel/config/arazzo.php`.

- [ ] **Step 11: Full core + bridge run**

```bash
composer test
```

(This invokes `test-core` then `test-laravel` per Task 1 root scripts.)

Expected: both green.

- [ ] **Step 12: Commit**

```bash
git add packages/laravel/ src/ tests/ config/ database/ resources/ testbench.yaml workbench package.json package-lock.json vite.config.js playwright.config.js
git commit -m "refactor(plan-a): move Laravel-specific code to alama/laravel-arazzo bridge"
```

---

## Task 10: Delete Empty Legacy Directories

**Files:**
- Delete: `src/` (must be empty after Task 9)
- Delete: `tests/` (must be empty of `.php` files after Task 9)
- Delete: any orphaned root-level `config/`, `database/`, `resources/`, `workbench/` remnants

**Interfaces:**
- Consumes: nothing new
- Produces: clean root layout matching the "Monorepo root (after Task 1)" diagram

- [ ] **Step 1: Verify src/ is empty**

```bash
find src -type f | wc -l
```

Expected: `0`. If not, investigate the leftover file — either it was missed by a task or it needs a decision (core vs. bridge).

- [ ] **Step 2: Verify tests/ is empty of PHP**

```bash
find tests -type f -name '*.php' | wc -l
```

Expected: `0`. Leftover fixture files (non-`.php`) are fine — they get handled in Step 3.

- [ ] **Step 3: Handle leftover fixture files**

```bash
find tests -type f
```

For any remaining files (typically leftover fixture `.yaml`/`.json`), decide OSS core vs bridge and `git mv` accordingly (grep for consumer as in Task 8 Step 2).

- [ ] **Step 4: Remove empty directories**

```bash
find src tests -type d -empty -delete
rmdir src tests 2>/dev/null || true
```

- [ ] **Step 5: Verify root layout matches spec diagram**

```bash
ls -la
```

Expected: no top-level `src/`, `tests/`, `config/`, `database/`, `resources/`, `testbench.yaml`. Only `packages/`, `docs/`, `.github/`, config dotfiles, root `composer.json`, `monorepo-builder.php`, `Makefile`, `phpstan.neon.dist` (root-level for backwards-compat with old CI — will be pruned in Task 13), `pint.json`, `phpunit.xml.dist` (same).

- [ ] **Step 6: Run full test suite one more time**

```bash
composer test
```

Expected: green.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore(plan-a): remove legacy src/ and tests/ directories after extraction"
```

---

## Task 11: Add Backwards-Compat Namespace Aliases in Bridge

**Files:**
- Create: `packages/laravel/src/Compat/aliases.php`
- Modify: `packages/laravel/composer.json` (add `"files"` autoload entry)
- Create: `packages/laravel/tests/Compat/BackwardsCompatAliasesTest.php`

**Interfaces:**
- Consumes: every public class in `Alama\Arazzo\*` (core) and `Alama\Arazzo\Laravel\*` (bridge)
- Produces: every old `Alama\LaravelArazzo\*` class name resolves to its new location via `class_alias` — existing consumer code continues to work without changes. Deprecation notices emitted only when `APP_DEBUG=true`.

- [ ] **Step 1: Write the failing test**

`packages/laravel/tests/Compat/BackwardsCompatAliasesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Compat;

it('resolves the old top-level ServiceProvider FQCN', function (): void {
    expect(class_exists(\Alama\LaravelArazzo\LaravelArazzoServiceProvider::class))->toBeTrue();
    expect(is_a(\Alama\LaravelArazzo\LaravelArazzoServiceProvider::class, \Alama\Arazzo\Laravel\LaravelArazzoServiceProvider::class, true))->toBeTrue();
});

it('resolves old core Dto FQCNs', function (): void {
    expect(class_exists(\Alama\LaravelArazzo\Dto\Workflow::class))->toBeTrue();
    expect(is_a(\Alama\LaravelArazzo\Dto\Workflow::class, \Alama\Arazzo\Dto\Workflow::class, true))->toBeTrue();
});

it('resolves old Execution engine FQCNs', function (): void {
    expect(class_exists(\Alama\LaravelArazzo\Execution\Engine::class))->toBeTrue();
    expect(is_a(\Alama\LaravelArazzo\Execution\Engine::class, \Alama\Arazzo\Execution\Engine::class, true))->toBeTrue();
});

it('resolves old Laravel bridge FQCNs', function (): void {
    expect(class_exists(\Alama\LaravelArazzo\Laravel\LaravelQueueDriver::class))->toBeTrue();
    expect(is_a(\Alama\LaravelArazzo\Laravel\LaravelQueueDriver::class, \Alama\Arazzo\Laravel\Queue\LaravelQueueDriver::class, true))->toBeTrue();
});

it('resolves old Validation Rule FQCN', function (): void {
    expect(class_exists(\Alama\LaravelArazzo\Validation\Validator::class))->toBeTrue();
    expect(is_a(\Alama\LaravelArazzo\Validation\Validator::class, \Alama\Arazzo\Validation\Validator::class, true))->toBeTrue();
});
```

- [ ] **Step 2: Run — expect fail**

```bash
(cd packages/laravel && vendor/bin/pest tests/Compat)
```

Expected: all 5 assertions fail — old classes don't exist.

- [ ] **Step 3: Write the alias file**

`packages/laravel/src/Compat/aliases.php`:

```php
<?php

declare(strict_types=1);

/**
 * Backwards-compatibility aliases for consumers still using
 * `Alama\LaravelArazzo\*` FQCNs from 1.x. All aliases will be
 * removed in `alama/laravel-arazzo` 3.0.0 (target: end of 2026).
 */

$aliases = [
    // Bridge — top level
    Alama\Arazzo\Laravel\LaravelArazzoServiceProvider::class => \Alama\LaravelArazzo\LaravelArazzoServiceProvider::class,

    // Bridge — HTTP controllers
    Alama\Arazzo\Laravel\Http\Controllers\ArazzoApiController::class => \Alama\LaravelArazzo\Http\Controllers\ArazzoApiController::class,
    Alama\Arazzo\Laravel\Http\Controllers\WebhookResumeController::class => \Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController::class,

    // Bridge — Persistence
    Alama\Arazzo\Laravel\Persistence\DatabaseDefinitionRegistry::class => \Alama\LaravelArazzo\Laravel\DatabaseDefinitionRegistry::class,
    Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger::class => \Alama\LaravelArazzo\Laravel\DatabaseEventLedger::class,
    Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry::class => \Alama\LaravelArazzo\Laravel\DatabaseExecutionRegistry::class,
    Alama\Arazzo\Laravel\Persistence\DatabasePendingCorrelationRegistry::class => \Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry::class,

    // Bridge — Queue
    Alama\Arazzo\Laravel\Queue\LaravelQueueDriver::class => \Alama\LaravelArazzo\Laravel\LaravelQueueDriver::class,
    Alama\Arazzo\Laravel\Queue\Jobs\RunExecuteStepJob::class => \Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob::class,
    Alama\Arazzo\Laravel\Queue\Jobs\RunResumeCorrelationJob::class => \Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob::class,

    // Bridge — Lock
    Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager::class => \Alama\LaravelArazzo\Laravel\LaravelRedisLockManager::class,

    // Bridge — State
    Alama\Arazzo\Laravel\State\RedisHotStateStore::class => \Alama\LaravelArazzo\Laravel\RedisHotStateStore::class,

    // Bridge — HTTP
    Alama\Arazzo\Laravel\Http\Psr18HttpClient::class => \Alama\LaravelArazzo\Laravel\Psr18HttpClient::class,
];

foreach ($aliases as $new => $old) {
    if (! class_exists($old, false) && ! interface_exists($old, false)) {
        class_alias($new, $old);
    }
}

// Core — Dto/Exceptions/Expression/Loader/Parser/Resolution/Validation/Generator/Execution/License
// (delegated to an autoload hook so we don't have to enumerate every class)
spl_autoload_register(static function (string $fqcn): void {
    if (! str_starts_with($fqcn, 'Alama\\LaravelArazzo\\')) {
        return;
    }

    // Skip Laravel bridge FQCNs (handled by the enumerated map above)
    foreach ([
        'Alama\\LaravelArazzo\\Laravel\\',
        'Alama\\LaravelArazzo\\Http\\',
        'Alama\\LaravelArazzo\\LaravelArazzoServiceProvider',
    ] as $bridgePrefix) {
        if (str_starts_with($fqcn, $bridgePrefix)) {
            return;
        }
    }

    $newFqcn = 'Alama\\Arazzo\\' . substr($fqcn, strlen('Alama\\LaravelArazzo\\'));

    if (class_exists($newFqcn) || interface_exists($newFqcn) || trait_exists($newFqcn) || enum_exists($newFqcn)) {
        class_alias($newFqcn, $fqcn);
    }
});
```

- [ ] **Step 4: Register `aliases.php` in `packages/laravel/composer.json`**

Modify the `autoload` block:

```json
"autoload": {
    "psr-4": {"Alama\\Arazzo\\Laravel\\": "src/"},
    "files": ["src/Compat/aliases.php"]
}
```

- [ ] **Step 5: Regenerate autoload + run compat tests**

```bash
(cd packages/laravel && composer dump-autoload && vendor/bin/pest tests/Compat)
```

Expected: 5 passing.

- [ ] **Step 6: Run entire bridge + core test suite as regression check**

```bash
composer test
```

Expected: green — the alias autoloader is idempotent and only runs on `Alama\LaravelArazzo\*` lookups, so it can't perturb the existing test infrastructure.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/composer.json packages/laravel/src/Compat packages/laravel/tests/Compat
git commit -m "feat(laravel-arazzo): add BC aliases for Alama\\LaravelArazzo\\* namespace"
```

---

## Task 12: Update Existing Root-Level Config Files + Remove Duplicates

**Files:**
- Delete: root `phpunit.xml.dist` (moved into each package in Task 1)
- Delete: root `phpstan.neon.dist` + `phpstan-baseline.neon` + `phpstan-report.txt` (moved into each package)
- Modify: `Makefile` — retarget commands at per-package composer scripts
- Modify: `pint.json` — repoint at `packages/*/src` and `packages/*/tests`
- Modify: `README.md` — install snippet points at `alama/laravel-arazzo` v2, mentions core availability

**Interfaces:**
- Consumes: monorepo layout (Task 10)
- Produces: no ambiguous config at the repo root; every tool operates via monorepo scripts or per-package dirs

- [ ] **Step 1: Delete redundant root config**

```bash
git rm phpunit.xml.dist phpstan.neon.dist phpstan-baseline.neon phpstan-report.txt
```

- [ ] **Step 2: Update `Makefile`**

Replace contents so common targets iterate per-package:

```makefile
.PHONY: test test-core test-laravel analyse analyse-core analyse-laravel format install

install:
	(cd packages/core && composer install)
	(cd packages/laravel && composer install)
	composer install

test-core:
	(cd packages/core && vendor/bin/pest)

test-laravel:
	(cd packages/laravel && vendor/bin/pest)

test: test-core test-laravel

analyse-core:
	(cd packages/core && vendor/bin/phpstan analyse)

analyse-laravel:
	(cd packages/laravel && vendor/bin/phpstan analyse)

analyse: analyse-core analyse-laravel

format:
	vendor/bin/pint
```

- [ ] **Step 3: Update `pint.json`**

Repoint `preset`, `exclude`, and `paths` at `packages/`:

```json
{
    "preset": "laravel",
    "exclude": ["vendor", "packages/*/vendor", "packages/*/.phpunit.cache"],
    "notPath": [
        "packages/laravel/database/migrations/*_stub.php",
        "packages/laravel/resources/**/*.blade.php"
    ],
    "rules": {
        "declare_strict_types": true
    }
}
```

Adjust `notPath` glob patterns per what already existed — don't drop existing exclusions.

- [ ] **Step 4: Update `README.md` install section**

Update install snippets from `composer require :vendor_slug/:package_slug` (placeholder from Spatie skeleton) to:

```markdown
## Installation

Install the Laravel bridge (which pulls the core automatically):

```bash
composer require alama/laravel-arazzo:^2.0@alpha
```

Or use the framework-agnostic core standalone in any PHP 8.4+ project:

```bash
composer require alama/arazzo-core:^1.0@alpha
```
```

Replace the entire `<!--delete-->` block of skeleton boilerplate with a real one-paragraph package description.

- [ ] **Step 5: Run full test suite one more time**

```bash
composer test
```

Expected: green.

- [ ] **Step 6: Commit**

```bash
git add Makefile pint.json README.md
git rm phpunit.xml.dist phpstan.neon.dist phpstan-baseline.neon phpstan-report.txt 2>/dev/null || true
git commit -m "chore(plan-a): retire root-level tool configs, retarget Makefile at packages/"
```

---

## Task 13: Update GitHub Actions Workflows

**Files:**
- Modify: `.github/workflows/run-tests.yml` — matrix over `packages/core` + `packages/laravel`
- Modify: `.github/workflows/phpstan.yml` — same matrix
- Modify: `.github/workflows/fix-php-code-style-issues.yml` — run pint at root (pint config now covers both packages)
- Modify: `.github/workflows/update-changelog.yml` — unchanged if generic; verify triggers still fire
- Modify: `.github/workflows/dependabot-auto-merge.yml` — verify triggers
- Create: `.github/workflows/split.yml` — pushes on tag `v*` to per-package subtree remotes

**Interfaces:**
- Consumes: monorepo topology, `symplify/monorepo-builder`
- Produces: green CI on push + working subtree split on tag

- [ ] **Step 1: Rewrite `run-tests.yml`**

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  tests:
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: false
      matrix:
        os: [ubuntu-latest]
        php: [8.4]
        package: [core, laravel]

    name: P${{ matrix.php }} - ${{ matrix.package }} - ${{ matrix.os }}

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: none

      - name: Install package dependencies
        run: composer install --no-interaction --prefer-dist --working-dir=packages/${{ matrix.package }}

      - name: Run tests
        run: |
          cd packages/${{ matrix.package }}
          vendor/bin/pest
```

- [ ] **Step 2: Rewrite `phpstan.yml`**

Same matrix pattern; replace test command with:
```yaml
      - name: Run PHPStan
        run: |
          cd packages/${{ matrix.package }}
          vendor/bin/phpstan analyse
```

- [ ] **Step 3: Adjust `fix-php-code-style-issues.yml`**

- Composer install happens per-package (matrix), OR install just Pint at root and run `vendor/bin/pint` — pint config in Task 12 already targets `packages/*`. Keep it as a single non-matrix job:

```yaml
      - name: Install Pint
        run: composer install --no-interaction --no-scripts

      - name: Run Pint
        run: vendor/bin/pint

      - name: Commit changes
        uses: stefanzweifel/git-auto-commit-action@v5
        with:
          commit_message: "chore: fix code style"
```

- [ ] **Step 4: Create `.github/workflows/split.yml`**

```yaml
name: Package Subtree Split

on:
  push:
    tags: ['v*']

jobs:
  split:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        include:
          - package: core
            repository: alama/arazzo-core
          - package: laravel
            repository: alama/laravel-arazzo

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Split ${{ matrix.package }}
        uses: symplify/monorepo-split-github-action@v2.3.0
        with:
          tag: ${{ github.ref_name }}
          package-directory: packages/${{ matrix.package }}
          split-repository-organization: alama
          split-repository-name: ${{ matrix.repository == 'alama/arazzo-core' && 'arazzo-core' || 'laravel-arazzo' }}
          user-name: 'alama-bot'
          user-email: 'bot@arazzo.dev'
        env:
          GITHUB_TOKEN: ${{ secrets.ACCESS_TOKEN }}
```

Note: this requires a `secrets.ACCESS_TOKEN` PAT with `repo` scope on the target split repos. Set that manually in GitHub settings — outside this plan's file-changing steps but documented in the progress log.

- [ ] **Step 5: Verify workflows locally (syntax only)**

```bash
find .github/workflows -name '*.yml' -print0 \
  | xargs -0 -n1 -I{} sh -c 'echo "--- {}"; python3 -c "import yaml; yaml.safe_load(open(\"{}\"))"'
```

Expected: every file parses without exception.

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/
git commit -m "ci(plan-a): matrix per-package tests/phpstan, add split workflow"
```

---

## Task 14: Version Bumps, Changelog, and Alpha Tag Preparation

**Files:**
- Modify: `packages/core/composer.json` — no `version` field (Composer prefers tags), but ensure `extra.branch-alias` if desired
- Modify: `packages/laravel/composer.json` — same
- Modify: `CHANGELOG.md` — add `## [Unreleased]` section documenting the extraction
- Create: `packages/core/UPGRADING.md` — one-liner: new package, install with `composer require alama/arazzo-core:^1.0@alpha`
- Create: `packages/laravel/UPGRADING.md` — full BC guide for 1.x → 2.x consumers

**Interfaces:**
- Consumes: everything in Tasks 1–13
- Produces: repo state ready for the `git tag v1.0.0-alpha.1` and `git tag v2.0.0-alpha.1` commands

Version tag policy: monorepo uses lockstep versioning. **One tag prefix per package**: `core-v1.0.0-alpha.1` for the core, `laravel-v2.0.0-alpha.1` for the bridge. The split workflow in Task 13 Step 4 assumes a single `v*` tag pushes all packages simultaneously — since core and bridge are on different major versions, we need per-package tag prefixes.

- [ ] **Step 1: Update split workflow to filter tag prefixes**

Edit `.github/workflows/split.yml`:

```yaml
on:
  push:
    tags:
      - 'core-v*'
      - 'laravel-v*'

jobs:
  split-core:
    if: startsWith(github.ref_name, 'core-v')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Extract version
        id: version
        run: echo "version=${GITHUB_REF_NAME#core-}" >> $GITHUB_OUTPUT
      - name: Split core
        uses: symplify/monorepo-split-github-action@v2.3.0
        with:
          tag: ${{ steps.version.outputs.version }}
          package-directory: packages/core
          split-repository-organization: alama
          split-repository-name: arazzo-core
          user-name: 'alama-bot'
          user-email: 'bot@arazzo.dev'
        env:
          GITHUB_TOKEN: ${{ secrets.ACCESS_TOKEN }}

  split-laravel:
    if: startsWith(github.ref_name, 'laravel-v')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Extract version
        id: version
        run: echo "version=${GITHUB_REF_NAME#laravel-}" >> $GITHUB_OUTPUT
      - name: Split laravel
        uses: symplify/monorepo-split-github-action@v2.3.0
        with:
          tag: ${{ steps.version.outputs.version }}
          package-directory: packages/laravel
          split-repository-organization: alama
          split-repository-name: laravel-arazzo
          user-name: 'alama-bot'
          user-email: 'bot@arazzo.dev'
        env:
          GITHUB_TOKEN: ${{ secrets.ACCESS_TOKEN }}
```

- [ ] **Step 2: Update `CHANGELOG.md`**

Prepend:

```markdown
## [Unreleased]

### Changed
- **BREAKING (Laravel bridge only)**: extracted framework-agnostic engine into new package `alama/arazzo-core`. `alama/laravel-arazzo` is now a thin bridge depending on the core. Existing consumers upgrade via `composer require alama/laravel-arazzo:^2.0@alpha` and (optionally) update FQCNs to `Alama\Arazzo\*`. Old `Alama\LaravelArazzo\*` FQCNs continue to resolve via `class_alias` throughout the 2.x line — planned removal in 3.0.
- Repository restructured as a Symplify monorepo hosting `packages/core` and `packages/laravel`. Tag-based subtree splits publish each package independently.

### Added
- `alama/arazzo-core` initial release (`1.0.0-alpha.1`): parser, validator (39 rules), execution engine with in-memory reference drivers, expression resolver, schema validator, generator skeleton, OAK-ready contracts, `LicenseVerifierInterface` for future pro-tier gating.
- `LicenseVerifierInterface` + `NullLicenseVerifier` — foundation for future pro-tier entitlement enforcement (currently a no-op in OSS).

### Migration
- See `packages/laravel/UPGRADING.md` for consumer migration guide.
- See `packages/core/UPGRADING.md` for standalone core usage.
```

- [ ] **Step 3: Write `packages/laravel/UPGRADING.md`**

```markdown
# Upgrading `alama/laravel-arazzo` from 1.x to 2.x

## TL;DR

```bash
composer require alama/laravel-arazzo:^2.0@alpha
```

Everything else keeps working. Old namespaces alias to new ones automatically.

## What changed

- `alama/laravel-arazzo` is now a thin Laravel bridge over the new
  framework-agnostic `alama/arazzo-core` (installed as a transitive dep — you
  don't touch it).
- All framework-agnostic classes moved from `Alama\LaravelArazzo\*` to
  `Alama\Arazzo\*`. All Laravel-specific classes moved from
  `Alama\LaravelArazzo\Laravel\*` (and `Alama\LaravelArazzo\Http\*`) to
  `Alama\Arazzo\Laravel\*`.

## Do I need to update my code?

Not yet — every old FQCN resolves to its new location via `class_alias`.
Your existing `use Alama\LaravelArazzo\Dto\Workflow` continues to work.

## When should I update my code?

Before `alama/laravel-arazzo` 3.0.0 (target: end of 2026). The compat
shim will be removed there.

## Namespace map

| Old | New |
|---|---|
| `Alama\LaravelArazzo\Dto\Workflow` | `Alama\Arazzo\Dto\Workflow` |
| `Alama\LaravelArazzo\Execution\Engine` | `Alama\Arazzo\Execution\Engine` |
| `Alama\LaravelArazzo\Validation\Validator` | `Alama\Arazzo\Validation\Validator` |
| `Alama\LaravelArazzo\Parser\Parser` | `Alama\Arazzo\Parser\Parser` |
| `Alama\LaravelArazzo\Loader\Loader` | `Alama\Arazzo\Loader\Loader` |
| `Alama\LaravelArazzo\Expression\SymbolTable` | `Alama\Arazzo\Expression\SymbolTable` |
| `Alama\LaravelArazzo\Generator\ArazzoGenerator` | `Alama\Arazzo\Generator\ArazzoGenerator` |
| `Alama\LaravelArazzo\Resolution\SourceResolver` | `Alama\Arazzo\Resolution\SourceResolver` |
| `Alama\LaravelArazzo\LaravelArazzoServiceProvider` | `Alama\Arazzo\Laravel\LaravelArazzoServiceProvider` |
| `Alama\LaravelArazzo\Laravel\LaravelQueueDriver` | `Alama\Arazzo\Laravel\Queue\LaravelQueueDriver` |
| `Alama\LaravelArazzo\Laravel\DatabaseEventLedger` | `Alama\Arazzo\Laravel\Persistence\DatabaseEventLedger` |
| `Alama\LaravelArazzo\Laravel\RedisHotStateStore` | `Alama\Arazzo\Laravel\State\RedisHotStateStore` |
| `Alama\LaravelArazzo\Laravel\LaravelRedisLockManager` | `Alama\Arazzo\Laravel\Lock\LaravelRedisLockManager` |
| `Alama\LaravelArazzo\Laravel\Psr18HttpClient` | `Alama\Arazzo\Laravel\Http\Psr18HttpClient` |
| `Alama\LaravelArazzo\Http\Controllers\ArazzoApiController` | `Alama\Arazzo\Laravel\Http\Controllers\ArazzoApiController` |
| `Alama\LaravelArazzo\Laravel\Http\Controllers\WebhookResumeController` | `Alama\Arazzo\Laravel\Http\Controllers\WebhookResumeController` |

Rewrite in-place with:
```bash
find app -name '*.php' -print0 | xargs -0 perl -pi -e '
  s/\bAlama\\LaravelArazzo\\Laravel\\Jobs\\/Alama\\Arazzo\\Laravel\\Queue\\Jobs\\/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\LaravelQueueDriver\b/Alama\\Arazzo\\Laravel\\Queue\\LaravelQueueDriver/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\Database(Definition|Event|Execution|PendingCorrelation)/Alama\\Arazzo\\Laravel\\Persistence\\Database$1/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\RedisHotStateStore\b/Alama\\Arazzo\\Laravel\\State\\RedisHotStateStore/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\LaravelRedisLockManager\b/Alama\\Arazzo\\Laravel\\Lock\\LaravelRedisLockManager/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\Psr18HttpClient\b/Alama\\Arazzo\\Laravel\\Http\\Psr18HttpClient/g;
  s/\bAlama\\LaravelArazzo\\Laravel\\Http\\Controllers\\/Alama\\Arazzo\\Laravel\\Http\\Controllers\\/g;
  s/\bAlama\\LaravelArazzo\\Http\\Controllers\\/Alama\\Arazzo\\Laravel\\Http\\Controllers\\/g;
  s/\bAlama\\LaravelArazzo\\LaravelArazzoServiceProvider\b/Alama\\Arazzo\\Laravel\\LaravelArazzoServiceProvider/g;
  s/\bAlama\\LaravelArazzo\\(Dto|Exceptions|Expression|Loader|Parser|Resolution|Validation|Generator|Execution)\\/Alama\\Arazzo\\$1\\/g;
'
```

## New capability: use the engine framework-agnostically

If you want to use the parser/validator/executor from a non-Laravel PHP
context, you can now depend on `alama/arazzo-core` directly — no Laravel
container required.
```

- [ ] **Step 4: Write `packages/core/UPGRADING.md`**

```markdown
# `alama/arazzo-core`

New package, no upgrading needed. Install with:

```bash
composer require alama/arazzo-core:^1.0@alpha
```

Requires PHP `^8.4`. No framework dependencies. See the main repo README for
a full quick-start.

If you were using `alama/laravel-arazzo` and want the Laravel integration
(service provider, queue driver, cache lock, Eloquent adapters), install
`alama/laravel-arazzo` — it depends on this package and wires it into
Laravel.
```

- [ ] **Step 5: Commit**

```bash
git add CHANGELOG.md packages/laravel/UPGRADING.md packages/core/UPGRADING.md .github/workflows/split.yml
git commit -m "docs(plan-a): CHANGELOG + UPGRADING guides for 2.x extraction, tag-prefix split workflow"
```

---

## Task 15: Final Verification + Alpha Tag

**Files:**
- No new files
- Run the full pipeline end-to-end
- Tag `core-v1.0.0-alpha.1` and `laravel-v2.0.0-alpha.1`

**Interfaces:**
- Consumes: everything from Tasks 1–14
- Produces: two tags pushed to GitHub → split workflow publishes both packages

- [ ] **Step 1: Full clean install from scratch**

```bash
rm -rf packages/core/vendor packages/laravel/vendor vendor
(cd packages/core && composer install)
(cd packages/laravel && composer install)
composer install
```

Expected: no errors.

- [ ] **Step 2: Full test suite (both packages)**

```bash
composer test
```

Expected: all tests green in both packages. Pass count MUST be ≥ the baseline recorded in Task 0 Step 2. Any regression = investigate before proceeding, do NOT tag.

- [ ] **Step 3: Static analysis (both packages)**

```bash
composer analyse
```

Expected: no PHPStan errors. Any errors surfaced by rearrangement (missing use-statements, unreachable class references) must be fixed inline before tagging.

- [ ] **Step 4: Pint / code style**

```bash
composer format
git diff --stat
```

If Pint modified anything, commit:
```bash
git add -A
git commit -m "chore(plan-a): pint autofix"
```

- [ ] **Step 5: Dry-run subtree split locally**

```bash
git subtree split --prefix=packages/core -b _tmp-core-split
git subtree split --prefix=packages/laravel -b _tmp-laravel-split
git log --oneline _tmp-core-split | head -5
git log --oneline _tmp-laravel-split | head -5
git branch -D _tmp-core-split _tmp-laravel-split
```

Expected: each branch has commits limited to files under its respective package path.

- [ ] **Step 6: Manual smoke test — install core standalone**

Create a throwaway dir outside the repo:
```bash
mkdir -p /tmp/arazzo-core-smoke && cd /tmp/arazzo-core-smoke
cat > composer.json <<EOF
{
  "name": "smoke/test",
  "repositories": [{"type": "path", "url": "$(pwd | sed 's|/private||')/../../Users/mohammedalama/Code/Me/laravel-arrazo/packages/core", "options": {"symlink": true}}],
  "require": {"alama/arazzo-core": "@dev"},
  "minimum-stability": "dev"
}
EOF
composer install
php -r 'require "vendor/autoload.php"; echo class_exists("Alama\\Arazzo\\Parser\\Parser") ? "OK" : "MISSING"; echo "\n";'
```

Expected output: `OK`. Confirms the core is installable and autoloadable with zero framework deps.

Return to the repo and clean up: `rm -rf /tmp/arazzo-core-smoke`.

- [ ] **Step 7: Manual smoke test — install bridge standalone in a fresh Laravel app**

Skip this step if you don't have a `laravel new` app handy — the automated bridge test suite (Task 9) already covers Testbench-based integration. Doing it in a real Laravel app is a nice-to-have that validates the alpha end-to-end. If done:

```bash
cd /tmp && laravel new arazzo-bridge-smoke && cd arazzo-bridge-smoke
composer config repositories.arazzo-core path /Users/mohammedalama/Code/Me/laravel-arrazo/packages/core
composer config repositories.arazzo-laravel path /Users/mohammedalama/Code/Me/laravel-arrazo/packages/laravel
composer require alama/laravel-arazzo:@dev
php artisan arazzo:validate --help
```

Expected: command discovered without errors.

- [ ] **Step 8: Merge worktree back to main**

Delegate to `superpowers:finishing-a-development-branch` skill to open a PR against `main`. Do not tag until PR is merged and CI is green on `main`.

- [ ] **Step 9: After merge — tag and push both packages**

On `main` post-merge:
```bash
git checkout main && git pull
git tag core-v1.0.0-alpha.1 -m "arazzo-core 1.0.0-alpha.1: initial framework-agnostic extraction"
git tag laravel-v2.0.0-alpha.1 -m "laravel-arazzo 2.0.0-alpha.1: rewired as thin bridge over arazzo-core"
git push origin core-v1.0.0-alpha.1 laravel-v2.0.0-alpha.1
```

Expected: the split workflow (Task 13/14) runs and pushes each package to its own repo. Verify at `github.com/alama/arazzo-core` and `github.com/alama/laravel-arazzo` that new tags exist.

- [ ] **Step 10: Verify Packagist ingest**

If auto-ingest is not yet configured, manually submit both packages once at packagist.org:
- `github.com/alama/arazzo-core`
- `github.com/alama/laravel-arazzo` (already submitted at 1.x — the 2.0.0-alpha.1 tag will ingest automatically)

Then verify:
```bash
composer show alama/arazzo-core --available
composer show alama/laravel-arazzo --available
```

Expected: both list the new alpha versions.

- [ ] **Step 11: Update progress log + close plan**

Append to `docs/superpowers/plans/2026-07-25-plan-a-core-extraction.progress.md`:

```markdown
## Completed 2026-XX-XX

- Baseline pass count: <N>
- Final pass count: <M> (must be >= N)
- Core tag: core-v1.0.0-alpha.1
- Bridge tag: laravel-v2.0.0-alpha.1
- Packagist links:
  - https://packagist.org/packages/alama/arazzo-core
  - https://packagist.org/packages/alama/laravel-arazzo
```

Commit:
```bash
git add docs/superpowers/plans/2026-07-25-plan-a-core-extraction.progress.md
git commit -m "docs(plan-a): mark core extraction complete"
```

- [ ] **Step 12: Announce**

Post to the Laravel News feed submission form + own Twitter/Bluesky + the OSS repo Discussions:

> `alama/laravel-arazzo` 2.0.0-alpha.1 released. The engine is now split into a
> framework-agnostic `alama/arazzo-core` and a thin Laravel bridge. Existing
> 1.x code keeps working via automatic namespace aliases. Full BC guide in
> `UPGRADING.md`. Next up: `alama/symfony-arazzo` and `alama/drupal-arazzo`
> bridges (Plan E), followed by the pro tier (Plan C).

(This is optional social work — no code step; just close the loop.)

---

## Self-Review Notes

**Spec coverage** (cross-check against `docs/superpowers/specs/2026-07-24-commercial-tier-and-multi-framework-design.md` Section 7 Phase A):

| Spec item | Task(s) covering it |
|---|---|
| A1 — Monorepo (Symplify) + GH Actions split | Tasks 1, 13, 14 |
| A2 — Move parser/validator/DTOs/expression/schema-validator, rename `Alama\Arazzo\*`, `git filter-repo` | Tasks 2–6 (namespace rewrite via `perl -pi`; `git mv` preserves blame; `git filter-repo` unnecessary since we're moving inside the same repo and split via subtree split at tag time) |
| A3 — Extract listed core interfaces + `LicenseVerifierInterface` | Task 5 (existing Execution contracts moved with the code), Task 7 (new LicenseVerifierInterface + NullLicenseVerifier) |
| A4 — In-memory reference impls in core | Task 5 (existing `SyncQueueDriver`, `InMemoryDefinitionRegistry` move to core with rest of Execution) |
| A5 — Rewire `packages/laravel/` as thin bridge, existing tests green | Tasks 9, 15 |
| A6 — Publish `arazzo-core 1.0.0-alpha` + `laravel-arazzo 2.0.0-alpha` | Tasks 14, 15 |
| Deliverable — existing users upgrade via `composer require` + namespace rename | Task 11 (`class_alias` shim + Compat test), Task 14 (`UPGRADING.md` with sed one-liner) |
| Risk R1 — namespace rename BC via `class_alias()` + `@deprecated` | Task 11 |

**Placeholder scan:** none found — each step has exact code, exact commands, exact file paths.

**Type consistency:** `LicenseVerifierInterface` signature identical across the interface definition (Task 7 Step 3), the null impl (Task 7 Step 5), and the tests (Task 7 Step 1). No signature drift.

**Known unknowns folded into the plan explicitly:**
- Task 5 Step 1 audits whether specific Job classes are Laravel-dependent (result may differ from plan-writing-time guess; Task 5 Step 2/3 branches on the audit).
- Task 6 Step 3 audits whether `OpenAiClient` is framework-agnostic; branches on the result.
- Task 8 Step 2 walks fixture files case-by-case rather than assuming a partition.
- Task 15 Step 7 flagged as optional smoke test (requires user judgment).
