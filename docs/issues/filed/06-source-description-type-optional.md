# Draft issue 06 — Make `sourceDescriptions[].type` optional

- **Suggested title:** `[Bug] Parser rejects valid documents that omit sourceDescription type`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #23 Named Source Resolution + OpenAPI, or a new sources epic)
- **Packages:** `document`, `sources`, `contracts`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` parser, `packages/contracts` `SourceDescription`, `packages/sources` resolution.
- **Arazzo spec version:** 1.0.0, 1.0.1, 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:bugfix`, `level:story`.
- **Priority:** high — the parser rejects documents the spec and the official schema declare valid.

### 📝 2. Problem Statement & Intent

In the Source Description Object, only `name` and `url` are **REQUIRED**. The `type` field is optional in the prose and in the official JSON Schema (`required: ["name", "url"]` in 1.0, 1.1 and 1.2).

php-arazzo's parser calls `requireString($obj, 'type')`, so any document that omits `type` throws `ParserException: Missing required field: /sourceDescriptions/0/type`. Such a document is valid Arazzo and is accepted by the official schema.

The field is genuinely useful when present, so the fix is not to drop it: keep `type` when supplied, make it optional on the DTO, and infer the source type at resolution time when it is absent (or require the caller to configure a default).

### 📦 3. System Dependency Matrix

- **Upstream:** `packages/contracts` (`SourceDescription::$type`, `SourceType`).
- **Parser:** `packages/document/src/Parser/Parser.php` — `parseSourceDescription()`.
- **Sources:** `packages/sources/src/Resolver/DefaultSourceResolver.php`, `SourceNormalizerRegistry`, `OpenApiVersionDetector`.
- **Validator:** `SourceTypeMatchesRule` (currently a no-op; draft 08 makes it real).
- **CLI:** `arazzo validate` must accept such documents.
- **Related:** #167 (Swagger 2.0 source support) and #171 (beyond-spec `SourceType` cases) are separate.

### 📄 4. Structural Artifacts & Raw Inputs

```yaml
arazzo: 1.1.0
info: { title: T, version: "1.0.0" }
sourceDescriptions:
  - name: api
    url: https://example.com/openapi.yaml
workflows:
  - workflowId: wf
    steps:
      - stepId: one
        operationId: $sourceDescriptions.api.getThing
```

### 🌀 5. Runtime Execution State Context

- Parse-time failure; no execution.
- Observed: `ParserException: Missing required field: /sourceDescriptions/0/type`.
- The official 1.1 schema accepts the same document (`required` is only `name`, `url`).

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Make `sourceDescriptions[].type` optional in the parser so valid documents that omit `type` (per the spec and official schema) are accepted, with type inferred at resolution time when absent.

**Architectural Boundary:** Bounded context = document parsing + source resolution. Packages: `packages/document` (parser `parseSourceDescription`, `SourceDescription::$type`), `packages/sources` (resolver inference), `packages/contracts` (`SourceDescription`, `SourceType`). CLI `arazzo validate` must accept such documents offline.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Parser change is O(1); inference happens at resolution time only when needed; no impact on valid documents with explicit `type`.
- [ ] **Flexible:** `$type` becomes nullable or gains `Unknown` sentinel; inference is pluggable via `SourceNormalizerRegistry`; explicit `type` always wins.
- [ ] **Maintainable:** Single change point in `parseSourceDescription()`; `SourceTypeMatchesRule` (draft 08) can consume inferred type.
- [ ] **Operable:** `arazzo validate` remains offline and deterministic; no deployment changes.
- [ ] **Reliable:** Deterministic parse result; idempotent; un-inferable sources produce a stable error rather than a crash.
- [ ] **Safe:** Parser change is additive for valid documents; no state mutation; inference failures are graceful diagnostics.
- [ ] **Secure:** No authorization impact; type inference does not bypass fetcher hardening (draft 09).
- [ ] **Suitable:** Aligns with Arazzo 1.1.0 §5.8.3.1 (only `name` and `url` are REQUIRED; `type` is optional in schema `required: ["name","url"]`).
- [ ] **Usable:** Users can omit `type` and let the resolver infer it; clear diagnostic when inference fails.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Document without `type` parses successfully on 1.1.x**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing a source description `{ name: "api", url: "https://example.com/openapi.yaml" }` with no `type` field; `SourceDescription::$type` is nullable (`?SourceType`); `Parser::parseSourceDescription()` does not call `requireString($obj, 'type')`.
- **When** `php bin/console arazzo validate doc.arazzo` invokes the parser's `parseSourceDescription()`.
- **Then** parsing succeeds; `SourceDescription::$type` is `null`; no `ParserException` is thrown; `ValidationResult::isValid()` returns `true`.

**Scenario 2: Document without `type` parses successfully on 1.0.x**
- **Given** an Arazzo 1.0.0 document containing a source description `{ name: "api", url: "https://example.com/openapi.yaml" }` with no `type` field.
- **When** the parser parses the source description.
- **Then** parsing succeeds; `SourceDescription::$type` is `null`; no `ParserException` is thrown.

