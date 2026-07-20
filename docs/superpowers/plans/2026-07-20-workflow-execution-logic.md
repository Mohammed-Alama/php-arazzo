# Workflow Execution Logic Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the concrete execution logic for Arazzo workflows by fleshing out `ExpressionEvaluator` and `StepExecutor`.

---

### Task 1: Expand Variable Context

**Files:**
- Modify: `src/Execution/VariableContext.php`

- [ ] **Step 1: Add request and response tracking**
Update `VariableContext` to be able to store full request and response payloads, as Arazzo expressions can reference `$steps.<step>.request.body` or `$steps.<step>.response.statusCode`.

```php
    public function setStepRequest(string $stepId, array $request): void
    {
        $this->steps[$stepId]['request'] = $request;
    }

    public function setStepResponse(string $stepId, array $response): void
    {
        $this->steps[$stepId]['response'] = $response;
    }
```

### Task 2: Implement Expression Evaluator

**Files:**
- Create: `src/Execution/JsonPointer.php` (Helper)
- Modify: `src/Execution/ExpressionEvaluator.php`

- [ ] **Step 1: Write `JsonPointer` resolver**
Since Arazzo uses RFC 6901 JSON Pointers (`#/data/id`), create a helper to resolve these against PHP arrays.

- [ ] **Step 2: Implement AST visitor in `ExpressionEvaluator`**
Implement `evaluate(ExpressionAst $ast)`. It should check the AST class (e.g. `InputRef`, `StepRef`) and pull the corresponding value from `VariableContext`.

### Task 3: StepExecutor - Operation Resolution

**Files:**
- Modify: `src/Execution/StepExecutor.php`

- [ ] **Step 1: Inject `SourceResolver`**
Add `\Alama\LaravelArazzo\Resolution\SourceResolver` to the constructor.

- [ ] **Step 2: Fetch OpenAPI Document**
In `execute()`, resolve the step's operation (using `$step->operationId` or `$step->operationPath`) from the OpenAPI specification to determine the HTTP method and URL.

### Task 4: StepExecutor - Request Building

**Files:**
- Modify: `src/Execution/StepExecutor.php`

- [ ] **Step 1: Resolve Parameters**
Iterate over `$step->parameters`, evaluate them using `ExpressionEvaluator`, and group them into `query`, `header`, and `path`.

- [ ] **Step 2: Build HTTP Request**
Replace path variables in the URL. Build the PSR-7 `RequestInterface` using the injected `RequestFactoryInterface`.

### Task 5: StepExecutor - Execution & Outputs

**Files:**
- Modify: `src/Execution/StepExecutor.php`

- [ ] **Step 1: Send Request & Log**
Send the built request using PSR-18 `ClientInterface`. Store the request and response in `VariableContext` via `setStepRequest` and `setStepResponse`.

- [ ] **Step 2: Extract Outputs**
Iterate over `$step->outputs`, evaluate each expression against the updated context, and return them in the `StepResult`.

- [ ] **Step 3: Commit Progress**
Commit the completed execution engine.
```bash
rtk proxy git add src/Execution
rtk proxy git commit -m "feat: Implement execution logic for WorkflowExecutor"
```
