# Domain Context Map

## Package Topology (alama/arazzo-core split)

The monorepo has been split into 6 focused packages with clear layering boundaries:

### `alama/arazzo-contracts`
- **Purpose**: Core data models, DTOs, and shared interfaces
- **Key contents**: Spec DTOs (`Expression`, `Step`, `Workflow`, `RawDocument`), Dependency Graph, State DTOs (`WorkflowContext`), Implicit Dependencies, Shared contracts
- **Layer**: Foundation — depends on nothing; no other `Alama\Arazzo` packages

### `alama/arazzo-expression`
- **Purpose**: Expression evaluation engine
- **Key contents**: `ExpressionEngineInterface` + `ExpressionEngine` (self-contained facade), `ExpressionEvaluator`, `ExpressionResolver`, `EvaluationInput`, `StepOutputExtractor`, `CriteriaEvaluator`, `ResponseSchemaValidator`, JsonPath evaluator, String interpolator
- **Layer**: Expression — depends on `contracts` + PSR-3 event-dispatcher + PSR-3 logger + jsonpath; facade `ExpressionEngine` hides the evaluation graph

### `alama/arazzo-document`
- **Purpose**: Arazzo document loading, parsing, validation, and preflight
- **Key contents**: `DocumentInterface` + `Document` (self-contained: Loader → Parser → Validator → PreflightValidator), `RuleSet::default()`, `SourceRegistry`, `DefaultSourceResolver`, `OpenApiOperationResolver`, `OpenApiVersionDetector`, `OpenApi30Normalizer`, `OpenApi31Normalizer`, `DomXpathEvaluator`
- **Layer**: Document — depends on `contracts` + `expression` + cebe json-schema + jsonpath + symfony/yaml + psr/log + psr/event-dispatcher

### `alama/arazzo-runner`
- **Purpose**: Workflow execution engine
- **Key contents**: `RunnerInterface` + `Runner` (self-contained graph: `DefaultOpenApiExecutor` + `ExpressionResolver` + `OpenApiOperationResolver` + `WorkflowEngine` + `PreflightValidator`), `StepExecutor`, `WorkflowEngine`, `WorkflowExecutor`, `StepOutputExtractor`, `CriteriaEvaluator`, `ResponseSchemaValidator`, `DefaultOpenApiExecutor`
- **Layer**: Runner — depends on `contracts` + `expression` + `document` + guzzle + otel + psr; facade `Runner` hides the full execution graph

### `alama/arazzo-cli`
- **Purpose**: Command-line tool for running Arazzo workflows
- **Key contents**: `RunCommand` (authoritative self-contained runner: `new Client()`, `HttpFactory`, `SourceRegistry`, `ExpressionEvaluator`, `OpenApiOperationResolver`, `ExpressionResolver`, `StepExecutor`, `WorkflowEngine`, `PreflightValidator`)
- **Layer**: CLI — depends on all lower packages; entry point for CLI usage

### `alama/laravel-arazzo`
- **Purpose**: Laravel integration package
- **Key contents**: Facade bindings (`ExpressionEngineInterface → ExpressionEngine`, `DocumentInterface → Document`, `RunnerInterface → Runner` via `FacadeBindings.php`), `ExecutionBindings` (engine, executors, outcome handling, resumption), `ResolverBindings` (source registry, operation resolver), `HttpBindings`, `EventBindings`, `PersistenceBindings`, `GeneratorBindings`
- **Layer**: Laravel — depends on all core packages; binds each `*Interface` → self-contained concrete facade

## Layering Order (bottom to top)

```
Contracts  ←  Expression  ←  Document  ←  Runner  ←  CLI
      ↑                                   ↑
  Laravel bindings                       (self-contained facades)
```

## Facade Seams (Task 5 entry points)

Each package exposes a single entry-point interface that hides its internal graph:

| Package | Interface | Facade | What it hides |
|---|---|---|---|
| `expression` | `Alama\Arazzo\Expression\ExpressionEngineInterface` | `Alama\Arazzo\Expression\ExpressionEngine` | Evaluator, Xpath, AST, inputs, outputs, criteria, validation |
| `document` | `Alama\Arazzo\Document\DocumentInterface` | `Alama\Arazzo\Document\Document` | Loader, Parser, Validator, PreflightValidator, RuleSet |
| `runner` | `Alama\Arazzo\Runner\RunnerInterface` | `Alama\Arazzo\Runner\Runner` | WorkflowEngine, Executors, state machine, protocol, async |

Cross-package edges must point only at `*Interface` entry points; never another package's concrete Facade.

## Glossary

- **Expression Evaluator**: The low-level module that parses and resolves an Arazzo Expression string against the current Workflow Context (via `ExpressionEvaluator`).
- **Request Compiler**: The module that takes an Arazzo Step and evaluates its inputs to produce a concrete HTTP Request (via `DefaultOpenApiExecutor` + PSR-7 client).
- **Output Extractor**: The module that processes an HTTP Response and extracts values into the Workflow Context based on the Step's output definitions (via `StepOutputExtractor` + `ExpressionResolver`).
- **Criteria Evaluator**: The module that evaluates Success Criteria expressions for a Step to determine if the execution succeeded (via `CriteriaEvaluator`).
- **Schema Validator**: The module that validates an HTTP Response body against an expected OpenAPI schema (via `ResponseSchemaValidator`).
- **Preflight Validator**: The module that audits a document/workflow before execution to ensure no side effects (via `PreflightValidator` with `SourceRegistry` + `OpenApiOperationResolver` + `DomXpathEvaluator`).
- **Workflow Engine**: The module that orchestrates step execution, retry logic, and state management (via `WorkflowEngine` wrapping `ExpressionResolver`).
- **Step Executor**: The module that executes a single Step against a Workflow Context (via `StepExecutor` wrapping `DefaultOpenApiExecutor` + `ExpressionResolver` + `OpenApiOperationResolver`).
- **Source Registry**: The module that resolves source URLs (http/https/file) through fetcher implementations (`HttpFetcher`, `LocalFetcher`).
- **OpenApiOperationResolver**: The module that resolves operation details from an OpenAPI document (via `OpenApiDocumentLoader` + `OpenApiVersionDetector` + `OpenApi30Normalizer` + `OpenApi31Normalizer`).