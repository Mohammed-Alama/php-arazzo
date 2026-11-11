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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] The executor refuses a redirect chain longer than `maxRedirects` with `transport.redirect_limit`.
- [ ] An HTTPS→HTTP redirect is refused unless `allowDowngradeRedirects` is set.
- [ ] A response body larger than `maxResponseBytes` is aborted without buffering the full body.
- [ ] A resolved URL with a scheme outside `allowedSchemes` is refused before sending.
- [ ] A denied host is refused before sending and on each redirect hop.
- [ ] `executionTimeoutSeconds` bounds the whole workflow; exceeding it produces `transport.execution_timeout`.
- [ ] Each policy refusal appears as a structured warning in the run report and trace.
- [ ] Defaults are secure; opt-outs are explicit and documented.
- [ ] Tests use a fake PSR-18 client and cover redirect limit, downgrade, oversized body, disallowed scheme, denied host and execution deadline.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new policy object and CLI flags; secure defaults may refuse previously accepted endpoints.
- **Blast radius:** every HTTP/OpenAPI execution in core, CLI and Laravel.
- **Severity:** high — SSRF, memory exhaustion and silent TLS downgrade.
- **Rollback:** allow explicit opt-outs; the policy object keeps previous behaviour configurable.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Only per-step timeout is forwarded, and only for Guzzle | T1 | `DefaultOpenApiExecutor.php:129-149` | direct read |
| No redirect, size, scheme or host policy exists | T1 | `grep -rIn -i 'redirect\|max.*response\|allowedSchemes' packages/protocol-http/src` → no matches | executed |
| Response bodies are read without a cap | T1 | `RequestCompiler::decodeResponse()` and `StepResult` hold the full body | direct read |
| No overall execution deadline exists | T1 | `Budget.php:13-21`; grep for `deadline` → no matches | direct read + executed |
| Enforcement must not rely on the injected client's defaults | T2 | PSR-18 has no policy surface | inferred from PSR-18 |
