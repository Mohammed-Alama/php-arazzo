# Phase D — Document: normalizer port + registry

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> You are free to commit directly to this branch (`main`) while landing this plan. Do not use worktrees or the apptree harness — the repo layout is the one you are working in.
>
> This plan assumes that **Phase A (contracts ports)**, **Phase B (expression grammar)**, **Phase C (expression split)**, and **#20 (JSON Schema validation)** have already landed. If any of those are not yet on `main`, stop and flag it before executing the first task.
>
> **D0 is new and runs first.** This plan previously began at D1. D0 splits `packages/document` into `alama/arazzo-document` (vendor-free model) and `alama/arazzo-sources` (resolution + cebe) before any normalizer work, and repoints D1, D2, D3c and D5 at the new boundary. If you are resuming from an older checkout of this plan, read D0 in full — it is a deliberate breaking change with no deprecation shims.
>
> The plan is the boss. If you can find a way to execute a task exactly as written, do it that way. Only deviate where the code forces you to, and note the deviation in the commit message. Follow the exact task order — do not reorder tasks.

## Goal

**D0 splits the package first.** `packages/document` currently carries five concerns that change for different reasons, and its public face leaks `cebe/php-openapi` through `ResolvedOperation`. D0 splits it into a vendor-free model package (`alama/arazzo-document`: `Parser`, `Validator`, the Spec AST, `DocumentInterface`, `ResolvedOperation`) and a source-resolution package (`alama/arazzo-sources`: `Resolver`, `Normalizer`, the `Document` implementation, every cebe touchpoint). D0 is a green state on its own, and every later task in this plan targets the boundary it establishes.

Then: port the OpenAPI normalization pipeline and the per-protocol step-validation rules for the 1.2 step variants so that source normalization is reachable through a pluggable `SourceNormalizerInterface` registry, and `ResolvedOperation` becomes the two-axis (source type + RPC protocol) value that later phases (F) relay on.

**Ownership after D0.** The registry (`alama-sources`), the normalizers, `OpenApiDocumentLoader`, `OpenApiOperationResolver` and `OpenApiVersionDetector` live in `alama/arazzo-sources`. `ResolvedOperation` and `NormalizedOpenApiOperation` are **pure model types in `alama/arazzo-document`** — they are *not* relocated to `alama/arazzo-protocol-http` by F1. The parsing primitives, `Validator`, and `RuleSet` stay in `arazzo-document`. The cebe handles that `ResolvedOperation` used to expose are carried by `Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle`.

## Architecture

Two packages, outer to inner. `arazzo-sources` depends on `arazzo-document`;
never the reverse.

```
laravel, cli                                       ← composition roots
     │
     ├── arazzo-sources   Resolver/ · Normalizer/ · Document (impl) · cebe · Guzzle
     │        │
     │        ▼
     └── arazzo-document  Parser · Validator · Spec AST · DocumentInterface
              │           · ResolvedOperation · NormalizedOpenApiOperation
              ▼
          contracts / expression                   ← zero vendor below here
```

```
SourceNormalizerInterface (contracts, Phase A)
        ▲
        │ implements
OpenApiSourceNormalizer (sources/src/Normalizer — D2)
        │ composes
        ├─ OpenApiDocumentLoader
        ├─ OpenApiVersionDetector
        ├─ OpenApi30Normalizer
        └─ OpenApi31Normalizer

SourceNormalizerRegistryInterface (contracts, Phase A)
        ▲
SourceNormalizerRegistry (sources/src/Resolver — D1)
        └─ get(SourceType): registers → OpenApiSourceNormalizer (D2)

OpenApiOperationHandle (sources/src/Normalizer — D0)
        └─ carries cebe OpenApi + Operation for the 3 OpenAPI-specific runner sites

ResolvedOperation (document/src — D0, two-axis in D3c)
        ├─ sourceType(): SourceType        (derived from $source->type)
        ├─ binding(): string              (derived: http/soap/grpc/... )
        └─ + rpcProtocol, operationName, rpcMethod, graphqlOperation, interaction

Parser (document/src/Parser — D4a) ──populates──▶ Step 1.2 fields + Components.interactions

Rules (document/src/Validator/Rules — D4b..f)
  └─ registered in RuleSet::default
```

## Tech Stack

- **PHP 8.4** with strict types, readonly classes (contracts), plain classes with promoted readonly props (`ResolvedOperation`).
- **Pest 5** tests, one test file per class. Tests live in `packages/{pkg}/tests`. The shared test helper `Alama\Arazzo\Tests\Support\Fx` (in `packages/core/tests/Support/Fx.php`) is autoloaded across all packages.
- **PHPStan** analysis per package: `composer run analyse-document`, `composer run analyse-contracts`, and — added by D0 — `composer run analyse-sources`.
- **cebe/openapi** for the loaded `OpenApi`/`Operation` models (versions 3.0/3.1 share the cebe object model). After D0 this dependency belongs to `arazzo-sources` only; `arazzo-document` is cebe-free and `ArchTest` enforces it.
- **pint** for formatting (`composer run format`).
- Tests run from repo root: `composer run test-document`, `composer run test-contracts`, and — added by D0 — `composer run test-sources`. Final gate: `make verify`.

## Spec

Master spec: [`2026-09-08-plugin-stack-oms-multiprotocol-design.md`](../specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md) — Phase D section (rows D1–D11), decision table (D2, D3, D9), sequencing (`#23` superseded, `#20` before D), Public API impact, Risks.

Split spec: [`2026-09-26-document-package-split-design.md`](../specs/2026-09-26-document-package-split-design.md) — the approved two-package boundary, the `ResolvedOperation` cebe purge, the composition root, and the five architecture guards that D0 lands. **D0 implements this spec; where this plan and the split spec disagree, the split spec wins.**

Research: [`2026-09-08-arazzo-protocol-spec-prs-impact.md`](../../research/2026-09-08-arazzo-protocol-spec-prs-impact.md) — PR #533 (SOAP/WSDL), #556 (RPC), #567 (GraphQL), #568 (interaction steps).

## Global constraints

