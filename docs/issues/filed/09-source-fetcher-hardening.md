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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Harden source fetchers with local path confinement and HTTP limits (redirects, response size, timeouts, scheme/host policy, auth, mTLS) to close SSRF and local-file-disclosure surfaces when documents or source URLs come from untrusted input.

**Architectural Boundary:** Bounded context = source fetching. Package affected: `packages/sources` (fetchers and `DefaultSourceResolver`). `SourceFetcher` interface unchanged; secure defaults are the default with explicit opt-outs.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Response-size abort avoids buffering unbounded bodies; redirect limit caps round-trips; timeouts bound connection/read duration.
- [ ] **Flexible:** `SourceFetchPolicy` value object is swappable; every limit configurable; secure defaults with opt-out flags.
- [ ] **Maintainable:** Confinement is a local change to `LocalFetcher`; limits surfaced as typed `SourceFetchException` with URL and violated limit; no credentials in messages/logs.
- [ ] **Operable:** Exceptions are typed and actionable; default behaviour change announced as minor-version with opt-outs; rollback by disabling individual limits.
- [ ] **Reliable:** Timeouts and size limits prevent hangs and OOM; redirect limit prevents infinite loops; host policy applied on each redirect hop.
- [ ] **Safe:** Local path confinement (`realpath` + prefix check) prevents directory traversal; symlink escapes rejected; no side effects beyond reading configured sources.
- [ ] **Secure:** SSRF mitigation (scheme allowlist, host allow/deny, loopback/link-local/private denied by default); local-file disclosure mitigation (confinement); optional mTLS and auth; credentials never logged.
- [ ] **Suitable:** Runtime safety improvement; no normative fetch policy in the spec; protects against untrusted documents and source URLs.
- [ ] **Usable:** Secure defaults require no configuration; opt-outs available for trusted environments; documented secure defaults and opt-outs.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `LocalFetcher` rejects `../` traversal escapes**
- **Given** a `LocalFetcher` configured with `localRoot: /app/data` and `allowAbsoluteLocalPaths: false`; a requested path `../../etc/passwd`; `realpath()` is used before the prefix check.
- **When** `LocalFetcher::fetch('../../etc/passwd', '/app/data')` is called.
- **Then** the fetch is rejected; a `SourceFetchException` is thrown with message containing `path traversal` or `outside root`; no file content is returned; the resolved real path is outside `/app/data`.

**Scenario 2: `LocalFetcher` rejects absolute paths outside root by default**
- **Given** a `LocalFetcher` configured with `allowAbsoluteLocalPaths: false`; an absolute path `/etc/passwd`; no opt-in flag is set.
- **When** `LocalFetcher::fetch('/etc/passwd', '/app/data')` is called.
- **Then** the fetch is rejected; a `SourceFetchException` is thrown; no file content is returned.

**Scenario 3: Symlinks that escape the root are rejected**
- **Given** a `LocalFetcher` configured with `localRoot: /app/data`; a symlink `/app/data/link` pointing to `/etc/passwd`; `realpath()` resolves the symlink target.
- **When** `LocalFetcher::fetch('link', '/app/data')` follows the symlink.
- **Then** the fetch is rejected; a `SourceFetchException` is thrown; the realpath of the target (`/etc/passwd`) is outside `/app/data`.

**Scenario 4: `HttpFetcher` enforces redirect limit and aborts on exceeding it**
- **Given** an `HttpFetcher` configured with `maxRedirects: 5`; a redirect chain of 6 HTTP 302 responses; each redirect location is valid `https`.
- **When** `HttpFetcher::fetch()` follows the redirect chain.
- **Then** the fetch is aborted after 5 redirects; a `SourceFetchException` is thrown with message naming the redirect limit (`maxRedirects`); no request is sent beyond the limit.

**Scenario 5: `HttpFetcher` enforces maximum response size without buffering**
- **Given** an `HttpFetcher` configured with `maxBytes: 1048576` (1 MiB); an HTTP response with `Content-Length: 5242880` (5 MiB); the body is streamed.
- **When** `HttpFetcher::fetch()` reads the response body.
- **Then** the fetch is aborted when the byte count exceeds 1 MiB; a `SourceFetchException` is thrown with message naming `maxBytes`; the full 5 MiB body is not buffered in memory.

**Scenario 6: Connect and read timeouts are applied**
- **Given** an `HttpFetcher` configured with `connectTimeout: 10.0` and `readTimeout: 30.0`; a target host that does not respond within 10 seconds.
- **When** `HttpFetcher::fetch()` initiates the HTTP request.
- **Then** a `SourceFetchException` is thrown with message naming the connect timeout; the exception includes the URL and the violated limit (`connectTimeout`); no request hangs indefinitely.

**Scenario 7: Only allowed schemes are fetched; redirect hops re-checked**
- **Given** an `HttpFetcher` configured with `allowedSchemes: ['http', 'https']`; an initial `https://` URL that redirects to `file:///etc/passwd`.
- **When** `HttpFetcher::fetch()` processes the redirect hop.
- **Then** the `file://` scheme is refused on the redirect hop; a `SourceFetchException` is thrown; the redirected request is not made.

