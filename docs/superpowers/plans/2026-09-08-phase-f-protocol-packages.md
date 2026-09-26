# Phase F — Protocol packages + runner internal structure

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the vertical protocol packages that prove the "vertical slice, zero core edits" pattern (spec D4/D7/D9) — **F1 `alama/arazzo-protocol-http`** (the reference recipe every other protocol copies), **F4 `alama/arazzo-protocol-soap`**, **F5 `alama/arazzo-protocol-rpc`** — and, between them, restructure `packages/runner` itself (**F2**) into explicit `Sync\`/`Async\` namespaces with the facade at the root, then close the `laravel`/`cli` leak so the facade is actually the stable API (**F3** is the phase gate).

**Depends on Phase E.** Phase E extracts the four inner layers (`arazzo-runtime`, `arazzo-events`, `arazzo-request-pipeline`, `arazzo-engine`) *before* the OMS lands. This phase sits on top of that split: the protocol packages are the first consumers of the published seams, and `arazzo-runner` is the only layer allowed to depend on them. Hard precondition — if Phase E is not green on `main`, stop and flag it before starting F1.0.

**The DIP boundary this phase establishes (the whole point):** `arazzo-protocol-http` depends on `contracts`, `document`, `expression`, `evaluation`, `arazzo-request-pipeline` and `arazzo-engine` — and on **nothing in `arazzo-runner`**. That is the inversion. Before the split, the `Protocol/*` executors reached into `runner` internals: `ExecutionEvaluationInput`, `ReusableParameterResolver`, `ExecutionException`, `PendingCorrelationRegistryInterface`, `HttpClientInterface`. Phase E dissolved three of those five on its own (`ReusableParameterResolver` → request-pipeline, `ExecutionException` → engine, `HttpClientInterface` → contracts). F1.2 dissolves the remaining two by promoting `ExecutionEvaluationInput` and `PendingCorrelationRegistryInterface` into `contracts`, where they belong regardless — both are values/ports crossing the executor seam, not runner internals. After F1.2 the cycle is structurally impossible, and `arch('protocol-http does not depend on the runner')` makes it permanent.

**Package model (source of truth — mirrors the Phase E header):**

| Layer | Package | Created in | Depends on |
|---|---|---|---|
| 1 | `alama/arazzo-runtime` | Phase E1 | contracts |
| 1 | `alama/arazzo-events` | Phase E2 | contracts |
| 2 | `alama/arazzo-request-pipeline` | Phase E3 | contracts, document, expression, evaluation |
| 3 | `alama/arazzo-engine` | Phase E4 | contracts, evaluation, runtime |
| 5+6 | `alama/arazzo-runner` (`Sync\`/`Async\` + shared composition classes at the runner root) | **F2** | engine, request-pipeline, runtime, events, protocol-* |
| vertical | `alama/arazzo-protocol-http` | **F1** | contracts, document, expression, evaluation, request-pipeline, engine |
| vertical | `alama/arazzo-protocol-soap` | **F4** | same as http, minus Guzzle/cebe |
| vertical | `alama/arazzo-protocol-rpc` | **F5** | same as http |

**Task map:** F1.0–F1.4 `protocol-http` · F2.0–F2.3 runner restructure (`Sync\`/`Async\` split, actor-in-loop seams, `laravel`/`cli` leak fix, docs) · F3 phase gate · F4.0–F4.7 `protocol-soap` · F5.0–F5.2 `protocol-rpc` (scaffold, four-variant dispatch, provider + gate).

**Scope note (intentional deferral):** the design doc's Phase F spans HTTP, SOAP, RPC and GraphQL. This plan covers HTTP, SOAP and RPC. GraphQL stays deferred and needs its own phase plan written against the F1/F4 pattern. Phase H's RPC/GraphQL tasks (H3/H4) only exercise document-level parsing/validation fixtures — already covered by Phase D's `RpcStepRule`/`GraphQlStepRule` — not executor dispatch, so deferring the GraphQL executor package does not block H.

**Actor-in-loop creates no new package.** It is an execution-model concern (suspension/resumption, correlation, handoff) and lands inside `alama/arazzo-runner`'s `Async\` namespace (F2.1), plus a contracts-level interface if it needs a seam. The spec's `AwaitingActorInput`/`ActorInputReceived` states are already modelled by E6's `StepStateMachineEngine` in `arazzo-engine`.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), PHPStan ^2.0 level max (root composite `analyse-*` scripts), Laravel Pint, `guzzlehttp/guzzle ^7.9` + `cebe/php-openapi ^1.7` + `softcreatr/jsonpath ^0.10.0` (F1 only), DOM/libxml + PSR-18 `psr/http-client`/`psr/http-message`/`psr/http-factory` (F4 SOAP and F5 RPC).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md` — Phase F rows (F1, F2), decision table (D4, D7, D9), package map (lines 165-180), Public API impact (lines 618-623), Risks, sequencing (`#25`/`#62` → F1, `#17`/`#16` → SOAP). Split evidence: `docs/research/2026-09-26-runner-package-split-validation.md`. Protocol PR impact: `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`.

## Global Constraints

- **Prerequisites (hard preconditions).** This plan assumes **Phase A** (contracts ports A1–A6 incl. `PluginInterface`, `OperationExecutorPluginInterface`, `SourceNormalizerInterface`, `SourceNormalizerRegistryInterface`, `ResponseTransferInterface` + generic `ResponseTransfer`, `StepState`), **Phase B** (step-scoped grammar + transfer views), **Phase C** (expression/evaluation split, JsonPath as built-in default plugins), **Phase D** (`SourceNormalizerRegistry`, `OpenApiSourceNormalizer`, two-axis `ResolvedOperation`, `RuleSet` WSDL step rules) and **Phase E** (the four-layer split, then `OperationExecutorRegistry`, `ResponseValidatorDispatcher`, `StepStateMachineEngine`) have landed. If any are not on `main`, **stop and flag it before executing the first task** — exactly as the D/E plans gate on A/B/C. Where a later-phase registry does not exist at implementation time, use the concrete today-bound surface and note the hand-off. Per the spec's transfer seam (D6, phase A A6): each protocol package ships a **typed transfer DTO implementing `ResponseTransferInterface`** (`status(): mixed`, `headers(): array`, `rawBody(): mixed`, `hasView(string): bool`, `view(string): mixed`, `meta(): array`) — HTTP keeps the generic `ResponseTransfer`, SOAP ships `SoapResponseTransfer`, RPC ships `RpcResponseTransfer`. Concrete facets live in the DTO's `views`/`meta` bags under documented keys, never as flat constructor props.
- **The plan is the boss.** Follow the exact task order; only deviate where the code forces you to, and note the deviation in the commit message. No code/spec edits outside the files each task lists (except root/umbrella/Laravel `composer.json` require+repositories, and the Laravel wiring each task lists).
- **FQCN stability (D9).** `Alama\Arazzo\Document\Normalizer\ResolvedOperation` and `…\NormalizedOpenApiOperation` are the ONLY relocated types whose FQCNs do not change. They must exist in exactly one package after F1 (protocol-http) — never re-declared in `document`. All other relocated types move to `Alama\Arazzo\Protocol\Http\…` marked `@internal stays out of the advertised contract; not part of the public API surface`.
- **Layers 0–3 never import a protocol package (H2 invariant).** No `use Alama\Arazzo\Protocol\...` may appear under `packages/{contracts,expression,document,evaluation,runtime,events,request-pipeline,engine}/src`. `arazzo-runner` is the one package above them that legitimately does.
- **Direction of dependencies.** `arazzo-protocol-http` requires `contracts` + `document` + `expression` + `evaluation` + `arazzo-request-pipeline` + `arazzo-engine` — and **not** `arazzo-runner`. `arazzo-protocol-soap` and `arazzo-protocol-rpc` require the same set. No package under `alama/` requires `arazzo-runner` except the umbrella, `laravel` and `cli`.
- **Priority convention (locked with G1).** Higher `PluginInterface::priority()` = resolved earlier. All registries here sort descending.
- **No new code comments** unless explaining a priority/BC decision or the dual-PSR-4 map. Existing relocated docblocks stay.
- Every task's `--filter` runs `vendor/bin/pest packages/<pkg>/tests --filter "<name>"` from the repo root. Every task ends with its package suite green (`test-http`/`test-soap`/`test-rpc` + `test-runner`/`test-laravel`/`test-engine` where touched). Static analysis per task: `composer run analyse-http` / `analyse-soap` / `analyse-rpc` (plus the touched package's `analyse-*`).
- New-command plumbing (F1.0, and separately F4.0/F5.0 as the first task of their package) is the ONLY place that edits root `composer.json` `scripts`; later tasks only add `require`/`repositories` entries.
- **Arch guards in this phase are regression guards, not red-first cycles.** Each is committed with the move that establishes the boundary and verified to bite by temporarily introducing a forbidden import, confirming RED, then reverting. Do not claim a red-first cycle that does not exist.

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
    "description": "HTTP/OpenAPI + AsyncAPI vertical slice for alama/arazzo-core: normalizers, operation executors, transport, response validation. Reference protocol package.",
    "keywords": ["alama", "arazzo", "openapi", "asyncapi", "http", "protocol"],
    "license": "MIT",
    "repositories": [
        {"type": "path", "url": "../contracts"},
        {"type": "path", "url": "../document"},
        {"type": "path", "url": "../expression"},
        {"type": "path", "url": "../evaluation"},
        {"type": "path", "url": "../engine"},
        {"type": "path", "url": "../request-pipeline"}
    ],
    "require": {
        "php": "^8.4",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-engine": "@dev",
        "alama/arazzo-request-pipeline": "@dev",
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

Note what is **absent** from `require`: `alama/arazzo-runner`. That omission is the DIP boundary written as a manifest — the package cannot reach runner internals because it cannot resolve them. The dual PSR-4 map (`Alama\Arazzo\Document\Normalizer\ → src/Document/Normalizer/`) keeps the two DTO FQCNs byte-identical (D9); the `extra.laravel.providers` entry is the Laravel auto-discovery hook (the provider class ships in F1.3 — until then the entry is inert). `illuminate/contracts` + `illuminate/support` are required so the provider can extend `Illuminate\Support\ServiceProvider`; they are the package's only non-PSR/north-of-core deps besides the transport.

- [ ] **Step 2: Create the PHPStan config**

`packages/protocol-http/phpstan.neon.dist` — mirror `packages/document/phpstan.neon.dist` exactly, including the shared core rules and scan directories (the relocated types import document/engine/pipeline code, so the same scan list those packages use must resolve them):

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
        - ../engine/src
        - ../request-pipeline/src
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

arch('protocol-http does not depend on the runner')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner');

arch('protocol-http does not depend on Laravel runtime types in the slice core')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Illuminate\Support')
    ->ignoring('Alama\Arazzo\Protocol\Http\Laravel');
```

The first rule is the DIP guard, asserted from the very first commit so F1.2 cannot regress it silently. Both rules pass vacuously on the empty `src`; F1.2 verifies the first one bites before committing the move.

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

Move the normalizer-focused unit tests into the package so they run under `test-http`. The inventory below is the one verified on `main`; `OpenApiVersionDetectorTest` stays behind because the detector is not relocated:

```bash
git mv packages/document/tests/Normalizer/OpenApi30NormalizerTest.php   packages/protocol-http/tests/Normalizer/OpenApi30NormalizerTest.php
git mv packages/document/tests/Normalizer/OtherNormalizersTest.php      packages/protocol-http/tests/Normalizer/OtherNormalizersTest.php
git mv packages/document/tests/Resolver/Resolver/OpenApiOperationResolverVersionTest.php packages/protocol-http/tests/Normalizer/OpenApiOperationResolverVersionTest.php
```

Adjust the moved tests' namespaces to `Alama\Arazzo\Tests\Protocol\Http\Normalizer`. Two caveats worth stating rather than discovering mid-task:

- **`OtherNormalizersTest` is a mixed bag.** Before moving it, check which classes it exercises; anything it covers that stayed in `document` (e.g. `OpenApiVersionDetector`) must be split out into a remaining document test rather than dragging a document-only assertion into a package that cannot see the class.
- **`ResolvedOperationTest` and `OpenApiSourceNormalizerTest` do not exist on `main`.** If Phase D added them, move them here; if not, do not invent them — the DTOs move to `src/Document/Normalizer/` in this same task, so consider adding `ResolvedOperationTest` here to cover the relocated FQCN-preserved DTO, and leave source-normalizer coverage to D2's own tests.

Tests that exercise `document`'s own resolution through `DocumentInterface` (e.g. `DocumentCapabilitiesTest`) stay in `document` and are covered in F1.3.

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

## Task F1.2: Relocate the HTTP/AsyncAPI executors into `arazzo-protocol-http`; promote the two remaining seam types to contracts

Move the HTTP execution stack out of `runner` into `protocol-http`, and finish the DIP inversion by promoting the last two types the executors need out of `runner`.

**What is *not* moved here (it already moved in Phase E):** `RequestCompiler`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`, `ResponseSchemaValidator`, `ExpressionValueResolver`, `ExecutionExpressionResolver`, `IdempotencyKeyInjector`, `StepParameterMerger`, `ReusableParameterResolver` and `StepOutputExtractor` are already in `alazzo-request-pipeline` (E3). protocol-http consumes them from there; this task only rewrites their `use` statements. Do not move them again.

**What stays in `runner` deliberately:** `Protocol/SubWorkflowStepExecutor` — it is not a protocol. It implements Arazzo recursive-workflow semantics and delegates to `WorkflowExecutor`, which the engine may not depend on, so it belongs above the engine with the runners. (Its dead sibling `Protocol/SubWorkflowExecutor` and the dead `Protocol/ProtocolExecutorRegistry` were deleted in E0.)

**The two seam promotions (the point of this task):** after Phase E, the executors' runner-internal imports are down to two types. Both are values/ports that cross the executor seam and belong in contracts under the FLATTEN philosophy ("one place for interfaces"), so this task promotes them rather than leaving protocol-http reaching into the runner:

| Type | Today | Becomes | Why |
|---|---|---|---|
| `ExecutionEvaluationInput` | `Alama\Arazzo\Runner\Execution\Data\ExecutionEvaluationInput` | `Alama\Arazzo\Contracts\Execution\ExecutionEvaluationInput` | The value object every `StepProtocolExecutorInterface::execute()` receives. It is part of the executor contract, not runner plumbing. |
| `PendingCorrelationRegistryInterface` | `Alama\Arazzo\Runtime\State\Interfaces\PendingCorrelationRegistryInterface` (after E1) | `Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface` | A port. `AsyncApiStepExecutor` needs it to register a pending correlation; an executor should not depend on a store package to express "I am waiting". |

Phase E already dissolved the other three: `ReusableParameterResolver` → `alazzo-request-pipeline`, `ExecutionException` → `alazzo-engine`, `HttpClientInterface` → `alama/arazzo-contracts` (E0 Step 4).

**Files moved (git mv):**
- → `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php` (ns `Alama\Arazzo\Protocol\Http\Execution`)
- → `packages/protocol-http/src/Protocol/HttpStepExecutor.php` (ns `Alama\Arazzo\Protocol\Http\Protocol`)
- → `packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php` (same ns)
- → `packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` (canonical, see Step 2)

**Files moved into contracts (git mv):**
- `packages/runner/src/Execution/Data/ExecutionEvaluationInput.php` → `packages/contracts/src/Execution/ExecutionEvaluationInput.php`
- `packages/runtime/src/State/Interfaces/PendingCorrelationRegistryInterface.php` → `packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php`

**Files kept in runner (edit only):**
- `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — becomes a `@deprecated` BC alias (Step 2)
- `packages/runner/src/Execution/ExecutionGraphFactory.php` — stop defaulting protocol-http classes (Step 3)
- `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php` — stop defaulting them in `assemble()` (Step 4)
- `packages/runner/src/Execution/StepExecutor.php` — stop constructing pipeline classes directly (Step 3)
- `packages/runner/src/RunnerFacade.php`, `packages/runner/src/RunnerGraphBuilder.php` — forward the new executor seam (Step 3)
- `packages/runtime/src/State/{InMemoryStateStore,FileStateStore}.php` — implement the relocated `PendingCorrelationRegistryInterface` (Step 1)
- `packages/runner/phpstan.neon.dist` + `packages/document/phpstan.neon.dist` — add `../protocol-http/src` to `scanDirectories` (Step 5)

**Interfaces:**
- Produces (protocol-http): `Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface` (canonical signature of today's runner interface).
- Runner's `OpenApiExecutorInterface` FQCN is preserved as a deprecated alias so existing type-hints (`StepExecutor`, `AsyncGraphSeams`, `AsyncExecutionGraphAssembler`, tests) keep compiling without a core→protocol import.
- Produces (contracts): `ExecutionEvaluationInput` under `Alama\Arazzo\Contracts\Execution\`, `PendingCorrelationRegistryInterface` under `Alama\Arazzo\Contracts\Interfaces\`.

- [ ] **Step 1: Promote the two seam types into contracts**

```bash
mkdir -p packages/contracts/src/Execution
git mv packages/runner/src/Execution/Data/ExecutionEvaluationInput.php packages/contracts/src/Execution/ExecutionEvaluationInput.php
git mv packages/runtime/src/State/Interfaces/PendingCorrelationRegistryInterface.php packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php
```

Rewrite the namespaces: `Alama\Arazzo\Runner\Execution\Data` → `Alama\Arazzo\Contracts\Execution`, and `Alama\Arazzo\Runtime\State\Interfaces` → `Alama\Arazzo\Contracts\Interfaces`. Then fix every importer repo-wide:

```bash
rg -l 'Execution\\Data\\ExecutionEvaluationInput|Runtime\\State\\Interfaces\\PendingCorrelationRegistryInterface' packages/
```

`ExecutionEvaluationInput` is passed to and returned from executors, so expect hits in `Execution/StepExecutor.php`, `Execution/StepOutputExtractor.php` (now in request-pipeline), `Execution/SubWorkflowInvoker.php`, `Execution/StepOutcomeHandler.php`, `Execution/UnifiedStepCarrier.php`, `Protocol/*` executors and their tests. `PendingCorrelationRegistryInterface` is implemented by the two state stores in `arazzo-runtime` and consumed by `Execution/StepOutcomeHandler.php`, `Execution/CorrelationResumer.php`, `Execution/StepExecutionWorker.php`, `Protocol/AsyncApiStepExecutor.php` and the Laravel bindings — update the `implements` clauses on both stores to the contracts FQCN.

Run: `composer run test-contracts && composer run test-runtime && composer run test-runner && composer run test-laravel && composer run test-pipeline`

Expected: PASS. The move is behaviour-preserving; only namespaces change.

- [ ] **Step 2: `git mv` the three classes + rewrite namespaces**

```bash
mkdir -p packages/protocol-http/src/Execution/Interfaces packages/protocol-http/src/Protocol
git mv packages/runner/src/Execution/DefaultOpenApiExecutor.php packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php
git mv packages/runner/src/Protocol/HttpStepExecutor.php packages/protocol-http/src/Protocol/HttpStepExecutor.php
git mv packages/runner/src/Protocol/AsyncApiStepExecutor.php packages/protocol-http/src/Protocol/AsyncApiStepExecutor.php
```

Namespace changes: `Alama\Arazzo\Runner\Execution` → `Alama\Arazzo\Protocol\Http\Execution` for `DefaultOpenApiExecutor`; `Alama\Arazzo\Runner\Protocol` → `Alama\Arazzo\Protocol\Http\Protocol` for the two executors.

Then re-point every import in the three moved files:
- `HttpStepExecutor` / `AsyncApiStepExecutor`: `ExecutionEvaluationInput` and `PendingCorrelationRegistryInterface` now resolve from `Alama\Arazzo\Contracts\...` (Step 1); `ReusableParameterResolver` from `Alama\Arazzo\RequestPipeline\...`; `ExecutionException` from `Alama\Arazzo\Engine\Exceptions\...`; `HttpClientInterface` from `Alama\Arazzo\Contracts\Interfaces\...`. The pipeline collaborators (`RequestCompiler`, `ExpressionValueResolver`, `IdempotencyKeyInjector`) come from `Alama\Arazzo\RequestPipeline\...`; the same-package ones (`DefaultOpenApiExecutor`) drop their `use`.
- `DefaultOpenApiExecutor`: keeps its Guzzle imports (`GuzzleHttp\Psr7\Utils`, `GuzzleHttp\Client`) and `Alama\Arazzo\Document\Normalizer\{ResolvedOperation,NormalizedOpenApiOperation}` (FQCNs unchanged by D9); gains `Alama\Arazzo\RequestPipeline\...` for the compiler/serializer it drives.
- Add/keep `@internal stays out of the advertised contract; not part of the public API surface` on all three.

**The DIP check — protocol-http must now have zero runner references:**
```bash
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src || echo "DIP clean"
```
Expected: `DIP clean`. If anything remains, it is a runner type this task missed — move it to contracts or a lower package rather than adding a `scanDirectories` entry.

- [ ] **Step 3: Split `OpenApiExecutorInterface` into canonical (protocol-http) + BC alias (runner)**

`packages/protocol-http/src/Execution/Interfaces/OpenApiExecutorInterface.php` — canonical. Copy the exact current method set/signatures from `packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` verbatim, namespace `Alama\Arazzo\Protocol\Http\Execution\Interfaces`.

`packages/runner/src/Execution/Interfaces/OpenApiExecutorInterface.php` — keep the file and the identical method signatures so PHPStan/types line up, and mark:

```php
/**
 * @deprecated relocated to
 * Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface
 * in arazzo-protocol-http (Phase F1). Keep this FQCN as a BC alias.
 * @internal BC alias; register arazzo-protocol-http to obtain the canonical type.
 */
