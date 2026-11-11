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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Execution accepts a server-override map keyed by source description `name`.
- [ ] `DefaultOpenApiExecutor` uses the override for the matching source name; falls back to `servers[0]` (then path/operation servers per existing normalizer precedence) when no override matches.
- [ ] Invalid URLs are rejected before execution with a clear error.
- [ ] `DefaultOpenApiExecutorTest` gains a case: override supplied → request URL uses override; no override → `servers[0]`.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--server name=url` flag on `RunCommand`) is tracked separately in draft 39.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive executor parameter; `DefaultOpenApiExecutor` is `@internal`, so no public contract break.
- **Blast radius:** `packages/protocol-http` execution; any entry point supplying overrides.
- **Severity:** medium — enables multi-environment execution without document edits.
- **Rollback:** remove override parameter; `servers[0]` behaviour unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Executor uses `servers[0]` only | T1 | `DefaultOpenApiExecutor.php:54-57` | direct read |
| `resolveServerUrl()` precedence exists at parse time | T1 | `OpenApi30Normalizer.php:65-90` | direct read |
| Override keyed by source `name` is available at execute time | T1 | `OpenApiOperationHandle->operation->source->name` | direct read |
| No server-override parameter exists today | T1 | grep for `serverOverride`/`server_overrides` → no matches | executed grep |