# Arazzo Source Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the `SourceResolver` and its Fetcher/Parser dependencies to load and parse referenced OpenAPI and Arazzo documents, enabling reference extraction.

**Architecture:** A Service-Oriented approach. A main `SourceResolver` orchestrates fetching via `SourceFetcher` (local, HTTP, cached) and parsing via `SourceParser` (cebe/php-openapi, Arazzo parser) to produce a `ResolvedSource` interface that can extract data via JSON Pointer.

**Tech Stack:** PHP 8.2+, Laravel (Illuminate/Support, Illuminate/Http, Illuminate/Cache), `cebe/php-openapi`, Pest PHP.

---

### Task 1: Setup cebe/php-openapi and Core Interfaces

**Files:**
- Create: `src/Resolution/Exceptions/SourceResolutionException.php`
- Create: `src/Resolution/Exceptions/SourceFetchException.php`
- Create: `src/Resolution/Exceptions/SourceParseException.php`
- Create: `src/Resolution/Exceptions/UnresolvableReferenceException.php`
- Create: `src/Resolution/ResolvedSource.php`
- Create: `src/Resolution/SourceFetcher.php`
- Create: `src/Resolution/SourceParser.php`
- Create: `src/Resolution/SourceResolver.php`
- Modify: `composer.json`

- [ ] **Step 1: Require `cebe/php-openapi`**

Run: `rtk composer require cebe/php-openapi:"^1.7"`
Expected: Installs successfully.

- [ ] **Step 2: Create Exception Classes**

```php
// src/Resolution/Exceptions/SourceResolutionException.php
namespace Alama\LaravelArazzo\Resolution\Exceptions;

abstract class SourceResolutionException extends \RuntimeException {}

// src/Resolution/Exceptions/SourceFetchException.php
namespace Alama\LaravelArazzo\Resolution\Exceptions;

class SourceFetchException extends SourceResolutionException {}

// src/Resolution/Exceptions/SourceParseException.php
namespace Alama\LaravelArazzo\Resolution\Exceptions;

class SourceParseException extends SourceResolutionException {}

// src/Resolution/Exceptions/UnresolvableReferenceException.php
namespace Alama\LaravelArazzo\Resolution\Exceptions;

class UnresolvableReferenceException extends SourceResolutionException {}
```

- [ ] **Step 3: Create Core Interfaces**

```php
// src/Resolution/ResolvedSource.php
namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

interface ResolvedSource
{
    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed;
}

// src/Resolution/SourceFetcher.php
namespace Alama\LaravelArazzo\Resolution;

interface SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string;
}

// src/Resolution/SourceParser.php
namespace Alama\LaravelArazzo\Resolution;

interface SourceParser
{
    public function parse(string $content): ResolvedSource;
}

// src/Resolution/SourceResolver.php
namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\SourceDescription;

interface SourceResolver
{
    public function resolve(SourceDescription $source, string $basePath): ResolvedSource;
}
```

- [ ] **Step 4: Commit**

```bash
rtk git add composer.json composer.lock src/Resolution/
rtk git commit -m "feat: add source resolution core interfaces and exceptions"
```

---

### Task 2: Implement Fetchers (Local, Http, Cached)

**Files:**
- Create: `src/Resolution/Fetchers/LocalFetcher.php`
- Create: `src/Resolution/Fetchers/HttpFetcher.php`
- Create: `src/Resolution/Fetchers/CachedFetcher.php`
- Create: `tests/Resolution/Fetchers/LocalFetcherTest.php`
- Create: `tests/Resolution/Fetchers/HttpFetcherTest.php`
- Create: `tests/Resolution/Fetchers/CachedFetcherTest.php`

- [ ] **Step 1: Write tests for Fetchers**

```php
// tests/Resolution/Fetchers/LocalFetcherTest.php
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;

it('fetches local file', function () {
    $fetcher = new LocalFetcher();
    $dir = __DIR__;
    file_put_contents($dir . '/test.json', '{"test": true}');
    
    $content = $fetcher->fetch('test.json', $dir);
    expect($content)->toBe('{"test": true}');
    
    unlink($dir . '/test.json');
});

it('throws on missing local file', function () {
    $fetcher = new LocalFetcher();
    $fetcher->fetch('missing.json', __DIR__);
})->throws(SourceFetchException::class);

// tests/Resolution/Fetchers/HttpFetcherTest.php
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Illuminate\Support\Facades\Http;

it('fetches http url', function () {
    Http::fake(['*' => Http::response('remote content')]);
    $fetcher = new HttpFetcher();
    
    expect($fetcher->fetch('https://example.com/api.json', ''))->toBe('remote content');
});

it('throws on http error', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);
    $fetcher = new HttpFetcher();
    
    $fetcher->fetch('https://example.com/api.json', '');
})->throws(SourceFetchException::class);

// tests/Resolution/Fetchers/CachedFetcherTest.php
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Illuminate\Support\Facades\Cache;

it('caches the fetched content', function () {
    $inner = new class implements SourceFetcher {
        public int $calls = 0;
        public function fetch(string $url, string $basePath): string {
            $this->calls++;
            return 'fetched';
        }
    };
    
    $fetcher = new CachedFetcher($inner, 3600);
    
    expect($fetcher->fetch('http://test.com', ''))->toBe('fetched');
    expect($fetcher->fetch('http://test.com', ''))->toBe('fetched');
    expect($inner->calls)->toBe(1);
});
```

