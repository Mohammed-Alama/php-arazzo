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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` gains a repeatable `--mtls` option accepting `domain=cert:key[:ca]`.
- [ ] A Guzzle-based PSR-18 client is built with per-domain SSL context.
- [ ] Requests to a configured domain use the mTLS context; other domains use the default client.
- [ ] Missing cert/key files error before execution with a clear message.
- [ ] Invalid option format errors before execution.
- [ ] `--mtls` can be repeated for multiple domains.
- [ ] `RunCommandTest` gains a case: fake PSR-18 client verifies client created with SSL context for matching domain.
- [ ] No behaviour change when `--mtls` is omitted.
- [ ] PHPStan max and Pint pass.
- [ ] **Core mTLS logic** (draft 32) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI option; client construction internal to `RunCommand`.
- **Blast radius:** `packages/cli` `run` outbound TLS; no document or validator impact.
- **Severity:** medium — enables mTLS-protected APIs; no behaviour change by default.
- **Rollback:** remove option; default (non-mTLS) client unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| No `--mtls` option on `RunCommand` | T1 | `RunCommand.php:45-51` | direct read |
| No mTLS/certificate code in php-arazzo | T1 | `grep -rIn -i "mtls\|clientCert\|certificate" packages --exclude-dir=vendor` → only comment/char matches | executed grep |
| Guzzle already a CLI dependency | T1 | `packages/cli/composer.json` `guzzlehttp/guzzle: ^7.9` | direct read |
| Draft 32 is the core dependency | T2 | draft 32 (core mTLS logic) | cross-ref |