# Runtime Semantics and Error Model Implementation Plan

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

- [ ] Add failing tests for every expression context, escaped JSON Pointer tokens, absent values, null, false, zero, empty strings, and empty arrays.
- [ ] Update `packages/core/src/Expression/Parser.php` and AST nodes to preserve typed context and pointer segments.
- [ ] Update `ExpressionEvaluator.php` and `VariableContext.php` so missing values are not confused with valid falsy values.
- [ ] Add contextual fields to `ExpressionSyntaxException` and unresolved-reference exceptions.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Expression tests/Runner/ExpressionEvaluatorTest.php`; expect PASS.
- [ ] Commit `fix: preserve typed Arazzo expression values and context`.

### Task 2: Complete selector capability interfaces

- [ ] Add failing tests for selectors in parameters, request-body replacements, outputs, success criteria, and action criteria.
- [ ] Define a selector evaluator contract with selector type/version, context, and typed result/error.
- [ ] Implement JSONPath and supported XPath versions through existing evaluator classes; reject unsupported versions explicitly.
- [ ] Remove generic `RuntimeException` selector failures from `DefaultOpenApiExecutor.php` and `ArazzoOutputExtractor.php`.
- [ ] Add capability errors identifying selector type/version and document location.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Resolver/SelectorEvaluatorTest.php tests/Resolver/Xpath tests/Runner/ArazzoOutputExtractorTest.php`; expect PASS.
- [ ] Commit `feat: evaluate Arazzo selectors through typed capabilities`.

### Task 3: Separate transport, protocol, and execution failures

- [ ] Add failing tests for timeout/connection errors, malformed response bodies, response schema failures, unmet criteria, and cancellation.
- [ ] Create stable exception classes/codes for transport, protocol, schema, authoring, execution, and cancellation failures.
- [ ] Modify `StepExecutor.php` so only expected response decoding/protocol cases become step outcomes; transport exceptions propagate as transport failures.
- [ ] Preserve raw response body and content type alongside decoded JSON.
- [ ] Update event and result payloads to retain error category and cause.
- [ ] Run focused runner tests and `composer run analyse`; expect PASS.
- [ ] Commit `feat: classify runtime and transport failures`.

### Task 4: Resolve step and workflow outputs

- [ ] Add failing tests for step outputs, workflow outputs, nested workflow outputs, end/goto/retry termination, and output values that are falsy.
- [ ] Update `ArazzoOutputExtractor.php` to use the shared expression/selector contracts and post-response context.
- [ ] Add workflow output evaluation to the canonical result finalization path.
- [ ] Update `ExecutionResult.php`, `StepResult.php`, `SubWorkflowResult.php`, and state serialization to expose outputs and settled terminal results.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/ArazzoOutputExtractorTest.php tests/Runner/SubWorkflowInvokerTest.php tests/Runner/WorkflowExecutorTest.php`; expect PASS.
- [ ] Commit `feat: resolve workflow and nested execution outputs`.
