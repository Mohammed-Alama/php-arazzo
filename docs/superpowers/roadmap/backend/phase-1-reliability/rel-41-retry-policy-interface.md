# `RetryPolicyInterface`

Category: **rel** · Phase: **1-reliability** · Tier: **OSS** (interface + defaults) + **Pro** (advanced strategies)
Depends on: shipped `StepOutcomeHandler`

## Problem

`StepOutcomeHandler` hardcodes retry logic (constant sleep on `retry` action). Pro-persistence
+ SLA/DLQ workflows (`rel-06`) need pluggable strategies: exponential backoff, decorrelated
jitter (AWS recommendation), retry budget (fail closed after cluster-wide N failures/min),
per-error-class policies (network vs. 5xx vs. 4xx-idempotent).

## Feature

```php
interface RetryPolicyInterface
{
    public function shouldRetry(RetryContext $ctx): RetryDecision;
}

final readonly class RetryContext
{
    public int $attempt;                     // 1-based
    public \Throwable|StepFailure $failure;
    public int $elapsedMs;
    public array $stepConfig;                // parsed x-retry-* fields
}

final readonly class RetryDecision
{
    public bool $retry;
    public int $delayMs;
    public ?string $reason;                  // for event ledger
}
```

Core ships:
- `NoRetryPolicy` — never retries.
- `FixedDelayRetryPolicy(maxAttempts, delayMs)` — matches current hardcoded behavior.
- `ExponentialBackoffPolicy(maxAttempts, baseMs, capMs, jitter=full|equal|none)`.

Pro (`arazzo-pro-persistence`) adds:
- `DecorrelatedJitterPolicy` — AWS-recommended.
- `RetryBudgetPolicy` — fails closed after cluster budget exhausted (needs shared counter, hence pro).
- `ErrorClassifierPolicy` — routes by exception type.

Wire via `StepOutcomeHandler` constructor. Per-step override via `x-retry-policy` field on
Step DTO.

## Acceptance

- Existing retry behavior unchanged when default `FixedDelayRetryPolicy` bound.
- Jitter strategies produce distribution matching mathematical spec (statistical test).
- Policy is a pure function of `RetryContext` — no side effects, no I/O in `shouldRetry`.
- New Step DTO fields (`x-retry-policy`, `x-retry-max-attempts`, `x-retry-base-ms`) parse
  cleanly and pass validator.

## Out of scope

- Circuit-breaker interaction — see `rel-43`.
- Manual intervention (operator-triggered replay) — `obs-18-retry-controls`.
