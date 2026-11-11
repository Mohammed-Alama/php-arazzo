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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `ValidateCommand` gains `--severity <code>:<level>` (repeatable), `--disable-rule <code>` (repeatable), `--strict`/`--no-strict` flags.
- [ ] Optional config file (`arazzo.validate.php` / `arazzo.json` section) read for policy.
- [ ] Policy passed to `RuleSet::default()`; disabled rules not active; severity overrides demote errors to warnings.
- [ ] `arazzo validate` exits non-zero only when errors (not warnings) are present.
- [ ] Warnings printed under a distinct section in output.
- [ ] `ValidateCommandTest` gains cases: severity demotion, rule disable, strict toggle, invalid flag format.
- [ ] PHPStan max and Pint pass.
- [ ] **Core policy mechanism** (draft 27) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Console/Command/ValidateCommand.php:40-42` — `RuleSet::default($engine)` with no policy.
- `packages/cli/src/Console/Command/ValidateCommand.php:43-69` — prints errors + warnings; returns `FAILURE` on invalid.
- `packages/document/src/Validator/RuleSet.php:88-100` — `default()` accepts `$disabled`/`$strict` (draft 27 extends with policy object).

<details><summary><code>ValidateCommand.php:40-42</code></summary>

```php
        $engine = new ExpressionEngine();
        $result = (new Validator(RuleSet::default($engine)))->validate($document);
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; no public contract break.
- **Blast radius:** `packages/cli` `validate` output and exit code; CI scripts parsing output.
- **Severity:** medium — no behaviour change by default; opt-in policy changes results.
- **Rollback:** remove flags; `RuleSet::default($engine)` unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim                                                                  | Tier | Evidence                          | Verification |
| ---------------------------------------------------------------------- | ---- | --------------------------------- | ------------ |
| `ValidateCommand` passes no policy to `RuleSet::default()`             | T1   | `ValidateCommand.php:40-42`       | direct read  |
| No `--severity`/`--disable-rule`/`--strict` flags on `ValidateCommand` | T1   | `ValidateCommand.php` configure() | direct read  |
| Draft 27 `ValidatorPolicy` is the core dependency                      | T2   | draft 27 (core policy mechanism)  | cross-ref    |
