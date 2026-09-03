# Modularity Review

**Scope**: php-arazzo monorepo (alama/arazzo-core + alama/laravel-arazzo) — full codebase
**Date**: 2026-09-02

## Executive Summary

php-arazzo is a PHP monorepo implementing the Arazzo workflow specification: a framework-agnostic engine (`arazzo-core`) that parses, validates, and executes multi-step API workflows, plus a Laravel bridge (`laravel-arazzo`) that provides production infrastructure. The codebase follows a hexagonal architecture with clear core/laravel separation, but the core package has accumulated five significant [coupling imbalances](https://coupling.dev/posts/core-concepts/balance/) that actively hinder the in-progress package split. The most critical issue is that the `Execution` module — the largest at 4,111 LOC — has become a [gravitational center](https://coupling.dev/posts/core-concepts/coupling/) that other modules bypass through [concrete classes](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) rather than [interfaces](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/), and several modules define interfaces that logically belong in lower-layer modules.

## Coupling Overview Table

| Integration | [Strength](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) | [Distance](https://coupling.dev/posts/dimensions-of-coupling/distance/) | [Volatility](https://coupling.dev/posts/dimensions-of-coupling/volatility/) | [Balanced?](https://coupling.dev/posts/core-concepts/balance/) |
| --- | --- | --- | --- | --- |
| Execution ↔ Async | [Contract](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) | Low (same package) | Async=Low | Yes |
| Infrastructure → State | [Contract](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) | Low (same package) | Both=Low | Yes |
| Policy → Execution | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (concrete, bidirectional) | Low (same package) | Policy=Low, Execution=High | Yes (high cohesion) |
| Evaluation → Execution | [Model](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) + [Functional](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (static call) | Low (same package) | Evaluation=Med, Execution=High | Yes (high cohesion) |
| Execution → Protocol | [Contract](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (interface) | Low (same package) | Execution=High, Protocol=Med | **No** — both low strength + low distance, both volatile |
| Generator → Execution | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (concrete) | Low (same package) | Generator=Med, Execution=High | Yes (high cohesion) |
| State → Execution | [Model](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (bidirectional) | Low (same package) | State=Low, Execution=High | Yes (low volatility) |
| Dependency → Execution | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (concrete) | Low (same package) | Dependency=Low, Execution=High | Yes (low volatility) |
| Jobs → Execution | [Model](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (shared DTO) | Low (same package) | Jobs=Low, Execution=High | Yes (low volatility) |
| Execution → Expression | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (6 concrete classes) + [Contract](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (fat interface) | Low (same package) | Both=High (Core) | Yes (high cohesion) |
| Telemetry → OpenTelemetry | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (vendor SDK) | Low (same package) | Telemetry=Low | Yes (low volatility) |
| Console → Symfony | [Intrusive](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) (30 refs) | Low (same package) | Console=Low | Yes (low volatility) |

## Issues

<div class="issue">

## Issue: Protocol ↔ Execution — The Fake Interface Seam

**Integration**: Protocol ↔ Execution
**Severity**: <span class="severity severity-significant">Significant</span>

### Knowledge Leakage

The `Protocol` module declares `StepProtocolExecutorInterface` as its public contract, suggesting a clean boundary. However, all three protocol executors — `HttpStepExecutor`, `SubWorkflowStepExecutor`, `AsyncApiStepExecutor` — directly import six [concrete](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) Execution classes: `ExpressionValueResolver`, `IdempotencyKeyInjector`, `RequestCompiler`, `ReusableParameterResolver`, `WorkflowExecutor`, and `WorkflowEngine`. Protocol has deep knowledge of Execution's [internal model](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) — how requests are compiled, how expressions are resolved, how idempotency keys are injected, and how sub-workflows are orchestrated. The interface seam exists but does not actually [encapsulate](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) Execution's implementation details.

### Complexity Impact

Protocol is effectively a sub-execution layer — it orchestrates HTTP calls, expression resolution, and sub-workflow invocation using Execution's internal machinery. When a developer modifies `RequestCompiler` or `ExpressionValueResolver`, they must also verify that all three protocol executors still work correctly. The [cognitive load](https://coupling.dev/posts/core-concepts/complexity/) exceeds the 4±1 threshold because understanding a protocol executor requires understanding five Execution internals simultaneously.

### Cascading Changes

- Changing `RequestCompiler`'s constructor signature forces changes in `HttpStepExecutor`
- Modifying how `ExpressionValueResolver` resolves values forces changes in `HttpStepExecutor`, `SubWorkflowStepExecutor`, and `AsyncApiStepExecutor`
- Changing `WorkflowEngine`'s transition model forces changes in `SubWorkflowExecutor`
- The in-progress package split cannot separate Protocol from Execution without first introducing proper [interfaces](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) at these boundaries

### Recommended Improvement

Extract the six concrete Execution dependencies behind [interfaces](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) that Protocol can depend on:

- `RequestCompilerInterface` — for request compilation
- `ExpressionResolverInterface` already exists but is too [fat](https://coupling.dev/posts/related-topics/connascence/) (5 methods). Split into focused interfaces
- `SubWorkflowInvokerInterface` — for sub-workflow orchestration
- `IdempotencyKeyInjector` is already injected; ensure it's typed to an interface

**Trade-off**: This adds interface boilerplate and one more indirection layer. But it's necessary for the package split — Protocol must be able to live in a separate package from Execution, which is only possible if the dependency is on abstractions, not [concrete classes](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/).

</div>

<div class="issue">

## Issue: Interface Ownership Inversions

**Integration**: Policy → Execution and Evaluation → Execution
**Severity**: <span class="severity severity-significant">Significant</span>

### Knowledge Leakage

Two modules define interfaces in `Execution\Interfaces\` that logically belong to the defining module:

1. `BackoffCalculatorInterface` lives in `Execution\Interfaces\` but is implemented by `Policy\ExponentialBackoffCalculator`. Policy's retry strategy knowledge leaks into Execution's interface namespace.
2. `OutputExtractorInterface` lives in `Execution\Interfaces\` but is consumed by `Evaluation\ExpressionResolver`. Evaluation's output extraction concern leaks into Execution's interface namespace.

This creates [implicit](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) coupling: lower-layer modules must depend on higher-layer modules to implement their own contracts. The dependency arrow points the wrong direction — [Policy](https://coupling.dev/posts/related-topics/domain-driven-design/) depends on Execution not because it needs Execution's functionality, but because Execution owns Policy's interface.

### Complexity Impact

When a developer looks at `Execution\Interfaces\`, they see a mix of concerns: some interfaces are Execution's own contracts (`StepProtocolExecutorInterface`, `ProtocolExecutorRegistryInterface`), while others are interfaces for other modules (`BackoffCalculatorInterface`, `OutputExtractorInterface`). This makes it unclear where responsibility boundaries actually lie. The [cognitive model](https://coupling.dev/posts/core-concepts/complexity/) of "Execution defines what Protocol, Policy, and Evaluation need" inverts the actual dependency direction.

### Cascading Changes

- Moving `BackoffCalculatorInterface` to `Policy\Interfaces\` requires updating `Execution\WorkflowEngine` to depend on `Policy` for the interface (which it already does for the concrete class)
- Moving `OutputExtractorInterface` to `Evaluation\Interfaces\` requires updating `Evaluation\ExpressionResolver` and any Execution class that implements it
- During the package split, these misplaced interfaces will create circular package dependencies unless relocated

### Recommended Improvement

Relocate misplaced interfaces to their logical owner:

- Move `BackoffCalculatorInterface` → `Policy\Interfaces\BackoffCalculatorInterface`
- Move `OutputExtractorInterface` → `Evaluation\Interfaces\OutputExtractorInterface`
- Execution should depend on Policy and Evaluation [interfaces](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/), not own them

**Trade-off**: This is a straightforward relocation that improves the dependency graph. The only cost is updating import paths across the codebase. The benefit is correct [layering](https://coupling.dev/posts/core-concepts/balance/) and no circular package dependencies during the split.

</div>

<div class="issue">

## Issue: Execution ↔ Expression — Concrete Classes Bypass the Interface

**Integration**: Execution → Expression
**Severity**: <span class="severity severity-significant">Significant</span>

### Knowledge Leakage

`ExpressionResolverInterface` exists as a [contract](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) with 5 methods, used by `StepExecutor`, `WorkflowEngine`, `StepExecutionWorker`, and `CorrelationResumer`. However, four other Execution classes bypass this interface entirely and directly instantiate [concrete](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) Expression module classes:

- `ExpressionValueResolver` → `new ExpressionEvaluator()`, `new SelectorEvaluator()`, `new StringInterpolator()`, `new DomXpathEvaluator()`
- `StepOutcomeHandler` → injects `ExpressionEvaluator` and `SelectorEvaluator` as concrete types
- `SubWorkflowInvoker` → injects `ExpressionEvaluator` and `SelectorEvaluator` as concrete types
- `StepOutputExtractor` → imports `ExpressionEvaluator`, `JsonPathEvaluator`, `Parser`, `SelectorEvaluator`, `DomXpathEvaluator`

Execution knows Expression's [internal class hierarchy](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) — that `SelectorEvaluator` wraps `DomXpathEvaluator`, that `ExpressionEvaluator` handles `{$...}` syntax, that `JsonPathEvaluator` handles JSONPath expressions. This is [intrusive coupling](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) through concrete classes.

### Complexity Impact

Both Execution and Expression are [Core domain](https://coupling.dev/posts/dimensions-of-coupling/volatility/) modules — the highest [volatility](https://coupling.dev/posts/dimensions-of-coupling/volatility/) in the system. Changes to Expression's internal class structure (e.g., renaming `SelectorEvaluator`, changing its constructor) cascade into four Execution classes. The [cognitive load](https://coupling.dev/posts/core-concepts/complexity/) is severe: understanding `StepOutputExtractor` requires knowing five Expression internals.

### Cascading Changes

- Renaming or restructuring `ExpressionEvaluator` forces changes in `ExpressionValueResolver`, `StepOutcomeHandler`, `SubWorkflowInvoker`, and `StepOutputExtractor`
- Adding a new expression type (e.g., JSON Pointer) requires modifying multiple Execution classes, not just the Expression module
- The `ExpressionResolverInterface` fat interface (5 methods) bundles unrelated concerns: evaluation, output extraction, criteria evaluation, schema validation. Splitting it would require updating all concrete injection points

### Recommended Improvement

1. **Extend the interface seam**: Add `ExpressionEvaluatorInterface` and `SelectorEvaluatorInterface` so that Execution classes depend on interfaces, not concrete classes
2. **Split the fat `ExpressionResolverInterface`** into focused interfaces: `ExpressionEvaluatorInterface` (evaluate), `OutputExtractorInterface` (extract outputs), `CriteriaEvaluatorInterface` (evaluate criteria), `SchemaValidatorInterface` (validate response schema)
3. **Inject the interfaces** in `ExpressionValueResolver`, `StepOutcomeHandler`, `SubWorkflowInvoker`, and `StepOutputExtractor`

**Trade-off**: More interfaces and more constructor parameters. But this is the highest-volatility coupling in the system — both modules are Core domain. Proper [encapsulation](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) here pays for itself immediately during the package split and ongoing development.

</div>

<div class="issue">

## Issue: Telemetry — Vendor Lock-in in Core

**Integration**: Telemetry → OpenTelemetry
**Severity**: <span class="severity severity-minor">Minor</span>

### Knowledge Leakage

`Telemetry/OtelSetup.php` contains the [complete](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) OpenTelemetry SDK initialization: exporter configuration, tracer setup, resource definition, sampler configuration. The core package has 23 references to `OpenTelemetry\*` namespaces. This is a vendor-specific [intrusive coupling](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) embedded in a package that is otherwise framework-agnostic.

### Complexity Impact

[Volatility](https://coupling.dev/posts/dimensions-of-coupling/volatility/) is low — telemetry is a [generic subdomain](https://coupling.dev/posts/dimensions-of-coupling/volatility/) (solved problem). The coupling is technically [balanced](https://coupling.dev/posts/core-concepts/balance/) (low strength + low distance OR low volatility). However, it violates the architectural principle that core must stay free of framework concerns. If the core is ever used outside Laravel (e.g., in a Symfony app or as a standalone library), the OpenTelemetry dependency becomes a forced transitive requirement.

### Cascading Changes

- Switching from OpenTelemetry to a different tracing solution requires modifying core, not just the adapter layer
- The package split will carry this dependency into whatever package Telemetry lands in, polluting its dependency graph
- Users who don't need telemetry still transitively depend on `open-telemetry/*` packages

### Recommended Improvement

Extract a `Telemetry\TracerInterface` (or adopt PSR-22 if ratified) in core, and move `OtelSetup` to the Laravel package or a dedicated `arazzo-telemetry-otel` adapter package.

**Trade-off**: Minimal — telemetry is already low-coupling. The main cost is creating one interface class. The benefit is keeping core truly framework-agnostic.

</div>

<div class="issue">

## Issue: Policy ↔ Execution — Bidirectional Dependency

**Integration**: Policy ↔ Execution
**Severity**: <span class="severity severity-minor">Minor</span>

### Knowledge Leakage

`WorkflowEngine` (Execution) directly instantiates `RetryPolicy` (Policy), while `RetryPolicy` imports `Execution\Data\WorkflowContext` to inspect step response headers for retry-after values. Additionally, `BackoffCalculatorInterface` is defined in `Execution\Interfaces\` but implemented by `Policy\ExponentialBackoffCalculator` — a [model coupling](https://coupling.dev/posts/dimensions-of-coupling/integration-strength/) inversion.

### Complexity Impact

[Volatility](https://coupling.dev/posts/dimensions-of-coupling/volatility/) is low for Policy ([generic subdomain](https://coupling.dev/posts/dimensions-of-coupling/volatility/)) and high for Execution ([core subdomain](https://coupling.dev/posts/dimensions-of-coupling/volatility/)). The [balance rule](https://coupling.dev/posts/core-concepts/balance/) `BALANCE = (STRENGTH XOR DISTANCE) OR NOT VOLATILITY` evaluates to TRUE because Policy's low volatility neutralizes the [unbalanced](https://coupling.dev/posts/core-concepts/balance/) coupling. The coupling is tolerable in practice but will complicate the package split.

### Cascading Changes

- Moving Policy to a separate package requires `RetryPolicy` to depend on a `WorkflowContextInterface` rather than the concrete `WorkflowContext`
- The `BackoffCalculatorInterface` must be relocated to `Policy\Interfaces\` to break the interface ownership inversion
- Neither change is urgent, but both are prerequisites for the package split

### Recommended Improvement

1. Relocate `BackoffCalculatorInterface` to `Policy\Interfaces\`
2. Introduce `WorkflowContextInterface` in a shared kernel (`Spec\Interfaces`) so Policy can depend on the abstraction rather than the concrete Execution model
3. Use a factory or container to inject `RetryPolicy` into `WorkflowEngine` rather than direct instantiation

**Trade-off**: Small interface additions. The bidirectional dependency is currently harmless due to low [volatility](https://coupling.dev/posts/dimensions-of-coupling/volatility/), but fixing it now prevents a circular package dependency during the split.

</div>

---

_This analysis was performed using the [Balanced Coupling](https://coupling.dev) model by [Vlad Khononov](https://vladikk.com)._
