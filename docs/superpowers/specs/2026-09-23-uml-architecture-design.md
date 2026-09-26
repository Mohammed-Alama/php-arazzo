# UML Architectural Specification & Enhancement Design

- **Status**: Approved
- **Author**: Antigravity & Mohammed Alama
- **Date**: 2026-09-23
- **Scope**: End-to-End System Architecture across `php-arazzo` Monorepo (`contracts`, `document`, `expression`, `evaluation`, `runner`, `cli`, `laravel`, `core`)

---

## 1. Executive Summary & Goals

This specification establishes a formal UML architectural model based on the **4+1 Architectural View Model** for `php-arazzo`. The model serves two unified purposes:
1. **Architectural Diagnosis & Enhancement**: Identify and remediate architectural boundary leaks, coupling imbalances, and hidden state mutations.
2. **Deterministic Documentation & Fitness Functions**: Maintain living, machine-verifiable UML models synchronized with source code via automated reflection scripts and enforced by Pest Arch fitness functions.

---

## 2. Document & Artifact Layout

The UML suite is partitioned into **hand-authored architectural models** (`docs/architecture/uml/`) representing design intent and **machine-generated models** (`docs/generated/uml/`) representing the live code state:

```
docs/
├── architecture/
│   └── uml/
│       ├── README.md                       # Navigation hub for the 4+1 UML Views
│       ├── 01-logical-view.md              # Domain Aggregates, AST, & Class Hierarchies
│       ├── 02-development-view.md          # Package Layering, Ports & Adapters, Seams
│       ├── 03-process-view.md              # Execution Sequence & Step State Machine
│       ├── 04-physical-view.md             # Distributed Deployment, Queues, & Redis
│       └── 05-scenarios.md                 # Real-World Execution Scenarios (+1 View)
└── generated/
    └── uml/
        ├── class-diagrams.md               # Auto-extracted Mermaid class diagrams
        └── package-coupling.md             # Automated Ca, Ce, I, A, D metrics table
```

---

## 3. The 4+1 Architectural Views

### 3.1 Development View (Package & Component Architecture)
Describes the physical organization of the monorepo packages, boundary contracts, and port-and-adapter seams.

```mermaid
flowchart TD
    subgraph DeliveryAdapters ["Primary Delivery (Inbound Adapters)"]
        CLI["packages/cli<br/>(Console, Generator)"]
        LARAVEL["packages/laravel<br/>(Queue, Redis, HTTP Webhooks)"]
    end

    subgraph CoreEngine ["Core Orchestration (Application Layer)"]
        RUNNER["packages/runner<br/>(WorkflowEngine, StepExecutor, State)"]
    end

    subgraph DomainCore ["Domain & Evaluation Layer"]
        EVAL["packages/evaluation<br/>(Evaluators, Registries, Plugins)"]
        EXPR["packages/expression<br/>(Lexer, Parser, AST Nodes, Symbols)"]
        DOC["packages/document<br/>(Doc Parser, Validator, Normalizer)"]
    end

    subgraph Foundation ["Domain Foundation"]
        CONTRACTS["packages/contracts<br/>(Interfaces, Enums, DTOs, Spec)"]
    end

    subgraph SecondaryAdapters ["Outbound Infrastructure Adapters (Seams)"]
        HTTP_ADAPTER["HTTP Client Adapter<br/>(PSR-18 / Guzzle)"]
        OAS_RESOLVER["OpenAPI Resolver Adapter<br/>(Cebe parser)"]
        TEL_ADAPTER["Telemetry Adapter<br/>(OpenTelemetry)"]
    end

    CLI --> RUNNER
    CLI --> DOC
    LARAVEL --> RUNNER
    LARAVEL --> CONTRACTS

    RUNNER --> EVAL
    RUNNER --> DOC
    RUNNER --> CONTRACTS
    RUNNER -.->|Port Seam| HTTP_ADAPTER
    RUNNER -.->|Port Seam| TEL_ADAPTER

    DOC --> EXPR
    DOC --> CONTRACTS
    DOC -.->|Port Seam| OAS_RESOLVER

    EVAL --> EXPR
    EVAL --> CONTRACTS
    EXPR --> CONTRACTS

    classDef adapter fill:#f0f7ff,stroke:#0071e3,stroke-width:2px;
    classDef core fill:#f6ffed,stroke:#52c41a,stroke-width:2px;
    classDef domain fill:#fff7e6,stroke:#fa8c16,stroke-width:2px;
    classDef foundation fill:#f9f0ff,stroke:#722ed1,stroke-width:2px;
    classDef seam fill:#fff1f0,stroke:#f5222d,stroke-width:2px,stroke-dasharray: 5 5;

    class CLI,LARAVEL adapter;
    class RUNNER core;
    class EVAL,EXPR,DOC domain;
    class CONTRACTS foundation;
    class HTTP_ADAPTER,OAS_RESOLVER,TEL_ADAPTER seam;
```

