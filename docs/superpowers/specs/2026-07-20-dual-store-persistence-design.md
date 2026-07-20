# Dual-Store Persistence (CQRS & Event Sourcing) Design

## Overview
A highly parallel, event-driven DAG execution engine requires robust state management that does not bottleneck on database locks. The Dual-Store Persistence model uses a CQRS (Command Query Responsibility Segregation) approach: a Redis "Hot State Cache" for rapid execution, and a PostgreSQL "Append-Only Event Ledger" for auditability and history.

## Core Requirements

1. **Hot State Cache (Redis)**
   - Real-time execution context managed entirely in Redis or memory during the active execution window.
   - Prevents RDBMS row-locking bottlenecks during rapid scatter-gather API orchestration.
   - Extremely fast read/writes for the `WorkflowContext`.

2. **Append-Only Event Ledger**
   - `workflow_state_events` table (or similar ledger structure).
   - Immutably logs every HTTP request payload, response, latency, and step transition.
   - Never UPDATES existing rows; only INSERTS new events.
   - Provides a complete audit trail of the workflow execution.

3. **Zero-Downtime Workflow Versioning**
   - A workflow execution is strictly bound to an immutable `workflow_definition_id`.
   - If the underlying Arazzo YAML/JSON file is updated on disk during an active execution, the running workflow continues to use its original definition graph.
   - Enables seamless zero-downtime deployments.

## Architecture

### Components

- **`StateStoreInterface`**: The contract for saving and loading execution state.
- **`RedisHotStateStore`**: Implements `StateStoreInterface` using Redis hash maps. Includes atomic operations for appending step results.
- **`EventLedgerInterface`**: Contract for recording immutable history events.
- **`PostgresEventLedger`**: Implements `EventLedgerInterface`. Uses native table partitioning (e.g., by month or workflow UUID) to ensure long-term scalability.
- **`DefinitionRegistry`**: Loads and caches the parsed `Workflow` DTO. Binds the execution to a specific version hash.

### Execution Flow

1. Workflow starts: A new definition hash is registered. The initial `WorkflowContext` is stored in the `RedisHotStateStore`.
2. A step completes: The `StepExecutionWorker` atomically updates the Redis state cache.
3. Simultaneously, an event (`StepCompletedEvent`) is dispatched.
4. An async listener catches the event and writes the raw request/response data into the `PostgresEventLedger`.

## Open Questions / Trade-offs
- If Redis evicts keys or crashes, how do we reconstruct the hot state? *Solution: The Hot State can be rebuilt on-the-fly by replaying the Append-Only Event Ledger.*
