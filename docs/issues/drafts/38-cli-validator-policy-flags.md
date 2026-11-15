# Draft issue 38 — CLI: configurable validator rule policy flags

- **Suggested title:** `[Feature] `arazzo validate`accept`--severity`, `--disable-rule`, `--strict` flags`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `cli`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `ValidateCommand`; `packages/document` `ValidatorPolicy` (draft 27 core).
- **Arazzo spec version:** independent of spec version; affects `arazzo validate` CLI invocation.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core policy mechanism lives in draft 27.
- **Kind / level:** `kind:feature`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — operators cannot downgrade noisy rules to warnings or disable rules from the CLI.

### 📝 2. Problem Statement & Intent

Draft 27 adds a core `ValidatorPolicy` object (per-rule severity overrides + disabled list) to `packages/document`. This draft wires it into the CLI:

- `ValidateCommand` calls `RuleSet::default($engine)` with no policy (`ValidateCommand.php:40-42`), so every rule fires at hard-coded `Error` severity.
- No CLI flag exists for `--severity <code>:<level>`, `--disable-rule <code>`, `--enable-rule <code>`, or `--strict`/`--no-strict`.
- No config file is read for validator policy.

Expected: `arazzo validate` accepts policy flags and/or a config file; the exit code reflects errors only (warnings do not fail); warnings printed under a distinct section.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `ValidateCommand` gains `--severity <code>:<level>` (repeatable), `--disable-rule <code>` (repeatable), `--strict`/`--no-strict` flags.
- Optional config file (`arazzo.validate.php` / `arazzo.json` section) read for policy.
- Policy passed to `RuleSet::default()`; disabled rules not active; severity overrides demote errors to warnings.
- `arazzo validate` exits non-zero only when errors (not warnings) are present.
- Warnings printed under a distinct section in output.

**Non-goals**

- Core `ValidatorPolicy` value object — that is draft 27.
- New validation rules — covered by drafts 28, 34, 35, 36.

**Prior art in this repository**

- `packages/cli/src/Console/Command/ValidateCommand.php:40-42` — `RuleSet::default($engine)` with no policy (the gap to fix).
- `packages/document/src/Validator/RuleSet.php:88-100` — `default()` accepts `$disabled`/`$strict` (draft 27 extends with policy object).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo validate doc.arazzo.yaml --severity requestBody-replacements-unique:warn --disable-rule expr.forward_step_ref --no-strict
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo validate <file> [--severity code:level]... [--disable-rule code]... [--strict|--no-strict]`.
- `ValidateCommand` builds `ValidatorPolicy` from flags/config, passes to `RuleSet::default($engine, policy: $policy)`.
- `Validator::validate()` returns `ValidationResult` with separate errors/warnings; exit code based on errors only.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Wire the core `ValidatorPolicy` mechanism (per-rule severity overrides + disabled list) into `arazzo validate` via CLI flags and/or config file, so operators can demote noisy rules to warnings or disable rules.

**Architectural Boundary:** Bounded context = CLI validation command. Packages: `packages/cli` (`ValidateCommand`), `packages/document` (`ValidatorPolicy` — draft 27 core). CLI port only; core policy mechanism is draft 27.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing is O(flags); policy passed to `RuleSet::default()` with no added runtime overhead when no policy supplied.
- [ ] **Flexible:** `--severity <code>:<level>` (repeatable), `--disable-rule <code>` (repeatable), `--enable-rule <code>`, `--strict`/`--no-strict`; optional config file (`arazzo.validate.php`/`arazzo.json`).
- [ ] **Maintainable:** Policy passed to `RuleSet::default()`; disabled rules not active; severity overrides demote errors to warnings; `ValidateCommandTest` gains cases.
- [ ] **Operable:** `arazzo validate` exits non-zero only on errors (not warnings); warnings under distinct section; rollback by removing flags (`RuleSet::default($engine)` unchanged).
- [ ] **Reliable:** Deterministic validation result with policy; idempotent; invalid flag format errors clearly.
- [ ] **Safe:** CLI-only wiring; validation logic unchanged; no state mutation.
- [ ] **Secure:** No authorization/ACL impact; input validation unaffected.
- [ ] **Suitable:** Closes gap where `ValidateCommand` calls `RuleSet::default($engine)` with no policy; every rule fires at hard-coded `Error`.
- [ ] **Usable:** Familiar flag syntax; optional config file; clear error on invalid flag format.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--severity` flag demotes error to warning**
- **Given** `arazzo validate doc.yaml --severity requestBody-replacements-unique:warn`; `ValidatorPolicy` (draft 27 core) is wired into `ValidateCommand`; `--severity` is repeatable and accepts `code:level` format.
- **When** `php bin/console arazzo validate doc.yaml --severity requestBody-replacements-unique:warn` executes.
- **Then** the rule's finding is a warning, not an error; `ErrorCollector` records it under `warnings` (not `errors`); exit code is `0` if no errors; `ValidationResult::isValid()` returns `true`.

