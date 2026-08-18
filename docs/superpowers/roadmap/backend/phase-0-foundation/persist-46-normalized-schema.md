# Normalized Arazzo Schema — Migrations, Write-Side Indexer, Eloquent Models

Category: **persist** · Phase: **0-foundation** · Tier: **OSS**
Enables: `frontend/bridges/filament` (`DefinitionResource`, `ExecutionResource`), easyadmin/drupal-admin
bridges, any future reporting surface
Depends on: CQRS & Event-Sourced Persistence (shipped), core-37 DependencyGraph (shipped),
core-38 event dispatcher (shipped)

## Problem

`arazzo_definitions.raw_document` and `arazzo_events.payload` store their content as opaque
JSON blobs. That was a deliberate, correct choice for the *execution* path (see the shipped
CQRS design — one validated parse path, no second hand-rolled dehydrator to drift). But it
means nothing above the execution engine — Filament, any future reporting, "how many workflows
reference operation X" — can be built with real relational integrity, indexes, or joins; every
question has to be answered by parsing JSON at read time.

A full normalized schema for this was designed in `docs/database-schema.md` — 10 new tables
plus alter-migrations on the 3 existing ones — checked against both this codebase's actual DTOs
and the official Arazzo Specification v1.0.1's fixed-fields tables. None of it is implemented
yet. This stub is that implementation, and only that: migrations, the write-side code that
keeps the index in sync, and the Eloquent layer. No Filament code here — that's the next stub,
and it's blocked on this one.

## Feature

Three deliverables, in dependency order:

**1. Migrations** — every table in `docs/database-schema.md`'s ERD: `arazzo_source_descriptions`,
`arazzo_workflows`, `arazzo_workflow_dependencies`, `arazzo_steps`, `arazzo_step_dependencies`,
`arazzo_parameters`, `arazzo_request_bodies`, `arazzo_payload_replacements`,
`arazzo_success_criteria`, `arazzo_values`, `arazzo_actions` — plus alter-migrations on
`arazzo_definitions` (`version_number`, `is_current`, `spec_version`, `self_uri`, `summary`,
`description`, `specification_extensions`), `arazzo_executions` (`workflow_row_id`,
`state_version`), and `arazzo_events` (widen from `payload` JSON to the typed columns the doc
specifies). Every uniqueness constraint the doc calls out as spec-mandated
(`sourceDescriptions[].name`, `workflowId`, `stepId`, no-duplicate parameter/action names) gets
a real `UNIQUE` index, not just an app-level check.

**2. Write-side indexer** — `DatabaseDefinitionRegistry::register()` currently only inserts
`raw_document`. It must walk the parsed `ArazzoDocument` it already has in hand and populate
every normalized table, **in the same DB transaction** as the `raw_document` insert — real
atomicity: either the whole version (blob + every normalized row) lands, or none of it does.
Same for `DatabaseEventLedger::append()`, which today only writes `(execution_id, event_type,
payload, created_at)` — it needs to populate the widened typed columns per event class instead.

**3. Eloquent models** — one per table, `ArazzoDefinition`, `ArazzoWorkflow`, `ArazzoStep`,
`ArazzoStepDependency`, `ArazzoWorkflowDependency`, `ArazzoParameter`, `ArazzoRequestBody`,
`ArazzoPayloadReplacement`, `ArazzoSuccessCriterion`, `ArazzoValue`, `ArazzoAction`,
`ArazzoExecution`, `ArazzoEvent`, `ArazzoPendingCorrelation`. The polymorphic
(`owner_type`/`owner_id`) tables don't fit Eloquent's `MorphTo` shape cleanly (`owner_type` is
a semantic string like `'step'`, not a class name) — model these as explicit scoped relations
per parent (e.g. `ArazzoStep::parameters(): HasMany` internally constrained to
`where('owner_type', 'step')`) rather than forcing Eloquent's morph-map conventions onto a
shape they weren't designed for.

**Consistency guarantee (the actual point of this stub):** a `arazzo:rebuild-index
{definitionId}` console command that re-derives every normalized row for a definition purely
from its `raw_document` and diffs against what's currently stored — proving the index really is
derived data, not something that can silently drift. This is the mechanism that makes "if it
ever drifts, `raw_document` stays authoritative" (from `docs/database-schema.md`'s architecture
principle) an operationally real guarantee instead of an aspiration.

## Acceptance

- Every table + column + constraint in `docs/database-schema.md` exists as a real migration,
  column-for-column.
- `register()` writes `raw_document` and every normalized row atomically — a forced failure
  mid-write (e.g. a constraint violation on one child table) leaves zero rows for that version,
  verified by a test that injects a failure partway through and asserts a clean rollback.
- `arazzo:rebuild-index` produces byte-identical normalized rows to what `register()` originally
  wrote, for every fixture in the test suite — the round-trip proof that the index is genuinely
  derived.
- `DatabaseEventLedger::append()` populates the typed columns correctly for all 9 event classes.
- Every Eloquent model has a factory and at least one relationship test (parent → children,
  and the reverse).
- Zero behavior change to `Parser`/`Loader`/`Validator`/the execution engine — this stub only
  adds a write-side listener to already-existing `register()`/`append()` call sites.

## Out of scope

- Filament resources themselves — next stub, blocked on this one.
- Normalizing `Components` — deferred per `docs/database-schema.md`'s open scoping decision.
- Parsing Reusable Parameters (`Parser::parseParameter()` doesn't support `{reference, value?}`
  today — see `docs/database-schema.md`'s "Reusable parameters aren't modeled — and aren't
  parsed either" finding). The `is_reusable_ref`/`reusable_reference` columns ship now
  (forward-looking, matching the pattern already used for actions) but stay unpopulated until
  the parser fix lands separately.
- Capturing nested-level (Workflow/Step/Parameter/etc.) `x-` extensions — same doc, same
  reasoning: `Parser.php` drops these before they'd ever reach the database. Root-level
  extensions (`specification_extensions` on `arazzo_definitions`) are in scope; nested ones
  are not.
- Any read-side API/query layer beyond the Eloquent models themselves (no GraphQL, no REST
  endpoints) — that's Filament's job, next stub.

## References

- `docs/database-schema.md` — the full ERD, every column, and the reasoning behind each
  design decision (versioning, event-log normalization, spec-completeness gaps).
- Shipped: `docs/superpowers/specs/shipped/2026-07-20-cqrs-event-sourced-persistence-design.md`
  — the "why `raw_document` stays source of truth" rationale this stub builds on top of, not
  around.
