# Draft issue 27 — Configurable validator rule policy (severity and enable/disable)

- **Suggested title:** `[Bug] Validator severity and rule enable/disable cannot be configured from the CLI`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` `RuleSet`, `ErrorCollector`, `Severity` enum, `Validator`; `packages/cli` `ValidateCommand`.
- **Arazzo spec version:** independent of spec version; affects every `arazzo validate` invocation.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — operators cannot downgrade noisy rules to warnings or disable rules for incremental adoption.

### 📝 2. Problem Statement & Intent

php-arazzo's validator has the machinery for a configurable policy, but none of it is exposed through the CLI:

- `RuleSet::default()` accepts `$disabled` (`list<string>`) and `$strict` (`bool`) (`RuleSet.php:88-100`), and `RuleSet::activeRules()` filters out disabled rules by code.
- `Severity` enum has `Error` and `Warning` cases (`Severity.php:10-14`); `ErrorCollector` records errors and warnings separately (`ErrorCollector.php:21-29`).
- But `ValidateCommand` calls `RuleSet::default($engine)` with no disabled list and no strict flag (`ValidateCommand.php:40-42`), so every registered rule runs at hard-coded severity.
- Every rule calls `$errors->error(...)` directly; there is no mechanism to report a rule's finding as a warning based on configuration.
- No CLI flag exists for `--severity`, `--disable-rule`, `--enable-rule`, or `--strict`.

Expected: `arazzo validate` accepts a policy (via config file and/or CLI flags) that can set per-rule severity and disable rules; rules can report findings as warnings when policy says so; the exit code reflects errors only (warnings do not fail).

### 📦 3. System Dependency Matrix

- **Upstream:** `RuleSet` constructor/seam already accepts `$disabled`/`$strict`; `Severity` enum and `ErrorCollector::warning()` exist.
- **Downstream:** `ValidateCommand` output format and exit code; `ValidationResult::isValid()` (errors only); CI gates that treat any output as failure.
- **Internal seams used:** `RuleSet::activeRules()`; `ErrorCollector::error()` / `warning()`; `ValidationResult` errors/warnings separation.
- **Packages touched:** `packages/document` (primary: `RuleSet`, `ErrorCollector`, rules, `Validator`), `packages/cli` (`ValidateCommand`).
- **Scope boundary:** this draft covers policy wiring and severity. It does not cover adding new rules (drafts 28, 34, 35 cover specific rule gaps).

### 📄 4. Structural Artifacts & Raw Inputs

Minimal reproduction:

```bash
# A document with one step whose successCriteria uses a forward step reference.
# ExpressionUnresolvedStepRefRule reports it as a warning today, but there is
# no way to downgrade it to a warning or disable it.
arazzo validate doc.arazzo.yaml
```

Observed: every rule fires at `Error` severity; `arazzo validate` returns non-zero if any rule fires, with no way to suppress or demote a rule.

Expected: a policy mechanism (config + flags) to e.g. `arazzo validate --disable-rule expr.forward_step_ref doc.arazzo.yaml` or set `requestBody-replacements-unique: warn`.

### 🌀 5. Runtime Execution State Context

- Command: `arazzo validate <file>`.
- Rule execution: `Validator::validate()` iterates `RuleSet::activeRules()` and calls `$rule->check($doc, $symbols, $collector)`.
- Collector: errors and warnings are collected separately but `ValidateCommand` prints both and returns `FAILURE` whenever there are errors — warnings do not affect the exit code but there is no way to produce them from configurable rules.
- No configuration file is read; no CLI options for policy exist.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose the validator's existing policy machinery (severity, enable/disable, strict) through the CLI so operators can demote noisy rules to warnings or disable rules for incremental adoption.

**Architectural Boundary:** Bounded context = CLI validation + validator policy. Packages: `packages/document` (`RuleSet`, `ErrorCollector`, `Severity`, rules), `packages/cli` (`ValidateCommand`). No new rules — this is policy wiring only.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Policy evaluation is O(rules) per validation; no added overhead when no policy supplied (backward compatible).
- [ ] **Flexible:** `RuleSet::default()` accepts policy object/named arguments for per-rule severity overrides and disabled list; config file and/or CLI flags.
- [ ] **Maintainable:** Rules report `warning()` instead of `error()` based on configured policy; `ErrorCollector` records errors/warnings separately; `ValidationResult` exposes both.
- [ ] **Operable:** `arazzo validate` exits non-zero only on errors (not warnings); warnings printed under distinct section; rollback by removing policy wiring.
- [ ] **Reliable:** Deterministic validation result with policy; idempotent; `ValidatorTest`/`PreflightValidatorTest` unchanged when no policy supplied.
- [ ] **Safe:** Validation is read-only; policy only affects error/warning classification; no state mutation.
- [ ] **Secure:** No authorization/ACL impact; input validation unaffected.
- [ ] **Suitable:** Closes the gap where every rule fires at hard-coded `Error` severity with no way to suppress or demote.
- [ ] **Usable:** CLI flags `--severity <code>:<level>`, `--disable-rule <code>`, `--enable-rule <code>`, `--strict`/`--no-strict`; optional config file (`arazzo.validate.php`/`arazzo.json`).

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `RuleSet::default()` accepts policy object**
- **Given** a `ValidatorPolicy` object with per-rule severity overrides (e.g., `{ "requestBody-replacements-unique": "warning" }`) and a disabled list (e.g., `["expr.forward_step_ref"]`); `RuleSet::default()` accepts the policy object as a named argument; `ValidateCommand` passes the policy to `RuleSet::default($engine, policy: $policy)`.
- **When** `RuleSet::default($engine, policy: $policy)` is called.
- **Then** disabled rules (`expr.forward_step_ref`) are not active in `RuleSet::activeRules()`; severity overrides are applied (`requestBody-replacements-unique` fires at warning severity, not error); `Validator::validate()` uses the active rules with configured severities.

**Scenario 2: Rules report `warning()` instead of `error()` based on policy**
- **Given** a rule (`RequestBodyReplacementsUniqueRule`) configured with `severity: warn` in the policy; the rule fires during validation (duplicate replacement targets found).
- **When** the rule's `check()` method is called and it calls `$errors->warning()` instead of `$errors->error()`.
- **Then** the finding is recorded as a warning, not an error; `ErrorCollector` records it under `warnings` (not `errors`); `ValidationResult::isValid()` returns `true` if no errors; warnings are printed under a distinct section in output.

**Scenario 3: `ValidateCommand` reads policy from flags/config**
- **Given** `arazzo validate doc.arazzo.yaml --severity requestBody-replacements-unique:warn --disable-rule expr.forward_step_ref --strict` or a config file (`arazzo.validate.php` or `arazzo.json` section) with policy settings.
- **When** `ValidateCommand` parses CLI flags and/or reads the config file; builds `ValidatorPolicy` from the combined configuration.
- **Then** the policy is applied to `RuleSet::default()`; disabled rules are not active; severity overrides demote errors to warnings; strict flag toggles strict mode.

**Scenario 4: Exit non-zero only on errors**
- **Given** validation produces warnings only (no errors); `--strict` is not set or warnings don't affect exit code.
- **When** `arazzo validate` completes.
- **Then** exit code is `0` (success); warnings are printed under a distinct section in output; `ValidationResult::isValid()` returns `true` (no errors); `ErrorCollector::errors()` is empty.

**Scenario 5: Backward compatible when no policy supplied**
- **Given** `arazzo validate doc.arazzo.yaml` with no policy flags/config; `RuleSet::default($engine)` is called without a policy object.
- **When** validation runs.
- **Then** behavior is unchanged from today (all rules at `Error` severity); every registered rule fires at hard-coded `Error`; `ValidationResult::isValid()` returns `false` if any rule fires; backward compatible with existing CI scripts.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RuleSet::default()` remains composition root; add policy object/named arguments for per-rule severity overrides and disabled list
- [ ] Rules can report `warning()` instead of `error()` based on configured policy for rule code
- [ ] `ValidateCommand` reads policy from config file (`arazzo.validate.php`/`arazzo.json`) and/or CLI flags
- [ ] `arazzo validate` exits non-zero only when errors (not warnings) present
- [ ] `ValidationResult` exposes `errors` and `warnings` separately; warnings under distinct section
- [ ] `RuleSetTest` cases: disabled rule not active; strict flag toggles; severity override demotes error to warning
- [ ] `ValidatorTest`/`PreflightValidatorTest` unchanged when no policy supplied
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI flags: `--severity <code>:<level>`, `--disable-rule <code>`, `--enable-rule <code>`, `--strict`/`--no-strict`

### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Validator/RuleSet.php:88-100` — `default()` accepts `$disabled`/`$strict` but `ValidateCommand` never passes them.
- `packages/document/src/Validator/RuleSet.php:143-157` — rules list; `ReceiveStepSuccessCriteriaRule($strict)` is the only rule consuming `$strict`.
- `packages/document/src/Validator/RuleSet.php:162-182` — `activeRules()` filters by `$disabled`; `isStrict()` exposed but unused by CLI.
- `packages/document/src/Validator/Enum/Severity.php:10-14` — `Error` / `Warning` cases.
- `packages/document/src/Validator/ErrorCollector.php:21-29` — `error()` / `warning()` record separately.
- `packages/document/src/Validator/Validator.php` — iterates `activeRules()` into a single `ErrorCollector`.
- `packages/cli/src/Console/Command/ValidateCommand.php:40-42` — `RuleSet::default($engine)` with no policy; prints errors + warnings; returns `FAILURE` on invalid.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/RuleSet.php#L88-L100"><code>RuleSet.php:88-100</code></a> @ <code>ea8c55a</code></summary>

```php
    public static function default(
        ExpressionEngineInterface $engine,
        ?SelectorPluginRegistryInterface $selectorRegistry = null,
        array $disabled = [],
        bool $strict = true,
    ): self {
        $selectorRegistry ??= $engine->selectorPlugins();
        return new self([...], $disabled, $strict);
    }
```

