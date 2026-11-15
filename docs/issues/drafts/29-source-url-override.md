# Draft issue 29 — Source URL override for OpenAPI source descriptions

- **Suggested title:** `[Feature] Override the base URL of a source description at execution time`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `protocol-http`, `sources`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/protocol-http` `DefaultOpenApiExecutor` base URL; `packages/sources` `OpenApi30Normalizer::resolveServerUrl()`; `packages/runner` `StepExecutor`.
- **Arazzo spec version:** independent of spec version; affects execution against an OpenAPI source.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — without a server override, execution always hits `servers[0]` and cannot target staging/prod/different hosts without editing the source document.

### 📝 2. Problem Statement & Intent

`DefaultOpenApiExecutor` derives the base URL exclusively from `openApi->servers[0]->url` (`DefaultOpenApiExecutor.php:54-57`), then builds `$url = $baseUrl.$urlPath` (:93). There is no way to override the base URL at execution time without editing the source document:

- `OpenApi30Normalizer::resolveServerUrl()` resolves operation/path/document-level servers at parse time, but the result is baked into `NormalizedOpenApiOperation::resolvedServerUrl` and never overridden by the runner.
- No per-source or per-description server override mapping exists in the execution layer.

Expected: execution accepts a server-override map keyed by source description `name` that overrides the base URL for that source, taking precedence over `servers[0]`, with fallback to the existing precedence (operation → path → document servers) when no override matches.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execution accepts a server-override map keyed by source description `name`.
- Override wins over `servers[0]`; falls back to existing precedence (operation → path → document servers) when no override matches.
- Invalid URLs rejected before execution with a clear error.

**Non-goals**

- Per-request (per-step) server override; only per-source-description.
- OpenAPI server `variables` rewriting — out of scope for v1.
- CLI flags (`--server name=url` on `RunCommand`) — tracked separately in draft 39.

**Prior art in this repository**

- `packages/sources/src/Normalizer/OpenApi30Normalizer.php:65` — `resolveServerUrl()` already implements operation/path/document precedence at parse time.
- `packages/document/src/NormalizedOpenApiOperation.php` — `resolvedServerUrl` field carries the resolved base URL.

### 📄 4. Structural Artifacts & Raw Inputs

```php
// An execution context carries server overrides keyed by source name:
$overrides = ['api' => 'https://prod.example.com'];
$executor->execute($handle, $payload, serverOverrides: $overrides);
```

Minimal document:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api
    url: https://staging.example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: get
        operationId: $sourceDescriptions.api.getThing
```

Observed without override: request goes to `https://staging.example.com/...` (`servers[0]`).
Expected with override `api=https://prod.example.com`: request goes to `https://prod.example.com/...`.

### 🌀 5. Runtime Execution State Context

- Executor: `DefaultOpenApiExecutor::execute()` computes `$baseUrl` from `servers[0]`; the override must be injected before `$url = $baseUrl.$urlPath`.
- The override is keyed by source description `name`; if multiple source descriptions exist, each can be overridden independently.
- `OpenApiOperationHandle->operation->source->name` provides the key at execute time.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Allow execution to override the base URL of a source description at execution time, keyed by source description `name`, so workflows can target staging/prod/different hosts without editing the source document.

**Architectural Boundary:** Bounded context = HTTP execution + source resolution. Packages: `packages/protocol-http` (`DefaultOpenApiExecutor` base URL), `packages/sources` (`OpenApi30Normalizer::resolveServerUrl()`), `packages/runner` (`StepExecutor`). Port-agnostic; CLI port tracked in draft 39.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Override lookup is O(1) via hash map keyed by source name; no per-request overhead beyond map lookup.
- [ ] **Flexible:** Override map keyed by source `name`; falls back to existing precedence (operation → path → document servers) when no override matches; per-source independent overrides.
- [ ] **Maintainable:** Additive executor parameter (`DefaultOpenApiExecutor` is `@internal`); no public contract break; invalid URLs rejected before execution.
- [ ] **Operable:** Override takes precedence over `servers[0]`; clear error on invalid URL; rollback by removing override parameter (`servers[0]` behavior unchanged).
- [ ] **Reliable:** Deterministic URL resolution; fallback chain well-defined; invalid URLs fail fast before any request.
- [ ] **Safe:** No state mutation; override only affects URL construction; no side effects.
- [ ] **Secure:** No new authorization surface; override is an operational concern, not a security boundary.
- [ ] **Suitable:** Enables multi-environment execution (staging/prod) without document edits; complements draft 20 (HTTP hardening).
- [ ] **Usable:** Simple map structure `$overrides = ['api' => 'https://prod.example.com']`; intuitive keyed by source name.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Override used for matching source name**
- **Given** an Arazzo document with source description `{ name: "api", url: "https://staging.example.com/openapi.yaml", type: "openapi" }` where `servers[0]` is `https://staging.example.com`; an override map `$overrides = ['api' => 'https://prod.example.com']` is supplied to execution; `DefaultOpenApiExecutor` receives the override map keyed by source description `name`.
- **When** a step executes against source `api` and `DefaultOpenApiExecutor` computes the request URL (`$url = $baseUrl . $urlPath`).
- **Then** the request URL uses `https://prod.example.com` as base (the override) instead of `https://staging.example.com` (`servers[0]`); the override wins over `servers[0]`.