1. **Only one deliverable**: this plan file's execution. Do not change the parent spec; do not run `git push`. Each task commits its own work with a descriptive message.
2. **No placeholders.** Every class, test, method, command, and error code below is real, was verified against the current tree, and must exist verbatim by the end of its task.
3. **`SourceNormalizerInterface` signature is frozen** (Phase A): `normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array`. `OpenApiSourceNormalizer::normalize()` returns an **operation index** whose *values* are `ResolvedOperation` objects (D3c) — this is a `mixed` subtype of `array<string, mixed>` and is the documented reconciliation of the two-axis model with the contracts signature. `ResolvedOperation` is a model type in `arazzo-document`; the normalizer that produces it lives in `arazzo-sources` and depends inward, so no F1 relocation is needed for coherence.
4. **D0 is a deliberate breaking change; D1–D5 are not.** D0 performs clean FQCN renames (`Alama\Arazzo\Document\Normalizer\ResolvedOperation` → `Alama\Arazzo\Document\ResolvedOperation`, and the whole `Normalizer/` + `Resolver/` trees → `Alama\Arazzo\Sources\`) with **no deprecation shims and no BC aliases**, and it **removes** `ResolvedOperation::$openApi`, `$cebeOperation` and `$rawDocument` outright rather than growing trailing defaulted parameters. The three consumers of the removed cebe handles (`StepOutputExtractor`, `ResponseSchemaValidator`, `DefaultOpenApiExecutor`) receive `OpenApiOperationHandle` and are amended in Phase E's E3 and Phase F's F1.2. After D0 lands, D1–D5 preserve BC: the internal per-version normalizer method `OpenApiNormalizerInterface::normalize(array, string, string)` is **not** renamed or re-typed, and `DocumentInterface` is **not** extended (new entry points go on the concrete `Document` facade only).
5. **Deviations from earlier phases are documented**, they are: `Step::$graphqlOperation` revised from `?string` (Phase A7) to `?GraphQlOperation` (D3b); `Components` gains `interactions` (D4a); `DocumentInterface` loses the dead `resolveSource()` and `detectOpenApiVersion()` methods in D0 (no consumer outside `document/src` calls either).

## Sequencing

Execution order (each task lists its prereqs explicitly):

| # | Task | Prereqs |
|---|------|---------|
| D0 | **package split** — scaffold `arazzo-sources`, move `Normalizer/` + `Resolver/`, purge cebe from the model, extract the composition root, land 5 guards | Phase A landed |
| D1 | `SourceNormalizerRegistry` | D0 landed, A3 landed |
| D3a | `SourceType` cases `Wsdl`, `Protobuf`, `Graphql` | contracts |
| D3b | contracts model: `GraphQlOperation`, `InteractionMode`, `Interaction` extension, `Step::$graphqlOperation` | A7 landed |
| D3c | two-axis `ResolvedOperation` | D0 landed, D3b |
| D2 | `OpenApiSourceNormalizer` | D0 landed, D1, D3c |
| D4a | parser: 1.2 step fields + `components.interactions` | D3b, A7 |
| D4b | `StepOperationTargetPresentRule` six-target mutual exclusion | D3b |
| D4c | `WsdlStepRule` | D4b |
| D4d | `RpcStepRule` | D4b |
| D4e | `GraphQlStepRule` | D4b |
| D4f | `InteractionStepRule` | D4b |
| D5 | Wire registry into `Document`, final gate | all of the above |

`D3a` and `D3b` can be interleaved freely; `D4b` must land before `D4c..f` because each new rule reuses the extended mutual-exclusion step, and the six-target rule needs the D3b Step fields.

**D0 runs first and is not optional.** It is split into nine sub-tasks that execute strictly in order (D0.1 → D0.9); D0.4 is the green-state gate that proves the split stands on its own before any normalizer work begins. D1, D2, D3c and D5 all write into directories D0 creates, so none of them can run before it. D0.9 amends the Phase E and Phase F plans so they no longer contradict the boundary D0 establishes.

---

## Task D0 — package split: `arazzo-document` (model) + `arazzo-sources` (resolution)

**Prereqs:** Phase A landed. Implements
[`2026-09-26-document-package-split-design.md`](../specs/2026-09-26-document-package-split-design.md).

**Files:**
- Create `packages/sources/composer.json`, `packages/sources/phpstan.neon.dist`, `packages/sources/tests/Pest.php`, `packages/sources/tests/ArchTest.php`
- Move `packages/document/src/Normalizer/**` → `packages/sources/src/Normalizer/**`
- Move `packages/document/src/Resolver/**` → `packages/sources/src/Resolver/**`
- Move `packages/document/src/Document.php` → `packages/sources/src/Document.php`
- Move `packages/document/src/ResolvedOperation.php` (from D0.5), `packages/document/src/NormalizedOpenApiOperation.php` (from D0.5)
- Create `packages/sources/src/ModelStack.php`, `packages/sources/src/SourceGraph.php`, `packages/sources/src/Normalizer/OpenApiOperationHandle.php`
- Create `packages/document/src/ModelStack.php` (from D0.3)
- Modify `packages/document/composer.json`, `packages/document/src/DocumentInterface.php`, `composer.json`
- Move `packages/document/tests/Normalizer/**`, `packages/document/tests/Resolver/**` → `packages/sources/tests/**`; move `DocumentTest.php`, `DocumentCapabilitiesTest.php`
- Modify the three cebe consumers in `packages/runner/src/Execution/`

**Interfaces:**
```
Consumes: (nothing — D0 is the first task in the phase)
Produces:
  Alama\Arazzo\Document\DocumentInterface   (unchanged name; loses resolveSource() + detectOpenApiVersion())
  Alama\Arazzo\Document\ModelStack::default(): ModelStack
  Alama\Arazzo\Document\ResolvedOperation   (namespace + shape changed: no cebe)
  Alama\Arazzo\Document\NormalizedOpenApiOperation
  Alama\Arazzo\Sources\Document             (implements DocumentInterface; moved FQCN)
  Alama\Arazzo\Sources\SourceGraph::default(): DocumentInterface
  Alama\Arazzo\Sources\SourceGraph::using(?ClientInterface, ?RequestFactoryInterface, ?SourceRegistry): DocumentInterface
  Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle
```

D0 is eight ordered sub-tasks. **D0.4 is the green-state gate**: the split must stand on its own, with all suites green, before any normalizer work starts.

---

### D0.1 — Scaffold `alama/arazzo-sources`

- [ ] **Step 1: Write the failing smoke test**

`packages/sources/tests/ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('sources package is autoloadable')
    ->expect('Alama\Arazzo\Sources')
    ->toUse('PHPUnit\Framework\TestCase');
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest packages/sources/tests`
Expected: FAIL — `Pest.php` not found, or the `Alama\Arazzo\Sources` namespace is not in the autoload map.

- [ ] **Step 3: Create the package manifest**

`packages/sources/composer.json`:

```json
{
    "name": "alama/arazzo-sources",
    "description": "Arazzo source resolution, fetching and OpenAPI normalization.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "cebe/php-openapi": "^1.7",
        "guzzlehttp/guzzle": "^7.9",
        "psr/http-client": "^1.0",
        "psr/http-message": "^1.0||^2.0",
        "psr/simple-cache": "^3.0"
    },
    "require-dev": {
        "larastan/larastan": "^3.0",
        "laravel/pint": "^1.14",
        "mockery/mockery": "^1.6",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\Sources\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Alama\\Arazzo\\Tests\\": "tests/"
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

`guzzlehttp/guzzle` is declared **here** because D0.2 moves the code that imports it, and today it is imported by `Document.php` without being declared anywhere `document` can see.

- [ ] **Step 4: Create `packages/sources/phpstan.neon.dist`**

Mirror `packages/document/phpstan.neon.dist` exactly, changing only the scanned sibling:

```neon
includes:
    - ../core/phpstan/rules/phpstan-custom.neon
    - phpstan-baseline.neon

parameters:
    level: max
    paths:
        - src
    excludePaths:
        - tests
    scanDirectories:
        - ../contracts/src
        - ../expression/src
        - ../document/src
    reportUnmatchedIgnoredErrors: false
```

Create an empty `packages/sources/phpstan-baseline.neon` containing `parameters: {}` so the include resolves.

- [ ] **Step 5: Create `packages/sources/tests/Pest.php`**

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\TestCase;

require_once __DIR__.'/../../../vendor/autoload.php';

// cebe/php-openapi emits PHP 8.4 "implicitly nullable parameter"
// deprecations when its spec classes are compiled. That is vendor noise,
// so load the whole spec folder once inside a silenced window instead of
// letting every test record the same deprecation.
set_error_handler(static fn (): bool => true, E_DEPRECATED);

$cebeSrc = dirname(__DIR__, 2).'/vendor/cebe/php-openapi/src/';

foreach (array_merge(
    [$cebeSrc.'SpecBaseObject.php', $cebeSrc.'SpecObjectInterface.php', $cebeSrc.'ReferenceContext.php', $cebeSrc.'Reference.php', $cebeSrc.'Reader.php'],
    glob($cebeSrc.'spec/*.php') ?: [],
) as $cebeFile) {
    if (is_string($cebeFile) && is_file($cebeFile)) {
        require_once $cebeFile;
    }
}

restore_error_handler();

pest()->extend(TestCase::class)->in(__DIR__);
```

This block is **moved** from `packages/document/tests/Pest.php` in D0.2, because it exists only to silence cebe and cebe leaves `arazzo-document` in D0.6.

- [ ] **Step 6: Wire the autoload map and scripts in the root `composer.json`**

Add to `autoload.psr-4`, keeping the map alphabetical:

```json
"Alama\\Arazzo\\Sources\\": "packages/sources/src/"
```

Add to `autoload-dev.psr-4."Alama\\Arazzo\\Tests\\"`:

```json
"packages/sources/tests"
```

Add the two scripts, matching the existing `test-*` / `analyse-*` shapes:

```json
"test-sources": "vendor/bin/pest packages/sources/tests",
"analyse-sources": "vendor/bin/phpstan analyse -c packages/sources/phpstan.neon.dist --memory-limit=1G"
```

Then add `"@test-sources"` to the `test` array (after `"@test-document"`) and `"@analyse-sources"` to the `analyse` array (after `"@analyse-document"`), so both run in CI.

- [ ] **Step 7: Create `packages/sources/src/` so the namespace resolves**

```bash
mkdir -p packages/sources/src
```

- [ ] **Step 8: Regenerate the autoloader and run the test**

Run: `composer dump-autoload && vendor/bin/pest packages/sources/tests`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add packages/sources composer.json
git commit -m "chore(sources): scaffold alama/arazzo-sources package"
```

---

### D0.2 — Mechanically move `Normalizer/`, `Resolver/` and the `Document` implementation

- [ ] **Step 1: Move the source trees with history preserved**

```bash
git mv packages/document/src/Normalizer packages/sources/src/Normalizer
git mv packages/document/src/Resolver    packages/sources/src/Resolver
git mv packages/document/src/Document.php packages/sources/src/Document.php
git mv packages/document/tests/Normalizer packages/sources/tests/Normalizer
git mv packages/document/tests/Resolver    packages/sources/tests/Resolver
git mv packages/document/tests/DocumentTest.php             packages/sources/tests/DocumentTest.php
git mv packages/document/tests/DocumentCapabilitiesTest.php packages/sources/tests/DocumentCapabilitiesTest.php
```

`git mv` on the whole directory moves the entire tree — including `Normalizer/Interfaces/OpenApiNormalizerInterface.php`, `Resolver/Exceptions/*`, `Resolver/Fetchers/*` and `Resolver/Interfaces/*` — so no per-file listing is needed.

- [ ] **Step 2: Rewrite the namespace declarations in the moved trees**

Every file under `packages/sources/src/Normalizer` and `packages/sources/src/Resolver`:

```bash
# namespace declarations
rg -l '^namespace Alama\\Arazzo\\Document\\(Normalizer|Resolver)' packages/sources/src \
  | xargs sed -i '' 's/^namespace Alama\\Arazzo\\Document\\/namespace Alama\\Arazzo\\Sources\\/'

# every import of the moved trees, anywhere in the repo
rg -l 'Alama\\Arazzo\\Document\\(Normalizer|Resolver)\\' packages \
  | xargs sed -i '' 's/Alama\\Arazzo\\Document\\/Alama\\Arazzo\\Sources\\/g'

# the moved implementation itself
sed -i '' 's/^namespace Alama\\Arazzo\\Document;/namespace Alama\\Arazzo\\Sources;/' packages/sources/src/Document.php
sed -i '' 's/use Alama\\Arazzo\\Document\\/use Alama\\Arazzo\\Sources\\/' packages/sources/src/Document.php
```

`DocumentInterface` is **not** touched — it stays in `Alama\Arazzo\Document`.

- [ ] **Step 3: Verify no stale references remain**

Run: `rg -n 'Alama\\Arazzo\\Document\\(Normalizer|Resolver)' packages --glob '!*/vendor/*'`
Expected: no output.

Run: `rg -n '^namespace' packages/sources/src | sort`
Expected: every file reports `namespace Alama\Arazzo\Sources\...`.

- [ ] **Step 4: Repoint the consumers**

`composer.json` of `runner`, `laravel`, `cli` and `core` each need the new requirement. In each, add `"alama/arazzo-sources": "@dev"` alongside the existing `alama/arazzo-document` entry. `packages/core` keeps its `alama/arazzo-document` requirement because `core` still uses the validator and parser.

`packages/laravel/src/Bindings/FacadeBindings.php` — the singleton moves to the builder that D0.3 creates. For this sub-task, repoint the import only:

```php
-use Alama\Arazzo\Document\Document;
+use Alama\Arazzo\Sources\Document;
```

Leave `$app->singleton(DocumentInterface::class, fn (): Document => new Document());` as-is for now; D0.3 replaces its body.

- [ ] **Step 5: Run the full suite**

Run: `composer run test`
Expected: PASS. `DocumentInterface` and the class names are unchanged, so the D0.2 move is behaviour-preserving.

- [ ] **Step 6: Run static analysis**

Run: `composer run analyse-document && composer run analyse-sources`
Expected: clean. If `analyse-sources` reports the empty baseline as unmatched, set `reportUnmatchedIgnoredErrors: false` (already set in D0.1 Step 4) and re-run.

- [ ] **Step 7: Commit**

```bash
git add packages composer.json
git commit -m "refactor(sources): move Normalizer, Resolver and Document into arazzo-sources"
```

---

### D0.3 — Extract the composition root (`ModelStack` + `SourceGraph`)

- [ ] **Step 1: Write the failing test**

`packages/sources/tests/SourceGraphTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Sources\SourceGraph;

it('builds a document that satisfies the port without inline http construction', function (): void {
    $document = SourceGraph::default();

    expect($document)->toBeInstanceOf(DocumentInterface::class);
});

it('honours an injected source registry', function (): void {
    $registry = new \Alama\Arazzo\Sources\Resolver\SourceRegistry(
        new \Alama\Arazzo\Sources\Resolver\DefaultSourceResolver([])
    );

    $document = SourceGraph::using(registry: $registry);

    expect($document->resolveOperation)->toBeCallable();
});

it('does not construct a guzzle client inside the document implementation', function (): void {
    $source = file_get_contents(__DIR__.'/../../document/src/Document.php');

    expect($source)->not->toContain('GuzzleHttp\Client');
});
```

The third test is the executable form of the composition-root rule; it fails now and passes in Step 4.

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest packages/sources/tests/SourceGraphTest.php`
Expected: FAIL — `Alama\Arazzo\Sources\SourceGraph` not found.

- [ ] **Step 3: Create `ModelStack` in the model package**

`packages/document/src/ModelStack.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Loader;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/**
 * The pure model half of the Document graph: parsing primitives, the
 * validator and the expression engine. No vendor sits behind any of these,
 * so a consumer may construct them without violating the dependency rule.
 */
final readonly class ModelStack
{
    public function __construct(
        public Loader $loader,
        public Parser $parser,
        public Validator $validator,
        public ExpressionEngineInterface $engine,
    ) {}

    public static function default(): self
    {
        $engine = new ExpressionEngine();

        return new self(
            loader: new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder()),
            parser: new Parser(),
            validator: new Validator(RuleSet::default($engine)),
            engine: $engine,
        );
    }
}
```

`PreflightValidator` is **not** in the stack: it is constructed by `SourceGraph` because it needs the `SourceRegistry` and the `OpenApiOperationResolver`, which are source-side.

- [ ] **Step 4: Create `SourceGraph`, the single place that knows about Guzzle**

`packages/sources/src/SourceGraph.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\ModelStack;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Composition root for source resolution. This is the only class in the
 * repository permitted to construct a Guzzle client.
 */
final class SourceGraph
{
    public static function default(): DocumentInterface
    {
        return self::using();
    }

    public static function using(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $httpFactory = null,
        ?SourceRegistry $registry = null,
    ): DocumentInterface {
        $client = $httpClient ?? new Client();
        $factory = $httpFactory ?? new HttpFactory();

        $sources = $registry ?? new SourceRegistry(new DefaultSourceResolver([
            'http' => new HttpFetcher($client, $factory),
            'https' => new HttpFetcher($client, $factory),
            'file' => new LocalFetcher(),
        ]));

        $versionDetector = new OpenApiVersionDetector();

        $operations = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($sources),
            $versionDetector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        return new Document(
            model: ModelStack::default(),
            sources: $sources,
            operations: $operations,
            versionDetector: $versionDetector,
            preflight: new PreflightValidator($sources, $operations),
        );
    }
}
```

- [ ] **Step 5: Rewrite the `Document` constructor to take explicit collaborators**

In `packages/sources/src/Document.php`, replace the constructor (currently lines 62–90) with:

```php
    public function __construct(
        private readonly ModelStack $model,
        private readonly SourceRegistry $sources,
        private readonly OpenApiOperationResolver $operations,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly PreflightValidator $preflight,
    ) {}
