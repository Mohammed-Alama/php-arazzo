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

### 🧬 7. Source Grounding & Code Location

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
| No `--json` exists on any CLI command | T1 | `grep -rIn "'--json'" packages/cli/src/Console` → no matches | executed |
| `#85` covers only `validate` formatters | T1 | issue #85 title and body | read |
| A versioned JSON Schema is feasible without a new dependency | T2 | Symfony Console options + `json_encode` | inferred |