**Scenario 2: Fallback to `servers[0]` when no override matches**
- **Given** an Arazzo document with source description `{ name: "api", url: "https://staging.example.com/openapi.yaml", type: "openapi" }` where `servers[0]` is `https://staging.example.com`; no override map entry exists for source `api`.
- **When** a step executes against source `api` and `DefaultOpenApiExecutor` computes the request URL.
- **Then** the request URL uses `https://staging.example.com` as base (`servers[0]`); then falls back to path/operation servers per existing normalizer precedence (`OpenApi30Normalizer::resolveServerUrl()`).

**Scenario 3: Invalid URLs rejected before execution**
- **Given** an override value that is not a valid URL (e.g., `api=not-a-url`); `DefaultOpenApiExecutor` validates override URLs before execution.
- **When** execution starts and the override map is processed.
- **Then** a clear error is raised before any request is sent; `InvalidArgumentException` or similar is thrown with message identifying the invalid override URL; no HTTP request is dispatched.

**Scenario 4: Multiple sources independently overridable**
- **Given** an Arazzo document with two source descriptions (`api` and `auth`); override map `$overrides = ['api' => 'https://prod.example.com', 'auth' => 'https://auth-prod.example.com']`; each source has its own `servers[0]`.
- **When** steps execute against each source independently.
- **Then** source `api` uses `https://prod.example.com` as base; source `auth` uses `https://auth-prod.example.com` as base; each source's override is applied independently; no cross-contamination between sources.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Execution accepts a server-override map keyed by source description `name`
- [ ] `DefaultOpenApiExecutor` uses override for matching source name; falls back to `servers[0]` (then path/operation servers) when no override matches
- [ ] Invalid URLs rejected before execution with clear error
- [ ] `DefaultOpenApiExecutorTest` case: override supplied → request URL uses override; no override → `servers[0]`
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--server name=url` flag on `RunCommand`) tracked separately in draft 39

### 🧬 10. Source Grounding & Code Location

- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:54-57` — `$baseUrl = rtrim($openApi->servers[0]->url, '/')`.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:93` — `$url = $baseUrl.$urlPath`.
- `packages/sources/src/Normalizer/OpenApi30Normalizer.php:65` — `resolveServerUrl()` existing precedence (operation → path → document).
- `packages/document/src/NormalizedOpenApiOperation.php` — `resolvedServerUrl` field.

<details><summary><code>DefaultOpenApiExecutor.php:54-57</code></summary>

```php
        $baseUrl = '';
        if ($openApi->servers) {
            $baseUrl = rtrim($openApi->servers[0]->url, '/');
        }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive executor parameter; `DefaultOpenApiExecutor` is `@internal`, so no public contract break.
- **Blast radius:** `packages/protocol-http` execution; any entry point supplying overrides.
- **Severity:** medium — enables multi-environment execution without document edits.
- **Rollback:** remove override parameter; `servers[0]` behaviour unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Executor uses `servers[0]` only | T1 | `DefaultOpenApiExecutor.php:54-57` | direct read |
| `resolveServerUrl()` precedence exists at parse time | T1 | `OpenApi30Normalizer.php:65-90` | direct read |
| Override keyed by source `name` is available at execute time | T1 | `OpenApiOperationHandle->operation->source->name` | direct read |
| No server-override parameter exists today | T1 | grep for `serverOverride`/`server_overrides` → no matches | executed grep |