- [ ] **Step 2: Run tests to see them fail**

Run: `rtk php artisan test --filter Fetchers`
Expected: FAIL due to missing classes.

- [ ] **Step 3: Implement Fetchers**

```php
// src/Resolution/Fetchers/LocalFetcher.php
namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;

class LocalFetcher implements SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string
    {
        $path = $this->isAbsolute($urlOrPath) ? $urlOrPath : rtrim($basePath, '/\\') . '/' . ltrim($urlOrPath, '/\\');
        
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new SourceFetchException("Failed to read local file: $path");
        }
        
        return $content;
    }
    
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}

// src/Resolution/Fetchers/HttpFetcher.php
namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceFetchException;
use Illuminate\Support\Facades\Http;

class HttpFetcher implements SourceFetcher
{
    public function fetch(string $urlOrPath, string $basePath): string
    {
        $response = Http::get($urlOrPath);
        
        if ($response->failed()) {
            throw new SourceFetchException("HTTP request failed for $urlOrPath: " . $response->status());
        }
        
        return $response->body();
    }
}

// src/Resolution/Fetchers/CachedFetcher.php
namespace Alama\LaravelArazzo\Resolution\Fetchers;

use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Illuminate\Support\Facades\Cache;

class CachedFetcher implements SourceFetcher
{
    public function __construct(
        private SourceFetcher $inner,
        private int $ttlSeconds = 3600
    ) {}

    public function fetch(string $urlOrPath, string $basePath): string
    {
        $key = 'arazzo_source_' . md5($urlOrPath . $basePath);
        
        return Cache::remember($key, $this->ttlSeconds, fn () => $this->inner->fetch($urlOrPath, $basePath));
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `rtk php artisan test --filter Fetchers`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add tests/Resolution/Fetchers/ src/Resolution/Fetchers/
rtk git commit -m "feat: implement local, http, and cached fetchers"
```

---

### Task 3: Implement Parsers and ResolvedSources

**Files:**
- Create: `src/Resolution/OpenApiResolvedSource.php`
- Create: `src/Resolution/ArazzoResolvedSource.php`
- Create: `src/Resolution/Parsers/OpenApiSourceParser.php`
- Create: `src/Resolution/Parsers/ArazzoSourceParser.php`
- Create: `tests/Resolution/Parsers/OpenApiSourceParserTest.php`

- [ ] **Step 1: Write test for OpenApiSourceParser**

```php
// tests/Resolution/Parsers/OpenApiSourceParserTest.php
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

it('parses openapi json and extracts value', function () {
    $parser = new OpenApiSourceParser();
    $json = '{"openapi": "3.0.0", "info": {"title": "Test API"}}';
    
    $resolved = $parser->parse($json);
    expect($resolved->extract('/info/title'))->toBe('Test API');
});

it('throws unresolvable reference on missing path', function () {
    $parser = new OpenApiSourceParser();
    $json = '{"openapi": "3.0.0", "info": {"title": "Test API"}}';
    
    $resolved = $parser->parse($json);
    $resolved->extract('/info/version');
})->throws(UnresolvableReferenceException::class);

it('throws parse exception on bad json', function () {
    $parser = new OpenApiSourceParser();
    $parser->parse('bad json');
})->throws(SourceParseException::class);
```

- [ ] **Step 2: Run test to see failure**

Run: `rtk php artisan test --filter OpenApiSourceParserTest`
Expected: FAIL

- [ ] **Step 3: Implement OpenApiResolvedSource and Parser**

```php
// src/Resolution/OpenApiResolvedSource.php
namespace Alama\LaravelArazzo\Resolution;

use cebe\openapi\spec\OpenApi;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

class OpenApiResolvedSource implements ResolvedSource
{
    public function __construct(private OpenApi $openapi) {}

    public function extract(string $jsonPointer): mixed
    {
        $parts = explode('/', trim($jsonPointer, '/'));
        $current = $this->openapi->getSerializableData();
        
        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } elseif (is_object($current) && property_exists($current, $part)) {
                $current = $current->{$part};
            } else {
                throw new UnresolvableReferenceException("Path not found: $jsonPointer");
            }
        }
        
        return $current;
    }
}

// src/Resolution/Parsers/OpenApiSourceParser.php
namespace Alama\LaravelArazzo\Resolution\Parsers;

use cebe\openapi\Reader;
use cebe\openapi\exceptions\TypeErrorException;
use Alama\LaravelArazzo\Resolution\SourceParser;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;

class OpenApiSourceParser implements SourceParser
{
    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $openapi = $isYaml 
                ? Reader::readFromYaml($content) 
                : Reader::readFromJson($content);
                
            return new OpenApiResolvedSource($openapi);
        } catch (\Throwable $e) {
            throw new SourceParseException("Failed to parse OpenAPI document: " . $e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 4: Implement ArazzoResolvedSource and Parser**

```php
// src/Resolution/ArazzoResolvedSource.php
namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

