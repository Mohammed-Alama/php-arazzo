# Conformance & Parity Tests

## Golden fixtures (`fixtures/*.json`)

Each fixture is a self-contained scenario executed against the real
pipeline (Parser → SourceRegistry → OpenApiOperationResolver →
StepExecutor/HttpStepExecutor → WorkflowEngine) with a scripted fake
HTTP transport.

Fixture keys:

| Key | Meaning |
| --- | --- |
| `name` | Fixture label (used in test names) |
| `arazzo` | Raw Arazzo document (array, `arazzo: 1.0.1`) |
| `sources` | Map of source name → inline OpenAPI document; seeded into `SourceRegistry` so no network/file access happens |
| `responses` | Scripted HTTP responses replayed FIFO: `{status, headers?, body}` |
| `inputs` | Workflow inputs for the first workflow |
| `expect` | Normalized observations: `status`, `steps` (`{stepId, status, attempts}`), `requests`, `outputs`, optional `errors`, `retries`, `requestHeaders`, `eventsContain` |

## Adapters under test

- **Synchronous** — `FixtureRunner` drives `WorkflowExecutor` in-process.
- **Queued** — `QueueFixtureRunner` dispatches an initial `ExecuteStepJob`
  into a recording queue and drains it through `StepExecutionWorker`
  until the run terminates.

Both adapters are normalized by `ConformanceHarness::observe()` from the
recorded event stream + HTTP traffic. `tests/Conformance/FixtureTest.php`
asserts each fixture's expectations AND sync↔queue parity for
`status`, `steps`, `requests`, and `outputs`.

Adding a fixture is enough to extend both suites automatically.

## Mutation targets

The execution invariants pinned here (and recommended mutation-testing
targets) are:

1. Action selection — `WorkflowEngine::transition()` success/failure action dispatch.
2. Retry exhaustion — ceiling enforcement (`maxRetryAttempts`, `retryLimit`) and `retry_exhausted` error entries.
3. Dependency ordering — `DependencyGraph::getEffectiveDependencies()` / implicit dependencies.
4. Workflow outputs — terminal evaluation in both adapters.
5. Error classification — `StepResult` errors and ledger event types.

Once `infection` is added to the dev dependencies, run focused mutation
checks with:

```bash
cd packages/core
vendor/bin/infection \
  --coverage=build/coverage \
  --only-covered \
  --mutators="TrueValue,FalseValue,IdenticalEqual,LessThan,DecrementInteger,IncrementInteger" \
  --filter="(WorkflowEngine|WorkflowExecutor|StepExecutionWorker|StepOutcomeHandler|ExpressionValueResolver|DependencyGraph)"
```

Record the MSI result alongside release notes when touching runner code.
