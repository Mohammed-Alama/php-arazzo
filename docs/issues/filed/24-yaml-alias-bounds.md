# Draft issue 24 — Bound YAML alias expansion and nesting depth

- **Suggested title:** `[Feature] Bound YAML alias expansion, nesting depth and document size`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #118 Document & runner validation hardening)
- **Packages:** `document`, `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/document` `SymfonyYamlDecoder` and `Loader`; `packages/sources` `DefaultSourceResolver`.
- **Arazzo spec version:** all versions; YAML is a supported input format.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`, under #118 (Document & runner validation hardening).
- **Priority:** P2 — denial of service when documents come from untrusted sources.

### 📝 2. Problem Statement & Intent

Both the Arazzo document and referenced source documents are decoded by the same
class:

```php
public function decode(string $source): mixed
{
    try {
        return Yaml::parse($source);
    } catch (ParseException $e) {
        throw new DecodeException($e->getMessage(), 0, $e);
    }
}
```

(`packages/document/src/Parser/Decoders/SymfonyYamlDecoder.php:17-24`)

`DefaultSourceResolver` uses the same decoder for fetched source documents
(`packages/sources/src/Resolver/DefaultSourceResolver.php:75`). `Yaml::parse()`
is called with defaults:

- **no alias expansion bound** — a small "billion laughs" document with nested
  anchors expands exponentially during parsing;
- **no nesting-depth bound** — deeply nested sequences/maps can exhaust the
  stack;
- **no document-size bound** — the caller reads the entire source into memory
  before decoding;
- **no explicit tag policy** — custom tags and object deserialization are not
  consciously refused.

A 1 KB YAML file can therefore consume gigabytes of memory or crash the process
before validation ever runs.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `YamlDecodePolicy`:
  - `maxBytes` (default 5 MiB)
  - `maxAliases` (default 100)
  - `maxDepth` (default 64)
  - `maxNodes` (default 100_000)
  - `allowCustomTags` (default false)
  - `allowObjectDeserialization` (default false)
- Pre-scan the raw text for anchors (`&name`) and aliases (`*name`); reject when
  the alias count or anchor-to-alias fan-out exceeds the policy.
- Enforce `maxBytes` before decoding; don't read an unbounded body just to
  reject it.
- Enforce `maxDepth` and `maxNodes` after decoding (or during a streaming
  parse) and reject with a stable `document.yaml_limit` error.
- Parse with `Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE`; never enable
  `PARSE_OBJECT` or custom object hydration.
- Surface limit failures as `DecodeException` subclasses with the limit name and
  byte offset where possible.
- Apply the same policy to source documents so a malicious OpenAPI source cannot
  bypass it.
- CLI flag: `--yaml-max-bytes`, `--yaml-max-depth` (advanced; defaults secure).

**Non-goals**

- Writing a YAML parser.
- Supporting YAML merge keys beyond what Symfony supports.
- JSON limits (JSON decoding is already bounded by input size and has no alias
  mechanism; a size cap is still applied by the caller).

**Prior art in this repository**

- `DecodeException` already wraps parse failures.
- `SymfonyYamlDecoder` is the single YAML entry point, so the policy is a local
  change.
- `DefaultSourceResolver` already routes all source YAML through it.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Bound YAML alias expansion, nesting depth, and document size to prevent denial-of-service (billion laughs, stack exhaustion, OOM) when documents come from untrusted sources.

**Architectural Boundary:** Bounded context = YAML decoding. Packages: `packages/document` (`SymfonyYamlDecoder`, `Loader`), `packages/sources` (`DefaultSourceResolver`). JSON is unaffected; single YAML entry point makes the policy a local change.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** `maxBytes` pre-scan rejects oversized documents before decoding; alias expansion bound prevents exponential expansion; node/depth limits prevent stack exhaustion.
- [ ] **Flexible:** `YamlDecodePolicy` value object with configurable fields (`maxBytes`, `maxAliases`, `maxDepth`, `maxNodes`, `allowCustomTags`, `allowObjectDeserialization`); secure defaults with opt-outs.
- [ ] **Maintainable:** `SymfonyYamlDecoder` is the single YAML entry point; policy is a local change; `DecodeException` already wraps parse failures.
- [ ] **Operable:** Limit failures surface as `DecodeException` subclasses with limit name; secure defaults are the default; rollback by raising/disabling individual limits.
- [ ] **Reliable:** Deterministic decode result; idempotent; no crashes from pathological YAML.
- [ ] **Safe:** Pre-scan rejects alias bombs before parsing; custom tags and object deserialization refused by default; no side effects.
- [ ] **Secure:** Mitigates billion laughs, deep nesting, large document, custom tag attacks; applies to source documents too.
- [ ] **Suitable:** Closes the gap where `Yaml::parse()` is called with defaults and no bounds; 1 KB YAML can consume gigabytes.
- [ ] **Usable:** Secure defaults require no configuration; CLI flags (`--yaml-max-bytes`, `--yaml-max-depth`) for advanced users; legitimate fixtures decode unchanged.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Document larger than `maxBytes` rejected before decoding**
- **Given** a YAML document (`doc.yaml`) of size 6 MiB; `YamlDecodePolicy` is configured with `maxBytes: 5242880` (5 MiB); `SymfonyYamlDecoder` is the single YAML entry point; `Loader::load()` routes through `SymfonyYamlDecoder`.
- **When** `SymfonyYamlDecoder::decode($yamlContent)` is called with the 6 MiB document.
- **Then** the document is rejected before full decoding; `maxBytes` is pre-scanned before `Yaml::parse()` is called; a `DecodeException` is thrown with message naming `maxBytes`; no unbounded body is read into memory.

**Scenario 2: Billion laughs alias document rejected with `document.yaml_limit`**
- **Given** a YAML document with nested anchors causing exponential alias expansion ("billion laughs" attack); `YamlDecodePolicy` is configured with `maxAliases: 100`; the alias count or anchor-to-alias fan-out exceeds the policy.
- **When** `SymfonyYamlDecoder::decode()` processes the document.
- **Then** the document is rejected with `document.yaml_limit` naming the `maxAliases` limit; `ErrorCollector` records the error; the alias bomb is caught before exponential expansion consumes memory.

**Scenario 3: Nesting deeper than `maxDepth` rejected**
- **Given** a YAML document with nesting deeper than `maxDepth` (default 64); e.g., 100 levels of nested maps/sequences.
- **When** `SymfonyYamlDecoder::decode()` processes the document after decoding.
- **Then** the document is rejected with `document.yaml_limit` naming `maxDepth`; stack exhaustion is prevented; `DecodeException` carries the limit name.

**Scenario 4: More than `maxNodes` nodes rejected**
- **Given** a YAML document with more than `maxNodes` (default 100,000) nodes; e.g., a large flat map with 200,000 entries.
- **When** `SymfonyYamlDecoder::decode()` processes the document.
- **Then** the document is rejected with `document.yaml_limit` naming `maxNodes`; `ErrorCollector` records the error; memory exhaustion from excessive nodes is prevented.

**Scenario 5: Custom tags and object deserialization refused by default**
- **Given** a YAML document with custom tags (`!!php/object`) or object deserialization; `YamlDecodePolicy` is configured with `allowCustomTags: false` and `allowObjectDeserialization: false` (defaults); `Yaml::parse()` is called with `PARSE_EXCEPTION_ON_INVALID_TYPE` and never `PARSE_OBJECT`.
- **When** `SymfonyYamlDecoder::decode()` processes the document.
- **Then** the document is rejected; custom tags and object deserialization are refused by default; no PHP object injection occurs.

**Scenario 6: Source documents go through the same policy**
- **Given** a source document fetched via `DefaultSourceResolver` (e.g., an OpenAPI source at `./openapi.yaml`); `DefaultSourceResolver` routes all source YAML through `SymfonyYamlDecoder`.
- **When** the source document is decoded by `SymfonyYamlDecoder`.
- **Then** the same `YamlDecodePolicy` is applied to source documents; a malicious OpenAPI source cannot bypass the policy; `maxBytes`, `maxAliases`, `maxDepth`, `maxNodes` are enforced for source documents too.

**Scenario 7: Legitimate Arazzo/OpenAPI fixtures decode unchanged**
- **Given** official Arazzo and OpenAPI YAML fixtures (well-formed, within limits); `YamlDecodePolicy` with secure defaults.
- **When** the fixtures are decoded with the policy.
- **Then** they decode unchanged; no false positives; legitimate documents pass through the policy without modification; `Loader::load()` returns the same parsed structure as before.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Document larger than `maxBytes` rejected before full decoding
- [ ] Billion laughs alias document rejected with `document.yaml_limit` naming the alias limit
- [ ] Nesting deeper than `maxDepth` rejected with `document.yaml_limit`
- [ ] More than `maxNodes` nodes rejected with `document.yaml_limit`
- [ ] Custom tags and object deserialization refused by default
- [ ] Source documents go through the same policy
- [ ] Legitimate official Arazzo and OpenAPI fixtures decode unchanged
- [ ] Policy configurable; secure defaults are the default
- [ ] Tests cover each limit, alias bomb, deep nesting, large flat document
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI flags: `--yaml-max-bytes`, `--yaml-max-depth` (advanced; defaults secure)

### 🧬 10. Source Grounding & Code Location

- `packages/document/src/Parser/Decoders/SymfonyYamlDecoder.php:17-24` — unbounded `Yaml::parse()`.
- `packages/document/src/Parser/Loader.php:35` — decoder selection.
- `packages/sources/src/Resolver/DefaultSourceResolver.php:75` — source documents use the same decoder.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/document/src/Parser/Decoders/SymfonyYamlDecoder.php#L15-L24"><code>SymfonyYamlDecoder.php:15-24</code></a> @ <code>ea8c55a</code></summary>

```php
final class SymfonyYamlDecoder implements DecoderInterface
{
    public function decode(string $source): mixed
    {
        try {
            return Yaml::parse($source);
        } catch (ParseException $e) {
            throw new DecodeException($e->getMessage(), 0, $e);
        }
    }
}
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** new policy object and exceptions; secure defaults may reject pathological documents that previously parsed.
- **Blast radius:** every YAML document and source; JSON is unaffected.
- **Severity:** high — memory/CPU denial of service.
- **Rollback:** raise or disable individual limits; decoding remains the same otherwise.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| YAML is parsed with defaults | T1 | `SymfonyYamlDecoder.php:20` | direct read |
| There is no size/alias/depth policy | T1 | `SymfonyYamlDecoder.php:15-24`; no policy class | direct read |
| Source documents use the same decoder | T1 | `DefaultSourceResolver.php:75` | direct read |
| The decoder is the single YAML entry point | T1 | `Loader.php:35` | direct read |
| Alias expansion is unbounded by default | T2 | Symfony's `Yaml::parse()` exposes no alias limit | inferred from API surface |
