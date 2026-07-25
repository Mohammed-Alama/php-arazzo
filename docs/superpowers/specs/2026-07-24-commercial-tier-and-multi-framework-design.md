# Commercial Tier + Multi-Framework Extraction — Design

**Status:** Draft (pending user review)
**Date:** 2026-07-24
**Author:** Mohammed Alama (with Claude Opus 4.7)
**Related:**
- `docs/superpowers/roadmap/ROADMAP.md`
- `jentic-ecosystem-comparison.md`
- `epistemic-analysis-and-implementation-plan.md`
- `arazzo-execution-and-ui-architecture.md`

## Purpose

Turn `alama/laravel-arazzo` from a Laravel-only OSS package into (a) a
framework-agnostic pure-PHP core with Laravel / Symfony / Drupal bridges,
and (b) an open-core commercial product sold to Laravel agencies through
Anystack, with a Filament plugin as the primary distribution surface for
the paid UI.

The core stays MIT. A separate `alama/arazzo-pro` monorepo publishes
proprietary packages under EULA, priced per developer seat on annual
subscription.

## Non-Goals

- Not building a hosted SaaS control plane in v1.
- Not building GraphQL or gRPC transport in v1.
- Not building a low-code marketplace for non-developers.
- Not implementing Arazzo → OpenAPI reverse generation.
- Not shipping Windows-native tooling; WSL2 acceptable.

## Constraints Captured From Brainstorm

| Constraint | Value |
|---|---|
| Target customer | Laravel agencies (per-seat annual, agency-standard) |
| License posture | Open-core: MIT core + proprietary EULA pro |
| Split intensity | Thin OSS, thick pro |
| Framework support | Laravel + Symfony + Drupal, via pure-PHP core + bridges |
| Distribution | Anystack (private Composer registry + Stripe billing) |
| Pricing | Per-developer-seat annual: $199 / $799 / $2,499 / $6,999 |
| Sequencing | Extract core → Laravel bridge parity → OSS Phase 0-1 gaps → first pro package → framework expansion |
| Repo topology | Two monorepos (`alama/arazzo` OSS + `alama/arazzo-pro` private) |
| Special UI surface | Filament v3 plugin (`alama/arazzo-pro-filament`) is primary agency-facing UX |
| OpenAPI generation | Tiered: deterministic (OSS) → AI-refined (pro) → visual designer (pro) |
| OAK catalog | Consumed via `alama/arazzo-oak` OSS bridge + `arazzo-pro-catalog` Filament UI |

## Section 1 — Repo & package topology

Two monorepos, each publishing multiple Composer packages via
`symplify/monorepo-builder`'s split workflow.

### `alama/arazzo` (public, MIT)

| Package | Purpose |
|---|---|
| `alama/arazzo-core` | Pure PHP 8.4. Parser, validator, executor, expression resolver, DTOs, in-memory reference impls. PSR interfaces only. |
| `alama/laravel-arazzo` | Laravel bridge: service provider, Eloquent adapters (optional), Laravel queue driver, Redis lock manager. |
| `alama/symfony-arazzo` | Symfony bundle: DI extension, Doctrine adapters (optional), Messenger queue driver, symfony/lock. |
| `alama/drupal-arazzo` | Drupal 10/11 module: service container hooks, Entity API adapters (optional), Drupal Queue API. |
| `alama/arazzo-oak` | OAK catalog client: search / fetch / import against `github.com/jentic/oak`. |
| `alama/pest-plugin-arazzo` | Pest 4 plugin for local workflow mocking. |

### `alama/arazzo-pro` (private, EULA)

| Package | Purpose |
|---|---|
| `arazzo-pro-persistence` | Production event store (Redis hot + DB cold, snapshots, replay, idempotency, dead-letter). |
| `arazzo-pro-saga` | Compensation engine + orchestrator + dynamic fan-out/fan-in. |
| `arazzo-pro-multitenancy` | Tenant context, storage prefixing, credential vault, egress allow-list, bounded-context bridges. |
| `arazzo-pro-observability` | Event ledger read-model API, time-travel state snapshots, waterfall trace API, Horizon/Telescope bridge, REPL hooks. |
| `arazzo-pro-ui` | Framework-agnostic React SPA served over HTTP against `-observability` API. |
| `arazzo-pro-filament` | Filament v3 plugin: resources, pages, widgets wrapping `-ui` + `-observability`. |
| `arazzo-pro-symfony-easyadmin` | (Phase E) Symfony EasyAdmin mirror of Filament plugin. |
| `arazzo-pro-drupal-admin` | (Phase E) Drupal admin sub-module. |
| `arazzo-pro-ai` | LLM-refined OpenAPI → Arazzo generator (OpenAI / Anthropic / Ollama backends). |
| `arazzo-pro-catalog` | Filament page for OAK 6000-API browser + one-click install + credential vault UX. |

