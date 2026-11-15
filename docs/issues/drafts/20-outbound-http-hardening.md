# Draft issue 20 — Bound outbound HTTP execution

- **Suggested title:** `[Feature] Bound outbound requests: redirects, response size, schemes and deadlines`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `protocol-http`, `runner`, `runtime`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/protocol-http` `DefaultOpenApiExecutor` and `HttpStepExecutor`; `packages/runner` step execution; `packages/runtime` budgets; `packages/cli` flags.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core plus CLI options.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — security and availability; matters as soon as endpoints or documents are untrusted.

### 📝 2. Problem Statement & Intent

`DefaultOpenApiExecutor` builds the request and delegates to the injected PSR-18
client (`packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:101-149`).
It applies a per-step timeout only when the client is Guzzle. It has:

- **no redirect policy** — a Guzzle client follows up to its own default and can
  be redirected from HTTPS to HTTP without the runner noticing;
- **no response-size limit** — the caller reads the body with no cap, so a
  multi-gigabyte response is buffered in memory;
- **no scheme/host policy** — a resolved `servers[].url` can be `file://`,
  `ftp://` or a link-local address;
- **no overall workflow deadline** — only individual step timeouts exist, so a
  workflow of many slow steps can run indefinitely;
- **no structured warning** when a transport policy is applied.

The source-fetch path has a separate draft (draft 09). This draft covers the
**execution** path.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Introduce an `HttpExecutionPolicy` value object:
  - `maxRedirects` (default 10)
  - `allowDowngradeRedirects` (default false; HTTPS→HTTP refused)
  - `maxResponseBytes` (default 10 MiB)
  - `allowedSchemes` (default `http`, `https`)
  - `allowedHosts` / `deniedHosts` (optional; deny loopback and link-local by
    default unless explicitly allowed)
  - `connectTimeoutSeconds` (default 10)
  - `executionTimeoutSeconds` (default 300)
- Enforce the policy in the executor (or in a wrapping PSR-18 client), not by
  trusting the injected client's defaults.
- Abort an oversized body **while streaming**; never buffer beyond the cap.
- Emit stable transport warnings (`transport.redirect_limit`,
  `transport.downgrade_refused`, `transport.response_too_large`,
  `transport.scheme_refused`, `transport.execution_timeout`) into the run
  report and trace.
- CLI flags: `--http-timeout`, `--execution-timeout`, `--max-response-size`,
  `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host`.
- Keep the existing per-step timeout semantics; the execution deadline is an
  additional ceiling.

**Non-goals**

