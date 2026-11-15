# Draft issue 32 — Per-domain mTLS client certificates

- **Suggested title:** `[Feature] Per-domain mTLS client certificates for outbound requests`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `protocol-http`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/protocol-http` `DefaultOpenApiExecutor` / PSR-18 client creation; `packages/runner` `StepExecutor`.
- **Arazzo spec version:** independent of spec version; transport-level concern outside the Arazzo document.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P3 — mTLS is required for some production APIs but php-arazzo has no certificate wiring at all.

### 📝 2. Problem Statement & Intent

php-arazzo has no mTLS support:

- No client-certificate configuration anywhere (`grep -rIn -i "mtls\|clientCert\|certificate" packages --exclude-dir=vendor` → no matches outside comments).
- `DefaultOpenApiExecutor` accepts a `ClientInterface` but no SSL context / client cert wiring exists.
- No per-domain certificate mapping (different domains need different client certs).

Expected: execution accepts a per-domain mTLS configuration mapping `domain → (cert, key, optional ca)`; requests to a configured domain use the mTLS context; other domains use the default (non-mTLS) client.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execution accepts a per-domain mTLS config mapping `domain → (cert, key, optional ca)`.
- Requests to a configured domain use the mTLS context; other domains use the default client.
- Missing cert/key files error before execution with a clear message.
- Invalid config format errors before execution.
- No behaviour change when mTLS config is omitted.

**Non-goals**

- Server-side CA trust beyond the optional `ca` (`--cacert` out of scope).
- Client cert rotation or reloading.
- mTLS configured inside the Arazzo document (purely execution-time concern).
- CLI flags (`--mtls domain=cert:key[:ca]` on `RunCommand`) — tracked separately in draft 42.

**Prior art in this repository**

- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:45-50` — `execute()` accepts a `ClientInterface`; SSL context wiring is the missing piece.
- `packages/cli/composer.json` — `guzzlehttp/guzzle: ^7.9` already a dependency (CLI side, referenced for context).

### 📄 4. Structural Artifacts & Raw Inputs

```php
// mTLS config keyed by domain:
$mtls = [
    'mtls.example.com' => ['cert' => 'client.pem', 'key' => 'client-key.pem', 'ca' => 'ca.pem'],
];
$executor->execute($handle, $payload, mTls: $mtls);
```

Minimal document (any HTTP step document). Expected: the request to `https://mtls.example.com/...` presents `client.pem`/`client-key.pem` and verifies against `ca.pem`; requests to other domains use the default client.

### 🌀 5. Runtime Execution State Context

- Execution accepts mTLS config keyed by domain.
- A PSR-18 client is built with per-domain SSL context (client cert + key + optional CA).
- Requests to a configured domain use the mTLS context; other domains use the default client.
- Missing cert/key files error before execution with a clear message.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Add per-domain mTLS client certificate support for outbound requests, mapping domain → (cert, key, optional CA), so production APIs requiring mutual TLS can be called.

**Architectural Boundary:** Bounded context = HTTP execution + TLS configuration. Packages: `packages/protocol-http` (`DefaultOpenApiExecutor`, PSR-18 client creation), `packages/runner` (`StepExecutor`). Port-agnostic; CLI port tracked in draft 42.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** PSR-18 client built with per-domain SSL context; no overhead for domains without mTLS config (default client used).
- [ ] **Flexible:** Per-domain config mapping `domain → (cert, key, optional ca)`; swappable client adapter; optional config — no behavior change when omitted.
- [ ] **Maintainable:** Client construction internal to `DefaultOpenApiExecutor`; missing cert/key files error before execution; invalid config format errors before execution.
- [ ] **Operable:** Deterministic client selection per domain; rollback by removing mTLS config parameter (default non-mTLS client unchanged).
- [ ] **Reliable:** Requests to configured domain use mTLS context; other domains use default client; cert/key validated before execution.
- [ ] **Safe:** No state mutation; SSL context setup is read-only configuration; no side effects beyond TLS handshake.
- [ ] **Secure:** mTLS enables mutual authentication for protected APIs; server-side CA trust via optional `ca` beyond v1 scope.
- [ ] **Suitable:** Closes the gap where php-arazzo has no certificate wiring at all; mTLS required for some production APIs.
- [ ] **Usable:** Simple config `$mtls = ['domain' => ['cert' => '...', 'key' => '...', 'ca' => '...']]`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: mTLS config accepted per domain**
- **Given** an Arazzo document with an HTTP step targeting `https://mtls.example.com/api`; `OutboundRequestPolicy`/`HttpExecutionPolicy` (draft 32 core) is configured with `mTls: ['mtls.example.com' => ['cert' => '/path/to/client.pem', 'key' => '/path/to/client-key.pem', 'ca' => '/path/to/ca.pem']]`; `DefaultOpenApiExecutor` accepts per-domain mTLS config.
- **When** `DefaultOpenApiExecutor` configures the PSR-18 client for the target domain.
- **Then** a PSR-18 client is built with per-domain SSL context (client cert + key + optional CA); Guzzle SSL context is configured with the client certificate for `mtls.example.com`.

