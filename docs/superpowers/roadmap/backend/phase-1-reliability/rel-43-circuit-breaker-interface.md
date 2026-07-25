# `CircuitBreakerInterface`

Category: **rel** · Phase: **1-reliability** · Tier: **OSS** (interface + in-memory) + **Pro** (shared/Redis-backed)
Related: `rel-41-retry-policy-interface`, `rel-06-sla-monitors-dlq`

## Problem

Retry policies (`rel-41`) protect one execution; they don't protect downstream systems from
a stampede. If Stripe is degraded, every workflow retrying against it turns our engine into
a DoS amplifier. A circuit breaker per downstream host trips after N failures/window and
fails fast for the cooldown period, giving the downstream time to recover.

## Feature

```php
interface CircuitBreakerInterface
{
    public function state(string $resource): CircuitState;  // Closed|Open|HalfOpen
    public function attempt(string $resource): void;         // throws CircuitOpenException if Open
    public function recordSuccess(string $resource): void;
    public function recordFailure(string $resource, \Throwable $cause): void;
}

enum CircuitState { case Closed; case Open; case HalfOpen; }
```

Core ships:
- `NullCircuitBreaker` — always Closed, current behavior.
- `InMemoryCircuitBreaker(failureThreshold, windowSeconds, cooldownSeconds, halfOpenProbes)` —
  process-local, sliding window counter.

Pro (`arazzo-pro-persistence`) ships:
- `RedisCircuitBreaker` — cluster-shared, atomic INCR-with-expiry, exposes state via
  observability API for dashboards.

`HttpStepExecutor` calls `$breaker->attempt($host)` before dispatch. On `CircuitOpenException`,
`StepOutcomeHandler` marks the step failed with reason `circuit-open` — feeds into `rel-06`
SLA/DLQ routing.

Resource identifier convention: host+path prefix (e.g. `api.stripe.com/v1/charges`) rather
than per-URL — prevents cardinality explosion.

## Acceptance

- Fixture: 5 consecutive 500s from a mock server within window → 6th request short-circuits
  (never dispatched), event ledger emits `CircuitOpened`.
- Cooldown elapses → single half-open probe dispatched → success closes → normal traffic.
- Half-open probe failure → circuit re-opens for another cooldown.
- Overhead when closed: < 1μs per attempt (in-memory impl benchmark).

## Out of scope

- Bulkhead pattern (per-tenant concurrency caps) — separate concern, likely `arazzo-pro-multitenancy`.
- Adaptive thresholds — v2.
