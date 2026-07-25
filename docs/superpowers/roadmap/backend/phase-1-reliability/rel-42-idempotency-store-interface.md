# `IdempotencyStoreInterface`

Category: **rel** · Phase: **1-reliability** · Tier: **OSS** (interface) + **Pro** (Redis-backed impl)
Depends on: shipped `IdempotencyKeyInjector`

## Problem

Shipped idempotency = HTTP header injection only. Downstream API dedups; our engine has no
memory of its own past requests. Two failure modes remain:

1. Network fault between our engine and downstream after downstream committed but before we
   recorded success → we retry, downstream dedups, but we cannot distinguish "already ran"
   from "still running" — we may re-execute local side effects (compensation, output
   extraction) against stale data.
2. Multi-worker replay (queue restart, blue-green deploy) may run the same step twice with
   the same idempotency key but different local WorkflowContext views.

Solution: an engine-side idempotency ledger that records `(idempotencyKey → StepResult)`
tuples with TTL. Any step whose key hits an unexpired record short-circuits to the recorded
result without dispatching.

## Feature

```php
interface IdempotencyStoreInterface
{
    /** @return StepResult|null */
    public function get(string $idempotencyKey): ?StepResult;
    public function put(string $idempotencyKey, StepResult $result, int $ttlSeconds): void;
    public function forget(string $idempotencyKey): void;
}
```

Core ships:
- `NullIdempotencyStore` — no memory, current behavior.
- `InMemoryIdempotencyStore` — process-local, fine for tests + single-worker deployments.

Pro (`arazzo-pro-persistence`) ships:
- `RedisIdempotencyStore` — cluster-wide, TTL-based, SETNX for atomic put-if-absent.

`HttpStepExecutor` + `StepExecutor` sync path both check store before dispatching, both
write on success. Failures do not populate the store (retry semantics preserved).

## Acceptance

- Fixture: run a step twice with the same input in the same process → second run skipped
  (record hit), event ledger emits `IdempotencyHit` event.
- Cross-worker fixture (with Redis-backed impl in pro tests) → identical short-circuit.
- Store never populated on failure — retry still works.
- TTL respected: after TTL elapses, next run dispatches fresh.

## Out of scope

- Distributed lock for concurrent identical requests — belongs to `LockManagerInterface`.
- Custom key derivation — `IdempotencyKeyInjector` already owns that (shipped).