**Scenario 2: Configured domain uses mTLS context**
- **Given** a request to `https://mtls.example.com/api/endpoint`; mTLS config exists for `mtls.example.com`.
- **When** the HTTP request is sent to the configured domain.
- **Then** the mTLS context (client certificate + key + optional CA) is used for the TLS handshake; the server can verify the client identity; `DefaultOpenApiExecutor` uses the mTLS-configured client for this domain.

**Scenario 3: Other domains use default client**
- **Given** a request to `https://other.example.com/api/endpoint` with no mTLS config for `other.example.com`; mTLS config exists only for `mtls.example.com`.
- **When** the HTTP request is sent to an unconfigured domain.
- **Then** the default (non-mTLS) PSR-18 client is used; no client certificate is presented; standard TLS handshake without mutual authentication.

**Scenario 4: Missing cert/key files error before execution**
- **Given** mTLS config references non-existent cert/key files (e.g., `/nonexistent/client.pem`); `DefaultOpenApiExecutor` validates cert/key file existence before execution.
- **When** `DefaultOpenApiExecutor` initializes the mTLS client configuration.
- **Then** a clear error is raised before any request (`InvalidArgumentException` or `RuntimeException` with message "mTLS certificate file not found: /nonexistent/client.pem"); no HTTP request is dispatched; execution fails fast.

**Scenario 5: Invalid config format errors before execution**
- **Given** malformed mTLS config (e.g., missing `key` field, invalid domain format); `DefaultOpenApiExecutor` validates config format before execution.
- **When** `DefaultOpenApiExecutor` parses the mTLS configuration.
- **Then** a clear error is raised before any request; the error identifies the malformed config entry; no HTTP request is dispatched; execution fails fast with a descriptive error.

**Scenario 6: No behavior change when mTLS config omitted**
- **Given** no mTLS config is provided (`mTls: []` or not set); `DefaultOpenApiExecutor` operates without mTLS configuration.
- **When** execution runs without mTLS config.
- **Then** behavior is identical to today (default non-mTLS client); all requests use the standard PSR-18 client without client certificates; no regression for existing users.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Execution accepts per-domain mTLS config mapping `domain → cert:key[:ca]`
- [ ] PSR-18 client built with per-domain SSL context (client cert + key + optional CA)
- [ ] Requests to configured domain use mTLS context; other domains use default client
- [ ] Missing cert/key files error before execution with clear message
- [ ] Invalid config format errors before execution
- [ ] No behavior change when mTLS config omitted
- [ ] `DefaultOpenApiExecutorTest` case: mTLS config → client with SSL context for matching domain; no config → default client
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (repeatable `--mtls domain=cert:key[:ca]`) tracked separately in draft 42

### 🧬 10. Source Grounding & Code Location

- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:45-50` — `execute()` receives the client indirectly via `RunnerFacade`.
- `packages/cli/composer.json` — `guzzlehttp/guzzle: ^7.9` already a dependency (CLI side, referenced for context).
- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--mtls` option (CLI side, referenced for context).

<details><summary><code>DefaultOpenApiExecutor::__construct</code></summary>

```php
public function __construct(ClientInterface $httpClient, ...)
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive executor parameter; client construction internal.
- **Blast radius:** `packages/protocol-http` execution outbound TLS; no document or validator impact.
- **Severity:** medium — enables mTLS-protected APIs; no behaviour change by default.
- **Rollback:** remove parameter; default (non-mTLS) client unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No mTLS/certificate code in php-arazzo | T1 | `grep -rIn -i "mtls\|clientCert\|certificate" packages --exclude-dir=vendor` → only comment/char matches | executed grep |
| Executor accepts `ClientInterface` (injectable) | T1 | `DefaultOpenApiExecutor::__construct(ClientInterface $httpClient, ...)` | direct read |
| No mTLS config parameter exists today | T1 | grep for `mtls`/`mTls` → no matches | executed grep |