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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Resolve Arazzo sources by `$self` identity and use `$self` as the base URI for relative references, implementing Arazzo 1.1.0 §5.2 identity-based referencing so cross-document references are interoperable.

**Architectural Boundary:** Bounded context = source resolution + document model. Packages: `packages/sources` (resolution + registry), `packages/document` (document model), `packages/runner` (locator, coordinated with #83). Does not implement cross-document workflow execution itself (#83) or bundling (#84).

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Identity index lookup is O(1) via hash map; resolution by identity first then location adds one map lookup; no impact on single-document users.
- [ ] **Flexible:** `SourceIdentityIndex` interface is swappable; `$self` capture is additive to `SourceDocument`; index is consumed by the #83 locator.
- [ ] **Maintainable:** Single-responsibility identity value object and registry; deterministic conflict error includes both retrieval URIs.
- [ ] **Operable:** No new deployment or telemetry; conflicts produce deterministic errors; rollback is removing the index (location-only resolution unchanged).
- [ ] **Reliable:** Deterministic resolution order (identity first, then location); cycle/duplicate `$self` handled with deterministic error.
- [ ] **Safe:** No state corruption; resolution is read-only; absent `$self` falls back to retrieval URI (unchanged behaviour).
- [ ] **Secure:** No authorization/ACL impact; identity-based resolution reduces reliance on location which the spec calls non-interoperable.
- [ ] **Suitable:** Implements Arazzo 1.1.0 §5.2 (identity-based referencing and relative references); `$self` is used as base URI when present and absolute.
- [ ] **Usable:** Clear error on duplicate `$self` with both retrieval URIs; behaviour unchanged when `$self` is absent.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `$self` is captured when an Arazzo document is fetched**
- **Given** an Arazzo 1.1.0 document fetched from `https://example.com/doc.arazzo` containing `$self: https://example.com/doc.arazzo`; `DefaultSourceResolver` has fetched and decoded the document; `SourceDocument` (or companion value object) exposes a `$selfUri` field.
- **When** `DefaultSourceResolver::resolve($source, $basePath)` returns a `SourceDocument`.
- **Then** `SourceDocument::$selfUri` equals `https://example.com/doc.arazzo`; `SourceDocument::$retrievalUri` equals `https://example.com/doc.arazzo`; the identity value object is populated.

**Scenario 2: Absolute source `url` matching a known `$self` resolves by identity**
- **Given** a `SourceIdentityIndex` containing one entry: retrieval URI `https://cache.example.com/copy.arazzo`, `$self` URI `https://example.com/doc.arazzo`; a source description with `url: https://example.com/doc.arazzo` (absolute, matches `$self` but different retrieval location).
- **When** `SourceIdentityIndex::findBySelf('https://example.com/doc.arazzo')` is called during resolution.
- **Then** the document is resolved by identity (returns the cached entry) even though the retrieval location differs; `SourceIdentityIndex::findByLocation` is not the primary match.

**Scenario 3: Identity miss falls back to location resolution**
- **Given** a `SourceIdentityIndex` containing no entry with `$self` URI `https://example.com/unknown.arazzo`; a source description with `url: https://example.com/unknown.arazzo` (absolute, no identity match).
- **When** `SourceIdentityIndex::findBySelf('https://example.com/unknown.arazzo')` returns `null`.
- **Then** resolution falls back to location-based resolution (`findByLocation`); the document is fetched from `https://example.com/unknown.arazzo`; no identity-based error is raised.

**Scenario 4: Relative source URLs resolved against `$self` when absolute**
- **Given** a document with absolute `$self: https://example.com/base/doc.arazzo` and a relative source `url: ./other.arazzo`; `$self` is absolute per RFC 3986.
- **When** the relative source URL is resolved against `$self` as the base URI.
- **Then** the resolved URL is `https://example.com/base/other.arazzo`; the `$self` URI serves as the base URI per Arazzo 1.1.0 §5.2.

**Scenario 5: Relative `$self` resolved against retrieval URI first**
- **Given** a document with relative `$self: ./doc.arazzo` retrieved from `https://example.com/base/`; `$self` is relative per RFC 3986.
- **When** `$self` is used as the base URI for relative references.
- **Then** `$self` is first resolved against the retrieval URI to `https://example.com/base/doc.arazzo`; subsequent relative source URLs resolve against this resolved `$self`.

**Scenario 6: Absent `$self` leaves behaviour unchanged**
- **Given** an Arazzo document with no `$self` field (`$self === null`); `SourceDocument::$selfUri` is `null`.
- **When** `DefaultSourceResolver::resolve()` processes the source description.
- **Then** the retrieval URI is used as the base URI (unchanged behaviour); no identity-based resolution is attempted; no error is raised.

**Scenario 7: Duplicate `$self` produces deterministic error**
- **Given** two fetched documents both claiming `$self: https://example.com/doc.arazzo` with retrieval URIs `https://cache-a.example.com/doc.arazzo` and `https://cache-b.example.com/doc.arazzo`; both are added to the `SourceIdentityIndex`.
- **When** the second document with the same `$self` URI is added to the index.
- **Then** a deterministic error is thrown with message containing both retrieval URIs (`https://cache-a.example.com/doc.arazzo` and `https://cache-b.example.com/doc.arazzo`); the error identifies the duplicate `$self` conflict; `SourceIdentityIndex::add()` does not silently overwrite.
- **When** resolution runs
- **Then** retrieval URI is used as the base (unchanged behaviour)

**Scenario 6: Duplicate `$self` produces deterministic error**
- **Given** two fetched documents both claiming `$self: https://example.com/doc.arazzo` with different retrieval URIs
- **When** both are added to the index
- **Then** a deterministic error reports both retrieval URIs

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `SourceDocument` (or companion value object) exposes the document's `$self` when present
- [ ] Registry indexes fetched Arazzo documents by `$self` and by retrieval URI
- [ ] Absolute source `url` equal to a known `$self` resolves by identity even when retrieval location differs
- [ ] Relative source URLs resolved against `$self` when absolute; relative `$self` first resolved against retrieval URI
- [ ] Absent `$self` leaves behaviour unchanged (retrieval URI is base)
- [ ] Conflicts (two documents claiming same `$self`) produce deterministic error with both retrieval URIs
- [ ] Tests: absolute `$self`; relative `$self`; identity match with different location; identity miss falls back to location; duplicate `$self`; nested source descriptions
- [ ] Design documented in `docs/architecture/` and referenced by #83
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed (resolution is read-only)


### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive — new identity value object and registry; `SourceDocument` gains a field.
- **Blast radius:** `packages/sources` resolution, `packages/runner` locator (#83), cross-document references.
- **Severity:** high for federated documents; medium for single-document users.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `$self` is parsed and stored | T1 | `Parser.php:74`, `ArazzoDocument.php:27` | direct read |
| `$self` is never used for resolution | T1 | grep for `->self` in `packages/sources`/`packages/runner` | direct read |
| Resolution uses the retrieval path | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| The spec requires identity-based resolution | T2 | Arazzo 1.1.0 §5.2 | spec text |
| #83 plans a locator that can consume this index | T1 | GitHub issue #83 body | issue read |
