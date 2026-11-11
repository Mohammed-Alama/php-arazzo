# Draft issue 42 — CLI: per-domain mTLS client certificates

- **Suggested title:** `[Feature] `arazzo run --mtls` per-domain client certificates`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `cli`, `protocol-http`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` options (`--mtls`); `packages/protocol-http` `DefaultOpenApiExecutor` / PSR-18 client creation (draft 32 core); `packages/runner` threading client to executor.
- **Arazzo spec version:** independent of spec version; transport-level concern outside the Arazzo document.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core mTLS logic lives in draft 32.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P3 — mTLS is required for some production APIs but php-arazzo has no certificate wiring at all.

### 📝 2. Problem Statement & Intent

Draft 32 adds core per-domain mTLS support to `DefaultOpenApiExecutor`. This draft exposes it via CLI flags:

- No `--mtls` option on `RunCommand` (`RunCommand.php:45-51`).
- No client-certificate configuration anywhere in the CLI.

Expected: `arazzo run --mtls <domain>=<cert-file>:<key-file>[:<ca-file>]` configures a Guzzle client with the client certificate for requests to that domain; multiple `--mtls` options support multiple domains; requests to domains without a configured cert use the default (non-mTLS) client.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RunCommand` gains a repeatable `--mtls` option accepting `domain=cert:key[:ca]`.
- A Guzzle-based PSR-18 client is built with per-domain SSL context (client cert + key + optional CA).
- Requests to a configured domain use the mTLS context; other domains use the default client.
- Missing cert/key files error before execution with a clear message.
- Invalid option format errors before execution.
- No behaviour change when `--mtls` is omitted.

**Non-goals**

- Core mTLS logic — that is draft 32.
- Server-side CA trust beyond the optional `ca`; client cert rotation.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--mtls` option.
- `packages/cli/src/Console/Command/RunCommand.php:80` — `$fakeClient = new FakePsr18Client();` (to be replaced by real client per draft 11).
- `packages/cli/composer.json` — `guzzlehttp/guzzle: ^7.9` already a dependency.

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo run doc.arazzo.yaml --mtls mtls.example.com=client.pem:client-key.pem:ca.pem
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> --mtls <domain>=<cert>:<key>[:<ca>]...`.
- `RunCommand` builds a Guzzle-based PSR-18 client with per-domain SSL context (client cert + key + optional CA).
- The client is passed to `DefaultOpenApiExecutor` via `RunnerFacade` (draft 11 wiring).
- Requests to a configured domain use the mTLS context; other domains use the default client.
- Missing cert/key files error before execution with a clear message.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core per-domain mTLS support via CLI flags so `arazzo run` can configure client certificates for outbound requests.

