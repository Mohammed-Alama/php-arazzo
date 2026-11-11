# Draft issue 39 — CLI: `run --server` source URL override

- **Suggested title:** `[Feature] `arazzo run --server` override the base URL of a source description`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `cli`, `protocol-http`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` options; `packages/protocol-http` `DefaultOpenApiExecutor` base URL (draft 29 core); `packages/runner` threading override to executor.
- **Arazzo spec version:** independent of spec version; affects `arazzo run` against an OpenAPI source.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core override logic lives in draft 29.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — without a server override, `arazzo run` always hits `servers[0]` and cannot target staging/prod/different hosts without editing the source document.

### 📝 2. Problem Statement & Intent

Draft 29 adds core server-override support to `DefaultOpenApiExecutor`. This draft exposes it via CLI flags:

- `RunCommand` has no `--server` option (`RunCommand.php:45-51`).
- The override map (keyed by source description `name`) must be constructed from CLI flags and threaded through `RunnerFacade` → `ExecutionGraphFactory` → `StepExecutor` → `DefaultOpenApiExecutor`.

Expected: `arazzo run --server <name>=<url>` (repeatable) overrides the base URL for the named source; `--server <url>` (bare) applies to the single source when exactly one exists. Override wins over `servers[0]`.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RunCommand` gains repeatable `--server` option accepting `name=url` or bare `url` for single-source documents.
- Bare `--server <url>` errors when multiple source descriptions exist ("ambiguous").
- Override map threaded through `RunnerFacade` → `StepExecutor` → `DefaultOpenApiExecutor`.
- Invalid URLs rejected with a clear CLI error before execution.

**Non-goals**

- Core server-override logic — that is draft 29.
- Per-request (per-step) server override — out of scope for v1.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `configure()` options: only `--workflow`/`--input`.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:54-57` — `$baseUrl = rtrim($openApi->servers[0]->url, '/')` (draft 29 core).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo run doc.arazzo.yaml --server api=https://prod.example.com
arazzo run doc.arazzo.yaml --server https://single-host.example.com   # bare URL, single source
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> [--server name=url]... [--server url]`.
- `RunCommand` parses `--server` values into an override map keyed by source `name`; bare URL applies to single source (errors if multiple sources).
- Override map threaded to `DefaultOpenApiExecutor`; executor uses override for matching source, falls back to `servers[0]` otherwise.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core server-override support via CLI flags so `arazzo run` can override the base URL of a source description without editing the document.

**Architectural Boundary:** Bounded context = CLI `run` command options + transport override threading. Packages: `packages/cli` (`RunCommand` options), `packages/protocol-http` (`DefaultOpenApiExecutor` base URL — draft 29 core), `packages/runner` (threading override to executor). CLI port only; core override logic is draft 29.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing is O(flags); override map lookup is O(1); no per-request overhead beyond existing executor logic.
- [ ] **Flexible:** Repeatable `--server` accepting `name=url` or bare `url`; bare URL errors when multiple sources exist ("ambiguous"); override map threaded through `RunnerFacade` → `StepExecutor` → `DefaultOpenApiExecutor`.
- [ ] **Maintainable:** Additive CLI option; `DefaultOpenApiExecutor` gains optional override parameter (`@internal`); invalid URLs rejected before execution with clear CLI error.
- [ ] **Operable:** Override wins over `servers[0]`; rollback by removing option (`servers[0]` behavior unchanged).
- [ ] **Reliable:** Deterministic URL resolution; override keyed by source `name`; invalid URLs fail fast before execution.
- [ ] **Safe:** No state mutation; override only affects URL construction; no side effects.
- [ ] **Secure:** No new authorization surface; override is an operational concern.
- [ ] **Suitable:** Enables multi-environment execution (staging/prod) from CLI without document edits.
- [ ] **Usable:** `arazzo run doc.yaml --server api=https://prod.example.com`; bare URL for single-source documents.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--server name=url` overrides base URL**
- **Given** `arazzo run doc.yaml --server api=https://prod.example.com`; `RunCommand` (draft 39 core) parses `--server` values into an override map keyed by source description `name`; `DefaultOpenApiExecutor` (draft 29 core) receives the override map via `RunnerFacade` → `StepExecutor`.
- **When** `php bin/console arazzo run doc.yaml --server api=https://prod.example.com` executes.
- **Then** the override map is threaded to `DefaultOpenApiExecutor`; the `api` source uses `https://prod.example.com` as base URL instead of `servers[0]`; the request URL reflects the override.