**Scenario 3: Explicit `type: openapi` is validated against the enum**
- **Given** an Arazzo 1.1.0 document containing a source description `{ name: "api", url: "https://example.com/openapi.yaml", type: "openapi" }`; `SourceType::tryFrom('openapi')` returns `SourceType::OpenAPI`.
- **When** `Parser::parseSourceDescription()` reads the `type` field.
- **Then** `SourceDescription::$type` is set to `SourceType::OpenAPI`; no `ParserException` is thrown.

**Scenario 4: Invalid explicit `type` still throws**
- **Given** an Arazzo 1.1.0 document containing a source description `{ name: "api", url: "https://example.com/openapi.yaml", type: "bogus" }`; `SourceType::tryFrom('bogus')` returns `null`.
- **When** `Parser::parseSourceDescription()` validates the `type` enum.
- **Then** a `ParserException` (invalid enum) is thrown with message containing `Invalid enum value 'bogus' for type; expected one of: openapi|arazzo|asyncapi|wsdl|protobuf|graphql`; parsing fails before document validation.

**Scenario 5: Missing `type` is inferred at resolution time**
- **Given** an Arazzo 1.1.0 document containing a source description `{ name: "api", url: "./openapi.yaml" }` with no `type`; `DefaultSourceResolver` fetches the URL and the content is an OpenAPI 3.0 document; `OpenApiVersionDetector` detects the OpenAPI shape.
- **When** `DefaultSourceResolver::resolve()` infers the type from the fetched document content.
- **Then** the inferred type is `openapi`; no `source.type_inference_failed` diagnostic is emitted.

**Scenario 6: Explicit `type` always wins over inference**
- **Given** an Arazzo 1.1.0 document containing a source description `{ name: "api", url: "./openapi.yaml", type: "arazzo" }`; the content at the URL looks like OpenAPI but the declared type is `arazzo`.
- **When** `DefaultSourceResolver::resolve()` processes the source description.
- **Then** `SourceType::OpenAPI` is NOT used; `SourceType::Arazzo` is used (explicit wins); inference is skipped when `type` is explicitly declared.

**Scenario 7: `arazzo validate` succeeds offline without `type`**
- **Given** an Arazzo 1.1.0 document containing a source description with no `type` field; no network access is available; validation does not require fetching sources.
- **When** `php bin/console arazzo validate doc.arazzo` runs without `--resolve-sources`.
- **Then** validation succeeds; parsing does not require fetching; no network request is made; `ValidationResult::isValid()` returns `true`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `SourceDescription::$type` becomes nullable (or gains `Unknown` sentinel); update existing code that assumes a `SourceType`
- [ ] Parser accepts a source description without `type` on 1.0.x and 1.1.x
- [ ] When `type` is present, validate against the version-appropriate enum exactly as today
- [ ] When `type` is absent, resolution infers type from fetched document (OpenAPI version detection, Arazzo document shape, AsyncAPI shape); clear diagnostic if inference fails
- [ ] Explicit `type` always wins over inference
- [ ] `SourceTypeMatchesRule` can consume the inferred type (coordinate with draft 08)
- [ ] Pest tests: omitted type parses; explicit type parses; invalid explicit type throws; inference for OpenAPI 3.x and Arazzo; un-inferable source produces stable error
- [ ] `arazzo validate` on a document without `type` succeeds offline
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging via `ErrorCollector` instrumented
- [ ] Document the minor-version BC change (`?SourceType`)


### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Parser/Parser.php:132-146` — `type` is read with `requireString()`.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Parser/Parser.php#L132-L146"><code>Parser.php:132-146</code></a> @ <code>ea8c55a</code></summary>

```php
protected function parseSourceDescription(mixed $node, ParseContext $ctx): SourceDescription
{
    $obj = $this->requireObjectMap($node, $ctx);
    $type = $this->requireString($obj, 'type', $ctx);
    $enum = SourceType::tryFrom($type)
        ?? throw ParserException::invalidEnum(
            $ctx->push('type'), 'openapi|arazzo|asyncapi|wsdl|protobuf|graphql', $type,
        );

    return new SourceDescription(
        name: $this->requireString($obj, 'name', $ctx),
        url: $this->requireString($obj, 'url', $ctx),
        type: $enum,
    );
}
```

</details>

- `packages/sources/src/Resolver/DefaultSourceResolver.php:58-96` — the resolver currently requires `$source->type` when normalizing.
- Vendored schema: `packages/document/resources/schemas/arazzo-1.0.schema.json` (and 1.1/1.2), `source-description-object.required = ["name","url"]`.
- Spec: Arazzo 1.1.0 §5.8.3.1 Source Description Object.

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** `SourceDescription::$type` changes from `SourceType` to `?SourceType` (or `SourceType::Unknown`). This is a small BC break; a minor release is appropriate.
- **Blast radius:** parser, source resolver, normalizer selection, validator.
- **Severity:** high — currently rejects valid third-party documents before any rule runs.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Omitting `type` throws today | T1 | Executed parser run on the artifact | `ParserException` |
| `type` is optional in the spec | T2 | Arazzo 1.1.0 §5.8.3.1 | spec text |
| `type` is optional in the official schema | T1 | Vendored schema `required` list | direct read |
| Resolution currently depends on `type` | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| Offline inference is feasible | T3 | `OpenApiVersionDetector` exists; Arazzo/AsyncAPI shape checks are straightforward | proposed design |
