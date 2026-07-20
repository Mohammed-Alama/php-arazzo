# CQRS & Event-Sourced Persistence — Design

Roadmap seed: [docs/superpowers/roadmap/02-cqrs-event-sourced-persistence.md](../roadmap/02-cqrs-event-sourced-persistence.md).

## Starting point: this is not greenfield, but it is non-functional

Three scaffolds already exist (see `CHANGELOG.md`, "Added — not yet wired into the runtime"):
`RedisHotStateStore`, `DatabaseEventLedger`, `InMemoryDefinitionRegistry`. None are bound in
`LaravelArazzoServiceProvider`. There is no migration for `arazzo_events`. Worse than "unwired,"
though: `EventLedgerInterface::append` has **zero callers anywhere** — the one call site in
`StepExecutionWorker` is commented out — and `Engine` holds a `StateStoreInterface` it never
calls. This item makes persistence functionally real, not just bindable: wiring, migration, a
real definition registry, and the call sites that actually invoke all three.

## Scope

**In scope:**
- Bind all three interfaces in `LaravelArazzoServiceProvider`.
- A migration for `arazzo_definitions`, `arazzo_executions`, and `arazzo_events` — portable base
  tables (any driver) plus a Postgres-only `PARTITION BY RANGE (created_at)` step on
  `arazzo_events`, applied conditionally on `DB::connection()->getDriverName() === 'pgsql'`.
- A real, persistent `DefinitionRegistryInterface` implementation (`DatabaseDefinitionRegistry`),
  replacing `InMemoryDefinitionRegistry` as the bound implementation.
- Splitting the ID model: `definitionId` (document version), `workflowId` (which workflow inside
  that document), `executionId` (which run) — three IDs, each honest about what it identifies,
  replacing today's single `WorkflowContext::$definitionId` doing all three jobs.
- Fixing `StepExecutionWorker`'s call sites: real `stateStore->save()` keyed by `executionId` (not
  today's `definitionId`), real `eventLedger->append()` (uncommented, repointed), real
  `definitionRegistry->get()` returning a hydrated `ArazzoDocument` instead of a
  `uniqid()`-keyed in-memory `Workflow`.
- TTL on hot state: `StateStoreInterface::save()` gains an optional TTL param, refreshed on every
  write, default from `config('arazzo.hot_state_ttl')`.

