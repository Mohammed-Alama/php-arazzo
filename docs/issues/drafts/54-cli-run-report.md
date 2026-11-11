# Draft issue 54 — CLI: `run --json` and `--export-run` structured run report

- **Suggested title:** `[Feature] `arazzo run --json` and `--export-run` emit a structured run report`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `runner`, `runtime`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand` output and run result serialization; `packages/runner` `RunnerFacade` result shape.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core `run-report.v1` contract lives in draft 45.
- **Kind / level:** `kind:feature`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P1 — required for CI use and for agent/tool consumption.

### 📝 2. Problem Statement & Intent

Draft 45 core defines the `run-report.v1` JSON Schema and a `RunReportSerializer`. This draft wires it into the CLI:

- `arazzo run` writes human-oriented text only (`RunCommand.php:173-189`). There is no way to obtain the workflow id, status, per-step status, outputs, failure category, attempts or warnings as structured data. Every consumer must scrape the ANSI-coloured output.
- `#85` covers `--format=json` for **`validate`** only. This draft covers the **run result** contract and a durable, versioned run artifact.

Expected: `arazzo run --json` writes exactly one JSON object to stdout; `--export-run <path>` writes the same report to a file; `schema run` emits the JSON Schema.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Add `--json` to `arazzo run`. Stdout is exactly one JSON object; human text goes to stderr so stdout stays machine-parseable.
- Emit a `run-report.v1` shaped object:
  - `schemaVersion`
  - `run.workflowId`, `run.status`, `run.startedAt`, `run.finishedAt`, `run.durationMs`
  - `run.inputs`, `run.outputs`
  - `run.steps[]`: `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `failureCategory`, `error`, `durationMs`
  - `run.warnings[]`: stable code, message, step id
- Add `--export-run <path>` to write the same report to a file, atomically.
- Add a `schema run` command (or `run --print-schema`) that emits the JSON Schema for the report so consumers can validate it.
- Keep exit codes: `0` succeeded, non-zero failed; JSON never changes the exit code semantics.
- Redact secret-bearing values in the report once draft 22 lands; until then, document that `inputs`/`outputs` are emitted verbatim.

**Non-goals**

- Replacing the human output format.
- JSON output for every other command (track separately; `validate` is #85).
- The full trace artifact with request/response bodies (draft 56).
- Core `run-report.v1` schema — that is draft 45.

**Prior art in this repository**

- `RunnerFacade::execute()` already returns a structured result (`RunnerFacade.php:44-70`).
- `CliRunResult::fromStatus()` exists for the async CLI worker (`packages/cli/src/Console/Cli/CliRunResult.php`).
- `#85` establishes the precedent for a JSON formatter on a command.
- Draft 45 core: `RunReportSerializer` and `run-report.v1` schema.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo run <file> --json` writes exactly one JSON object to stdout and no human text to stdout.
- [ ] The object validates against the published `run-report.v1` JSON Schema.
- [ ] `run.status` is `succeeded` or `failed` and matches the process exit code.
- [ ] Every executed step appears in `run.steps` with `stepId`, `status`, `attempts`, `statusCode`, `outputs`, `durationMs` and `failureCategory` when failed.
- [ ] `run.outputs` contains the evaluated workflow outputs.
- [ ] `--export-run <path>` writes the same bytes to a file and leaves stdout human-readable unless `--json` is also passed.
- [ ] `schema run` prints the JSON Schema for `run-report.v1` and exits `0`.
- [ ] A representative run is fixture-tested: known document, deterministic fake transport, asserted JSON shape and values.
- [ ] Documentation includes the schema version and the stability promise.
- [ ] PHPStan max and Pint pass.
- [ ] **Core contract** (`run-report.v1` schema + serializer) is draft 45; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:173-189` — text-only rendering.
- `packages/runner/src/RunnerFacade.php:44-70` — structured result already available.
- `packages/cli/src/Console/Cli/CliRunResult.php` — existing result value object.
- Draft 45 core: `RunReportSerializer` and `run-report.v1` JSON Schema.

<details><summary><code>RunCommand.php:173-189</code> (text-only rendering to replace)</summary>

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI option and a new, versioned output contract.
- **Blast radius:** `packages/cli`; consumers that parse human output are unaffected unless they opt in.
- **Severity:** medium — without it, CI and agents cannot consume run results reliably.
- **Rollback:** remove the options; the schema is versioned, so a future breaking change ships as `run-report.v2`.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The CLI writes text only | T1 | `RunCommand.php:173-189` | direct read |
| The runner returns a structured result | T1 | `RunnerFacade.php:44-70` | direct read |
| No `--json` exists on any CLI command | T1 | grep for `'--json'` in `packages/cli/src/Console` → no matches | executed |
| `#85` covers only `validate` formatters | T1 | issue #85 title and body | read |
| Draft 45 is the core dependency | T2 | draft 45 (run-report contract) | cross-ref |