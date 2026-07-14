# Laravel Arazzo — Workflow Runner (v1) Design

**Status**: Draft
**Created**: 2026-07-14
**Package**: `alama/laravel-arazzo`
**Namespace**: `Alama\LaravelArazzo\Runner`
**Slice**: Workflow Execution (Runner).

---

## 1. Goals & Non-Goals

### Goals

- Execute workflows defined in a validated `ArazzoDocument`.
- Safely evaluate Arazzo runtime expressions (`{$inputs.x}`, `{$steps.login.outputs.token}`) using the AST built by the parser.
- Resolve API endpoints and construct HTTP requests by integrating with the `SourceResolver`.
- Handle execution flow control (`goto`, `end`, `retry`) via procedural loop.
- Provide a generic `HttpClient` interface decoupled from the framework, with a default Laravel implementation.

### Non-Goals

- Concurrency or asynchronous execution of multiple workflows (workflows execute synchronously in v1).
- Visualizing the execution (React Flow UI is a separate spec).
- Generating specs.

---

## 2. Architecture & Interfaces

The Runner operates through a few highly focused services.

```
┌─────────────────────────────────────────────────────────┐
│                     WorkflowRunner                      │
│   (Maintains ExecutionContext, handles the `while` loop)│
└────────┬─────────────────────────────────────┬──────────┘
         │                                     │
┌────────▼────────┐                 ┌──────────▼──────────┐
│  StepExecutor   │                 │    AstEvaluator     │
│  (Builds & sends│                 │  (Traverses AST &   │
│   HTTP requests)│                 │   extracts state)   │
└────────┬────────┘                 └──────────┬──────────┘
         │                                     │
┌────────▼────────┐                 ┌──────────▼──────────┐
│   HttpClient    │                 │   ExecutionContext  │
│  (Interface)    │                 │   (Mutable state)   │
└─────────────────┘                 └─────────────────────┘
```

### Core Components

```php
interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;
}

class ExecutionContext
{
    public array $inputs = [];
    public array $steps = []; // Map of stepId => ['outputs' => [...]]
    public ?HttpResponse $response = null; // Response of the current step
}

class WorkflowRunner
{
    public function execute(ArazzoDocument $doc, string $workflowId, array $inputs): WorkflowResult;
}
```

---

## 3. State Management & Expression Evaluation

### ExecutionContext
A mutable object passed through the workflow. It accumulates the `inputs` provided at execution time, and as steps complete, their `outputs` are merged into the `$steps` array. This provides a single source of truth for the evaluator.

### AstEvaluator
Rather than using brittle regex replacements, the `AstEvaluator` leverages the `ExpressionAst` produced by the parser. It visits AST nodes (`InputRef`, `StepRef`, `ResponseRef`) and plucks the corresponding values from the `ExecutionContext`.

---

## 4. Request Building & Resolution

The `StepExecutor` handles the lifecycle of a single step:

1. **Resolution**: Uses the `SourceResolver` to fetch the OpenAPI document associated with the step's `operationId` or `operationPath`. Extracts the target URL and HTTP method.
2. **Hydration**: Iterates over `step.parameters` and `step.requestBody`. Uses the `AstEvaluator` to resolve dynamic expressions.
3. **Execution**: Builds an `HttpRequest` object and passes it to the `HttpClient`. The resulting `HttpResponse` is saved into the `ExecutionContext`.

---

## 5. Flow Control & Criteria Evaluation

Arazzo's flow control is linear by default, but allows branching.

1. **Evaluation**: After the HTTP response is received, the `CriteriaEvaluator` checks `step.successCriteria` using the `AstEvaluator`.
2. **Action Determination**: 
   - If criteria pass: Step is marked successful. `step.outputs` are evaluated and saved to the `ExecutionContext`. Determines the next action from `onSuccess` (defaulting to the next sequential step).
   - If criteria fail: Determines the next action from `onFailure`.
3. **The Loop**: The `WorkflowRunner` uses a procedural `while(true)` loop to process steps. It reacts to the determined actions:
   - `goto <stepId>`: Updates the current pointer to `<stepId>`.
   - `end`: Breaks the loop, marks workflow complete.
   - `retry`: Delays (if `retryAfter` is set) and re-executes the current step (decrements a local retry counter).
   - `next`: Increments pointer to the next sequential step.

---

## 6. Error Handling

Exceptions during execution represent terminal failures:
- `ExecutionException`: Base exception.
- `EvaluationException`: Thrown by `AstEvaluator` if an expression references a missing value.
- `HttpTransportException`: Thrown if the `HttpClient` fails at the network level (timeouts, DNS errors). Note: 4xx/5xx responses are *not* exceptions; they are valid `HttpResponse` objects evaluated by `successCriteria`.
- `MaxRetriesExceededException`: Thrown when a step's `retryLimit` is breached.