```

and drop the `GuzzleHttp\Client`, `GuzzleHttp\Psr7\HttpFactory`, `HttpFetcher`, `LocalFetcher`, `DefaultSourceResolver`, `OpenApiDocumentLoader`, `OpenApi30Normalizer`, `OpenApi31Normalizer`, `OpenApiOperationResolver` construction imports. The `$loader`, `$parser`, `$validator` and `$engine` properties are removed; their call sites read through `$this->model`:

- `load()` → `$this->model->parser->parse($this->model->loader->load($path))`
- `parse()` → `$this->model->parser->parse($raw)`
- `validate()` → `$this->model->validator->validate($document)`

`GuzzleHttp\Client` must no longer appear in this file — that is what Step 1's third test asserts.

- [ ] **Step 6: Update every construction site**

`rg -n 'new Document\(' packages --glob '!*/vendor/*'` currently reports 12 sites. Each becomes a `SourceGraph` call:

```php
- new Document()
+ SourceGraph::default()
```

```php
- new Document(null, null, $registry)
+ SourceGraph::using(registry: $registry)
```

Sites: `packages/laravel/src/Bindings/FacadeBindings.php` (use `SourceGraph::default()` in the singleton), `packages/core/tests/Validator/PreflightValidatorTest.php`, `packages/core/tests/Validator/InputsPreValidationTest.php` (×2), `packages/core/tests/Conformance/ConformanceHarness.php`, `packages/runner/tests/RunnerCapabilitiesTest.php` (×3), `packages/runner/tests/RunnerTest.php`, `packages/runner/tests/Execution/ArazzoOutputExtractorTest.php`, `packages/runner/tests/Execution/AdapterParityTest.php`, `packages/runner/tests/Execution/WorkflowExecutorTest.php`, `packages/runner/tests/Execution/AsyncExecutionGraphAssemblerTest.php`, `packages/runner/tests/Async/PreflightGuardTest.php`.

`AdapterParityTest` passes an anonymous `SourceResolver`; route it through `SourceGraph::using(registry: new SourceRegistry($anonymousResolver))`.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `composer run test && vendor/bin/pest packages/sources/tests/SourceGraphTest.php`
Expected: PASS, including the `GuzzleHttp\Client` assertion.

- [ ] **Step 8: Commit**

```bash
git add packages composer.json
git commit -m "refactor(sources): extract ModelStack and SourceGraph composition root"
```

---

### D0.4 — Green-state gate

The split must stand on its own before any normalizer work begins. Do not proceed if anything here fails.

- [ ] **Step 1: Confirm the package boundary is real**

Run: `ls packages/sources/src`
Expected: `Normalizer/`, `Resolver/`, `Document.php`, `ModelStack.php`, `SourceGraph.php`.

Run: `ls packages/document/src`
Expected: `Parser/`, `Validator/`, `DocumentInterface.php`, `ModelStack.php`. **No `Normalizer/`, no `Resolver/`, no `Document.php`.**

- [ ] **Step 2: Confirm `document` no longer reaches a transport client**

Run: `rg -n 'GuzzleHttp|Psr\\Http|Psr\\SimpleCache' packages/document/src`
Expected: no output.

- [ ] **Step 3: Run the whole suite and both analysers**

Run: `composer run test && composer run analyse-document && composer run analyse-sources`
Expected: all green.

- [ ] **Step 4: Confirm the dependency direction**

Run: `rg -n 'Alama\\Arazzo\\Sources' packages/document/src`
Expected: no output — `arazzo-document` must not name `arazzo-sources`.

- [ ] **Step 5: Commit the gate as a no-op if needed**

Nothing to commit unless a fix landed. Record the result in the D0 commit trail.

---

### D0.5 — Make `ResolvedOperation` a pure model type

The cebe purge. This is the breaking change Global constraint 4 sanctions, and the reason the model package can be vendor-free.

- [ ] **Step 1: Write the failing test**

`packages/document/tests/ResolvedOperationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;

it('carries only vendor-free collaborators', function (): void {
    $normalized = new NormalizedOpenApiOperation(
        path: '/pets/{id}',
        method: 'get',
        resolvedServerUrl: 'https://example.test',
        pathParameters: ['id' => ['style' => 'simple']],
        queryParameters: [],
        headerParameters: [],
        cookieParameters: [],
        requestBodies: [],
        responses: ['200' => ['contentType' => 'application/json']],
    );

    $operation = new ResolvedOperation(
        source: new SourceDescription(
            name: 'api',
            url: 'https://example.test/openapi.json',
            type: SourceType::Openapi,
        ),
        normalized: $normalized,
    );

    expect($operation->normalized)->toBe($normalized)
        ->and($operation->source->name)->toBe('api');
});

it('does not expose cebe handles on the public surface', function (): void {
    $properties = array_map(
        static fn (\ReflectionProperty $p): string => $p->getName(),
        (new \ReflectionClass(ResolvedOperation::class))->getProperties(),
    );

    expect($properties)->toBe(['source', 'normalized']);
});
```

`NormalizedOpenApiOperation`'s constructor takes all nine arguments with no defaults, in this order: `path`, `method`, `resolvedServerUrl`, `pathParameters`, `queryParameters`, `headerParameters`, `cookieParameters`, `requestBodies`, `responses`. `SourceDescription`'s takes `name`, `url`, `type` — note `url` precedes `type`, though named arguments make the order irrelevant at the call site.

The second test fails today: the class is in the `Normalizer` namespace and its properties are `source`, `normalized`, `openApi`, `rawDocument`, `cebeOperation`.

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest packages/document/tests/ResolvedOperationTest.php`
Expected: FAIL — class not found at the new FQCN.

- [ ] **Step 3: Move both DTOs to the model package root and purge the cebe handles**

```bash
git mv packages/sources/src/Normalizer/ResolvedOperation.php        packages/document/src/ResolvedOperation.php
git mv packages/sources/src/Normalizer/NormalizedOpenApiOperation.php packages/document/src/NormalizedOpenApiOperation.php
sed -i '' 's/^namespace Alama\\Arazzo\\Sources\\Normalizer;/namespace Alama\\Arazzo\\Document;/' \
  packages/document/src/ResolvedOperation.php packages/document/src/NormalizedOpenApiOperation.php
```

`ResolvedOperation` becomes exactly:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\SourceDescription;

class ResolvedOperation
{
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
    ) {}
}
```

The `use cebe\openapi\spec\OpenApi;` and `use cebe\openapi\spec\Operation;` imports and the `@param array<string, mixed> $rawDocument` docblock go. The class stays non-`final` with promoted `public readonly` props because D3c extends it in place and runner tests construct it positionally.

- [ ] **Step 4: Add the wrapper that carries the cebe handles**

`packages/sources/src/Normalizer/OpenApiOperationHandle.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Document\ResolvedOperation;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

/**
 * The cebe-facing view of a resolved operation. The three OpenAPI-specific
 * consumers in the runner receive this instead of the model type, which is
 * what lets alama/arazzo-document stay cebe-free.
 */
final readonly class OpenApiOperationHandle
{
    public function __construct(
        public ResolvedOperation $operation,
        public OpenApi $openApi,
        public Operation $cebeOperation,
    ) {}
}
```

- [ ] **Step 5: Make the resolver return the handle alongside the model**

`OpenApiOperationResolver::resolve()` currently returns `ResolvedOperation` and is the sole producer of the cebe handles. Change it to return `OpenApiOperationHandle`, and add a `resolveModel()` that returns just the `ResolvedOperation`:

```php
    public function resolve(Step $step, ArazzoDocument $document): OpenApiOperationHandle;

    public function resolveModel(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        return $this->resolve($step, $document)->operation;
    }
```

`Document::resolveOperation()` — still on the port, still returning `ResolvedOperation` — now calls `$this->operations->resolveModel(...)`, so the public face is unchanged.

`PreflightValidator` line 218 also calls `$this->operations->resolve($step, $document)`, but discards the return value. Widening the return type to `OpenApiOperationHandle` therefore needs no edit there — confirm it is still a bare statement after the change rather than assuming.

- [ ] **Step 6: Repoint the three cebe consumers**

Run: `rg -n -- '->(openApi|cebeOperation)' packages/runner/src`
Expected: exactly three hits, in `StepOutputExtractor.php:99`, `ResponseSchemaValidator.php:91` and `DefaultOpenApiExecutor.php`.

All three currently call a private `resolveOperation()` helper that returns the model type and then read a cebe property off it. Change the helper's return type to `?OpenApiOperationHandle` and have it return the handle:

```php
    private function resolveOperation(Step $step, ArazzoDocument $document): ?OpenApiOperationHandle
    {
        try {
            return $this->operationResolver->resolve($step, $document);
        } catch (\RuntimeException) {
            return null;
        }
    }
```

Then update the three read sites:

- `StepOutputExtractor.php:99` — `$operation = $resolved->cebeOperation;` becomes `$operation = $handle?->cebeOperation;`, and the `$operation->responses` access on line 105 is guarded for `null`.
- `ResponseSchemaValidator.php:91` — `return $resolved->cebeOperation;` becomes `return $handle?->cebeOperation;`.
- `DefaultOpenApiExecutor.php` — `$openApi = $operation->openApi;` becomes `$openApi = $handle->openApi;`. The `->normalized->*` reads on that class are untouched: `NormalizedOpenApiOperation` is a model type, so those stay on the model.

Add `use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;` to all three files.

- [ ] **Step 7: Fix every construction and import of the moved DTOs**

Run: `rg -l 'Alama\\Arazzo\\Sources\\Normalizer\\(ResolvedOperation|NormalizedOpenApiOperation)' packages --glob '!*/vendor/*' | xargs sed -i '' 's/Alama\\Arazzo\\Sources\\Normalizer\\/Alama\\Arazzo\\Document\\/g'`

Run: `rg -l 'Alama\\Arazzo\\Document\\Normalizer\\' packages --glob '!*/vendor/*' | xargs sed -i '' 's/Alama\\Arazzo\\Document\\Normalizer\\/Alama\\Arazzo\\Document\\/g'`

Any test constructing `ResolvedOperation` with five positional arguments drops the last three.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `composer run test && vendor/bin/pest packages/document/tests/ResolvedOperationTest.php`
Expected: PASS.

- [ ] **Step 9: Confirm the purge landed**

Run: `rg -n 'cebe' packages/document/src`
Expected: no output.

- [ ] **Step 10: Commit**

```bash
git add packages
git commit -m "refactor(document): make ResolvedOperation a vendor-free model type"
```

---

### D0.6 — Make the manifests tell the truth

- [ ] **Step 1: Update `packages/sources/composer.json`**

Already declares `guzzlehttp/guzzle` and `cebe/php-openapi` from D0.1. Verify nothing is missing by running:

Run: `rg -o 'GuzzleHttp\\[A-Za-z]+|Psr\\[A-Za-z]+\\[A-Za-z]+' packages/sources/src -N | sort -u`
Expected: every root namespace found is declared in `packages/sources/composer.json`.

- [ ] **Step 2: Trim `packages/document/composer.json`**

Remove `"cebe/php-openapi"`, `"psr/http-client"`, `"psr/http-message"`, `"psr/simple-cache"` and `"softcreatr/jsonpath"`. The result is:

```json
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-expression": "@dev",
        "justinrainbow/json-schema": "^6.0",
        "symfony/yaml": "^7.0"
    },