```

If PHPStan flags the alias as deprecated/unused, add the `@phpstan-ignore` with a comment, or `extends`/mirrors the canonical interface — whichever keeps the runner build green. Never remove the runner FQCN in this phase. (`StepProtocolExecutorInterface` already lives in contracts and needs no treatment; verify on `main` and note if that changed.)

**Verify the DIP guard bites before committing:**
```bash
# temporarily add a forbidden import to the moved executor
#   use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
composer run test-http   # expect RED on arch('protocol-http does not depend on the runner')
# revert the import, re-run, expect GREEN
```

- [ ] **Step 4: Remove protocol-http defaults from the runner's sync path**

`StepExecutor` can no longer `new RequestCompiler(new ExpressionValueResolver($this->engine), $this->engine)` — those are pipeline classes now, injected rather than constructed.

- `StepExecutor::__construct(OpenApiExecutorInterface $openApiExecutor, ExpressionEngine $engine)` keeps its signature; drop the pipeline constructions and take the collaborators via constructor injection instead. If that makes the signature unwieldy, group them behind a small `RequestPipelineFactory` in `alazzo-request-pipeline` and inject that — do **not** reach back into protocol-http to build them.
- `ExecutionGraphFactory`: replace the `$httpClient` param and its `new DefaultOpenApiExecutor(...)` / `new StepOutputExtractor(...)` / `new ResponseSchemaValidator(...)` defaults. New shape mirrors the async seams:

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

- `RunnerFacade`: forward the seam — `__construct` gains `?OpenApiExecutorInterface $openApiExecutor = null`, passed to `ExecutionGraphFactory`. Callers who omit it get the throw *at execution time* (accepted: standalone document/runner consumers must register `arazzo-protocol-http`).
- `RunnerGraphBuilder`: the factory receives `$this->openApiExecutor` (nullable), as it receives `$client` today — F1.3 feeds the real executor from Laravel.

- [ ] **Step 5: Remove protocol-http defaults from the async path**

`packages/runner/src/Execution/AsyncExecutionGraphAssembler.php::assemble()` currently does `new Client()`, `new HttpFactory()`, `new DefaultOpenApiExecutor($client, $factory, $seams->logger)` and default-constructs `StepOutputExtractor`/`ResponseSchemaValidator`. After this task, `assemble()` must not construct any `Alama\Arazzo\Protocol\...` type:

```php
$openApiExecutor = $seams->openApiExecutor
    ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
