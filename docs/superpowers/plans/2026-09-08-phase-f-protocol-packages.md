# Phase F — Protocol packages (F1 HTTP reference slice + F2 SOAP)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the two protocol packages that prove the "vertical slice, zero core edits" pattern (spec D4/D7/D9): **F1 `alama/arazzo-protocol-http`** — the extracted HTTP/OpenAPI + AsyncAPI defaults relocated out of `document`/`runner`, the recipe every other protocol copies — and **F2 `alama/arazzo-protocol-soap`** — the first genuinely *new* protocol implemented against the SPI, zero vendor. After F1, `document` and `runner` ship no HTTP/OpenAPI implementation; after F2, SOAP is `composer require alama/arazzo-protocol-soap` + nothing else in a composed app.

**Scope note (intentional deferral):** the design doc's Phase F spans F1–F4 (HTTP, SOAP, RPC, GraphQL). This plan deliberately covers only F1 and F2 — they establish the vertical-slice recipe that F3 (`alama/arazzo-protocol-rpc`) and F4 (`alama/arazzo-protocol-graphql`) will copy. F3/F4 *execution* packages are out of scope for this plan set; Phase H's RPC/GraphQL tasks (H3/H4) only exercise document-level parsing/validation fixtures (already covered by Phase D's `RpcStepRule`/`GraphQlStepRule`), not executor dispatch. F3/F4 need their own phase plans, written against this F1/F2 pattern, before RPC or GraphQL steps can actually run.

**Architecture:** `arazzo-protocol-http` pulls the embedded OpenAPI normalizers out of `document` and the HTTP/AsyncAPI executors + request compilation + response validation out of `runner` into one package that sits **on top of** core (it requires `contracts` + `expression` + `document` + `runner`). Core never imports it — the two value DTOs `ResolvedOperation` / `NormalizedOpenApiOperation` keep their exact FQCNs (dual PSR-4 map in protocol-http, `Alama\Arazzo\Document\Normalizer\`), everything else relocates into `Alama\Arazzo\Protocol\Http\...` marked `@internal` (spec D9, "Public API impact"). The Guzzle/cebe transports move with the slice; the runner's graph assemblers stop defaulting a Guzzle client and instead receive the protocol's executor/validator/extractor through the existing `AsyncGraphSeams` nullable seams (`packages/runner/src/AsyncGraphSeams.php:38-41`), plus one narrow interface `document` keeps (`OpenApiOperationResolverInterface`) so `PreflightValidator`/`Document` never import protocol types. `arazzo-protocol-soap` mirrors that package shape with DOM + PSR-18 only: `WsdlSourceNormalizer`, `SoapOperationExecutor`, `SoapResponseTransfer` + mapper, xpath `ReplacementTargetResolver`, XSD-flavoured `ResponseValidator`. Both packages ship an optional Laravel service provider that tags plugin services `arazzo.plugins.*` (G1 vocabulary) and a framework-agnostic registrar helper for CLI/umbrella preset wiring.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), PHPStan ^2.0 level max (root composite `analyse-*` scripts), Laravel Pint, `guzzlehttp/guzzle ^7.9` + `cebe/php-openapi ^1.7` + `softcreatr/jsonpath ^0.10.0` (F1 only), DOM/libxml + PSR-18 `psr/http-client`/`psr/http-message`/`psr/http-factory` (F2 only).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md` — Phase F rows (F1, F2), decision table (D4, D7, D9), package map (lines 165-180), Public API impact (lines 618-623), Risks, sequencing (`#25`/`#62` → F1, `#17`/`#16` → F2).

## Global Constraints

- **Prerequisites (hard preconditions).** This plan assumes **Phase A** (contracts ports A1–A6 incl. `PluginInterface`, `OperationExecutorPluginInterface`, `SourceNormalizerInterface`, `SourceNormalizerRegistryInterface`, `ResponseTransferInterface` + generic `ResponseTransfer`, `StepState`), **Phase B** (step-scoped grammar + transfer views), **Phase C** (expression/evaluation split into `arazzo-expression` + `arazzo-evaluation`, with JsonPath wired as built-in default plugins — no third package), **Phase D** (`SourceNormalizerRegistry`, `OpenApiSourceNormalizer`, two-axis `ResolvedOperation`, `RuleSet` WSDL step rules), and **Phase E** (`OperationExecutorRegistry`, `ResponseValidatorDispatcher`, `StepStateMachineEngine`) have landed. If any are not on `main`, **stop and flag it before executing the first task** — exactly as the D/E plans gate on A/B/C. Where a later-phase registry does not exist at implementation time, use the concrete today-bound surface and note the hand-off (the G plan's "consumes, do not re-implement" rule). Per the spec's transfer seam (D6, phase A A6): each protocol package ships a **typed transfer DTO implementing `ResponseTransferInterface`** (`status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`) — HTTP keeps the generic `ResponseTransfer`, SOAP ships `SoapResponseTransfer`, RPC ships `RpcResponseTransfer`. Concrete facets live in the DTO's `views`/`meta` bags under documented keys, never as flat constructor props.
- **The plan is the boss.** Follow the exact task order; only deviate where the code forces you to, and note the deviation in the commit message. No code/spec edits outside the files each task lists (except root/umbrella/Laravel `composer.json` require+repositories for the two new packages, and the Laravel wiring that "ships the default wiring" per spec F1).
- **FQCN stability (D9).** `Alama\Arazzo\Document\Normalizer\ResolvedOperation` and `…\NormalizedOpenApiOperation` are the ONLY relocated types whose FQCNs do not change. They must exist in exactly one package after F1 (protocol-http) — never re-declared in `document`. All other relocated types move to `Alama\Arazzo\Protocol\Http\…` marked `@internal stays out of the advertised contract; not part of the public API surface`.
- **Core never imports protocol (H2 invariant).** No `use Alama\Arazzo\Protocol\...` may appear under `packages/{contracts,expression,document,evaluation,runner}/src`. `document`/`runner` PHPStan runs resolve relocated types through `phpstan.neon.dist` `scanDirectories` (F1.2), not composer requires. Final gate: `make verify` at repo root.
- **Direction of dependencies.** `arazzo-protocol-http` requires `contracts` + `expression` + `evaluation` + `document` + `runner` (the relocated executors consume runner internals already verified: `ExecutionEvaluationInput`, `ReusableParameterResolver`, `HttpClientInterface`, `PendingCorrelationRegistryInterface`). `arazzo-protocol-soap` requires the same, minus Guzzle/cebe. Neither `document` nor `runner` ever requires a protocol package.
- **Priority convention (locked with G1).** Higher `PluginInterface::priority()` = resolved earlier. All registries here sort descending.
- **No new code comments** unless explaining a priority/BC decision or the dual-PSR-4 map. Existing relocated docblocks stay.
- Every task's `--filter` runs `vendor/bin/pest packages/<pkg>/tests --filter "<name>"` from the repo root. Every task ends with its package suite green (`test-http`/`test-soap` + `test-document`/`test-runner`/`test-laravel` where touched). Static analysis per task: `composer run analyse-http` / `analyse-soap` (plus the touched package's `analyse-*`).
- New-command plumbing (F1.0) is the ONLY task that edits root `composer.json` `scripts`; later tasks only add `require`/`repositories` entries.

---

## Task F1.0: `arazzo-protocol-http` package scaffold + root plumbing

Create the package directory, composer manifest (incl. the dual PSR-4 map), Pest/PHPStan scaffolding, and root monorepo plumbing (repositories, require, autoload-dev, `scripts`). This task only *declares* the package — no relocated classes yet, so `composer update` must succeed with a valid (empty) package.

**Files:**
- Create `packages/protocol-http/composer.json`
- Create `packages/protocol-http/phpstan.neon.dist`
- Create `packages/protocol-http/tests/Pest.php`
- Create `packages/protocol-http/tests/ArchTest.php`
- Create `packages/protocol-http/src/.gitkeep` (so the dir exists pre-relocation)
- Modify `composer.json` (root): `repositories`, `require`, `autoload-dev`, `scripts`

**Interfaces:**
- Produces: package `alama/arazzo-protocol-http` installable as a path repository, analysable via `composer run analyse-http`, testable via `composer run test-http`.

- [ ] **Step 1: Create the composer manifest**

`packages/protocol-http/composer.json`:

```json
{
    "name": "alama/arazzo-protocol-http",
    "description": "HTTP/OpenAPI + AsyncAPI vertical slice for alama/arazzo-core: normalizers, executors, request compiler, response validators. Reference protocol package.",
    "keywords": ["alama", "arazzo", "openapi", "asyncapi", "http", "protocol"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"},
        {"type": "path", "url": "../document"},
        {"type": "path", "url": "../expression"},
        {"type": "path", "url": "../evaluation"},
        {"type": "path", "url": "../runner"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-runner": "@dev",
        "cebe/php-openapi": "^1.7",
        "guzzlehttp/guzzle": "^7.8||^8.0",
        "illuminate/contracts": "^11.0||^12.0||^13.0",
        "illuminate/support": "^11.0||^12.0||^13.0",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.1",
        "psr/http-message": "^2.0",
        "psr/log": "^3.0",
        "softcreatr/jsonpath": "^0.9.0||^0.10.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0||^10.0||^11.0",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "Alama\\Arazzo\\Protocol\\Http\\": "src/",
            "Alama\\Arazzo\\Document\\Normalizer\\": "src/Document/Normalizer/"
        }
    },
    "autoload-dev": {
        "psr-4": {"Alama\\Arazzo\\Tests\\Protocol\\Http\\": "tests/"}
    },
    "extra": {
        "laravel": {
            "providers": ["Alama\\Arazzo\\Protocol\\Http\\Laravel\\ProtocolHttpServiceProvider"]
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

Note the dual PSR-4 map: `Alama\Arazzo\Document\Normalizer\ → src/Document/Normalizer/` keeps the two DTO FQCNs byte-identical (D9); the `extra.laravel.providers` entry is the Laravel auto-discovery hook (the provider class ships in F1.3 — until then the entry is inert). `illuminate/contracts` + `illuminate/support` are required so the provider can extend `Illuminate\Support\ServiceProvider`; they are the package's only non-PSR/North-of-core deps besides the transport.

- [ ] **Step 2: Create the PHPStan config**

`packages/protocol-http/phpstan.neon.dist` — mirror `packages/document/phpstan.neon.dist` exactly, including the shared core rules and scan directories (the relocated types `import` document/runner internals, so the same scan list document/runner use must resolve them):

```neon
includes:
    - ../core/phpstan/rules/phpstan-custom.neon
    - ../core/phpstan/rules/phpstan-annotations.neon

parameters:
    level: max
    tmpDir: .phpstan
    paths:
        - src
    scanDirectories:
        - ../contracts/src
        - ../expression/src
        - ../evaluation/src
        - ../document/src
        - ../runner/src
    excludePaths:
        - tests
```

If `phpstan-custom.neon`/`phpstan-annotations.neon` do not both exist under `packages/core/phpstan/rules/` at implementation time, copy the `includes` block verbatim from `packages/document/phpstan.neon.dist` — that is the source of truth for the rules.

- [ ] **Step 3: Create the Pest bootstrap + a smoke test**

`packages/protocol-http/tests/Pest.php` — copy `packages/document/tests/Pest.php` verbatim (same cebe PHP 8.4 deprecation silencing is needed here, since cebe now lives in this package):

```php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
```

(Add the shared `Alama\Arazzo\Tests\TestCase` binding if document's `Pest.php` uses it — mirror document exactly.)

Create `packages/protocol-http/tests/Architecture/ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('protocol-http does not depend on Laravel runtime types in the slice core')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Illuminate\Support')
    ->ignoring('Alama\Arazzo\Protocol\Http\Laravel');
```

`src/.gitkeep` ensures the dir exists; `composer run test-http` must be green with the smoke arch test.

- [ ] **Step 4: Wire the root monorepo**

Edit root `composer.json`:
- `repositories`: append `{"type": "path", "url": "packages/protocol-http"}`.
- `require`: append `"alama/arazzo-protocol-http": "@dev"`.
- `autoload-dev.psr-4`: append `"Alama\\Arazzo\\Tests\\Protocol\\Http\\": "packages/protocol-http/tests"` (alongside the existing `Alama\Arazzo\Tests\` map — note protocol-http uses its *own* test namespace, mirroring how `packages/evaluation` uses its own in Phase C).
- `scripts`: append `"analyse-http": "vendor/bin/phpstan analyse -c packages/protocol-http/phpstan.neon.dist --memory-limit=1G"`, add `"@analyse-http"` to the `"analyse"` array, add `"test-http": "vendor/bin/pest packages/protocol-http/tests"`, add `"@test-http"` to the `"test"` array (keep the key order the existing array uses).

- [ ] **Step 5: Install and verify**

```bash
composer update alama/arazzo-protocol-http --with-dependencies --no-interaction
composer run analyse-http
composer run test-http
```

Expected: package symlinked into the root vendor from `packages/protocol-http`; analyse passes (0 errors on the empty `src`); Pest passes the arch smoke test.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock packages/protocol-http
git commit -m "build(protocol-http): scaffold arazzo-protocol-http package + root plumbing (F1.0)"
```

---

## Task F1.1: Relocate the document OpenAPI normalizers into `arazzo-protocol-http`

Move the normalizer classes out of `document` into the payload-http package. `ResolvedOperation` + `NormalizedOpenApiOperation` keep their FQCNs (land in `src/Document/Normalizer/`); the rest relocate to `Alama\Arazzo\Protocol\Http\Normalizer\`. `OpenApiVersionDetector` and `Interfaces/OpenApiNormalizerInterface` **stay in `document`** — they are zero-vendor sniffing/scoping primitives the spec's F1 list does not include, and `OpenApiOperationResolver.Type`-users in `document` (PreflightValidator) still need the detector. Protocol-http importing them is legal (protocol → core).

**Files moved (git mv) — see the real inventory verified on `main`:**
- → `packages/protocol-http/src/Document/Normalizer/ResolvedOperation.php` (FQCN `Alama\Arazzo\Document\Normalizer\ResolvedOperation` unchanged)
- → `packages/protocol-http/src/Document/Normalizer/NormalizedOpenApiOperation.php` (FQCN unchanged)
- → `packages/protocol-http/src/Normalizer/OpenApi30Normalizer.php` (ns `Alama\Arazzo\Protocol\Http\Normalizer`)
- → `packages/protocol-http/src/Normalizer/OpenApi31Normalizer.php`
- → `packages/protocol-http/src/Normalizer/Swagger2Normalizer.php`
- → `packages/protocol-http/src/Normalizer/OpenApiDocumentLoader.php`
- → `packages/protocol-http/src/Normalizer/OpenApiOperationResolver.php`
- → `packages/protocol-http/src/Normalizer/OpenApiSourceNormalizer.php` (created by D2; its docblock already says "relocated to alama/arazzo-protocol-http in F1")

**Files created:**
- `packages/protocol-http/src/Normalizer/Interfaces/OpenApiNormalizerInterface.php`? — NO: interfaces stay in document. Everything above is a `git mv` + namespace edit.

**Files edited to fix imports (document side re-points to protocol package FQCN only where legal):**
- `packages/document/src/DocumentInterface.php` — return type `ResolvedOperation` (FQCN unchanged; no edit needed beyond verifying the `use` matches the DTO FQCN).
- `packages/document/src/Document.php` — remove the in-constructor plumbing of `OpenApiDocumentLoader`/`OpenApiOperationResolver`/`OpenApiVersionDetector`; after D5 the constructor receives `SourceNormalizerRegistryInterface` (`packages/document/src/Resolver/SourceNormalizerRegistry.php` from D1) and `PreflightValidator` accepts the resolver seam (F1.2). This task only removes the moved classes' construction — the concrete replacement wiring is F1.2.
- `packages/document/src/Validator/PreflightValidator.php` — constructor type for `$operations` changes from `OpenApiOperationResolver` to the new document-side interface (F1.2); its own `new OpenApiVersionDetector()` stays (detector is not relocated).
- `packages/laravel/src/Bindings/ResolverBindings.php` — the `OpenApiDocumentLoader`/`OpenApiOperationResolver` singletons construct the protocol-http classes now (F1.3) — do NOT edit until F1.3 to keep this task compilable in one commit; instead, keep this edit in F1.3.

**Interfaces:**
- Consumes (protocol-http): `SourceResolver` (`Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver`), `UnsupportedSourceVersionException`, `OpenApiNormalizerInterface`+`OpenApiVersionDetector` (both stay in document), the two DTO FQCNs.
- Produces: `Alama\Arazzo\Protocol\Http\Normalizer\{OpenApi30Normalizer, OpenApi31Normalizer, Swagger2Normalizer, OpenApiDocumentLoader, OpenApiOperationResolver, OpenApiSourceNormalizer}`.

- [ ] **Step 1: `git mv` the eight files**

```bash
mkdir -p packages/protocol-http/src/Normalizer packages/protocol-http/src/Document/Normalizer
git mv packages/document/src/Normalizer/ResolvedOperation.php packages/protocol-http/src/Document/Normalizer/ResolvedOperation.php
git mv packages/document/src/Normalizer/NormalizedOpenApiOperation.php packages/protocol-http/src/Document/Normalizer/NormalizedOpenApiOperation.php
git mv packages/document/src/Normalizer/OpenApi30Normalizer.php packages/protocol-http/src/Normalizer/OpenApi30Normalizer.php
git mv packages/document/src/Normalizer/OpenApi31Normalizer.php packages/protocol-http/src/Normalizer/OpenApi31Normalizer.php
git mv packages/document/src/Normalizer/Swagger2Normalizer.php packages/protocol-http/src/Normalizer/Swagger2Normalizer.php
git mv packages/document/src/Normalizer/OpenApiDocumentLoader.php packages/protocol-http/src/Normalizer/OpenApiDocumentLoader.php
git mv packages/document/src/Normalizer/OpenApiOperationResolver.php packages/protocol-http/src/Normalizer/OpenApiOperationResolver.php
git mv packages/document/src/Normalizer/OpenApiSourceNormalizer.php packages/protocol-http/src/Normalizer/OpenApiSourceNormalizer.php
```

(If D2's `OpenApiSourceNormalizer` does not exist on `main`, the D plan was not completed — stop and flag before proceeding.)

- [ ] **Step 2: Rewrite namespaces in the six relocated `@internal` classes**

`OpenApi30Normalizer`, `OpenApi31Normalizer`, `Swagger2Normalizer`, `OpenApiDocumentLoader`, `OpenApiOperationResolver`, `OpenApiSourceNormalizer`: `namespace Alama\Arazzo\Document\Normalizer;` → `namespace Alama\Arazzo\Protocol\Http\Normalizer;`, and update their internal imports:
- `use Alama\Arazzo\Document\Normalizer\Interfaces\OpenApiNormalizerInterface;` → `use Alama\Arazzo\Document\Normalizer\Interfaces\OpenApiNormalizerInterface;` (**unchanged** — the interface stays in document).
- The two DTOs resolve by unchanged FQCN — keep the existing `use Alama\Arazzo\Document\Normalizer\ResolvedOperation;` / `NormalizedOpenApiOperation` imports as-is.
- `OpenApiSourceNormalizer` (D2 impl) imports `Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader` / `OpenApiVersionDetector` / `OpenApi30Normalizer` / `OpenApi31Normalizer` — within-package now, so drop those `use` statements or point them at `Alama\Arazzo\Protocol\Http\Normalizer\*`; keep `Alama\Arazzo\Document\Resolver\Exceptions\UnsupportedSourceVersionException` etc.
- `OpenApiOperationResolver` keeps `use Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver;` and `use Alama\Arazzo\Document\Resolver\Exceptions\UnsupportedSourceVersionException;` (both stay in document).
- Docblocks: leave as-is (they already carry `@internal`; the "relocated" notes in D2/D3c docblocks are now accurate).

- [ ] **Step 3: Drop `cebe/php-openapi` from `document`, move it in protocol-http**

Edit `packages/document/composer.json`: remove `"cebe/php-openapi": "^1.7"` from `require`. **Keep** `symfony/yaml` + `justinrainbow/json-schema` + `psr/http-client`/`psr/http-message` + `psr/simple-cache` (verified: `packages/document/src/Resolver/Fetchers/HttpFetcher.php` still uses `Psr\Http\Client\ClientInterface`/`RequestFactoryInterface`, `CachedFetcher` uses `Psr\SimpleCache`). protocol-http `require` already lists `cebe/php-openapi` (F1.0).

- [ ] **Step 4: Repoint `PreflightValidator`'s operation-resolver seam (document side)**

Create `packages/document/src/Normalizer/OpenApiOperationResolverInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Normalizer;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Document-side seam for operation resolution during preflight. Implemented
 * by the HTTP/OpenAPI protocol package (arazzo-protocol-http F1) so the
 * validator never imports protocol types.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OpenApiOperationResolverInterface
{
    public function resolve(Step $step, ArazzoDocument $document): ResolvedOperation;
}
```

Edit `packages/document/src/Validator/PreflightValidator.php`:
- Replace `use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;` with `use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolverInterface;`.
- Constructor param `private readonly OpenApiOperationResolver $operations` → `private readonly OpenApiOperationResolverInterface $operations`.
- `OpenApiVersionDetector` usage unchanged (it stays in document).

Precondition: the D5 `Document` refactor (registry wired in) must have landed, otherwise `Document::__construct` still classes the moved types directly — in that case stop and finish D5 first.

- [ ] **Step 5: Write/relocate the document normalizer tests**

Move the normalizer-focused unit tests into the package so they run under `test-http`:
```bash
git mv packages/document/tests/Normalizer/OpenApiOperationResolverVersionTest.php packages/protocol-http/tests/Normalizer/OpenApiOperationResolverVersionTest.php
git mv packages/document/tests/Normalizer/ResolvedOperationTest.php packages/protocol-http/tests/Normalizer/ResolvedOperationTest.php
git mv packages/document/tests/Normalizer/OpenApiSourceNormalizerTest.php packages/protocol-http/tests/Normalizer/OpenApiSourceNormalizerTest.php
```
(Adjust test namespaces to `Alama\Arazzo\Tests\Protocol\Http\Normalizer`.) Tests that exercise `document`'s own resolution through `DocumentInterface` (e.g. `DocumentCapabilitiesTest`) stay in `document` and are covered in F1.3.

- [ ] **Step 6: Run the gates**

```bash
composer update cebe/php-openapi --no-interaction
composer run test-http -- --filter=Normalizer
composer run analyse-http
composer run test-document
composer run analyse-document
```

Expected: `test-http` green on the relocated suites; `test-document`/`analyse-document` still green (the only document refs are the FQCN-preserved DTOs + the new interface). `analyse-document` must NOT error on resolving `ResolvedOperation` — verify `packages/document/phpstan.neon.dist` `scanDirectories` includes `../protocol-http/src` (add it if document's config doesn't already resolve the DTO room after the move).

- [ ] **Step 7: Commit**

```bash
git add -A packages/document packages/protocol-http packages/laravel packages/runner composer.json composer.lock
git commit -m "refactor(protocol-http): relocate OpenAPI normalizers out of document (F1.1)"
```

---

## Task F1.2: Relocate the runner HTTP/OpenAPI/AsyncAPI classes; seam the graph assemblers

Move the HTTP execution stack out of `runner` into `protocol-http`. Kept permanently in `runner` (verified consumers on `main`, so protocol-http -> runner is the correct edge): `Execution/ExecutionExpressionResolver` (types the contract interfaces `OutputExtractorInterface`/`ResponseValidatorInterface`), `Execution/ReusableParameterResolver`, `Execution/ExecutionEvaluationInput`, `Exceptions/ExecutionException`, `Infrastructure/Interfaces/HttpClientInterface` + `PendingCorrelationRegistryInterface`. `ExecutionExpressionResolver` staying means protocol-http importing runner is required and sufficient — no cycle (runner's `src` never imports `Alama\Arazzo\Protocol\`).

**Files moved (git mv):**
- → `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php` (ns `Alama\Arazzo\Protocol\Http\Execution`)
- → `packages/protocol-http/src/Execution/RequestCompiler.php`
- → `packages/protocol-http/src/Execution/ExpressionValueResolver.php`
- → `packages/protocol-http/src/Execution/ParameterSerializer.php`
- → `packages/protocol-http/src/Execution/TypeCaster.php`
- → `packages/protocol-http/src/Execution/SchemaValidator.php`
- → `packages/protocol-http/src/Execution/ResponseSchemaValidator.php`
- → `packages/protocol-http/src/Execution/StepOutputExtractor.php`
- → `packages/protocol-http/src/Protocol/HttpStepExecutor.php`
- → `packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php`
- `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` (canonical, see Step 2)

**Files kept in runner (edit only):**
- `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — becomes a `@deprecated` BC alias (Step 2)
- `packages/runner/src/Execution/ExecutionGraphFactory.php` — stop defaulting protocol-http classes (Step 3)
- `packages/runner/src/AsyncExecutionGraphAssembler.php` — stop defaulting them in `assemble()` (Step 4)
- `packages/runner/src/Execution/StepExecutor.php` — stop constructing `RequestCompiler`/`ExpressionValueResolver` (Step 3)
- `packages/runner/src/RunnerFacade.php`, `packages/runner/src/RunnerGraphBuilder.php` — forward the new executor seam (Step 3/5)
- `packages/runner/phpstan.neon.dist` + `packages/document/phpstan.neon.dist` — add `../protocol-http/src` to `scanDirectories` (Step 5)

**Interfaces:**
- Produces (protocol-http): `Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface` (canonical signature of today's runner interface).
- Runner's `OpenApiExecutorInterface` FQCN is preserved as a deprecated alias so existing type-hints (`StepExecutor`, `AsyncGraphSeams`, `AsyncExecutionGraphAssembler`, tests) keep compiling without a core->protocol import.

- [ ] **Step 1: `git mv` the ten classes + rewrite namespaces**

```bash
mkdir -p packages/protocol-http/src/Execution packages/protocol-http/src/Protocol
git mv packages/runner/src/Execution/DefaultOpenApiExecutor.php packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php
git mv packages/runner/src/Execution/RequestCompiler.php packages/protocol-http/src/Execution/RequestCompiler.php
git mv packages/runner/src/Execution/ExpressionValueResolver.php packages/protocol-http/src/Execution/ExpressionValueResolver.php
git mv packages/runner/src/Execution/ParameterSerializer.php packages/protocol-http/src/Execution/ParameterSerializer.php
git mv packages/runner/src/Execution/TypeCaster.php packages/protocol-http/src/Execution/TypeCaster.php
git mv packages/runner/src/Execution/SchemaValidator.php packages/protocol-http/src/Execution/SchemaValidator.php
git mv packages/runner/src/Execution/ResponseSchemaValidator.php packages/protocol-http/src/Execution/ResponseSchemaValidator.php
git mv packages/runner/src/Execution/StepOutputExtractor.php packages/protocol-http/src/Execution/StepOutputExtractor.php
git mv packages/runner/src/Protocol/HttpStepExecutor.php packages/protocol-http/src/Protocol/HttpStepExecutor.php
git mv packages/runner/src/Protocol/AsyncApiStepExecutor.php packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php
```

Then `namespace Alama\Arazzo\Runner\...;` → `namespace Alama\Arazzo\Protocol\Http\Execution|protocol;` in all ten, and re-point imports:
- `HttpStepExecutor`/`AsyncApiStepExecutor`: `use Alama\Arazzo\Runner\Execution\...` internals that STAY (`EvaluationExecutionInput` is `Alama\Arazzo\Runner\Execution\Data\ExecutionEvaluationInput`, `ReusableParameterResolver` is `Alama\Arazzo\Runner\Execution\ReusableParameterResolver`, `Exceptions\ExecutionException`, `Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface` + `PendingCorrelationRegistryInterface`) — these imports are unchanged (runner package is now a dependency, imports are legal). Same-namespace imports (`RequestCompiler`, `ExpressionValueResolver`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`, `ResponseSchemaValidator`, `StepOutputExtractor`, `DefaultOpenApiExecutor`) drop their `use` (same package now).
- `DefaultOpenApiExecutor`: imports Guzzle (`GuzzleHttp\Psr7\Utils`, `GuzzleHttp\Client`/`Utils::defaultOption` for timeouts) + `Alama\Arazzo\Document\Normalizer\{ResolvedOperation,NormalizedOpenApiOperation}` (FQCN unchanged) + `Alama\Arazzo\Document\Parser\Exceptions\UnsupportedSerializationStyleException` (stays in document) + runner `ExecutionEvaluationInput`/`StepState`/`ResponseTransfer` (contracts) + `Alama\Arazzo\Contract\Execution\Executions...` — keep as today; only namespaces of fellow movers change.
- `RequestCompiler`: imports `ReusableParameterResolver` (runner, unchanged) + `ExpressionValueResolver` (same package now, drop `use`).
- `ParameterSerializer`: imports `Document\Parser\Exceptions\UnsupportedSerializationStyleException` (stays in document, keep) + `StepState`/`ResponseTransfer`/`RuntimeExpressionPayload`? (contracts, keep).
- `ResponseSchemaValidator`/`SchemaValidator`: `cebe\php-openapi` imports now resolve within-package (cebe is required by protocol-http) — no change needed beyond namespace.
- `TypeCaster`: pure DateTime/array coercion, move namespace only.
- Add/keep `@internal stays out of the advertised contract; not part of the public API surface` on every moved class (`DefaultOpenApiExecutor` AND `HttpStepExecutor` etc.).
- Also move `OpenApiExecutorInterface`'s canonical definition — see Step 2.

- [ ] **Step 2: Split `OpenApiExecutorInterface` into canonical (protocol-http) + BC alias (runner)**

Copy the interface into the package and demote the runner one to an alias:

`packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — canonical. Copy the exact current method set/signatures from `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` verbatim (e.g. `public function execute(\Alama\Arazzo\Contracts\Execution\ExecutionRequest $request): \Alama\Arazzo\Contracts\Execution\ExecutionResult;` plus whatever `TimeoutAware*`/config methods exist today), namespace `Alama\Arazzo\Protocol\Http\Execution\Interfaces`.

`packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — keep the file, keep the identical method signatures so PHPStan/types line up, and mark:

```php
/**
 * @deprecated since will be removed in X — relocated to
 * Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface
 * in arazzo-protocol-http (Phase F1). Keep this FQCN as a BC alias.
 * @internal BC alias; register arazzo-protocol-http to obtain the canonical type.
 */
```

Demote `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface` from "implementation reference" to "signature mirror". If phpstan flags `DeprecatedInterface`/unused, add the `@phpstan-ignore` with a comment, or `implements \Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface` on the alias — whichever keeps the runner build green; never remove the runner FQCN in this phase. (`StepProtocolExecutorInterface`, if it exists under runner/contracts, gets the same alias treatment; verify on `main` and replicate.)

- [ ] **Step 3: Remove protocol-http defaults from runner's sync path**

`packages/runner/src/Execution/ExpressionValueResolver` moved, so `StepExecutor` can no longer `new RequestCompiler(new ExpressionValueResolver($this->engine), $this->engine)`.

- `StepExecutor::__construct(OpenApiExecutorInterface $openApiExecutor, ExpressionEngine $engine)` keeps its signature, but drop the RequestCompiler/ExpressionValueResolver construction. Instead move that construction **into** `DefaultOpenApiExecutor` (legal — both live in protocol-http): `DefaultOpenApiExecutor` gains `private ?RequestCompiler $requestCompiler` built in its constructor from the same engine/expression inputs it receives (`new RequestCompiler(new ExpressionValueResolver($engine), $engine)`), or receives it via the factory in F1.3.
- `ExecutionGraphFactory`: replace the `$httpClient` param + `new DefaultOpenApiExecutor(...)/new StepOutputExtractor(...)/new ResponseSchemaValidator(...)` defaults. New shape mirrors the async seams:

```php
public function __construct(
    private readonly DocumentRegistry $documents,
    private readonly ExpressionEngine $engine,
    private readonly ?OpenApiExecutorInterface $openApiExecutor = null,
) {}

public function createWorkflowExecutor(...): WorkflowExecutor
{
    $openApiExecutor = $this->openApiExecutor
        ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
    // build StepExecutor($openApiExecutor, ...) as today, minus the moved constructions
}
```

- `packages/runner/src/RunnerFacade.php`: forward the seam — `__construct` gains `?OpenApiExecutorInterface $openApiExecutor = null`, passed to `ExecutionGraphFactory`. Existing callers who don't pass it get the throw *at execution time* (accepted: spec says standalone document/runner consumers must register arazzo-protocol-http).
- `packages/runner/src/RunnerGraphBuilder.php`: factory receives `$this->openApiExecutor` (nullable) as today it receives `$client` — F1.3 will feed the real executor from Laravel.

- [ ] **Step 4: Remove protocol-http defaults from the async path**

`packages/runner/src/AsyncExecutionGraphAssembler.php::assemble()` lines ~38-45 currently do `new Client()`, `new HttpFactory()`, `new DefaultOpenApiExecutor($client, $factory, $seams->logger)`, and default-construct `StepOutputExtractor`/`ResponseSchemaValidator`. After F1.2, assemble() must not construct any `Alama\Arazzo\Protocol\...` type:

```php
$openApiExecutor = $seams->openApiExecutor
    ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
$expressionResolver = $seams->expressionResolver ?? new ExecutionExpressionResolver($this->engine, ...); // stays (runner)
```

`AsyncGraphSeams::$openApiExecutor` keeps its `?OpenApiExecutorInterface` type (now the @deprecated alias — same FQCN, no signature churn). Wherever `assemble()` additionally defaulted `StepOutputExtractor`/`ResponseSchemaValidator` for the AsyncAPI branch, move them behind `$seams` as nullable `?OutputExtractorInterface`/`?ResponseValidatorInterface` (contracts types, runner-legal) or feed the E5 `ResponseValidatorDispatcher` from its registry — choose whichever survives PHPStan at implementation time, and leave a `// cleans up in F1.3` marker where a concrete protocol-http instance must flow down from Laravel.

**Sweep guard (must be empty after this task):**
```bash
rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor|StepOutputExtractor|ResponseSchemaValidator|ParameterSerializer|TypeCaster|SchemaValidator|RequestCompiler|ExpressionValueResolver)\(" packages/runner/src packages/document/src
```
Any hit = a core->protocol import; eliminate every one (move the construction into protocol-http or a seam). `rg -n "Protocol\\Http" packages/runner/src packages/document/src` must also be empty (no `Alama\Arazzo\Protocol\Http` imports under core).

- [ ] **Step 5: PHPStan scan directories**

In `packages/document/phpstan.neon.dist` AND `packages/runner/phpstan.neon.dist`, add to `scanDirectories`: `../protocol-http/src` (and keep `../evaluation/src` if Phase C added it). Rationale: relocated DTOs/types are imported by document/runner code (e.g. `DocumentInterface::resolveOperation(): ResolvedOperation` in document) and must resolve without a composer dependency. Analyse runs from repo root so `cebe/php-openapi` resolves via protocol-http's `require`.

- [ ] **Step 6: Move the runner HTTP/Execution tests that exercise relocated classes**

```bash
git mv packages/runner/tests/Execution/DefaultOpenApiExecutorTest.php packages/protocol-http/tests/Execution/DefaultOpenApiExecutorTest.php
git mv packages/runner/tests/Execution/RequestCompilerTest.php packages/protocol-http/tests/Execution/RequestCompilerTest.php
git mv packages/runner/tests/Execution/ParameterSerializerTest.php packages/protocol-http/tests/Execution/ParameterSerializerTest.php
git mv packages/runner/tests/Execution/SchemaValidatorTest.php packages/protocol-http/tests/Execution/SchemaValidatorTest.php
git mv packages/runner/tests/Execution/ResponseSchemaValidatorTest.php packages/protocol-http/tests/Execution/ResponseSchemaValidatorTest.php
git mv packages/runner/tests/Execution/StepOutputExtractorTest.php packages/protocol-http/tests/Execution/StepOutputExtractorTest.php
git mv packages/runner/tests/Protocol/HttpStepExecutorTest.php packages/protocol-http/tests/Protocol/HttpStepExecutorTest.php
git mv packages/runner/tests/Protocol/AsyncApiStepExecutorTest.php packages/protocol-http/tests/Protocol/AsyncApiStepExecutorTest.php
```

Update namespaces in the moved tests to `Alama\Arazzo\Tests\Protocol\Http\Execution|Protocol`. The test fixtures/HTTP mocks they reference (PSR-18 fake clients, sample documents) move with them. **Do not move** `RunnerFacadeTest`/`ExecutionGraphFactoryTest`/`AsyncExecutionGraphAssemblerTest` — rewrite those in place to inject a small in-repo fake `OpenApiExecutorInterface` (runner tests may no longer exercise real HTTP: core package tests are allowed to, but runner's own suite cannot require protocol-http — that is the accepted D9-era test seam; the real vertical slice is asserted under `test-laravel` + the final gate).

- [ ] **Step 7: Gates**

```bash
composer run test-http
composer run analyse-http
composer run test-runner
composer run analyse-runner
composer run test-document
composer run analyse-document
```

Expected: runner tests green using the fake executor; `rg "Protocol\\Http" packages/{runner,document}/src` empty; sweep guard (Step 4) empty.

- [ ] **Step 8: Commit**

```bash
git add -A packages/runner packages/protocol-http packages/document composer.json composer.lock
git commit -m "refactor(protocol-http): relocate runner HTTP/OpenAPI/AsyncAPI stack; seam assembly (F1.2)"
```

---

## Task F1.3: Laravel default wiring + `ProtocolHttpServiceProvider` + registrar + plugin tags

Make the composed app (laravel + umbrella) work with zero config: bind the protocol-http classes into the container, register them into the D/E registries under the `arazzo.plugins.*` tag vocabulary (G1), and update the Laravel bindings that currently construct `document/runner` classes that no longer exist there.

**Files created:**
- `packages/protocol-http/src/Laravel/ProtocolHttpServiceProvider.php`
- `packages/protocol-http/src/Registrar/ProtocolHttpRegistrar.php`
- `packages/protocol-http/config/arazzo.php` (G3: `arazzo.plugins.tags` default `['arazzo.plugins.*']`, `arazzo.plugins.operation_executor` default `'arazzo.plugins.operation-executor'`)
- `packages/protocol-http/tests/Laravel/ProtocolHttpServiceProviderTest.php`

**Files edited:**
- `packages/laravel/composer.json` — require + repositories + `extra.laravel.providers`? NO — providers are auto-discovered from the package's own `extra.laravel.providers` (F1.0). Laravel only needs the require entry + repositories.
- `packages/laravel/src/Bindings/ResolverBindings.php` — construct protocol-http normalizer classes
- `packages/laravel/src/Bindings/FacadeBindings.php` — feed the protocol executor into `RunnerGraphBuilder`/`RunnerFacade`
- `packages/laravel/src/Bindings/ExecutionBindings.php` + `packages/laravel/src/Support/AsyncGraphResolver.php` — feed the protocol executor into `AsyncGraphSeams`

**Interfaces:**
- Consumes: `SourceNormalizerRegistryInterface`, `OperationExecutorRegistry` (D/E), `ResponseValidatorDispatcher` (E), `PluginRegistry` (G1) — via the G3-located tag names, resolving whatever is on `main` today and noting the hand-off per Global Constraints (if a registry is absent, `registerInto` merges into what exists and we note it).
- Produces: `Alama\Arazzo\Protocol\Http\Laravel\ProtocolHttpServiceProvider`, `Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar`.

- [ ] **Step 1: Registrar (framework-agnostic)**

`packages/protocol-http/src/Registrar/ProtocolHttpRegistrar.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Http\Registrar;

/**
 * Framework-agnostic registration of the HTTP/OpenAPI vertical slice into the
 * core SPI registries. Used by the Laravel provider and by CLI/umbrella wiring.
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ProtocolHttpRegistrar
{
    public function __construct(/* registry dependencies resolved from constructor */) {}

    public function registerInto(
        ?SourceNormalizerRegistryInterface $sourceRegistry = null,
        ?OperationExecutorRegistry $operationExecutorRegistry = null,
        ?ResponseValidatorDispatcher $responseValidators = null,
    ): void
    {
        $sourceRegistry?->register(new OpenApiSourceNormalizer(/* loader + detector + normalizers */));
        $operationExecutorRegistry?->register(
            new DefaultOpenApiExecutor(/* client/factory/logger */),
        );
        $responseValidators?->register(new ResponseSchemaValidator(/* ... */), /* priority */);
    }
}
```

Construct the exact concrete wiring (which registry classes exist, argument order) from the D/E/G implementations on `main`; `Priority` set via the class constants + descending sort per Global Constraints. If PHPStan complains the `?Type`s clash (registries not yet ported), annotate `@phpstan-ignore` + a `// TODO(G)` and wire to container callbacks.

- [ ] **Step 2: `ProtocolHttpServiceProvider`**

`packages/protocol-http/src/Laravel/ProtocolHttpServiceProvider.php`, extends `Illuminate\Support\ServiceProvider`:

```php
final class ProtocolHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/arazzo.php', 'arazzo');

        $this->app->singleton(DefaultOpenApiExecutor::class, fn () => /* from container: Guzzle client (config default timeout), PSR factories, logger */);

        $this->app->tag([/* normalizer + executor + validator services */], config('arazzo.plugins.tags.0', 'arazzo.plugins.*'));

        // G3 binding: the container can answer "the tag configured as operation_executor"
        $this->app->singleton(\Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface::class,
            fn ($app) => $app->make(config('arazzo.plugins.operation_executor')));
    }

    public function boot(): void
    {
        $this->app->get(ProtocolHttpRegistrar::class)->registerInto(
            sourceRegistry: $this->app->make(SourceNormalizerRegistryInterface::class),
            operationExecutorRegistry: $this->app->make(OperationExecutorRegistry::class),
            responseValidators: $this->app->make(ResponseValidatorDispatcher::class),
        );
    }
}
```

Exact service names/classes finalized against the G-plan's tag + `ProtocolDiscovery::tags()`/`operationExecutorTag()` (G3) once G lands; until then the provider registers under `arazzo.plugins.*` and `arazzo.plugins.operation-executor` as the default config, and the provider test asserts the tag keys exist. Keep the provider's `use Illuminate\...` limited to `ServiceProvider` + the tagged container; all slice classes stay in `Alama\Arazzo\Protocol\Http\{Execution,Normalizer,Protocol}` (the F1.0 arch test ignores the `Laravel` sub-namespace).

- [ ] **Step 3: Update Laravel resolver bindings**

`packages/laravel/src/Bindings/ResolverBindings.php` — the `OpenApiDocumentLoader` / `OpenApiOperationResolver` / `PreflightValidator` singletons now point at:
- `Alama\Arazzo\Protocol\Http\Normalizer\OpenApiDocumentLoader`
- `Alama\Arazzo\Protocol\Http\Normalizer\OpenApiOperationResolver`
- `Alama\Arazzo\Document\Validator\PreflightValidator` (unchanged; its `OpenApiOperationResolverInterface` binding resolves to the protocol-http `OpenApiOperationResolver`).

Edit `packages/laravel/tests/Bindings/ResolverBindingsTest.php` accordingly (assert the resolver binding returns the protocol class, preflight still passes the existing fixture documents).

- [ ] **Step 4: Feed the executor into the async + sync seams**

- `ExecutionBindings`/`AsyncGraphResolver`: `self::seams($app)` gains the executor — `$seams->openApiExecutor = $app->make(Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface::class);` (resolves via the G3 tag; fall back to a direct `make(DefaultOpenApiExecutor::class)` until G lands).
- `FacadeBindings`: `RunnerGraphBuilderInterface → RunnerGraphBuilder` (currently built with `ClientInterface`/`RequestFactoryInterface` from the container) now passes `$app->make(OpenApiExecutorInterface::class)` into the builder, which forwards it to `ExecutionGraphFactory`/`RunnerFacade` (F1.2 seams). Remove the `ClientInterface`/`RequestFactoryInterface` args from the `RunnerGraphBuilder` edge only if PHPStan proves them unused after F1.2 — otherwise leave them, unused, until F1.4 clean-up (do not remove public container bindings in this task).

- [ ] **Step 5: Laravel provider + wiring tests**

`packages/protocol-http/tests/Laravel/ProtocolHttpServiceProviderTest.php` (extends `Alama\Arazzo\Tests\Protocol\Http\TestCase`, which extends `Orchestra\Testbench\TestCase`):
- assert `$app->make(OpenApiExecutorInterface::class)` resolves `DefaultOpenApiExecutor`;
- assert the tagged services exist under `arazzo.plugins.operation-executor` when the config key is used;
- assert `PreflightValidator` still validates an OpenAPI fixture end-to-end through the container;
- assert an end-to-end workflow execute: run a WorkflowExecutor + HttpStepExecutor against a PSR-18 fixture client (an in-test fake HTTP client that returns canned responses for the step's URL) — this is the F1 vertical-slice proof.

Laravel suite: add `"alama/arazzo-protocol-http": "@dev"` + path repository to `packages/laravel/composer.json` (apps using laravel-arazzo get the default HTTP slice per spec), bump autoload-dev if needed, then:

```bash
composer update alama/arazzo-protocol-http --with-dependencies --no-interaction
composer run test-http
composer run test-laravel
composer run analyse-laravel && composer run analyse-http
```

- [ ] **Step 6: Root-level integration gate**

```bash
composer run test && composer run analyse && make verify
```

This exercises the `packages/core` and root test suites that construct the moved classes (`Conformance\ConformanceHarness`, `tests/Validator/PreflightValidatorTest.php`, `tests/Validator/InputsPreValidationTest.php`, runner graph tests) through root autoload-dev — proving the H2-on-disk + D9-FQCN story end-to-end. `make verify` runs format+analyse+test linting; **no diffs may remain after `composer run format`** (`verification-before-completion` rule: run it and confirm clean output before claiming done).

- [ ] **Step 7: Commit**

```bash
git add -A packages/laravel packages/protocol-http composer.json composer.lock
git commit -m "feat(protocol-http): Laravel default wiring, provider, registrar, plugin tags (F1.3)"
```

---

## Task F1.4: F1 verification slice — complete the HTTP reference vertical slice

Confirm F1 as a whole delivers a working "HTTP protocol on a composed core" slice with zero core edits, and close any swept-up references left by F1.1–F1.3.

**Files:**
- Sweep: `rg -n "Arazzo\\\\Runner\\\\(Execution|Protocol)" packages/document/src packages/laravel/src` — any remaining runner-side import of a relocated class must now point at the protocol-http FQCN (allowed from Laravel only).
- Sweep: `rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor|StepOutputExtractor|ResponseSchemaValidator|RequestCompiler|ExpressionValueResolver)\(" packages/{document,runner,contracts,expression,evaluation}/src` — must be empty.
- Sweep: `rg -n "use Alama\\\\Arazzo\\\\Protocol\\\\" packages/{document,runner,contracts,expression,evaluation}/src` — must be empty (H2 on-disk invariant).
- Verify `packages/core/tests/**` that reference the moved classes still pass via root autoload-dev (`composer run test`, plus the conformance suite).

**Interfaces:**
- Produces: end-to-end proof — `arazzo-protocol-http` executed through a composed `arazzo-core` + `laravel-arazzo` app (testbench) without touching any core package.

- [ ] **Step 1: Sweeps** — run the three `rg` sweeps above, fix any hits by re-issuing local wiring (not by importing protocol into core).
- [ ] **Step 2: Full F1 gate**

```bash
composer run format
composer run analyse
composer run test
make verify
```

`make verify` must be green; `composer run format` must leave zero diffs (run Pint first, then re-run `make verify`). Confirm `composer run analyse-http` `analyse-document` `analyse-runner` `analyse-laravel` all pass.
- [ ] **Step 3: Commit + report F1 state**

```bash
git add -A
git commit -m "chore(protocol-http): F1 verification slice green (F1.4)"
```

Report: package `alama/arazzo-protocol-http` is the ONLY place Guzzle/cebe transport + OpenAPI normalizers + HTTP executors exist; `document`/`runner` source no longer imports any `Alama\Arazzo\Protocol\` type; `make verify` green.

---

## Task F2.0: `arazzo-protocol-soap` package scaffold + root plumbing

First brand-new protocol (spec #17/#16): SOAP over PSR-18 with DOM + libxml, zero vendor — proof that a protocol adds a vertical slice with no core edits. Scaffold exactly like F1.0 minus the transport deps.

**Files:**
- Create `packages/protocol-soap/composer.json`, `packages/protocol-soap/phpstan.neon.dist`, `packages/protocol-soap/tests/{Pest.php,ArchTest.php}`, `packages/protocol-soap/src/.gitkeep`
- Modify root `composer.json` (repositories, require, autoload-dev, `scripts`)

**Interfaces:**
- Produces: installable `alama/arazzo-protocol-soap`, `composer run analyse-soap`, `composer run test-soap`.

- [ ] **Step 1: Composer manifest**

```json
{
    "name": "alama/arazzo-protocol-soap",
    "description": "SOAP vertical slice for alama/arazzo-core: WSDL normalizer, SOAP executor, SoapResponseTransfer mapper, XSD response validator. Zero vendor, DOM + PSR-18.",
    "keywords": ["alama", "arazzo", "soap", "wsdl", "xsd", "protocol"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"},
        {"type": "path", "url": "../document"},
        {"type": "path", "url": "../expression"},
        {"type": "path", "url": "../evaluation"},
        {"type": "path", "url": "../runner"}
    ],
    "require": {
        "php": "^8.4",
        "ext-dom": "*",
        "ext-libxml": "*",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-runner": "@dev",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.1",
        "psr/http-message": "^2.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0||^10.0||^11.0",
        "pestphp/pest": "^5.0",
        "pestphp/pest-plugin-arch": "^5.0"
    },
    "autoload": {"psr-4": {"Alama\\Arazzo\\Protocol\\Soap\\": "src/"}},
    "autoload-dev": {"psr-4": {"Alama\\Arazzo\\Tests\\Protocol\\Soap\\": "tests/"}},
    "extra": {"laravel": {"providers": ["Alama\\Arazzo\\Protocol\\Soap\\Laravel\\SoapServiceProvider"]}},
    "config": {"sort-packages": true, "allow-plugins": {"pestphp/pest-plugin": true, "phpstan/extension-installer": true}},
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

SOAP has no DTO-relocation concern → **single PSR-4 map** (unlike F1.0). No `illuminate` require needed until F2.6 (the provider dir can be mounted later; keep it vendor-free now — the `extra.laravel.providers` entry is inert until the class exists).

- [ ] **Step 2: `phpstan.neon.dist`** — mirror `packages/document/phpstan.neon.dist` (includes + `scanDirectories` for `../contracts/src`, `../expression/src`, `../evaluation/src`, `../document/src`, `../runner/src`, `../protocol-http/src`).
- [ ] **Step 3: Pest bootstrap + arch smoke test**

`packages/protocol-soap/tests/Pest.php` — copy `packages/protocol-http/tests/Pest.php`. `packages/protocol-soap/tests/Architecture/ArchTest.php`:

```php
arch('protocol-soap is vendor-free and does not depend on the HTTP protocol')
    ->expect('Alama\Arazzo\Protocol\Soap')
    ->not->toUse('Alama\Arazzo\Protocol\Http')
    ->ignoring('Alama\Arazzo\Protocol\Soap\Laravel');
```

- [ ] **Step 4: Root plumbing** — same shape as F1.0 Step 4: repositories entry, root `require` `"alama/arazzo-protocol-soap": "@dev"`, autoload-dev `"Alama\\Arazzo\\Tests\\Protocol\\Soap\\": "packages/protocol-soap/tests"`, scripts `analyse-soap`/`test-soap` (+ compositions).
- [ ] **Step 5: Install + verify + commit**

```bash
composer update alama/arazzo-protocol-soap --with-dependencies --no-interaction
composer run analyse-soap && composer run test-soap
git add composer.json composer.lock packages/protocol-soap
git commit -m "build(protocol-soap): scaffold arazzo-protocol-soap (F2.0)"
```

---

## Task F2.1: `WsdlSourceNormalizer`

Read a WSDL 1.1/2.0 document (DOM, no vendor) and produce `ResolvedOperation`s for the core registry — the SOAP sibling of F1.1's `OpenApiSourceNormalizer`.

**Files created:**
- `packages/protocol-soap/src/Normalizer/WsdlSourceNormalizer.php`
- `packages/protocol-soap/tests/Normalizer/WsdlSourceNormalizerTest.php`
- `packages/protocol-soap/tests/Fixtures/{calculator.wsdl, order-service.wsdl}`

**Interfaces:**
- Implements `Alama\Arazzo\Contracts\...\SourceNormalizerInterface` (A2); consumes `Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver` + `Document\Parser\...` (document, unchanged) — no protocol-http types.

- [ ] **Step 1:** Class skeleton implementing `SourceNormalizerInterface::supports($source): bool` (WSDL sniffs `definition` root + `wsdl:` namespace / `definitions`), `normalize($source): NormalizedOpenApiOperation`? NO — normalize yields `Alama\Arazzo\Document\Normalizer\NormalizedOpenApiOperation` (F1 DTO, reused by value-slice for a shared shape).
- [ ] **Step 2:** `dom_import_simplexml`/`DOMDocument::loadXML` parse; extract `service` → `port` → `binding` (`@type` → portType/interface) → `operation` (`@name`) → `bindingOperation` (`soap:operation soapAction` = operation URL suffix; `soap:body use="literal"`). Build the `ResolvedOperation`'s `targetResolver`-compatible fields: target URL = `$port['location']` (+ optional per-step path), `method` = SOAP post, headers `Content-Type: text/xml; charset=utf-8` (+ `SOAPAction` from the soapAction attr). Include two-axis fields per D3c (`bodySchema`/`responseSchema` derived from the XSD types referenced by `message` parts — resolve via `types`/`schema` `import` best-effort; keep `null` when unresolvable with a `// note(doc): xsd import not inlined` in code).
- [ ] **Step 3:** Fixture WSDLs in `tests/Fixtures/` (handwritten minimal calculator + order-service with imports). Test: `supports()` true for WSDL strings/`{url: ...}` raw JSON values, false for plain JSON; `normalize()` returns a `NormalizedOpenApiOperation`/`ResolvedOperation` with expected target + soapAction + `portType` values.
- [ ] **Step 4:** Register into the registrar (F2.6) + `SoapServiceProvider`; for now instantiate + unit-test directly.
- [ ] **Step 5:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): WsdlSourceNormalizer (F2.1)"`.

---

## Task F2.2: `SoapOperationExecutor` + envelope builder + fault mapping

Execute a SOAP step: build the DOM envelope (SOAP 1.1 + 1.2), serialize the body from `ResolvedOperation`/step input, send via PSR-18, map HTTP/SOAP faults to `StepState`-compatible outcomes.

**Files created:**
- `packages/protocol-soap/src/Execution/SoapOperationExecutor.php`
- `packages/protocol-soap/src/Execution/SoapEnvelope.php` (`@internal`)
- `packages/protocol-soap/src/Execution/SoapFaultMapper.php`
- `packages/protocol-soap/tests/{Execution/SoapOperationExecutorTest.php, Execution/SoapEnvelopeTest.php, Execution/SoapFaultMapperTest.php}`

**Interfaces:**
- Implements the operation-executor plugin SPI (E4/A2 `OperationExecutorPluginInterface`); consumes runner `ExecutionEvaluationInput`/`ResponseTransferInterface` + contracts `StepState`; produces `SoapResponseTransfer` via the F2.3 mapper; PSR-18 `ClientInterface` + `RequestFactoryInterface` via constructor.

- [ ] **Step 1:** `SoapEnvelope` — `DOMDocument` builder: `<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">`, `<Header>` (correlation id when provided), `<Body><tns:{op} ...>` with params serialized from the step's resolved parameters (bool/string/number → text nodes; nested arrays → nested DOM). `__toString(): string` returns XML. SOAP 1.2 namespace switch when the resolved version says 1.2.
- [ ] **Step 2:** `SoapOperationExecutor::execute()` — resolve target URL + soapAction from the `ResolvedOperation` (or per-step overrides via `withInvocationTarget` pattern from core); build request (`POST`, `Content-Type: text/xml`, `SOAPAction` or `action` for 1.2); send via `ClientInterface`; on non-2xx → `SoapFaultMapper` outcome. Map `SoapFault` response bodies (HTTP 200 with `faultcode`/`faultstring`) into a `StepState` `failed` outcome carrying `{'soap.faultcode', 'soap.faultstring'}` in the transfer `meta` bag — reusing the response-transfer vocabulary (B, F2.3 key homes).
- [ ] **Step 3:** Tests with a fixture PSR-18 client (`FakeSoapClient` that echoes the request body / returns canned fault XML). Assert: envelope XML structure, soapAction header, fault mapping, success path returns a `SoapResponseTransfer` with the XML preserved in `view('xml')`.
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapOperationExecutor + envelope + fault mapping (F2.2)"`.

---

## Task F2.3: `SoapResponseTransfer` + `SoapResponseTransferMapper`

Translate a raw HTTP response (or SOAP body string) into the SOAP typed transfer so expression engines/replacements see SOAP-native fields. Per the D6 seam design this package ships its own transfer DTO implementing `ResponseTransferInterface` — contracts owns only the seam, not the SOAP facets.

**Files created:**
- `packages/protocol-soap/src/Transfer/SoapResponseTransfer.php`
- `packages/protocol-soap/src/Transfer/SoapResponseTransferMapper.php`
- `packages/protocol-soap/tests/Transfer/SoapResponseTransferTest.php`
- `packages/protocol-soap/tests/Transfer/SoapResponseTransferMapperTest.php`

**Interfaces:**
- Consumes contracts `ResponseTransferInterface` (seam, A6); produces `SoapResponseTransfer implements ResponseTransferInterface`: constructor takes `status(int|string|object|null)`, `headers(array)`, `rawBody(string)`, `views(array)` and `meta(array)` exactly like the generic `ResponseTransfer` (so the two differ only by type), with **documented key homes** — `view('xml')` = the parsed `DOMDocument` body envelope (roots B2 xpath criteria), `meta['soap.faultcode']`/`meta['soap.faultstring']` on faults.

- [ ] **Step 1:** Create `SoapResponseTransfer` — `final readonly`, `implements ResponseTransferInterface`, six public methods returning constructor state (mirror the generic implementation from A6; do not copy flat `json`/`xml`/`proto` props — facets stay in `views`, protocol metadata in `meta`).
- [ ] **Step 2:** `SoapResponseTransferMapper` — map 2xx responses to the transfer with `views: ['xml' => $dom]` (parsed `//Body/*`), `meta['soap.faultcode']`/`meta['soap.faultstring']` (XPath on the body when a fault), `headers` passthrough. Non-2xx / parse failure → transfer with `status` set to the raw status and a `meta['error']` entry (mirroring how HTTP's mapper signals failures through the same seam).
- [ ] **Step 3:** Tests (`SoapResponseTransferTest` + `SoapResponseTransferMapperTest`): DTO satisfies the seam (returns constructor state); success XML → `hasView('xml')` true + no fault meta keys; fault XML → `meta['soap.faultcode']`/`meta['soap.faultstring']` set; garbage body → `meta['error']` set.
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapResponseTransfer + SoapResponseTransferMapper (F2.3)"`.

---

## Task F2.4: `XPathReplacementTargetResolver`

SOAP steps replace request replacements of the form `xpath:{expr}` against the pending SOAP envelope/response — resolving targets inside DOM rather than JSON path.

**Files created:**
- `packages/protocol-soap/src/Resolver/XPathReplacementTargetResolver.php`
- `packages/protocol-soap/tests/Resolver/XPathReplacementTargetResolverTest.php`

**Interfaces:**
- Implements the replacement-target resolver SPI from Phase B/E (`ReplacementTargetResolverInterface` if E5 defined one; otherwise the `$target` resolution seam used by RequestCompiler in F1.2) — consume what the E plan produced on `main`, verbatim; if the SPI differs, note and adapt (Global Constraints).

- [ ] **Step 1:** `resolve(string $target, ResponseTransferInterface $transfer): mixed` — match `xpath:` prefix; `DOMXPath::query` over the transfer's `view('xml')` DOMDocument (guard with `hasView('xml')`); return node text (or node set → array of texts) so expressions render `{{ $request.xpath:... }}` values. Non-`xpath:` targets fall through to `null` (chain to any JSON-path resolver registered, lowest priority).
- [ ] **Step 2:** Tests: `xpath:/Envelope/Body/...` on a fixture `SoapResponseTransfer` (`views: ['xml' => $dom]`); fallthrough for non-xpath targets.
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XPathReplacementTargetResolver (F2.4)"`.

---

## Task F2.5: `XsdResponseValidator`

Validate a SOAP response body against a referenced XSD schema (`DOMDocument::schemaValidateSource`) — the SOAP analogue of `ResponseSchemaValidator` (F1.2), registry-fed as the SOAP default.

**Files created:**
- `packages/protocol-soap/src/Validation/XsdResponseValidator.php`
- `packages/protocol-soap/tests/Validation/XsdResponseValidatorTest.php`
- `packages/protocol-soap/tests/Fixtures/order.xsd`

**Interfaces:**
- Implements the contract `ResponseValidatorInterface` (E5); consumed by `ResponseValidatorDispatcher` (E) via priority.

- [ ] **Step 1:** `validate(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document): void` — pick the schema from `document`'s SOAP source (`ResolvedOperation` XSD reference) or the step's explicit `schemaUrl`; `DOMDocument::schemaValidateSource($xsd)`; throw a validation failure (same exception family `ResponseSchemaValidator` uses) with `libxml_get_errors()` details.
- [ ] **Step 2:** Tests: valid response → passes; invalid (missing element / wrong type) → throws with message; missing schema reference → passes-through (validation not applicable).
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XsdResponseValidator (F2.5)"`.

---

## Task F2.6: `SoapServiceProvider` + SOAP registrar + tags + Laravel test

Wire the whole SOAP slice into a composed app exactly like F1.3, under the same `arazzo.plugins.*` tag vocabulary.

**Files created:**
- `packages/protocol-soap/src/Laravel/SoapServiceProvider.php`
- `packages/protocol-soap/src/Registrar/SoapRegistrar.php` (`registerInto(SourceNormalizerRegistry|OperationExecutorRegistry|ResponseValidatorDispatcher)` — same shape as `ProtocolHttpRegistrar`)
- `packages/protocol-soap/config/arazzo.php` (SOAP tags: reuse `arazzo.plugins.operation_executor` default? NO — separate `arazzo-protocol-soap` tag key `arazzo.plugins.soap_operation_executor`, default `'arazzo.plugins.soap-operation-executor'`; the G3 `ProtocolDiscovery` picks it up via the tag list)
- `packages/protocol-soap/tests/Laravel/SoapServiceProviderTest.php`
- Modify `packages/protocol-soap/composer.json` — add `"illuminate/contracts": "^11.0||^12.0||^13.0"`, `"illuminate/support": "^11.0||^12.0||^13.0"`, `orchestra/testbench` stays in require-dev

**Interfaces:**
- Consumes: the same registry SPI + `ResponseValidatorDispatcher` (E). Produces: `Alama\Arazzo\Protocol\Soap\Laravel\SoapServiceProvider` + `SoapRegistrar`.

- [ ] **Step 1:** `SoapRegistrar` — register `WsdlSourceNormalizer`, `SoapOperationExecutor`, `XsdResponseValidator` into the core registries (constructor-injected like F1.3 Step 1); priority descending.
- [ ] **Step 2:** `SoapServiceProvider` — `mergeConfigFrom(__DIR__.'/../../config/arazzo.php', 'arazzo')`; bind `SoapOperationExecutor` + register the tag list from config; in `boot()` call `SoapRegistrar::registerInto(...)` with container-resolved registries. Keep only `Illuminate\Support\ServiceProvider` + container usage at the provider edge (F1-style arch test on `Alama\Arazzo\Protocol\Soap` ignores the `Laravel` dir).
- [ ] **Step 3:** Update `packages/protocol-soap/tests/Pest.php` with the harness so the provider test loads; `SoapServiceProviderTest` (testbench): container resolves `SoapOperationExecutor`, tagged services exist, `PreflightValidator` accepts the WSDL fixture through the container, end-to-end workflow execute via `FakeSoapClient` (assert the `xpath:` replacement worked + the transfer exposes `view('xml')`).
- [ ] **Step 4:** Wire into the F1 HTTP default only as an optional peer — the composed app works with EITHER protocol installed (nothing in `packages/laravel` references SOAP classes; `SoapServiceProvider` is auto-discovered from its own `extra.laravel.providers`). Confirm `rg "Protocol\\Soap" packages/laravel/src packages/document packages/runner` is empty.
- [ ] **Step 5:** Gates: `composer run test-soap && composer run analyse-soap && composer run test-laravel && composer run test` + `make verify` (root gate, no commits).
- [ ] **Step 6:** `git add -A`, commit `feat(protocol-soap): SoapServiceProvider + registrar + tags (F2.6)`.

---

## Task F2.7: Final SOAP verification gate

Same final-gate routine as F1.4.

- [ ] **Step 1:** Sweeps — `rg -n "Protocol\\Soap" packages/{document,runner,contracts,expression,evaluation}/src` empty; `rg -n "new (FakeSoap|SoapOperationExecutor)\(" packages/...src` only inside `protocol-soap`; `composer run format` zero-diff.
- [ ] **Step 2:** `composer run analyse && composer run test && make verify` — all green.
- [ ] **Step 3:** `git add -A && git commit -m "chore(protocol-soap): F2 verification gate green (F2.7)"`.
- [ ] **Step 4:** Report: `alama/arazzo-protocol-soap` installed via one `composer require` with ZERO core edits; WSDL→SOAP→XSD vertical slice exercised by `test-soap` + root gate; both protocol packages coexist (F1 HTTP default untouched).