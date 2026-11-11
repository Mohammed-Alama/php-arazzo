# Draft issue 09 — Harden source retrieval (confinement, limits, auth)

- **Suggested title:** `[Feature] Harden source fetchers: local path confinement, HTTP limits, auth and mTLS`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #23 Named Source Resolution + OpenAPI, or a new sources epic)
- **Packages:** `sources`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/sources` fetchers and `DefaultSourceResolver`.
- **Arazzo spec version:** independent of spec version; applies to every source fetch.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`.
- **Priority:** high when documents or source URLs come from untrusted input; security-relevant.

### 📝 2. Problem Statement & Intent

The default fetchers are deliberately minimal and have no security or robustness boundary:

- **LocalFetcher** joins `basePath` and the requested path with no canonicalisation, no confinement, and no `..` rejection. A document can read `../../etc/passwd` or any absolute path the process can read.
- **HttpFetcher** sends the request through an injected PSR-18 client with no redirect limit, no response-size limit, no connect/read timeout, no scheme allowlist, no host allowlist/denylist, no authentication, and no client-certificate (mTLS) support.

`DefaultSourceResolver` dispatches by URL scheme and otherwise trusts the URL. This is acceptable for trusted local development, but it becomes an SSRF and local-file-disclosure surface as soon as an Arazzo document is accepted from a user, a repository, or a remote feed.

### 🎯 3. Feature Scope & Prior Art

**Goals — local fetching**

- Canonicalise the resolved path (`realpath`) and require it to stay inside the configured base directory.
- Reject `..` traversal and absolute paths by default; allow them only through an explicit opt-in flag.
- Optionally support a file allowlist/denylist by real path.
- Keep the existing `SourceFetcher` interface unchanged.

**Goals — HTTP fetching**

- Restrict schemes to `http` and `https`.
- Add a configurable maximum redirect count (default: 5) and re-validate the scheme/host on each hop.
- Add a maximum response size (default: e.g. 10 MiB) and abort larger bodies.
- Add connect/read timeouts (default: e.g. 10 s / 30 s).
- Add optional host allowlist/denylist; deny loopback, link-local and private ranges by default unless explicitly allowed.
- Add optional auth: static headers, basic auth, bearer token.
- Add optional mTLS: client certificate + private key + CA bundle, passed to the PSR-18 client adapter.
- Surface every limit as a typed `SourceFetchException` with the URL and the violated limit; never include credentials.

**Non-goals**

- OAuth flows, token refresh, proxy auto-discovery.
- Replacing the caller-supplied PSR-18 client.
- Changing the Arazzo document model.

**Prior art in this repository**

- `SourceFetchException` already carries fetch failures.
- `HttpFetcher` already accepts a PSR-18 client and request factory.
- `LocalFetcher` is small enough to make confinement a local change.

**Proposed configuration shape (illustrative)**

```php
final readonly class SourceFetchPolicy
{
    public function __construct(
        public bool $allowAbsoluteLocalPaths = false,
        public ?string $localRoot = null,
        public int $maxRedirects = 5,
        public int $maxBytes = 10_485_760,
        public float $connectTimeout = 10.0,
        public float $readTimeout = 30.0,
        public array $allowedSchemes = ['http', 'https'],
        public array $allowedHosts = [],
        public array $deniedHosts = ['127.0.0.1', '::1', '169.254.0.0/16'],
        public ?array $auth = null,
        public ?array $tls = null,
    ) {}
}
```

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `LocalFetcher` rejects `../` escapes and, by default, absolute paths outside the configured root; `realpath` is used before the prefix check.
- [ ] Symlinks that escape the root are rejected.
- [ ] The local policy is configurable; the secure behaviour is the default.
- [ ] `HttpFetcher` enforces the redirect limit and aborts on exceeding it.
- [ ] `HttpFetcher` enforces the maximum response size without buffering an unbounded body.
- [ ] Connect and read timeouts are applied and surfaced as `SourceFetchException`.
- [ ] Only allowed schemes are fetched; redirect hops are re-checked.
- [ ] Host allow/deny policy is applied before the request and on each redirect.
- [ ] Optional static/basic/bearer auth and mTLS are supported through the injected client adapter.
- [ ] Credentials and certificate paths are never included in exception messages or logs.
- [ ] Tests use local fixtures and a fake PSR-18 client; cover traversal, absolute path, symlink escape, too many redirects, oversized body, timeout, disallowed scheme, denied host, auth header, mTLS config passthrough.
- [ ] Documentation describes secure defaults and the opt-outs.

### 🧬 7. Source Grounding & Code Location

- `packages/sources/src/Resolver/Fetchers/LocalFetcher.php:15-28` — path join with no confinement.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/sources/src/Resolver/Fetchers/LocalFetcher.php#L15-L28"><code>LocalFetcher.php:15-28</code></a> @ <code>ea8c55a</code></summary>

```php
public function fetch(string $urlOrPath, string $basePath): string
{
    $path = $this->isAbsolute($urlOrPath)
        ? $urlOrPath
        : rtrim($basePath, '/\\').'/'.ltrim($urlOrPath, '/\\');

    $content = is_file($path) ? @file_get_contents($path) : false;

    if ($content === false) {
        throw new SourceFetchException("Failed to read local file: {$path}");
    }

    return $content;
}
```

</details>

- `packages/sources/src/Resolver/Fetchers/HttpFetcher.php:23-56` — request, status check, redirect-free body read.
- `packages/sources/src/Resolver/DefaultSourceResolver.php:58-96` — scheme dispatch.
- Spec: no normative fetch policy; this is a runtime safety improvement.

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive configuration; secure defaults may break callers that relied on absolute local paths or unrestricted hosts. Announce as a minor-version behaviour change with opt-outs.
- **Blast radius:** `packages/sources` and every caller that resolves sources (runner preflight, CLI, Laravel wiring).
- **Severity:** high — SSRF and local-file disclosure.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| LocalFetcher allows traversal/absolute paths | T1 | `LocalFetcher.php:15-28` | direct read |
| HttpFetcher has no redirect/size/timeout/host policy | T1 | `HttpFetcher.php:23-56` | direct read |
| The resolver dispatches by scheme | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| The fetchers are used by preflight/CLI | T1 | `PreflightValidator`, `ValidateCommand` (draft 08) | direct read |
| Secure defaults can be added without changing the interface | T2 | `SourceFetcher::fetch(string,string): string` is stable | direct read |