#### Boundary Remediations Enforced by Development View:
1. **Runner Secondary Ports**: Introduce `Alama\Arazzo\Contracts\Http\HttpClientInterface` and `Alama\Arazzo\Contracts\Source\OpenApiResolverInterface`. Remove direct imports of `GuzzleHttp\*` and `cebe\*` from `packages/runner`.
2. **Telemetry Decoupling**: Invert `OpenTelemetry\*` behind `Alama\Arazzo\Contracts\Telemetry\TracerInterface`.
3. **Symbol Table Contract**: Move symbol table interfaces from `packages/expression` into `packages/contracts` to decouple `packages/document` from expression engine internals.

---

### 3.2 Logical View (Domain Class Models & Registries)
Defines domain aggregates, immutable value objects, AST hierarchies, and evaluator registries.

```mermaid
classDiagram
    direction TB

    class ArazzoDocument {
        +SpecVersion version
        +Info info
        +SourceDescription[] sourceDescriptions
        +Workflow[] workflows
        +Components components
        +getWorkflow(id): Workflow
    }

    class Workflow {
        +string workflowId
        +string[] dependsOn
        +Parameter[] parameters
        +Step[] steps
        +SuccessAction[] successActions
        +FailureAction[] failureActions
        +outputs: array
    }

    class Step {
        +string stepId
        +string description
        +string operationId
        +string operationPath
        +Parameter[] parameters
        +RequestBody requestBody
        +SuccessCriterion[] successCriteria
        +Action[] onEnd
        +outputs: array
    }

    class SuccessCriterion {
        +CriterionType type
        +string condition
        +Selector selector
        +matches(context): bool
    }

    class Action {
        <<abstract>>
        +ActionKind type
        +string name
    }

    class SuccessGotoAction {
        +string stepId
    }

    class RetryAction {
        +int retryAfter
        +int retryLimit
    }

    class FailureEndAction {
        +string message
    }

    ArazzoDocument *-- Workflow : contains 1..*
    Workflow *-- Step : contains 1..*
    Step *-- SuccessCriterion : validates 0..*
    Step *-- Action : triggers on result
    Action <|-- SuccessGotoAction
    Action <|-- RetryAction
    Action <|-- FailureEndAction
```

#### Evaluator Registry Model (`packages/evaluation`):
- `ExpressionEngineInterface`: Single facade for expression resolution.
- `ExpressionEvaluatorRegistry`: Open registry managing `ExpressionEvaluatorPluginInterface` implementations (JSONPath, XPath, regex).
- `CriterionEvaluatorRegistry`: Open registry managing `CriterionEvaluatorPluginInterface` implementations.

---

### 3.3 Process View (Execution Sequence & State Machines)
Captures the runtime lifecycle, step pipelines, and execution state transitions.

