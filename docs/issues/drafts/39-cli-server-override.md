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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains repeatable `--server` option accepting `name=url` or bare `url` for single-source documents.
- [ ] Bare `--server <url>` errors when multiple source descriptions exist ("ambiguous").
- [ ] Override map threaded through `RunnerFacade` → `StepExecutor` → `DefaultOpenApiExecutor`.
- [ ] `DefaultOpenApiExecutor` uses override for matching source; falls back to `servers[0]` otherwise.
- [ ] Invalid URLs rejected with a clear CLI error before execution.
- [ ] `RunCommandTest` gains a case: overridden host appears in outgoing request (fake PSR-18 client).
- [ ] PHPStan max and Pint pass.
- [ ] **Core server-override logic** (draft 29) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI option; `DefaultOpenApiExecutor` gains optional override parameter (draft 29 core).
- **Blast radius:** `packages/cli` `run`; `packages/protocol-http` execution. No change to `validate` or documents.
- **Severity:** medium — enables multi-environment execution without document edits.
- **Rollback:** remove option; `servers[0]` behaviour unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--server` option on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| Executor uses `servers[0]` only | T1 | `DefaultOpenApiExecutor.php:54-57` | direct read (draft 29 core) |
| Override keyed by source `name` available at execute time | T1 | `OpenApiOperationHandle->operation->source->name` | direct read |
| Draft 29 is the core dependency | T2 | draft 29 (core server override) | cross-ref |