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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Execution accepts a per-domain mTLS config mapping `domain → cert:key[:ca]`.
- [ ] A PSR-18 client is built with per-domain SSL context.
- [ ] Requests to a configured domain use the mTLS context; other domains use the default client.
- [ ] Missing cert/key files error before execution with a clear message.
- [ ] Invalid config format errors before execution.
- [ ] No behaviour change when mTLS config is omitted.
- [ ] `DefaultOpenApiExecutorTest` gains a case: mTLS config → client created with SSL context for matching domain; no config → default client.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (repeatable `--mtls domain=cert:key[:ca]` flag on `RunCommand`) is tracked separately in draft 42.

### 🧬 7. Source Grounding & Code Location

- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:45-50` — `execute()` receives the client indirectly via `RunnerFacade`.
- `packages/cli/composer.json` — `guzzlehttp/guzzle: ^7.9` already a dependency (CLI side, referenced for context).
- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--mtls` option (CLI side, referenced for context).

<details><summary><code>DefaultOpenApiExecutor::__construct</code></summary>

```php
public function __construct(ClientInterface $httpClient, ...)
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive executor parameter; client construction internal.
- **Blast radius:** `packages/protocol-http` execution outbound TLS; no document or validator impact.
- **Severity:** medium — enables mTLS-protected APIs; no behaviour change by default.
- **Rollback:** remove parameter; default (non-mTLS) client unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No mTLS/certificate code in php-arazzo | T1 | `grep -rIn -i "mtls\|clientCert\|certificate" packages --exclude-dir=vendor` → only comment/char matches | executed grep |
| Executor accepts `ClientInterface` (injectable) | T1 | `DefaultOpenApiExecutor::__construct(ClientInterface $httpClient, ...)` | direct read |
| No mTLS config parameter exists today | T1 | grep for `mtls`/`mTls` → no matches | executed grep |