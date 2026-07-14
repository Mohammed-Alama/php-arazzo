# Laravel Arazzo — Source Resolution (v1) Design

**Status**: Draft
**Created**: 2026-07-14
**Package**: `alama/laravel-arazzo`
**Namespace**: `Alama\LaravelArazzo\Resolution`
**Slice**: Source Resolution (Loading and parsing referenced OpenAPI/Arazzo docs).

---

## 1. Goals & Non-Goals

### Goals

- Resolve `SourceDescription` DTOs into accessible, parsed object trees.
- Support both local files and remote HTTP(S) URLs.
- Implement caching for remote files to prevent repetitive fetching during execution.
- Extract specific values using JSON Pointers (RFC 6901) for Arazzo expressions (e.g., `$sourceDescriptions.api.paths./users.get`).
- Delegate OpenAPI parsing to `cebe/php-openapi` for robust, typed object trees.
- Keep the foundation DTOs pure and immutable (resolution logic lives in dedicated services).

### Non-Goals

- Strict validation of the referenced OpenAPI/Arazzo documents. Lenient parsing is used; if it parses, it's accepted.
- Executing workflows (this is a prerequisite for the Runner, but the Runner itself is a separate spec).
- Modifying or merging the resolved documents back into the source Arazzo file.

---

## 2. Architecture & Interfaces

The resolution layer follows a Service-Oriented Architecture to ensure the core parser DTOs remain pure.

```
┌────────────────────────────────────────────────────────┐
│                      SourceResolver                    │
│   (Orchestrator: takes SourceDescription, returns      │
│    ResolvedSource)                                     │
└────────┬─────────────────────────────┬─────────────────┘
         │                             │
┌────────▼────────┐           ┌────────▼────────┐
│  SourceFetcher  │           │  SourceParser   │
│  (Interface)    │           │  (Interface)    │
└────────┬────────┘           └────────┬────────┘
         │                             │
   ┌─────┴─────┐                 ┌─────┴─────┐
   │           │                 │           │
 Local      Cached             OpenApi     Arazzo
 Fetcher    Fetcher            Parser      Parser
               │                 │
               │            (cebe/php-openapi)
          HttpFetcher
```

### Core Interfaces

```php
interface SourceResolver
{
    /**
     * Resolves a SourceDescription into a queryable object tree.
     */
    public function resolve(SourceDescription $source, string $basePath): ResolvedSource;
}

interface ResolvedSource
{
    /**
     * Extracts a value from the resolved document using a JSON Pointer.
     * @throws UnresolvableReferenceException if the path does not exist.
     */
    public function extract(string $jsonPointer): mixed;
}

interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}

interface SourceParser
{
    public function parse(string $content): ResolvedSource;
}
```

---

## 3. Fetching & Caching

### Fetchers

- **`LocalFetcher`**: Reads from the local filesystem. Resolves relative paths against the `$basePath` (which is the directory of the original Arazzo document being executed/validated).
- **`HttpFetcher`**: Fetches URLs using Laravel's `Http` facade.
- **`CachedFetcher`**: A decorator around `SourceFetcher` (typically wrapping `HttpFetcher`) that uses a PSR-16 `CacheInterface` (Laravel's Cache facade).

### Caching Strategy

- **What is cached**: The *raw string payload* returned by the HTTP response. We avoid caching the parsed `cebe\openapi\spec\OpenApi` objects due to complex serialization issues with third-party libraries.
- **Cache Key**: `arazzo_source_md5($url)`.
- **TTL**: Configurable via Laravel config `config('arazzo.source_cache_ttl', 3600)`.

---

## 4. Parsing & Data Extraction

### Parsers

- **`OpenApiSourceParser`**: Uses `cebe/php-openapi` to read the raw string and instantiate a full OpenAPI object tree. Wraps the result in an `OpenApiResolvedSource`.
- **`ArazzoSourceParser`**: Uses our existing `Alama\LaravelArazzo\Parser` to instantiate an `ArazzoDocument`. Wraps the result in an `ArazzoResolvedSource`.

### Extraction

To resolve expressions like `$sourceDescriptions.myApi.paths./users.get`, the `ResolvedSource` interface provides an `extract(string $jsonPointer)` method.

- **`OpenApiResolvedSource`**: Traverses the `cebe/php-openapi` object properties based on the JSON pointer segments.
- **`ArazzoResolvedSource`**: Traverses the `ArazzoDocument` DTO properties.

If a segment of the pointer cannot be found, the method throws an `UnresolvableReferenceException`.

---

## 5. Error Handling

Exceptions are specific to the resolution domain and do not bleed into the foundation parser exceptions.

- `SourceResolutionException` (Abstract base)
  - `SourceFetchException`: Thrown on 404, connection timeout, or unreadable local files.
  - `SourceParseException`: Thrown on malformed YAML/JSON or fatal `cebe/php-openapi` errors.
  - `UnresolvableReferenceException`: Thrown during `extract()` if the requested JSON pointer path doesn't exist in the parsed document.

---

## 6. Dependencies

Add to `composer.json`:
- `cebe/php-openapi: ^1.7` — for robust OpenAPI v3.x parsing.
