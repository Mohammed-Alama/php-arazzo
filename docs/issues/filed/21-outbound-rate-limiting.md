# Draft issue 21 — Rate limiting and concurrency caps for outbound execution

- **Suggested title:** `[Feature] Rate-limit and cap concurrency for outbound workflow requests`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #24 Transport Failure Handling)
- **Packages:** `protocol-http`, `runner`, `runtime`, `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/protocol-http` transport; `packages/runner` executor; `packages/runtime` policy/state; `packages/cli` flags.
- **Arazzo spec version:** independent of spec version.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core plus CLI options.
- **Kind / level:** `kind:feature`, `level:story`, under #24 (Transport Failure Handling).
- **Priority:** P2 — protects providers and keeps retries from becoming a self-inflicted flood.

### 📝 2. Problem Statement & Intent

The runner can execute steps in a loop and retry them, but it has no notion of
how many requests leave the process per second or at once. `Budget` counts
**steps**, not **requests** (`packages/runtime/src/State/Data/Budget.php:13-21`).
`RetryPolicy` can honour `Retry-After`, but a workflow with many steps, a
parallel run (draft 17) or a retry storm can still exceed a provider's limit and
turn a recoverable `429` into an outage.

`#108` adds per-step and per-workflow **step** budgets. That is a different
axis: a workflow can stay under its step budget while issuing hundreds of
requests per second.

Expected: an opt-in, configurable outbound request policy with a token-bucket
rate limit and an in-flight concurrency cap, applied before each request and
respected by retries.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `OutboundRequestPolicy`:
  - `requestsPerSecond` (default: unlimited; opt-in)
  - `burst` (default 1)
  - `maxConcurrent` (default 4)
  - `respectRetryAfter` (default true)
  - `scope` — per workflow run or per host (default: per host)
- Apply the limiter immediately before transport and release the slot after the
  response or failure.
- Retries count against the same budget; a `Retry-After` value delays the next
  attempt.
- When a limit is hit, wait rather than fail by default; `failFast` makes it a
  typed error (`transport.rate_limited`).
- Emit stable warnings/events for throttling (`transport.throttled`) with the
  wait duration.
- CLI flags: `--rate-limit <n/s>`, `--rate-burst <n>`, `--max-concurrency <n>`,
  `--rate-limit-scope <host|run>`, `--rate-limit-fail-fast`.
- The concurrency cap must cooperate with draft 17's worker pool; the effective
  concurrency is `min(schedulerConcurrency, maxConcurrent)`.

**Non-goals**

