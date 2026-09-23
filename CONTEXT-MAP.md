# Domain Context Map

## Package Topology (alama/arazzo-core split)

The monorepo has been split into 7 focused packages with clear layering boundaries:

### `alama/arazzo-contracts`

- **Purpose**: Core data models, DTOs, spec expression grammar/AST, and shared interfaces
- **Key contents**: Spec DTOs (`Expression`, `Step`, `Workflow`, `RawDocument`), Spec Expression Grammar (`Lexer`,
  `Parser`, `ExpressionAst`, `Token`), Dependency Graph, State DTOs (`WorkflowContext`, `ExecutionResult`), Implicit
  Dependencies, Shared contracts
- **Layer**: Foundation — depends on nothing; no other `Alama\Arazzo` packages

### `alama/arazzo-expression`

- **Purpose**: Static Arazzo expression syntax, lexing, parsing, AST, symbol tables, and reference inspection
- **Key contents**: `ExpressionEngineInterface` + `ExpressionEngine` (static analysis facade), `Lexer`, `Parser`,
  AST nodes, `SymbolTable`, `ExpressionInspector`, `Data\ExpressionReference`
- **Layer**: Expression — depends on `contracts` only; facade `ExpressionEngine` parses expressions, projects
  references statically, and builds the document symbol table

### `alama/arazzo-evaluation`

- **Purpose**: Runtime dynamic evaluation of Arazzo expressions against workflow context
- **Key contents**: `EvaluationEngineInterface` + `EvaluationEngine` (runtime facade), `ExpressionEvaluator`,
  `CriteriaEvaluator`, `SelectorEvaluator` (JSONPath, XPath, JSON Pointer), `StringInterpolator`, `PayloadReplacer`
- **Layer**: Evaluation — depends on `contracts` + `expression` + PSR-3 event-dispatcher + PSR-3 logger + jsonpath;
  facade `EvaluationEngine` evaluates expressions, criteria, selectors, interpolation, and payload replacement at runtime

### `alama/arazzo-document`

- **Purpose**: Arazzo document loading, parsing, validation, and preflight
- **Key contents**: `DocumentInterface` + `Document` (self-contained: Loader → Parser → Validator → PreflightValidator),
  `DocumentSymbols`, `RuleSet::default()`, `SourceRegistry`, `DefaultSourceResolver`, `OpenApiOperationResolver`,
  `OpenApiVersionDetector`, `OpenApi30Normalizer`, `OpenApi31Normalizer`
- **Layer**: Document — depends on `contracts` + `expression` + cebe json-schema + jsonpath + symfony/yaml + psr/log +
  psr/event-dispatcher (zero dependency on `evaluation`; validation is a static-domain concern)

### `alama/arazzo-runner`

- **Purpose**: Workflow execution engine
- **Key contents**: `RunnerFacadeInterface` + `RunnerFacade` (adapter-driven facade: `run()` & `resume()`),
  `ExecutionGraphFactory`, `WorkflowExecutor`, `StepExecutor`, `WorkflowEngine`, `StepExecutionWorker`,
  `PreflightValidator`, `OpenApiOperationResolver`
- **Layer**: Runner — depends on `contracts` + `document` + `evaluation` + guzzle + otel + psr; facade `RunnerFacade`
  hides the full execution graph behind external adapters

### `alama/arazzo-cli`

- **Purpose**: Command-line tool for running Arazzo workflows
- **Key contents**: `RunCommand` (consumes `RunnerFacadeInterface` directly with CLI adapters)
- **Layer**: CLI — depends on `runner` and `document`; thin adapter layer for CLI usage

### `alama/laravel-arazzo`

- **Purpose**: Laravel integration package
- **Key contents**: Facade bindings (`ExpressionEngineInterface → ExpressionEngine`,
  `EvaluationEngineInterface → EvaluationEngine`, `DocumentInterface → Document`,
  `RunnerFacadeInterface → RunnerFacade`), `ExecutionBindings` (registers external framework adapters into
  `RunnerFacade`), `PersistenceBindings`, `HttpBindings`, `EventBindings`
- **Layer**: Laravel — depends on all core packages; binds each `*Interface` to its concrete facade and passes Laravel
  adapters

## Layering Order (diamond foundation)