```mermaid
sequenceDiagram
    autonumber
    actor Caller
    participant Facade as RunnerFacade
    participant Engine as WorkflowEngine
    participant StepExec as StepExecutor
    participant Compiler as RequestCompiler
    participant Expr as ExpressionEngine
    participant Protocol as HttpStepExecutor
    participant Eval as CriteriaEvaluator
    participant Handler as StepOutcomeHandler
    participant State as ExecutionContext

    Caller->>Facade: run(workflowId, inputs)
    Facade->>Engine: executeWorkflow(workflow, inputs)
    Engine->>State: init(workflowId, inputs)

    loop For each runnable Step in DAG
        Engine->>StepExec: executeStep(step, State)
        StepExec->>Compiler: compileRequest(step, State)
        Compiler->>Expr: resolveExpressions(parameters, payload)
        Expr-->>Compiler: resolvedData
        Compiler-->>StepExec: compiledHttpRequest

        StepExec->>Protocol: execute(compiledHttpRequest)
        Protocol-->>StepExec: httpResponse

        StepExec->>Eval: evaluateCriteria(successCriteria, httpResponse)
        Eval-->>StepExec: criteriaOutcome (Pass/Fail)

        StepExec->>Handler: determineTransition(step, criteriaOutcome)
        Handler-->>StepExec: Transition (Next | Goto | Retry | End)

        StepExec->>State: recordStepResult(stepId, outputs, transition)
        StepExec-->>Engine: StepResult
    end

    Engine->>Facade: ExecutionResult
    Facade-->>Caller: ExecutionResult
```

#### Step Lifecycle State Machine:
```mermaid
stateDiagram-v2
    [*] --> Pending : Step queued in DAG
    Pending --> Resolving : Dependencies satisfied
    Resolving --> Executing : Input/Param expressions resolved
    Executing --> Evaluating : Protocol response received
    Evaluating --> Routing : Success criteria checked

    Routing --> Succeeded : Passed & Next step available
    Routing --> Retrying : Failed & retry limit not reached
    Retrying --> Resolving : Exponential backoff elapsed
    Routing --> Suspended : Awaiting webhook/correlation
    Suspended --> Resolving : Webhook event received
    Routing --> Terminated : Goto target or EndAction triggered
    Routing --> Failed : Failed & no retry/recovery

    Succeeded --> [*]
    Terminated --> [*]
    Failed --> [*]
```

---

### 3.4 Physical & Deployment View (Distributed Runtime Topology)
Models the execution infrastructure in production environments using the Laravel adapter:

