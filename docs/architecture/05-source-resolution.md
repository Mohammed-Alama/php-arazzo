# 05 — Source Resolution

## Purpose

Technical deep-dive into how the `sourceDescriptions` a workflow references — external OpenAPI documents, or nested Arazzo documents — are fetched, decoded, and turned into something the engine can resolve operations against. Relevant classes live in `Alama\Arazzo\Resolver\` and `Runner\Resolver\`/`Runner\Normalizer\`.

## Where source resolution fits

An Arazzo document's `sourceDescriptions` are just `{name, url, type}` triples at parse time (`Spec\SourceDescription`) — the parser never fetches anything. Resolution happens lazily, on demand, the first time a step actually needs to call an operation from that source (via `OpenApiOperationResolver::resolve()`, doc 02/04) or the first time a nested-Arazzo source needs its sub-workflows loaded.

## The `SourceResolver` contract

```php
interface SourceResolver {
    public function resolve(SourceDescription $source, string $basePath): SourceDocument;
}
```

One method, one job: given a `SourceDescription` and a base path/URL to resolve relative references against, return a `Spec\SourceDocument` — `{name, type, canonicalUri, content}` where `content` is the fully decoded (YAML or JSON) document as a plain array.

## `DefaultSourceResolver`: scheme dispatch + decode

`DefaultSourceResolver` (the only concrete `SourceResolver` implementation in `core`) is constructed with a `array<string, SourceFetcher>` map keyed by URL scheme (`'http'`, `'https'`, `'file'`). Its `resolve()`:

1. Parses the scheme out of `$source->url` via `parse_url(..., PHP_URL_SCHEME)`, defaulting to `'file'` when the URL has no scheme (a bare relative/absolute path).
2. Looks up the matching `SourceFetcher` from the injected map; throws `SourceFetchException` if none is configured for that scheme.
3. Calls `$fetcher->fetch($source->url, $basePath)` to get the raw document text.
4. Sniffs format by content, not extension: `!str_starts_with(trim($content), '{')` — i.e. "doesn't start with `{`" is treated as YAML, otherwise JSON — and decodes with `SymfonyYamlDecoder` or `NativeJsonDecoder` accordingly. A decode failure is wrapped as `SourceParseException`.
5. Computes a `canonicalUri` via `resolveCanonicalUri()` — if the URL already has a scheme it's used as-is; otherwise it's resolved as a relative path against `$basePath`, with `.`/`..` segments normalized manually (split on `/`, drop `.`, pop on `..`) and prefixed `file:///`.

This class is `final readonly` — it has no mutable state; all statefulness (caching, circular-reference tracking) is layered on separately.

## Fetchers: `HttpFetcher`, `LocalFetcher`, `CachedFetcher`

Each implements the one-method `SourceFetcher` interface (`fetch(string $urlOrPath, string $basePath): string`):

- **`HttpFetcher`** — takes a PSR-18 `ClientInterface` and PSR-17 `RequestFactoryInterface`. `resolveUrl()` passes absolute `http(s)://` URLs through unchanged; for relative URLs it requires `$basePath` to itself be an `http(s)://` URL and joins them (`rtrim($basePath,'/') . '/' . ltrim($url,'/')`) — otherwise it throws `SourceFetchException`, since a relative URL can't be resolved against a non-HTTP base. Non-2xx responses and PSR-18 client exceptions both raise `SourceFetchException` with the status code / underlying message.
- **`LocalFetcher`** — resolves the path (absolute paths — starting with `/` or a Windows drive letter — pass through; everything else is joined onto `$basePath`) and reads it with `@file_get_contents()`, raising `SourceFetchException` on failure.
- **`CachedFetcher`** — a decorator (`SourceFetcher $inner` + PSR-16 `CacheInterface`) that memoizes fetch results under `'arazzo_source_' . md5($urlOrPath . '|' . $basePath)` for a configurable TTL (default 3600s). This is what the Laravel bridge wraps around both the `http` and `https` fetchers (see below) — `LocalFetcher` is registered undecorated, since local file reads are already cheap and don't benefit from a network-style cache.

Fetchers are pure I/O — they know nothing about YAML/JSON decoding or the Arazzo document model; that's `DefaultSourceResolver`'s job, one layer up.

## Avoiding duplicate work and cycles: `SourceRegistry`

`SourceRegistry` wraps an inner `SourceResolver` and adds memoization plus circular-reference protection, while itself implementing `SourceResolver` (decorator pattern):

```php
public function resolve(SourceDescription $source, string $basePath): SourceDocument
{
    if (isset($this->documents[$source->name])) {
        return $this->documents[$source->name];       // already resolved
    }
    if (isset($this->resolving[$source->name])) {
        throw new UnresolvableReferenceException(...); // cycle
    }
    $this->resolving[$source->name] = true;
    try {
        $document = $this->resolver->resolve($source, $basePath);
        $this->register($document);
        return $document;
    } finally {
        unset($this->resolving[$source->name]);
    }
}
```

This matters specifically for nested Arazzo sources (`type: arazzo`): source A's document can declare a source pointing back to a document that (transitively) references A again. The `$resolving` guard turns that into a clean `UnresolvableReferenceException` instead of infinite recursion. `get(string $name)` gives read-only lookup of an already-resolved document without triggering a fetch, and `register()` allows pre-seeding the registry (useful in tests, or when a document is already in hand).

In the Laravel service provider (doc 06), the bound `SourceResolver::class` singleton *is* a `SourceRegistry` wrapping a `DefaultSourceResolver` — so every consumer in the app shares one resolved-source cache per request/job lifecycle.

## From `SourceDocument` to an executable operation: the OpenAPI path

Resolving a *source* only gets you the raw decoded document. Turning a step's `operationId`/`operationPath` into something `StepExecutor` can call against is a separate, more involved step, handled by `Runner\Resolver\OpenApiOperationResolver` in coordination with `Runner\Execution\OpenApiDocumentLoader` and `Runner\Normalizer\*`:

1. **Parse the operation reference.** A step identifies its target operation one of two ways:
   - `operationPath`: `{$sourceDescriptions.NAME.url}#/paths/PATH/METHOD` — parsed by splitting on `#`, validating the prefix, and extracting `NAME` plus the `/paths/...` JSON-Pointer-style suffix (with `~1`/`~0` unescaping for `/` and `~` in the path).
   - `operationId`: either source-qualified (`{$sourceDescriptions.NAME.url}#operationId`) or bare (just `operationId`) — a bare ID is only unambiguous when the document has exactly one non-Arazzo source; otherwise `OpenApiOperationResolver` throws, requiring the workflow author to qualify it.
2. **Load the OpenAPI document.** `OpenApiDocumentLoader` (backed by the `SourceResolver`/`SourceRegistry` chain above and `cebe/php-openapi`) parses the raw content into a structured `cebe\openapi\spec\OpenApi` object graph.
3. **Round-trip through a plain array.** The resolver re-serializes (`getSerializableData()` → `json_encode` → `json_decode`) to get a normalized `array<string,mixed>` representation — this is what `OpenApiVersionDetector` and the normalizers operate on, rather than the `cebe` object graph directly.
4. **Detect and reject unsupported versions.** `OpenApiVersionDetector::detect()` inspects the document (`openapi: 3.0.x` / `3.1.x` / Swagger `2.0`) and `OpenApiOperationResolver` fails fast with `UnsupportedSourceVersionException` for anything outside `3.0`/`3.1` — Swagger 2.0 has a `Swagger2Normalizer` present in the codebase but is explicitly rejected at this gate today.
5. **Locate the operation** within the `cebe` object graph, either by walking to the exact `/paths/{path}/{method}` (path-based) or by scanning every path × HTTP verb combination for a matching `operationId` (ID-based, `break 2` on first match).
6. **Normalize.** The matched raw document, path, and method are handed to `OpenApi30Normalizer` or `OpenApi31Normalizer` (chosen by detected version), which produces a `Normalizer\NormalizedOpenApiOperation` — a version-agnostic shape (parameters, request body schema, response schemas) that the rest of the execution pipeline (`StepExecutor`, `ArazzoOutputExtractor`'s schema-cast logic, `ArazzoSchemaValidator`) consumes without needing to know whether the source was 3.0 or 3.1.

The result, `Runner\Resolver\ResolvedOperation`, bundles the target `SourceDescription`, the `NormalizedOpenApiOperation`, the full `cebe` `OpenApi` object, the raw array document, and the specific `cebe\openapi\spec\Operation` instance — giving downstream consumers both the normalized convenience shape and an escape hatch to the full fidelity `cebe` model (used, for instance, by `ArazzoOutputExtractor` to walk response schemas for type-casting, doc 04).

## Summary

```
 Step.operationId / operationPath
        │  parse "{$sourceDescriptions.NAME.url}#..."
        ▼
 SourceDescription (by name, from ArazzoDocument)
        │  SourceRegistry::resolve()  ── memoized, cycle-guarded
        ▼
 DefaultSourceResolver::resolve()
        │  scheme dispatch → SourceFetcher (Http | Local, optionally Cached)
        ▼
 SourceDocument (raw decoded array)
        │  OpenApiDocumentLoader → cebe\openapi\spec\OpenApi
        ▼
 OpenApiVersionDetector → OpenApi30Normalizer | OpenApi31Normalizer
        ▼
 ResolvedOperation (NormalizedOpenApiOperation + cebe Operation)
        │  consumed by StepExecutor / ArazzoOutputExtractor / ArazzoSchemaValidator
```