### Monorepo layout

```
packages/
  core/            (composer.json → alama/arazzo-core)
  laravel/         (composer.json → alama/laravel-arazzo)
  symfony/         (composer.json → alama/symfony-arazzo)
  drupal/          (composer.json → alama/drupal-arazzo)
  oak/             (composer.json → alama/arazzo-oak)
  pest-plugin/     (composer.json → alama/pest-plugin-arazzo)
composer.json      (monorepo dev-only, path-repos to packages/*)
monorepo-builder.php
.github/workflows/split.yml
```

Existing git history preserved via `git filter-repo` on first split.

## Section 2 — Core boundary

### In `arazzo-core` (framework-agnostic)

- Parser + 39 validation rules
- DTOs: `Workflow`, `Step`, `SuccessCriterion`, `FailureAction`, `Parameter`, etc.
- ExpressionResolver + SymbolTable + JsonPathEvaluator + TypeCaster + SchemaValidator
- Executor: `Engine`, `StepExecutor`, `HttpStepExecutor` (uses PSR-18)
- Interfaces:
  - `QueueDriverInterface`
  - `LockManagerInterface`
  - `EventLedgerInterface`
  - `HotStateStoreInterface`
  - `DefinitionRegistryInterface`
  - `ExpressionResolverInterface`
  - `LicenseVerifierInterface`
- In-memory reference impls: `InMemoryQueueDriver`, `InMemoryLockManager`, `InMemoryEventLedger`, `InMemoryHotStateStore`, `InMemoryDefinitionRegistry`, `NullLicenseVerifier`
- CLI: `bin/arazzo` — PSR-3 stderr logger + in-memory drivers

### Out of core

- Config publishing, service providers, DI wiring
- Framework queue drivers (Laravel Queue, Symfony Messenger, Drupal Queue)
- Framework lock managers
- ORM adapters (Eloquent, Doctrine, Drupal Entity API) — optional
- Concrete HTTP client selection

### Core dependencies

```
php ^8.4
psr/log ^3
psr/http-client ^1
psr/http-factory ^1
psr/http-message ^2
psr/simple-cache ^3
psr/event-dispatcher ^1
psr/container ^2
softcreatr/jsonpath ^0.10
cebe/php-openapi ^1.7
symfony/yaml ^7
```

No Guzzle, no `illuminate/*`, no `spatie/laravel-package-tools`.

### API stability

Core interfaces are SemVer 2.0 with BC guarantee inside 1.x. Bridges depend
on `^1.0`. Breaking changes = 2.0 major + UPGRADE.md.

## Section 3 — Framework bridges

### `laravel-arazzo`

- `LaravelArazzoServiceProvider` — config, migrations (optional), views.
- Bindings:
  - `QueueDriverInterface` → `LaravelQueueDriver`
  - `LockManagerInterface` → `LaravelLockManager` (`Illuminate\Cache\Repository::lock`)
  - PSR-18 → Guzzle discovery
  - PSR-3 → Laravel `Log` channel
- Artisan: `arazzo:validate`, `arazzo:run`, `arazzo:list`
- Optional Eloquent adapters behind config flag `driver: memory|eloquent|pro`

### `symfony-arazzo`

- `ArazzoBundle` — DI extension + config tree
- Bindings:
  - `QueueDriverInterface` → `MessengerQueueDriver`
  - `LockManagerInterface` → `SymfonyLockManager` (`symfony/lock`)
  - PSR-18 → `symfony/http-client`
  - PSR-3 → Symfony logger
- Console: `arazzo:validate`, `arazzo:run`, `arazzo:list`
- Optional Doctrine adapters
- Config `config/packages/arazzo.yaml`

### `drupal-arazzo`