</details>

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Command/ValidateCommand.php#L40-L42"><code>ValidateCommand.php:40-42</code></a> @ <code>ea8c55a</code></summary>

```php
        $engine = new ExpressionEngine();
        $result = (new Validator(RuleSet::default($engine)))->validate($document);
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** `RuleSet::default()` signature unchanged (additive policy object optional); `RuleSet`/`ErrorCollector` are `@internal`, so no public contract break.
- **Blast radius:** `packages/cli` `ValidateCommand` output and exit code; CI scripts parsing `arazzo validate` output.
- **Severity:** medium — no behaviour change by default; opt-in policy changes results.
- **Rollback:** remove policy wiring; `RuleSet::default($engine)` call unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RuleSet::default()` accepts `$disabled`/`$strict` but CLI passes none | T1 | `RuleSet.php:88-100`; `ValidateCommand.php:40-42` | direct read |
| `Severity` has Error/Warning; `ErrorCollector::warning()` exists | T1 | `Severity.php:10-14`; `ErrorCollector.php:21-29` | direct read |
| Rules call `$errors->error()` directly; no configurable severity per rule | T1 | `grep -rn "->warning(" packages/document/src/Validator/Rules` → 5 rules use warning, all hard-coded | executed grep |
| `ValidateCommand` returns FAILURE on any error; warnings printed but non-fatal | T1 | `ValidateCommand.php:43-69` | direct read |
| `RuleSet::activeRules()` filters `$disabled`; `isStrict()` exposed | T1 | `RuleSet.php:162-182` | direct read |
| No CLI flag / config for validator policy | T1 | `RunCommand`/`ValidateCommand` options; no config file read | direct read |
