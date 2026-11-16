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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Make execution input collection ergonomic by accepting multiple input specifications (`key=value`, `key=@file.json`, inline JSON object, `@file.json`), merging in declaration order, plus automatic `.env` discovery.

**Architectural Boundary:** Bounded context = CLI input handling + execution input merge. Packages: `packages/runner` (execution input handling); CLI side referenced in `RunCommand::parseInputs()` for context. Core merge logic is this draft; CLI port (repeatable `--input`/`-i`) is draft 41.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Input merge is O(specs × size); `.env` discovery walks up directory tree once; no runtime execution impact.
- [ ] **Flexible:** Multiple input spec forms; merge in declaration order (later overrides earlier); `.env` overridden by explicit inputs; duplicate keys — last wins.
- [ ] **Maintainable:** Single `mergeInputs()` function; `parseInputs()` returns `array<string, mixed>`; no new Composer dependency (small internal dotenv parser).
- [ ] **Operable:** Invalid input errors before execution with clear message; empty input → `[]`; rollback to single-map input if needed.
- [ ] **Reliable:** Deterministic merge result; idempotent; invalid JSON or unparseable `key=value` errors before execution.
- [ ] **Safe:** No state mutation before execution; `.env` values are inputs only; secret redaction (draft 22) applies separately.
- [ ] **Secure:** No authorization impact; `.env` loading doesn't bypass input validation (draft 19/52).
- [ ] **Suitable:** Closes ergonomics gap where many inputs as one JSON blob is poor UX; no `key=value` shorthand, no repeated input merge, no `.env` loading today.
- [ ] **Usable:** `key=value` shorthand for scalars; `key=@file.json` for file contents; inline JSON object; `@file.json` for whole object.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Multiple input specs accepted**
- **Given** an Arazzo document (`doc.arazzo`) with a workflow that declares `inputs`; `mergeInputs()` (draft 31 core) accepts multiple input specs: `key=value` (string), `key=@file.json` (file contents), inline JSON object, `@file.json` (whole object); `arazzo run doc.arazzo --input 'username=amy' --input '{"timeout":30}' --input @env.json` is invoked; `parseInputs()` returns `array<string, mixed>`.
- **When** `parseInputs()` merges all input specifications into one `array<string, mixed>`.
- **Then** all forms are accepted and merged into one input map; `username` is `"amy"` (string), `timeout` is `30` (integer from JSON), and values from `@env.json` are included; `empty input → []` when no specs provided.

**Scenario 2: Values merged in declaration order**
- **Given** `--input username=amy --input username=bob`; `mergeInputs()` processes specs in declaration order; later specs override earlier ones.
- **When** `mergeInputs(['username=amy', 'username=bob'])` is called.
- **Then** `username=bob` (later overrides earlier); the final value for `username` is `"bob"`; `array_replace()` semantics apply (last declaration wins).

**Scenario 3: `.env` discovered and overridden by explicit inputs**
- **Given** a `.env` file next to the document (`./.env`) containing `username=envuser` and `timeout=60`; explicit `--input username=amy` is passed; `.env` discovery walks up from the document's directory.
- **When** inputs are resolved (`.env` loaded first, then explicit inputs override).
- **Then** `.env` values are loaded (`username=envuser`, `timeout=60`); explicit inputs override `.env` values (`username=amy` wins over `envuser`); final input map: `username=amy`, `timeout=60`.

**Scenario 4: Duplicate keys — last declaration wins**
- **Given** two specs setting the same key (`--input key=first --input key=second`).
- **When** `mergeInputs()` processes both specs.
- **Then** the last declaration's value wins; `key=second`; `parseInputs()` returns `['key' => 'second']`.

**Scenario 5: Invalid input errors before execution**
- **Given** invalid JSON (`--input 'not-json'`) or unparseable `key=value` (`--input 'not-key-value'`); `parseInputs()` validates before execution.
- **When** `parseInputs()` is called with invalid input.
- **Then** a clear error message is returned; `Command::FAILURE` is returned; no execution proceeds; `RunnerFacade::execute()` is never called with invalid inputs.

**Scenario 6: `.env` not found — no error**
- **Given** no `.env` file exists next to or above the document's directory; `.env` discovery walks up the directory tree.
- **When** `parseInputs()` attempts `.env` discovery.
- **Then** no error is thrown; `.env` loading is optional; input merge proceeds with only explicit inputs; empty input → `[]` if no explicit inputs either.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Execution accepts multiple input specs: `key=value`, `key=@file.json`, inline JSON object, `@file.json`
- [ ] Values merged in declaration order (later overrides earlier)
- [ ] `.env` discovered by walking up from document's directory; `.env` values overridden by explicit inputs
- [ ] Duplicate keys: last declaration wins
- [ ] Invalid input errors before execution with clear message
- [ ] `parseInputs()` returns `array<string, mixed>`; empty input → `[]`
- [ ] `RunnerTest` cases: repeated inputs, `key=value`, `@file`, `.env` discovery, merge precedence, invalid input
- [ ] No new Composer dependency for dotenv (small internal parser)
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (repeatable `--input`/`-i`) tracked separately in draft 41

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive input-spec parameter; internal merge logic.
- **Blast radius:** `packages/runner` execution; users supplying inputs.
- **Severity:** medium — ergonomics; no behaviour change for existing single-map input use.
- **Rollback:** revert to single-map input; `.env` loading removed.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `--input` single `VALUE_REQUIRED` on CLI | T1 | `RunCommand.php:50` | direct read (CLI side) |
| `parseInputs()` handles one string / `@file` | T1 | `RunCommand.php:140-` | direct read (CLI side) |
| No `.env` loading anywhere | T1 | `grep -rIn "\.env\|getenv\|dotenv" packages` → no matches | executed grep |
| Workflow `inputs` is a JSON Schema map | T2 | Arazzo spec §5.8.4 Workflow Object (`inputs` field) | external spec |