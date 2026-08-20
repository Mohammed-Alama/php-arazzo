# Canonical Execution Core

## Goal

Create one authoritative PHP execution engine implementing official Arazzo workflow semantics. Synchronous execution, queued execution, and Laravel integration must use the same transition logic.

## Core responsibilities

The core loads a validated document and workflow, creates execution state, resolves dependencies, selects the next eligible step, evaluates inputs and outputs, interprets actions, resolves workflow outputs, and produces a structured result. It emits domain events through interfaces and has no Laravel or queue dependency.

The state machine must interpret `onSuccess`, `onFailure`, workflow defaults, `goto`, `retry`, `end`, `invoke`, `dependsOn`, and terminal workflow behavior.

## Adapters

The synchronous adapter runs transitions in-process and returns the final result. The queue adapter persists state after transitions, enqueues the next transition, applies locks, and supports delayed retry and resumption. The Laravel adapter binds core interfaces to Laravel queues, cache locks, events, persistence, and configuration. Laravel code must not contain Arazzo decision-making logic.

## State model

State contains the execution ID, definition ID, workflow call stack, current workflow and step position, workflow inputs, step statuses and attempts, requests, responses, outputs, nested workflow results, dependency results, terminal status, error details, timestamps, and persistence/event version metadata. State transitions are deterministic and serializable.

## Compatibility

Breaking changes are allowed. Existing executor classes may be replaced or redesigned around the canonical engine.

## Acceptance

Acceptance requires equivalent observable results for synchronous and queued execution, one implementation of control-flow decisions, workflow outputs in final results, distinct transport/criteria/authoring semantics, and queue resumption without repeating completed side effects.
