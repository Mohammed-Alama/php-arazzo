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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A document larger than `maxBytes` is rejected before full decoding.
- [ ] A "billion laughs" alias document is rejected with `document.yaml_limit` naming the alias limit.
- [ ] A document with nesting deeper than `maxDepth` is rejected with `document.yaml_limit`.
- [ ] A document with more than `maxNodes` nodes is rejected with `document.yaml_limit`.
- [ ] Custom tags and object deserialization are refused by default.
- [ ] Source documents go through the same policy.
- [ ] Legitimate official Arazzo and OpenAPI fixtures still decode unchanged.
- [ ] The policy is configurable; secure defaults are the default.
- [ ] Tests cover each limit, an alias bomb, deep nesting and a large flat document.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** new policy object and exceptions; secure defaults may reject pathological documents that previously parsed.
- **Blast radius:** every YAML document and source; JSON is unaffected.
- **Severity:** high — memory/CPU denial of service.
- **Rollback:** raise or disable individual limits; decoding remains the same otherwise.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| YAML is parsed with defaults | T1 | `SymfonyYamlDecoder.php:20` | direct read |
| There is no size/alias/depth policy | T1 | `SymfonyYamlDecoder.php:15-24`; no policy class | direct read |
| Source documents use the same decoder | T1 | `DefaultSourceResolver.php:75` | direct read |
| The decoder is the single YAML entry point | T1 | `Loader.php:35` | direct read |
| Alias expansion is unbounded by default | T2 | Symfony's `Yaml::parse()` exposes no alias limit | inferred from API surface |