```

- [ ] **Step 3: Prove `softcreatr/jsonpath` is genuinely unused before removing it**

Grepping `jsonpath` is a trap: the tree is full of `jsonpath` *selector-type enum strings* that have nothing to do with this library. Grep for the vendor symbol:

Run: `rg -n 'Softcreatr' packages --glob '!*/vendor/*'`
Expected: **no output**. Only then is the removal safe.

- [ ] **Step 4: Refresh the lock and reinstall**

Run: `composer update --lock && composer install`
Expected: `softcreatr/jsonpath`, `cebe/php-openapi` and the `psr/*` entries drop out of `packages/document`'s resolved set.

- [ ] **Step 5: Verify the model package resolves standalone**

Run: `composer run analyse-document && composer run test-document`
Expected: green, with no cebe class referenced.

- [ ] **Step 6: Commit**

```bash
git add packages/document/composer.json composer.lock
git commit -m "chore(document): drop cebe, psr and unused softcreatr/jsonpath"
```

---

### D0.7 — Guards, dead API, and two misfiled tests

- [ ] **Step 1: Add guard 1 — the model package is vendor-free**

Append to `packages/document/tests/ArchTest.php`:

```php
arch('document model is vendor-free')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('cebe')
    ->not->toUse('GuzzleHttp')
    ->not->toUse('Psr\Http')
    ->not->toUse('Psr\SimpleCache')
    ->not->toUse('Softcreatr');
```

- [ ] **Step 2: Add guard 2 — sources does not point at outer layers**

Replace the placeholder arch block written in D0.1 Step 1 with:

```php
arch('sources does not depend on outer layers')
    ->expect('Alama\Arazzo\Sources')
    ->not->toUse('Alama\Arazzo\Runner')
    ->not->toUse('Alama\Arazzo\Cli')
    ->not->toUse('Alama\Arazzo\Protocol')
    ->not->toUse('Alama\Arazzo\Runtime')
    ->not->toUse('Illuminate');

arch('sources may reach the model package and the transport')
    ->expect('Alama\Arazzo\Sources')
    ->toUse('Alama\Arazzo\Document');
```

- [ ] **Step 3: Add guard 3 — only the composition root constructs http clients**

Append to `packages/sources/tests/ArchTest.php`:

```php
arch('only the sources composition root constructs http clients')
    ->expect('Alama\Arazzo\Sources\Document')
    ->not->toUse('GuzzleHttp')
    ->expect('Alama\Arazzo\Sources\Resolver\DefaultSourceResolver')
    ->not->toUse('GuzzleHttp');
```

- [ ] **Step 4: Add guard 4 — the runner is cebe-free**

Append to `packages/runner/tests/ArchTest.php`:

```php
arch('runner does not depend on cebe directly')
    ->expect('Alama\Arazzo\Runner')
    ->not->toUse('cebe');
```

`DefaultOpenApiExecutor` is cebe-free after D0.5 Step 6 because it now reads the handle's properties rather than naming a cebe type. If this guard fails, the class is still importing a cebe type in a signature — move that read into the handle.

- [ ] **Step 5: Add guard 5 — the manifests agree with the code**

Create `packages/document/tests/PackageManifestTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

it('does not declare transport or openapi dependencies', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(__DIR__.'/../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $require = array_keys($manifest['require']);

    expect($require)
        ->not->toContain('cebe/php-openapi')
        ->not->toContain('guzzlehttp/guzzle')
        ->not->toContain('psr/http-client')
        ->not->toContain('psr/http-message')
        ->not->toContain('psr/simple-cache')
        ->not->toContain('softcreatr/jsonpath');
});
```

- [ ] **Step 6: Remove the two dead methods from the public face**

`resolveSource()` and `detectOpenApiVersion()` are called by nobody outside `document/src`. Delete them from `DocumentInterface` (lines 70 and 80 of the pre-split file) **only**. Keep them as public methods on the concrete `Alama\Arazzo\Sources\Document` so `DocumentCapabilitiesTest` still covers them.

- [ ] **Step 7: Relocate two misfiled tests**

`packages/sources/tests/Resolver/SelectorEvaluatorTest.php` and `packages/sources/tests/Resolver/Xpath/DomXpathEvaluatorTest.php` test `Alama\Arazzo\Evaluation\SelectorEvaluator` and `Alama\Arazzo\Evaluation\Xpath\DomXpathEvaluator` — classes that live in `packages/evaluation`, not here. Moving them wholesale into `arazzo-sources` would propagate a pre-existing mistake:

```bash
git mv packages/sources/tests/Resolver/SelectorEvaluatorTest.php        packages/evaluation/tests/SelectorEvaluatorTest.php
git mv packages/sources/tests/Resolver/Xpath/DomXpathEvaluatorTest.php   packages/evaluation/tests/Xpath/DomXpathEvaluatorTest.php
rmdir packages/sources/tests/Resolver/Xpath
```

`packages/evaluation/tests/ArchTest.php` already asserts that `Alama\Arazzo\Evaluation` does not use `Alama\Arazzo\Document`, so these tests must not import from the model package — check their imports when moving.

- [ ] **Step 8: Run the guards**

Run: `vendor/bin/pest packages/document/tests/ArchTest.php packages/sources/tests/ArchTest.php packages/runner/tests/ArchTest.php`
Expected: PASS.

- [ ] **Step 9: Run the full suite**

Run: `composer run test`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add packages
git commit -m "test(sources): add boundary guards, drop dead document interface methods"
```

---

### D0.8 — D0 final gate

- [ ] **Step 1: Full verification**

Run: `make verify`
Expected: green — docs generation, pint, phpstan across all seven packages, every pest suite.

- [ ] **Step 2: Confirm each file the design names exists**

Run: `ls packages/sources/src/SourceGraph.php packages/sources/src/Normalizer/OpenApiOperationHandle.php packages/document/src/ModelStack.php`
Expected: all three present.

- [ ] **Step 3: Confirm the dependency direction one final time**

Run: `rg -n 'Alama\\Arazzo\\Sources' packages/document/src` and `rg -n 'cebe|GuzzleHttp' packages/document/src`
Expected: no output from either.

- [ ] **Step 4: Commit**

```bash
git commit --allow-empty -m "chore(document): D0 package split complete"
```

---

### D0.9 — Amend the Phase E and Phase F plans

D0 changes where the normalizers and the two DTOs live, which invalidates specific instructions in two already-written plans. Leaving them unamended would hand the next implementer contradictory instructions. This sub-task is a documentation-only change to two plan files.

**Files:**
- Modify `docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md`
- Modify `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md`

- [ ] **Step 1: Delete the FQCN-stability rule from the Phase F plan**

In `docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md`, delete the constraint that begins:

> **FQCN stability (D9).** `Alama\Arazzo\Document\Normalizer\ResolvedOperation` and `…\NormalizedOpenApiOperation` are the ONLY relocated types whose FQCNs do not change.

Replace it with:

> **The two DTOs are not relocated.** D0 made `ResolvedOperation` and `NormalizedOpenApiOperation` pure model types owned by `alama/arazzo-document` at `Alama\Arazzo\Document\ResolvedOperation` and `Alama\Arazzo\Document\NormalizedOpenApiOperation`. F1.1 does not move them. The cebe handles they used to expose travel on `Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle`, which **does** move to `alama/arazzo-protocol-http` as an `@internal` type.

- [ ] **Step 2: Correct F1.1's premise — normalizers come out of `arazzo-sources`, not `document`**

In the same file, the F1.1 step beginning "Move the normalizer classes out of `document` into the payload-http package" must be rewritten: the source normalizers, `OpenApiDocumentLoader`, `OpenApiOperationResolver` and `OpenApiDocumentLoader`'s cebe usage now live in `packages/sources/src/Normalizer/`, so F1.1 moves them **out of `arazzo-sources`**. Delete the two `git mv` commands that relocate the DTOs:

```bash
git mv packages/document/src/Normalizer/ResolvedOperation.php packages/protocol-http/src/Document/Normalizer/ResolvedOperation.php
git mv packages/document/src/Normalizer/NormalizedOpenApiOperation.php packages/protocol-http/src/Document/Normalizer/NormalizedOpenApiOperation.php
```

and replace the two resulting `→  packages/protocol-http/src/Document/Normalizer/…` file entries with a note that both DTOs stay in `packages/document/src/`.

Also correct the sentence asserting that `OpenApiVersionDetector` and `Interfaces/OpenApiNormalizerInterface` "**stay in `document`**": after D0 they are in `alama/arazzo-sources` alongside the other normalizers, so F1.1 relocates them too unless a reason to keep them appears.

- [ ] **Step 3: Remove the dual-PSR-4 and scanDirectories instructions**

Three instructions in the Phase F plan exist only to make the DTOs resolvable from `protocol-http`, and are now wrong:

- the "dual PSR-4 map (`Alama\Arazzo\Document\Normalizer\ → src/Document/Normalizer/`)" note — delete it;
- the step telling the implementer to add `../protocol-http/src` to `scanDirectories` in `packages/document/phpstan.neon.dist` — delete it, and add the guard-1 assertion instead: `arazzo-document` must not reference `protocol-http`;
- the instruction to add `../protocol-http/src` to `scanDirectories` in `packages/runner/phpstan.neon.dist` — keep this one; `runner` does read `OpenApiOperationHandle` after D0.5.

Update `DefaultOpenApiExecutor`'s description to import `Alama\Arazzo\Document\ResolvedOperation` (model, no `Normalizer` segment) and `Alama\Arazzo\Protocol\Http\Normalizer\OpenApiOperationHandle`.

- [ ] **Step 4: Give F1.2 the two OpenAPI-specific classes**

In the Phase F plan's F1.2 files-moved list, add:

```
- packages/runner/src/Execution/StepOutputExtractor.php
- packages/runner/src/Execution/ResponseSchemaValidator.php
```

with the rationale: both read the cebe `Operation` (the former's `$operation->responses`, the latter's returned handle), so they are OpenAPI-specific and cannot live in vendor-free `alama/arazzo-request-pipeline`. Remove them from that list in the Phase E plan.

- [ ] **Step 5: Amend Phase E's E3 migration list**

In `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md`, the E3 line beginning `- \`RequestCompiler.php\`, \`ParameterSerializer.php\`, …` lists eleven files moving to `packages/request-pipeline/src/`. Delete `ResponseSchemaValidator.php` and `StepOutputExtractor.php` from that list, leaving nine. Delete the follow-on sentence that begins:

> `StepOutputExtractor` moves here too — note it imports `Expression\Enum\ReferenceKind`

and replace it with:

> `StepOutputExtractor` and `ResponseSchemaValidator` do **not** move here: both are OpenAPI-specific (they read the cebe `Operation` that D0 moved behind `OpenApiOperationHandle`), so Phase F's F1.2 relocates them into `alama/arazzo-protocol-http` instead. `alama/arazzo-request-pipeline` stays vendor-free.

- [ ] **Step 6: Fix the F4 SOAP task's DTO reference**

In the Phase F plan, the SOAP normalizer step states that `normalize()` yields `Alama\Arazzo\Document\Normalizer\NormalizedOpenApiOperation`. Change it to `Alama\Arazzo\Document\NormalizedOpenApiOperation`.

- [ ] **Step 7: Verify no stale references survive in either plan**

Run:
```bash
rg -n 'Document\\Normalizer\\(ResolvedOperation|NormalizedOpenApiOperation)' \
  docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md \
  docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md
```
Expected: no output.

Run:
```bash
rg -n 'packages/document/src/(Normalizer|Resolver)' \
  docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md \
  docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md
```
Expected: no output, except any line that explicitly describes the D0 move itself.

- [ ] **Step 8: Commit**

```bash
git add docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md
git commit -m "docs(plans): amend phases E and F for the arazzo-sources boundary"
```

---

## Task D1 — `SourceNormalizerRegistry`

**Prereqs:** Phase A landed (`SourceNormalizerInterface` + `SourceNormalizerRegistryInterface` exist in `packages/contracts/src/Interfaces`). If they do not exist on `main`, stop and flag that Phase A has not landed.

**Files:**
- Create `packages/sources/src/Resolver/SourceNormalizerRegistry.php`
- Create `packages/sources/tests/Resolver/SourceNormalizerRegistryTest.php`

**Interfaces:**
```
Consumes: SourceNormalizerInterface (name(), priority(), supports(SourceType), normalize(...) : array)
Produces: SourceNormalizerRegistryInterface
```

### Steps

1. Write the failing registry test.

`packages/sources/tests/Resolver/SourceNormalizerRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;

final class FakeNormalizer implements SourceNormalizerInterface
{
    /** @param list<SourceType> $types */
    public function __construct(
        private readonly string $fakeName,
        private readonly int $fakePriority,
        private readonly array $types,
    ) {}

    public function name(): string
    {
        return $this->fakeName;
    }

    public function priority(): int
    {
        return $this->fakePriority;
    }

    public function supports(SourceType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        return [];
    }
}

it('returns null when no normalizer supports the requested source type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $registry->register($openapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBeNull();
});

it('returns the first registered normalizer that supports the requested type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $asyncapi = new FakeNormalizer('asyncapi', 0, [SourceType::Asyncapi]);
    $registry->register($openapi);
    $registry->register($asyncapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBe($asyncapi);
});

