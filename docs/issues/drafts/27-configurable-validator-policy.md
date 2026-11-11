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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RuleSet::default()` remains the composition root; add a policy object (or named arguments) accepting per-rule severity overrides and a disabled list.
- [ ] Rules can report a finding as `warning()` instead of `error()` based on the configured policy for that rule's code.
- [ ] `ValidateCommand` reads a policy (config file `arazzo.validate.php`/`arazzo.json` section, and/or CLI flags `--severity <code>:<level>`, `--disable <code>`, `--strict`/`--no-strict`).
- [ ] `arazzo validate` exits non-zero only when errors (not warnings) are present.
- [ ] `ValidationResult` continues to expose `errors` and `warnings` separately; output lists warnings under a distinct section.
- [ ] `RuleSetTest` gains cases: disabled rule is not active; strict flag toggles; severity override demotes an error to a warning.
- [ ] `ValidatorTest`/`PreflightValidatorTest` unchanged in behaviour when no policy is supplied (backward compatible).
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** `RuleSet::default()` signature unchanged (additive policy object optional); `RuleSet`/`ErrorCollector` are `@internal`, so no public contract break.
- **Blast radius:** `packages/cli` `ValidateCommand` output and exit code; CI scripts parsing `arazzo validate` output.
- **Severity:** medium — no behaviour change by default; opt-in policy changes results.
- **Rollback:** remove policy wiring; `RuleSet::default($engine)` call unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RuleSet::default()` accepts `$disabled`/`$strict` but CLI passes none | T1 | `RuleSet.php:88-100`; `ValidateCommand.php:40-42` | direct read |
| `Severity` has Error/Warning; `ErrorCollector::warning()` exists | T1 | `Severity.php:10-14`; `ErrorCollector.php:21-29` | direct read |
| Rules call `$errors->error()` directly; no configurable severity per rule | T1 | `grep -rn "->warning(" packages/document/src/Validator/Rules` → 5 rules use warning, all hard-coded | executed grep |
| `ValidateCommand` returns FAILURE on any error; warnings printed but non-fatal | T1 | `ValidateCommand.php:43-69` | direct read |
| `RuleSet::activeRules()` filters `$disabled`; `isStrict()` exposed | T1 | `RuleSet.php:162-182` | direct read |
| No CLI flag / config for validator policy | T1 | `RunCommand`/`ValidateCommand` options; no config file read | direct read |
