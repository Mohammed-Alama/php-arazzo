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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Add an opt-in `arazzo validate --resolve-sources` mode that fetches, parses, and type-checks each source URL, closing the gap between "syntactically valid document" and "document that can actually execute."

**Architectural Boundary:** Bounded context = document validation + source resolution. Packages: `packages/sources` (resolution + normalizers), `packages/document` (`SourceTypeMatchesRule`), `packages/cli` (`ValidateCommand`). Default offline behaviour unchanged; no workflow execution.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Each URL fetched once per invocation (cacheable within one run); fetch subject to fetcher hardening policy (draft 09); no impact on default offline `validate`.
- [ ] **Flexible:** Opt-in flag (`--resolve-sources`); strict alias; `SourceTypeMatchesRule` active only in resolve mode; inference (draft 06) used when `type` omitted.
- [ ] **Maintainable:** Stable diagnostic codes (`source.unreadable`, `source.unparseable`, `source.type_matches`, `source.type_inference_failed`) with exact pointers; logged via `ErrorCollector`.
- [ ] **Operable:** CI can enable resolve mode as an additional gate; default path unchanged and deterministic; rollback is removing the flag.
- [ ] **Reliable:** Fetch failures produce typed diagnostics, not crashes; results cacheable within one invocation; no network in default path.
- [ ] **Safe:** No workflow execution; no side effects beyond reading sources; fetch policy confinement (draft 09) applies.
- [ ] **Secure:** Honors fetcher hardening (timeouts, size limits, redirect limits, local confinement); no credentials in diagnostics.
- [ ] **Suitable:** Makes `arazzo validate` a stronger pre-merge gate; catches missing/unreadable/unparseable/type-mismatch sources before execution.
- [ ] **Usable:** Clear diagnostics with pointers at `/sourceDescriptions/{i}/url` or `/type`; default behaviour unchanged for existing users.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--resolve-sources` fetches and parses every source description**
- **Given** an Arazzo document (`doc.arazzo`) with two source descriptions: `{ name: "api", url: "./openapi.yaml", type: "openapi" }` and `{ name: "auth", url: "./auth.yaml", type: "openapi" }`; `arazzo validate --resolve-sources doc.arazzo` is invoked; the fetcher policy allows local file access.
- **When** `ValidateCommand` runs in resolve mode and iterates each source description.
- **Then** each source URL is fetched and parsed as YAML/JSON; `SourceTypeMatchesRule` checks each source; no `source.unreadable`, `source.unparseable`, or `source.type_matches` diagnostics are emitted for valid sources.

**Scenario 2: Missing source produces `source.unreadable`**
- **Given** an Arazzo document with source description `{ name: "api", url: "./missing.yaml", type: "openapi" }`; the file `./missing.yaml` does not exist; `--resolve-sources` is enabled.
- **When** `DefaultSourceResolver` attempts to fetch `./missing.yaml`.
- **Then** validation reports `source.unreadable` with pointer at `/sourceDescriptions/0/url`; `ErrorCollector` records the error under `errors`; the error message names the missing URL; `ValidationResult::isValid()` returns `false`.

**Scenario 3: Unparseable source produces `source.unparseable`**
- **Given** an Arazzo document with source description `{ name: "api", url: "./bad.yaml", type: "openapi" }`; the file `./bad.yaml` contains invalid YAML (e.g., unbalanced quotes); `--resolve-sources` is enabled.
- **When** `SymfonyYamlDecoder::decode()` attempts to parse the fetched content.
- **Then** validation reports `source.unparseable` with pointer at `/sourceDescriptions/0/url`; `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`.

**Scenario 4: Type mismatch produces `source.type_matches`**
- **Given** an Arazzo document with source description `{ name: "api", url: "./openapi.yaml", type: "arazzo" }`; the file `./openapi.yaml` contains an OpenAPI 3.0 document (not an Arazzo document); `--resolve-sources` is enabled; type inference or content detection identifies the content as OpenAPI.
- **When** `SourceTypeMatchesRule` compares the declared type `arazzo` against the detected content type `openapi`.
- **Then** validation reports `source.type_matches` with pointer at `/sourceDescriptions/0/type`; `ErrorCollector` records the error under `errors`; `ValidationResult::isValid()` returns `false`.

**Scenario 5: Correct source produces no new diagnostics**
- **Given** an Arazzo document with source descriptions that are readable, parseable, and type-correct (declared type matches content); `--resolve-sources` is enabled.
- **When** `Validator::validate()` runs in resolve mode.
- **Then** no new diagnostics beyond the base validation are emitted; `ErrorCollector` records no `source.*` errors; `ValidationResult::isValid()` returns `true` if base validation passes.

**Scenario 6: Default `validate` remains offline**
- **Given** an Arazzo document (`doc.arazzo`); `arazzo validate doc.arazzo` is invoked without `--resolve-sources`; no fetcher policy is consulted.
- **When** `ValidateCommand` runs in default (offline) mode.
- **Then** no sources are fetched; no network requests are made; the result is identical to validation without `--resolve-sources`; `ValidationResult::isValid()` depends only on structural/semantic validation of the Arazzo document itself.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `arazzo validate --resolve-sources <file>` fetches and parses every source description
- [ ] Missing/unreadable/unparseable sources produce diagnostics with exact pointers
- [ ] Declared `type: arazzo` pointing at OpenAPI produces `source.type_matches`
- [ ] Correct source produces no new diagnostics
- [ ] Type inference runs only when `type` is omitted; explicit type always wins (coordinate with draft 06)
- [ ] Default `arazzo validate <file>` remains offline and produces the same result as today
- [ ] Fetching honors fetcher hardening policy (draft 09): timeouts, size limits, redirect limits, local confinement
- [ ] Results cacheable within one validate invocation (each URL fetched once)
- [ ] Tests use local fixtures and fake PSR-18 client; no network in CI
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Diagnostics logged via `ErrorCollector`
- [ ] Docs describe the new flag, diagnostics, and offline default


### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI flag; `SourceTypeMatchesRule` changes from no-op to active only in resolve mode.
- **Blast radius:** `packages/sources`, `packages/cli`, `packages/document`; no runtime execution change.
- **Severity:** medium — prevents late failures and improves CI signal.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `validate` does not fetch sources | T1 | `ValidateCommand` reads only the document; no resolver call | direct read |
| `SourceTypeMatchesRule` is a no-op | T1 | `SourceTypeMatchesRule.php:26-29` | direct read |
| Preflight resolves sources only at run time | T1 | `PreflightValidator.php:48-118` | direct read |
| The resolver can fetch/decode sources | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| Type inference is feasible after draft 06 | T3 | Design dependency | proposed |
