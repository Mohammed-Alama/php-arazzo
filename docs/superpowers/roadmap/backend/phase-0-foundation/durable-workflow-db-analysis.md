# Durable Workflow DB Migration Analysis & Recommendations

**Status:** Research note
**Created:** 2026-08-07
**Context:** Reviewing `durable-workflow` v2 migrations to inform `docs/database-schema.md` and `persist-46-normalized-schema-design.md`.

## 1. Event Sequencing & Strict Ordering

**How Durable Workflow does it:**
In `workflow_history_events`, they use an `unsignedInteger('sequence')` and a composite unique constraint: `unique(['workflow_run_id', 'sequence'])`.
They also track `last_history_sequence` and `message_cursor_position` on `workflow_runs`.

**How Arazzo does it:**
`arazzo_events` relies on an auto-incrementing `bigint id` and `created_at`. 

**Recommendation for Arazzo:**
Consider adding a `sequence_number` integer to `ARAZZO_EVENTS` with a unique constraint `UNIQUE(execution_id, sequence_number)`. When multiple queue workers append events (e.g. step completed vs. external signal received), relying solely on timestamps or auto-increment IDs can lead to race conditions where the true causal order is lost. A sequence number enforces strict linear append-only history.

## 2. Deadlines, SLA Monitors & Heartbeats

**How Durable Workflow does it:**
They embed absolute timestamps for every SLA contract:
- `workflow_runs`: `execution_deadline_at`, `run_deadline_at`, `run_timeout_seconds`
- `activity_executions`: `schedule_deadline_at`, `close_deadline_at`, `heartbeat_deadline_at`, `last_heartbeat_at`

**How Arazzo does it:**
Currently, `docs/database-schema.md` does not model timeouts or SLA deadlines on `ARAZZO_EXECUTIONS` or step state. However, the roadmap (`rel-06-sla-monitors-dlq` and `rel-49-heartbeat-interface`) will strictly require these.

**Recommendation for Arazzo:**
Extend `ARAZZO_EXECUTIONS` and `ARAZZO_PENDING_CORRELATIONS` (or a new `ARAZZO_ACTIVE_STEPS` table) to include:
- `ARAZZO_EXECUTIONS.deadline_at` (for overall workflow timeout)
- `ARAZZO_EVENTS.heartbeat_deadline_at` or tracking it in active step state.
*Note:* Since Arazzo derives state from the ledger, heartbeats might just be a `step.heartbeat` event type, but having an indexed `deadline_at` column is critical for a background cron/monitor to efficiently find timed-out executions.

## 3. Worker Affinity (Sticky Executions)

**How Durable Workflow does it:**
`workflow_runs` has `sticky_worker_id` and `sticky_until` timestamps.

**Recommendation for Arazzo:**
*Skip for now.* Arazzo steps are mostly discrete HTTP calls via `$stepExecutor`. Unless Arazzo loads large amounts of state into a worker memory and executes multiple steps synchronously without yielding to the queue, sticky routing adds unnecessary complexity.

## 4. Archiving & History Compaction

**How Durable Workflow does it:**
`workflow_runs` has `archived_at` and `archive_reason`.

**Recommendation for Arazzo:**
Arazzo's `raw_document` and event-sourcing mean executions can grow large. Adding an `archived_at` timestamp to `ARAZZO_EXECUTIONS` would allow you to safely prune or move old execution records and their events to cold storage while keeping the normalized relational schema clean.

## 5. Summary of Proposed Changes to `docs/database-schema.md`

If approved, we should apply these modifications to `docs/database-schema.md`:
1. Add `int sequence_number` to `ARAZZO_EVENTS`, plus `UNIQUE(execution_id, sequence_number)`.
2. Add `timestamp deadline_at` to `ARAZZO_EXECUTIONS` for SLA monitoring.
3. Add `timestamp archived_at` to `ARAZZO_EXECUTIONS` for cold storage.