it('prefers the highest-priority normalizer, then registration order for ties', function (): void {
    $registry = new SourceNormalizerRegistry();
    $lenient = new FakeNormalizer('openapi-lenient', 0, [SourceType::Openapi]);
    $strict = new FakeNormalizer('openapi-strict', 100, [SourceType::Openapi]);
    $registry->register($lenient);
    $registry->register($strict);

    expect($registry->get(SourceType::Openapi))->toBe($strict);

    $registry2 = new SourceNormalizerRegistry();
    $first = new FakeNormalizer('first', 0, [SourceType::Openapi]);
    $second = new FakeNormalizer('second', 0, [SourceType::Openapi]);
    $registry2->register($first);
    $registry2->register($second);

    expect($registry2->get(SourceType::Openapi))->toBe($first);
});
```

2. Run it — it must fail to load (`SourceNormalizerRegistry` not found):

```bash
composer run test-document -- --filter=SourceNormalizerRegistry
```

3. Implement the registry.

`packages/sources/src/Resolver/SourceNormalizerRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SourceNormalizerRegistry implements SourceNormalizerRegistryInterface
{
    /** @var list<SourceNormalizerInterface> */
    private array $normalizers = [];

    public function register(SourceNormalizerInterface $normalizer): void
    {
        $this->normalizers[] = $normalizer;
    }

    public function get(SourceType $type): ?SourceNormalizerInterface
    {
        foreach ($this->ordered() as $normalizer) {
            if ($normalizer->supports($type)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * Highest priority first; stable so registration order breaks ties.
     *
     * @return list<SourceNormalizerInterface>
     */
    private function ordered(): array
    {
        $normalizers = $this->normalizers;
        usort(
            $normalizers,
            static fn (SourceNormalizerInterface $a, SourceNormalizerInterface $b): int => $b->priority() <=> $a->priority(),
        );

        return $normalizers;
    }
}
```

4. Re-run the test — it passes:

```bash
composer run test-document -- --filter=SourceNormalizerRegistry
composer run analyse-document
```

5. Commit:

```bash
git add packages/sources/src/Resolver/SourceNormalizerRegistry.php packages/sources/tests/Resolver/SourceNormalizerRegistryTest.php
git commit -m "feat(document): add SourceNormalizerRegistry (D1)"
```

---

## Task D3a — `SourceType` gains `Wsdl`, `Protobuf`, `Graphql`

**Prereqs:** contracts package present. Verified today that no code exhaustively `match`es `SourceType` — the only uses are `===`/`!==` comparisons, so adding cases is safe.

**Files:**
- Edit `packages/contracts/src/Spec/Enum/SourceType.php`
- Edit `packages/document/src/Parser/Parser.php` (`parseSourceDescription` enum error message only)
- Create `packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php`

### Steps

1. Write the failing enum test.

`packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

it('exposes the six standard source types with their wire values', function (): void {
    expect(array_column(SourceType::cases(), 'value'))->toBe([
        'openapi',
        'arazzo',
        'asyncapi',
        'wsdl',
        'protobuf',
        'graphql',
    ]);
});

it('backs each protocol reference source type with its enum value', function (): void {
    expect(SourceType::Wsdl->value)->toBe('wsdl');
    expect(SourceType::Protobuf->value)->toBe('protobuf');
    expect(SourceType::Graphql->value)->toBe('graphql');
});
```

2. Run it — fails (no `Wsdl` case).

```bash
composer run test-contracts -- --filter=SourceTypeTest
```

3. Extend the enum.

`packages/contracts/src/Spec/Enum/SourceType.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
    case Wsdl = 'wsdl';
    case Protobuf = 'protobuf';
    case Graphql = 'graphql';
}
```

4. Fix the now-stale enum error message in the parser (was already out of sync — it says `openapi|arazzo` while `asyncapi` also parses). In `packages/document/src/Parser/Parser.php`, `parseSourceDescription`:

```php
        $enum = SourceType::tryFrom($type)
            ?? throw ParserException::invalidEnum(
                $ctx->push('type'),
                'openapi|arazzo|asyncapi|wsdl|protobuf|graphql',
                $type,
            );
```

5. Re-run tests, add a parser round-trip assertion for a wsdl source description inside `packages/document/tests/Parser/ParserTest.php`:

```php
it('parses wsdl source descriptions', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions:
      - name: legacy
        type: wsdl
        url: ./service.wsdl
    workflows:
      - workflowId: w
        steps: []
    YAML;

    $document = (new Parser())->parse(new RawDocument(
        (new SymfonyYamlDecoder())->decode($yaml),
        'memory://wsdl',
        Format::Yaml,
    ));

    expect($document->sourceDescriptions[0]->type)->toBe(SourceType::Wsdl);
});
```

Verify the new test's imports are present (`SourceType`, `Format`, `RawDocument`, `SymfonyYamlDecoder`, `Parser` — all already imported in `ParserTest.php`).

6. Gates + commit:

```bash
composer run test-document -- --filter=ParserTest
composer run test-contracts
composer run analyse-contracts
composer run analyse-document
git add -A packages/contracts/src/Spec/Enum/SourceType.php packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php packages/document/src/Parser/Parser.php packages/document/tests/Parser/ParserTest.php
git commit -m "feat(contracts): extend SourceType with wsdl/protobuf/graphql (D3a)"
```

---

## Task D3b — contracts model for 1.2 step variants

**Prereqs:** Phase A landed (the decomposed model already carries these on the axes: `StepTarget` has `operationId`, `operationPath`, `workflowId`, `action`, `channelPath`, `correlationId`, `operationName`, `rpcMethod: ?RpcProtocol`, `graphqlOperation: ?string`, `interaction: ?Interaction`; `StepFlow` has `onTimeout`, `onCancel`, `timeoutDuration`; `Interaction` has `expectedPayload` + `timeout`; `RpcProtocol` enum exists with `grpc`/`grpc-web`/`twirp`/`connect`).

**This task revises one Phase A field.** `StepTarget::$graphqlOperation` is changed from `?string` to `?GraphQlOperation`. Rationale (documented deviation, kept in this commit message): PR #567 defines a GraphQL operation *object* (`schema`, `operation`, `extensions`, `extensionsSelector`); the D4 rule requires those sub-fields and cannot operate on a plain string. The A7-shipped string form is a simplification that D must revise before any rule can enforce the proposal.

**Files:**
- Create `packages/contracts/src/Spec/GraphQlOperation.php`
- Create `packages/contracts/src/Spec/Enum/InteractionMode.php`
- Edit `packages/contracts/src/Spec/Interaction.php` (append authoring fields)
- Edit `packages/contracts/src/Spec/StepTarget.php` (`graphqlOperation` type + `StepTarget::graphql()` factory signature)
- Edit `packages/contracts/src/Spec/StepFactory.php` (`graphql` factory signature)
- Edit `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php` (graphql test uses GraphQlOperation)
- Create `packages/contracts/tests/Contracts/Spec/GraphQlOperationTest.php`
- Create `packages/contracts/tests/Contracts/Spec/Enum/InteractionModeTest.php`
- Edit `packages/contracts/tests/Contracts/Spec/InteractionTest.php` (extend for #568 fields)

### Steps

1. Write the model tests first.

`packages/contracts/tests/Contracts/Spec/GraphQlOperationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;

it('models a GraphQL operation object', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensions: ['contentType' => 'application/json'],
    );

    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
    expect($op->extensionsSelector)->toBeNull();
});

it('accepts an extensionsSelector form', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/operations/0', ExpressionType::JsonPointer),
        extensions: null,
    );

    expect($op->extensionsSelector?->type)->toBe(ExpressionType::JsonPointer);
});
```

`packages/contracts/tests/Contracts/Spec/Enum/InteractionModeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;

