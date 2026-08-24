# Internal Architecture Docs - Design Spec

## Objective
Create a comprehensive, centralized internal architecture documentation suite for the `php-arazzo` monorepo. The goal is to provide both high-level onboarding for new contributors and deep technical references for the engine's complex subsystems.

## Approach
**The Central Directory (Approach A)**
Documentation will be stored in a dedicated `docs/architecture/` directory using a numbered, sequential file structure. This allows developers to read the documentation linearly like a book, while keeping topics properly scoped.

## Documentation Outline & Scope

### `01-system-overview.md`
- **Purpose**: Contributor onboarding.
- **Content**: 
  - Brief explanation of the Arazzo specification mapped to PHP concepts.
  - Monorepo structure and the architectural boundary between `core` and `laravel` packages.
  - Domain glossary (e.g., Workflow, Step, Success Criteria, Source).

### `02-execution-lifecycle.md`
- **Purpose**: High-level core engine heartbeat.
- **Content**:
  - The step-by-step flow from parsing an Arazzo YAML/JSON file to instantiating the Workflow object.
  - Resolution of initial inputs.
  - The main step execution loop.

### `03-dependency-graph.md`
- **Purpose**: Technical deep-dive into orchestration.
- **Content**:
  - Analysis of `dependsOn` declarations.
  - Construction and traversal of the Directed Acyclic Graph (DAG).
  - Guaranteeing step execution order and handling upstream failures.

### `04-expression-evaluation.md`
- **Purpose**: Technical deep-dive into state and dynamic variables.
- **Content**:
  - Resolution mechanics for dynamic expressions (`$inputs`, `$steps`, `$response.body`).
  - Integration and usage of the JSONPath evaluator.
  - How state is preserved and accessed across sequential and parallel steps.

### `05-source-resolution.md`
- **Purpose**: Technical deep-dive into specification fetching.
- **Content**:
  - Architecture of the `SourceResolver`.
  - How `Fetchers` (HTTP, Local) retrieve external OpenAPI documents.
  - How `Parsers` translate those documents into a format the engine can execute.

### `06-laravel-integration.md`
- **Purpose**: Laravel bridge internals.
- **Content**:
  - Dispatching workflows to Laravel's async queues.
  - Mechanisms for Cache-based state locking during execution.
  - Service Container bindings and dependency injection strategies.
