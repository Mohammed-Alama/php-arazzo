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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `SourceDescription::$type` becomes nullable (or gains a sentinel `Unknown` case); existing code that assumes a `SourceType` is updated.
- [ ] The parser accepts a source description without `type` on 1.0.x and 1.1.x.
- [ ] When `type` is present, it is validated against the version-appropriate enum exactly as today.
- [ ] When `type` is absent, resolution infers the type from the fetched document (OpenAPI version detection, Arazzo document shape, AsyncAPI shape) and reports a clear diagnostic if inference fails.
- [ ] Explicit `type` always wins over inference.
- [ ] `SourceTypeMatchesRule` can consume the inferred type (coordinate with draft 08).
- [ ] Pest tests cover: omitted type parses; explicit type parses; invalid explicit type still throws; inference for OpenAPI 3.x and Arazzo; un-inferable source produces a stable error.
- [ ] `arazzo validate` on a document without `type` succeeds offline (validation must not require fetching).

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** `SourceDescription::$type` changes from `SourceType` to `?SourceType` (or `SourceType::Unknown`). This is a small BC break; a minor release is appropriate.
- **Blast radius:** parser, source resolver, normalizer selection, validator.
- **Severity:** high — currently rejects valid third-party documents before any rule runs.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Omitting `type` throws today | T1 | Executed parser run on the artifact | `ParserException` |
| `type` is optional in the spec | T2 | Arazzo 1.1.0 §5.8.3.1 | spec text |
| `type` is optional in the official schema | T1 | Vendored schema `required` list | direct read |
| Resolution currently depends on `type` | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| Offline inference is feasible | T3 | `OpenApiVersionDetector` exists; Arazzo/AsyncAPI shape checks are straightforward | proposed design |