**Scenario 8: Host allow/deny policy applied before request and on each redirect**
- **Given** an `HttpFetcher` configured with `deniedHosts: ['127.0.0.1', '::1', '169.254.0.0/16']`; an initial URL `http://127.0.0.1:8080/` or a redirect to `http://169.254.169.254/`.
- **When** `HttpFetcher::fetch()` processes the request or redirect.
- **Then** the host is denied before the request is sent and on each redirect hop; a `SourceFetchException` is thrown; no request is made to the denied host.

**Scenario 9: Optional auth and mTLS supported; credentials never logged**
- **Given** an `HttpFetcher` configured with auth `{ type: 'bearer', token: 'secret-token-123' }` and mTLS `{ cert: '/path/to/client.pem', key: '/path/to/client-key.pem', ca: '/path/to/ca.pem' }` for domain `api.example.com`.
- **When** `HttpFetcher::fetch()` makes a request to `https://api.example.com/`.
- **Then** the bearer token and client certificate are used in the TLS handshake; exception messages and logs never include `secret-token-123`, `/path/to/client.pem`, or `/path/to/client-key.pem`; credentials are never logged.
- **Then** the fetch is rejected

**Scenario 4: `HttpFetcher` enforces redirect limit and aborts on exceeding it**
- **Given** a redirect chain longer than `maxRedirects` (default 5)
- **When** `HttpFetcher` follows redirects
- **Then** the fetch is aborted with `SourceFetchException` naming the redirect limit

**Scenario 5: `HttpFetcher` enforces maximum response size without unbounded buffering**
- **Given** a response body larger than `maxBytes` (default 10 MiB)
- **When** the body is streamed
- **Then** the fetch is aborted without buffering the full body

**Scenario 6: Connect and read timeouts are applied and surfaced as `SourceFetchException`**
- **Given** a host that does not respond within `connectTimeout`/`readTimeout`
- **When** the request is made
- **Then** a `SourceFetchException` is thrown with the URL and the violated limit

**Scenario 7: Only allowed schemes are fetched; redirect hops re-checked**
- **Given** a URL with scheme `file://` or `ftp://`, or a redirect to such a scheme
- **When** `HttpFetcher` processes the request/redirect
- **Then** the scheme is refused

**Scenario 8: Host allow/deny policy applied before request and on each redirect**
- **Given** a denied host (loopback, link-local, private range) or a redirect to one
- **When** the request/redirect is processed
- **Then** the host is denied

**Scenario 9: Optional auth and mTLS supported; credentials never logged**
- **Given** static/basic/bearer auth or mTLS config (cert, key, optional CA)
- **When** the request is made to a configured domain
- **Then** credentials/certificates are used; exception messages and logs never include them

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `LocalFetcher` canonicalizes resolved path (`realpath`) and requires it to stay inside the configured base directory
- [ ] Reject `..` traversal and absolute paths by default; allow only through explicit opt-in flag
- [ ] Optionally support file allowlist/denylist by real path
- [ ] `SourceFetcher` interface unchanged
- [ ] `HttpFetcher` restricts schemes to `http` and `https`
- [ ] `HttpFetcher` adds configurable maximum redirect count (default 5), re-validating scheme/host on each hop
- [ ] `HttpFetcher` adds maximum response size (default 10 MiB), aborting larger bodies during streaming
- [ ] `HttpFetcher` adds connect/read timeouts (default 10s/30s)
- [ ] `HttpFetcher` adds optional host allowlist/denylist; deny loopback/link-local/private by default unless explicitly allowed
- [ ] `HttpFetcher` adds optional auth: static headers, basic auth, bearer token
- [ ] `HttpFetcher` adds optional mTLS: client certificate + private key + CA bundle
- [ ] Every limit surfaced as typed `SourceFetchException` with URL and violated limit; no credentials included
- [ ] Tests: traversal, absolute path, symlink escape, too many redirects, oversized body, timeout, disallowed scheme, denied host, auth header, mTLS config passthrough
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Error logging/audit trails instrumented (no credentials in logs)
- [ ] Documentation describes secure defaults and opt-outs


### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive configuration; secure defaults may break callers that relied on absolute local paths or unrestricted hosts. Announce as a minor-version behaviour change with opt-outs.
- **Blast radius:** `packages/sources` and every caller that resolves sources (runner preflight, CLI, Laravel wiring).
- **Severity:** high — SSRF and local-file disclosure.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| LocalFetcher allows traversal/absolute paths | T1 | `LocalFetcher.php:15-28` | direct read |
| HttpFetcher has no redirect/size/timeout/host policy | T1 | `HttpFetcher.php:23-56` | direct read |
| The resolver dispatches by scheme | T1 | `DefaultSourceResolver.php:58-96` | direct read |
| The fetchers are used by preflight/CLI | T1 | `PreflightValidator`, `ValidateCommand` (draft 08) | direct read |
| Secure defaults can be added without changing the interface | T2 | `SourceFetcher::fetch(string,string): string` is stable | direct read |