- Drupal 10/11 module (`arazzo.services.yml`, `arazzo.info.yml`)
- Bindings:
  - `QueueDriverInterface` → `DrupalQueueDriver` (`\Drupal\Core\Queue\QueueFactory`)
  - `LockManagerInterface` → `DrupalLockManager` (`\Drupal\Core\Lock\LockBackendInterface`)
  - PSR-18 → Drupal HTTP client
  - PSR-3 → Drupal logger channel
- Drush: `arazzo:validate`, `arazzo:run`, `arazzo:list`
- Optional Entity API adapter
- Config schema `config/schema/arazzo.schema.yml`

### `arazzo-pro-filament` (pro)

Requires `filament/filament ^3.2`, `alama/laravel-arazzo ^1`,
`alama/arazzo-pro-observability ^1`, `alama/arazzo-pro-ui ^1`.

Registers plugin via `->plugin(ArazzoPlugin::make())` on Filament panel provider.

**Resources:** `WorkflowResource`, `WorkflowRunResource`, `EventLedgerResource`
**Pages:** `TimeTravelPage`, `WaterfallPage`, `SagaTracingPage`, `WorkflowDesignerPage`, `CatalogPage` (from `arazzo-pro-catalog`)
**Widgets:** `RunsThroughputWidget`, `FailedRunsWidget`, `ActiveTenantsWidget`
Real-time via Laravel Reverb / Pusher if configured, else polling.
Filament policies integrate with any Laravel auth.

Nav group: `Workflows`.

Symfony EasyAdmin and Drupal admin mirrors land in Phase E.

## Section 4 — OSS / pro feature partition (29 roadmap items)

### OSS

| # | Feature | Where |
|---|---|---|
| 01 | Zero-Code Data Pipelining | `arazzo-core` |
| 03 (basic) | Async Control Flow — in-memory + framework queue driver | `arazzo-core` + bridges |
| 04 | Strict Runtime Schema Validation | `arazzo-core` (already shipped) |
| 12 | Local Mocking Engine (Pest) | `arazzo-core` + `pest-plugin-arazzo` |
| 14 | Pre-Flight Linter | `arazzo-core` (`arazzo lint` CLI) |
| — | Arazzo 1.1.0 spec support | `arazzo-core` |
| — | Basic workflow builder UI — static React Flow view + YAML edit (design-time only, no execution surface) | `laravel-arazzo` (already shipped `resources/js/arazzo-ui.jsx`). Pro `WorkflowDesignerPage` extends this component with generator integration, live validation, expression autocomplete, and run/audit hooks. |
| new | Deterministic OpenAPI → Arazzo generator | `arazzo-core` |
| new | OAK catalog bridge | `arazzo-oak` |

### Pro

| # | Feature | Package |
|---|---|---|
| 02 | CQRS & Event-Sourced Persistence | `arazzo-pro-persistence` |
| 03 (advanced) | Async — backpressure, priority queues, DLQ routing | `arazzo-pro-persistence` |
| 05 | Idempotency & Replay Safeguards | `arazzo-pro-persistence` |
| 06 | SLA Monitors & Dead Letter Workflows | `arazzo-pro-persistence` + `-observability` |
| 07 | Saga Compensation Engine | `arazzo-pro-saga` |
| 08 | Dynamic Fan-Out / Fan-In | `arazzo-pro-saga` |
| 09 | Cross-Module Bounded Context Bridges | `arazzo-pro-multitenancy` |
| 10 | AI Agent & Epistemic Protocol Routing | `arazzo-pro-ai` |
| 11 | Multi-Tenancy Isolation | `arazzo-pro-multitenancy` |
| 13 | Interactive REPL Debugging Hooks | `arazzo-pro-observability` |
| 15 | Graph Explorer (execution view) | `arazzo-pro-ui` |
| 16 | Event Ledger UI | `arazzo-pro-ui` + `-filament` |
| 17 | Payload Inspector | `arazzo-pro-ui` + `-filament` |
| 18 | Retry & Intervention Controls | `arazzo-pro-ui` + `-filament` |
| 19 | Interactive Time-Travel Debugger | `arazzo-pro-ui` + `-filament` |
| 20 | JSONPath Visual Diffing | `arazzo-pro-ui` |
| 21 | Webhook Payload Interception UI | `arazzo-pro-ui` + `-filament` |
| 22 | Blast Radius Analyzer | `arazzo-pro-ui` |
| 23 | Error Triage Board | `arazzo-pro-ui` + `-filament` |
| 24 | Golden Path Overlay | `arazzo-pro-ui` |
| 25 | Execution Waterfall Profiler | `arazzo-pro-ui` + `-filament` |
| 26 | Visual Version Diffing | `arazzo-pro-ui` |
| 27 | Visual Saga Tracing | `arazzo-pro-ui` + `-saga` |
| 28 | Ecosystem Bridge (Horizon / Telescope) | `arazzo-pro-observability` |
| 29 | Dry-Run Sandbox | `arazzo-pro-observability` |
| new | AI-refined OpenAPI generator | `arazzo-pro-ai` |
| new | Visual Workflow Designer | `arazzo-pro-ui` + `-filament` |
| new | OAK catalog browser + credential vault | `arazzo-pro-catalog` |
| new | Audit report export (PDF/CSV/JSON, sha256-signed) | `arazzo-pro-observability` |

