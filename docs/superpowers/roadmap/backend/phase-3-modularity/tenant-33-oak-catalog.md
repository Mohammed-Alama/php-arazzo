# OAK Catalog Bridge

Category: **tenant** · Phase: **3-modularity** · Tier: **OSS bridge** (`alama/arazzo-oak`) + **Pro UI** (`arazzo-pro-catalog`)
Related: [ai-30 deterministic generator](../phase-0-ai/ai-30-openapi-deterministic-gen.md), [ai-32 designer agent](../phase-0-ai/ai-32-workflow-designer-agent.md)

## Problem

Jentic's OAK catalog (Apache-2.0) is 6000 APIs and 2000 published Arazzo workflows —
a curated library the PHP ecosystem cannot currently reach. Building a Stripe workflow from
scratch when Stripe already has a canonical OAK workflow is wasted effort. A first-class OAK
consumer turns this engine into "pip install → search → run" for the whole indexed API set.

## Feature

### OSS package: `alama/arazzo-oak`

```php
interface CatalogClientInterface
{
    public function search(string $query, array $filters = []): iterable; // ApiSummary
    public function fetchOpenApi(string $apiId): OpenApiDocument;
    public function fetchWorkflow(string $workflowId): ArazzoDocument;
    public function listWorkflowsFor(string $apiId): iterable; // WorkflowSummary
}
```

Implementations:

- `GithubOakClient` — hits the `jentic/oak` repo raw content endpoints with PSR-16 24h cache.
- `SelfHostedOakClient` — same interface, custom base URL (internal mirrors, air-gapped).

CLI: `arazzo oak:search "stripe"`, `arazzo oak:show <api-id>`, `arazzo oak:import <workflow-id>`.

### Pro package: `arazzo-pro-catalog`

- Filament `CatalogPage`: 6000-API card grid, filters, one-click install.
- `CredentialStoreInterface` — encrypted per-tenant credential vault (Laravel `encrypt()`,
  Symfony Secrets, Drupal Key). Engine runtime-injects credentials into the request; they
  are never serialized into workflow YAML on disk.
- "Suggested workflows" widget — scoped to APIs the tenant already has credentials for.
- Per-tenant usage analytics → upgrade prompts.

## Why phase 3

Foundational orchestration (0/1/2) and AI (0-ai) come first because they define the engine.
OAK is a supply-side amplifier — the more workflows the engine can execute out of the box,
the more valuable everything above it becomes. Delivering the catalog *after* the runtime is
production-grade compounds; before is a demo without a floor.

## Acceptance

- `alama/arazzo-oak` installable standalone with zero framework dependencies (pure PHP).
- Any of the top-100 OAK workflows imports + executes end-to-end given valid credentials.
- Pro catalog page passes Playwright: search → detail → install → run.

## Jentic coordination

- Upstream issue on `jentic/oak` proposing PHP as first-class consumer + our
  `CatalogClientInterface` as reference schema.
- Contribute conformance fixtures for cross-language OAK validation.