**Architectural Boundary:** Bounded context = CLI `run` command TLS options. Packages: `packages/cli` (`RunCommand` `--mtls` option), `packages/protocol-http` (`DefaultOpenApiExecutor` — draft 32 core), `packages/runner` (threading client to executor). CLI port only; core mTLS logic is draft 32.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing O(flags); Guzzle client built with per-domain SSL context; no overhead for non-mTLS domains.
- [ ] **Flexible:** Repeatable `--mtls` accepting `domain=cert:key[:ca]`; multiple domains supported; optional config — no behavior change when omitted.
- [ ] **Maintainable:** Additive CLI option; client construction internal to `RunCommand`; missing cert/key files error before execution.
- [ ] **Operable:** Requests to configured domain use mTLS context; others use default client; rollback by removing option (default client unchanged).
- [ ] **Reliable:** Deterministic client selection per domain; invalid option format errors before execution.
- [ ] **Safe:** No state mutation; SSL context setup is read-only configuration.
- [ ] **Secure:** mTLS enables mutual authentication for protected APIs; server-side CA trust via optional `ca` beyond v1.
- [ ] **Suitable:** Closes gap where php-arazzo has no certificate wiring; mTLS required for some production APIs.
- [ ] **Usable:** `arazzo run --mtls mtls.example.com=client.pem:client-key.pem:ca.pem`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--mtls` repeatable option**
- **Given** `arazzo run doc.yaml --mtls domain1=cert1:key1:ca1 --mtls domain2=cert2:key2`; `RunCommand` accepts repeatable `--mtls` options; each option is parsed as `domain=cert:key[:ca]`.
- **When** `php bin/console arazzo run doc.yaml --mtls domain1=cert1:key1:ca1 --mtls domain2=cert2:key2` executes.
- **Then** both domains are configured with mTLS; `RunCommand` builds per-domain SSL context for each domain; `DefaultOpenApiExecutor` receives the mTLS configuration.

**Scenario 2: Guzzle client with per-domain SSL context**
- **Given** `--mtls domain=cert:key[:ca]`; `RunCommand` builds a Guzzle-based PSR-18 client; the client is configured with per-domain SSL context (client cert + key + optional CA).
- **When** the Guzzle client is built for the configured domain.
- **Then** the client has per-domain SSL context; the client certificate and key are loaded from the specified files; the optional CA is used for server verification if provided.

**Scenario 3: Configured domain uses mTLS; others use default**
- **Given** a request to the configured domain (`mtls.example.com`) and a request to an unconfigured domain (`other.example.com`); mTLS config exists only for `mtls.example.com`.
- **When** HTTP requests are sent to both domains.
- **Then** the configured domain uses mTLS (client certificate presented); the unconfigured domain uses the default (non-mTLS) PSR-18 client without client certificate.

**Scenario 4: Missing cert/key files error before execution**
- **Given** `--mtls` referencing non-existent cert/key files (e.g., `--mtls domain=/nonexistent/client.pem:/nonexistent/client-key.pem`); `RunCommand` validates cert/key file existence before execution.
- **When** `php bin/console arazzo run doc.yaml --mtls domain=/nonexistent/client.pem:/nonexistent/client-key.pem` executes.
- **Then** a clear error is raised before any request; `InvalidArgumentException` or `RuntimeException` is thrown with message identifying the missing file; no HTTP request is dispatched.

**Scenario 5: Invalid option format errors before execution**
- **Given** malformed `--mtls` value (e.g., `--mtls invalid-format` without `domain=cert:key` structure); `RunCommand` validates the option format before execution.
- **When** `php bin/console arazzo run doc.yaml --mtls invalid-format` executes.
- **Then** a clear error is raised before any request; the error identifies the malformed `--mtls` value; no HTTP request is dispatched.

**Scenario 6: No behavior change when `--mtls` omitted**
- **Given** no `--mtls` flag is passed; `arazzo run doc.yaml` is invoked without mTLS configuration.
- **When** the command runs without `--mtls`.
- **Then** behavior is identical to today (default non-mTLS client); all requests use the standard PSR-18 client without client certificates; no regression for existing users.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains repeatable `--mtls` option accepting `domain=cert:key[:ca]`
- [ ] Guzzle-based PSR-18 client built with per-domain SSL context (client cert + key + optional CA)
- [ ] Requests to configured domain use mTLS context; other domains use default client
- [ ] Missing cert/key files error before execution with clear message
- [ ] Invalid option format errors before execution
- [ ] `--mtls` repeatable for multiple domains
- [ ] `RunCommandTest` case: fake PSR-18 client verifies client created with SSL context for matching domain
- [ ] No behavior change when `--mtls` omitted
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core mTLS logic (draft 32) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — no `--mtls` option.
- `packages/cli/src/Console/Command/RunCommand.php:80` — `$fakeClient = new FakePsr18Client();` (to be replaced by real client per draft 11).
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:45-50` — `execute()` receives the client indirectly via `RunnerFacade` (draft 32 core).
- `packages/cli/composer.json` — `guzzlehttp/guzzle: ^7.9` already a dependency.

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

- **Public API impact:** additive CLI option; client construction internal to `RunCommand`.
- **Blast radius:** `packages/cli` `run` outbound TLS; no document or validator impact.
- **Severity:** medium — enables mTLS-protected APIs; no behaviour change by default.
- **Rollback:** remove option; default (non-mTLS) client unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--mtls` option on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| No mTLS/certificate code in php-arazzo | T1 | `grep -rIn -i "mtls\|clientCert\|certificate" packages --exclude-dir=vendor` → only comment/char matches | executed grep |
| Guzzle already a CLI dependency | T1 | `packages/cli/composer.json` `guzzlehttp/guzzle: ^7.9` | direct read |
| Draft 32 is the core dependency | T2 | draft 32 (core mTLS logic) | cross-ref |