$expressionResolver = $seams->expressionResolver ?? new ExecutionExpressionResolver($this->engine, ...);
```

`AsyncGraphSeams::$openApiExecutor` keeps its `?OpenApiExecutorInterface` type (the `@deprecated` alias — same FQCN, no signature churn). Where `assemble()` additionally defaulted `StepOutputExtractor`/`ResponseSchemaValidator` for the AsyncAPI branch, move them behind `$seams` as nullable `?OutputExtractorInterface`/`?ResponseValidatorInterface` (contracts types, runner-legal) or feed the E10 `ResponseValidatorDispatcher` from its registry — choose whichever survives PHPStan at implementation time, and leave a `// cleans up in F1.3` marker where a concrete protocol-http instance must flow down from Laravel.

**Sweep guard (must be empty after this task):**
```bash
rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor)\(" packages/runner/src packages/document/src
rg -n "Protocol\\Http" packages/runner/src packages/document/src
```
Any hit in the first = a core→protocol construction; eliminate it (move the construction into protocol-http or behind a seam). The second must also be empty for `document`; `runner` may still name `Alama\Arazzo\Protocol\Http` after F2 resolves the assembler, but note any hit and carry it into F4.0's checklist.

- [ ] **Step 6: PHPStan scan directories**

In `packages/document/phpstan.neon.dist` AND `packages/runner/phpstan.neon.dist`, add to `scanDirectories`: `../protocol-http/src` (and keep `../evaluation/src`, `../engine/src`, `../request-pipeline/src` if Phase E added them). Rationale: relocated DTOs/types are imported by document/runner code (e.g. `DocumentInterface::resolveOperation(): ResolvedOperation` in document) and must resolve without a composer dependency. Analyse runs from repo root so `cebe/php-openapi` resolves via protocol-http's `require`.

- [ ] **Step 7: Move the tests that exercise relocated classes**

```bash
git mv packages/runner/tests/Execution/DefaultOpenApiExecutorTest.php packages/protocol-http/tests/Execution/DefaultOpenApiExecutorTest.php
git mv packages/runner/tests/Execution/HttpStepExecutorTest.php packages/protocol-http/tests/Protocol/HttpStepExecutorTest.php
git mv packages/runner/tests/Execution/AsyncApiStepExecutorTest.php packages/protocol-http/tests/Protocol/AsyncApiStepExecutorTest.php
git mv packages/runner/tests/Execution/AsyncApiSpecVersionBranchTest.php packages/protocol-http/tests/Protocol/AsyncApiSpecVersionBranchTest.php
```

Update namespaces in the moved tests to `Alama\Arazzo\Tests\Protocol\Http\Execution|Protocol`. The test fixtures/HTTP mocks they reference (PSR-18 fake clients, sample documents) move with them. Tests for the pipeline classes moved in E3 and are already under `packages/request-pipeline/tests`. **Do not move** `RunnerFacadeTest`/`ExecutionGraphFactoryTest`/`AsyncExecutionGraphAssemblerTest` — rewrite those in place to inject a small in-repo fake `OpenApiExecutorInterface` (runner tests may not require protocol-http; the real vertical slice is asserted under `test-laravel` + the final gate).

- [ ] **Step 8: Gates**

```bash
composer run test-http
composer run analyse-http
composer run test-runner
composer run analyse-runner
composer run test-document
composer run analyse-document
composer run test-contracts
composer run test-runtime
composer run test-pipeline
```