it('exposes the three interaction modes', function (): void {
    expect(InteractionMode::Form->value)->toBe('form');
    expect(InteractionMode::Redirect->value)->toBe('redirect');
    expect(InteractionMode::Acknowledge->value)->toBe('acknowledge');
});
```

Extend `packages/contracts/tests/Contracts/Spec/InteractionTest.php` (append to the existing file):

```php
it('carries the interaction-step authoring fields', function (): void {
    $interaction = new Interaction(
        expectedPayload: ['ok'],
        timeout: '5s',
        prompt: 'Confirm the transfer',
        context: ['account' => '1234'],
        inputSchema: ['type' => 'object'],
        mode: InteractionMode::Form,
        redirectOperationId: 'confirm',
        parameters: [new Parameter('token', 'in')],
    );

    expect($interaction->prompt)->toBe('Confirm the transfer');
    expect($interaction->context)->toBe(['account' => '1234']);
    expect($interaction->inputSchema)->toBe(['type' => 'object']);
    expect($interaction->mode)->toBe(InteractionMode::Form);
    expect($interaction->redirectOperationId)->toBe('confirm');
    expect($interaction->parameters)->toHaveCount(1);
});
```

2. Run — fails to compile. Implement the model.

`packages/contracts/src/Spec/GraphQlOperation.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * The graphql-operation object (PR #567): a GraphQL step targets a whole
 * source and names an operation, optionally with extension headers.
 */
final readonly class GraphQlOperation
{
    /**
     * @param  array<string,mixed>|null  $extensions
     */
    public function __construct(
        public ?string $schema = null,
        public ?string $operation = null,
        public ?array $extensions = null,
        public ?Selector $extensionsSelector = null,
    ) {}
}
```

`packages/contracts/src/Spec/Enum/InteractionMode.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum InteractionMode: string
{
    case Form = 'form';
    case Redirect = 'redirect';
    case Acknowledge = 'acknowledge';
}
```

`packages/contracts/src/Spec/Interaction.php` (append after `timeout`; **keep** `expectedPayload` and `timeout` for BC with A7):

```php
final readonly class Interaction
{
    /**
     * @param  array<string,mixed>|null  $context
     * @param  array<string,mixed>|null  $inputSchema
     * @param  list<Parameter|Reusable>  $parameters
     */
    public function __construct(
        public mixed $expectedPayload = null,
        public ?string $timeout = null,
        public ?string $prompt = null,
        public ?array $context = null,
        public ?array $inputSchema = null,
        public ?InteractionMode $mode = null,
        public ?string $redirectOperationId = null,
        public ?string $redirectOperationPath = null,
        public ?string $redirectUrl = null,
        public array $parameters = [],
    ) {}
}
```

`packages/contracts/src/Spec/StepTarget.php` — change the trailing `graphqlOperation` field from `?string` to `?GraphQlOperation` and update the `StepTarget::graphql()` factory:

```php
        public ?GraphQlOperation $graphqlOperation = null,
```

and update `StepTarget::graphql()`:
```php
    public static function graphql(GraphQlOperation $graphqlOperation): self
    {
        return new self(graphqlOperation: $graphqlOperation);
    }
```

Also update `StepFactory::graphql()` to accept `GraphQlOperation`:
```php
    public static function graphql(string $stepId, ?string $description, StepFlow $flow, StepIo $io, GraphQlOperation $graphqlOperation): Step
    {
        return new Step($stepId, $description, StepTarget::graphql($graphqlOperation), $flow, $io);
    }
```

3. Update the Phase A StepFactory test that used the string form. In `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php`, replace the `graphqlOperation: 'query Load'` argument with:

```php
            graphqlOperation: new GraphQlOperation(operation: 'query Load'),
```

and update the assertion to:

```php
    expect($step->target->graphqlOperation)->toBeInstanceOf(GraphQlOperation::class);
```

Add `use Alama\Arazzo\Contracts\Spec\GraphQlOperation;` to the test imports.

4. Run the gates + commit:

```bash
composer run test-contracts
composer run analyse-contracts
composer run test-document
git add -A packages/contracts/src/Spec packages/contracts/tests/Contracts/Spec
git commit -m "feat(contracts): model GraphQL operations and interaction-step fields (D3b)"
```

---

## Task D3c — two-axis `ResolvedOperation`

**Prereqs:** D3b landed (`GraphQlOperation` exists; `Interaction` extended).

**Files:**
- Edit `packages/document/src/ResolvedOperation.php`
- Create `packages/document/tests/ResolvedOperationTest.php`

### Steps

1. Write the failing test.

`packages/document/tests/ResolvedOperationTest.php`. Reuse the minimal OpenAPI fixture built inline (mirrors `OpenApiOperationResolverVersionTest`):

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

function makeResolvedOperation(?RpcProtocol $rpcProtocol = null, ?string $operationName = null, ?string $rpcMethod = null, ?string $graphqlOperation = null, ?Interaction $interaction = null): ResolvedOperation
{
    $source = new SourceDescription('api', '/u', SourceType::Wsdl);
    $normalized = new NormalizedOpenApiOperation(
        path: '/pets',
        method: 'get',
        resolvedServerUrl: 'https://example.test',
        pathParameters: [],
        queryParameters: [],
        headerParameters: [],
        cookieParameters: [],
        requestBodies: [],
        responses: [],
    );

    return new ResolvedOperation(
        source: $source,
        normalized: $normalized,
        openApi: new OpenApi([]),
        rawDocument: [],
        cebeOperation: new Operation([]),
        rpcProtocol: $rpcProtocol,
        operationName: $operationName,
        rpcMethod: $rpcMethod,
        graphqlOperation: $graphqlOperation,
        interaction: $interaction,
    );
}

it('keeps constructing with positional 5-arity for runner BC', function (): void {
    $source = new SourceDescription('api', '/u', SourceType::Openapi);
    $op = new ResolvedOperation(
        $source,
        new NormalizedOpenApiOperation('/', 'get', 'https://example.test', [], [], [], [], [], []),
        new OpenApi([]),
        [],
        new Operation([]),
    );

    expect($op->sourceType())->toBe(SourceType::Openapi);
    expect($op->binding())->toBe('http');
    expect($op->rpcProtocol)->toBeNull();
});

it('derives sourceType from the source description', function (): void {
    expect(makeResolvedOperation()->sourceType())->toBe(SourceType::Wsdl);
});

it('derives binding from rpcProtocol, then source type', function (): void {
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Grpc)->binding())->toBe('grpc');
    expect(makeResolvedOperation()->binding())->toBe('soap');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::GrpcWeb)->binding())->toBe('grpc-web');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Twirp)->binding())->toBe('twirp');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Connect)->binding())->toBe('connect');
});

it('carries the protocol-specific operation reference fields', function (): void {
    $op = makeResolvedOperation(
        rpcProtocol: RpcProtocol::Grpc,
        operationName: 'com.acme.PetService/GetPet',
        rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
        graphqlOperation: '$sourceDescriptions.gql.query GetPet',
        interaction: new Interaction(prompt: 'Proceed'),
    );

    expect($op->operationName)->toBe('com.acme.PetService/GetPet');
    expect($op->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($op->graphqlOperation)->toBe('$sourceDescriptions.gql.query GetPet');
    expect($op->interaction?->prompt)->toBe('Proceed');
});
```

2. Run — must fail to compile (`rpcProtocol` etc. do not exist).

```bash
composer run test-document -- --filter=ResolvedOperation
```

3. Implement.

`packages/document/src/ResolvedOperation.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

/**
 * A fully resolved single-operation view over one described source.
 *
 * Two-axis: sourceType() (openapi/arazzo/asyncapi/wsdl/protobuf/graphql) x
 * optional rpcProtocol() (grpc/grpc-web/twirp/connect). binding() is derived.
 *
 * Relocated to alama/arazzo-protocol-http in F1.
 */
class ResolvedOperation
{
    /**
     * @param  array<string, mixed>  $rawDocument
     */
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
        public readonly OpenApi $openApi,
        public readonly array $rawDocument,
        public readonly Operation $cebeOperation,
        public readonly ?RpcProtocol $rpcProtocol = null,
        public readonly ?string $operationName = null,
        public readonly ?string $rpcMethod = null,
        public readonly ?string $graphqlOperation = null,
        public readonly ?Interaction $interaction = null,
    ) {}

    public function sourceType(): SourceType
    {
        return $this->source->type;
    }

    public function binding(): string
    {
        return $this->rpcProtocol?->value ?? match ($this->source->type) {
            SourceType::Openapi => 'http',
            SourceType::Arazzo => 'arazzo',
            SourceType::Asyncapi => 'asyncapi',
            SourceType::Wsdl => 'soap',
            SourceType::Protobuf => 'protobuf',
            SourceType::Graphql => 'graphql',
        };
    }
}
```

4. Re-run, run the runner suite as a BC check (these construct with 5 positional args), then commit.

```bash
composer run test-document -- --filter=ResolvedOperation
composer run test-runner
composer run analyse-document
git add packages/document/src/ResolvedOperation.php packages/document/tests/ResolvedOperationTest.php
git commit -m "feat(document): two-axis ResolvedOperation with derived binding (D3c)"
```

---

## Task D2 — `OpenApiSourceNormalizer` (port implementation)

**Prereqs:** D1 (registry), D3c (`ResolvedOperation` two-axis). The zero-arg per-version normalizers, `OpenApiDocumentLoader`, and `OpenApiVersionDetector` are used as-is; the internal `OpenApiNormalizerInterface` is **not** changed.

**Files:**
- Create `packages/sources/src/Normalizer/OpenApiSourceNormalizer.php`
- Create `packages/sources/tests/Normalizer/OpenApiSourceNormalizerTest.php`

### Steps

1. Write the failing test (mirrors the `LocalFetcher` + temp-file pattern from `OpenApiOperationResolverVersionTest`).

`packages/sources/tests/Normalizer/OpenApiSourceNormalizerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;

function makeOpenApiSourceNormalizer(): OpenApiSourceNormalizer
{
    $resolver = new DefaultSourceResolver(fetchers: ['file' => new LocalFetcher()]);

    return new OpenApiSourceNormalizer(
        new OpenApiDocumentLoader($resolver),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );
}

it('does not advertise support for non-openapi sources', function (): void {
    $normalizer = makeOpenApiSourceNormalizer();

    expect($normalizer->name())->toBe('openapi');
    expect($normalizer->priority())->toBe(0);
    expect($normalizer->supports(SourceType::Openapi))->toBeTrue();
    expect($normalizer->supports(SourceType::Asyncapi))->toBeFalse();
    expect($normalizer->supports(SourceType::Wsdl))->toBeFalse();
});

it('builds an operation index keyed by qualified name and json pointer', function (): void {
    $openapiJson = json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => [
            '/pets' => [
                'get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['operationId' => 'createPet', 'responses' => ['201' => ['description' => 'Created']]],
            ],
        ],
    ]);

    $file = tempnam(sys_get_temp_dir(), 'oas_').'.json';
    file_put_contents($file, $openapiJson);

    try {
        $source = new SourceDescription('pets-api', $file, SourceType::Openapi);
        $index = makeOpenApiSourceNormalizer()->normalize($source, (string) $openapiJson);

        $qualified = $index['$sourceDescriptions.pets-api.listPets'] ?? null;
        expect($qualified)->toBeInstanceOf(ResolvedOperation::class);
        expect($qualified->sourceType())->toBe(SourceType::Openapi);
        expect($qualified->binding())->toBe('http');
        expect($qualified->normalized->method)->toBe('get');

        $pointer = $index['#/paths/pets/post'] ?? null;
        expect($pointer)->toBeInstanceOf(ResolvedOperation::class);
        expect($pointer->normalized->method)->toBe('post');
        expect($index['createPet'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});

it('rejects Swagger 2.0 content instead of mis-routing it', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'swagger_').'.json';
    file_put_contents($file, json_encode(['swagger' => '2.0', 'info' => ['title' => 'L', 'version' => '1'], 'paths' => []]));

    try {
        $source = new SourceDescription('legacy', $file, SourceType::Openapi);
        makeOpenApiSourceNormalizer()->normalize($source, (string) file_get_contents($file));
    } finally {
        @unlink($file);
    }
})->throws(UnsupportedSourceVersionException::class, 'declares version \'2.0\', which is not supported');
```

2. Run — must fail to load.

```bash
composer run test-document -- --filter=OpenApiSourceNormalizer
```

3. Implement the composed normalizer. It needs the sniffing helpers already used by `DefaultSourceResolver`; reuse `SymfonyYamlDecoder`/`NativeJsonDecoder` for decoding:

`packages/sources/src/Normalizer/OpenApiSourceNormalizer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;

/**
 * Port (D2): turns a described source + raw content into an operation index.
 *
 * Index keys mirror OpenApiOperationResolver's accepted references:
 *   - plain operationId
 *   - "$sourceDescriptions.<name>.<operationId>"
 *   - "#/paths/<escaped-path>/<method>"
 *
 * Values are two-axis ResolvedOperation objects (mixed-compatible with the
 * contracts array signature). Privileged in `document` during D; relocated
 * to alama/arazzo-protocol-http in F1.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OpenApiSourceNormalizer implements SourceNormalizerInterface
{
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(
        private readonly OpenApiDocumentLoader $loader,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly OpenApi30Normalizer $normalizer30,
        private readonly OpenApi31Normalizer $normalizer31,
    ) {}

    public function name(): string
    {
        return 'openapi';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Openapi;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        $decoded = $this->decode($rawContent);
        $version = $this->versionDetector->detect($decoded);
        if ($version !== '3.0' && $version !== '3.1') {
            throw UnsupportedSourceVersionException::forVersion($version, $source->name);
        }

        $openApi = $this->loader->load($source, getcwd() ?: '');
        if ($openApi === null) {
            return [];
        }

        $normalizer = $version === '3.1' ? $this->normalizer31 : $this->normalizer30;
        $index = [];
        foreach ($openApi->paths as $path => $pathItem) {
            foreach (self::METHODS as $method) {
                if (!isset($pathItem->{$method}) || !$pathItem->{$method} instanceof \cebe\openapi\spec\Operation) {
                    continue;
                }

                $operation = $pathItem->{$method};
                $resolved = new ResolvedOperation(
                    source: $source,
                    normalized: $normalizer->normalize($decoded, (string) $path, $method),
                    openApi: $openApi,
                    rawDocument: $decoded,
                    cebeOperation: $operation,
                );

                if ($operation->operationId !== null) {
                    $index[$operation->operationId] = $resolved;
                    $index['$sourceDescriptions.'.$source->name.'.'.$operation->operationId] = $resolved;
                }
                $pointer = '#/paths/'.str_replace(['~', '/'], ['~0', '~1'], (string) $path).'/'.$method;
                $index[$pointer] = $resolved;
            }
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $rawContent): array
    {
        $trimmed = ltrim($rawContent);
        $decoded = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? (new NativeJsonDecoder())->decode($rawContent)
            : (new SymfonyYamlDecoder())->decode($rawContent);

        if (!is_array($decoded)) {
            throw UnsupportedSourceVersionException::forVersion('unknown', '');
        }

        return $decoded;
    }
}
```

Adjust imports at the top when implementing (`NativeJsonDecoder`, `SymfonyYamlDecoder` come from `Alama\Arazzo\Document\Parser\Decoders`). If `OpenApi30Normalizer::normalize` requires arguments that differ, keep whatever the current internal interface declares — the call above already matches the verified `normalize(array $document, string $path, string $method): NormalizedOpenApiOperation`.

4. Run + gates + commit:

```bash
composer run test-document -- --filter=OpenApiSourceNormalizer
composer run analyse-document
git add packages/sources/src/Normalizer/OpenApiSourceNormalizer.php packages/sources/tests/Normalizer/OpenApiSourceNormalizerTest.php
git commit -m "feat(document): OpenApiSourceNormalizer port implementing SourceNormalizerInterface (D2)"
```

---

## Task D4a — parser: 1.2 step fields + `components.interactions`

**Prereqs:** D3b landed; Phase A landed (Step already has the appended fields; `parseStep` in `Parser.php` currently ends its `new Step(...)` at `timeout:`).

**Files:**
- Edit `packages/document/src/Parser/Parser.php` (`parseStep`, new `parseGraphQlOperation` + `parseInteraction` helpers, `parseComponents` interactions map)
- Edit `packages/contracts/src/Spec/Components.php` (append `interactions` array)
- Edit `packages/core/tests/Support/Fx.php` (`Fx::step` gains the new named params; `Fx::doc` keeps working)
- Create `packages/document/tests/Parser/ProtocolStepFieldsTest.php`

### Steps

1. Write the failing parser test.

`packages/document/tests/Parser/ProtocolStepFieldsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Parser;

$decode = function (string $yaml): RawDocument {
    return new RawDocument((new SymfonyYamlDecoder())->decode($yaml), 'memory://protocol', Format::Yaml);
};

it('parses wsdl-step operationName', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: soap, type: wsdl, url: ./service.wsdl }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            operationName: GetPet
    YAML));

    expect($document->workflows[0]->steps[0]->target->operationName)->toBe('GetPet');
    expect($document->workflows[0]->steps[0]->target->operationId)->toBeNull();
});

it('parses rpc-step rpcMethod and rpcProtocol', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: proto, type: protobuf, url: ./svc.proto }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            rpcMethod: $sourceDescriptions.proto.com.acme.PetService/GetPet
            rpcProtocol: grpc
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($step->target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('parses graphql-step graphqlOperation object', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: gql, type: graphql, url: ./schema.graphql }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            graphqlOperation:
              schema: $sourceDescriptions.gql
              operation: "query GetToken { token }"
              extensions:
                contentType: application/json
    YAML));

    $op = $document->workflows[0]->steps[0]->target->graphqlOperation;
    expect($op)->toBeInstanceOf(GraphQlOperation::class);
    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
});

it('parses interaction-step interaction and components.interactions', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions: []
    components:
      interactions:
        confirmation:
          prompt: Confirm
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            interaction:
              prompt: Confirm transfer
              context: { account: "1234" }
              inputSchema: { type: object }
              mode: redirect
              redirect:
                operationId: confirm
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->interaction)->not->toBeNull();
    expect($step->target->interaction->prompt)->toBe('Confirm transfer');
    expect($step->target->interaction->mode)->toBe(InteractionMode::Redirect);
    expect($step->target->interaction->redirectOperationId)->toBe('confirm');
    expect($step->target->interaction->inputSchema)->toBe(['type' => 'object']);
    expect($document->components->interactions)->toHaveKey('confirmation');
    expect($document->components->interactions['confirmation']->prompt)->toBe('Confirm');
});
```

2. Run — fails (Step has no `operationName` parse; `components` has no `interactions`).

```bash
composer run test-document -- --filter=ProtocolStepFieldsTest
```

3. Contracts first: `packages/contracts/src/Spec/Components.php` gains the interactions map (append):

```php
    /**
     * @param  array<string, mixed>   $inputs
     * @param  array<string, Parameter> $parameters
     * @param  array<string, SuccessAction> $successActions
     * @param  array<string, FailureAction> $failureActions
     * @param  array<string, Interaction>    $interactions
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $successActions,
        public array $failureActions,
        public array $interactions = [],
    ) {}
```

4. Parser edits. Add the four D-only reads to `parseStep` (after the `$idempotencyHeader`/`timeout` reads, before `return new Step(...)`):

```php
        $operationName = $this->optionalString($obj, 'operationName', $ctx);
        $rpcMethod = $this->optionalString($obj, 'rpcMethod', $ctx);

        $rpcProtocol = null;
        $rawRpcProtocol = $this->optionalString($obj, 'rpcProtocol', $ctx);
        if ($rawRpcProtocol !== null) {
            $rpcProtocol = RpcProtocol::tryFrom($rawRpcProtocol)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('rpcProtocol'),
                    'grpc|grpc-web|twirp|connect',
                    $rawRpcProtocol,
                );
        }

        $graphqlOperation = null;
        if (array_key_exists('graphqlOperation', $obj) && $obj['graphqlOperation'] !== null) {
            $graphqlOperation = $this->parseGraphQlOperation($obj['graphqlOperation'], $ctx->push('graphqlOperation'));
        }

        $interaction = null;
        if (array_key_exists('interaction', $obj) && $obj['interaction'] !== null) {
            $interaction = $this->parseInteraction($obj['interaction'], $ctx->push('interaction'));
        }
```

Do **not** touch `onTimeout`/`onCancel`/`timeoutDuration` — those are Phase A's parser concern and land with A7.

Extend the `$target` match from A7 Step 28 (which already handles `workflow`/`async`/`http`/bare) with the four protocol variants, appending after the http arm and before `default`:

```php
            $rpcMethod !== null && $rpcProtocol !== null => StepTarget::rpc($rpcMethod, $rpcProtocol),
            $graphqlOperation !== null => StepTarget::graphql($graphqlOperation),
            $operationName !== null => StepTarget::wsdl($operationName),
            $interaction !== null => StepTarget::interaction($interaction),
            default => new StepTarget(),
```

`$graphqlOperation` here is the `GraphQlOperation` object from `parseGraphQlOperation` (matching the D3b-revised `StepTarget::$graphqlOperation` type); `$rpcMethod`/`$operationName`/`$interaction` flow through `StepTarget::rpc`/`::wsdl`/`::interaction` unchanged. The existing flow/io construction (`new StepFlow(...)`, `new StepIo(...)`) stays untouched from A7.

Add the two helpers near the other `parse*` object helpers (after `parseFailureAction`). `Selector` and `ExpressionType` are already imported by `Parser` (the outputs parser at ~line 420 builds `Selector` inline the same way) — reuse that pattern rather than adding a new helper:

```php
    protected function parseGraphQlOperation(mixed $node, ParseContext $ctx): GraphQlOperation
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $extensionsSelector = null;
        if (array_key_exists('extensionsSelector', $obj) && $obj['extensionsSelector'] !== null) {
            $rawSelector = $obj['extensionsSelector'];
            $selCtx = $ctx->push('extensionsSelector');
            $typeStr = $this->requireString($rawSelector, 'type', $selCtx);
            $type = ExpressionType::tryFrom($typeStr)
                ?? throw ParserException::invalidEnum(
                    $selCtx->push('type'), 'simple|regex|jsonpath|xpath', $typeStr,
                );
            $extensionsSelector = new Selector(
                context: $this->optionalString($rawSelector, 'context', $selCtx),
                selector: $this->requireString($rawSelector, 'selector', $selCtx),
                type: $type,
                version: $this->optionalString($rawSelector, 'version', $selCtx),
            );
        }

        return new GraphQlOperation(
            schema: $this->optionalString($obj, 'schema', $ctx),
            operation: $this->optionalString($obj, 'operation', $ctx),
            extensions: $this->optionalArray($obj, 'extensions', $ctx),
            extensionsSelector: $extensionsSelector,
        );
    }

    protected function parseInteraction(mixed $node, ParseContext $ctx): Interaction
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $mode = null;
        if (($rawMode = $this->optionalString($obj, 'mode', $ctx)) !== null) {
            $mode = InteractionMode::tryFrom($rawMode)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('mode'), 'form|redirect|acknowledge', $rawMode,
                );
        }

        $redirect = null;
        if (array_key_exists('redirect', $obj) && $obj['redirect'] !== null) {
            $redirect = $this->requireObjectMap($obj['redirect'], $ctx->push('redirect'));
        }

        $parameters = [];
        if (($p = $this->optionalList($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        return new Interaction(
            expectedPayload: $obj['expectedPayload'] ?? null,
            timeout: $this->optionalString($obj, 'timeout', $ctx),
            prompt: $this->optionalString($obj, 'prompt', $ctx),
            context: $this->optionalArray($obj, 'context', $ctx),
            inputSchema: $this->optionalArray($obj, 'inputSchema', $ctx),
            mode: $mode,
            redirectOperationId: is_array($redirect) ? $this->optionalString($redirect, 'operationId', $ctx->push('redirect')) : null,
            redirectOperationPath: is_array($redirect) ? $this->optionalString($redirect, 'operationPath', $ctx->push('redirect')) : null,
            redirectUrl: is_array($redirect) ? $this->optionalString($redirect, 'url', $ctx->push('redirect')) : null,
            parameters: $parameters,
        );
    }
```

Update `parseComponents` to read the map and pass it to `Components`:

```php
        $interactions = [];
        if (($i = $this->optionalArray($obj, 'interactions', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                $parsed = $this->parseInteraction($v, $ctx->push('interactions')->push((string) $k));
                if ($parsed instanceof Reusable) {
                    throw ParserException::wrongType(
                        $ctx->push('interactions')->push((string) $k),
                        'interaction (not a reusable ref)', $v,
                    );
                }
                $interactions[(string) $k] = $parsed;
            }
        }

        return new Components($inputs, $parameters, $successActions, $failureActions, $interactions);
```

and the null-node guard above it becomes `return new Components([], [], [], [], []);`.

5. Extend `Fx::step` (`packages/core/tests/Support/Fx.php`) so rule tests can build 1.2 steps without raw Step constructors:

```php
    public static function step(
        string $id = 's',
        ?string $opId = 'op',
        ?string $opPath = null,
        ?string $wfId = null,
        array $params = [],
        ?RequestBody $body = null,
        array $crit = [],
        array $onSuccess = [],
        array $onFailure = [],
        array $outputs = [],
        ?string $operationName = null,
        ?string $rpcMethod = null,
        ?RpcProtocol $rpcProtocol = null,
        ?GraphQlOperation $graphqlOperation = null,
        ?Interaction $interaction = null,
    ): Step {
        $flow = new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure);
        $io = new StepIo(parameters: $params, requestBody: $body, successCriteria: $crit, outputs: $outputs);

        return match (true) {
            $wfId !== null => StepFactory::workflow($id, null, $flow, $io, $wfId),
            $opId !== null || $opPath !== null => StepFactory::http($id, null, $flow, $io, operationId: $opId, operationPath: $opPath),
            $rpcMethod !== null && $rpcProtocol !== null => StepFactory::rpc($id, null, $flow, $io, $rpcMethod, $rpcProtocol),
            $graphqlOperation !== null => StepFactory::graphql($id, null, $flow, $io, $graphqlOperation),
            $operationName !== null => StepFactory::wsdl($id, null, $flow, $io, $operationName),
            $interaction !== null => StepFactory::interaction($id, null, $flow, $io, $interaction),
            default => new Step($id, null, new StepTarget(), $flow, $io),
        };
    }
```

6. Run + gates + commit:

```bash
composer run test-document -- --filter=ProtocolStepFieldsTest
composer run test-document
composer run analyse-document
composer run analyse-contracts
git add -A
git commit -m "feat(document): parse 1.2 step variants and components.interactions (D4a)"
```

If the parser's `new Step(...)` currently lacks `x-arazzo-timeout-duration`/`operationName` etc. (i.e., Phase A has not actually landed), stop and fix the precondition first.

---

## Task D4b — six-target mutual exclusion in `StepOperationTargetPresentRule`

**Prereqs:** D3b landed.

**Files:**
- Edit `packages/document/src/Validator/Rules/StepOperationTargetPresentRule.php`
- Create `packages/document/tests/Validator/Rules/StepOperationTargetPresentSixTargetTest.php`

### Steps

1. Write the failing test (mirror the `StepOperationIdSourceScopedRuleTest` shape using `Fx`):

`packages/document/tests/Validator/Rules/StepOperationTargetPresentSixTargetTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts each of the six targets individually', function (): void {
    $doc = Fx::wf('w', [
        Fx::step('a', 'op'),
        Fx::step('b', null, '#/paths/pets/get'),
        Fx::step('c', null, null, 'other'),
        Fx::step('d', null, null, null, operationName: 'GetPet'),
        Fx::step('e', null, null, null, rpcMethod: '$sourceDescriptions.proto.M/Get', rpcProtocol: RpcProtocol::Grpc),
        Fx::step('f', null, null, null, graphqlOperation: new GraphQlOperation(operation: 'query Q')),
        Fx::step('g', null, null, null, interaction: new Interaction(prompt: 'Go')),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('flags steps that set an http target alongside a protocol target', function (): void {
    $doc = Fx::wf('w', [
        Fx::step('a', 'op', null, null, operationName: 'GetPet'),
        Fx::step('b', 'op', null, null, rpcMethod: '$sourceDescriptions.proto.M/Get'),
        Fx::step('c', null, null, 'other', interaction: new Interaction(prompt: 'Go')),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(3);
    expect($ec->errors()[0]->code)->toBe('step.operation_target_present');
});

it('still flags steps with no target at all', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('ghost')])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});
```

2. Run — first test fails (the six-target rule does not exist in `StepOperationTargetPresentRule`'s logic).

```bash
composer run test-document -- --filter=StepOperationTargetPresentSixTargetTest
```

3. Implement. Replace the `check` in `StepOperationTargetPresentRule` so the exactly-one scan covers all six target fields, keeping the existing message and path style:

```php
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $fields = [
            'operationName',
            'rpcMethod',
            'interaction',
            'operationId',
            'operationPath',
            'workflowId',
        ];
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $set = [];
                foreach ($fields as $field) {
                    if ($s->target->{$field} !== null) {
                        $set[] = $field;
                    }
                }
                if (count($set) !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId, "
                        .'operationName, rpcMethod, interaction (got '.count($set).').',
                        "/workflows/{$i}/steps/{$j}",
                    );
                }
            }
        }
    }
```

Keep the existing `code()` method and `@internal` docblock untouched; only `check` changes.

4. Run + commit:

```bash
composer run test-document -- --filter=StepOperationTargetPresent
composer run analyse-document
git add -A packages/document/src/Validator/Rules/StepOperationTargetPresentRule.php packages/document/tests/Validator/Rules
git commit -m "feat(document): six-target mutual exclusion for steps (D4b)"
```

---

## Task D4c — `WsdlStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/WsdlStepRule.php`
- Create `packages/document/tests/Validator/Rules/WsdlStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.wsdl_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/WsdlStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WsdlStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a wsdl step that names its operation on a wsdl source', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects operationPath on a wsdl step', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', 'op', '#/paths/pets/get', null, operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('requires a wsdl source when operationName is set', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('http', '/api', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('ignores steps without wsdl target fields', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});
```

2. Run — must fail (rule not found).

```bash
composer run test-document -- --filter=WsdlStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/WsdlStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * wsdl-step rules (PR #533):
 *  - an operationName may only be combined with WSDL sources
 *  - operationPath is prohibited on WSDL steps (prose-only per spec; the
 *    meta-schema currently permits it → characterized as a known false positive)
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlStepRule implements Rule
{
    public function code(): string
    {
        return 'step.wsdl_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $hasWsdlSource = false;
        foreach ($doc->sourceDescriptions as $source) {
            if ($source->type === SourceType::Wsdl) {
                $hasWsdlSource = true;
                break;
            }
        }

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationName === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if (!$hasWsdlSource) {
                    $errors->error(
                        'step.wsdl_step',
                        "Step '{$s->stepId}' declares operationName but the document has no wsdl-type sourceDescription.",
                        $path,
                    );
                }

                if ($s->target->operationPath !== null) {
                    $errors->error(
                        'step.wsdl_step',
                        "Step '{$s->stepId}' is a WSDL step and MUST NOT use operationPath.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default` — add the import `use Alama\Arazzo\Document\Validator\Rules\WsdlStepRule;` and append `new WsdlStepRule(),` to the rules list (alphabetical position: after `WorkflowUniqueIdRule`).

5. Existing gate: run the full document suite (the rule only fires when new fields are present, so no existing fixture breaks).

```bash
composer run test-document -- --filter=WsdlStepRule
composer run analyse-document
git add -A packages/document/src/Validator/Rules/WsdlStepRule.php packages/document/tests/Validator/Rules/WsdlStepRuleTest.php packages/document/src/Validator/RuleSet.php
git commit -m "feat(document): wsdl-step validation rule (D4c)"
```

---

## Task D4d — `RpcStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/RpcStepRule.php`
- Create `packages/document/tests/Validator/Rules/RpcStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.rpc_step`.

**Protocol content-type constraints (from PR #556):**

| protocol | allowed request content types |
|----------|------------------------------|
| grpc | `application/grpc` |
| grpc-web | `application/grpc-web+proto`, `application/grpc-web-text` |
| twirp | `application/protobuf`, `application/json` |
| connect | `application/json`, `application/proto`, `application/connect+json`, `application/connect+proto` |

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/RpcStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\RpcStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts qualified rpcMethod with matching protocol and content type', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g', rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet', rpcProtocol: RpcProtocol::Grpc),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects malformed rpcMethod references', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', rpcMethod: 'GetPet', rpcProtocol: RpcProtocol::Grpc),
        Fx::step('b', rpcMethod: '$sourceDescriptions.missing.pkg/M', rpcProtocol: RpcProtocol::Grpc),
    ])]);
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('rejects cross-protocol request content types', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g',
                rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
                rpcProtocol: RpcProtocol::Twirp,
                body: new RequestBody('application/grpc', []),
            ),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.rpc_step');
});
```

(`Fx::step` gains the `body:` named parameter? It already has `$body` — pass `body:`.)

2. Run — fails.

```bash
composer run test-document -- --filter=RpcStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/RpcStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * rpc-step rules (PR #556):
 *  - rpcMethod must be source-qualified: $sourceDescriptions.<name>.<service>/<method>
 *  - the named source must exist in the document
 *  - rpcProtocol must be set whenever rpcMethod is set
 *  - request content-type must be allowed for the declared protocol
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcStepRule implements Rule
{
    private const RPC_METHOD_PATTERN = '/^\$sourceDescriptions\.[A-Za-z0-9_-]+(?:\.[A-Za-z_][A-Za-z0-9_]*)+\\/[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<string, list<string>> */
    private const CONTENT_TYPES = [
        'grpc' => ['application/grpc'],
        'grpc-web' => ['application/grpc-web+proto', 'application/grpc-web-text'],
        'twirp' => ['application/protobuf', 'application/json'],
        'connect' => ['application/json', 'application/proto', 'application/connect+json', 'application/connect+proto'],
    ];

    public function code(): string
    {
        return 'step.rpc_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->rpcMethod === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if (preg_match(self::RPC_METHOD_PATTERN, $s->target->rpcMethod) !== 1) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' rpcMethod must be source-qualified as "
                        .'$sourceDescriptions.<name>.<service>/<method>.',
                        $path,
                    );

                    continue;
                }

                $sourceName = explode('.', $s->target->rpcMethod)[1];
                if ($s->target->rpcProtocol === null) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod requires rpcProtocol to be set.", $path);
                } elseif (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod references unknown source '{$sourceName}'.", $path);
                } elseif (($type = $s->io->requestBody?->contentType) !== null
                    && !in_array($type, self::CONTENT_TYPES[$s->target->rpcProtocol->value], true)) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' content type '{$type}' is not allowed for {$s->target->rpcProtocol->value} RPC.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default` (import + `new RpcStepRule(),` after `WorkflowUniqueIdRule`).