**Defensibility:** ship pro-tier UI + Filament plugin fast enough that a
hostile MIT fork cannot catch up; keep core APIs stable and generous —
no BC breaks as competitive moat.

## Section 4b — OpenAPI generation + OAK catalog

### OpenAPI → Arazzo lifecycle (define / run / audit)

| Layer | Package | Tier |
|---|---|---|
| Deterministic scaffold | `arazzo-core::Generator\DeterministicGenerator` | OSS |
| AI refinement | `arazzo-pro-ai` (backends: OpenAI, Anthropic, Ollama) | Pro |
| Interactive designer | `arazzo-pro-ui` + `arazzo-pro-filament::WorkflowDesignerPage` | Pro |

**Flow:**

```
[OpenAPI spec] → DeterministicGenerator → scaffold.yaml
                                            ↓
                               (optional) AI refinement
                                            ↓
                       WorkflowDesigner (drag-drop) → workflow.yaml
                                            ↓
                                  Executor (OSS core)
                                            ↓
                               EventLedger (pro persistence)
                                            ↓
                          Audit UI (pro observability + Filament):
                          - immutable event stream per run
                          - input/output snapshots per step
                          - retries + failure reasons
                          - Filament auth ties events to a user
                          - export as compliance report
                            (PDF/CSV, sha256-signed event chain)
```

`arazzo:audit-export {run_id} --format=pdf|csv|json` deterministic report
per run, sha256-signed for tamper detection.

### Jentic OAK catalog integration

OAK (Apache-2.0) — 6000 APIs + 2000 workflows.

- `alama/arazzo-oak` (OSS):
  - `CatalogClientInterface`
    - `search(string $query, array $filters = []): iterable<ApiSummary>`
    - `fetchOpenApi(string $apiId): OpenApiDocument`
    - `fetchWorkflow(string $workflowId): ArazzoDocument`
    - `listWorkflowsFor(string $apiId): iterable<WorkflowSummary>`
  - Default: `GithubOakClient` (raw content + PSR-16 cache 24h)
  - Optional: `SelfHostedOakClient` for internal mirrors
  - CLI: `arazzo oak:search`, `oak:show`, `oak:import`

- `alama/arazzo-pro-catalog` (pro):
  - Filament `CatalogPage`: 6000-API card grid, filters, one-click install
  - `CredentialStoreInterface`: encrypted per-tenant credential vault (Laravel `encrypt()`, Symfony Secrets, Drupal Key module) — engine runtime-injects, never serialized into workflow YAML
  - "Suggested workflows" widget scoped to tenant credentials
  - Per-tenant usage analytics feed upgrade prompts

### Jentic coordination

Per `jentic-ecosystem-comparison.md` option 3:
- Upstream issue on `jentic/oak` proposing PHP as first-class consumer + our
  `CatalogClientInterface` as reference schema
- Contribute conformance fixtures (`arazzo-1.1-cross-protocol-saga.yaml`) for
  cross-language OAK validation
- Discord introduction — position this engine as PHP-ecosystem member of the
  Arazzo implementer set alongside `arazzo-engine` (Py) + `jentic-arazzo-tools`
  (TS)

### Roadmap impact

- Item #28 narrows to Horizon/Telescope only (pro observability).
- OAK integration becomes new roadmap item #30 in a future revision.
- Item #14 (linter) gains `--against-openapi=path.yaml` mode for drift detection.

