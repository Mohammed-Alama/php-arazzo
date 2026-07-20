# Laravel Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide a complete Laravel integration for the Arazzo engine by configuring the Service Provider, publishing a config file, and binding the core interfaces (PSR-18 HTTP Client, AI Generator, and Workflow Executor) to the Laravel dependency container.

**Architecture:** 
1. Require `guzzlehttp/guzzle` in `composer.json` to provide concrete PSR-18 and PSR-17 implementations.
2. Publish `config/arazzo.php` for user configurations (e.g., OpenAI API key).
3. Bind the `SourceResolver`, `AiClientInterface`, `ArazzoGenerator`, and `WorkflowExecutor` into the container in `LaravelArazzoServiceProvider`.

**Tech Stack:** PHP 8.2+, Laravel, Spatie Laravel Package Tools, Guzzle

---

### Task 1: Composer Dependencies and Config File

**Files:**
- Modify: `composer.json`
- Create: `config/arazzo.php`

- [ ] **Step 1: Install Guzzle**

```bash
rtk proxy herd php composer require guzzlehttp/guzzle "^7.8"
```
*(No test to run here, just ensuring the dependency is present).*

- [ ] **Step 2: Create the config file**

```php
// config/arazzo.php
<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],
];
```

- [ ] **Step 3: Commit**

```bash
rtk git add composer.json composer.lock config/arazzo.php
rtk git commit -m "chore: add guzzle and arazzo config file"
```

---

### Task 2: Service Provider Bindings

**Files:**
- Modify: `src/LaravelArazzoServiceProvider.php`
- Create: `tests/LaravelArazzoServiceProviderBindingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/LaravelArazzoServiceProviderBindingsTest.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests;

use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('binds psr interfaces to guzzle', function () {
    expect(app(ClientInterface::class))->toBeInstanceOf(\GuzzleHttp\Client::class);
    expect(app(RequestFactoryInterface::class))->toBeInstanceOf(\GuzzleHttp\Psr7\HttpFactory::class);
    expect(app(StreamFactoryInterface::class))->toBeInstanceOf(\GuzzleHttp\Psr7\HttpFactory::class);
});

it('binds AiClientInterface', function () {
    expect(app(AiClientInterface::class))->toBeInstanceOf(\Alama\LaravelArazzo\Generator\Clients\OpenAiClient::class);
});

it('binds ArazzoGenerator', function () {
    expect(app(ArazzoGenerator::class))->toBeInstanceOf(ArazzoGenerator::class);
});

it('binds WorkflowExecutor and StepExecutor', function () {
    expect(app(WorkflowExecutor::class))->toBeInstanceOf(WorkflowExecutor::class);
    expect(app(StepExecutor::class))->toBeInstanceOf(StepExecutor::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `rtk proxy herd php vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: FAIL (Target bindings not resolvable)

- [ ] **Step 3: Write minimal implementation**

Update `src/LaravelArazzoServiceProvider.php`:

```php
// src/LaravelArazzoServiceProvider.php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Generator\Clients\OpenAiClient;
use Alama\LaravelArazzo\Generator\Contracts\AiClientInterface;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile('arazzo');
    }

    public function packageRegistered(): void
    {
        // PSR HTTP Bindings
        $this->app->bindIf(ClientInterface::class, Client::class);
        $this->app->bindIf(RequestFactoryInterface::class, HttpFactory::class);
        $this->app->bindIf(StreamFactoryInterface::class, HttpFactory::class);

        // Core Resolver
        $this->app->singleton(SourceResolver::class, function () {
            return new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher(), 3600),
                    'https' => new CachedFetcher(new HttpFetcher(), 3600),
                    'file' => new LocalFetcher(),
                ],
                parsers: [
                    SourceType::Openapi->value => new OpenApiSourceParser(),
                    SourceType::Arazzo->value => new ArazzoSourceParser(new Parser()),
                ],
            );
        });

        // AI Generator
        $this->app->singleton(AiClientInterface::class, function ($app) {
            return new OpenAiClient(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(StreamFactoryInterface::class),
                config('arazzo.openai.api_key', ''),
                config('arazzo.openai.model', 'gpt-4o')
            );
        });

        $this->app->singleton(ArazzoGenerator::class, function ($app) {
            return new ArazzoGenerator($app->make(AiClientInterface::class));
        });

        // Workflow Execution
        $this->app->singleton(StepExecutor::class, function ($app) {
            return new StepExecutor(
                $app->make(SourceResolver::class),
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                new ExpressionEvaluator()
            );
        });

        $this->app->singleton(WorkflowExecutor::class, function ($app) {
            return new WorkflowExecutor($app->make(StepExecutor::class));
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `rtk proxy herd php vendor/bin/pest tests/LaravelArazzoServiceProviderBindingsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add tests/LaravelArazzoServiceProviderBindingsTest.php src/LaravelArazzoServiceProvider.php
rtk git commit -m "feat: register container bindings for arazzo core services"
```
