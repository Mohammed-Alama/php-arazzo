# Design: AsyncAPI `receive` step default success semantics + validator rule

- **Status**: Draft (awaiting approval)
- **Author**: Mohammed Alama & OpenCode
- **Date**: 2026-11-09
- **Issue**: #168 (`[Feature] AsyncAPI receive steps: default success semantics + validator rule`), epic #150
- **Builds on**: #159 (`CriterionResultCollection` / `CriterionEvaluatedEvent`)

---

## 1. Summary

A `receive` step with no `successCriteria` currently evaluates as an empty criteria list, which
`CriterionResultCollection::criteriaMet()` reports as `true` unconditionally. Define the default outcome as
**"a message was received"**, make that state explicit in the step record, and add a validator rule that reports
missing criteria using the repository's strict-aware severity convention.

The design also repairs a wiring regression: `EvaluationEngine::evaluateSuccessCriteria()` bypasses
`CriteriaEvaluator::evaluateSuccessCriteria()`, so **all** default outcomes (including the existing HTTP 2xx default)
are currently inert in the runner. Defaults must live on the path the runtime actually calls.

---

## 2. Evidence Base

All findings below were read in this worktree on 2026-11-09.

| # | Finding | Location |
| --- | --- | --- |
| E1 | Operation steps with no criteria default to any 2xx response. | `packages/evaluation/src/CriteriaEvaluator.php:70-80` |
| E2 | Receive steps with no criteria fall through to `evaluateCriteria([])` → `criteriaMet()` → `true`. | `CriteriaEvaluator.php:82`; `CriterionResultCollection::criteriaMet()` |
| E3 | The runner calls `EvaluationEngineInterface::evaluateSuccessCriteria()`. | `packages/runner/src/Sync/StepExecutor.php:98,172`; `packages/runner/src/Async/CorrelationResumer.php:135`; `packages/runner/src/Async/StepExecutionWorker.php:197` |
| E4 | `EvaluationEngine::evaluateSuccessCriteria()` calls `evaluateCriteria()` directly, bypassing the defaults in E1/E2. Before #159 it delegated to `CriteriaEvaluator::evaluateSuccessCriteria()`. | `packages/evaluation/src/EvaluationEngine.php:80-83`; commit `0e3df9d1` |
| E5 | A receive step reaches criteria evaluation only after `CorrelationResumer::resume()` records the received payload. | `CorrelationResumer.php:112-147` |
| E6 | A receive timeout synthesises a 504 and calls the outcome handler with `criteriaMet=false` directly; criteria are never evaluated for that path. | `CorrelationResumer.php:89-110` |
| E7 | AsyncAPI `action`/`channelPath`/`correlationId` are 1.1-only; 1.0 documents are already rejected by an existing rule. | `AsyncApiFieldsRequire11Rule.php` |
| E8 | Existing validator convention for configurable severity: strict → error, non-strict → warning. | `DocUnknownFieldRule.php:35-39`; `RuleSet::$strict` |

---

## 3. Runtime Semantics

### 3.1 Default outcome

For a step whose target action is `receive` and whose `successCriteria` list is **empty**:

| Recorded state for the step | Outcome |
| --- | --- |
| `messageReceived === true` | **Success** — a message resumed the step. |
| no record, marker absent, or marker not `true` | **Failure** — no message was received. |
| explicit `successCriteria` present | Criteria evaluated as today; the default does not apply. |

**Definition.** A `receive` step succeeds by default iff a message arrived. Arrival is recorded by
`CorrelationResumer` as an explicit boolean marker (`messageReceived => true`) in the step record. This is
deliberately narrower than "a response record exists": the timeout path also writes a 504 response record, and a
missing/partial record must not silently pass.

### 3.2 Message arrival marker

`CorrelationResumer::resume()` writes:

```php
$contextWithResult = $context->withStepResult($step->stepId, [
    'statusCode' => $statusCode,
    'response' => $response,
    'outputs' => $outputs,
    'messageReceived' => true,
]);
```

Criteria and outputs keep reading `$response` / `$outputs` exactly as before. The marker is additive and
persisted with the rest of the step record. The timeout path does not set it, and does not evaluate criteria.

### 3.3 Criteria precedence

Explicit criteria always win. If a receive step declares criteria, they are evaluated unchanged; the marker only
affects the empty-criteria branch. A criterion failure still fails the step even when a message arrived.

