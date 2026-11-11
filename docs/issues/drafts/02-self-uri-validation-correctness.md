# Draft issue 02 — Fix `$self` URI validation

- **Suggested title:** `[Bug] '$self' validation rejects valid relative URIs and accepts fragments on 1.0`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` — `Validator/Rules/SelfUriSyntaxRule.php`.
- **Arazzo spec version:** 1.0.0, 1.0.1, 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** high — the rule both rejects valid documents and accepts invalid ones.

### 📝 2. Problem Statement & Intent

The spec defines `$self` as a URI-reference (RFC 3986 §4.1), explicitly allowing relative references, and states that the `$self` URI **MUST NOT contain a fragment identifier**.

`SelfUriSyntaxRule` does three things wrong:

1. It returns immediately for Arazzo 1.0.x, so `$self` is never validated there.
2. It strips the fragment with `strtok($doc->self, '#')` before validating, so a `$self` with a fragment is accepted instead of rejected.
3. It validates with `FILTER_VALIDATE_URL`, which accepts only absolute URLs, so a valid relative `$self` such as `./doc.arazzo` is rejected on 1.1.

The 1.1 official schema happens to reject fragments through a `pattern` (`^[^#]*$`), but the 1.0 schema does not, and the semantic rule gives the wrong answer in both directions.

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`ArazzoDocument::$self`, `SpecVersion`).
- **Validator:** `SelfUriSyntaxRule`.
- **Parser:** stores `$self` as an optional string (`Parser.php:74`).
- **Downstream:** source resolution and identity-based referencing (draft 07) depend on a correct `$self`.

### 📄 4. Structural Artifacts & Raw Inputs

Accepted today but invalid:

```yaml
arazzo: 1.0.0
info: { title: T, version: "1.0.0" }
$self: https://example.com/doc.arazzo#frag
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
```

Rejected today but valid:

```yaml
arazzo: 1.1.0
info: { title: T, version: "1.0.0" }
$self: ./doc.arazzo
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
    type: openapi
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
```

### 🌀 5. Runtime Execution State Context

- Validation only.
- Observed for `$self: https://example.com/doc.arazzo#x` on `1.0.0`: **zero errors**.
- Observed for `$self: ./doc.arazzo` on `1.1.0`: `[document.self_uri_syntax] Invalid $self URI: './doc.arazzo'`.
- Observed for an absolute 1.1 `$self` without a fragment: **zero errors** (correct).

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] Relative URI-references are accepted on 1.0.x and 1.1.x (e.g. `./doc.arazzo`, `../base/doc.arazzo`, `doc.arazzo`).
- [ ] Absolute URIs are accepted on 1.0.x and 1.1.x.
- [ ] A `$self` containing `#` is rejected on every supported version, with a pointer at `/$self`.
- [ ] An empty or whitespace-only `$self` is rejected.
- [ ] Validation uses RFC 3986 URI-reference rules rather than `FILTER_VALIDATE_URL` (e.g. `parse_url()` plus a relative-reference check, or a dedicated URI parser).
- [ ] Pest tests cover the six combinations of {1.0, 1.1} × {absolute, relative, fragment}.
- [ ] The existing error code `document.self_uri_syntax` is retained for compatibility.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/SelfUriSyntaxRule.php:18-33` — the 1.0 early return, fragment stripping, and `FILTER_VALIDATE_URL` check.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/SelfUriSyntaxRule.php#L18-L33"><code>SelfUriSyntaxRule.php:18-33</code></a> @ <code>ea8c55a</code></summary>

```php
public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
{
    if ($doc->specVersion === SpecVersion::V1_0 || $doc->self === null) {
        return;
    }

    // Strip fragment before URL validation (filter_var rejects fragments in some cases).
    $withoutFragment = strtok($doc->self, '#') ?: '';
    if (filter_var($withoutFragment, FILTER_VALIDATE_URL) === false) {
        $errors->error(
            $this->code(),
            "Invalid \$self URI: '{$doc->self}'",
            '/$self',
        );
    }
}
```

</details>

- `packages/document/src/Parser/Parser.php:74` — `$self` is parsed as an optional string.
- Spec: Arazzo 1.1.0 §5.8.1 (Arazzo Object `$self`) and §5.2 “Relative References in Arazzo Description URIs”.

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; validation correctness only.
- **Blast radius:** `packages/document`. Relative `$self` documents become valid; fragment documents become invalid as the spec requires.
- **Severity:** medium — blocks valid federated documents and hides invalid ones.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Relative `$self` is rejected on 1.1 | T1 | Executed validator run on the artifact | `document.self_uri_syntax` error |
| Fragment `$self` is accepted on 1.0 | T1 | Executed validator run on the artifact | zero errors |
| The rule strips the fragment before checking | T1 | `SelfUriSyntaxRule.php:25` | direct read |
| The rule skips 1.0 entirely | T1 | `SelfUriSyntaxRule.php:20` | direct read |
| The spec allows relative URI-references | T2 | Arazzo 1.1.0 §5.2 | spec text |
| The spec forbids a fragment in `$self` | T2 | Arazzo 1.1.0 §5.8.1 | spec text |
