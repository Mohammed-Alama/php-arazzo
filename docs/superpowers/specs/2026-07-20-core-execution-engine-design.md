# Arazzo Core Execution Engine Design Specification

**Status**: 📋 Validated Design
**Date**: 2026-07-20
**Topic**: Core Execution Engine (Sub-Project 1)

## 1. Overview
The Core Execution Engine is the foundation of the Arazzo-based workflow orchestrator. It is responsible for parsing the Arazzo declarative workflow specification, evaluating the Directed Acyclic Graph (DAG) of API steps, injecting variables, making HTTP requests, and safely managing state transitions. 

Crucially, the Core Engine is designed to be **framework-agnostic**. It defines strict interfaces for queues, locks, and state management, allowing it to be used in any modern PHP project, with a dedicated Laravel Adapter bridging it into the Laravel ecosystem.

## 2. Architecture & Components

The engine is decoupled into several highly focused components:

- **`WorkflowContext`**: An immutable state container representing the workflow at an exact point in time. It holds inputs, step outputs, and a reference to the specific workflow version (`workflow_definition_id`) to ensure zero-downtime versioning (protecting inflight workflows from live YAML changes).
- **`DependencyAnalyzer`**: Analyzes the Arazzo `dependsOn` arrays to build a DAG. Given a `WorkflowContext`, it determines which steps are unblocked and eligible to execute immediately.
- **`JSONPathResolver`**: Resolves Arazzo Runtime Expressions (e.g., `$steps.step_a.outputs.data`) against the `WorkflowContext` to construct HTTP request payloads and evaluate success criteria.
- **`Engine` (The Orchestrator)**: The central state machine loop. It triggers the analyzer, dispatches runnable steps via the queue interface, and sleeps if waiting on pending steps.

## 3. Data Flow & Parallelism (Scatter-Gather)

The engine supports native Map-Reduce and parallel execution by leveraging an event-driven architecture rather than relying on framework-specific queue chaining.

1. **Scatter (Dispatch)**: When the `Engine` finds multiple independent steps, it dispatches them all simultaneously via the `QueueDriverInterface`.
2. **Execute**: Workers pick up the steps, resolve variables, optionally validate the payload against the OpenAPI schema (Fail-Fast), and execute the HTTP request via a PSR-18 client.
3. **Gather (State Lock)**: Upon completion, the step must update the global `WorkflowContext`. To prevent race conditions from parallel steps finishing simultaneously, the step acquires a **Pessimistic Lock** via the `LockManagerInterface` (e.g., Redis). It updates the context, releases the lock, and fires a completion event.
4. **Re-evaluate**: The completion event triggers the `Engine` to wake up, re-analyze the graph with the new context, and dispatch the next unblocked steps.

## 4. Interfaces & Framework Independence

To ensure the engine can be used outside of Laravel in the future, the core library will define and rely exclusively on the following interfaces:
- `QueueDriverInterface`: Handles async job dispatching.
- `LockManagerInterface`: Handles atomic locking for the scatter-gather pattern.
- `StateStoreInterface`: Handles persisting and retrieving the `WorkflowContext` and workflow definitions.
- `HttpClientInterface`: A PSR-18 compliant interface for executing the actual API calls.

## 5. Error Handling & Resilience

- **Native Retries**: The engine does not rely on framework-specific queue retry limits. It respects the Arazzo `onFailure` block natively. If a step fails, the `StepExecutor` catches the exception and schedules a delayed retry via the `QueueDriverInterface` based on the YAML configuration.
- **Fail-Fast Schema Validation**: Payloads are validated against the linked OpenAPI schema before the HTTP request is dispatched, preventing type-mismatch errors in the target system.
- **Testing (Dry-Run)**: Developers can swap the `HttpClientInterface` with a Mock client that seeds responses directly from the OpenAPI spec, allowing for instantaneous, offline Pest/PHPUnit tests of workflow logic without real network calls.
