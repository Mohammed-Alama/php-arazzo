# Design — Plugin Stack, OMS Pause/Resume, Multi-Protocol Steps

Date: 2026-09-08
Issue: to be created — the Phase tickets A–H below are the issue breakdown

## Context

The Arazzo 1.1+ roadmap adds new Step targets and protocols — SOAP, gRPC,
GraphQL, MCP/A2A, and actor-in-the-loop (human or agent approval). Today, this
monorepo executes steps over HTTP/OpenAPI (plus an AsyncAPI `send`/`receive`
path) through a small protocol-executor layer, and pause/resume exists as a
scattered async-only suspension story (`SuspensionHandler`,
`CorrelationResumer`, `PendingCorrelationRegistryInterface`). The public-face
boundary work (#58–#63) is landed, so this design is **additive** on top of the
published `alama/*` package contracts — it renames nothing, re-homes only
`@internal` types, and keeps the engine hot path zero-vendor.

Three forces shape this design:

1. **Zero-overhead core** — `arazzo-contracts` and the `arazzo-runner` engine
   hot path must stay vendor-free (PSR interfaces only). Guzzle, JSONPath, and
   future gRPC/proto codecs live in dedicated sub-packages behind plugins.
2. **One public face per package** (#63) — new SPI is added to `contracts`;
   existing public faces and value types stay public. New faces are deliberate,
   reviewed additions to `docs/generated/public-api.md`.
3. **Spec vocabulary** — Arazzo calls the Step target an *Operation*; the
   emerging 1.2 binding work (OAI/Arazzo-Specification#523) names the protocol
   profile a *Binding*. We adopt these words and the repo's established word
   *Executor* (see `docs/generated/ubiquitous-language-audit.md`).

## Vocabulary (locked)

- **Operation** — the Arazzo domain noun a Step targets (`operationId`,
  `operationPath`, `workflowId`; future `functionId` + binding).
- **Executor** — the runtime unit that runs an Operation for a given protocol.
- **Binding** — the protocol profile that parameterizes how an Operation
  executes (HTTP, SOAP, gRPC, GraphQL; MCP/A2A later).
- **Plugin** — a named, priority-ordered extension unit behind a contracts SPI.
- SPI seam: `OperationExecutorPluginInterface` in `contracts`; the existing
  `StepProtocolExecutorInterface` remains as a `@deprecated` alias.

## Goals

- Steps can target Operations over SOAP, gRPC and GraphQL in addition to
  HTTP/OpenAPI and AsyncAPI, selected by a protocol-agnostic executor registry.
- `arazzo-contracts` and the `arazzo-runner` engine hot path are vendor-free;
  vendored evaluators/transports live in sub-packages behind the SPI.
- Step execution is an explicit OMS state machine — `PENDING → EXECUTING_REQUEST
  → EVALUATING_CRITERIA → (AWAITING_ACTOR_INPUT → ACTOR_INPUT_RECEIVED) …
  → COMPLETED / FAILED` — with the `WorkflowContextInterface` transfer serialized
  by a `WorkflowStateRepositoryInterface` for safe pause/resume.
- The sync (`WorkflowExecutor`) and async (`StepExecutionWorker` /
  `StepOutcomeHandler`) loop carriers are consolidated onto one state machine,
  killing the sync/async divergence tracked in the roadmap (C11).
- Laravel auto-registers plugins via container tagging and a `PluginRegistry`.

## Non-goals

- No composer-package renames (`arazzo-runner`/`arazzo-expression` keep their
  published names; roles are mapped, not renamed).
- No changes to existing public faces' signatures (`RunnerFacadeInterface`,
  `ExpressionEngineInterface`, `DocumentInterface`) — additive only.
- No MCP/A2A Step targets in this iteration (they ride the same SPI later).
- No OTEL `grpc-trace-bin` propagation (noted as deferred edge).
- No changes to `arazzo-document`'s parsing deps (document is the parsing
  layer; zero-vendor scope is contracts + engine hot path).

## Decisions (locked with stakeholders)

| # | Decision |
|---|---|
| D1 | Map roles onto existing packages; no composer renames. New SPI via new interfaces. |
| D2 | `WorkflowStateRepositoryInterface` persists the serialized `WorkflowContextInterface` transfer plus a versioned envelope with the current `StepState`. |
| D3 | Zero-vendor core = `contracts` + `runner` engine hot path. Document/CLI keep parsing deps. |
| D4 | SOAP is the reference transport, fully fleshed, zero-vendor (DOM + PSR-18). |
| D5 | gRPC is planned fully: `.proto` source normalizer + `GrpcOperationExecutor`; optional proto vendor lives only in the gRPC sub-package. |
| D6 | Protocol-typed response handling is **additive**: a new `ResponseTransfer` value type; the existing `EvaluationInput` DTO is untouched. |

## Architecture

### Package map (roles, names kept)

| Package | Role | Dependency change |
|---|---|---|
| `alama/arazzo-contracts` | SPI + value DTOs + Transfers + enums | none (PSR-only) |
| `alama/arazzo-expression` | evaluator core + `ExpressionEngine` facade | drops `softcreatr/jsonpath` |
| `alama/arazzo-evaluator-jsonpath` (new) | JSONPath expression + criterion plugins | owns `softcreatr/jsonpath` |
| `alama/arazzo-runner` | engine: OMS state machine, executor registry, persistence | drops Guzzle/OTEL from hot path |
| `alama/arazzo-operation-executor-http` (new) | `HttpOperationExecutor` + `SoapOperationExecutor` (reference) | owns Guzzle wiring; SOAP is DOM + PSR-18 |
| `alama/arazzo-operation-executor-grpc` (new) | `GrpcOperationExecutor` (full) | owns proto codec (optional vendor allowed here) |
| `alama/arazzo-operation-executor-graphql` (new) | `GraphQlOperationExecutor` | PSR-18 only |
| `alama/arazzo-document` | source loading/normalization + validation | adds WSDL/proto/SDL source normalizers |
| `alama/arazzo-cli` / `alama/arazzo-core` / `alama/laravel-arazzo` | console / umbrella / bridge | require new sub-packages; tagging |

### Public SPI additions (contracts)

```php
interface PluginInterface
{
    public function name(): string;
    public function priority(): int;
}

interface OperationExecutorPluginInterface extends PluginInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}

interface CriterionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(CriterionType|SuccessCriterion $criterion): bool;
    public function evaluate(SuccessCriterion $criterion, mixed $context, Step $step, WorkflowContextInterface $workflowContext): bool;
}

interface ExpressionEvaluatorPluginInterface extends PluginInterface
{
    public function supports(ExpressionReference $expression): bool;
    public function evaluate(ExpressionReference $expression, EvaluationInputInterface $context): mixed;
}

interface WorkflowStateRepositoryInterface
{
    public function save(string $executionId, WorkflowContextInterface $state): void;
    public function load(string $executionId): ?WorkflowContextInterface;
    public function delete(string $executionId): void;
}

enum StepState: string
{
    case PENDING = 'pending';
    case EXECUTING_REQUEST = 'executing_request';
    case EVALUATING_CRITERIA = 'evaluating_criteria';
    case AWAITING_ACTOR_INPUT = 'awaiting_actor_input';
    case ACTOR_INPUT_RECEIVED = 'actor_input_received';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
```

`ResponseTransfer` (new public value type in contracts) carries the
protocol-typed response:

```php
final readonly class ResponseTransfer
{
    public function __construct(
        public readonly mixed $status,
        public readonly array $headers,
        public readonly mixed $rawBody,
        public readonly ?array $json = null,                  // HTTP/GraphQL body view
        public readonly ?DOMDocument $xml = null,             // SOAP envelope view
        public readonly ?object $proto = null,                // decoded gRPC message
        public readonly ?array $graphQlErrors = null,         // GraphQL top-level errors
    ) {}
}
```

`StepProtocolExecutorInterface`, `OpenApiExecutorInterface` and the existing
`ProtocolExecutorRegistryInterface` stay; the new `OperationExecutorRegistry`
supersedes them and both interfaces are accepted so the existing executors
(`HttpStepExecutor`, `AsyncApiStepExecutor`, `SubWorkflowExecutor`) keep working
while being migrated.

## The transfer-encoding axis

Runtime-expression vocabulary today is HTTP-shaped (`$response.body`,
`$response.header.*`, `$statusCode`, `$message.payload`). Protocol-typed views
are required:

| Transfer facet | HTTP/GraphQL | SOAP | gRPC |
|---|---|---|---|
| `$response.body` | JSON body | XML Envelope/Body (DOM view) | decoded protobuf message |
| status | `$statusCode` | HTTP 200 + SOAP Fault (`faultcode`/`faultstring`) | `grpc-status` / `grpc-message` trailers |
| headers | `$response.header.*` | SOAP headers (same HTTP channel) | gRPC metadata |
| selectors | jsonpath / xpath | xpath on DOM | jsonpath on decoded message |
| payload replacement target | JSON Pointer, XPath | XPath into envelope | proto field setter |
| criteria context | `$response.body` | DOM node | decoded message |

Extension plan: widen `ReferenceKind`, route evaluation through
`ResponseTransfer` views, and make `PayloadReplacer` target resolution
protocol-aware behind a `ReplacementTargetResolver` port.

## Phase tickets

### Phase A — Contracts SPI (zero-dep preserved)
- **A1** `PluginInterface` + `OperationExecutorPluginInterface`; deprecate
  `StepProtocolExecutorInterface`.
- **A2** `CriterionEvaluatorPluginInterface` + `ExpressionEvaluatorPluginInterface`.
- **A3** `StepState` enum (above); `Retrying` mapped to an edge
  `EVALUATING_CRITERIA → PENDING`, not a state.
- **A4** `WorkflowStateRepositoryInterface` (versioned envelope).
- **A5** `ResponseTransfer` value type (additive).

### Phase B — Expression language (transfer-encoding axis)
- **B1** Widen `ReferenceKind`/grammar: `$response.soap.fault`,
  `$response.grpcStatus`, `$response.grpcMessage`, `$response.errors`.
- **B2** `ExpressionEngine`/`SelectorEvaluator`/`CriteriaEvaluator` consult
  `ResponseTransfer` views when the base body isn't JSON.
- **B3** `PayloadReplacer` target resolution through `ReplacementTargetResolver`.
- **B4** Criteria evaluation receives the typed view (DOM node / message).

### Phase C — Evaluator plugins + vendor isolation
- **C1** New `alama/arazzo-evaluator-jsonpath`: move `JsonPathEvaluator` +
  `softcreatr/jsonpath`; expose jsonpath expression + criterion plugins.
  `arazzo-expression` drops the vendor dep.
- **C2** `ExpressionEvaluatorRegistry` + `CriterionEvaluatorRegistry`
  (priority-ordered, first-match) assembled by the `ExpressionEngine` facade.
- **C3** Refactor `CriteriaEvaluator`'s hard-coded `match`: simple/regex/xpath
  in-core; jsonpath + future types via plugins (typed "unsupported criterion"
  error without the plugin).

### Phase D — Document: multi-source operation resolution
- **D1** Source-type registry: `SourceResolver`/`SourceRegistry`/fetchers
  resolve `wsdl`, `proto`, `graphql` alongside `openapi`/`asyncapi`.
- **D2** `WsdlSourceNormalizer` (DOM, no vendor) → `ResolvedOperation`
  (endpoint, binding, SOAPAction, message parts). Reference normalizer.
- **D3** `ProtoSourceNormalizer` (full): proto3 parser → operations +
  messages/methods; own parser; proto vendor optional in document package.
- **D4** `GraphQlSchemaNormalizer`: SDL tokenizer → operations.
- **D5** `RuleSet` validation rules for new step forms/source types;
  `ResolvedOperation` gains a binding field.

### Phase E — Runner: OMS engine + executor registry
- **E1** `StepStateMachineEngine`: explicit transition table over `StepState`;
  guards delegate to pure `WorkflowEngine::transition()` (budget/deps/actions
  stay protocol-agnostic). Enter-handlers for each state.
- **E2** `StoredWorkflowStateRepository` over `StateStoreInterface`; versioned
  envelope; backward-compatible loader for existing raw
  `WorkflowContext::toArray()` payloads.
- **E3** Consolidate sync `WorkflowExecutor` + async `StepExecutionWorker` /
  `StepOutcomeHandler` onto one carrier (kills C11 divergence).
- **E4** `OperationExecutorRegistry` (first-`supports()` wins) replaces direct
  `openApiExecutor` calls; composition roots feed from the registry.
- **E5** Binding-aware `RequestCompiler` + per-protocol
  `ResponseValidatorInterface` (XSD / proto message / SDL types).
- **E6** Events carry protocol-neutral status + optional `grpc-status`/SOAP
  fault; OTEL edge deferred.

### Phase F — Operation executor sub-packages
- **F1** `alama/arazzo-operation-executor-http`: `HttpOperationExecutor`
  (refactored `DefaultOpenApiExecutor`, PSR-18) + `SoapOperationExecutor`
  reference impl (DOM envelope, SOAPAction, fault → outcome, zero vendor).
- **F2** `alama/arazzo-operation-executor-grpc` (full):
  `GrpcOperationExecutor`, HTTP/2 PSR-18 framing, `ProtoCodec` port,
  `grpc-status` trailer mapping.
- **F3** `alama/arazzo-operation-executor-graphql`: `GraphQlOperationExecutor`,
  query+variables over PSR-18, `errors` handling.

### Phase G — Laravel tagging + CLI + umbrella
- **G1** Tags `arazzo.plugins.operation-executor` / `arazzo.plugins.expression`
  / `arazzo.plugins.criterion`; `PluginRegistry` collector; third-party tagging
  documented.
- **G2** `AsyncGraphResolver`/`HttpBindings` extended for executors + source
  types; queue jobs/controllers unchanged.
- **G3** CLI: `RunCommand` accepts source-type/protocol flags.
- **G4** Root umbrella + monorepo-builder: new sub-packages added to root
  `composer.json`; `arazzo-core` requires them.

### Phase H — Conformance, arch, docs, tests
- **H1** Fixtures: WSDL/SDL/proto source fixtures + SOAP/GraphQL/gRPC step
  fixtures + invalid forms, feeding the conformance matrix (`arazzo-core`).
- **H2** Arch constraints (pest-plugin-arch): contracts ≤ PSR deps;
  `arazzo-expression` no `Flow\JSONPath`; runner core no `GuzzleHttp` /
  `Flow\JSONPath`.
- **H3** OMS tests: every `StepState` transition; pause → persist → resume →
  `ACTOR_INPUT_RECEIVED → EVALUATING_CRITERIA` round-trip via repository;
  sync/async parity.
- **H4** Plugin tests: priority ordering, first-match resolution, criterion
  chain replacing the `match`, jsonpath plugin isolation, SOAP envelope
  correctness (PSR-18 in-memory client), `grpc-status` mapping.
- **H5** Laravel tagging tests; regenerate `public-api.md` + `package-contracts.md`;
  `make verify` gate.

## Sequencing

A → C → B → D → E → F → G → H.

- C before B: jsonpath extraction frees the language work.
- E's registry first, then F fills it with real executors (both touch runner).
- Each phase ends green on `make verify` (docs regen, pint, phpstan, Pest).

## Public API impact

- **Additive** contract faces: `PluginInterface`, `OperationExecutorPluginInterface`,
  `CriterionEvaluatorPluginInterface`, `ExpressionEvaluatorPluginInterface`,
  `WorkflowStateRepositoryInterface`, `StepState`, `ResponseTransfer`.
- `StepProtocolExecutorInterface` / `OpenApiExecutorInterface` become
  deprecated aliases (kept for BC, removed from active BC-tracking in a later
  minor release).
- `docs/generated/public-api.md` + `package-contracts.md` regenerate with the
  new faces/value types; reviewed like #63.

## Out of scope / deferred

- MCP/A2A Step targets (future; same SPI).
- OTEL `grpc-trace-bin` propagation.
- `arazzo-document` parsing deps untouched (document stays the parsing layer).

## Risks

- Re-homing `JsonPathEvaluator` out of `arazzo-expression` changes default
  `ExpressionEngine` wiring; standalone expression users without the jsonpath
  sub-package get a typed "criteria unsupported" error.
- OMS persistence envelope must stay backward-compatible with in-flight async
  payloads (versioned load path).
- gRPC `.proto` normalizer + codec is the largest single piece; isolated in its
  own sub-package so it can land without blocking the rest.