5. Run + gates + commit:

```bash
composer run test-document -- --filter=RpcStepRule
composer run analyse-document
git add -A
git commit -m "feat(document): rpc-step validation rule (D4d)"
```

---

## Task D4e — `GraphQlStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/GraphQlStepRule.php`
- Create `packages/document/tests/Validator/Rules/GraphQlStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.graphql_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/GraphQlStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\GraphQlStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a whole-source schema with an operation', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step(
            's',
            graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: 'query GetPet'),
        )])],
        sources: [new SourceDescription('gql', '/schema.graphql', SourceType::Graphql)],
    );
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects a graphqlOperation without an operation', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql'),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.graphql_step');
});

it('rejects a partial (operation-path) schema reference', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql.url#/operations/0'),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('rejects extensions together with extensionsSelector', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(
            schema: '$sourceDescriptions.gql',
            operation: 'query GetPet',
            extensions: ['contentType' => 'application/json'],
            extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/extensions', ExpressionType::JsonPointer),
        ),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('ignores non-graphql steps', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});
```

2. Run — fails.

```bash
composer run test-document -- --filter=GraphQlStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/GraphQlStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * graphql-step rules (PR #567):
 *  - schema is a whole-source reference ($sourceDescriptions.<name>)
 *  - operation is required
 *  - extensions and extensionsSelector are mutually exclusive
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class GraphQlStepRule implements Rule
{
    public function code(): string
    {
        return 'step.graphql_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $operation = $s->target->graphqlOperation;
                if ($operation === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if ($operation->schema === null || preg_match('/^\$sourceDescriptions\.[A-Za-z0-9_-]+$/', $operation->schema) !== 1) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' GraphQL schema must be a whole-source reference like \$sourceDescriptions.<name>.",
                        $path,
                    );
                }

                if ($operation->operation === null || trim($operation->operation) === '') {
                    $errors->error('step.graphql_step', "Step '{$s->stepId}' GraphQL operation is required.", $path);
                }

                if ($operation->extensions !== null && $operation->extensionsSelector !== null) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' extensions and extensionsSelector are mutually exclusive.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default`.