- Distributed rate limiting across processes or hosts.
- Provider-specific quota discovery.
- Step budgets (already #108).
- Response-size limits and redirect policy (draft 20).

**Prior art in this repository**

- `RetryPolicy` already parses `Retry-After` and computes backoff
  (`packages/runtime/src/Policy/RetryPolicy.php:25-51`).
- `Budget` is the existing policy value-object pattern.
- `SyncQueueDriver` and `CliRunner` already drain discrete jobs, so a limiter
  can sit at the transport boundary without touching state semantics.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Add an opt-in, configurable outbound request policy with a token-bucket rate limit and in-flight concurrency cap, applied before each request and respected by retries, to protect providers and prevent retry storms from becoming self-inflicted floods.

**Architectural Boundary:** Bounded context = HTTP execution + transport policy. Packages: `packages/protocol-http` (transport), `packages/runner` (executor), `packages/runtime` (policy/state), `packages/cli` (flags). Core policy is internal; no distributed rate limiting.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Token bucket enforces `requestsPerSecond` and `burst`; `maxConcurrent` caps in-flight requests; no request leaves without acquiring a slot.
- [ ] **Flexible:** `OutboundRequestPolicy` value object with configurable fields; per-host or per-run scope; `failFast` toggle; cooperates with draft 17's worker pool (`min(schedulerConcurrency, maxConcurrent)`).
- [ ] **Maintainable:** Single-responsibility limiter at the transport boundary; stable warnings (`transport.throttled`); typed error (`transport.rate_limited`).
- [ ] **Operable:** Unlimited by default (existing behaviour preserved); configurable via CLI flags; rollback by disabling the policy (defaults are no-op).
- [ ] **Reliable:** Retries count against the limit and honour `Retry-After`; throttled requests wait by default; fail-fast produces typed error without sending.
- [ ] **Safe:** No state mutation; limits are enforced before transport; no side effects beyond wait/error.
- [ ] **Secure:** Protects external providers from request floods; no authorization impact.
- [ ] **Suitable:** Addresses the gap where `Budget` counts steps, not requests; `RetryPolicy` handles `Retry-After` but no rate limit exists.
- [ ] **Usable:** CLI flags: `--rate-limit`, `--rate-burst`, `--max-concurrency`, `--rate-limit-scope`, `--rate-limit-fail-fast`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Token bucket enforces `requestsPerSecond` and `burst`**
- **Given** an `OutboundRequestPolicy` configured with `requestsPerSecond: 10` and `burst: 1`; a token bucket is initialized with 1 token and refills at 10 tokens/second; 15 requests are attempted within one second; `OutboundRequestPolicy` is wired into the transport boundary of `DefaultOpenApiExecutor`.
- **When** 15 HTTP requests are attempted in rapid succession (within one second).
- **Then** 4 requests proceed immediately (consuming the burst of 1 plus initial tokens); 11 requests are throttled (wait or fail-fast); `transport.throttled` warnings are recorded with wait durations; no more than `requestsPerSecond` requests leave the process per second on average.

**Scenario 2: `maxConcurrent` caps in-flight requests**
- **Given** `OutboundRequestPolicy` configured with `maxConcurrent: 4`; a semaphore is initialized with 4 permits; 10 independent steps execute in parallel via the DAG scheduler; each step acquires a semaphore permit before sending and releases it after response/failure.
- **When** 10 independent steps execute concurrently.
- **Then** no more than 4 requests are in-flight at any time; the semaphore enforces the cap; steps 5-10 wait for a permit before sending.

**Scenario 3: Retries count against the limit and honour `Retry-After`**
- **Given** a step that receives HTTP 429 with `Retry-After: 5` header; `RetryPolicy` (existing) parses `Retry-After`; `OutboundRequestPolicy` counts retries against the same token bucket budget; `respectRetryAfter: true` (default).
- **When** the retry is attempted after a 429 response.
- **Then** the retry counts against the token bucket budget; the retry is delayed by 5 seconds (honouring `Retry-After`); `transport.throttled` is recorded with the wait duration; the retry does not bypass the rate limit.

**Scenario 4: Throttled request waits by default**
- **Given** the rate limit is hit (token bucket is empty); `failFast` is not set (default); `OutboundRequestPolicy` is configured to wait rather than fail by default.
- **When** a request would exceed the rate limit.
- **Then** the request waits for tokens to refill; `transport.throttled` is recorded with the exact wait duration; the request proceeds once a token is available; no error is returned.

**Scenario 5: `failFast` produces `transport.rate_limited` without sending**
- **Given** `failFast` enabled in `OutboundRequestPolicy`; the rate limit is hit (token bucket empty, no tokens available).
- **When** a request would exceed the limit.
- **Then** `transport.rate_limited` error is returned immediately; no request is sent; `ErrorCollector` records the error under `errors`; the command exits non-zero for that step.

**Scenario 6: Limiter is per host by default and per run when configured**
- **Given** `OutboundRequestPolicy` with `scope: perHost` (default) vs `scope: perRun`; requests are made to `api.example.com` and `auth.example.com` concurrently.
- **When** requests are made to different hosts.
- **Then** per-host scope: each host has its own token bucket (limits applied independently per host); per-run scope: a single shared token bucket across all hosts; the scope is respected in the limiter key.

**Scenario 7: Effective concurrency with `--parallel` is `min(scheduler, maxConcurrent)`**
- **Given** `--parallel` is used with DAG scheduler concurrency of 8; `OutboundRequestPolicy` `maxConcurrent` is 4; both limits are active.
- **When** 8 independent steps execute in parallel.
- **Then** no more than 4 requests are in-flight at any time (`min(8, 4) = 4`); the effective concurrency cap is the minimum of scheduler concurrency and policy maxConcurrent; `SyncQueueDriver` enforces the combined cap.

**Scenario 8: No request leaves the process without acquiring a slot**
- **Given** `OutboundRequestPolicy` is active at the transport boundary; every outbound HTTP request must acquire a semaphore permit before `DefaultOpenApiExecutor` calls `$httpClient->sendRequest()`.
- **When** any step attempts to send an HTTP request.
- **Then** the permit is acquired before the request leaves the process; if no permit is available, the request waits or fails-fast; no request bypasses the limiter; `SyncQueueDriver` enforces slot acquisition at the job drain level.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `requestsPerSecond` and `burst` enforced with a token bucket
- [ ] `maxConcurrent` caps in-flight requests across the run
- [ ] Retries count against the limit and honour `Retry-After`
- [ ] Throttled request waits by default and records `transport.throttled` with wait duration
- [ ] `failFast` produces `transport.rate_limited` without sending
- [ ] Limiter is per host by default and per run when configured
- [ ] Effective concurrency with `--parallel` is `min(schedulerConcurrency, maxConcurrent)`
- [ ] No request leaves the process without acquiring a slot
- [ ] Tests use a fake clock and fake transport; cover burst, steady-state, concurrency, retry interaction, fail-fast and both scopes
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Warning/error logging instrumented via `ErrorCollector`
- [ ] CLI flags implemented: `--rate-limit`, `--rate-burst`, `--max-concurrency`, `--rate-limit-scope`, `--rate-limit-fail-fast`

### 🧬 10. Source Grounding & Code Location

- `packages/runtime/src/Policy/RetryPolicy.php:25-51` — existing `Retry-After` handling.
- `packages/runtime/src/State/Data/Budget.php:13-21` — step budget only.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:101-149` — transport boundary; no limiter.
- `packages/runner/src/Execution/SyncQueueDriver.php` — job drain where a slot could be acquired.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/runtime/src/State/Data/Budget.php#L13-L31"><code>Budget.php:13-31</code></a> @ <code>ea8c55a</code></summary>

```php
public function __construct(
    public int $maxSteps,
    public int $stepsSpent,
    public int $maxWorkflowDepth,
    public array $workflowCallStack,
) {}

public function remainingSteps(): int
{
    return max(0, $this->maxSteps - $this->stepsSpent);
}
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive policy and CLI flags; unlimited by default, so existing behaviour is preserved.
- **Blast radius:** HTTP execution only; no state or event schema change beyond new warnings.
- **Severity:** medium — protects external providers and internal capacity.
- **Rollback:** disable the policy; defaults are no-op.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `Budget` counts steps, not requests | T1 | `Budget.php:13-21` | direct read |
| `Retry-After` is already parsed | T1 | `RetryPolicy.php:25-51` | direct read |
| No rate or concurrency limiter exists | T1 | `grep -rIn -i 'rate.limit\|concurren\|throttl' packages/protocol-http/src packages/runtime/src` → no matches | executed |
| Retries can amplify request volume | T2 | retry loop re-executes the step | inferred |
| A token bucket at the transport boundary is sufficient | T2 | all outbound HTTP flows through the executor | inferred |