## Section 5 — License-key + entitlement enforcement

Soft check on install/update. Hard check at boot for pro features. Never
phone home per-execution.

### Composer-time (soft, mandatory)

- Anystack issues per-customer license key + private Composer auth token.
- Customer `composer.json`:
  ```json
  {
    "repositories": [
      {"type": "composer", "url": "https://arazzo.repo.anystack.sh/{customer-id}"}
    ],
    "require": {"alama/arazzo-pro-persistence": "^1"}
  }
  ```
- No token → `composer install` 401 → cannot download. Primary gate.

### Runtime (hard, per pro feature)

- Each pro package includes Composer post-install script writing
  `vendor/alama/arazzo-pro-*/license.json`, signed by Anystack with the
  customer's key + expiry (ed25519).
- `arazzo-core::LicenseVerifierInterface`:
  ```php
  interface LicenseVerifierInterface
  {
      public function verifyOrThrow(string $feature): void;
      public function isValid(string $feature): bool;
      public function expiresAt(string $feature): ?\DateTimeImmutable;
  }
  ```
- Default OSS impl: `NullLicenseVerifier` (always valid).
- Pro packages replace the binding with `Ed25519LicenseVerifier` via their
  framework bridge integration (Laravel service provider, Symfony bundle
  extension, Drupal service subscriber). First-use call throws
  `LicenseException` at app boot on failure.
- Grace period: 30 days after expiry (log warning), then hard-fail.
- Clock skew tolerance: ±24h.

### Seat enforcement (Anystack-side)

- Rolling 30-day distinct-IP / machine-ID count per auth token.
- Overage → email customer, no runtime effect.
- Reason: hard runtime seat gates get bypassed (vendor/ committed to git,
  Docker images); punishing legit dev laptops is worse than the fraud.

### Offline / air-gapped

- Anystack "offline license bundle": 12-month signed JSON, cannot revoke,
  manual re-issue at renewal, priced higher. Enterprise-only.

### Explicit non-enforcement

- No per-execution phone-home.
- No obfuscation.
- No "expired = wipe data" — feature gate only; OSS core always reads data.

### Piracy realism

Ed25519 verifier ~200 LOC, patchable. Real leak vector = agencies sharing
one license across many devs. Seat-count telemetry + polite outreach handles
this. No legal action until systemic.

### Free trial

30-day full trial via Anystack, no card required. OSS core always free
forever without any Anystack token.

## Section 6 — Billing & customer lifecycle (Anystack)

### Products

| SKU | Seats | Includes | Price |
|---|---|---|---|
| `arazzo-pro-solo` | 1 | All pro packages | $199/yr |
| `arazzo-pro-team` | 5 | All + priority email support (48h weekdays) | $799/yr |
| `arazzo-pro-agency` | 25 | All + Slack Connect + 24h weekday SLA + roadmap input | $2,499/yr |
| `arazzo-pro-agency-plus` | 100 | All above + quarterly office hours | $6,999/yr |

Single policy bundling all `arazzo-pro-*` packages. No à la carte in v1.

### Sign-up flow

1. `arazzo.dev` (Laravel Statamic or Astro, separate repo `alama/arazzo-website`) → Buy CTA
2. Anystack hosted checkout (Stripe) — card + VAT ID
3. Anystack webhook → provision private Composer namespace `arazzo.repo.anystack.sh/{cust-id}` + auth token + license
4. Email install snippet (copy-paste `composer config` + `composer require`)

### Renewal / expiry

- Anystack auto-renews via Stripe
- Failed card → 3 retries over 7 days → grace license (still valid) → after 14d expires
- Composer downloads keep working for currently-installed version (no runtime hostage)
- Reminder emails at 30 / 14 / 3 days pre-expiry

### Downgrade / cancel

- Self-serve via Anystack portal
- Cancel: Composer downloads stop next billing period, runtime 30-day grace, then pro features hard-fail; OSS core continues

### Support intake

- Solo: GitHub Discussions on `alama/arazzo` (community + occasional maintainer)
- Team: `pro-support@arazzo.dev` (Help Scout or Front) — 48h weekdays
- Agency: private Slack Connect channel — 24h weekdays
- Agency+: quarterly 60-min video office hours + roadmap voting

