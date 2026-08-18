# Normalized Arazzo Schema — Migrations, Write-Side Indexer, Eloquent Models — Design

Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/persist-46-normalized-schema.md`](../roadmap/backend/phase-0-foundation/persist-46-normalized-schema.md)
Category: **persist** · Phase: **0-foundation** · Tier: **OSS**
Table shapes: `docs/database-schema.md` (this spec implements that doc; it does not redesign it)

## Problem

See the stub and `docs/database-schema.md`'s own "Architecture principle" section — not
repeating that reasoning here. What this spec adds on top: the *mechanics* of getting
`DatabaseDefinitionRegistry::register()` and `DatabaseEventLedger::append()` — both real,
already-shipped, already-tested classes — to populate the normalized tables without breaking
their existing dedup/idempotency guarantees, and without the DTO-walking code becoming a second
place `Parser.php`'s shape has to be kept in sync with by hand forever.

## Approach

**One indexer class per source DTO, not indexer logic inlined into the registries.**
`DefinitionIndexer::index(string $definitionId, ArazzoDocument $doc): void` and
`EventIndexer::columns(object $event): array` are separate, independently testable classes.
`DatabaseDefinitionRegistry::register()` and `DatabaseEventLedger::append()` call into them but
stay otherwise unchanged — same dedup-by-content-hash logic, same transaction boundary, same
public API. This keeps the "walk the DTO tree and emit rows" logic in one place per source
(mirroring how `ArazzoDocumentWriter` from `ai-30` is one place for "walk the DTO tree and emit
YAML/JSON" — same shape of problem, same shape of solution) instead of bloating the registry
classes themselves.

**Everything in one transaction, using the DB facade's existing transaction, not a new one.**
`DatabaseDefinitionRegistry::register()` already wraps its insert in `DB::transaction()`
(confirmed by reading the shipped class). `DefinitionIndexer::index()` runs *inside* that same
callback, after the `arazzo_definitions` row is inserted and its `id` is known — so a failure
in, say, the 6th step's parameter insert rolls back the definition row too. No new transaction
scope is introduced; this spec adds work inside an existing one.

**Dedup interacts with indexing exactly once.** `register()`'s existing content-hash dedup means
a re-registered identical document returns the existing `id` without a new insert — and
therefore `DefinitionIndexer::index()` must **not** run on the dedup-hit path, only on the
actual-insert path. Getting this backwards would mean re-indexing (and via the unique
constraints on `workflow_id`/`step_id` etc., *failing* on) a definition that already has its
index rows from the first time it was registered.

**Rebuild command reuses the same indexer, not a parallel implementation.**
`arazzo:rebuild-index {definitionId}` reads `raw_document`, runs it back through
`Parser::parse()` to get a fresh `ArazzoDocument`, then calls the exact same
`DefinitionIndexer::index()` — inside a transaction that deletes the existing normalized rows
for that `definitionId` first. If `index()` had two implementations (one for `register()`, one
for the rebuild command) they'd drift from each other the same way a hand-rolled reverse
serializer would drift from `Parser.php` — the entire thing this design is trying to avoid.

## Architecture

New files, `packages/laravel/src/`:

- `Persistence/Indexing/DefinitionIndexer.php` — `ArazzoDocument` → normalized rows.
- `Persistence/Indexing/EventIndexer.php` — one of the 9 `Alama\Arazzo\Events\*` classes →
  a flat `array` of column values for `DatabaseEventLedger::append()` to insert.
- `Models/ArazzoDefinition.php`, `ArazzoWorkflow.php`, `ArazzoStep.php`,
  `ArazzoWorkflowDependency.php`, `ArazzoStepDependency.php`, `ArazzoParameter.php`,
  `ArazzoRequestBody.php`, `ArazzoPayloadReplacement.php`, `ArazzoSuccessCriterion.php`,
  `ArazzoValue.php`, `ArazzoAction.php`, `ArazzoExecution.php`, `ArazzoEvent.php`,
  `ArazzoPendingCorrelation.php`.
- `Console/Commands/RebuildIndexCommand.php` — `arazzo:rebuild-index {definitionId}`.

Modified:

- `Persistence/DatabaseDefinitionRegistry.php` — `register()` calls `DefinitionIndexer::index()`
  on the actual-insert path only.
- `Persistence/DatabaseEventLedger.php` — `append()` calls `EventIndexer::columns()` and inserts
  the widened row shape instead of `(execution_id, event_type, payload, created_at)`.

New migrations, `packages/laravel/database/migrations/`, one file per table in
`docs/database-schema.md`'s ERD, plus three alter-migrations (`arazzo_definitions`,
`arazzo_executions`, `arazzo_events`) for the new columns on existing tables.

## API

```php
namespace Alama\LaravelArazzo\Persistence\Indexing;

