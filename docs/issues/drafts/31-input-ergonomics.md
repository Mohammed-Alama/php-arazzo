# Draft issue 31 — Input collection ergonomics

- **Suggested title:** `[Feature] Accept repeated `key=value` inputs and `.env` files at execution time`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #85 CLI input handling)
- **Packages:** `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/runner` execution input handling; workflow `inputs` is a JSON Schema (§5.8.4 Workflow Object).
- **Arazzo spec version:** independent of spec version; workflow `inputs` supplies values at run time regardless of entry point.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #85 (CLI input handling).
- **Priority:** P2 — supplying many inputs as one JSON blob is ergonomically poor; there is no `key=value` shorthand, no repeated input merge, and no `.env` loading.

### 📝 2. Problem Statement & Intent

Execution input collection currently accepts a single input map. Ergonomic gaps:

- Multiple input sources cannot be merged into one map.
- No `key=value` shorthand for scalar inputs (e.g. `username=amy`).
- No `.env` file discovery/loading next to (or above) the document.

Expected: execution accepts input specifications in multiple forms — `key=value` (string), `key=@file.json` (file contents), inline JSON object, `@file.json` (whole object) — merged in declaration order (later overrides earlier), plus automatic `.env` discovery by walking up from the document's directory (`.env` values overridden by explicit inputs).

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Execution accepts multiple input specs: `key=value` (string), `key=@file.json` (file contents), inline JSON object, `@file.json` (whole object).
- Values merged in declaration order (later overrides earlier).
- `.env` discovered by walking up from the document's directory; `.env` values overridden by explicit inputs.
- Duplicate keys: last declaration wins.
- Invalid input errors before execution with a clear message.

**Non-goals**

- Type coercion of `.env` string values to the JSON Schema (draft 19).
- Redaction of secret values loaded from `.env` (draft 22).
- CLI flags (repeatable `--input`/`-i` on `RunCommand`) — tracked separately in draft 41.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RunCommand.php:140-` — `parseInputs()` decodes a single JSON string or `@file`; the merge point for new input sources (CLI side, referenced for context).
- `packages/cli/src/Console/Command/RunCommand.php:122` — `RunnerFacade::execute()` receives the merged input map (CLI side).

### 📄 4. Structural Artifacts & Raw Inputs

```php
// Input specs accepted by execution:
$inputs = mergeInputs([
    'username=amy',                                    // key=value (string)
    'timeout={"timeout":30}',                          // key=JSON value
    'headers=@headers.json',                           // key=@file
    '@env.json',                                       // whole file object
]);
```

`.env` next to the document:
```
username=amy
password=hunter2
timeout=30
```

Expected: all sources merged into one input map; `.env` discovered by walking up from the document's directory; explicit inputs override `.env`; duplicate keys — last declaration wins.

### 🌀 5. Runtime Execution State Context

- Entry point supplies input specs; execution merges them into `array<string, mixed>`.
- Result passed to `RunnerFacade::execute($document, $workflowId, $inputs)`.
- Invalid JSON or unparseable `key=value` errors before execution with a clear message.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Execution accepts multiple input specs of forms: `key=value`, `key=@file.json`, inline JSON object, `@file.json`.
- [ ] Values merged in declaration order (later overrides earlier).
- [ ] `.env` discovered by walking up from the document's directory; `.env` values overridden by explicit inputs.
- [ ] Duplicate keys: last declaration wins.
- [ ] Invalid input errors before execution with a clear message.
- [ ] `parseInputs()` returns `array<string, mixed>`; empty input → `[]`.
- [ ] `RunnerTest` gains cases: repeated inputs, `key=value`, `@file`, `.env` discovery, merge precedence, invalid input.
- [ ] No new Composer dependency for dotenv (small internal parser).
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (repeatable `--input`/`-i` flag on `RunCommand`) is tracked separately in draft 41.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:45-51` — `--input` single `VALUE_REQUIRED` (CLI side, referenced for context only).
- `packages/cli/src/Console/Command/RunCommand.php:68-73` — `$rawInput = $input->getOption('input'); $inputs = $this->parseInputs(...)` (CLI side).
- `packages/cli/src/Console/Command/RunCommand.php:140-` — `parseInputs()` implementation (single string decode / `@file`) (CLI side).
- `packages/runner/src/RunnerFacade.php` — `execute()` receives merged input map.

<details><summary><code>RunCommand.php:68-73</code> (CLI side, for context)</summary>

```php
        /** @var string|null $rawInput */
        $rawInput = $input->getOption('input');
        $inputs = $this->parseInputs($rawInput, $output);
        if ($inputs === null) {
            return Command::FAILURE;
        }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive input-spec parameter; internal merge logic.
- **Blast radius:** `packages/runner` execution; users supplying inputs.
- **Severity:** medium — ergonomics; no behaviour change for existing single-map input use.
- **Rollback:** revert to single-map input; `.env` loading removed.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--input` single `VALUE_REQUIRED` on CLI | T1 | `RunCommand.php:50` | direct read (CLI side) |
| `parseInputs()` handles one string / `@file` | T1 | `RunCommand.php:140-` | direct read (CLI side) |
| No `.env` loading anywhere | T1 | `grep -rIn "\.env\|getenv\|dotenv" packages` → no matches | executed grep |
| Workflow `inputs` is a JSON Schema map | T2 | Arazzo spec §5.8.4 Workflow Object (`inputs` field) | external spec |