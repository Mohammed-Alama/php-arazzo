# Draft issue 07 — `$self` identity resolution and base-URI derivation

- **Suggested title:** `[Feature] Resolve Arazzo sources by '$self' identity and use '$self' as the base URI`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics & cross-document references)
- **Packages:** `sources`, `document`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/sources` (resolution + registry), `packages/document` (document model), `packages/runner` (locator, coordinated with #83).
- **Arazzo spec version:** 1.1.0 (§5.2 Identity-Based Referencing and Relative References).
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`.
- **Priority:** high for federated documents; complements #83, does not duplicate it.

### 📝 2. Problem Statement & Intent

Arazzo 1.1.0 makes `$self` load-bearing for interoperability:

- When referencing an Arazzo Description by URI, references **MUST** use the target document's `$self` URI when present.
- When a Source Description `url` is an absolute URI, the implementation **MUST** resolve it to an Arazzo Description by identity (matching `$self`) rather than by location alone.
- The base URI for relative references inside a document is the `$self` URI when present and absolute; when `$self` is relative it must first be resolved against the next base-URI source.

php-arazzo parses `$self` into `ArazzoDocument::$self` but never uses it. `DefaultSourceResolver` resolves relative URLs against the retrieval path only (`$basePath`), and no registry indexes documents by `$self`. Cross-document work (#83) therefore has to rely on location, which the spec explicitly calls non-interoperable.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Capture `$self` when an Arazzo document is fetched and expose it on `SourceDocument` (or an identity value object).
- Build an index of available Arazzo documents keyed by their `$self` URI, with the retrieval URI as a secondary key.
- Resolve an absolute source `url` by identity first, then by location (spec-preferred order).
- Use `$self` as the base URI when resolving relative `url` values inside a document, including nested source descriptions.
- Make the result available to the workflow locator planned in #83.

**Non-goals**

- Implementing cross-document workflow execution itself (#83).
- Bundling/dereferencing (#84).
- Changing the parser's `$self` syntax handling (draft 02).
- Resolving `$self` across HTTP redirects beyond the final retrieval URI.

**Prior art in this repository**

- `packages/sources/src/Resolver/DefaultSourceResolver.php` already fetches, decodes and computes a canonical URI.
- `packages/sources/src/Resolver/SourceRegistry.php` resolves sources by name.
- `packages/contracts/src/Spec/ArazzoDocument.php` already carries `$self`.
- #83 plans an `ArazzoWorkflowLocator`; this issue supplies the identity index it should consume.

**Proposed interface (shape, not final)**

```php
final readonly class ArazzoSourceIdentity
{
    public function __construct(
        public string $retrievalUri,
        public ?string $selfUri,
    ) {}
}

interface SourceIdentityIndex
{
    public function add(ArazzoSourceIdentity $identity): void;
    public function findBySelf(string $uri): ?ArazzoSourceIdentity;
    public function findByLocation(string $uri): ?ArazzoSourceIdentity;
}
```

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `SourceDocument` (or a companion value object) exposes the document's `$self` when present.
- [ ] A registry indexes fetched Arazzo documents by `$self` and by retrieval URI.
- [ ] An absolute source `url` equal to a known `$self` resolves by identity even when the retrieval location differs.
- [ ] Relative source URLs inside a document are resolved against its `$self` when `$self` is absolute; when `$self` is relative, it is first resolved against the retrieval URI.
- [ ] If `$self` is absent, behaviour is unchanged (retrieval URI is the base).
- [ ] Conflicts (two documents claiming the same `$self`) produce a deterministic error with both retrieval URIs.
- [ ] Tests cover: absolute `$self`; relative `$self`; identity match with different location; identity miss falls back to location; duplicate `$self`; nested source descriptions.
- [ ] The design is documented in `docs/architecture/` and referenced by #83.

### 🧬 7. Source Grounding & Code Location

- `packages/sources/src/Resolver/DefaultSourceResolver.php:58-96` — resolves by URL and computes `canonicalUri` from the retrieval path; no `$self` use.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/sources/src/Resolver/DefaultSourceResolver.php#L58-L96"><code>DefaultSourceResolver.php:58-96</code></a> @ <code>ea8c55a</code></summary>

```php
public function resolve(SourceDescription $source, string $basePath): SourceDocument
{
    $scheme = parse_url($source->url, PHP_URL_SCHEME);
    if (!is_string($scheme) || $scheme === '') {
        $scheme = 'file';
    }

    $fetcher = $this->fetchers[$scheme] ?? null;
    // ... fetch and decode ...
    $canonicalUri = $this->resolveCanonicalUri($source->url, $basePath);

    return new SourceDocument(
        name: $source->name,
        type: $source->type,
        canonicalUri: $canonicalUri,
        content: $decoded,
    );
}
```

</details>

- `packages/document/src/Parser/Parser.php:74` — `$self` is parsed but only stored.
- `packages/contracts/src/Spec/ArazzoDocument.php:27` — `public ?string $self = null`.
- Spec: Arazzo 1.1.0 §5.2 “Identity-Based Referencing” and “Relative References in Arazzo Description URIs”.

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive — new identity value object and registry; `SourceDocument` gains a field.
- **Blast radius:** `packages/sources` resolution, `packages/runner` locator (#83), cross-document references.
- **Severity:** high for federated documents; medium for single-document users.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `$self` is parsed and stored | T1 | `Parser.php:74`, `ArazzoDocument.php:27` | direct read |
| `$self` is never used for resolution | T1 | grep for `->self` in `packages/sources`/`packages/runner` | direct read |
| Resolution uses the retrieval path | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| The spec requires identity-based resolution | T2 | Arazzo 1.1.0 §5.2 | spec text |
| #83 plans a locator that can consume this index | T1 | GitHub issue #83 body | issue read |