```
                   ┌────────────────────────┐
                   │    arazzo-contracts    │
                   │    Spec DTOs & Grammar │
                   └───────────▲────────────┘
                               │
                ┌──────────────┴──────────────┐
                │                             │
     ┌──────────┴─────────┐        ┌──────────┴──────────┐
     │   arazzo-document  │        │  arazzo-expression  │
     │  Parsing, Validation│       │  Syntax, AST, Lexer │
     │    (Static Domain) │        │    (Static Domain)  │
     └──────────▲─────────┘        └──────────▲──────────┘
                │                             │
                └──────────────┬──────────────┘
                               │
                    ┌──────────┴──────────┐
                    │  arazzo-evaluation  │
                    │    Runtime Engine   │
                    └──────────▲──────────┘
                               │
                    ┌──────────┴──────────┐
                    │     arazzo-runner   │
                    │  Execution Engine   │
                    └──────────▲──────────┘
                               │
                     ┌─────────┴─────────┐
                     │                   │
              ┌──────┴──────┐     ┌──────┴──────┐
              │ arazzo-cli  │     │laravel-arazzo│
              └─────────────┘     └─────────────┘
```

## Facade Seams

Each package exposes a single entry-point interface that hides its internal graph:

| Package       | Interface                                              | Facade                                        | What it hides                                                          |
|---------------|--------------------------------------------------------|-----------------------------------------------|----------------------------------------------------------------------|
| `expression`  | `Alama\Arazzo\Expression\ExpressionEngineInterface`    | `Alama\Arazzo\Expression\ExpressionEngine`    | Lexer, Parser, AST, SymbolTable, reference inspection (static domain) |
| `evaluation`  | `Alama\Arazzo\Evaluation\EvaluationEngineInterface`   | `Alama\Arazzo\Evaluation\EvaluationEngine`   | Evaluator, XPath, JSONPath, criteria, runtime payload replacer        |
| `document`    | `Alama\Arazzo\Document\DocumentInterface`              | `Alama\Arazzo\Document\Document`              | Loader, Parser, Validator, DocumentSymbols, PreflightValidator, RuleSet |
| `runner`      | `Alama\Arazzo\Runner\RunnerFacadeInterface`            | `Alama\Arazzo\Runner\RunnerFacade`            | WorkflowExecutor, StepExecutor, WorkflowEngine, State Machine, Protocol, Async Workers |

Cross-package edges must point only at `*Interface` entry points; never another package's concrete Facade.

## Glossary

- **Expression Evaluator**: The low-level module that parses and resolves an Arazzo Expression string against the
  current Workflow Context (via `ExpressionEvaluator`).
- **Request Compiler**: The module that takes an Arazzo Step and evaluates its inputs to produce a concrete HTTP
  Request (via `DefaultOpenApiExecutor` + PSR-7 client).
- **Output Extractor**: The module that processes an HTTP Response and extracts values into the Workflow Context based
  on the Step's output definitions (via `StepOutputExtractor` + `ExpressionResolver`).
- **Criteria Evaluator**: The module that evaluates Success Criteria expressions for a Step to determine if the
  execution succeeded (via `CriteriaEvaluator`).
- **Schema Validator**: The module that validates an HTTP Response body against an expected OpenAPI schema (via
  `ResponseSchemaValidator`).
- **Preflight Validator**: The module that audits a document/workflow before execution to ensure no side effects (via
  `PreflightValidator` with `SourceRegistry` + `OpenApiOperationResolver` + `DomXpathEvaluator`).
- **Workflow Engine**: The module that orchestrates step execution, retry logic, and state management (via
  `WorkflowEngine` wrapping `ExpressionResolver`).
- **Step Executor**: The module that executes a single Step against a Workflow Context (via `StepExecutor` wrapping
  `DefaultOpenApiExecutor` + `ExpressionResolver` + `OpenApiOperationResolver`).
- **Source Registry**: The module that resolves source URLs (http/https/file) through fetcher implementations (
  `HttpFetcher`, `LocalFetcher`).
- **OpenApiOperationResolver**: The module that resolves operation details from an OpenAPI document (via
  `OpenApiDocumentLoader` + `OpenApiVersionDetector` + `OpenApi30Normalizer` + `OpenApi31Normalizer`).
- **DocumentSymbols**: An internal index of workflow IDs, step IDs, outputs, sources, and component keys built from
  `ArazzoDocument` solely for document validation rules.
- **RunnerFacade**: The authoritative adapter-driven entry point for workflow execution, accepting external adapters (
  HTTP client, queue, state store, logger) and exposing `run()` and `resume()` returning typed `ExecutionResult`.
