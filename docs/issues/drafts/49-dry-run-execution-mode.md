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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a core dry-run execution mode that performs full document validation, preflight, operation resolution and request compilation, then stops before sending — producing a request plan for preview.

**Architectural Boundary:** Bounded context = dry-run execution + request planning. Packages: `packages/runner` (step execution), `packages/protocol-http` (request compilation), `packages/sources` (preflight and operation resolution). Port-agnostic; CLI port tracked in draft 58.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Zero outbound requests; full preflight and resolution without execution; transport that throws on use never invoked.
- [ ] **Flexible:** Strict mode promotes unresolved placeholders to errors; composes with `--step`, `--trace`; `--json` emits plan using `run-report.v1`.
- [ ] **Maintainable:** Dry-run is a distinct execution mode not entering the sequential loop; reuses `PreflightValidator` and `RequestCompiler`.
- [ ] **Operable:** Exit `0` when plan resolvable; non-zero on hard resolution errors; `--strict-dry-run` for CI gates.
- [ ] **Reliable:** Deterministic plan output; unresolved response-derived values use stable placeholders; no side effects.
- [ ] **Safe:** Never constructs live transport; safe even if PSR-18 client would send real requests.
- [ ] **Secure:** No new authorization surface; input validation (draft 52) applies to dry-run inputs.
- [ ] **Suitable:** Safest way to preview a workflow before touching a real system; reports unresolved values and `not-evaluated` criteria.
- [ ] **Usable:** Request plan printed for every resolvable step; placeholders identify unresolvable expressions.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Dry-run sends zero outbound requests**
- **Given** dry-run mode with a PSR-18 client that throws `TransportException` on any `sendRequest()` call; `DryRunExecutionMode` (draft 49 core) is wired into `RunCommand`; `PreflightValidator` and `RequestCompiler` are used for preflight and request compilation without entering the execution loop.
- **When** `php bin/console arazzo run doc.arazzo --dry-run` executes.
- **Then** zero outbound requests are sent; the throwing transport is never invoked; `PreflightValidator` resolves all operations; `RequestCompiler` compiles request records for each step; a request plan is printed to stdout; the command exits with code 0.

**Scenario 2: Request plan produced for every resolvable step**
- **Given** a workflow with resolvable steps (step-1: `GET /items`, step-2: `POST /orders` depending on step-1 output); `RequestCompiler` can compile request records for each step without live responses.
- **When** `DryRunExecutionMode` executes the preflight and resolution path.
- **Then** a request plan is produced for each step (method, URL, headers, query, body preview); step-2's body uses `<unresolved:response.body#/orderId>` for the dependency on step-1's output; all resolvable steps appear in the plan.

**Scenario 3: Unresolved values use stable placeholders**
- **Given** a step referencing a previous response (`$steps.step-1.outputs.orderId`) but step-1 has not been executed (dry-run cannot know live responses); `--strict-dry-run` is NOT set.
- **When** `DryRunExecutionMode` executes and encounters unresolved response-derived values.
- **Then** unresolved value uses stable placeholder `<unresolved:response.body#/orderId>` identifying the expression; the command does not fail; unresolved values are marked but do not block execution.

**Scenario 4: Unknown operations/inputs fail**
- **Given** unknown operation (`$sourceDescriptions.api.nonexistentOp`) or missing required input (`username` required but not supplied via `--input`); `PreflightValidator` validates operations and inputs.
- **When** `php bin/console arazzo run doc.arazzo --dry-run` executes with unknown operation or missing required input.
- **Then** stable error is returned (`operation.not_found` or `preflight.inputs_schema`); `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`; the command exits with non-zero code before any request is sent.

**Scenario 5: Strict mode promotes placeholders to errors**
- **Given** `--strict-dry-run` is set and an unresolved response-derived value exists (`$steps.step-1.outputs.orderId` unresolved); `DryRunExecutionMode` treats unresolved placeholders as errors in strict mode.
- **When** `php bin/console arazzo run doc.arazzo --dry-run --strict-dry-run` executes.
- **Then** placeholder is promoted to error (`dry-run.unresolved_placeholder`); `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`; the command exits with non-zero code.

**Scenario 6: Success criteria reported as `not-evaluated`**
- **Given** workflow with `successCriteria` (`condition: "$statusCode == 200"`); `DryRunExecutionMode` does not evaluate criteria without live responses.
- **When** `php bin/console arazzo run doc.arazzo --dry-run` executes.
- **Then** criteria are reported as `not-evaluated` in the request plan; `ValidationResult::isValid()` returns `true` (criteria are not evaluated in dry-run); the plan indicates criteria status as `not-evaluated` for each step with success criteria.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Dry-run sends zero outbound requests; transport that throws on use never invoked
- [ ] Dry-run produces request plan for every step that can be resolved
- [ ] Unresolved response-derived values use stable placeholder identifying expression
- [ ] Unknown operations, missing required inputs, invalid documents fail with stable error and non-zero exit
- [ ] Strict mode promotes unresolved placeholders to errors
- [ ] Success criteria reported as `not-evaluated`
- [ ] Schema defaults from input validation appear in plan
- [ ] `DryRunTest` asserts transport never called; covers resolvable, partially resolvable, hard-failure documents
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--dry-run`, `--strict-dry-run`) tracked separately in draft 58

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive dry-run mode and request plan shape; no public contract break.
- **Blast radius:** `packages/runner`, `packages/protocol-http`, `packages/sources`; any entry point that previews executions.
- **Severity:** medium — prevents destructive mistakes and shortens the authoring loop.
- **Rollback:** remove dry-run mode; no data migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Preflight can run without side effects | T1 | `PreflightValidator.php:48-59` | direct read |
| `RequestCompiler` can build a request record | T1 | `RequestCompiler.php:83-110` | direct read |
| No dry-run or plan code exists | T1 | grep for `dry.run`/`DryRun` → no matches | executed grep |
| Placeholders are necessary for response-derived values | T2 | step expressions reference prior responses | inferred from expression model |