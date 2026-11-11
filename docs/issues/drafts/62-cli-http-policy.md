# Draft issue 62 — CLI: `run` HTTP policy flags

- **Suggested title:** `[Feature] `arazzo run` HTTP policy flags (timeout, response size, redirects, schemes)`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `cli`, `protocol-http`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` options; `packages/protocol-http` `DefaultOpenApiExecutor` (draft 53 core); `packages/runner` threading policy to executor.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core `HttpExecutionPolicy` lives in draft 53.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — security and availability; matters as soon as endpoints or documents are untrusted.

### 📝 2. Problem Statement & Intent

Draft 53 core adds `HttpExecutionPolicy` (redirects, response size, schemes, deadlines) enforced in `DefaultOpenApiExecutor`. This draft exposes it via CLI flags:

- `DefaultOpenApiExecutor` applies a per-step timeout only when the client is Guzzle. It has no redirect policy, no response-size limit, no scheme/host policy, no overall workflow deadline.
- `RunCommand` has no flags to control these policies.

Expected: `arazzo run --http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags that construct an `HttpExecutionPolicy` and thread it to the executor.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RunCommand` gains `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags.
- Flags construct an `HttpExecutionPolicy` (draft 53 core) and thread it through `RunnerFacade` → `StepExecutor` → `DefaultOpenApiExecutor`.
- Secure defaults: `maxRedirects=10`, `allowDowngradeRedirects=false`, `maxResponseBytes=10MiB`, `allowedSchemes=[http, https]`, `executionTimeoutSeconds=300`.
- Each policy refusal appears as a structured warning in the run report and trace.
- Core `HttpExecutionPolicy` — that is draft 53.

**Non-goals**

- Source fetching (draft 09).
- TLS verification controls beyond a per-host opt-out.
- Rate limiting (draft 21).

**Prior art in this repository**

- `DefaultOpenApiExecutor` already accepts `?float $timeoutSeconds` and forwards it to Guzzle (`DefaultOpenApiExecutor.php:129-147`).
- `SourceFetchException` and `ExecutionException` exist for typed failures.
- `Budget` already models execution limits (`packages/runtime/src/State/Data/Budget.php:13-21`).
- Draft 53 core: `HttpExecutionPolicy` and enforcement.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags.
- [ ] Flags construct an `HttpExecutionPolicy` and thread it to `DefaultOpenApiExecutor`.
- [ ] The executor refuses a redirect chain longer than `maxRedirects` with `transport.redirect_limit`.
- [ ] An HTTPS→HTTP redirect is refused unless `allowDowngradeRedirects` is set.
- [ ] A response body larger than `maxResponseBytes` is aborted without buffering the full body.
- [ ] A resolved URL with a scheme outside `allowedSchemes` is refused before sending.
- [ ] A denied host is refused before sending and on each redirect hop.
- [ ] `executionTimeoutSeconds` bounds the whole workflow; exceeding it produces `transport.execution_timeout`.
- [ ] Each policy refusal appears as a structured warning in the run report and trace.
- [ ] `RunCommandTest` fixture-tests redirect limit, downgrade, oversized body, disallowed scheme, denied host and execution deadline.
- [ ] PHPStan max and Pint pass.
- [ ] **Core policy** (`HttpExecutionPolicy`) is draft 53; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no HTTP policy flags.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:101-149` — request build, timeout-only transport handling (draft 53 core).
- `packages/runtime/src/State/Data/Budget.php:13-21` — existing execution limits, no deadline.

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

- **Public API impact:** additive CLI options; `HttpExecutionPolicy` (draft 53 core).
- **Blast radius:** every HTTP/OpenAPI execution in core, CLI and Laravel. Secure defaults may refuse previously accepted endpoints.
- **Severity:** high — SSRF, memory exhaustion and silent TLS downgrade.
- **Rollback:** remove flags; `HttpExecutionPolicy` defaults keep previous behaviour configurable.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No HTTP policy flags on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| Only per-step timeout is forwarded, and only for Guzzle | T1 | `DefaultOpenApiExecutor.php:129-149` | direct read (draft 53 core) |
| No redirect, size, scheme or host policy exists | T1 | grep in `packages/protocol-http/src` → no matches | executed grep |
| Draft 53 is the core dependency | T2 | draft 53 (`HttpExecutionPolicy`) | cross-ref |