# `MetricsRecorderInterface`

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Enables: pro-observability, bridge-28 (Horizon/Telescope), health-22 (blast radius), perf-25 (waterfall)

## Problem

No metrics contract exists. Pro-observability, waterfall profiler, blast-radius heatmap,
Horizon/Telescope bridge all need duration + counter + gauge telemetry. Without a core
interface, each grows its own hook or scrapes events (expensive, brittle).

## Feature

```php
interface MetricsRecorderInterface
{
    public function counter(string $name, int $delta = 1, array $tags = []): void;
    public function gauge(string $name, float $value, array $tags = []): void;
    public function histogram(string $name, float $value, array $tags = []): void;
    public function timer(string $name, array $tags = []): TimerHandle;
}

interface TimerHandle
{
    public function stop(): void;   // records elapsed on stop
}
```

Default binding: `NullMetricsRecorder` (all no-ops, zero overhead).

Core call sites emit under a fixed namespace `arazzo.*`:
- `arazzo.run.started` / `.completed` / `.failed` (counter)
- `arazzo.step.duration` (timer)
- `arazzo.step.retries` (counter with `step_id` tag)
- `arazzo.step.http.status` (counter with `status_code` tag)
- `arazzo.hot_state.reads` / `.writes` (counter)
- `arazzo.event_ledger.appends` (counter)

Pro-observability binds an OpenTelemetry-backed impl. Bridge-28 binds a Horizon/Telescope
adapter. Users can bind Prometheus/StatsD/Datadog impls without core knowing.

## Acceptance

- Core call-site instrumentation adds < 1μs overhead when `NullMetricsRecorder` is bound
  (benchmark included).
- All namespaced metrics enumerated + documented under `docs/metrics.md`.
- Playwright/perf tests confirm bridge-28 populates Horizon's job payload with
  `arazzo.step.duration`.

## Out of scope

- Metric name migrations — v1; freeze names + document.
- Distributed tracing (spans) — separate interface if we ever need it.
