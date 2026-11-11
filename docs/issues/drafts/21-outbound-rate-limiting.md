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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `requestsPerSecond` and `burst` are enforced with a token bucket.
- [ ] `maxConcurrent` caps in-flight requests across the run.
- [ ] Retries count against the limit and honour `Retry-After`.
- [ ] A throttled request waits by default and records `transport.throttled` with the wait duration.
- [ ] `failFast` produces `transport.rate_limited` without sending.
- [ ] The limiter is per host by default and per run when configured.
- [ ] The effective concurrency with `--parallel` is `min(scheduler, maxConcurrent)`.
- [ ] No request leaves the process without acquiring a slot.
- [ ] Tests use a fake clock and a fake transport; cover burst, steady-state, concurrency, retry interaction, fail-fast and both scopes.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive policy and CLI flags; unlimited by default, so existing behaviour is preserved.
- **Blast radius:** HTTP execution only; no state or event schema change beyond new warnings.
- **Severity:** medium — protects external providers and internal capacity.
- **Rollback:** disable the policy; defaults are no-op.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `Budget` counts steps, not requests | T1 | `Budget.php:13-21` | direct read |
| `Retry-After` is already parsed | T1 | `RetryPolicy.php:25-51` | direct read |
| No rate or concurrency limiter exists | T1 | `grep -rIn -i 'rate.limit\|concurren\|throttl' packages/protocol-http/src packages/runtime/src` → no matches | executed |
| Retries can amplify request volume | T2 | retry loop re-executes the step | inferred |
| A token bucket at the transport boundary is sufficient | T2 | all outbound HTTP flows through the executor | inferred |