use Alama\Arazzo\Dto\ArazzoDocument;

final class DefinitionIndexer
{
    /**
     * Walks $doc and inserts every normalized row, scoped to $definitionId.
     * MUST be called inside the same transaction as the arazzo_definitions insert,
     * and MUST NOT be called on register()'s dedup-hit path.
     */
    public function index(string $definitionId, ArazzoDocument $doc): void;

    /** Used by the rebuild command: deletes every normalized row for $definitionId first. */
    public function reindex(string $definitionId, ArazzoDocument $doc): void;
}
```

```php
namespace Alama\LaravelArazzo\Persistence\Indexing;

final class EventIndexer
{
    /**
     * @return array<string, mixed> column => value, ready for DB::table('arazzo_events')->insert().
     *   Populates only the columns relevant to $event's concrete class; the rest are left absent
     *   (nullable columns default to null on insert).
     */
    public function columns(object $event): array;
}
```

## Behavior

**`DefinitionIndexer::index()`, per table, in insert order (parents before children, respecting
FKs):**

1. `arazzo_source_descriptions` — one row per `$doc->sourceDescriptions`.
2. `arazzo_workflows` — one row per `$doc->workflows`, `sort_order` = array index. Capture the
   returned auto-increment `id` per workflow (`workflow_row_id` in later tables) in a local
   `workflowId => rowId` map — needed because `Step::$dependsOn`/actions reference workflows by
   spec `workflowId` string, not by row id.
3. `arazzo_workflow_dependencies` — one row per entry in each workflow's `$dependsOn`.
4. Per workflow, `arazzo_steps` — one row per `$workflow->steps`, `sort_order` = array index.
   Same local `stepId => rowId` map pattern for `arazzo_step_dependencies` and any
   `$steps.<id>` cross-references within the same workflow.
5. Per step: `arazzo_step_dependencies`, `arazzo_parameters` (`owner_type = 'step'`),
   `arazzo_request_bodies` (+ `arazzo_payload_replacements` if `replacements` non-empty),
   `arazzo_success_criteria` (`owner_type = 'step'`), `arazzo_values`
   (`owner_type = 'step_output'`), `arazzo_actions` (`owner_type` = `'step_success'` /
   `'step_failure'` per `onSuccess`/`onFailure`).
6. Per workflow (after its steps, since actions can target `stepId`s that must already have
   their `arazzo_steps` rows for the `target_step_id` value to be meaningful — note this is a
   string column, not an FK, per `docs/database-schema.md`'s polymorphic-table note, but
   ordering still matters for the values to be correct): `arazzo_parameters`
   (`owner_type = 'workflow'`), `arazzo_values` (`owner_type = 'workflow_output'`),
   `arazzo_actions` (`owner_type` = `'workflow_success'` / `'workflow_failure'`).
7. `Expression`/`Selector`/literal values throughout: same three-column pattern
   (`value_kind`/`value_expression`/`value_selector`/`value_literal`) as
   `docs/database-schema.md` specifies — the actual value-to-columns mapping is identical logic
   to `ArazzoDocumentWriter::valueToArray()` from the `ai-30` work, just writing to DB columns
   instead of an array destined for YAML/JSON. **Reuse that mapping, don't reimplement it** —
   either extract a small shared `ValueNormalizer` both `ArazzoDocumentWriter` and
   `DefinitionIndexer` call, or have `DefinitionIndexer` depend on
   `Alama\Arazzo\Generator\Support\ArazzoDocumentWriter` directly for just that piece. Confirm
   which at implementation time based on which produces a cleaner dependency direction (core
   `Generator` support classes being depended on by the Laravel bridge is fine — it's already
   the direction `packages/laravel` depends on `packages/core`, not the reverse).

**`register()`'s call site**, in `DatabaseDefinitionRegistry.php`:

```php
return DB::transaction(function () use ($document, $identity, $hash, $raw) {
    $existing = $this->db->table('arazzo_definitions')
        ->where('document_identity', $identity)->where('content_hash', $hash)->first();
    if ($existing !== null) {
        return $existing->id; // dedup hit -- indexer NOT called
    }

    $id = (string) Str::ulid();
    $versionNumber = $this->nextVersionNumber($identity); // new: MAX(version_number)+1 scoped to $identity
    $this->db->table('arazzo_definitions')->insert([/* ... existing columns ... */
        'id' => $id, 'version_number' => $versionNumber, 'is_current' => true, /* ... */
    ]);
    $this->markPreviousVersionsNotCurrent($identity, exceptId: $id); // new

    $this->indexer->index($id, $document); // new -- only on the actual-insert path

    return $id;
});
```

`nextVersionNumber()`/`markPreviousVersionsNotCurrent()` are the `version_number`/`is_current`
mechanics from `docs/database-schema.md`'s versioning section — small additions to the existing
class, not new files.

**`EventIndexer::columns()`** — a `match ($event::class)` over the 9 known classes (see
`packages/core/src/Events/*.php`), each arm returning the subset of typed columns that class's
properties map to, per `docs/database-schema.md`'s `ARAZZO_EVENTS` block: `RunStarted` →
`event_type='run.started', workflow_id, inputs, occurred_at`; `StepExecuted` → `event_type=
'step.executed', workflow_id, step_id, status_code, criteria_met, outputs, occurred_at`; etc.
`occurred_at` comes from `$event->at`, `created_at` is left to the DB default (`now()`), and `sequence_number` is assigned monotonically by the ledger during append — see `docs/database-schema.md`'s finding on sequence constraints and timestamp separation.

## Testing

**`DefinitionIndexerTest`** — for a handful of fixture documents (reuse the `ai-30` generator
fixtures where they fit — `petstore-minimal.yaml` already has multiple steps with parameters,
request bodies, and outputs, which is most of what needs covering): assert every expected row
lands, in the right table, with the right `owner_type`/`owner_id`/`sort_order`, and that
`Expression`/`Selector`/literal values round-trip through `value_kind` correctly.

**`RegisterIndexingIntegrationTest`** — the actual acceptance-proving test: call
`DatabaseDefinitionRegistry::register()` against a real (test) database, then assert the
normalized rows exist via the Eloquent models, not just that no exception was thrown.
Separately: register the *same* document twice, assert the second call is a dedup hit (same
`id` returned) **and** that no duplicate normalized rows were inserted (row counts unchanged
after the second call).

**`TransactionRollbackTest`** — force a failure partway through indexing (e.g. a
uniqueness-constraint violation via a crafted fixture with two workflows sharing a
`workflowId`), assert `register()` throws, and assert **zero** rows exist afterward across
`arazzo_definitions` and every normalized table — the atomicity claim in the stub's acceptance
criteria, proven rather than asserted.

**`RebuildIndexCommandTest`** — register a document, capture the normalized rows, run
`arazzo:rebuild-index`, assert the rows after rebuild are identical to the rows before (same
values; row ids may legitimately differ since `reindex()` deletes and re-inserts) — the
derived-data guarantee, proven.

**`EventIndexerTest`** — one test per event class, asserting `columns()` maps every relevant
property to the right column and leaves irrelevant columns absent.

**Model relationship tests** — one per model, factory + at least one `hasMany`/`belongsTo`
assertion, per the stub's acceptance criteria.

Full existing Pest suite green; PHPStan max level clean on all new/modified files.

## Migration + CHANGELOG

`## Unreleased` → `### Added`:

- Normalized relational schema for the Arazzo document/execution/event model — 11 new tables,
  Eloquent models for all of them, migrations. `arazzo_definitions`/`arazzo_executions` gain
  versioning columns (`version_number`, `is_current`, `workflow_row_id`, `state_version`) plus `deadline_at`/`archived_at`;
  `arazzo_events` widens from a generic `payload` JSON column to typed per-event-type columns and gains a strict `sequence_number`.
  `raw_document` remains the execution source of truth — see `docs/database-schema.md`.
- `arazzo:rebuild-index` console command — re-derives normalized rows from `raw_document`.

No breaking changes to `Parser`/`Loader`/`Validator`/the execution engine or to
`DefinitionRegistryInterface`/`EventLedgerInterface`'s public contracts — `register()`/`append()`
gain internal behavior, not new required parameters.

## Acceptance

Matches stub Acceptance section — traced directly above: schema-matches-doc (Architecture),
atomic transaction (`TransactionRollbackTest`), rebuild proves derivation
(`RebuildIndexCommandTest`), event ledger typed columns (`EventIndexerTest`), model tests
(per-model), zero engine behavior change (no `Parser`/`Loader`/`Validator` files touched by this
plan when it's written).

## Out of Scope

Matches stub Out of Scope section verbatim — Filament resources, Components normalization,
Reusable-parameter parsing, nested-level extension capture, any read-side API beyond the
Eloquent models.

## References

- `docs/database-schema.md` — table shapes and design rationale this spec implements.
- `docs/superpowers/specs/shipped/2026-07-20-cqrs-event-sourced-persistence-design.md` —
  the `register()`/`append()` shapes and dedup logic this spec builds inside of.
- `packages/laravel/src/Persistence/DatabaseDefinitionRegistry.php`,
  `DatabaseEventLedger.php` — the exact current call sites being extended.
- `packages/core/src/Events/*.php` — the 9 event classes `EventIndexer` maps.
- `docs/superpowers/specs/2026-08-03-ai-30-openapi-deterministic-gen-design.md` /
  `Alama\Arazzo\Generator\Support\ArazzoDocumentWriter` — the value-normalization logic this
  spec reuses rather than reimplements.