```mermaid
flowchart TB
    subgraph ClientLayer ["Client / External Traffic"]
        USER["API Consumer / CLI"]
        WEBHOOK_CALLER["External Webhook Callback"]
    end

    subgraph WebServerNode ["App / Web Server Node"]
        API_CTRL["ArazzoApiController"]
        HOOK_CTRL["WebhookResumeController"]
        SP["LaravelArazzoServiceProvider<br/>(Container Bindings)"]
    end

    subgraph QueueWorkerNodes ["Distributed Queue Workers"]
        WORKER1["Queue Worker #1<br/>(RunExecuteStepJob)"]
        WORKER2["Queue Worker #2<br/>(RunResumeCorrelationJob)"]
        LOCK["LaravelRedisLockManager<br/>(Pessimistic / Distributed Lock)"]
    end

    subgraph StateAndCache ["In-Memory & Cache Tier (Redis)"]
        REDIS_STATE["RedisHotStateStore<br/>(Active ExecutionContext)"]
        REDIS_QUEUE["Redis Queue Broker"]
    end

    subgraph DatabaseTier ["Persistence Tier (RDBMS)"]
        DB_EXEC["DatabaseExecutionRegistry"]
        DB_DEF["DatabaseDefinitionRegistry"]
        DB_LEDGER["DatabaseEventLedger"]
        DB_CORR["DatabasePendingCorrelationRegistry"]
    end

    subgraph ExternalApis ["Target Infrastructure / APIs"]
        TARGET_API["Target Service APIs<br/>(via Psr18HttpClient)"]
        OAS_SOURCES["Remote OpenAPI Schemas<br/>(HTTPS / Local Fetcher)"]
    end

    USER --> API_CTRL
    WEBHOOK_CALLER --> HOOK_CTRL

    API_CTRL --> SP
    HOOK_CTRL --> SP
    SP --> REDIS_QUEUE

    REDIS_QUEUE --> WORKER1
    REDIS_QUEUE --> WORKER2

    WORKER1 <--> LOCK
    WORKER1 <--> REDIS_STATE
    WORKER1 --> TARGET_API
    WORKER1 --> DB_LEDGER
    WORKER1 --> DB_EXEC

    WORKER2 <--> REDIS_STATE
    WORKER2 --> DB_CORR

    SP --> DB_DEF
    SP --> OAS_SOURCES

    classDef web fill:#e6f7ff,stroke:#1890ff,stroke-width:2px;
    classDef worker fill:#f6ffed,stroke:#52c41a,stroke-width:2px;
    classDef storage fill:#fff7e6,stroke:#fa8c16,stroke-width:2px;
    classDef external fill:#f9f0ff,stroke:#722ed1,stroke-width:2px;

    class API_CTRL,HOOK_CTRL,SP web;
    class WORKER1,WORKER2,LOCK worker;
    class REDIS_STATE,REDIS_QUEUE,DB_EXEC,DB_DEF,DB_LEDGER,DB_CORR storage;
    class TARGET_API,OAS_SOURCES,USER,WEBHOOK_CALLER external;
```

---

### 3.5 Scenarios (+1 View)
Traces key real-world Arazzo execution scenarios across the 4 views:
1. **Sequential HTTP Step Execution**: Step A makes request $\rightarrow$ evaluates status code $\rightarrow$ records output $\rightarrow$ Step B uses `$steps.stepA.outputs.id` in path parameter.
2. **Conditional Branching via Actions**: Step returns 404 $\rightarrow$ `FailureGotoAction` directs execution to a recovery step rather than failing workflow.
3. **Asynchronous Webhook Resumption**: Step initiates an async task $\rightarrow$ records `PendingCorrelation` $\rightarrow$ suspends execution $\rightarrow$ inbound webhook hits `WebhookResumeController` $\rightarrow$ resumes state from `RedisHotStateStore`.

---

## 4. Automation & Quality Gates

### 4.1 Automated UML Extraction (`UmlClassDiagramDoc.php`)
- Integrated into `scripts/generate-docs.php` and `scripts/generate-docs/`.
- Scans classes in `packages/*/src`, extracting class names, stereotypes (`<<interface>>`, `<<abstract>>`), inheritance, implemented interfaces, and key public methods.
- Emits deterministic Mermaid `classDiagram` blocks in `docs/generated/uml/class-diagrams.md`.

### 4.2 Architectural Fitness Functions (Pest Arch)
Enforced in `packages/*/tests/ArchTest.php`:
```php
test('contracts package has zero external dependencies')
    ->expect('Alama\Arazzo\Contracts')
    ->toOnlyDependOn([]);

test('runner package does not directly reference third-party transport or schema parsers')
    ->expect('Alama\Arazzo\Runner')
    ->not->toUse(['GuzzleHttp', 'cebe\openapi']);

test('document package only depends on contracts and expression')
    ->expect('Alama\Arazzo\Document')
    ->toOnlyDependOn([
        'Alama\Arazzo\Contracts',
        'Alama\Arazzo\Expression',
    ]);
```

### 4.3 Pre-commit Verification
- Pre-commit hook triggers `composer docs` (running `scripts/generate-docs.php`).
- Output changes in `docs/generated/uml/` are automatically staged, guaranteeing zero documentation drift.