### 3.4 Runtime wiring

`EvaluationEngine::evaluateSuccessCriteria()` delegates to `CriteriaEvaluator::evaluateSuccessCriteria()` again,
restoring the pre-#159 shape. This is required for the receive default to run in production and also restores the
existing HTTP 2xx default.

**Deliberate consequence:** operation steps with no criteria now actually use the documented 2xx rule at runtime
(previously they passed unconditionally in the runner). The rule itself is unchanged — this is a wiring fix, not a
new HTTP default. Pinned by tests at both the `CriteriaEvaluator` and `EvaluationEngine` levels.

---

## 4. Validator Rule

New rule: `ReceiveStepSuccessCriteriaRule`.

- **Code:** `step.receive_missing_success_criteria`
- **Scope:** steps with `target->action === 'receive'` and an empty `io->successCriteria` list.
- **Severity:** error when `RuleSet::$strict` is true, warning otherwise (injected via constructor, matching
  `DocUnknownFieldRule`).
- **Path:** `/workflows/{workflowIndex}/steps/{stepIndex}/successCriteria`
- **Message:** names the step and explains that the default only requires a message to arrive.
- **Version scope:** skip 1.0 documents. `action` is already rejected there by
  `AsyncApiFieldsRequire11Rule`, so this rule must not double-report one mistake.
- **Registration:** `RuleSet::default()` passes `$strict` when constructing the rule.

Absence of criteria remains legal (the runtime has a defined default); the diagnostic is advice that the workflow
accepts any message shape, and strict validators reject it.

---

## 5. Non-Goals

- No change to the HTTP 2xx rule itself.
- No change to `evaluateCriteria()` semantics for an empty list (the detailed API remains an empty result list).
- No new AsyncAPI transport behaviour; the receive executor and correlation registry are untouched.
- No new configuration surface beyond the existing strict/disable convention.

---

## 6. Acceptance Criteria Mapping

| Issue criterion | How it is satisfied |
| --- | --- |
| A receive step with no criteria has a defined, documented default outcome. | §3.1 + architecture doc update. |
| The default does not silently pass when no message was received. | Marker gate: absent/false marker → failure. |
| A validator rule reports the missing-criteria case as configured. | §4 strict-aware error/warning, registered in `RuleSet`. |
| Pest cases cover receive-with and receive-without criteria. | Test plan below. |

---

## 7. Test Plan (TDD: RED first)

### `packages/evaluation/tests/ReceiveStepDefaultTest.php`
- receive + no criteria + `messageReceived => true` → `true`
- receive + no criteria + no step record → `false`
- receive + no criteria + `messageReceived => false` → `false`
- receive + explicit failing criterion + marker → `false`
- receive + explicit passing criterion → `true`
- operation + no criteria + 2xx → `true`; non-2xx → `false` (pins the existing default)

### `packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php`
- `EvaluationEngine::evaluateSuccessCriteria()` applies the receive default (marker present/absent)
- `EvaluationEngine::evaluateSuccessCriteria()` applies the operation 2xx default (regression for E4)

### `packages/runner/tests/Async/CorrelationResumerTest.php` (extend)
- resuming a receive step records `messageReceived => true` alongside `response`/`outputs`

### `packages/document/tests/Validator/Rules/ReceiveStepSuccessCriteriaRuleTest.php`
- receive + no criteria + strict → error
- receive + no criteria + non-strict → warning
- receive + criteria → clean
- send + no criteria → clean
- 1.0 document → no diagnostic from this rule

### `packages/document/tests/Validator/RuleSetTest.php` (extend)
- default rule set contains `step.receive_missing_success_criteria`

---

## 8. Risks and Decisions

| Risk | Mitigation |
| --- | --- |
| Restoring delegation changes runtime behaviour for operation steps with no criteria. | Deliberate; documented in §3.4 and pinned by tests. It restores pre-#159 intent. |
| Persisted step records gain a key. | Additive; all consumers read known keys and ignore extras. `WorkflowContext::fromPersisted`/`reconciled` carry steps verbatim. |
| A transport could resume with an empty payload. | Arrival is the signal, not payload size; an empty message still counts as received. |
| 1.0 documents cannot legally declare receive steps. | Rule no-ops for 1.0; existing version rule reports the field violation. |