- Source fetching (draft 09).
- Typed exception hierarchy (already #24).
- TLS verification controls beyond a per-host opt-out; a full PKI policy is out
  of scope.
- Rate limiting (draft 21).

**Prior art in this repository**

- `DefaultOpenApiExecutor` already accepts `?float $timeoutSeconds` and forwards
  it to Guzzle.
- `SourceFetchException` and `ExecutionException` exist for typed failures.
- `Budget` already models execution limits; an overall deadline can live beside
  it.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Bound outbound HTTP execution with an `HttpExecutionPolicy` covering redirects, response size, schemes, hosts, connect timeout, and overall workflow deadline, enforced in the executor rather than trusting the injected client's defaults.

**Architectural Boundary:** Bounded context = HTTP execution policy + transport enforcement. Packages: `packages/protocol-http` (`DefaultOpenApiExecutor`, `HttpStepExecutor`), `packages/runner` (step execution), `packages/runtime` (budgets), `packages/cli` (flags). Core `HttpExecutionPolicy` is draft 53; this draft is the CLI port. Source fetching covered by draft 09.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Oversized body aborted during streaming (no unbounded buffering); execution deadline bounds the whole workflow; per-step timeout preserved.
- [ ] **Flexible:** `HttpExecutionPolicy` value object with configurable fields; secure defaults with explicit opt-outs; enforcement in the executor not the injected client.
- [ ] **Maintainable:** Policy object is additive; typed warnings (`transport.redirect_limit`, `transport.downgrade_refused`, etc.); `Budget` already models execution limits.
- [ ] **Operable:** Structured warnings in run report and trace; secure defaults may refuse previously accepted endpoints; rollback via explicit opt-outs.
- [ ] **Reliable:** Redirect limit prevents loops; downgrade refused; execution timeout prevents indefinite runs; denied hosts refused on each redirect hop.
- [ ] **Safe:** Response size cap prevents memory exhaustion; scheme/host policy prevents SSRF and link-local access; no state corruption.
- [ ] **Secure:** SSRF mitigation (allowed schemes, denied hosts); TLS downgrade prevention; memory exhaustion prevention; secure defaults.
- [ ] **Suitable:** P2 security/availability feature; matters as soon as endpoints or documents are untrusted; builds on existing `Budget` and exception types.
- [ ] **Usable:** CLI flags (`--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host`); secure defaults require no configuration.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Redirect chain longer than `maxRedirects` is refused**
- **Given** an Arazzo document with workflow index 0, step index 0 making an HTTP request that triggers a redirect chain of 11 HTTP 302 responses; `HttpExecutionPolicy` (draft 53 core) is configured with `maxRedirects: 10`; `DefaultOpenApiExecutor` enforces the policy in the executor (not trusting the injected client's defaults).
- **When** the request follows redirects through the chain.
- **Then** the executor refuses after 10 redirects with `transport.redirect_limit`; `ErrorCollector` records the warning under `warnings` in the run report and trace; no request is sent beyond the limit.

**Scenario 2: HTTPS→HTTP redirect refused unless allowed**
- **Given** an HTTPS→HTTP redirect (from `https://api.example.com/` to `http://insecure.example.com/`); `allowDowngradeRedirects: false` (default).
- **When** `DefaultOpenApiExecutor` processes the redirect.
- **Then** the redirect is refused with `transport.downgrade_refused`; `ErrorCollector` records the warning; the request is not downgraded from HTTPS to HTTP; `ValidationResult::isValid()` returns `false` for the step.

**Scenario 3: Oversized response body aborted without buffering**
- **Given** `maxResponseBytes: 1048576` (1 MiB, default); an HTTP response with `Content-Length: 5242880` (5 MiB); the body is being streamed.
- **When** `DefaultOpenApiExecutor` reads the response body.
- **Then** the body is aborted when the byte count exceeds 1 MiB; `transport.response_too_large` warning is recorded; the full 5 MiB body is not buffered in memory; `StepResult` does not hold the oversized body.

**Scenario 4: Disallowed scheme refused before sending**
- **Given** a resolved URL with scheme `file://` or `ftp://`; `allowedSchemes: ["http", "https"]` (default).
- **When** `DefaultOpenApiExecutor` prepares the request.
- **Then** the scheme is refused before sending; `transport.scheme_refused` warning is recorded; no HTTP request is made to the disallowed scheme.

**Scenario 5: Denied host refused before sending and on each redirect**
- **Given** a denied host (`127.0.0.1`, `::1`, or `169.254.169.254`); `deniedHosts` includes loopback/link-local/private ranges; an initial URL or redirect targets that host.
- **When** `DefaultOpenApiExecutor` prepares the request or processes a redirect hop.
- **Then** the host is refused before sending and on each redirect hop; `transport.denied_host` warning is recorded; no request is made to the denied host.

**Scenario 6: `executionTimeoutSeconds` bounds the whole workflow**
- **Given** `executionTimeoutSeconds: 300` (default); a workflow with many slow steps whose total execution time exceeds 300 seconds; `HttpExecutionPolicy` tracks the overall workflow deadline.
- **When** the workflow runs and the cumulative execution time exceeds 300 seconds.
- **Then** `transport.execution_timeout` is produced; `ErrorCollector` records the warning; the workflow is aborted; no further steps are executed.

**Scenario 7: Each policy refusal appears as a structured warning**
- **Given** any policy refusal (redirect limit, downgrade, oversized body, disallowed scheme, denied host, execution timeout).
- **When** the run completes.
- **Then** the refusal appears as a structured warning in the run report (`run.warnings[]`) and trace (`run.steps[].warnings[]`); each warning has a stable code (`transport.*`), message, and step ID; `TraceSerializer` includes warnings in the trace artifact.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--http-timeout`, `--execution-timeout`, `--max-response-size`, `--max-redirects`, `--allow-downgrade-redirects`, `--insecure-host` flags
- [ ] Flags construct an `HttpExecutionPolicy` and thread it to `DefaultOpenApiExecutor`
- [ ] The executor refuses a redirect chain longer than `maxRedirects` with `transport.redirect_limit`
- [ ] An HTTPS→HTTP redirect is refused unless `allowDowngradeRedirects` is set
- [ ] A response body larger than `maxResponseBytes` is aborted without buffering the full body
- [ ] A resolved URL with a scheme outside `allowedSchemes` is refused before sending
- [ ] A denied host is refused before sending and on each redirect hop
- [ ] `executionTimeoutSeconds` bounds the whole workflow; exceeding it produces `transport.execution_timeout`
- [ ] Each policy refusal appears as a structured warning in the run report and trace
- [ ] Tests use a fake PSR-18 client and cover redirect limit, downgrade, oversized body, disallowed scheme, denied host and execution deadline
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging/audit trails instrumented via structured warnings
- [ ] Core policy (`HttpExecutionPolicy`) is draft 53; this draft is the CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:101-149` — request build, timeout-only transport handling.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:129-147` — Guzzle-only timeout path.
- `packages/runner/src/Execution/Data/StepResult.php:21-34` — where response bodies are held.
- `packages/runtime/src/State/Data/Budget.php:13-21` — existing execution limits, no deadline.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php#L124-L149"><code>DefaultOpenApiExecutor.php:124-149</code></a> @ <code>ea8c55a</code></summary>

```php
if ($requestInterceptor !== null) {
    $intercepted = $requestInterceptor($request);
    $request = $intercepted instanceof RequestInterface ? $intercepted : $request;
}

// PSR-18 cannot express per-request timeouts; delegate to Guzzle when
// available so declared step timeouts are actually enforced.
if ($timeoutSeconds !== null && $this->httpClient instanceof \GuzzleHttp\ClientInterface) {
    // ...
    return $this->httpClient->request(
        $request->getMethod(),
        (string) $request->getUri(),
        [
            'headers' => $headers,
            'body' => (string) $request->getBody(),
            'timeout' => $timeoutSeconds,
        ],
    );
}

return $this->httpClient->sendRequest($request);
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new policy object and CLI flags; secure defaults may refuse previously accepted endpoints.
- **Blast radius:** every HTTP/OpenAPI execution in core, CLI and Laravel.
- **Severity:** high — SSRF, memory exhaustion and silent TLS downgrade.
- **Rollback:** allow explicit opt-outs; the policy object keeps previous behaviour configurable.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Only per-step timeout is forwarded, and only for Guzzle | T1 | `DefaultOpenApiExecutor.php:129-149` | direct read |
| No redirect, size, scheme or host policy exists | T1 | `grep -rIn -i 'redirect\|max.*response\|allowedSchemes' packages/protocol-http/src` → no matches | executed |
| Response bodies are read without a cap | T1 | `RequestCompiler::decodeResponse()` and `StepResult` hold the full body | direct read |
| No overall execution deadline exists | T1 | `Budget.php:13-21`; grep for `deadline` → no matches | direct read + executed |
| Enforcement must not rely on the injected client's defaults | T2 | PSR-18 has no policy surface | inferred from PSR-18 |
