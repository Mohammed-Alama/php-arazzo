# Draft issue 36 — Validate that referenced OpenAPI operations actually exist at lint time

- **Suggested title:** `[Bug] `arazzo validate` passes when a step references a non-existent OpenAPI operation`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` validator rules; `packages/sources` OpenAPI operation resolver and preflight validator.
- **Arazzo spec version:** 1.0.x and 1.1.x (Step Object `operationId` / `operationPath` §5.8.5).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — a workflow referencing a missing operation passes `arazzo validate` but fails at runtime with an obscure resolution error.

### 📝 2. Problem Statement & Intent

A step's `operationId` (or `operationPath`) must reference an operation that actually exists in one of the document's `sourceDescriptions`. An implementation should load each source description, index operations by `operationId` and `<METHOD> <path>`, and reject any step whose reference does not resolve to a real operation. php-arazzo does not:

- `StepOperationIdSourceScopedRule` only checks that `operationId` is *qualified* (prefixed with a source name or `$sourceDescriptions.<name>.`) when multiple sources exist — it never checks whether the named operation exists (`StepOperationIdSourceScopedRule.php:16-43`).
- `StepOperationPathSyntaxRule` checks the *syntax* of `operationPath` but not whether the pointer addresses a real operation.
- Operation *existence* is only checked at execution preflight time by `PreflightValidator::checkStep()` (`PreflightValidator.php:48-111`), which runs after `validate` and only when the runner is invoked.
- A probe confirmed the defect: a document with `operationId: $sourceDescriptions.api.nonexistentOp` validates `valid=true` (`probe-op-exists.php`).

Expected: `arazzo validate` rejects references to operations that do not exist in any loaded source description, with a clear error pointing at the step's `operationId`/`operationPath`.

### 📦 3. System Dependency Matrix

- **Upstream:** Arazzo spec §5.8.5 Step Object (`operationId`, `operationPath`, `channelPath`, `workflowId`); OpenAPI source descriptions must be loadable and indexed by operation.
- **Downstream:** `arazzo validate` exit code and error output; any CI gate that treats `validate` as a pre-merge quality gate.
- **Internal seams used:** `SymbolTable::sourceDescriptions` (declared source names); `OpenApiOperationResolver` / `OpenApiOperationResolverInterface` (operation resolution); `PreflightValidator` (existing operation-existence logic that can be reused).
- **Packages touched:** `packages/document` (new lint rule + registration), `packages/sources` (read-only: resolution logic already exists).
- **Scope boundary:** operation existence for `openapi`-typed sources only. Cross-document `$sourceDescriptions.<name>.<workflowId>` references are covered by #83. AsyncAPI channel existence is out of scope for v1.

### 📄 4. Structural Artifacts & Raw Inputs

Minimal document that reproduces the defect:

```yaml
arazzo: 1.1.0
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: api
    url: ./openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.nonexistentOp
```

`arazzo validate repro.arazzo.yaml` → `valid=true` (no error). Expected: an error such as "operation 'nonexistentOp' not found in source description 'api'" at `/workflows/0/steps/0/operationId`.

### 🌀 5. Runtime Execution State Context

- Command: `arazzo validate <file>`.
- Validation phase: `Validator::validate()` → `RuleSet::activeRules()` → `StepOperationIdSourceScopedRule::check()` (qualification check only; no existence check).
- The operation resolver (`OpenApiOperationResolver`) is available in the sources package and already indexes operations; the rule just needs to consult it.
- Execution phase (not reached because no error): `PreflightValidator` would catch the missing operation, but only at run time.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A new validator rule (e.g. `StepOperationExistsRule`) checks that each step's `operationId` / `operationPath` resolves to a real operation in a loaded source description.
- [ ] The rule reports an error naming the missing operation and the source description, with a pointer to the step's `operationId`/`operationPath`.
- [ ] Qualified references (`$sourceDescriptions.<name>.<op>`) are resolved against the named source; bare references require exactly one source (existing behavior preserved).
- [ ] `operationPath` pointers (`{$sourceDescriptions.<name>.url}#/paths/...`) are checked for operation existence where the source is loadable.
- [ ] The rule is registered in `RuleSet::default()` and enabled by default.
- [ ] `ValidatorTest` gains cases: nonexistent operation → invalid; existent operation → valid; multiple sources with unqualified op → invalid (existing); qualified op against wrong source → invalid.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/StepOperationIdSourceScopedRule.php:16-43` — checks operationId qualification only; no existence check.
- `packages/document/src/Validator/Rules/StepOperationPathSyntaxRule.php` — checks operationPath syntax only.
- `packages/sources/src/Validator/PreflightValidator.php:48-111` — validates operation existence at execution preflight (reusable logic, wrong phase).
- `packages/document/src/Validator/RuleSet.php:143-157` — rule registration list; new rule goes here.
- `packages/sources/src/Normalizer/OpenApiOperationResolver.php` — operation resolution seam to consult.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/StepOperationIdSourceScopedRule.php#L16-L43"><code>StepOperationIdSourceScopedRule.php:16-43</code></a> @ <code>ea8c55a</code></summary>

```php
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationId === null) {
                    continue;
                }
                $isQualified = false;
                foreach (array_keys($symbols->sourceDescriptions) as $srcName) {
                    if (
                        str_starts_with($s->target->operationId, $srcName.'.')
                        || str_starts_with($s->target->operationId, '$sourceDescriptions.'.$srcName.'.')
                    ) {
                        $isQualified = true;
                        break;
                    }
                }
                if (!$isQualified) {
                    if (count($doc->sourceDescriptions) !== 1) {
                        $errors->error(
                            $this->code(),
                            "Step '{$s->stepId}' uses unqualified operationId '{$s->target->operationId}' but the document does not declare exactly one sourceDescription.",
```

</details>

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/sources/src/Validator/PreflightValidator.php#L48-L111"><code>PreflightValidator.php:48-111</code></a> @ <code>ea8c55a</code></summary>

```php
    public function validate(ArazzoDocument $document): ValidationResult
    {
        $errors = new ErrorCollector();
        foreach ($document->workflows as $workflow) {
            foreach ($workflow->steps as $step) {
                $this->checkStep($document, $workflow, $step, $errors);
            }
        }
        return new ValidationResult($document, $errors->errors(), $errors->warnings());
    }
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none (validator is `@internal`); new validation error may cause previously-passing documents to fail `arazzo validate`.
- **Blast radius:** documents referencing mistyped or removed operationIds (authoring errors); no runtime behaviour change for valid documents.
- **Severity:** medium — catches broken operation references at validation time instead of run time.
- **Rollback:** remove the rule; scoping check and preflight validation unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `StepOperationIdSourceScopedRule` checks qualification only, not existence | T1 | `StepOperationIdSourceScopedRule.php:16-43` | direct read |
| `PreflightValidator` checks operation existence but only at preflight/execution | T1 | `PreflightValidator.php:48-111` | direct read |
| Non-existent operationId passes `arazzo validate` | T1 | probe-op-exists.php → `valid=true` | executed probe |
| Operation resolver indexes operations by operationId | T1 | `OpenApiOperationResolver.php` | direct read |
| Rule registration point is `RuleSet::default()` | T1 | `RuleSet.php:143-157` | direct read |
