# Draft issue 58 — CLI: `run --dry-run` request plan preview

- **Suggested title:** `[Feature] `arazzo run --dry-run` — resolve the request plan without side effects`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `protocol-http`, `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/runner` step execution; `packages/protocol-http` request compilation; `packages/sources` preflight and operation resolution.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core dry-run mode lives in draft 49.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — the safest way to preview a workflow before it touches a real system.

### 📝 2. Problem Statement & Intent

Draft 49 core adds a dry-run execution mode that performs full document validation, preflight, operation resolution and request compilation, then stops before sending. This draft exposes it via the CLI:

- `PreflightValidator` can resolve operations and validate document structure without side effects (`packages/sources/src/Validator/PreflightValidator.php:48-59`), but `arazzo run` has no mode that uses it.
- There is no way to answer: which requests will this workflow send, in what order, with which parameters, headers and bodies?

Expected: `arazzo run <file> --dry-run` performs full preflight and resolution, then stops before sending. Print a request plan and never construct a live transport.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo run <file> --dry-run` performs full document validation, preflight, operation resolution and request compilation, then stops before sending.
- Print a request plan: step id, method, URL, headers, query, body preview, and values that could not be resolved without a live response, marked with a stable placeholder such as `<unresolved:response.body#/id>`.
- Do not evaluate live success criteria; report them as `not-evaluated`.
- Exit `0` when the plan is fully resolvable, non-zero when a hard resolution error occurs (unknown operation, invalid input, unresolved required value).
- Soft placeholders that only exist because a previous response is unavailable do not fail the command by default; `--strict-dry-run` promotes them to errors.
- `--json` emits the plan in the `run-report.v1` shape with `status: planned` and a `plan` section (draft 54).
- Never construct a live transport. The dry-run must be safe even if the PSR-18 client would send real requests.
- Inputs are validated and schema defaults applied (draft 61 core), so the plan uses the same values a real run would.

**Non-goals**

- Simulating responses or control flow. The plan is a partial request plan, not a mock execution.
- Replacing `validate`; dry-run runs the whole preflight and resolution path.
- Evaluating criteria that depend on a live response.
- Core dry-run mode — that is draft 49.

**Prior art in this repository**

- `PreflightValidator::validate()` resolves each step.
- `RequestCompiler` (`packages/engine/src/Execution/RequestCompiler.php`) already builds request records.
- `RunCommand` already accepts an injectable `ClientInterface`; dry-run can supply a transport that throws if touched, proving no request escapes.
- Draft 49 core: dry-run execution mode and request plan.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core dry-run mode via CLI flag so `arazzo run --dry-run` previews the request plan without sending requests.

