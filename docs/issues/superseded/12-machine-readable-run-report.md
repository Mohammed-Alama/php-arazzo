# Draft issue 12 — Machine-readable run reports

- **Suggested title:** `[Feature] `arazzo run --json` and `--export-run` structured run report`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` console output and run result serialization; `packages/runner` `RunnerFacade` result shape.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — required for CI use and for agent/tool consumption.

### 📝 2. Problem Statement & Intent

`arazzo run` writes human-oriented text only (`RunCommand.php:173-189`). There is
no way to obtain the workflow id, status, per-step status, outputs, failure
category, attempts or warnings as structured data. Every consumer must scrape
the ANSI-coloured output.

`RunnerFacade::execute()` already returns a structured array with `workflowId`,
`status`, `outputs`, `stepsSpent`, `workflowCallStack` and a `steps` map
(`packages/runner/src/RunnerFacade.php:44-70`), but the CLI discards that shape
into text.

`#85` covers `--format=json` for **`validate`** only. This draft covers the
**run result** contract and a durable, versioned run artifact.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Add `--json` to `arazzo run`. Stdout is exactly one JSON object; human text
  goes to stderr so stdout stays machine-parseable.
- Define a stable `run-report.v1` shape:
  - `schemaVersion`
  - `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`
  - `run.inputs`, `run.outputs`
  - `run.steps[]`: `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `failureCategory`, `error`, `durationMs`
  - `run.warnings[]`: stable code, message, step id
- Add `--export-run <path>` to write the same report to a file, atomically.
- Add a `schema run` command (or `run --print-schema`) that emits the JSON
  Schema for the report so consumers can validate it.
- Keep exit codes: `0` succeeded, non-zero failed; JSON never changes the exit
  code semantics.
- Redact secret-bearing values in the report once draft 22 lands; until then,
  document that `inputs`/`outputs` are emitted verbatim.

**Non-goals**

- Replacing the human output format.
- JSON output for every other command (track separately; `validate` is #85).
- The full trace artifact with request/response bodies (draft 14).

**Prior art in this repository**

- `RunnerFacade::execute()` already returns a structured result.
- `CliRunResult::fromStatus()` exists for the async CLI worker
  (`packages/cli/src/Console/Cli/CliRunResult.php`).
- `#85` establishes the precedent for a JSON formatter on a command.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Provide a machine-readable, versioned run report (`run-report.v1`) from `arazzo run` via `--json` and `--export-run`, so CI and agents can consume structured run results instead of scraping human-oriented text.

