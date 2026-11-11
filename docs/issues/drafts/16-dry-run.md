# Draft issue 16 — `arazzo run --dry-run`

- **Suggested title:** `[Feature] `arazzo run --dry-run` — resolve the request plan without side effects`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `protocol-http`, `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/runner` step execution; `packages/protocol-http` request compilation; `packages/sources` preflight and operation resolution.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — the safest way to preview a workflow before it touches a real system.

### 📝 2. Problem Statement & Intent

`PreflightValidator` can resolve operations and validate document structure
without side effects (`packages/sources/src/Validator/PreflightValidator.php:48-59`),
but `arazzo run` has no mode that uses it. There is no way to answer:

- Which requests will this workflow send, in what order, with which
  parameters, headers and bodies?
- Do all `operationId` / `operationPath` targets resolve?
- Are all required inputs present and correctly typed?
- Which expressions depend on a response that would only exist at runtime?

Without a dry-run, authors discover these problems by executing destructive
requests.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `arazzo run <file> --dry-run` performs full document validation, preflight,
  operation resolution and request compilation, then stops before sending.
- Print a request plan: step id, method, URL, headers, query, body preview, and
  the values that could not be resolved without a live response, marked with a
  stable placeholder such as `<unresolved:response.body#/id>`.
- Do not evaluate live success criteria; report them as `not-evaluated`.
- Exit `0` when the plan is fully resolvable, non-zero when a hard resolution
  error occurs (unknown operation, invalid input, unresolved required value).
  Soft placeholders that only exist because a previous response is unavailable
  do not fail the command by default; `--strict-dry-run` promotes them to errors.
- `--json` emits the plan in the `run-report.v1` shape with `status: planned`
  and a `plan` section.
- Never construct a live transport. The dry-run must be safe even if the PSR-18
  client would send real requests.
- Inputs are validated and schema defaults applied (draft 19), so the plan uses
  the same values a real run would.

**Non-goals**

- Simulating responses or control flow. The plan is a partial request plan, not
  a mock execution.
- Replacing `validate`; dry-run runs the whole preflight and resolution path.
- Evaluating criteria that depend on a live response.

**Prior art in this repository**

- `PreflightValidator::validate()` resolves each step.
- `RequestCompiler` (`packages/engine/src/Execution/RequestCompiler.php`) already
  builds request records.
- `RunCommand` already accepts an injectable `ClientInterface`; dry-run can
  supply a transport that throws if touched, proving no request escapes.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo run <file> --dry-run` sends zero outbound requests; a transport that throws on use is never invoked.
- [ ] The command prints a request plan for every step that can be resolved.
- [ ] Unresolved response-derived values use a stable placeholder and identify the expression.
- [ ] Unknown operations, missing required inputs and invalid documents fail with a stable error and non-zero exit code.
- [ ] `--strict-dry-run` promotes unresolved placeholders to errors.
- [ ] Success criteria are reported as `not-evaluated`.
- [ ] `--json` emits a plan using the `run-report.v1` contract (draft 12).
- [ ] Schema defaults from input validation appear in the plan.
- [ ] Tests assert the transport is never called and cover resolvable, partially resolvable and hard-failure documents.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/sources/src/Validator/PreflightValidator.php:48-59` — side-effect-free preflight.
- `packages/runner/src/Sync/WorkflowExecutor.php:116-152` — execution loop that dry-run must not enter.
- `packages/engine/src/Execution/RequestCompiler.php:83-110` — request record and response decode.
- `packages/cli/src/Console/Command/RunCommand.php:45-51` — current options; no `--dry-run`.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Command/RunCommand.php#L45-L51"><code>RunCommand.php:45-51</code></a> @ <code>ea8c55a</code></summary>

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

- **Public API impact:** additive option.
- **Blast radius:** `packages/cli`, `packages/runner`; a new no-op transport path.
- **Severity:** medium — prevents destructive mistakes and shortens the authoring loop.
- **Rollback:** remove the option.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Preflight can run without side effects | T1 | `PreflightValidator.php:48-59` | direct read |
| `run` has no `--dry-run` | T1 | `RunCommand.php:45-51` | direct read |
| `RequestCompiler` can build a request record | T1 | `RequestCompiler.php:83-110` | direct read |
| No dry-run or plan code exists | T1 | `grep -rIn -i "dry.run" packages` → no matches | executed |
| Placeholders are necessary for response-derived values | T2 | step expressions reference prior responses | inferred from expression model |