### Invoicing / tax

- Stripe Tax handles VAT (EU MOSS), GST (AU), US sales tax
- Optional PO / bank-transfer / NET-30 for Agency+ via Anystack invoice mode

### Analytics on day 1

- Trial → paid conversion (Anystack native)
- MRR / ARR + churn (Anystack native)
- Most-installed pro package post-purchase (opt-out phone-home once per install with package name + license ID)
- Opt-in in-app telemetry via `arazzo-pro-observability` for Filament page views

### Refunds

30-day no-questions-asked on annual plans.

## Section 7 — Sequencing & release plan

**Solo-dev pace, 9-12 months to first revenue.**

### Phase A — Core extraction (weeks 1-4)

- A1: Monorepo (`symplify/monorepo-builder`) + GitHub Actions split
- A2: Move parser/validator/DTOs/expression/schema-validator to `packages/core/src/`, rename `Alama\Arazzo\*`, `git filter-repo` for blame
- A3: Extract all listed core interfaces + `LicenseVerifierInterface`
- A4: Ship in-memory reference impls
- A5: Rewire `packages/laravel/` as thin bridge, existing test suite green
- A6: Publish `arazzo-core 1.0.0-alpha` + `laravel-arazzo 2.0.0-alpha` (major bump = namespace change)

**Deliverable:** existing users upgrade via `composer require alama/laravel-arazzo:^2` + documented rename. Zero behavior change.

### Phase B — Fill OSS gaps (weeks 5-10)

- B1: Wire `LaravelQueueDriver`, `RedisLockManager`, deterministic executor with in-memory event ledger (already-scaffolded Phase 0)
- B2: OSS deterministic OpenAPI → Arazzo generator
- B3: OSS pre-flight linter
- B4: OSS OAK bridge + CLI
- B5: OSS Pest plugin
- B6: Arazzo 1.1.0 spec support merged
- B7: Announce on Laravel News + Twitter + Jentic Discord introduction

**Deliverable:** OSS engine production-viable standalone. Fork risk minimized.

### Phase C — First pro package + Anystack launch (weeks 11-16)

- C1: `arazzo-pro-persistence` (Redis hot + DB event store + snapshot/replay)
- C2: `arazzo-pro-observability` (event ledger query API + waterfall + time-travel state)
- C3: `arazzo-pro-ui` (React SPA served over HTTP)
- C4: `arazzo-pro-filament`
- C5: Anystack org + products + Stripe + marketing site
- C6: Ed25519 verifier + Composer post-install signing
- C7: Trial license flow + VitePress docs
- C8: **Public beta launch** — `persistence + observability + ui + filament` bundle. Target: 5 paid Solo licenses in month 1.

### Phase D — Saga + multi-tenancy pro (weeks 17-24)

- D1: `arazzo-pro-saga`
- D2: `arazzo-pro-multitenancy`
- D3: `arazzo-pro-catalog` (Filament OAK browser + credential vault UX)
- D4: Push Team + Agency tiers, first case study

### Phase E — Framework expansion (weeks 25-40)

- E1: `alama/symfony-arazzo` bridge — full Laravel-parity
- E2: Port pro packages for bridge specifics (Doctrine persistence adapter, Symfony Messenger queue driver)
- E3: `alama/arazzo-pro-symfony-easyadmin` plugin
- E4: `alama/drupal-arazzo` bridge + `-drupal-admin` sub-module
- E5: Announce on Symfony Blog + Drupal.org + Laravel News

### Phase F — AI + advanced pro (weeks 41+)

- F1: `arazzo-pro-ai` (LLM-refined generator)
- F2: Remaining pro observability UI (blast radius, saga tracing, waterfall profiler, JSONPath diffing, golden path overlay, error triage, dry-run sandbox)
- F3: Enterprise features (SSO, audit export enhancements, offline license bundle, on-prem support contracts)

### KPIs / gates

- End of A: existing users upgraded, no regressions, tests green
- End of B: ≥3 external contributors on OSS GitHub
- End of C: 5 paid Solo customers OR abandon commercial track (kill criterion)
- End of D: 20 total paid, ≥1 Agency tier
- End of E: ≥1 paying Symfony customer

### Risks