**Out of scope (explicitly deferred):**
- `Engine::evaluate()` reloading persisted state before computing runnable steps, to fix the
  named double-dispatch/diamond-DAG gap — [03 — Native Async Control Flow](../roadmap/03-native-async-control-flow.md)'s
  stub explicitly claims this fix. This item only ensures `StateStoreInterface` is bound (it
  wasn't before), so 03 has something real to call; it does not add the reload/merge logic
  itself.
- `arazzo_executions.status` (running/completed/failed) — `Engine::evaluate()` has an explicit
  unfinished TODO for completion detection (`// Workflow complete or waiting. We will handle
  completion logic later.`). Without real completion detection, a status column would just get
  stuck on `running` forever — worse than not having it. The row's existence marks "this run
  started"; that's all this item needs. Status tracking is a follow-up once completion detection
  exists.
- Retry/circuit-breaking around Redis or DB unavailability —
  [06 — SLA Monitors & Dead Letter Workflows](../roadmap/06-sla-monitors-dead-letter-workflows.md)'s
  job.
- Versioned migration of the `arazzo_definitions.definition` JSON shape if the `ArazzoDocument`
  DTO tree gains fields later — noted as a known future concern, not solved now.

## Architecture

### ID model

- **`definitionId`** (ULID) — identifies one registered *document version*. Stable across every
  run of every workflow inside that document. Returned by `register()`.
- **`workflowId`** — the spec-level `workflowId` string identifying which workflow inside the
  document a given run executes. Not new to the system (already a `Workflow` field) but new to
  `WorkflowContext`, which didn't previously need to distinguish it from `definitionId`.
- **`executionId`** (ULID) — identifies one *run*. Minted when a workflow starts (not by the
  registry). Becomes the state-store and event-ledger key — both were incorrectly keyed on
  `definitionId` before.

`WorkflowContext` gains `$workflowId` and `$executionId` alongside its existing `$definitionId`.

### Why the registry stores the whole `ArazzoDocument`, not a bare `Workflow`

`Step::operationId` only resolves against the parent document's `sourceDescriptions`; `Reusable`
references (`$components.failureActions.globalFail`) only resolve against the document's
`Components`. A registry that returns a bare `Workflow` would be durable but functionally
crippled — `ArazzoExpressionResolver::compileRequest($step, $context, $document)` would always
receive `$document = null`, permanently stuck on the literal-URL-only fallback path instead of
real OpenAPI-driven request compilation. Registering the whole document also avoids a consistency
gap the original per-`Workflow` signature had: multiple `register(Workflow)` calls for workflows
that share one document could theoretically content-hash against different fragments and drift
onto inconsistent `sourceDescriptions` snapshots. One document round-trip = one row = one content
hash = one consistent execution context for every workflow inside it.

```php
interface DefinitionRegistryInterface
{
    public function register(ArazzoDocument $document): string; // returns definitionId
    public function get(string $definitionId): ?ArazzoDocument;
}
```

### Tables (new migration)

1. **`arazzo_definitions`** — `id` (ULID PK), `document_identity` (e.g. `info.title`, since a
   document has no `workflowId` of its own), `content_hash` (sha256 of the dehydrated JSON with
   keys sorted recursively before encoding, so semantically-identical documents always hash
   identically regardless of key order), `definition` (JSON — the full `ArazzoDocument`,
   dehydrated), `created_at`. Unique index on `(document_identity, content_hash)` — makes
   `register()` idempotent: re-registering identical content returns the existing row's ID
   instead of inserting a duplicate. `document_identity` uniqueness assumes single-tenant use;
   cross-tenant title collisions are [11 — Multi-Tenancy Isolation](../roadmap/11-multi-tenancy-isolation.md)'s
   concern, not this item's.
2. **`arazzo_executions`** — `id` (ULID PK), `definition_id` (FK), `workflow_id`, `created_at`,
   `updated_at`. No `status` column (see Scope). Exists so `executionId`s have a row of their own
   rather than being orphaned strings.
3. **`arazzo_events`** — `id`, `execution_id` (FK — renamed from the original `workflow_id` param,
   which was always meant to identify one run), `event_type`, `payload` (JSON), `created_at`.
   Plain indexed table on any driver; Postgres-only second migration step adds
   `PARTITION BY RANGE (created_at)`.

### Dehydration

`ArazzoDocument` (and its nested DTOs) need array↔DTO round-tripping to live in
`arazzo_definitions.definition` as JSON. `Parser::parse(RawDocument $raw): ArazzoDocument`
already does array→DTO for a whole document — reuse its private per-node parse methods where
shapes match, rather than writing a second hydrator from scratch. DTO→array is new (recursive
`readonly`-property dump; no library needed).

### Wiring (`LaravelArazzoServiceProvider`)

```php
$this->app->singleton(StateStoreInterface::class, fn ($app) => new RedisHotStateStore(
    $app->make(RedisFactory::class),
    ttl: config('arazzo.hot_state_ttl', 86400),
));
$this->app->singleton(EventLedgerInterface::class, DatabaseEventLedger::class);
$this->app->singleton(DefinitionRegistryInterface::class, DatabaseDefinitionRegistry::class);
```

New `config/arazzo.php` keys: `hot_state_ttl`, `events_table`, `definitions_table`, `executions_table`
(defaults matching current hardcoded table names, now overridable).

### Data flow (`StepExecutionWorker::handle()`)

1. Step executes, `$newContext` built (unchanged).
2. `$this->stateStore->save($executionId, [...snapshot...], ttl: config(...))` — keyed by
   `executionId`, not `definitionId` (today's bug).
3. `$this->eventLedger->append($executionId, 'step.executed', ['stepId' => ..., 'statusCode' =>
   ..., 'outputs' => ...])` — the currently-commented-out line, uncommented and repointed.
4. `$document = $this->definitionRegistry->get($context->getDefinitionId())` — a stable ULID
   looked up in `arazzo_definitions`, not a `uniqid()` string that only ever existed in one
   process's memory. This is the actual fix for "not viable across real queue worker processes."
   `$workflow = $document?->workflows[...]` (matched by `$context->getWorkflowId()`).
5. `$this->engine->evaluate($workflow, $newContext)` — unchanged. `compileRequest`/
   `extractOutputs`/`evaluateSuccessCriteria` now receive a real, non-null `$document` for the
   first time on the async path.

**Not touched:** `Engine`'s `StateStoreInterface` dependency stays unused by this item — binding
it (step above) is what unblocks item 03's double-dispatch fix, not this item building that fix.

## Error Handling

- **`register()` race** — two workers registering the same document simultaneously (cold start
  after a deploy) can't be check-then-insert (TOCTOU). Atomic upsert against the unique
  `(document_identity, content_hash)` index — `insertOrIgnore` + re-select on conflict.
- **`get()` miss** (bad/deleted `definitionId`) — stays `null`; `StepExecutionWorker` still
  no-ops on null, but first appends an `execution.definition_missing` event, so a stalled run
  leaves an audit trace instead of silently vanishing.
- **Hydration failure** (corrupted/unparseable JSON in `definition`) — wrapped in try/catch,
  throws a dedicated `DefinitionHydrationException` rather than a generic `TypeError` leaking
  out. Matches the codebase's existing pattern (`ParserException`,
  `UnsupportedCriterionTypeException`) of typed exceptions over generic ones.
- **Event ledger write failure** — `append()` is an audit trail, not the execution source of
  truth. Catch + log on DB failure, don't fail a step that already succeeded — same
  "best-effort, log don't fail" philosophy the zero-code-pipelining spec already uses for
  schema-cast failures.
- **Redis unavailable** — no retry/circuit-breaker added here (item 06's job). Confirmed no new
  bug to guard against: `LaravelRedisLockManager::acquire` uses `Cache::lock()->block()`, whose
  `finally` already releases the lock regardless of what the callback throws.
- **DTO schema drift** — not solved here (YAGNI); noted as a known future concern.

## Testing

- `DatabaseDefinitionRegistryTest` — idempotent re-register (same content → same ID, no
  duplicate row), concurrent-register race (unique index holds, both callers converge on the
  same ID), hydration round-trip (register → get → same `ArazzoDocument` shape),
  hydration-failure path (`DefinitionHydrationException`).
- `DatabaseEventLedgerTest` (extend existing) — `append()` keyed by `$executionId`, non-fatal
  swallow-and-log on DB failure.
- `RedisHotStateStoreTest` (extend existing) — TTL passed through to `setex`, default pulled
  from `config('arazzo.hot_state_ttl')`.
- Migration test — base tables asserted on sqlite (current CI driver, unchanged); Postgres
  partitioning path behind `if (DB::getDriverName() !== 'pgsql') $this->markTestSkipped(...)`,
  so CI doesn't need a real Postgres instance to stay green.
- `StepExecutionWorkerTest` (extend existing) — full flow: register a document, dispatch a job,
  assert state saved with TTL, event appended, `compileRequest` received a non-null `$document`,
  `engine->evaluate` called with the correctly-hydrated workflow.
- `LaravelArazzoServiceProviderBindingsTest` (extend existing, matches its current pattern) —
  container resolves the three interfaces to the new Laravel-backed classes, not the old
  scaffolds.

## Key decisions (for future reference)

- **Call-site wiring is in scope**, not deferred to item 03 — shipping bindable-but-uncalled
  persistence classes would repeat the exact "unwired scaffolding" problem this item exists to
  fix.
- **Registry stores the whole `ArazzoDocument`**, not a bare `Workflow` — a bare `Workflow` can't
  actually execute (no `sourceDescriptions`/`Components` context), so storing only that would be
  durable but useless. This reverses an initial narrower design that punted this as "deferred to
  item 03" — it isn't; it's this item's own correctness bar.
- **Three IDs, not one** — `definitionId`/`workflowId`/`executionId` replace the single
  `WorkflowContext::$definitionId` that was silently doing all three jobs (state-store key,
  registry key, and — implicitly — run identity) before.
- **No `status` column on `arazzo_executions` yet** — building a status field with no way to
  transition it to `completed` would be a second half-wired feature, the same smell this item is
  fixing elsewhere. Wait for real completion detection.
- **Postgres partitioning is additive, not required** — package stays installable on any driver;
  partitioning only applies when the connection actually is Postgres.
- **Coordination note**: `WorkflowContext` is being actively edited in the
  `feat-zero-code-data-pipelining` worktree (additive `with*` mutators, no field changes so far)
  — adding `$workflowId`/`$executionId` here is a straightforward rebase, not a conflict.
