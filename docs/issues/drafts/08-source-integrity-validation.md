# Draft issue 08 — Validate source reachability, parseability and declared type

- **Suggested title:** `[Feature] 'arazzo validate --resolve-sources' checks each source URL and declared type`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #23 Named Source Resolution + OpenAPI, or a new sources epic)
- **Packages:** `sources`, `document`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/sources` (resolution + normalizers), `packages/document` (`SourceTypeMatchesRule`), `packages/cli` (`validate`).
- **Arazzo spec version:** 1.0.x and 1.1.x.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`.
- **Priority:** medium — closes the gap between “document is syntactically valid” and “document can actually execute”.

### 📝 2. Problem Statement & Intent

`arazzo validate` validates the Arazzo document in isolation. It never fetches `sourceDescriptions[].url`, so a document whose sources are missing, unreadable, unparseable, or of the wrong type still validates green. The reserved rule `SourceTypeMatchesRule` exists specifically for this and is an empty no-op today:

> `source.type_matches` — “future non-parser-time checks (e.g. 'type: arazzo yet url points to an OpenAPI file') can land here.”

Execution preflight (`PreflightValidator`) does resolve sources, but only at run time, after a caller has already accepted the document.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Add an opt-in mode to `arazzo validate` (suggested: `--resolve-sources`, with a strict alias) that, for each source description:
  1. fetches the URL (subject to the configured fetcher policy);
  2. parses it as YAML/JSON;
  3. checks that the content matches the declared `type` (`openapi`, `asyncapi`, `arazzo`, or the project’s extended types);
  4. emits stable diagnostics with a pointer at `/sourceDescriptions/{i}/url` or `/type`.
- Make `SourceTypeMatchesRule` real for the resolve mode: report unreadable, unparseable and type-mismatch sources.
- When `type` is omitted (draft 06), infer it and report the inferred type; a failed inference is a diagnostic.
- Keep the default offline behaviour unchanged and deterministic.

**Non-goals**

- Executing any workflow or operation.
- Bundling sources into one document (#84).
- Network access in the default `validate` path.
- Deep semantic validation of the source document (that is the source normalizer’s job).

**Prior art in this repository**

- `PreflightValidator::validate()` already resolves operations, selectors and inputs before executing.
- `DefaultSourceResolver` already fetches and decodes sources.
- `SourceNormalizerRegistry` already dispatches by `SourceType`.
- `SourceTypeMatchesRule` reserves the intended error code.

**Proposed diagnostics**

| Code | Meaning |
|---|---|
| `source.unreadable` | Fetch failed, non-2xx, missing file, or disallowed by policy |
| `source.unparseable` | Fetched content is not valid YAML/JSON or not an object |
| `source.type_matches` | Content does not match the declared/inferred type |
| `source.type_inference_failed` | Type omitted and content is not recognisable |

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `arazzo validate --resolve-sources <file>` fetches and parses every source description.
- [ ] Missing/unreadable/unparseable sources produce the diagnostics above with exact pointers.
- [ ] A declared `type: arazzo` pointing at an OpenAPI document produces `source.type_matches`.
- [ ] A correct source produces no new diagnostics.
- [ ] Type inference runs only when `type` is omitted; an explicit type always wins.
- [ ] The default `arazzo validate <file>` remains offline and produces the same result as today.
- [ ] Fetching honours the fetcher hardening policy (draft 09): timeouts, size limits, redirect limits, local confinement.
- [ ] Results are cacheable within one validate invocation (each URL fetched once).
- [ ] Tests use local fixtures and a fake PSR-18 client; no network in CI.
- [ ] Docs describe the new flag, diagnostics and the offline default.

### 🧬 7. Source Grounding & Code Location

- `packages/document/src/Validator/Rules/SourceTypeMatchesRule.php:19-30` — reserved code, no-op implementation.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Validator/Rules/SourceTypeMatchesRule.php#L19-L30"><code>SourceTypeMatchesRule.php:19-30</code></a> @ <code>ea8c55a</code></summary>

```php
final class SourceTypeMatchesRule implements Rule
{
    public function code(): string
    {
        return 'source.type_matches';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1 — the enum guarantees correctness.
    }
}
```

</details>

- `packages/sources/src/Resolver/DefaultSourceResolver.php:58-96` — reusable fetch/decode path.
- `packages/sources/src/Validator/PreflightValidator.php:48-118` — existing source/operation resolution used by execution.
- `packages/cli/src/Console/Command/ValidateCommand.php` — the command to extend.
- Spec: Arazzo 1.1.0 §5.8.3.1 (Source Description Object) and §5.2 (reference resolution).

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI flag; `SourceTypeMatchesRule` changes from no-op to active only in resolve mode.
- **Blast radius:** `packages/sources`, `packages/cli`, `packages/document`; no runtime execution change.
- **Severity:** medium — prevents late failures and improves CI signal.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `validate` does not fetch sources | T1 | `ValidateCommand` reads only the document; no resolver call | direct read |
| `SourceTypeMatchesRule` is a no-op | T1 | `SourceTypeMatchesRule.php:26-29` | direct read |
| Preflight resolves sources only at run time | T1 | `PreflightValidator.php:48-118` | direct read |
| The resolver can fetch/decode sources | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| Type inference is feasible after draft 06 | T3 | Design dependency | proposed |