**Architectural Boundary:** Bounded context = CLI output + run result serialization. Packages: `packages/cli` (`RunCommand` output/serialization), `packages/runner` (`RunnerFacade` result shape), `packages/runtime` (versioned artifact schemas). Core contract (`run-report.v1` schema + serializer) is draft 45; this draft is the CLI port only.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** JSON serialization is O(result size); one JSON object to stdout; no per-step overhead beyond existing result collection.
- [ ] **Flexible:** Versioned schema (`run-report.v1`) allows future breaking changes as `v2`; additive CLI options; human text unaffected unless `--json` opted in.
- [ ] **Maintainable:** `RunReportSerializer` (draft 45 core) turns `RunnerFacade` result into the shape; deterministic output; no new dependencies beyond Symfony Console + `json_encode`.
- [ ] **Operable:** `schema run` emits the JSON Schema for consumer validation; exit codes unchanged (0 succeeded, non-zero failed); atomic file write for `--export-run`.
- [ ] **Reliable:** `run.status` matches process exit code; deterministic JSON; idempotent across runs with same inputs.
- [ ] **Safe:** No state mutation; output is read-only serialization of the run result.
- [ ] **Secure:** Secret redaction (draft 22) deferred; until then `inputs`/`outputs` emitted verbatim with a documented warning.
- [ ] **Suitable:** Required for CI use and agent/tool consumption; provides workflow id, status, per-step status, outputs, failure category, attempts, warnings.
- [ ] **Usable:** Exactly one JSON object to stdout; `--export-run <path>` writes to file; `schema run` for consumer validation.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--json` writes exactly one JSON object to stdout**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 (`workflowId: "wf-001"`) with one step (`stepId: "step-1"`, status `succeeded`); `arazzo run doc.arazzo --json` is invoked; `RunReportSerializer` (draft 45 core) is wired into `RunCommand`; `--json` is passed on the command line.
- **When** `php bin/console arazzo run doc.arazzo --json` executes and the run completes.
- **Then** stdout contains exactly one JSON object (no human text on stdout); human-oriented text goes to stderr; the JSON object validates against `run-report.v1` schema; `run.workflowId` is `"wf-001"`; `run.status` is `"succeeded"`.

**Scenario 2: The JSON object validates against `run-report.v1`**
- **Given** the `--json` output from a successful run; the `run-report.v1` JSON Schema is published (draft 45 core).
- **When** the JSON object is validated against the `run-report.v1` JSON Schema.
- **Then** schema validation passes; the object contains `schemaVersion`, `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`, `run.inputs`, `run.outputs`, `run.steps[]`, `run.warnings[]`.

**Scenario 3: `run.status` matches the process exit code**
- **Given** an Arazzo document containing workflow index 0 where step index 0 fails (HTTP 500 with failing `successCriteria`); `arazzo run doc.arazzo --json` is invoked.
- **When** `--json` is emitted after the run completes.
- **Then** `run.status` is `"failed"`; the process exits with a non-zero code; `run.status` matches the process exit code.

**Scenario 4: Every executed step appears in `run.steps`**
- **Given** an Arazzo document containing workflow index 0 with three steps (`stepId: "step-1"`, `stepId: "step-2"`, `stepId: "step-3"`); all steps execute successfully.
- **When** `--json` is emitted.
- **Then** `run.steps[]` contains three entries; each entry has `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs`; `failureCategory` is present when a step failed; `run.outputs` contains the evaluated workflow outputs.

**Scenario 5: `--export-run <path>` writes the same bytes to a file**
- **Given** `arazzo run doc.arazzo --export-run report.json` is invoked without `--json`; the run completes successfully.
- **When** `--export-run` writes the report to `report.json`.
- **Then** the file `report.json` contains the same JSON bytes as the `--json` output would produce; stdout remains human-readable (not JSON); unless `--json` is also passed, stdout is human-oriented text.

**Scenario 6: `schema run` prints the JSON Schema and exits 0**
- **Given** `php bin/console arazzo run schema run` is invoked; the `run-report.v1` JSON Schema is published.
- **When** the `schema run` subcommand executes.
- **Then** the JSON Schema for `run-report.v1` is printed to stdout; the command exits with code 0.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo run <file> --json` writes exactly one JSON object to stdout and no human text to stdout
- [ ] The object validates against the published `run-report.v1` JSON Schema (draft 45 core)
- [ ] `run.status` is `succeeded` or `failed` and matches the process exit code
- [ ] Every executed step appears in `run.steps` with `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs`, `failureCategory` when failed
- [ ] `run.outputs` contains the evaluated workflow outputs
- [ ] `--export-run <path>` writes the same bytes to a file; stdout human-readable unless `--json` also passed
- [ ] `schema run` prints the JSON Schema for `run-report.v1` and exits 0
- [ ] Representative run fixture-tested: known document, deterministic fake transport, asserted JSON shape and values
- [ ] Documentation includes schema version and stability promise
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via existing CLI error handling
- [ ] Core contract (`run-report.v1` schema + serializer) is draft 45; this draft is the CLI port only


### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:173-189` — text-only rendering.
- `packages/runner/src/RunnerFacade.php:44-70` — structured result already available.
- `packages/cli/src/Console/Cli/CliRunResult.php` — existing result value object.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Command/RunCommand.php#L173-L189"><code>RunCommand.php:173-189</code></a> @ <code>ea8c55a</code></summary>

```php
private function renderOutput(OutputInterface $output, array $result): void
{
    $output->writeln(sprintf('workflow <info>%s</info>: <comment>%s</comment>', $result['workflowId'], $result['status']));

    foreach ($result['steps'] as $stepResult) {
        $status = $stepResult['success'] ? '<info>✔</info>' : '<error>✘</error>';
        $output->writeln(sprintf('  %s %s', $status, $stepResult['stepId']));
    }

    if ($result['outputs'] !== []) {
        $output->writeln('outputs:');
        foreach ($result['outputs'] as $name => $value) {
            $output->writeln(sprintf('  %s = %s', $name, json_encode($value)));
        }
    }
}
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI option and a new, versioned output contract.
- **Blast radius:** `packages/cli`; consumers that parse human output are unaffected unless they opt in.
- **Severity:** medium — without it, CI and agents cannot consume run results reliably.
- **Rollback:** remove the options; the schema is versioned, so a future breaking change ships as `run-report.v2`.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The CLI writes text only | T1 | `RunCommand.php:173-189` | direct read |
| The runner returns a structured result | T1 | `RunnerFacade.php:44-70` | direct read |
| No `--json` exists on any CLI command | T1 | `grep -rIn "'--json'" packages/cli/src/Console` → no matches | executed |
| `#85` covers only `validate` formatters | T1 | issue #85 title and body | read |
| A versioned JSON Schema is feasible without a new dependency | T2 | Symfony Console options + `json_encode` | inferred |
