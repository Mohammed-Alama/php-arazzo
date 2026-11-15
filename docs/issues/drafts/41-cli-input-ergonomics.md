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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core input-merge logic via CLI flags so `arazzo run` accepts repeated `--input`, `key=value` inputs, and `.env` files.

**Architectural Boundary:** Bounded context = CLI `run` command input handling. Packages: `packages/cli` (`RunCommand::parseInputs()` / `--input` option), `packages/runner` (input merge — draft 31 core). CLI port only; core input-merge logic is draft 31.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing is O(flags); input merge O(specs × size); `.env` discovery walks directory tree once.
- [ ] **Flexible:** `--input` (`-i`) repeatable; each value supports `key=value`, `key=@file.json`, inline JSON object, `@file.json`; `.env` overridden by explicit `--input`.
- [ ] **Maintainable:** Additive CLI option behavior; `parseInputs()` may accept array (internal); no new Composer dependency for dotenv.
- [ ] **Operable:** Invalid input errors before execution; exit code non-zero; rollback by reverting `--input` to single-value.
- [ ] **Reliable:** Deterministic merge; duplicate keys — last wins; `.env` values overridden by explicit inputs.
- [ ] **Safe:** No state mutation before execution; `.env` loading is input-only; secret redaction (draft 22) applies separately.
- [ ] **Secure:** No authorization impact; input validation (draft 19/52) applies to merged inputs.
- [ ] **Suitable:** Closes ergonomics gap where many inputs as one JSON blob is poor UX; no `key=value` shorthand today.
- [ ] **Usable:** `arazzo run --input username=amy --input '{"timeout":30}' -i @env.json`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--input` repeatable**
- **Given** `arazzo run doc.yaml --input username=amy --input '{"timeout":30}'`; `RunCommand` accepts repeatable `--input` (`-i`) options; `parseInputs()` (draft 41 core) merges specs in declaration order.
- **When** `php bin/console arazzo run doc.yaml --input username=amy --input '{"timeout":30}'` executes.
- **Then** all inputs are merged in declaration order; `username=amy` (string) and `timeout=30` (integer from JSON) are in the merged input map; later values override earlier ones.

**Scenario 2: Each value supports multiple forms**
- **Given** `--input key=value`, `--input key=@file.json`, `--input '{"k":"v"}'`, `--input @file.json`; each form is parsed by `parseInputs()`.
- **When** each `--input` value is parsed.
- **Then** all forms are accepted and merged; `key=value` sets a string value; `key=@file.json` reads file contents as the value; inline JSON object is parsed; `@file.json` reads the whole file as a JSON object.

**Scenario 3: `.env` discovered and overridden**
- **Given** a `.env` file next to the document (`./.env`) containing `username=envuser`; explicit `--input username=amy` is passed; `.env` discovery walks up from the document's directory.
- **When** inputs are resolved (`.env` loaded first, then explicit inputs override).
- **Then** `.env` values are loaded (`username=envuser`); explicit inputs override `.env` values (`username=amy` wins); final input map has `username=amy`.

**Scenario 4: Invalid input errors before execution**
- **Given** invalid JSON (`--input 'not-json'`) or unparseable `key=value` (`--input 'not-key-value'`); `parseInputs()` validates before execution.
- **When** `php bin/console arazzo run doc.yaml --input 'not-json'` executes.
- **Then** a clear error message is returned before execution; `Command::FAILURE` is returned; `RunnerFacade::execute()` is never called with invalid inputs.

**Scenario 5: `.env` not found — no error**
- **Given** no `.env` file exists next to or above the document's directory; `.env` discovery walks up the directory tree.
- **When** `parseInputs()` attempts `.env` discovery.
- **Then** no error is thrown; `.env` loading is optional; input merge proceeds with only explicit inputs; empty input → `[]` if no explicit inputs either.

**Scenario 4: Invalid input errors before execution**
- **Given** invalid JSON or unparseable `key=value`
- **When`command runs
- **Then`clear error before execution

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `--input` (`-i`) becomes repeatable; values merged in declaration order (later overrides earlier)
- [ ] Each value supports: `key=value`, `key=@file.json`, inline JSON object, `@file.json`
- [ ] `.env` discovered walking up from document's directory; `.env` values overridden by explicit `--input`
- [ ] Duplicate keys: last declaration wins
- [ ] Invalid input errors before execution with clear message
- [ ] `parseInputs()` returns `array<string, mixed>`; empty → `[]`
- [ ] `RunCommandTest` cases: repeated `--input`, `key=value`, `@file`, `.env` discovery, merge precedence, invalid input
- [ ] No new Composer dependency for dotenv (small internal parser)
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core input-merge logic (draft 31) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI option behaviour; `parseInputs()` signature may change to accept an array (internal method).
- **Blast radius:** `packages/cli` `run`; users supplying inputs via `--input`.
- **Severity:** medium — ergonomics; no behaviour change for existing single-JSON `--input` use.
- **Rollback:** revert `--input` to single-value; `.env` loading removed.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--input` single `VALUE_REQUIRED` | T1 | `RunCommand.php:50` | direct read |
| `parseInputs()` handles one string / `@file` | T1 | `RunCommand.php:140-` | direct read |
| No `.env` loading anywhere in CLI | T1 | `grep -rIn "\.env\|getenv\|dotenv" packages/cli/src` → no matches | executed grep |
| Draft 31 is the core dependency | T2 | draft 31 (core input-merge logic) | cross-ref |