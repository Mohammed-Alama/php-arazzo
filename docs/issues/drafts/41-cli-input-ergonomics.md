# Draft issue 41 — CLI: repeated `--input`, `key=value`, `.env` loading

- **Suggested title:** `[Feature] `arazzo run` accept repeated `--input`, `key=value` inputs and `.env` files`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #85 CLI input handling)
- **Packages:** `cli`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand::parseInputs()` / `--input` option; `packages/runner` input merge (draft 31 core).
- **Arazzo spec version:** independent of spec version; workflow `inputs` is a JSON Schema (§5.8.4 Workflow Object).
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core input-merge logic lives in draft 31.
- **Kind / level:** `kind:feature`, `level:story`, under #85 (CLI input handling).
- **Priority:** P2 — supplying many inputs as one JSON blob is ergonomically poor; there is no `key=value` shorthand, no repeated `--input`, and no `.env` loading.

### 📝 2. Problem Statement & Intent

Draft 31 adds core input-merge logic (multiple input specs, `.env` discovery). This draft exposes it via CLI flags:

- `RunCommand` has a single `--input` option accepting "inline JSON or @file.json" (`RunCommand.php:50`).
- Only one `--input` is allowed; multiple input documents cannot be merged.
- No `key=value` shorthand for scalar inputs (e.g. `--input username=amy`).
- No `.env` file discovery/loading.

Expected: `arazzo run --input username=amy --input '{"timeout":30}' -i @env.json` merges all sources; `key=value` args set scalar inputs; a `.env` next to (or above) the Arazzo file is loaded automatically.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `--input` (`-i`) becomes repeatable; values merged in declaration order (later overrides earlier).
- Each value supports: `key=value`, `key=@file.json`, inline JSON object, `@file.json`.
- `.env` discovered by walking up from the document's directory; `.env` values overridden by explicit `--input`.
- Duplicate keys: last declaration wins.
- Invalid input errors before execution with a clear message.

**Non-goals**

- Core input-merge logic — that is draft 31.
- Type coercion of `.env` string values (draft 19); redaction of secrets (draft 22).

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--input` single `VALUE_REQUIRED`.
- `packages/cli/src/Console/Command/RunCommand.php:68-73` — `$rawInput = $input->getOption('input'); $inputs = $this->parseInputs(...)`.
- `packages/cli/src/Console/Command/RunCommand.php:140-` — `parseInputs()` implementation (single string decode / `@file`).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo run doc.arazzo.yaml --input username=amy --input '{"timeout":30}' -i @env.json
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo run <file> --input <spec>...`.
- Each `--input` value supports: `key=value` (string), `key=@file.json` (file contents), inline JSON object, `@file.json` (whole object).
- `.env` discovered by walking up from the document's directory; `.env` values overridden by explicit `--input`.
- Duplicate keys: last declaration wins.
- Invalid JSON or unparseable `key=value` errors before execution with a clear message.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `--input` (`-i`) becomes repeatable; values merged in declaration order (later overrides earlier).
- [ ] Each value supports: `key=value`, `key=@file.json`, inline JSON object, `@file.json`.
- [ ] `.env` discovered by walking up from the document's directory; `.env` values overridden by explicit `--input`.
- [ ] Duplicate keys: last declaration wins.
- [ ] Invalid input errors before execution with a clear message.
- [ ] `parseInputs()` returns `array<string, mixed>`; empty → `[]`.
- [ ] `RunCommandTest` gains cases: repeated `--input`, `key=value`, `@file`, `.env` discovery, merge precedence, invalid input.
- [ ] No new Composer dependency for dotenv (small internal parser).
- [ ] PHPStan max and Pint pass.
- [ ] **Core input-merge logic** (draft 31) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--input` single `VALUE_REQUIRED`.
- `packages/cli/src/Console/Command/RunCommand.php:68-73` — `$rawInput = $input->getOption('input'); $inputs = $this->parseInputs(...)`.
- `packages/cli/src/Console/Command/RunCommand.php:140-` — `parseInputs()` implementation (single string decode / `@file`).

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

- **Public API impact:** additive CLI option behaviour; `parseInputs()` signature may change to accept an array (internal method).
- **Blast radius:** `packages/cli` `run`; users supplying inputs via `--input`.
- **Severity:** medium — ergonomics; no behaviour change for existing single-JSON `--input` use.
- **Rollback:** revert `--input` to single-value; `.env` loading removed.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--input` single `VALUE_REQUIRED` | T1 | `RunCommand.php:50` | direct read |
| `parseInputs()` handles one string / `@file` | T1 | `RunCommand.php:140-` | direct read |
| No `.env` loading anywhere in CLI | T1 | `grep -rIn "\.env\|getenv\|dotenv" packages/cli/src` → no matches | executed grep |
| Draft 31 is the core dependency | T2 | draft 31 (core input-merge logic) | cross-ref |