5. Run + commit.

---

## Task D4f — `InteractionStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/InteractionStepRule.php`
- Create `packages/document/tests/Validator/Rules/InteractionStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.interaction_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/InteractionStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\InteractionStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a form interaction with a prompt and input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Confirm', inputSchema: ['type' => 'object'], mode: InteractionMode::Form),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('accepts an acknowledge interaction with no input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Proceed', mode: InteractionMode::Acknowledge),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('requires a prompt', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', interaction: new Interaction(inputSchema: ['type' => 'object'], mode: InteractionMode::Form))])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.interaction_step');
});

it('requires an input schema for form and redirect modes', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Form)),
        Fx::step('b', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Redirect, redirectUrl: 'https://x.test')),
    ])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('forbids input schema on acknowledge', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'P', mode: InteractionMode::Acknowledge, inputSchema: ['type' => 'object']),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('requires exactly one redirect target', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', interaction: new Interaction(
            prompt: 'P',
            mode: InteractionMode::Redirect,
            inputSchema: ['type' => 'object'],
            redirectOperationId: 'x',
            redirectUrl: 'https://x.test',
        )),
    ])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});
```

2. Run — fails.

```bash
composer run test-document -- --filter=InteractionStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/InteractionStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * interaction-step rules (PR #568):
 *  - prompt is required
 *  - form/redirect modes require an inputSchema; acknowledge forbids it
 *  - redirect mode requires exactly one redirect target
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class InteractionStepRule implements Rule
{
    public function code(): string
    {
        return 'step.interaction_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $interaction = $s->target->interaction;
                if ($interaction === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                $mode = $interaction->mode ?? InteractionMode::Form;

                if ($interaction->prompt === null) {
                    $errors->error('step.interaction_step', "Step '{$s->stepId}' interaction requires a prompt.", $path);
                }

                if ($mode === InteractionMode::Acknowledge) {
                    if ($interaction->inputSchema !== null) {
                        $errors->error(
                            'step.interaction_step',
                            "Step '{$s->stepId}' acknowledge interaction cannot declare an inputSchema.",
                            $path,
                        );
                    }

                    continue;
                }

                if ($interaction->inputSchema === null) {
                    $errors->error(
                        'step.interaction_step',
                        "Step '{$s->stepId}' {$mode->value} interaction requires an inputSchema.",
                        $path,
                    );
                }

                if ($mode === InteractionMode::Redirect) {
                    $targets = array_filter([
                        $interaction->redirectOperationId,
                        $interaction->redirectOperationPath,
                        $interaction->redirectUrl,
                    ], static fn (mixed $v): bool => $v !== null);
                    if (count($targets) !== 1) {
                        $errors->error(
                            'step.interaction_step',
                            "Step '{$s->stepId}' redirect interaction requires exactly one of "
                            .'redirectOperationId, redirectOperationPath, or redirectUrl.',
                            $path,
                        );
                    }
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default`.

5. Run + gates + commit:

```bash
composer run test-document -- --filter=InteractionStepRule
composer run analyse-document
git add -A
git commit -m "feat(document): interaction-step validation rule (D4f)"
```

---

## Task D5 — wire the registry into `Document`, final gate

**Prereqs:** D1, D2, D3a–c, D4a–f all landed.

**Files:**
- Edit `packages/sources/src/Document.php`
- Create `packages/sources/tests/DocumentFacadeNormalizeTest.php`

### Steps

1. Failing facade test.

`packages/sources/tests/DocumentFacadeNormalizeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Sources\Document;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Tests\Support\Fx;

it('normalizes document sources through the registry', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'facade_').'.json';
    file_put_contents($file, json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => ['/pets' => ['get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]]]],
    ]));

    try {
        $document = new Document();
        $index = $document->normalizeSources(Fx::doc(
            sources: [new SourceDescription('pets-api', $file, SourceType::Openapi)],
        ));

        expect($index['$sourceDescriptions.pets-api.listPets'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});
```

2. Run — fails (`normalizeSources` doesn't exist).

```bash
composer run test-document -- --filter=DocumentFacadeNormalizeTest
```

3. Implement. In `Document.php`:

- new property `private SourceNormalizerRegistryInterface $normalizers;`
- imports `use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;`, `SourceNormalizerRegistry`, `OpenApiSourceNormalizer`, `SourceDocument` already imported.
- constructor adds an optional `?SourceNormalizerRegistryInterface $normalizers = null` param (after `$sources`), then:

```php
        $this->normalizers = $normalizers ?? $this->buildDefaultNormalizers($this->sources, $this->versionDetector);
```

with a private helper:

```php
    private function buildDefaultNormalizers(SourceRegistry $sources, OpenApiVersionDetector $versionDetector): SourceNormalizerRegistryInterface
    {
        $registry = new SourceNormalizerRegistry();
        $registry->register(new OpenApiSourceNormalizer(
            new OpenApiDocumentLoader($sources),
            $versionDetector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ));

        return $registry;
    }
```

- new public method (concrete facade only — **not** on `DocumentInterface`, preserving BC). Note: `SourceResolver` returns a **decoded** `array` (`SourceDocument::$content`), while the port accepts raw text, so `normalizeSources` re-encodes before handing to the normalizer:

```php
    /**
     * Normalize every described source and return an operation index.
     *
     * Keys mirror the resolver's accepted references (operationId,
     * $sourceDescriptions.<name>.<operationId>, and #/paths/.../method).
     *
     * @return array<string, ResolvedOperation>
     */
    public function normalizeSources(ArazzoDocument $document, string $basePath = ''): array
    {
        $index = [];
        foreach ($document->sourceDescriptions as $source) {
            $normalizer = $this->normalizers->get($source->type);
            if ($normalizer === null) {
                continue;
            }
            $resolved = $this->sources->resolve($source, $basePath);
            $rawContent = json_encode($resolved->content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $index += $normalizer->normalize($source, $rawContent, $document);
        }

        return $index;
    }
```

4. Run the full suite, then the final gate.

```bash
composer run test-document -- --filter=DocumentFacadeNormalizeTest
composer run test-document
composer run analyse-document
composer run analyse-contracts
composer run format
make verify
```

5. Commit + mark the spec rows:

```bash
git add -A
git commit -m "feat(document): expose normalizeSources on the facade, typed registry wiring (D5)"
```

6. Update the parent spec's decision table to reflect the completed D rows (D1, D2, D3, D4, D5, D9 privileges note) and mark **#23** as closed/superseded-by-D in the spec's "Related issues" section. This spec edit is part of the phase, not a separate plan.

---

## Final gate

- [ ] `make verify` is green on `main` (docs generation, pint, phpstan all seven packages, all pest suites).
- [ ] Every file listed in D0–D5 exists with the names above.
- [ ] The package boundary holds: `rg -n 'Alama\\Arazzo\\Sources' packages/document/src` and `rg -n 'cebe|GuzzleHttp|Psr\\Http|Psr\\SimpleCache|Softcreatr' packages/document/src` both return nothing.
- [ ] All five D0 guards pass: `vendor/bin/pest packages/document/tests/ArchTest.php packages/sources/tests/ArchTest.php packages/runner/tests/ArchTest.php packages/document/tests/PackageManifestTest.php`.
- [ ] `DocumentInterface` changed only as D0 sanctions: `resolveSource()` and `detectOpenApiVersion()` removed, nothing else altered.
- [ ] `ResolvedOperation` constructs with **2** positional args (`$source`, `$normalized`) and exposes no cebe handle. The three former consumers read `OpenApiOperationHandle`.
- [ ] Phase E's E3 no longer lists `StepOutputExtractor` / `ResponseSchemaValidator`, and Phase F's F1.1 no longer relocates `ResolvedOperation` — both amended as Global constraint 4 requires.
- [ ] `#23` closed as superseded by D in the spec.
- [ ] Commit each task separately with the exact messages above.