class ArazzoResolvedSource implements ResolvedSource
{
    public function __construct(private ArazzoDocument $document) {}

    public function extract(string $jsonPointer): mixed
    {
        // Simple extraction based on casting DTO to array for JSON pointer traversal
        $data = json_decode(json_encode($this->document), true);
        
        $parts = explode('/', trim($jsonPointer, '/'));
        $current = $data;
        
        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                throw new UnresolvableReferenceException("Path not found: $jsonPointer");
            }
        }
        
        return $current;
    }
}

// src/Resolution/Parsers/ArazzoSourceParser.php
namespace Alama\LaravelArazzo\Resolution\Parsers;

use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Resolution\SourceParser;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\ArazzoResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Throwable;

class ArazzoSourceParser implements SourceParser
{
    public function __construct(private Parser $parser) {}

    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $data = $isYaml 
                ? (new SymfonyYamlDecoder())->decode($content) 
                : (new NativeJsonDecoder())->decode($content);
                
            $raw = new RawDocument($data, '', $isYaml ? Format::Yaml : Format::Json);
            $doc = $this->parser->parse($raw);
            
            return new ArazzoResolvedSource($doc);
        } catch (Throwable $e) {
            throw new SourceParseException("Failed to parse Arazzo document: " . $e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 5: Run tests**

Run: `rtk php artisan test --filter OpenApiSourceParserTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
rtk git add src/Resolution/OpenApiResolvedSource.php src/Resolution/ArazzoResolvedSource.php src/Resolution/Parsers/ tests/Resolution/Parsers/
rtk git commit -m "feat: implement OpenAPI and Arazzo source parsers"
```

---

### Task 4: Implement DefaultSourceResolver

**Files:**
- Create: `src/Resolution/DefaultSourceResolver.php`
- Create: `tests/Resolution/DefaultSourceResolverTest.php`

- [ ] **Step 1: Write test for DefaultSourceResolver**

```php
// tests/Resolution/DefaultSourceResolverTest.php
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Alama\LaravelArazzo\Resolution\SourceParser;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Enum\SourceType;

it('resolves source using appropriate fetcher and parser', function () {
    $fetcher = new class implements SourceFetcher {
        public function fetch(string $url, string $basePath): string { return 'test content'; }
    };
    
    $resolved = new class implements ResolvedSource {
        public function extract(string $jsonPointer): mixed { return 'extracted'; }
    };
    
    $parser = new class($resolved) implements SourceParser {
        public function __construct(public ResolvedSource $resolved) {}
        public function parse(string $content): ResolvedSource { return $this->resolved; }
    };

    $resolver = new DefaultSourceResolver(
        ['http' => $fetcher, 'https' => $fetcher],
        ['file' => $fetcher],
        [SourceType::Openapi->value => $parser]
    );
    
    $source = new SourceDescription('test', 'http://example.com', SourceType::Openapi);
    $result = $resolver->resolve($source, '');
    
    expect($result)->toBe($resolved);
});
```

- [ ] **Step 2: Run test to see failure**

Run: `rtk php artisan test --filter DefaultSourceResolverTest`
Expected: FAIL

- [ ] **Step 3: Implement DefaultSourceResolver**

```php
// src/Resolution/DefaultSourceResolver.php
namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\SourceDescription;

class DefaultSourceResolver implements SourceResolver
{
    /**
     * @param array<string, SourceFetcher> $remoteFetchers
     * @param array<string, SourceFetcher> $localFetchers
     * @param array<string, SourceParser> $parsers
     */
    public function __construct(
        private array $remoteFetchers,
        private array $localFetchers,
        private array $parsers
    ) {}

    public function resolve(SourceDescription $source, string $basePath): ResolvedSource
    {
        $isRemote = str_starts_with($source->url, 'http://') || str_starts_with($source->url, 'https://');
        
        $fetcher = $isRemote ? ($this->remoteFetchers['http'] ?? null) : ($this->localFetchers['file'] ?? null);
        
        if (!$fetcher) {
            throw new \RuntimeException("No appropriate fetcher configured for {$source->url}");
        }
        
        $content = $fetcher->fetch($source->url, $basePath);
        
        $parser = $this->parsers[$source->type->value] ?? null;
        
        if (!$parser) {
            throw new \RuntimeException("No parser configured for source type {$source->type->value}");
        }
        
        return $parser->parse($content);
    }
}
```

- [ ] **Step 4: Run test to verify pass**

Run: `rtk php artisan test --filter DefaultSourceResolverTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk git add src/Resolution/DefaultSourceResolver.php tests/Resolution/DefaultSourceResolverTest.php
rtk git commit -m "feat: implement DefaultSourceResolver orchestrator"
```