Expected: runner tests green using the fake executor; `rg "Protocol\\Http" packages/document/src` empty; sweep guard (Step 5) empty for document; `rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src` empty.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(protocol-http): relocate HTTP/AsyncAPI executors, promote executor seam types to contracts (F1.2)"
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
        // The step-protocol plugins, NOT DefaultOpenApiExecutor: the registry holds
        // OperationExecutorPluginInterface implementations keyed by protocol.
        $operationExecutorRegistry?->register(
            new HttpStepExecutor(/* openApiExecutor + pipeline collaborators */),
            new AsyncApiStepExecutor(/* openApiExecutor + pendingCorrelationRegistry */),
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

        // Tag the protocol plugins + normalizers + validators; the operation-executor
        // sub-tag is what OperationExecutorRegistry consumes (G3).
        $this->app->tag([/* normalizer + step-executor + validator services */], config('arazzo.plugins.tags.0', 'arazzo.plugins.*'));

        // OpenApiExecutorInterface is a *value* seam consumed by the executors above,
        // not a registry entry, so it binds directly to DefaultOpenApiExecutor.
        $this->app->singleton(OpenApiExecutorInterface::class, fn ($app) => $app->make(DefaultOpenApiExecutor::class));
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
- `FacadeBindings`: F1.2 Step 4 already replaced the `RunnerGraphBuilder`/`RunnerFacade` `$httpClient` seam with `?OpenApiExecutorInterface`, so this binding now passes `$app->make(OpenApiExecutorInterface::class)` into the builder and nothing else changes. If the `ClientInterface`/`RequestFactoryInterface` container bindings are left unused by that swap, leave them registered (they are public container bindings) and note them for a later cleanup — do not delete them in this task.
- The `OperationExecutorRegistry` filled in `boot()` already holds the two protocol plugins, so F2.0's assembler only has to *read* it; no extra wiring is needed here.

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
- Sweep: `rg -n 'Alama\\Arazzo\\Runner' packages/protocol-http/src` — must be empty. This is the DIP rule and the one that matters most; F1.2 Step 2 asserts it.
- Sweep: `rg -n "new (Client|HttpFactory|DefaultOpenApiExecutor)\(" packages/{document,runner,contracts,expression,evaluation}/src` — must be empty: neither core nor runner constructs protocol-http transport or executors. (`RequestCompiler`, `StepOutputExtractor` and `ResponseSchemaValidator` are constructed inside `alama-request-pipeline` since E3 and inside the protocol registrar, so they are deliberately absent from this sweep.)
- Sweep: `rg -n "use Alama\\\\Arazzo\\\\Protocol\\\\" packages/{contracts,expression,document,evaluation,runtime,events,request-pipeline,engine}/src` — must be empty (H2 on-disk invariant). `packages/runner` is intentionally **not** in this list: runner sits above the protocol packages and may name them; F2.0 Step 2 finishes removing the assembler's direct references by routing them through `OperationExecutorRegistry`.
- Sweep: `rg -n 'Arazzo\\\\Runner\\\\(Execution|Protocol)' packages/document/src` — must be empty. `packages/laravel` and `packages/cli` are excluded here and handled by F2.2.
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

Report: package `alama/arazzo-protocol-http` is the ONLY place Guzzle/cebe transport + OpenAPI normalizers + HTTP executors exist; `protocol-http` has no `Alama\Arazzo\Runner` import; no package below runner imports `Alama\Arazzo\Protocol\`; `make verify` green.

---

## Task F2.0: Split `runner`'s `Execution/` into `Sync\` and `Async\` namespaces

Phase E deliberately left `runner` with a single `Execution/` namespace because the package boundary was what mattered first. This task draws the internal line that the package boundary made possible, and it is where actor-in-loop work lands.

**Why sync and async share a package but not a namespace:** the three coupling dimensions all point to one package — integration strength is low (`StepExecutor` imports only `OpenApiExecutorInterface`; `WorkflowExecutor` imports only events/data/enum; `ExecutionGraphFactory` imports no runner internals at all, and there are zero sync→async imports), distance is short (same vocabulary, no translation layer), and volatility is identical (both change on any step-lifecycle edit, so splitting would duplicate the change rather than decouple it). Async also adds no package dependency — the queue arrives via `QueueDriverInterface`, HTTP via `HttpClientInterface` — so a sync-only user pays download size, not dependency weight. What the split *does* buy is a reviewable, test-enforced boundary, and that is enforced with namespaces plus arch rules rather than more composer packages.

**Moves:**

| Target | Classes |
|---|---|
| `packages/runner/src/Sync/` | `Execution/StepExecutor.php`, `Execution/WorkflowExecutor.php`, `Execution/ExecutionGraphFactory.php` |
| `packages/runner/src/Async/` | `Execution/StepExecutionWorker.php`, `Execution/StepOutcomeHandler.php`, `Execution/CorrelationResumer.php`, `Execution/SubWorkflowInvoker.php`, `Execution/AsyncExecutionGraphAssembler.php`, `Jobs/ExecuteStepJob.php`, `Jobs/ResumeCorrelationJob.php` |
| `packages/runner/src/` (root, shared) | `Execution/UnifiedStepCarrier.php` (E8), `OperationExecutorRegistry.php` (E9), `ResponseValidatorDispatcher.php` (E10), `RunnerFacade.php`, `RunnerGraphBuilder.php`, `AsyncGraphSeams.php`, `AsyncExecutionGraph.php`, `Execution/InMemoryDefinitionRegistry.php`, `Execution/SyncQueueDriver.php` |
| `packages/runner/src/Interfaces/` | `Execution/Interfaces/ProtocolExecutorRegistryInterface.php` — E9 deprecates it in favour of `OperationExecutorRegistry`, but the FQCN stays importable |
| `Protocol/SubWorkflowStepExecutor.php` | moves to `packages/runner/src/Sync/SubWorkflowStepExecutor.php` — it is Arazzo recursive-workflow semantics, not a protocol, and it delegates to `WorkflowExecutor`, so it belongs beside the sync path |

`Async/` is the destination directory name, but note the old dead `Async/` directory (6 classes) was deleted in E0 — do not confuse the two. The surviving async classes use a different set of names (`StepExecutionWorker`, `StepOutcomeHandler`, …), so the `git mv` below is unambiguous; verify with `git status` before committing.

`SubWorkflowInvoker` (async) and `SubWorkflowStepExecutor` (sync) are near-duplicates by name. They are *not* the same class and must not be merged in this task — the dead `Protocol/SubWorkflowExecutor` was the redundant third one and E0 removed it. Move each to its namespace and leave a note if you find them genuinely redundant.

**Files:**
- Move: as tabled above, plus matching tests
- Modify: every importer in `packages/runner`, `packages/laravel`, `packages/cli`

**Interfaces:**
- Namespaces `Alama\Arazzo\Runner\Sync\...` and `Alama\Arazzo\Runner\Async\...` are new; the facade classes stay at `Alama\Arazzo\Runner\...`.
- Arch guards (below) are the deliverable, not the moves.

- [ ] **Step 1: Move the classes and rewrite namespaces**

```bash
mkdir -p packages/runner/src/Sync packages/runner/src/Async
git mv packages/runner/src/Execution/StepExecutor.php            packages/runner/src/Sync/StepExecutor.php
git mv packages/runner/src/Execution/WorkflowExecutor.php        packages/runner/src/Sync/WorkflowExecutor.php
git mv packages/runner/src/Execution/ExecutionGraphFactory.php   packages/runner/src/Sync/ExecutionGraphFactory.php
git mv packages/runner/src/Protocol/SubWorkflowStepExecutor.php packages/runner/src/Sync/SubWorkflowStepExecutor.php

git mv packages/runner/src/Execution/StepExecutionWorker.php       packages/runner/src/Async/StepExecutionWorker.php
git mv packages/runner/src/Execution/StepOutcomeHandler.php       packages/runner/src/Async/StepOutcomeHandler.php
git mv packages/runner/src/Execution/CorrelationResumer.php       packages/runner/src/Async/CorrelationResumer.php
git mv packages/runner/src/Execution/SubWorkflowInvoker.php        packages/runner/src/Async/SubWorkflowInvoker.php
git mv packages/runner/src/Execution/AsyncExecutionGraphAssembler.php packages/runner/src/Async/AsyncExecutionGraphAssembler.php
git mv packages/runner/src/Jobs/ExecuteStepJob.php                packages/runner/src/Async/ExecuteStepJob.php
git mv packages/runner/src/Jobs/ResumeCorrelationJob.php           packages/runner/src/Async/ResumeCorrelationJob.php
rmdir packages/runner/src/Jobs 2>/dev/null || true
```

Namespace declarations: `namespace Alama\Arazzo\Runner\Execution;` → `namespace Alama\Arazzo\Runner\Sync;` (for the four sync classes) or `namespace Alama\Arazzo\Runner\Async;` (for the async ones), and `namespace Alama\Arazzo\Runner\Protocol;` → `namespace Alama\Arazzo\Runner\Sync;` for `SubWorkflowStepExecutor`. The root-namespace classes (`UnifiedStepCarrier`, `OperationExecutorRegistry`, `ResponseValidatorDispatcher`, the facade trio) lose their `use Alama\Arazzo\Runner\Execution\...` lines for the moved classes and gain `use Alama\Arazzo\Runner\Sync\...` / `use Alama\Arazzo\Runner\Async\...`.

Then fix importers repo-wide:
```bash
rg -l 'Alama\\Arazzo\\Runner\\Execution\\(StepExecutor|WorkflowExecutor|ExecutionGraphFactory|StepExecutionWorker|StepOutcomeHandler|CorrelationResumer|SubWorkflowInvoker|AsyncExecutionGraphAssembler)' packages/
```
Each match must be classified by hand — `Alama\Arazzo\Runner\Async\...` is correct for async callers, `Alama\Arazzo\Runner\Sync\...` for sync callers, and a root-namespace consumer (the facade) may import either.

Move the matching tests:
```bash
git mv packages/runner/tests/Execution/StepExecutorTest.php              packages/runner/tests/Sync/StepExecutorTest.php
git mv packages/runner/tests/Execution/WorkflowExecutorTest.php          packages/runner/tests/Sync/WorkflowExecutorTest.php
git mv packages/runner/tests/Execution/WorkflowExecutorEventsTest.php    packages/runner/tests/Sync/WorkflowExecutorEventsTest.php
git mv packages/runner/tests/Execution/SubWorkflowStepExecutorTest.php   packages/runner/tests/Sync/SubWorkflowStepExecutorTest.php
git mv packages/runner/tests/Execution/StepOutcomeSubWorkflowRoutingTest.php packages/runner/tests/Sync/StepOutcomeSubWorkflowRoutingTest.php

git mv packages/runner/tests/Execution/StepExecutionWorkerTest.php        packages/runner/tests/Async/StepExecutionWorkerTest.php
git mv packages/runner/tests/Execution/StepExecutionWorkerEventsTest.php  packages/runner/tests/Async/StepExecutionWorkerEventsTest.php
git mv packages/runner/tests/Execution/StepOutcomeHandlerTest.php         packages/runner/tests/Async/StepOutcomeHandlerTest.php
git mv packages/runner/tests/Execution/StepOutcomeHandlerEventsTest.php   packages/runner/tests/Async/StepOutcomeHandlerEventsTest.php
git mv packages/runner/tests/Execution/StepOutcomeRetrySemanticsTest.php   packages/runner/tests/Async/StepOutcomeRetrySemanticsTest.php
git mv packages/runner/tests/Execution/StepOutcomeSelectorOutputsTest.php  packages/runner/tests/Async/StepOutcomeSelectorOutputsTest.php
git mv packages/runner/tests/Execution/CorrelationResumerTest.php         packages/runner/tests/Async/CorrelationResumerTest.php
git mv packages/runner/tests/Execution/CorrelationResumerEventsTest.php   packages/runner/tests/Async/CorrelationResumerEventsTest.php
git mv packages/runner/tests/Execution/SubWorkflowInvokerTest.php          packages/runner/tests/Async/SubWorkflowInvokerTest.php
git mv packages/runner/tests/Execution/AsyncExecutionGraphAssemblerTest.php packages/runner/tests/Async/AsyncExecutionGraphAssemblerTest.php
```

Two things this list cannot express, so verify them by hand rather than trusting the commands above:

- **Event-suffix variants are per-class, not per-file-count.** Every `*EventsTest` / `*SemanticsTest` / `*SelectorOutputsTest` in `tests/Execution/` belongs to whichever class its subject names, and some (`StepOutcome*`) are listed above under `Async\`. Re-run `ls packages/runner/tests/Execution` after the moves and confirm nothing that references a moved class was left behind.
- **`tests/Async/` already exists on `main`** — it holds the 6 dead-class tests that E0 deletes. If E0 removed the directory this is moot; if it only removed the files, delete the empty directory first, or the `git mv` above fails on a non-empty target. There is no `tests/Jobs`; the job tests live under `tests/Execution/` and are not called out above, so check for them by name during the sweep.

Leave in `tests/Execution/` everything that belongs to a class this task did not move: the pipeline tests (E3 already moved them to `packages/request-pipeline`), `AdapterParityTest`, `ExecutionStateTest`, `LedgerRegressionTest`, `ReceiveTimeoutTest`, `SharedBudgetTest`, `StateMergeTest`, `WorkflowContextTest` and the `Validation/`+`Conformance/`+`State/` directories — then relocate them only if the class they exercise moved. Note `AsyncApiSpecVersionBranchTest` leaves for `protocol-http` in F1.2, not here.

Also relocate any remaining `Execution/Data/*` and `Execution/Enum/*` that belong to neither Sync nor Async to the runner root's `Data/` + `Enum/` (`ExecutionData` shapes are runner-level; the `Enum\TransitionType` belongs to whatever consumed it — if only `Sync` uses it, it moves to `Sync/Enum/`).

- [ ] **Step 2: Resolve the assembler's protocol imports through the registry**

`AsyncExecutionGraphAssembler` still hard-codes the protocol executor list (`$protocolExecutors = [$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor]`, importing the three `Alama\Arazzo\Protocol\Http\...` classes by name). E9 introduced `OperationExecutorRegistry` for exactly this; finish the job now that the executors live in a separate package:

- Replace the direct `new HttpStepExecutor(...)` / `new AsyncApiStepExecutor(...)` / `new SubWorkflowStepExecutor(...)` construction with construction done by whoever composes the graph, then `register()`ed into the injected `OperationExecutorRegistry`.
- `AsyncGraphSeams` gains `?OperationExecutorRegistry $executorRegistry = null` alongside the existing nullable seams; `RunnerFacade` and the Laravel `AsyncGraphResolver` populate it (F1.3 already builds these objects, so it is the natural home for the construction).
- If no registry is injected and the step needs a protocol executor, throw the same `ExecutionException` shape F1.2 uses for the missing `OpenApiExecutorInterface`, naming `arazzo-protocol-http` in the message.

- [ ] **Step 3: Add the internal arch guards**

`packages/runner/tests/Architecture/ArchTest.php` (create if it does not exist; E0's `Execution→Protocol` rule is superseded by the cross-package rule in F1.0 and should be removed or repointed here):

```php
arch('sync path is queue-free')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse(['Alama\Arazzo\Runner\Async', 'Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface']);

arch('async path may compose the sync path')
    ->expect('Alama\Arazzo\Runner\Async')
    ->toUse('Alama\Arazzo\Runner\Sync');

arch('nothing below the facade depends on the facade')
    ->expect(['Alama\Arazzo\Runner\Sync', 'Alama\Arazzo\Runner\Async'])
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');
```

The first rule is the one that matters: it keeps the fast path free of queue concerns, which is the whole reason sync and async are distinguishable at all. Verify it bites by temporarily importing an async class into `Sync/StepExecutor.php`, confirming RED, then reverting.

Note the second rule asserts `Async → Sync` is *allowed*; it documents the direction rather than forbidding it, so a future reader does not "fix" it.

- [ ] **Step 4: Gates + commit**

```bash
composer run test-runner && composer run analyse-runner
composer run test-laravel && composer run test-engine && composer run test-pipeline
git add -A
git commit -m "refactor(runner): split Execution/ into Sync\\ and Async\\ namespaces, resolve protocol executors via registry (F2.0)"
```

---

## Task F2.1: Actor-in-loop seams in the `Async\` namespace

The spec's actor-in-loop work (interaction steps, `AwaitingActorInput` → `ActorInputReceived`, handoff to a human, resumption) is an execution-model concern. It lands here, in `Async\`, plus at most one contracts-level interface — **no new package**.

**Why it belongs in `Async\` and not a package of its own:** it changes when and where a step runs and how a suspended step is resumed, both of which are already `Async\` concerns (`StepExecutionWorker`, `CorrelationResumer`, `StepOutcomeHandler`, the jobs). A new package would have exactly one consumer and no independent volatility.

**Files:**
- Create: `packages/runner/src/Async/ActorHandoffRegistry.php` (if the interaction flow needs a durable record of a handed-off step) — or extend `PendingCorrelationRegistryInterface` (contracts, promoted in F1.2) rather than adding a parallel port
- Modify: `packages/runner/src/Async/StepOutcomeHandler.php` — route the suspended interaction outcome to the handoff path instead of the retry path
- Modify: `packages/contracts/src/Interfaces/PendingCorrelationRegistryInterface.php` only if the interaction flow genuinely reuses that port; add a *separate* narrow interface here if it does not

**Interfaces:**
- E6's `StepStateMachineEngine` (in `arazzo-engine`) already owns the `AwaitingActorInput` / `ActorInputReceived` transitions — this task supplies the async side effects, not new states.
- If a durable handoff record needs a new port, it goes in `contracts/Interfaces/` per the FLATTEN philosophy, and the implementation goes in `arazzo-runtime`'s `State/`. Do not put a new port in the runner.

- [ ] **Step 1: Confirm the state machine already covers the transitions**

Read `packages/engine/src/StepStateMachineEngine.php` and verify `fromPending` (interaction bypass) and `fromAwaitingActorInput` / `fromActorInputReceived` exist as E6 specified. If they do, this task has no state work to do — proceed to Step 2. If they do not, stop and flag it: the state machine is E6's deliverable, not this task's.

- [ ] **Step 2: Implement the async handoff path**

Extend `StepOutcomeHandler` so a suspended outcome on an interaction step registers the pending correlation (or handoff record) and emits the existing `CorrelationPendingEvent` instead of scheduling a retry. Mirror the correlation-id scalar guard that E0 carried over from the deleted `Async/SuspensionHandler.php:47` — `is_scalar($evaluated) ? (string) $evaluated : ''`, not an unconditional cast.

- [ ] **Step 3: Test the handoff and resumption**

Add `packages/runner/tests/Async/ActorHandoffTest.php`: an interaction step that suspends writes the pending record and emits the event; resuming with the actor's input completes the step; a non-scalar correlation id produces an empty id rather than a cast error.

- [ ] **Step 4: Gates + commit**

```bash
composer run test-runner && composer run analyse-runner
git add -A
git commit -m "feat(runner): add actor-in-loop handoff and resumption to the Async namespace (F2.1)"
```

---

## Task F2.2: Close the `laravel` / `cli` facade leak

`packages/laravel` and `packages/cli` currently import ~26 `Alama\Arazzo\Runner\...` symbols, and among them are concretes that should never be reachable from a consumer: `Protocol\HttpStepExecutor`, `Protocol\AsyncApiStepExecutor`, `Protocol\SubWorkflowStepExecutor`, `Jobs\ExecuteStepJob`, `Jobs\ResumeCorrelationJob`, `Execution\StepExecutor`, `Execution\StepExecutionWorker`, `Execution\StepOutcomeHandler`, `Execution\CorrelationResumer`, `Execution\WorkflowEngine`, `Execution\WorkflowExecutor`, `Infrastructure\Interfaces\HttpClientInterface` and the `State\Interfaces\*` ports.

That is the leak: the facade exists (`RunnerFacade`, `RunnerFacadeInterface`, `RunnerGraphBuilder`, `RunnerGraphBuilderInterface`) and is bypassed anyway, which is why a separate `arazzo-runner-facade` package was rejected — an abstraction nobody honours is indirection without control. This task makes the facade real.

**Files:**
- Modify: `packages/laravel/src/**` — the `Bindings/*` registrars and `Support/AsyncGraphResolver.php`
- Modify: `packages/cli/src/**` — wherever it constructs the runner
- Modify: `packages/laravel/tests/**`, `packages/cli/tests/**` as needed
- Modify: root `composer.json` only if `packages/laravel` / `packages/cli` need new `require` entries for the protocol packages (they legitimately do — they are composition roots)

**Interfaces:**
- Produces: a documented public surface for consumers — `RunnerFacadeInterface`, `RunnerGraphBuilderInterface`, the `SyncQueueDriver` seam, and the plugin/registry entry points. Everything else becomes `@internal`.

- [ ] **Step 1: Inventory the leak precisely**

```bash
rg -o 'Alama\\Arazzo\\Runner\\[A-Za-z\\]*' packages/laravel/src packages/cli/src | sed 's/.*://' | sort -u
```

Save the list. Every symbol in it is either (a) facade/interface — allowed, (b) a contracts type re-exported through the runner — allowed, or (c) a concrete internal — must go.

- [ ] **Step 2: Replace concrete construction with facade calls**

In the Laravel bindings, stop `new`-ing `HttpStepExecutor` / `AsyncApiStepExecutor` / `SubWorkflowStepExecutor` / `StepExecutor` / `StepExecutionWorker` / `StepOutcomeHandler` / `CorrelationResumer`. Instead resolve the protocol objects from the container (F1.3's `ProtocolHttpRegistrar` already registers them) and hand them to the runner through the seams the facade exposes — `RunnerFacade::__construct(?OpenApiExecutorInterface, ?OperationExecutorRegistry, ?QueueDriverInterface, …)` and `AsyncGraphSeams`' nullable fields.

In the CLI, do the same: build the registry + normalizers + validators once, hand them to `RunnerGraphBuilder`, and stop reaching past it.

**Any consumer that genuinely needs a symbol not on the facade** gets it added to the facade (with a test) rather than imported directly. If a symbol resists that — say it is a Laravel binding detail — keep it out of `packages/laravel/src`'s public surface and note it in the commit message.

- [ ] **Step 3: Guard the leak so it does not come back**

Add to `packages/laravel/tests/Architecture/ArchTest.php` (and the equivalent in `packages/cli`):

```php
arch('laravel does not reach past the runner facade')
    ->expect('Alama\Arazzo\Laravel')
    ->not->toUse([
        'Alama\Arazzo\Runner\Sync\StepExecutor',
        'Alama\Arazzo\Runner\Async\StepExecutionWorker',
        'Alama\Arazzo\Runner\Async\StepOutcomeHandler',
        'Alama\Arazzo\Runner\Async\CorrelationResumer',
        'Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor',
        'Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor',
    ]);
```

Note the guard lists *specific* concretes rather than the whole `Alama\Arazzo\Protocol\Http` namespace: consumers are allowed to name protocol *interfaces* and the registrar, and a blanket namespace ban would forbid legitimate composition-root wiring. Verify it bites with a temporary import, then revert.

- [ ] **Step 4: Gates + commit**

```bash
composer run test-laravel && composer run analyse-laravel
composer run test-runner
make verify
git add -A
git commit -m "refactor(laravel,cli): consume the runner through its facade instead of concrete internals (F2.2)"
```

---

## Task F2.3: `AsyncGraphSeams` + facade documentation pass

With the namespaces settled and the leak closed, the composition surface is worth stating once so the next protocol package has a template.

**Files:**
- Create: `docs/architecture/07-runner-package-map.md` — the package/layer table from this plan's header, the Sync/Async namespace split, which package owns which seam, and the three arch rules that protect it. Follow the existing `docs/architecture/` convention: next free number after `06-laravel-integration.md`, with a mermaid diagram like its siblings.
- Modify: `docs/architecture/README.md` — add the `07` row to the index table **and** an `README --> D07` edge in the mermaid flowchart. The index is hand-maintained, so a new doc that is not listed there is effectively invisible.
- Create: `packages/runner/README.md` — the consumer-facing entry point: require `alama/arazzo-runner`, register a protocol package, get a `RunnerFacade`. Only `packages/core` and `packages/laravel` have READMEs today, so match `packages/laravel/README.md`'s shape rather than inventing a new one.

**Interfaces:**
- None. This task is documentation and must not change behaviour.

- [ ] **Step 1: Write the package map**

Include the layer table, the dependency direction per package, the Sync/Async rule with its rationale, the DIP rule (`protocol-*` must not depend on `runner`), and the list of arch tests that enforce each. Link `docs/research/2026-09-26-runner-package-split-validation.md` and `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md` as the evidence.

- [ ] **Step 2: Write the runner README**

Show the minimum wiring for a non-Laravel consumer, and for Laravel point at auto-discovery. Keep it short — this is a README, not the design doc.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "docs: add docs/architecture/07 runner package map, index it, add runner README (F2.3)"
```

---

## Task F3: Phase F gate — HTTP slice + runner structure + leak closed

**Files:**
- None to modify unless a gate fails.

**Interfaces:**
- Consumes: F1.0–F1.4, F2.0–F2.3.

- [ ] **Step 1: Run every suite**

```bash
composer run test-contracts && composer run test-expression && composer run test-evaluation
composer run test-document && composer run test-runtime && composer run test-events
composer run test-pipeline && composer run test-engine
composer run test-runner && composer run test-http && composer run test-laravel
```

Expected: PASS.

- [ ] **Step 2: Run static analysis everywhere**

```bash
composer run analyse
```

Expected: PASS (0 errors) across all registered `analyse-*` scripts.

- [ ] **Step 3: Verify the DIP boundary and the layer invariants by sweep**

```bash
# the DIP: no protocol package may reach into the runner
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-*/src || echo "DIP clean"
# layers 0-3 never import a protocol package
rg -n 'Alama\\Arazzo\\Protocol' packages/{contracts,expression,document,evaluation,runtime,events,request-pipeline,engine}/src || echo "layers clean"
# no package but the umbrella/laravel/cli requires arazzo-runner
rg -n '"alama/arazzo-runner"' packages/*/composer.json
# sync stays queue-free
rg -n 'QueueDriverInterface' packages/runner/src/Sync || echo "sync queue-free"
```

Expected: `DIP clean`, `layers clean`, `sync queue-free`, and the third sweep naming only `packages/laravel`, `packages/cli` and the umbrella `packages/core`.

- [ ] **Step 4: Run the repo gate**

Run: `make verify` (repo root)

Expected: PASS.

- [ ] **Step 5: Mark this plan's steps complete and record in the spec**

Flip every `- [ ]` for F1 and F2 tasks to `- [x]`. Add to `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md` under the Phase F heading:

```markdown
Phase F status (F1–F3): ✅ Implemented — `arazzo-protocol-http` extracted, `arazzo-runner` split into `Sync\`/`Async\`, `laravel`/`cli` facade leak closed. See `plans/2026-09-08-phase-f-protocol-packages.md`.
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: Phase F1-F3 gate green (protocol-http, runner structure, facade leak)"
```

---

## Task F4.0: `arazzo-protocol-soap` package scaffold + root plumbing

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
        {"type": "path", "url": "../engine"},
        {"type": "path", "url": "../request-pipeline"}
    ],
    "require": {
        "php": "^8.4",
        "ext-dom": "*",
        "ext-libxml": "*",
        "alama/arazzo-contracts": "@dev",
        "alama/arazzo-document": "@dev",
        "alama/arazzo-expression": "@dev",
        "alama/arazzo-evaluation": "@dev",
        "alama/arazzo-engine": "@dev",
        "alama/arazzo-request-pipeline": "@dev",
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

SOAP has no DTO-relocation concern → **single PSR-4 map** (unlike F1.0). No `illuminate` require needed until F4.6 (the provider dir can be mounted later; keep it vendor-free now — the `extra.laravel.providers` entry is inert until the class exists).

- [ ] **Step 2: `phpstan.neon.dist`** — mirror `packages/document/phpstan.neon.dist` (includes + `scanDirectories` for `../contracts/src`, `../expression/src`, `../evaluation/src`, `../document/src`, `../engine/src`, `../request-pipeline/src`, `../protocol-http/src`).
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
git commit -m "build(protocol-soap): scaffold arazzo-protocol-soap (F4.0)"
```

---

## Task F4.1: `WsdlSourceNormalizer`

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
- [ ] **Step 4:** Register into the registrar (F4.6) + `SoapServiceProvider`; for now instantiate + unit-test directly.
- [ ] **Step 5:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): WsdlSourceNormalizer (F4.1)"`.

---

## Task F4.2: `SoapOperationExecutor` + envelope builder + fault mapping

Execute a SOAP step: build the DOM envelope (SOAP 1.1 + 1.2), serialize the body from `ResolvedOperation`/step input, send via PSR-18, map HTTP/SOAP faults to `StepState`-compatible outcomes.

**Files created:**
- `packages/protocol-soap/src/Execution/SoapOperationExecutor.php`
- `packages/protocol-soap/src/Execution/SoapEnvelope.php` (`@internal`)
- `packages/protocol-soap/src/Execution/SoapFaultMapper.php`
- `packages/protocol-soap/tests/{Execution/SoapOperationExecutorTest.php, Execution/SoapEnvelopeTest.php, Execution/SoapFaultMapperTest.php}`

**Interfaces:**
- Implements the operation-executor plugin SPI (E9 `OperationExecutorRegistry` over A2 `OperationExecutorPluginInterface`); consumes contracts `ExecutionEvaluationInput` (promoted in F1.2) + `ResponseTransferInterface` + contracts `StepState`; produces `SoapResponseTransfer` via the F4.3 mapper; PSR-18 `ClientInterface` + `RequestFactoryInterface` via constructor.

- [ ] **Step 1:** `SoapEnvelope` — `DOMDocument` builder: `<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">`, `<Header>` (correlation id when provided), `<Body><tns:{op} ...>` with params serialized from the step's resolved parameters (bool/string/number → text nodes; nested arrays → nested DOM). `__toString(): string` returns XML. SOAP 1.2 namespace switch when the resolved version says 1.2.
- [ ] **Step 2:** `SoapOperationExecutor::execute()` — resolve target URL + soapAction from the `ResolvedOperation` (or per-step overrides via `withInvocationTarget` pattern from core); build request (`POST`, `Content-Type: text/xml`, `SOAPAction` or `action` for 1.2); send via `ClientInterface`; on non-2xx → `SoapFaultMapper` outcome. Map `SoapFault` response bodies (HTTP 200 with `faultcode`/`faultstring`) into a `StepState` `failed` outcome carrying `{'soap.faultcode', 'soap.faultstring'}` in the transfer `meta` bag — reusing the response-transfer vocabulary (B, F4.3 key homes).
- [ ] **Step 3:** Tests with a fixture PSR-18 client (`FakeSoapClient` that echoes the request body / returns canned fault XML). Assert: envelope XML structure, soapAction header, fault mapping, success path returns a `SoapResponseTransfer` with the XML preserved in `view('xml')`.
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapOperationExecutor + envelope + fault mapping (F4.2)"`.

---

## Task F4.3: `SoapResponseTransfer` + `SoapResponseTransferMapper`

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
- [ ] **Step 4:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): SoapResponseTransfer + SoapResponseTransferMapper (F4.3)"`.

---

## Task F4.4: `XPathReplacementTargetResolver`

SOAP steps replace request replacements of the form `xpath:{expr}` against the pending SOAP envelope/response — resolving targets inside DOM rather than JSON path.

**Files created:**
- `packages/protocol-soap/src/Resolver/XPathReplacementTargetResolver.php`
- `packages/protocol-soap/tests/Resolver/XPathReplacementTargetResolverTest.php`

**Interfaces:**
- Implements the replacement-target resolver SPI from Phase B/E (`ReplacementTargetResolverInterface`, already in contracts per Phase A; otherwise the `$target` resolution seam used by `Alama\Arazzo\RequestPipeline\RequestCompiler` from E3) — consume what the E plan produced on `main`, verbatim; if the SPI differs, note and adapt (Global Constraints).

- [ ] **Step 1:** `resolve(string $target, ResponseTransferInterface $transfer): mixed` — match `xpath:` prefix; `DOMXPath::query` over the transfer's `view('xml')` DOMDocument (guard with `hasView('xml')`); return node text (or node set → array of texts) so expressions render `{{ $request.xpath:... }}` values. Non-`xpath:` targets fall through to `null` (chain to any JSON-path resolver registered, lowest priority).
- [ ] **Step 2:** Tests: `xpath:/Envelope/Body/...` on a fixture `SoapResponseTransfer` (`views: ['xml' => $dom]`); fallthrough for non-xpath targets.
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XPathReplacementTargetResolver (F4.4)"`.

---

## Task F4.5: `XsdResponseValidator`

Validate a SOAP response body against a referenced XSD schema (`DOMDocument::schemaValidateSource`) — the SOAP analogue of `ResponseSchemaValidator` (F1.2), registry-fed as the SOAP default.

**Files created:**
- `packages/protocol-soap/src/Validation/XsdResponseValidator.php`
- `packages/protocol-soap/tests/Validation/XsdResponseValidatorTest.php`
- `packages/protocol-soap/tests/Fixtures/order.xsd`

**Interfaces:**
- Implements the contract `ResponseValidatorInterface`; consumed by E10's `ResponseValidatorDispatcher` via priority.

- [ ] **Step 1:** `validate(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document): void` — pick the schema from `document`'s SOAP source (`ResolvedOperation` XSD reference) or the step's explicit `schemaUrl`; `DOMDocument::schemaValidateSource($xsd)`; throw a validation failure (same exception family `ResponseSchemaValidator` uses) with `libxml_get_errors()` details.
- [ ] **Step 2:** Tests: valid response → passes; invalid (missing element / wrong type) → throws with message; missing schema reference → passes-through (validation not applicable).
- [ ] **Step 3:** `composer run analyse-soap && composer run test-soap`; `git commit -m "feat(protocol-soap): XsdResponseValidator (F4.5)"`.

---

## Task F4.6: `SoapServiceProvider` + SOAP registrar + tags + Laravel test

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
- [ ] **Step 6:** `git add -A`, commit `feat(protocol-soap): SoapServiceProvider + registrar + tags (F4.6)`.

---

## Task F4.7: Final SOAP verification gate

Same final-gate routine as F1.4.

- [ ] **Step 1:** Sweeps — `rg -n "Protocol\\Soap" packages/{contracts,expression,document,evaluation,runtime,events,request-pipeline,engine}/src` empty; `rg -n "new (FakeSoap|SoapOperationExecutor)\(" packages/...src` only inside `protocol-soap`; `composer run format` zero-diff.
- [ ] **Step 2:** `composer run analyse && composer run test && make verify` — all green.
- [ ] **Step 3:** `git add -A && git commit -m "chore(protocol-soap): F4 verification gate green (F4.7)"`.
- [ ] **Step 4:** Report: `alama/arazzo-protocol-soap` installed via one `composer require` with ZERO core edits; WSDL→SOAP→XSD vertical slice exercised by `test-soap` + root gate; both protocol packages coexist (F1 HTTP default untouched).
---

## Task F5.0: `arazzo-protocol-rpc` package scaffold + root plumbing

Create the third protocol package for Protocol-Buffer RPC (PR #556). One package, four internal protocol variants — the alternative (four packages) is rejected for the same reason `protocol-soap` is one package: they share `.proto` parsing and differ only in wire behaviour, so splitting would duplicate the parser and produce four near-identical packages with one consumer each. The `rpcProtocol` discriminator on the step object is the internal dispatch key, not a package boundary.

**The four variants this package must cover** (from `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md` §F2):

| `rpcProtocol` | Streaming | Content types | Status |
|---|---|---|---|
| `grpc` | unary, server, client, bidi | `application/grpc` (+ `+proto`/`+json`) | integer code |
| `grpc-web` | unary, server | `application/grpc-web*` | integer code |
| `twirp` | unary | `application/protobuf` or `application/json` | string error code |
| `connect` | unary, server, client, bidi | `application/proto`, `application/json`, `application/connect+proto`, `application/connect+json` | string error code |

**Files created:**
- `packages/protocol-rpc/composer.json`
- `packages/protocol-rpc/phpstan.neon.dist`
- `packages/protocol-rpc/src/` (empty `Normalizer/`, `Protocol/`, `Registrar/`, `Laravel/` + the arch test)
- `packages/protocol-rpc/tests/Architecture/ArchTest.php`
- `packages/protocol-rpc/tests/TestCase.php`
- `packages/protocol-rpc/tests/fixtures/`
- root `composer.json` — `test-rpc` / `analyse-rpc` scripts + path repositories

**Files edited:** root `composer.json`, root `phpstan.neon.dist` (if it enumerates packages), `.gitattributes` (if `export-ignore` is per-package).

**Interfaces:**
- Consumes: `SourceNormalizerRegistryInterface`, `OperationExecutorRegistry` (E9), `ResponseValidatorDispatcher` (E10), `HttpClientInterface` (contracts, E0), `ExecutionEvaluationInput` (contracts, F1.2).
- Produces: nothing yet — F5.1 produces the normalizer + executors.

- [ ] **Step 1: `composer.json`**

Mirror `packages/protocol-soap/composer.json` (F4.0) with the RPC dependency set. Path repositories: `../contracts`, `../expression`, `../document`, `../evaluation`, `../engine`, `../request-pipeline`. Requires: the umbrella contracts/document/expression/evaluation packages, `alama/arazzo-engine`, `alama/arazzo-request-pipeline`, a PSR-18 client + PSR-17/18 factories, and a `.proto` parser (choose the one the spec PRs assume; if none is forced, prefer a pure-PHP parser over a protoc binary dependency so the package installs without a toolchain).

**Do not require `alama/arazzo-runner`.** Add the `arch('protocol-rpc does not depend on the runner')` guard immediately, not at the end — the SOAP package F4.0 got this right, and the guard is cheap to add while the package is empty.

Single PSR-4 map on `Alama\Arazzo\Protocol\Rpc\` (no FQCN preservation needed here — nothing has moved out of `document` yet).

- [ ] **Step 2: Arch test + TestCase + fixtures**

```php
arch('protocol-rpc does not depend on the runner')
    ->expect('Alama\Arazzo\Protocol\Rpc')
    ->not->toUse('Alama\Arazzo\Runner');
```

Note this guard passes vacuously on an empty `src`; F5.1 is where it must be re-checked with real code (same trap as F1.0's HTTP guard).

Fixtures: copy the #556 documents named in the PR impact doc §H1 into `packages/protocol-rpc/tests/fixtures/` — `protobuf-grpc-workflows.arazzo.yaml`, `protobuf-grpc-web-steps.arazzo.yaml`, `protobuf-twirp-step.arazzo.yaml`, `protobuf-connectrpc-steps.arazzo.yaml`, `protobuf-hybrid.arazzo.yaml`, `protobuf-request-body-shapes.arazzo.yaml`, `protobuf-streaming-request-items.arazzo.yaml`, plus the parameter/common-fields fixture. Add a README row per the `add-fixture` convention. The 17 fail fixtures belong to the document/validator suites, not here — note them and file a follow-up rather than silently dropping them.

- [ ] **Step 3: Root plumbing**

Add `"test-rpc"` and `"analyse-rpc"` scripts mirroring `test-soap`/`analyse-soap`, and register the package in whatever root config enumerates packages.

- [ ] **Step 4: Gates + commit**

```bash
composer update alama/arazzo-protocol-rpc --with-dependencies --no-interaction
composer run analyse-rpc && composer run test-rpc
git add -A
git commit -m "build(protocol-rpc): scaffold arazzo-protocol-rpc (F5.0)"
```

---

## Task F5.1: `ProtoSourceNormalizer` + `RpcOperationExecutor` with four-variant dispatch

**This task has two unresolved upstream decisions that must be answered before the code is written.** Both are recorded as open in the PR impact doc; guessing here is what produces a package that cannot parse a real PR fixture.

1. **`ResolvedOperation` shape.** PR #556 gives RPC steps *both* a source type (`protobuf`) and an execution-level `rpcProtocol` discriminator (`grpc`/`grpc-web`/`twirp`/`connect`), while our `ResolvedOperation.binding` is a single string. Recommended: give `ResolvedOperation` two fields — `sourceType` (from the source description) and `rpcProtocol` — and keep `binding` as the derived convenience value the rest of the codebase already reads. If that is too wide a change for this phase, the minimum viable shape is `binding` carrying `protobuf/grpc` style values; decide explicitly and write the decision into the spec, because both SOAP (F4) and RPC consume `binding`.
2. **Expression grammar clash.** PR #556 introduces `$response.status` as a *structured object* with `#/code`, `#/message`, `#/details`, plus `$response.metadata.*`, `$response.trailingMetadata.*` and `$request.metadata.*`. Our design uses `$response.status` for a plain HTTP status number and `$response.meta.*` as the generic bag. Recommended: keep the HTTP meaning intact for HTTP/AsyncAPI steps and introduce the structured object **only** for RPC steps, documenting the divergence in `docs/` — the alternative (changing `$response.status` globally) breaks every existing HTTP fixture for the benefit of one protocol.

**Files created:**
- `packages/protocol-rpc/src/Normalizer/ProtoSourceNormalizer.php`
- `packages/protocol-rpc/src/Normalizer/ProtoCompiler.php` (`.proto` → service/method descriptors)
- `packages/protocol-rpc/src/Protocol/RpcOperationExecutor.php` (dispatches on `rpcProtocol`)
- `packages/protocol-rpc/src/Protocol/Grpc/{GrpcCodec,GrpcStatus}.php`
- `packages/protocol-rpc/src/Protocol/Twirp/{TwirpCodec,TwirpErrorMapper}.php`
- `packages/protocol-rpc/src/Protocol/Connect/{ConnectCodec,ConnectErrorMapper}.php`
- matching tests + fixtures

**Interfaces:**
- Implements: the operation-executor plugin SPI (E9 `OperationExecutorRegistry` over A2 `OperationExecutorPluginInterface`); consumes contracts `ExecutionEvaluationInput` (promoted in F1.2) + `StepState`, and `HttpClientInterface` via constructor injection.
- Produces: `RpcStatus` (code + message + details) for the `$response.status` object, and per-protocol metadata bags for `$request.metadata` / `$response.metadata` / `$response.trailingMetadata`.

- [ ] **Step 1: Source normalization**

`ProtoSourceNormalizer` implements `SourceNormalizerInterface`: parse the `.proto` files from the source description, index services and methods, and resolve `rpcMethod` (`<fully-qualified-service-name>/<method-name>`) using PR #556's priority: (1) `operationId`/`rpcMethod`/`workflowId`, (2) field names. Return a `ResolvedOperation` carrying the decision from open item 1 above, plus the `rpcMethod` and `rpcProtocol` the executor will dispatch on.

- [ ] **Step 2: Executors and codecs**

`RpcOperationExecutor` implements the same plugin SPI as `HttpStepExecutor`/`SoapOperationExecutor` (F1.2, F4.2), consumes `ExecutionEvaluationInput` + `HttpClientInterface`, and delegates wire encoding to the codec for the resolved `rpcProtocol`. gRPC framing (length-prefixed messages, `grpc-status` trailers), gRPC-Web trailers, Twirp's standard-HTTP-plus-`application/protobuf` encoding and Connect's dual proto/JSON encoding each get their own codec class — the shared surface is the message model, not the bytes.

Status and error mapping: gRPC/gRPC-Web produce the integer `$response.status#/code`; Twirp and Connect produce a string error code. Do not normalise these to one type — the PR treats them as distinct, and fixtures will assert the distinction.

- [ ] **Step 3: Streaming and the async runner**

A streaming RPC step is a long-lived pending operation, which is the actor-in-loop shape from F2.1, not a new mechanism: client-streaming and bidi gRPC/Connect steps register a pending correlation via the contracts `PendingCorrelationRegistryInterface` and resume through the `Async\` path. Unary and server-streaming steps complete within the step. Keep this explicit in the code and in the README — it is the main architectural consequence of RPC living beside an async runner.

- [ ] **Step 4: Tests**

One test per variant against the #556 fixtures, plus a rejection test per unsupported combination (gRPC-Web client-streaming and bidi, Twirp streaming) asserting a clear `ExecutionException` rather than a silent success. Add a `test-rpc`-level test that all four `rpcProtocol` values are reachable through one installed package.

- [ ] **Step 5: Gates + commit**

```bash
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-rpc/src || echo "DIP clean"
composer run analyse-rpc && composer run test-rpc
git add -A
git commit -m "feat(protocol-rpc): ProtoSourceNormalizer + RpcOperationExecutor with four-variant dispatch (F5.1)"
```

---

## Task F5.2: `RpcServiceProvider` + registrar + tags + final RPC gate

**Files created:**
- `packages/protocol-rpc/src/Registrar/RpcRegistrar.php`
- `packages/protocol-rpc/src/Laravel/RpcServiceProvider.php`
- `packages/protocol-rpc/tests/Laravel/RpcServiceProviderTest.php`

**Files edited:** `packages/laravel/composer.json` (optional `require` + path repository — the *recommended* install is opt-in, unlike HTTP which is the default; see below), `packages/laravel/src/Bindings/ResolverBindings.php` (protobuf source normalizer registration if the app opts in).

- [ ] **Step 1: Registrar + provider**

Copy the F4.6 SOAP shape exactly: framework-agnostic `RpcRegistrar::registerInto(SourceNormalizerRegistryInterface, OperationExecutorRegistry, ResponseValidatorDispatcher)` plus a thin `RpcServiceProvider` that tags the normalizer/codecs/executor and binds the `HttpClientInterface`/protobuf loader singletons.

- [ ] **Step 2: Default or opt-in?**

HTTP is the default in `laravel-arazzo` (F1.3). RPC should **not** be: it adds a protobuf parser dependency and four wire variants, and a consumer with no protobuf source descriptions pays for it. Ship it as an explicit `composer require alama/arazzo-protocol-rpc` that auto-discovers its provider. If that is inconsistent with the SOAP decision, make the same choice for both and record it in the spec — but do not make protobuf a default.

- [ ] **Step 3: Final RPC gate**

```bash
rg -n "Protocol\\Rpc" packages/{contracts,expression,document,evaluation,runtime,events,request-pipeline,engine}/src   # empty
rg -n 'Alama\\Arazzo\\Runner' packages/protocol-rpc/src                                                     # empty
composer run format                                              # zero diffs
composer run analyse && composer run test && make verify
```

Expected: all green with `alama/arazzo-protocol-http` still installed and unchanged. Report: three protocol packages coexist; each installs with one `composer require`; no core package imports any `Alama\Arazzo\Protocol\` type; `runner` names protocol packages only through `OperationExecutorRegistry` and the facade seams.

- [ ] **Step 4: Record the deferred work**

Append to the spec's Phase F section: GraphQL remains unimplemented (PR #567), including the `$sourceDescriptions.<name>` whole-source expression form that our grammar does not yet accept (PR impact doc §F3); the interaction fixtures from #568 (PR impact doc §H1) are still unpulled; the 17 #556 fail fixtures are pending a validator ticket.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(protocol-rpc): provider, registrar, tags + RPC verification gate (F5.2)"
```