**Architectural Boundary:** Bounded context = CLI `run` command dry-run mode. Packages: `packages/cli` (`RunCommand`), `packages/runner` (step execution), `packages/protocol-http` (request compilation), `packages/sources` (preflight). CLI port only; core mode is draft 49.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Zero outbound requests; transport that throws on use never invoked; full preflight without execution.
- [ ] **Flexible:** `--strict-dry-run` promotes unresolved placeholders to errors; `--json` emits plan using `run-report.v1`; composes with `--step`, `--trace`.
- [ ] **Maintainable:** Dry-run is distinct execution mode not entering sequential loop; reuses `PreflightValidator` and `RequestCompiler`.
- [ ] **Operable:** Exit `0` when plan resolvable; non-zero on hard errors; `--strict-dry-run` for CI gates.
- [ ] **Reliable:** Deterministic plan output; unresolved values use stable placeholders; no side effects.
- [ ] **Safe:** Never constructs live transport; safe even if PSR-18 client would send real requests.
- [ ] **Secure:** No new authorization surface; input validation (draft 19/52) applies to dry-run.
- [ ] **Suitable:** Safest way to preview workflow before touching real system; reports `not-evaluated` criteria.
- [ ] **Usable:** `--dry-run` and `--strict-dry-run` flags; request plan printed for every resolvable step.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--dry-run` sends zero outbound requests**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with two HTTP steps; a PSR-18 client that throws `TransportException` on any `sendRequest()` call is injected; `DryRunExecutionMode` (draft 49 core) is wired into `RunCommand`; `PreflightValidator` and `RequestCompiler` are used for preflight and request compilation without entering the execution loop.
- **When** `php bin/console arazzo run doc.arazzo --dry-run` executes.
- **Then** zero outbound requests are sent; the throwing transport is never invoked; `PreflightValidator` resolves all operations; `RequestCompiler` compiles request records for each step; a request plan is printed to stdout; the command exits with code 0.

**Scenario 2: Request plan printed for every resolvable step**
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

**Scenario 5: `--strict-dry-run` promotes placeholders to errors**
- **Given** `--strict-dry-run` is set and an unresolved response-derived value exists (`$steps.step-1.outputs.orderId` unresolved); `DryRunExecutionMode` treats unresolved placeholders as errors in strict mode.
- **When** `php bin/console arazzo run doc.arazzo --dry-run --strict-dry-run` executes.
- **Then** placeholder is promoted to error (`dry-run.unresolved_placeholder`); `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`; the command exits with non-zero code.

**Scenario 6: `--json` emits plan using `run-report.v1`**
- **Given** `--dry-run --json` is passed; `DryRunExecutionMode` produces a request plan that conforms to the `run-report.v1` contract.
- **When** `php bin/console arazzo run doc.arazzo --dry-run --json` executes.
- **Then** JSON plan using `run-report.v1` contract is emitted to stdout; the plan validates against `run-report.v1` JSON Schema; `run.status` is `"succeeded"`; `run.steps[]` contains request plan entries for each step.

**Scenario 7: Schema defaults appear in plan**
- **Given** an input with declared default `{ type: "integer", "default": 3 }` for property `retries`; `--input '{"username": "alice"}'` is passed without supplying `retries`; `PreflightValidator::validateInputs()` applies defaults before dry-run.
- **When** `--dry-run` runs with inputs and defaults.
- **Then** defaults are visible in the plan; `RequestCompiler` uses `{ "username": "alice", "retries": 3 }` for request compilation; `$inputs.retries` resolves to `3` in expressions.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` gains `--dry-run` option
- [ ] `--dry-run` sends zero outbound requests; transport that throws on use never invoked
- [ ] Request plan printed for every resolvable step
- [ ] Unresolved response-derived values use stable placeholder identifying expression
- [ ] Unknown operations, missing required inputs, invalid documents fail with stable error and non-zero exit
- [ ] `--strict-dry-run` promotes unresolved placeholders to errors
- [ ] Success criteria reported as `not-evaluated`
- [ ] `--json` emits plan using `run-report.v1` contract (draft 45/54)
- [ ] Schema defaults from input validation appear in plan
- [ ] Tests assert transport never called; cover resolvable, partially resolvable, hard-failure documents
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core mode (dry-run execution) is draft 49; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — current options; no `--dry-run`.
- `packages/sources/src/Validator/PreflightValidator.php:48-59` — side-effect-free preflight.
- `packages/runner/src/Sync/WorkflowExecutor.php:116-152` — execution loop that dry-run must not enter.
- `packages/engine/src/Execution/RequestCompiler.php:83-110` — request record and response decode.
- Draft 49 core: dry-run execution mode and request plan.

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

- **Public API impact:** additive option.
- **Blast radius:** `packages/cli`, `packages/runner`; a new no-op transport path.
- **Severity:** medium — prevents destructive mistakes and shortens the authoring loop.
- **Rollback:** remove the option.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Preflight can run without side effects | T1 | `PreflightValidator.php:48-59` | direct read |
| `run` has no `--dry-run` | T1 | `RunCommand.php:45-51` | direct read |
| `RequestCompiler` can build a request record | T1 | `RequestCompiler.php:83-110` | direct read |
| No dry-run or plan code exists | T1 | grep for `dry.run` in `packages` → no matches | executed grep |
| Draft 49 is the core dependency | T2 | draft 49 (dry-run execution mode) | cross-ref |