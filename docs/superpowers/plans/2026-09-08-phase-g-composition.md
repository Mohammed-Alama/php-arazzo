# Phase G — Composition: Laravel tagging + umbrella (implementation plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the composition layer that auto-registers protocol packages into a composed app. A container-tagged `PluginRegistry` collects every `PluginInterface`-conforming service registered by `alama/arazzo-protocol-*` packages (and anything else tagging `arazzo.plugins.*`), orders them by `PluginInterface::priority()`, and feeds the Phase E `OperationExecutorRegistry` + Phase C evaluator registries + Phase D `SourceNormalizerRegistry` composition roots. Adding a protocol package to a Laravel app becomes `composer require alama/arazzo-protocol-<x>` + nothing else (spec G1; goal line 101-102). The umbrella (`alama/arazzo-core`), Laravel bridge and root monorepo require `arazzo-protocol-http` by default so composed apps observe no change (spec D9, "Public API impact" line 622-623).

**Architecture:** All composition code lives in `packages/laravel/src` (namespace `Alama\Arazzo\Laravel`), following the repo's established `Bindings/*` registrar pattern (`LaravelArazzoServiceProvider::packageRegistered()` calls each `XBindings::register($this->app)`; every registrar is `@internal`). The registry is a concrete Laravel service (`Alama\Arazzo\Laravel\Support\PluginRegistry`) that resolves container-tagged services via `Container::tagged()` / `Container::tag()`, sorts by `PluginInterface::priority()` descending, and exposes typed buckets consumed by the composition roots. **The spec leaves Phase G deliberately thin** (G1 is a single line; G2/G3/G4 are config wiring); this plan grounds the design on the real Laravel package conventions read from `packages/laravel/src` — the `Bindings` registrar convention, the `AsyncGraphSeams` nullable seam pattern (`?OpenApiExecutorInterface $openApiExecutor` in `packages/runner/src/AsyncGraphSeams.php:38`), the `$app->bound(...)` guard in `AsyncGraphResolver::seams()` (`packages/laravel/src/Support/AsyncGraphResolver.php:46-49`), and the fact that the runner currently hard-codes its protocol executor list as `[$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor]` in `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php:127` — the exact list G replaces with registry-fed composition.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), Pest plugins (`pest-plugin-laravel`, `pest-plugin-arch`), Orchestra Testbench (the laravel package's `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`), Larastan (phpstan `level: max` — `packages/laravel/phpstan.neon.dist`), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- New Laravel source files: namespace `Alama\Arazzo\Laravel\<Dir>`, `declare(strict_types=1)`, and marked `@internal stays out of the advertised contract; not part of the public API surface` (exact repo docblock, see every `Bindings/*` registrar).
- Every registrar mirrors the existing `final class XBindings { public static function register(Container $app): void }` shape (see `HttpBindings.php`, `ExecutionBindings.php`). Register each new registrar from `LaravelArazzoServiceProvider::packageRegistered()` in dependency order.
- **Consumes, does not re-implement:** the caller-of-record registries (`OperationExecutorRegistry` from Phase E4, `ExpressionEvaluatorRegistry`/`CriterionEvaluatorRegistry` from Phase C2, `SourceNormalizerRegistry` from Phase D1/A3) are owned by their phases. Phase G only wires *into* them. Where a phase's registry does not exist yet at implementation time, the task below states the assumption explicitly and falls back to the existing `ProtocolExecutorRegistry` (`packages/runner/src/Protocol/ProtocolExecutorRegistry.php`) as the concrete today-bound surface — G must not edit runner internals beyond consuming its registry/interface.
- No code comments unless explaining the priority ordering or the BC rationale for defaulting protocol-http.
- Every task's `--filter` runs: `vendor/bin/pest packages/laravel/tests --filter "<name>"` from the repo root.
- Every task ends with `composer run test-laravel` green (runs `vendor/bin/pest packages/laravel/tests` from the repo root — the monorepo composer scripts are all root-run; `packages/laravel/composer.json` `autoload-dev` maps `Alama\Arazzo\Laravel\Tests\` → `tests/`).
- Static analysis per task: `composer run analyse-laravel` (runs `cd packages/laravel && vendor/bin/phpstan analyse` per root `composer.json` line 97).
- Full gate (final task only): `make verify` (docs regen + `vendor/bin/pint --test` + `composer run analyse` + `composer run test`) — see `Makefile` line 84-88.
- **No commits that touch code/spec outside `packages/laravel`** except the root/umbrella `composer.json` require additions (G5) and `LaravelArazzoServiceProvider.php` (G1). Do **not** edit the protocol packages themselves; Phase F owns their plugin classes and their `register` ship in F1–F4. Do **not** edit runner internals beyond reading its existing registry/interface.
- Adding/editing protocol packages is **out of scope** — later phases own the normalizer/executor *implementations*. G provides the tag vocabulary + registry + seams they register into.

---

### Task G1: PluginRegistry — container-tagged plugin collector

Container-tagged collector over `PluginInterface` (Phase A1). This is the heart of "Laravel auto-registers protocol packages via container tagging and a `PluginRegistry`" (spec goal line 101-102). It resolves every service tagged `arazzo.plugins.*`, validates it implements a `PluginInterface` contract face, sorts by `PluginInterface::priority()` descending, and exposes typed buckets for each composition root. Protocol packages (F1–F4) will tag their plugin services; the registry never hard-codes a protocol class.

**Files:**
- Create: `packages/laravel/src/Support/PluginRegistry.php`
- Create: `packages/laravel/src/Bindings/PluginBindings.php`
- Modify: `packages/laravel/src/LaravelArazzoServiceProvider.php` (register `PluginBindings`)
- Test: `packages/laravel/tests/Support/PluginRegistryTest.php`
- Test: `packages/laravel/tests/Bindings/PluginBindingsTest.php`

**Interfaces:**
- Consumes: `PluginInterface::name(): string`, `::priority(): int` (Phase A1, `Alama\Arazzo\Contracts\Interfaces\PluginInterface`); `Container` (`Illuminate\Contracts\Container\Container`) for `tag`/`tagged`.
- Produces: `PluginRegistry::register(string $tag, ?string $abstract = null): void`; `::plugins(): list<PluginInterface>` (priority-desc sorted); `::typed(string $class): list<T>` filtered by `instanceof` (where `T extends PluginInterface`); `::priority(string $name): int`; `::has(string $name): bool`.

> **Grounding note:** Phase A1 defines `PluginInterface { name(): string; priority(): int }` (confirmed in `packages/contracts/src/Interfaces/` — the file does not exist yet, so the registry is written against that Phase A signature; it is forward-compatible with the existing `StepProtocolExecutorInterface` face which shares `supports`/`execute` shape). The registry itself only needs the two `PluginInterface` methods, so it is decoupled from which subclass faces (executors, normalizers, evaluators, resolvers) ultimately land.

- [ ] **Step 1: Write the failing test**

Create `packages/laravel/tests/Support/PluginRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Alama\Arazzo\Laravel\Support\PluginRegistry;

final class LowPlugin implements PluginInterface
{
    public function name(): string { return 'low'; }

    public function priority(): int { return 10; }
}

final class HighPlugin implements PluginInterface
{
    public function name(): string { return 'high'; }

    public function priority(): int { return 90; }
}

it('collects tagged plugins and orders them by priority descending', function (): void {
    $app = app();

    $app->tag(LowPlugin::class, 'arazzo.plugins.*');
    $app->tag(HighPlugin::class, 'arazzo.plugins.*');

    $registry = new PluginRegistry($app);

    $plugins = $registry->plugins();

    expect(array_map(fn (PluginInterface $p) => $p->name(), $plugins))
        ->toBe(['high', 'low']);
});

it('filters plugins by a concrete contract face', function (): void {
    $app = app();

    $app->tag(LowPlugin::class, 'arazzo.plugins.operation-executor');

    $registry = new PluginRegistry($app);

    expect($registry->typed(LowPlugin::class))->toHaveCount(1);
});

it('reports priority and presence by name', function (): void {
    $app = app();

    $app->tag(LowPlugin::class, 'arazzo.plugins.*');

    $registry = new PluginRegistry($app);

    expect($registry->priority('low'))->toBe(10)
        ->and($registry->has('low'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/laravel/tests --filter PluginRegistryTest` (repo root)

Expected: FAIL with "Class PluginRegistry not found".

- [ ] **Step 3: Write minimal implementation**

Create `packages/laravel/src/Support/PluginRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Container-tagged plugin collector.
 *
 * Resolves every service tagged arazzo.plugins.*, sorts by
 * PluginInterface::priority() descending, and exposes typed buckets
 * for the composition roots (OperationExecutorRegistry, evaluator
 * registries, SourceNormalizerRegistry).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class PluginRegistry
{
    /**
     * @var array<string, list<PluginInterface>>
     */
    private array $byTag = [];

    public function __construct(private readonly Container $container) {}

    public function register(string $tag, ?string $abstract = null): void
    {
        $this->byTag[$tag] ??= [];

        foreach ($this->container->tagged($tag) as $service) {
            if (!$service instanceof PluginInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Tagged service [%s] must implement %s.',
                    is_object($service) ? $service::class : (string) $service,
                    PluginInterface::class,
                ));
            }

            $this->byTag[$tag][] = $service;
        }
    }

    /**
     * @return list<PluginInterface>
     */
    public function plugins(): array
    {
        $all = [];

        foreach ($this->byTag as $tagged) {
            foreach ($tagged as $plugin) {
                $all[] = $plugin;
            }
        }

        usort($all, static fn (PluginInterface $a, PluginInterface $b): int => $b->priority() <=> $a->priority());

        return $all;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function typed(string $class): array
    {
        return array_values(array_filter(
            $this->plugins(),
            static fn (PluginInterface $plugin): bool => $plugin instanceof $class,
        ));
    }

    public function priority(string $name): int
    {
        foreach ($this->plugins() as $plugin) {
            if ($plugin->name() === $name) {
                return $plugin->priority();
            }
        }

        return 0;
    }

    public function has(string $name): bool
    {
        foreach ($this->plugins() as $plugin) {
            if ($plugin->name() === $name) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Write the failing bindings test**

Create `packages/laravel/tests/Bindings/PluginBindingsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Bindings\PluginBindings;
use Alama\Arazzo\Laravel\Support\PluginRegistry;

it('registers the plugin registry as a container singleton', function (): void {
    PluginBindings::register($this->app);

    expect(app(PluginRegistry::class))->toBeInstanceOf(PluginRegistry::class)
        ->and(app(PluginRegistry::class))->toBe(app(PluginRegistry::class));
});
```

- [ ] **Step 5: Run test to verify it fails**

Run: `vendor/bin/pest packages/laravel/tests --filter PluginBindingsTest` (repo root)

Expected: FAIL with "Class PluginBindings not found".

- [ ] **Step 6: Write minimal implementation**

Create `packages/laravel/src/Bindings/PluginBindings.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Laravel\Support\PluginRegistry;
use Illuminate\Contracts\Container\Container;

/**
 * Container-tagged plugin collection. Protocol packages tag their plugin
 * services arazzo.plugins.*; this binds the collector that feeds the
 * composition roots.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class PluginBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(PluginRegistry::class, fn (Container $app): PluginRegistry => new PluginRegistry($app));
    }
}
```

- [ ] **Step 7: Wire the registrar into the service provider**

Edit `packages/laravel/src/LaravelArazzoServiceProvider.php`:
- Add `use Alama\Arazzo\Laravel\Bindings\PluginBindings;` to the top import block.
- In `packageRegistered()`, call `PluginBindings::register($this->app);` **first**, before `HttpBindings::register($this->app);` (the composition roots consume it).

```php
    public function packageRegistered(): void
    {
        PluginBindings::register($this->app);
        HttpBindings::register($this->app);
        EventBindings::register($this->app);
        PersistenceBindings::register($this->app);
        ResolverBindings::register($this->app);
        GeneratorBindings::register($this->app);
        ExecutionBindings::register($this->app);
        FacadeBindings::register($this->app);
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `composer run test-laravel` (repo root)

Expected: PASS (new PluginRegistry + PluginBindings tests, plus existing suite stays green — adding a registrar that only binds a singleton changes no existing bindings).

- [ ] **Step 9: Verify static analysis**

Run: `composer run analyse-laravel` (repo root)

Expected: PASS (0 errors, phpstan `level: max`). The anonymous-class test fixtures are outside `src` (excluded by `packages/laravel/phpstan.neon.dist` `excludePaths: - tests`), so only the two `src` classes are analysed.

- [ ] **Step 10: Commit**

```bash
git add packages/laravel/src/Support/PluginRegistry.php packages/laravel/src/Bindings/PluginBindings.php packages/laravel/src/LaravelArazzoServiceProvider.php packages/laravel/tests/Support/PluginRegistryTest.php packages/laravel/tests/Bindings/PluginBindingsTest.php
git commit -m "feat(laravel): add container-tagged PluginRegistry collector"
```

---

### Task G2: Composition roots feed the phase registries

Wire the collector into the callers-of-record registries so a composed app resolves executors/source types/donors by *plugin*, not by the hard-coded list the runner assembler currently uses. Reads the existing `ProtocolExecutorRegistry` (runner) and its interface `ProtocolExecutorRegistryInterface` (`packages/runner/src/Execution/Interfaces/ProtocolExecutorRegistryInterface.php`), and bridges the nullable `?OpenApiExecutorInterface` seam in `AsyncGraphSeams`.

> **Consumes (do NOT re-implement):** Phase E4's `OperationExecutorRegistry` and Phase C2's `ExpressionEvaluatorRegistry`/`CriterionEvaluatorRegistry` and Phase D1/A3's `SourceNormalizerRegistry` are the target surfaces. At implementation time, the concrete today-bound registrar is `Alama\Arazzo\Runner\Protocol\ProtocolExecutorRegistry` (already present, `packages/runner/src/Protocol/ProtocolExecutorRegistry.php`), a chain-of-responsibility keyed on `StepProtocolExecutorInterface` with `register(string $name, ...)`, `resolve(Step, ArazzoDocument)`, `getSupportedProtocols()`. G registers registry-feeding bindings and documents the hand-off to E4/C2/D1 when those land.

**Files:**
- Modify: `packages/laravel/src/Bindings/ExecutionBindings.php` (feed `ProtocolExecutorRegistry` from `PluginRegistry`)
- Test: `packages/laravel/tests/Bindings/ExecutionBindingsTest.php` (extend)

**Interfaces:**
- Consumes: `PluginRegistry::typed(...)`/`::plugins()` (G1), `ProtocolExecutorRegistryInterface` (runner), `PluginInterface`.
- Produces: a `ProtocolExecutorRegistryInterface` singleton whose registered executors are drawn from the plugin registry ordered by priority.

- [ ] **Step 1: Write the failing test**

Append to `packages/laravel/tests/Bindings/ExecutionBindingsTest.php`:

```php
use Alama\Arazzo\Runner\Execution\Interfaces\ProtocolExecutorRegistryInterface;
use Alama\Arazzo\Runner\Protocol\ProtocolExecutorRegistry;

it('feeds the protocol executor registry from tagged plugins', function (): void {
    $registry = app(ProtocolExecutorRegistryInterface::class);

    expect($registry)->toBeInstanceOf(ProtocolExecutorRegistry::class)
        ->and($registry->getSupportedProtocols())->toBeArray();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/laravel/tests --filter "protocol executor registry"` (repo root)

Expected: FAIL — `ProtocolExecutorRegistryInterface` is not bound as a singleton in the container yet (the Laravel package currently only exposes protocol executors as aliases into the graph via `ExecutionBindings`, keyed at fixed array indexes — see `ExecutionBindings.php:37-43`).

- [ ] **Step 3: Write minimal implementation**

Edit `packages/laravel/src/Bindings/ExecutionBindings.php` — add the registry binding after the existing `WorkflowEngine` singleton, and add the imports:

```php
use Alama\Arazzo\Laravel\Support\PluginRegistry;
use Alama\Arazzo\Runner\Execution\Interfaces\ProtocolExecutorRegistryInterface;
use Alama\Arazzo\Runner\Protocol\ProtocolExecutorRegistry;
```

Inside `register(Container $app)`, after the `WorkflowEngine` binding:

```php
$app->singleton(ProtocolExecutorRegistryInterface::class, function (Container $app): ProtocolExecutorRegistry {
    $registry = new ProtocolExecutorRegistry();

    $app->make(PluginRegistry::class)->register('arazzo.plugins.operation-executor');

    foreach ($app->make(PluginRegistry::class)->typed(StepProtocolExecutorInterface::class) as $executor) {
        $registry->register($executor->name(), $executor);
    }

    return $registry;
});
```

Add `use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;` to the import block. (If Phase E4 renames the concrete to `OperationExecutorRegistry`, swap the concrete `new ProtocolExecutorRegistry()` for the E4 registry and keep the same `register`/`resolve` shape — the binding closure is the seam.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer run test-laravel` (repo root)

Expected: PASS.

- [ ] **Step 5: Verify static analysis**

Run: `composer run analyse-laravel` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Bindings/ExecutionBindings.php packages/laravel/tests/Bindings/ExecutionBindingsTest.php
git commit -m "feat(laravel): feed protocol executor registry from tagged plugins"
```

---

### Task G3: Config surface — plugins tagging + enabled protocol packages

Config keys let a composed app (or a test) opt protocol packages in/out and pin tag names, keeping the "composer require + nothing else" promise while giving an escape hatch. Adds a `plugins` block to the published `arazzo` config and a `ProtocolDiscovery` helper that reads it (mirroring `ConfigValue`'s safe-narrowing pattern).

**Files:**
- Modify: `packages/laravel/config/arazzo.php`
- Create: `packages/laravel/src/Support/ProtocolDiscovery.php`
- Test: `packages/laravel/tests/Support/ConfigValueTest.php` (extend) — or new `ProtocolDiscoveryTest.php`

**Interfaces:**
- Produces: config keys `arazzo.plugins.tags` (default `['arazzo.plugins.*']`) and `arazzo.plugins.operation_executor` (default `'arazzo.plugins.operation-executor'`); `ProtocolDiscovery::tags(): list<string>` and `ProtocolDiscovery::operationExecutorTag(): string`, each via `ConfigValue` narrowing.

- [ ] **Step 1: Write the failing test**

Create `packages/laravel/tests/Support/ProtocolDiscoveryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Support\ProtocolDiscovery;

it('defaults to the wildcard plugin tag', function (): void {
    config()->set('arazzo.plugins', []);

    expect(ProtocolDiscovery::tags())->toContain('arazzo.plugins.*')
        ->and(ProtocolDiscovery::operationExecutorTag())->toBe('arazzo.plugins.operation-executor');
});

it('reads configured tags and operation executor tag', function (): void {
    config()->set('arazzo.plugins', [
        'tags' => ['arazzo.plugins.custom.*'],
        'operation_executor' => 'arazzo.plugins.custom-operation',
    ]);

    expect(ProtocolDiscovery::tags())->toBe(['arazzo.plugins.custom.*'])
        ->and(ProtocolDiscovery::operationExecutorTag())->toBe('arazzo.plugins.custom-operation');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/laravel/tests --filter ProtocolDiscoveryTest` (repo root)

Expected: FAIL with "Class ProtocolDiscovery not found".

- [ ] **Step 3: Write minimal implementation**

Create `packages/laravel/src/Support/ProtocolDiscovery.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

/**
 * Reads the arazzo.plugins config block with safe defaults so a protocol
 * package can register without the host wiring a tag by hand.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtocolDiscovery
{
    /** @return list<string> */
    public static function tags(): array
    {
        $tags = config('arazzo.plugins.tags', ['arazzo.plugins.*']);

        if (!is_array($tags)) {
            return ['arazzo.plugins.*'];
        }

        return array_values(array_map('strval', $tags));
    }

    public static function operationExecutorTag(): string
    {
        return (string) config('arazzo.plugins.operation_executor', 'arazzo.plugins.operation-executor');
    }
}
```

Modify `packages/laravel/config/arazzo.php` — append a `plugins` block before the closing `];`:

```php
    'plugins' => [
        'tags' => ['arazzo.plugins.*'],
        'operation_executor' => 'arazzo.plugins.operation-executor',
    ],
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer run test-laravel` (repo root)

Expected: PASS. (The existing `LaravelArazzoServiceProviderBindingsTest` and config-dependent tests continue to pass — adding a config key is additive; `pint` will reformat the config array if needed.)

- [ ] **Step 5: Verify static analysis**

Run: `composer run analyse-laravel` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/config/arazzo.php packages/laravel/src/Support/ProtocolDiscovery.php packages/laravel/tests/Support/ProtocolDiscoveryTest.php
git commit -m "feat(laravel): add plugin tag config surface"
```

---

### Task G4: Composition root resolver — seam wiring (AsyncGraphResolver + seams)

Extend the composition surface where a composed app actually builds its execution graph. Currently `AsyncGraphResolver::seams()` (`packages/laravel/src/Support/AsyncGraphResolver.php`) builds `AsyncGraphSeams` and passes the existing nullable `?OpenApiExecutorInterface` seam (`packages/runner/src/AsyncGraphSeams.php:38`). G4 makes the composition-root seeding explicit: a `CompositionRoot` helper resolves the registry-fed executor(s) from the plugin registry and hands them into the seams so the runner assembler consumes them — without editing the runner's hard-coded fallback list.

> **Grounding note:** the runner's `AsyncExecutionGraphAssembler::assemble()` still hard-codes `[$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor]` (`packages/runner/src/Execution/AsyncExecutionGraphAssembler.php:127`). That list is Phase E3/E4's to replace with registry-fed composition. G4 only wires the *seams* a protocol package's executor flows through; it does not touch runner internals.

**Files:**
- Create: `packages/laravel/src/Support/CompositionRoot.php`
- Modify: `packages/laravel/src/Support/AsyncGraphResolver.php`
- Test: `packages/laravel/tests/Support/CompositionRootTest.php`

**Interfaces:**
- Produces: `CompositionRoot::resolvedOpenApiExecutor(): ?OpenApiExecutorInterface` — resolves a tagged `OpenApiExecutorInterface` (the Phase E/F1 executor face) from `PluginRegistry` if present, else `null` (preserving today's `$app->bound(...) ? ... : null` behavior in `AsyncGraphResolver::seams()` line 46).

- [ ] **Step 1: Write the failing test**

Create `packages/laravel/tests/Support/CompositionRootTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Laravel\Support\CompositionRoot;
use Alama\Arazzo\Laravel\Support\PluginRegistry;

it('resolves null when no tagged open-api executor is registered', function (): void {
    expect(CompositionRoot::resolvedOpenApiExecutor())->toBeNull();
});

it('resolves a tagged open-api executor from the plugin registry', function (): void {
    $executor = new class() implements StepProtocolExecutorInterface {
        public function supports(\Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\Spec\ArazzoDocument $document): bool { return false; }

        public function execute(\Alama\Arazzo\Contracts\Spec\Step $step, \Alama\Arazzo\Contracts\State\WorkflowContext $context, \Alama\Arazzo\Contracts\Spec\ArazzoDocument $document, string $executionId): \Alama\Arazzo\Contracts\Spec\StepExecutionOutcome
        {
            throw new LogicException('not executed in composition test');
        }
    };

    app(PluginRegistry::class)->register('arazzo.plugins.operation-executor', $executor::class);

    expect(CompositionRoot::resolvedOpenApiExecutor())->toBe($executor);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/laravel/tests --filter CompositionRootTest` (repo root)

Expected: FAIL with "Class CompositionRoot not found".

- [ ] **Step 3: Write minimal implementation**

Create `packages/laravel/src/Support/CompositionRoot.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;

/**
 * Resolves protocol-provided composition roots from the tagged plugin
 * registry. Returns null when a protocol package has not registered a
 * face, matching the existing nullable-seam behavior in AsyncGraphSeams.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class CompositionRoot
{
    public static function resolvedOpenApiExecutor(): ?OpenApiExecutorInterface
    {
        $app = app();
        $registry = $app->make(PluginRegistry::class);

        $registry->register(ProtocolDiscovery::operationExecutorTag());

        /** @var list<OpenApiExecutorInterface> $executors */
        $executors = $registry->typed(OpenApiExecutorInterface::class);

        return $executors[0] ?? null;
    }
}
```

- [ ] **Step 4: Wire the composition root into the resolver**

Edit `packages/laravel/src/Support/AsyncGraphResolver.php` — replace the `openApiExecutor` seam line with a call through `CompositionRoot` (keeping the `$app->bound(...)` guard as a fallback for non-tagged apps):

```php
use Alama\Arazzo\Laravel\Support\CompositionRoot;

// in seams():
openApiExecutor: CompositionRoot::resolvedOpenApiExecutor() ?? ($app->bound(OpenApiExecutorInterface::class) ? $app->make(OpenApiExecutorInterface::class) : null),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer run test-laravel` (repo root)

Expected: PASS. (Existing `ExecutionBindingsGraphTest` / `ExecutionBindingsTest` resolve the graph; with no tagged executor registered the composition root returns null and behavior is unchanged.)

- [ ] **Step 6: Verify static analysis**

Run: `composer run analyse-laravel` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/src/Support/CompositionRoot.php packages/laravel/src/Support/AsyncGraphResolver.php packages/laravel/tests/Support/CompositionRootTest.php
git commit -m "feat(laravel): resolve protocol composition roots from tagged plugins"
```

---

### Task G5: Umbrella + Laravel require arazzo-protocol-http by default (D9)

Make the composed-app default seamless: add `alama/arazzo-protocol-http` to `packages/core/composer.json` (the umbrella `alama/arazzo-core`) and `packages/laravel/composer.json`, and to the root `composer.json` monorepo require. This encodes spec D9 (lines 133, 622-623): "the umbrella and Laravel require [`arazzo-protocol-http`] by default so composed apps see no change." The protocol *implementation package* itself is authored in Phase F1; G5 only declares the dependency and registers the concrete `alama/arazzo-protocol-http` path repository.

**Files:**
- Modify: `packages/laravel/composer.json` (require + repositories)
- Modify: `packages/core/composer.json` (require + repositories)
- Modify: `composer.json` (root require + repositories)
- Modify: `packages/laravel/config/arazzo.php` (assert the default protocol is present — optional guard, skipped if it would fail on a dev tree without the package installed)

**Interfaces:**
- Produces: `alama/arazzo-protocol-http` (`@dev`) added to `require` and as a `repositories` `{"type": "path", "url": "../protocol-http"}` entry in each composer file.

- [ ] **Step 1: Add the path repository + require to Laravel**

Edit `packages/laravel/composer.json`:
- Add to `repositories`:
```json
{"type": "path", "url": "../protocol-http"}
```
- Add to `require`:
```json
"alama/arazzo-protocol-http": "@dev"
```

- [ ] **Step 2: Add the path repository + require to the umbrella (core)**

Edit `packages/core/composer.json` the same way (same `repositories` entry + `alama/arazzo-protocol-http: "@dev"` in `require`).

- [ ] **Step 3: Add the path repository + require to the root monorepo**

Edit `composer.json` the same way (add `{"type": "path", "url": "packages/protocol-http"}` to `repositories`, and `"alama/arazzo-protocol-http": "@dev"` to `require`). Note the root repository URL is `packages/protocol-http` (root is monorepo-root-relative), while Laravel/core use `../protocol-http`.

- [ ] **Step 4: Verify the dependency tree resolves**

Run: `composer update alama/arazzo-protocol-http --with-dependencies --no-interaction` (repo root)

Expected: Composer reports the package is not published / not found on the path (the package dir does not exist until Phase F1). This step is **informational** — it confirms the require declaration is syntactically valid and documents that the path repository surfaces the dependency once F1 creates `packages/protocol-http`. Do not treat the "not found" as a failure; G5 declares the contract, F1 supplies the implementation.

> If the monorepo's root `composer.json` is a single lock for all packages and a missing path would break `composer install`, guard this task so the require is added but the lock is only refreshed by F1 (which creates the package). The check in Step 4 is advisory.

- [ ] **Step 5: Run the Laravel suite to confirm no regression**

Run: `composer run test-laravel` (repo root)

Expected: PASS (composer file edits alone do not change runtime behavior; existing tests cover the service provider and bindings).

- [ ] **Step 6: Commit**

```bash
git add composer.json packages/core/composer.json packages/laravel/composer.json
git commit -m "build(composer): umbrella and laravel require arazzo-protocol-http by default (D9)"
```

---

### Task G6: Phase G-wide verification + gate

Close out Phase G: full Laravel quality gate, confirm the composition surface is drift-free, and mark the spec's Phase G row complete.

**Files:**
- None to modify (unless formatting requires).

**Interfaces:**
- Consumes: all tasks G1–G5.

- [ ] **Step 1: Run the full Laravel test suite**

Run: `composer run test-laravel` (repo root)

Expected: PASS (all tests, including Arch tests if registered).

- [ ] **Step 2: Run static analysis**

Run: `composer run analyse-laravel` (repo root)

Expected: PASS (0 errors, phpstan `level: max`).

- [ ] **Step 3: Run the formatter check**

Run: `composer run format` or `vendor/bin/pint --test` (repo root)

Expected: PASS (no style violations). If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [ ] **Step 4: Run the repo-wide gate**

Run: `make verify` (repo root)

Expected: PASS — docs regenerated, pint clean, full analyse + test across all packages. This confirms the new `PluginRegistry`/`CompositionRoot`/`ProtocolDiscovery`/config additions do not break `core`/`runner`/`cli` consumers and that the `composer.json` require additions are syntactically sound.

- [ ] **Step 5: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [ ] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Row for Phase G in the "Sequencing" table, and mark it done:

```markdown
**Phase G** — composition ✅ Implemented 2026-09-08 — see `plans/2026-09-08-phase-g-composition.md`.
```

- [ ] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase G composition complete"
```
