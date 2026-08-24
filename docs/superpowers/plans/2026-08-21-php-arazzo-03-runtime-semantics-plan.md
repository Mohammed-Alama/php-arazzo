# Runtime Semantics and Error Model Implementation Plan

> **Status (2026-08-24):** verified against the working tree. `- [x]` = implemented and verified by the current suite; inline notes mark partial/not-done items and where the consolidation refactor changed the approach.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement typed runtime evaluation, selector support, output resolution, and distinct authoring/transport/protocol/execution errors.

**Architecture:** Expression evaluation, selector evaluation, output extraction, and error classification are separate interfaces. The execution core consumes their typed results. Transport failures remain exceptions; failed criteria remain ordinary failed results.

**Tech Stack:** PHP 8.4, PSR-7/PSR-18, DOM XPath, JSONPath implementation already used by the package, Pest PHP.

---

## File map

- Modify `packages/core/src/Expression/Parser.php`, AST classes, and expression exceptions.
- Modify `packages/core/src/Runner/ExpressionEvaluator.php`, `ArazzoExpressionEvaluator.php`, and `ArazzoExpressionResolver.php`.
- Modify `packages/core/src/Resolver/SelectorEvaluator.php`, `Xpath/*`, and `JsonPathEvaluator.php`.
- Modify `packages/core/src/Runner/ArazzoOutputExtractor.php`, `StepExecutor.php`, and `Dto/ExecutionResult.php`.
- Create `packages/core/src/Exceptions/TransportException.php`, `ProtocolException.php`, `CancellationException.php`, and `packages/core/src/Runner/Exceptions/SelectorCapabilityException.php`.
- Modify expression, selector, output, and runner tests; add fixtures under `packages/core/tests/fixtures/`.

### Task 1: Stabilize expression AST and missing-value semantics

- [x] Add failing tests for every expression context, escaped JSON Pointer tokens, absent values, null, false, zero, empty strings, and empty arrays. _(falsy survival additionally pinned by ExecutionState round-trip tests)_
- [x] Update `packages/core/src/Expression/Parser.php` and AST nodes to preserve typed context and pointer segments.
- [x] Update `ExpressionEvaluator.php` and `VariableContext.php` so missing values are not confused with valid falsy values. _(superseded: evaluator now takes EvaluationContext after the namespace restructure; missing-vs-null distinction covered by ConditionEvaluatorTest)_
- [x] Add contextual fields to `ExpressionSyntaxException` and unresolved-reference exceptions. _(a3cdc51: expression+offset / sourceName)_
- [x] Run `cd packages/core && vendor/bin/pest tests/Expression tests/Runner/ExpressionEvaluatorTest.php`; expect PASS.
- [x] Commit `fix: preserve typed Arazzo expression values and context`. _(landed across expression/evaluation commits)_

### Task 2: Complete selector capability interfaces

- [x] Add failing tests for selectors in parameters, request-body replacements, outputs, success criteria, and action criteria. _(a3cdc51: Selector values now resolved at runtime by ExpressionValueResolver in both adapters)_
- [x] Define a selector evaluator contract with selector type/version, context, and typed result/error. _(now at Runner/Evaluation/SelectorEvaluator.php; returns mixed with typed errors)_
- [x] Implement JSONPath and supported XPath versions through existing evaluator classes; reject unsupported versions explicitly. (xpath-10 only)
- [x] Remove generic `RuntimeException` selector failures from `DefaultOpenApiExecutor.php` and `ArazzoOutputExtractor.php`. _(selectors raise typed SelectorEvaluationException)_
- [x] Add capability errors identifying selector type/version and document location. _(a3cdc51: location = workflows/<id>/steps/<stepId>)_
- [x] Run `cd packages/core && vendor/bin/pest tests/Resolver/SelectorEvaluatorTest.php tests/Resolver/Xpath tests/Runner/ArazzoOutputExtractorTest.php`; expect PASS.
- [x] Commit `feat: evaluate Arazzo selectors through typed capabilities`. _(landed as 48f01ef + follow-ups)_

### Task 3: Separate transport, protocol, and execution failures

- [ ] Add failing tests for timeout/connection errors, malformed response bodies, response schema failures, unmet criteria, and cancellation. _(partial: timeouts/schema/criteria/transport covered; cancellation has no concept in core and malformed-body still lacks a dedicated test)_
- [ ] Create stable exception classes/codes for transport, protocol, schema, authoring, execution, and cancellation failures. _(not done as a taxonomy: schema/execution/goto/budget exceptions exist, but no TransportException/ProtocolException/CancellationException; transport failures deliberately become retryable synthetic-500 outcomes instead)_
- [x] Modify `StepExecutor.php` so only expected response decoding/protocol cases become step outcomes; transport exceptions propagate as transport failures. _(superseded by design: StepExecutor and HttpStepExecutor convert transport throwables into synthetic 500 step outcomes so onFailure/retry applies - pinned by the transport-failure-retry-exhaustion fixture)_
- [x] Preserve raw response body and content type alongside decoded JSON. _(a3cdc51: on StepExecutionOutcome, worker step records, and sync step responses)_
- [x] Update event and result payloads to retain error category and cause. _(a3cdc51: category on StepFailed/RunFailed - criteria/schema/transport/execution)_
- [x] Run focused runner tests and `composer run analyse`; expect PASS.
- [x] Commit `feat: classify runtime and transport failures`. _(landed as the transport-policy work in a4522a8/0b06d0a)_

### Task 4: Resolve step and workflow outputs

- [x] Add failing tests for step outputs, workflow outputs, nested workflow outputs, end/goto/retry termination, and output values that are falsy. _(golden fixtures cover all but explicit falsy-output values; falsy persistence covered by InvariantsTest)_
- [x] Update `ArazzoOutputExtractor.php` to use the shared expression/selector contracts and post-response context.
- [x] Add workflow output evaluation to the canonical result finalization path. _(WorkflowEngine::evaluateWorkflowOutputs consumed by BOTH WorkflowExecutor and StepExecutionWorker)_
- [x] Update `ExecutionResult.php`, `StepResult.php`, `SubWorkflowResult.php`, and state serialization to expose outputs and settled terminal results.
- [x] Run `cd packages/core && vendor/bin/pest tests/Runner/ArazzoOutputExtractorTest.php tests/Runner/SubWorkflowInvokerTest.php tests/Runner/WorkflowExecutorTest.php`; expect PASS.
- [x] Commit `feat: resolve workflow and nested execution outputs`. _(landed as caee368 + invoke fixture in 0b06d0a)_