- R1 — Namespace rename breaks silent downstream users. Mitigation: `class_alias()` for 6 months + deprecation warnings.
- R2 — Jentic ships Laravel bridge before Phase C. Mitigation: engage in Phase B7; propose collaboration around OAK.
- R3 — Filament v4 breaks plugin API. Mitigation: track roadmap, pin `^3.2`, ship v4-compat release when it lands.
- R4 — Anystack outage during launch. Mitigation: static price page + `sales@arazzo.dev` fallback + Anystack SLA.

## Section 8 — Success criteria, testing, error handling

### Success criteria

**Technical:**
- Existing `alama/laravel-arazzo` users upgrade to 2.x with only namespace + composer.json edit; verified by current test suite green post-extraction.
- `arazzo-core` runs full workflow end-to-end with no framework code loaded; verified by `packages/core/tests/EndToEndCliTest.php` — no `illuminate/*`, no `symfony/*` in vendor/.
- Every pro package fails closed on tampered/missing license; verified by mutation test stripping signature bytes.
- Cross-framework parity: same workflow YAML + OpenAPI produces identical Arazzo scaffold under Laravel, Symfony, Drupal; verified by shared conformance fixtures.

**Commercial:**
- Phase C kill: ≥5 paid Solo by week 20 of launch. Below → freeze pro, revert to OSS + consulting.
- Phase D: MRR growth ≥15% MoM for 3 consecutive months → hire second dev; else stay solo.

### Testing

- **Core:** Pest 4 + Larastan, coverage 85%, mutation testing on expression resolver, schema validator, license verifier
- **Bridges:** Orchestra Testbench (Laravel), Symfony `KernelTestCase`, Drupal `KernelTestBase`
- **Pro:** private test suite in `alama/arazzo-pro` mono, links core via Composer path repo
- **Conformance:** `tests/conformance/` — YAML fixtures + expected-behavior JSON, run against every bridge in CI (and — if Jentic collaboration lands — cross-language against Python + TS)
- **License tampering:** adversarial suite (byte-flip, rollback, key substitution, disk replay) — all must fail closed
- **Playwright:** extend existing `playwright.config.js` for `arazzo-pro-ui` + `arazzo-pro-filament` end-to-end
- **Visual regression:** Chromatic or Percy on `arazzo-pro-ui`

### Error handling

- **Core:** throw typed exceptions (`ParserException`, `ValidationException`, `SchemaValidationException`, `ExpressionException`, `ExecutionException`). Never swallow. Always PSR-3.
- **Bridges:** catch at framework boundary (HTTP controller, console, queue worker) — translate to framework-appropriate response.
- **Pro observability:** every core exception is a first-class event in the ledger with stack trace, step context, retry count, decision.
- **License verifier:** `LicenseException` at boot — app halts with clear message rather than 500-ing on customer request at 3am.

### Threat model

| Attacker | Vector | Mitigation |
|---|---|---|
| Pirate customer | Extracts + shares `vendor/` | Seat telemetry + billing outreach |
| Hostile fork | Rebuilds pro under MIT | Filament + Anystack + support moat + OAK first-mover |
| Workflow-injection via untrusted OpenAPI | Crafted expression → RCE | Expression resolver already JSONPath-only (no PHP eval); reinforce with fuzz suite in Phase B |
| Credential exfiltration via workflow YAML | `{$credentials.api_key}` shipped to attacker URL | Credentials always runtime-injected, never in YAML; egress allow-list in `arazzo-pro-multitenancy` |

### Open questions (resolve during writing-plans)

- Exact Anystack pricing after competitive research (Filament plugins, Laravel Nova, JetBrains Toolbox)
- Credential vault: reuse `spatie/laravel-encrypted-cast` vs. framework-agnostic PSR-16-based abstraction (leaning latter)
- Docs site: VitePress vs. Docusaurus vs. Statamic + Peak
- Symfony bridge: solo build vs. community contributor recruit
- Signing key rotation cadence (annual vs. per-major) + revocation semantics if key compromised

## Next Steps

1. User reviews this spec.
2. On approval: invoke `superpowers:writing-plans` to produce
   `docs/superpowers/plans/2026-07-24-commercial-tier-and-multi-framework.md`
   with per-phase task decomposition, acceptance criteria, dependency graph.
3. Delete stale roadmap stubs that this spec supersedes once implementation
   plans land (per ROADMAP.md cleanup convention).
