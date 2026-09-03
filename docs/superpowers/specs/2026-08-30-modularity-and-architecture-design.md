# Modularity and Architecture Design

## Context & Goals
The `php-arazzo` package is a durable workflow engine that parses, validates, and executes Arazzo specifications. The current architecture suffers from layering violations (e.g., horizontal layers like `Interfaces` depending on higher-level domains like `Spec` or `Normalizer`). 
The goal is to restructure the architecture into a **Shared Kernel + Vertical Domains** model to eliminate circular dependencies, ensure a strict one-way data flow, and provide structured error handling suited for autonomous agents driving the workflows.

## Architecture: Shared Kernel + Vertical Domains

### 1. The Shared Kernel (Layer 0)
Foundational components that flow through the entire system. These components have zero dependencies on higher-level modules.
*   **`Alama\Arazzo\Spec`**: The immutable AST (Abstract Syntax Tree) representing the parsed Arazzo document (e.g., `Workflow`, `Step`).
*   **`Alama\Arazzo\State`**: Durable state definitions (e.g., `ExecutionContext`, `StepResult`) tracking workflow progress.
*   **`Alama\Arazzo\Contracts`**: Pure base interfaces (e.g., a marker `ArazzoException`) with no external domain coupling.

### 2. Vertical Domains (The Pipeline)
Self-contained modules that own their internal logic, interfaces, and exceptions. They depend downward on the Shared Kernel, never upward or horizontally (unless strictly architected).
*   **`Alama\Arazzo\Parser`**: Converts YAML/JSON to `Spec`. Owns decoding interfaces (`JsonDecoderInterface`).
*   **`Alama\Arazzo\Resolver`**: Fetches external OpenAPI/Arazzo sources. Owns `SourceFetcherInterface`.
*   **`Alama\Arazzo\Validator`**: Asserts structural/logical soundness of the `Spec`.
*   **`Alama\Arazzo\Expression`**: Evaluates `{$...}` syntax against the `State`.
*   **`Alama\Arazzo\Execution`**: The durable execution engine. Manages HTTP calls, async suspensions, and state transitions.

## Data Flow & Error Handling

### 1. One-Way Execution Pipeline
1.  **Parse:** Raw string → `Parser` → `Spec`.
2.  **Resolve & Preflight:** `Spec` → `Resolver` (fetches sources) → `Validator` (returns a comprehensive `ValidationResult`).
3.  **Execute:** `Spec` + Inputs + `State` → `Execution` engine. The engine coordinates with `Expression` to resolve runtime values and suspends durably when reaching async boundaries.

### 2. Domain-Specific Error Handling (For Agents)
To provide agents with actionable feedback, exceptions are highly structured:
*   **Marker Interface:** All engine exceptions implement a base `ArazzoException` from the Shared Kernel.
*   **Module Ownership:** Each domain throws specific exceptions (e.g., `SyntaxErrorException` with line numbers, `UnresolvableSourceException` with the URI, `EvaluationException`).
*   **State Trapping:** The `Execution` engine does not crash the PHP process on step failure (e.g., HTTP 500 or bad expression). Instead, it traps the domain exception, marks the `StepResult` as `failed`, and attaches the structured error payload to the `State`. This allows an agent to inspect the failure, correct external systems, and resume the durable workflow.

## Testing Strategy & Conformance

1.  **Domain-Isolated Unit Testing:** Modules like `Validator` and `Expression` are tested in pure isolation by passing them in-memory `Spec` objects. No heavy mocking required.
2.  **Execution Engine Testing:** Tested using a real `Spec` and `Expression` evaluator, but with a mock PSR-18 HTTP Client and PSR-14 Event Dispatcher to simulate real-world workflow suspensions and continuations.
3.  **OAI Conformance Harness:** The ultimate integration test. Runs the official OAI test corpus through the entire `Parser` → `Resolver` → `Validator` → `Execution` pipeline to guarantee spec compliance and validate the architectural boundaries.