**Scenario 2: `--disable-rule` flag disables a rule**
- **Given** `arazzo validate doc.yaml --disable-rule expr.forward_step_ref`; `ValidatorPolicy` disabled list contains `expr.forward_step_ref`.
- **When** `ValidateCommand` runs validation.
- **Then** the rule is not active in `RuleSet::activeRules()`; no findings from `expr.forward_step_ref` are reported; `ValidationResult::isValid()` is unaffected by that rule.

**Scenario 3: `--strict`/`--no-strict` toggle**
- **Given** `--no-strict` is passed; `ValidatorPolicy` strict mode is disabled.
- **When** `ValidateCommand` runs validation.
- **Then** strict mode is disabled per policy; rules that require strict mode behave accordingly; `RuleSet::isStrict()` returns `false`.

**Scenario 4: Config file read for policy**
- **Given** `arazzo.validate.php` or `arazzo.json` with a policy section (e.g., `{'severity': {'requestBody-replacements-unique': 'warn'}, 'disabled': ['expr.forward_step_ref']}`).
- **When** `ValidateCommand` reads the config file and builds `ValidatorPolicy`.
- **Then** the policy is applied from the config file; severity overrides and disabled rules are configured; CLI flags can override config file settings.

**Scenario 5: Exit non-zero only on errors**
- **Given** validation produces warnings only (no errors); `--strict` is not set or warnings don't affect exit code.
- **When** `arazzo validate` completes.
- **Then** exit code is `0`; warnings are printed under a distinct section in output; `ErrorCollector::errors()` is empty; `ValidationResult::isValid()` returns `true`; CI scripts that check exit code see success.
- **When`validation runs
- **Then`policy applied from config file

**Scenario 5: Exit non-zero only on errors**
- **Given** warnings only (no errors)
- **When`arazzo validate completes
- **Then`exit code 0; warnings under distinct section

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `ValidateCommand` gains `--severity <code>:<level>` (repeatable), `--disable-rule <code>` (repeatable), `--strict`/`--no-strict` flags
- [ ] Optional config file (`arazzo.validate.php`/`arazzo.json` section) read for policy
- [ ] Policy passed to `RuleSet::default()`; disabled rules not active; severity overrides demote errors to warnings
- [ ] `arazzo validate` exits non-zero only when errors (not warnings) present
- [ ] Warnings printed under distinct section in output
- [ ] `ValidateCommandTest` cases: severity demotion, rule disable, strict toggle, invalid flag format
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core policy mechanism (draft 27) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/ValidateCommand.php:40-42` — `RuleSet::default($engine)` with no policy.
- `packages/cli/src/Console/Command/ValidateCommand.php:43-69` — prints errors + warnings; returns `FAILURE` on invalid.
- `packages/document/src/Validator/RuleSet.php:88-100` — `default()` accepts `$disabled`/`$strict` (draft 27 extends with policy object).

<details><summary><code>ValidateCommand.php:40-42</code></summary>

```php
        $engine = new ExpressionEngine();
        $result = (new Validator(RuleSet::default($engine)))->validate($document);
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; no public contract break.
- **Blast radius:** `packages/cli` `validate` output and exit code; CI scripts parsing output.
- **Severity:** medium — no behaviour change by default; opt-in policy changes results.
- **Rollback:** remove flags; `RuleSet::default($engine)` unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim                                                                  | Tier | Evidence                          | Verification |
| ---------------------------------------------------------------------- | ---- | --------------------------------- | ------------ |
| `ValidateCommand` passes no policy to `RuleSet::default()`             | T1   | `ValidateCommand.php:40-42`       | direct read  |
| No `--severity`/`--disable-rule`/`--strict` flags on `ValidateCommand` | T1   | `ValidateCommand.php` configure() | direct read  |
| Draft 27 `ValidatorPolicy` is the core dependency                      | T2   | draft 27 (core policy mechanism)  | cross-ref    |