**Scenario 2: Bare `--server <url>` for single source**
- **Given** `arazzo run doc.yaml --server https://single-host.example.com` with exactly one source description declared in the document.
- **When** the command runs and `RunCommand` processes the bare `--server` URL.
- **Then** the bare URL applies to the single source; `RunCommand` detects that exactly one source exists and applies the override to it.

**Scenario 3: Bare `--server <url>` errors with multiple sources**
- **Given** `arazzo run doc.yaml --server https://example.com` with multiple source descriptions declared in the document.
- **When** `RunCommand` processes the bare `--server` URL.
- **Then** a clear CLI error is returned: "ambiguous bare URL -- multiple source descriptions exist; use --server name=url"; execution does not proceed; non-zero exit code.

**Scenario 4: Invalid URL rejected before execution**
- **Given** `--server api=not-a-url`; `RunCommand` validates the override URL before execution.
- **When** the command runs and parses `--server api=not-a-url`.
- **Then** a clear CLI error is raised before any request is sent; `InvalidArgumentException` is thrown with message identifying the invalid URL; no HTTP request is dispatched.

**Scenario 5: Override wins over `servers[0]`**
- **Given** override `api=https://prod.example.com` and `servers[0]=https://staging.example.com` in the OpenAPI source; `DefaultOpenApiExecutor` computes `$baseUrl` from the override map first, falling back to `servers[0]`.
- **When** a step executes against source `api`.
- **Then** the request uses `https://prod.example.com` as base URL (the override wins); `servers[0]` is only used when no override matches; path/operation server precedence applies when no override matches for that source.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains repeatable `--server` option accepting `name=url` or bare `url` for single-source documents
- [ ] Bare `--server <url>` errors when multiple source descriptions exist ("ambiguous")
- [ ] Override map threaded through `RunnerFacade` → `StepExecutor` → `DefaultOpenApiExecutor`
- [ ] `DefaultOpenApiExecutor` uses override for matching source; falls back to `servers[0]` otherwise
- [ ] Invalid URLs rejected with clear CLI error before execution
- [ ] `RunCommandTest` case: overridden host appears in outgoing request (fake PSR-18 client)
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core server-override logic (draft 29) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `configure()` options: only `--workflow`/`--input`.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:54-57` — `$baseUrl = rtrim($openApi->servers[0]->url, '/')` (draft 29 core).
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:93` — `$url = $baseUrl.$urlPath` (draft 29 core).

<details><summary><code>RunCommand.php:45-51</code></summary>

```php
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'workflowId to run (defaults to the first workflow)')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Workflow inputs as inline JSON or @file.json');
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI option; `DefaultOpenApiExecutor` gains optional override parameter (draft 29 core).
- **Blast radius:** `packages/cli` `run`; `packages/protocol-http` execution. No change to `validate` or documents.
- **Severity:** medium — enables multi-environment execution without document edits.
- **Rollback:** remove option; `servers[0]` behaviour unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--server` option on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| Executor uses `servers[0]` only | T1 | `DefaultOpenApiExecutor.php:54-57` | direct read (draft 29 core) |
| Override keyed by source `name` available at execute time | T1 | `OpenApiOperationHandle->operation->source->name` | direct read |
| Draft 29 is the core dependency | T2 | draft 29 (core server override) | cross-ref |