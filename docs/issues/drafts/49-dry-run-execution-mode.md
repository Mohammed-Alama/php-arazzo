# Draft issue 49 — Dry-run execution mode

- **Suggested title:** `[Feature] Core dry-run mode that resolves the request plan without side effects`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `runner`, `protocol-http`, `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` step execution; `packages/protocol-http` request compilation; `packages/sources` preflight and operation resolution.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — the safest way to preview a workflow before it touches a real system.

### 📝 2. Problem Statement & Intent

`PreflightValidator` can resolve operations and validate document structure without side effects (`packages/sources/src/Validator/PreflightValidator.php:48-59`), but there is no core dry-run mode that uses it. There is no way to answer:

- Which requests will this workflow send, in what order, with which parameters, headers and bodies?
- Do all `operationId` / `operationPath` targets resolve?
- Are all required inputs present and correctly typed?
- Which expressions depend on a response that would only exist at runtime?

Without a dry-run, authors discover these problems by executing destructive requests.

Expected: a core dry-run execution mode that performs full document validation, preflight, operation resolution and request compilation, then stops before sending. It produces a request plan that any entry point (CLI, MCP, UI) can display.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Dry-run performs full document validation, preflight, operation resolution and request compilation, then stops before sending.
- Produces a request plan: step id, method, URL, headers, query, body preview, and values that could not be resolved without a live response, marked with a stable placeholder such as `<unresolved:response.body#/id>`.
- Does not evaluate live success criteria; reports them as `not-evaluated`.
- Returns success when the plan is fully resolvable, failure when a hard resolution error occurs (unknown operation, invalid input, unresolved required value).
- Soft placeholders that only exist because a previous response is unavailable do not fail the command by default; strict mode promotes them to errors.
- Inputs are validated and schema defaults applied (draft 52 core), so the plan uses the same values a real run would.
- Never constructs a live transport. The dry-run must be safe even if the PSR-18 client would send real requests.

**Non-goals**

- Simulating responses or control flow. The plan is a partial request plan, not a mock execution.
- Replacing `validate`; dry-run runs the whole preflight and resolution path.
- Evaluating criteria that depend on a live response.
- CLI flag (`--dry-run`) — tracked separately in draft 58.

**Prior art in this repository**

- `PreflightValidator::validate()` resolves each step.
- `RequestCompiler` (`packages/engine/src/Execution/RequestCompiler.php`) already builds request records.
- `RunCommand` already accepts an injectable `ClientInterface`; the CLI port can supply a transport that throws if touched, proving no request escapes.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Dry-run sends zero outbound requests; a transport that throws on use is never invoked.
- [ ] Dry-run produces a request plan for every step that can be resolved.
- [ ] Unresolved response-derived values use a stable placeholder and identify the expression.
- [ ] Unknown operations, missing required inputs and invalid documents fail with a stable error and non-zero exit.
- [ ] Strict mode promotes unresolved placeholders to errors.
- [ ] Success criteria are reported as `not-evaluated`.
- [ ] Schema defaults from input validation appear in the plan.
- [ ] `DryRunTest` asserts the transport is never called and covers resolvable, partially resolvable and hard-failure documents.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--dry-run`, `--strict-dry-run` on `RunCommand`) is tracked separately in draft 58.

### 🧬 7. Source Grounding & Code Location

- `packages/sources/src/Validator/PreflightValidator.php:48-59` — side-effect-free preflight.
- `packages/runner/src/Sync/WorkflowExecutor.php:116-152` — execution loop that dry-run must not enter.
- `packages/engine/src/Execution/RequestCompiler.php:83-110` — request record and response decode.

<details><summary><code>RequestCompiler.php:83-110</code></summary>

```php
    public function compile(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): OpenApiPayload
    {
        // ... builds method, URL, headers, query, body from step + context
    }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive dry-run mode and request plan shape; no public contract break.
- **Blast radius:** `packages/runner`, `packages/protocol-http`, `packages/sources`; any entry point that previews executions.
- **Severity:** medium — prevents destructive mistakes and shortens the authoring loop.
- **Rollback:** remove dry-run mode; no data migration.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Preflight can run without side effects | T1 | `PreflightValidator.php:48-59` | direct read |
| `RequestCompiler` can build a request record | T1 | `RequestCompiler.php:83-110` | direct read |
| No dry-run or plan code exists | T1 | grep for `dry.run`/`DryRun` → no matches | executed grep |
| Placeholders are necessary for response-derived values | T2 | step expressions reference prior responses | inferred from expression model |