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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core `HttpExecutionPolicy` via CLI flags so `arazzo run` can bound outbound HTTP execution (timeout, response size, redirects, schemes, hosts, deadline).

**Architectural Boundary:** Bounded context = CLI `run` command options + transport policy threading. Packages: `packages/cli` (`RunCommand` options), `packages/protocol-http` (`DefaultOpenApiExecutor` — draft 53 core), `packages/runner` (threading policy to executor). CLI port only; core policy is draft 53.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Oversized body aborted during streaming; execution deadline bounds whole workflow; per-step timeout preserved.
- [ ] **Flexible:** CLI flags map to `HttpExecutionPolicy` fields; secure defaults with opt-outs; enforcement in executor not injected client.
- [ ] **Maintainable:** Additive CLI options; policy object (draft 53 core); typed warnings in run report and trace.
- [ ] **Operable:** Structured warnings (`transport.redirect_limit`, `transport.downgrade_refused`, etc.); secure defaults may refuse previously accepted endpoints; rollback by removing flags.
- [ ] **Reliable:** Redirect limit prevents loops; downgrade refused; execution timeout prevents indefinite runs; denied hosts refused on each hop.
- [ ] **Safe:** Response size cap prevents memory exhaustion; scheme/host policy prevents SSRF/link-local access; no state corruption.
- [ ] **Secure:** SSRF mitigation (allowed schemes, denied hosts); TLS downgrade prevention; memory exhaustion prevention.
- [ ] **Suitable:** P2 security/availability feature; matters when endpoints/documents untrusted; builds on existing `Budget`.
- [ ] **Usable:** `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: CLI gains HTTP policy flags**
## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: CLI flags parsed into `HttpExecutionPolicy`**
- **Given** `arazzo run doc.yaml --http-timeout 30 --max-response-size 5242880`; `RunCommand` gains repeatable `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags; flags construct `HttpExecutionPolicy` (draft 53 core); `HttpExecutionPolicy` is threaded to `DefaultOpenApiExecutor`.
- **When** `php bin/console arazzo run doc.yaml --http-timeout 30 --max-response-size 5242880` executes.
- **Then** flags parsed into `HttpExecutionPolicy`; threaded to executor; `DefaultOpenApiExecutor` enforces per-step connect timeout (30s) and max response body (5 MiB).

**Scenario 2: Redirect limit enforced**
- **Given** `--max-redirects 5`; redirect chain exceeds 5; `HttpExecutionPolicy` configured with `maxRedirects: 5`; `DefaultOpenApiExecutor` enforces the policy in the executor.
- **When** `request follows redirects` through the chain.
- **Then** refused with `transport.redirect_limit`; `ErrorCollector` records the warning under `warnings`; `ValidationResult::isValid()` returns `false`; no request sent beyond the limit.

**Scenario 3: Downgrade redirect refused**
- **Given** `--allow-downgrade-redirects` not set (default false); HTTPS→HTTP redirect (from `https://api.example.com/` to `http://insecure.example.com/`).
- **When** `redirect followed` and `DefaultOpenApiExecutor` processes the redirect.
- **Then** refused with `transport.downgrade_refused`; `ErrorCollector` records the warning; request not downgraded from HTTPS to HTTP; `ValidationResult::isValid()` returns `false`.

**Scenario 4: Oversized body aborted**
- **Given** `--max-response-size 1048576` (1 MiB); response > 1 MiB; body is being streamed.
- **When** `body streamed` and `DefaultOpenApiExecutor` reads the response body.
- **Then** aborted without buffering full body; `transport.response_too_large` warning recorded under `warnings`; full body not buffered in memory; `StepResult` does not hold the oversized body.

**Scenario 5: Disallowed scheme refused**
- **Given** URL scheme outside `allowedSchemes` (http, https); `allowedSchemes: ["http", "https"]` (default).
- **When** `request prepared` and `DefaultOpenApiExecutor` prepares the request.
- **Then** scheme refused before sending; `transport.scheme_refused` warning recorded under `warnings`; no HTTP request dispatched to disallowed scheme.

**Scenario 6: Denied host refused**
- **Given** denied host (loopback, link-local, private); `deniedHosts` includes loopback/link-local/private ranges.
- **When** `request or redirect targets host` and `DefaultOpenApiExecutor` prepares the request or processes a redirect hop.
- **Then** host refused; `transport.denied_host` warning recorded under `warnings`; no request made to denied host; refusal checked before sending and on each redirect hop.

**Scenario 7: Execution timeout bounds workflow**
- **Given** `--execution-timeout 300`; workflow exceeds 300s; `HttpExecutionPolicy` configured with `executionTimeoutSeconds: 300`.
- **When** `workflow runs` and cumulative execution time exceeds 300 seconds.
- **Then** ``transport.execution_timeout`` produced as structured warning under `warnings`; workflow aborted; no further steps executed; `ValidationResult::isValid()` returns `false`.

**Scenario 8: Policy refusals as structured warnings**
- **Given** any policy refusal (redirect limit, downgrade, oversized body, disallowed scheme, denied host, execution timeout).
- **When** `run completes`.
- **Then** `refusal appears as structured warning in run report and trace`; each warning has stable code (`transport.*`), message, and step ID; `TraceSerializer` includes warnings in the trace artifact; `run.warnings[]` populated.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags
- [ ] Flags construct `HttpExecutionPolicy`; threaded to `DefaultOpenApiExecutor`
- [ ] Executor refuses redirect chain > `maxRedirects` with `transport.redirect_limit`
- [ ] HTTPS→HTTP redirect refused unless `allowDowngradeRedirects` set
- [ ] Oversized body aborted during streaming; not buffered beyond cap
- [ ] Disallowed scheme refused before sending
- [ ] Denied host refused before sending and on each redirect hop
- [ ] `executionTimeoutSeconds` bounds whole workflow; `transport.execution_timeout` on exceed
- [ ] Each refusal appears as structured warning in run report and trace
- [ ] `RunCommandTest` fixture-tests redirect limit, downgrade, oversized body, disallowed scheme, denied host, deadline
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core policy (`HttpExecutionPolicy`) is draft 53; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; `HttpExecutionPolicy` (draft 53 core).
- **Blast radius:** every HTTP/OpenAPI execution in core, CLI and Laravel. Secure defaults may refuse previously accepted endpoints.
- **Severity:** high — SSRF, memory exhaustion and silent TLS downgrade.
- **Rollback:** remove flags; `HttpExecutionPolicy` defaults keep previous behaviour configurable.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No HTTP policy flags on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| Only per-step timeout is forwarded, and only for Guzzle | T1 | `DefaultOpenApiExecutor.php:129-149` | direct read (draft 53 core) |
| No redirect, size, scheme or host policy exists | T1 | grep in `packages/protocol-http/src` → no matches | executed grep |
| Draft 53 is the core dependency | T2 | draft 53 (`HttpExecutionPolicy`) | cross-ref |