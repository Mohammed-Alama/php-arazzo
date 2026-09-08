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
   hot path must stay vendor-free (PSR interfaces only). Vendored codecs live
   in protocol packages behind the SPI.
2. **One public face per package** (#63) — new SPI is added to `contracts`;
   existing public faces and value types stay public. New faces are deliberate,
   reviewed additions to `docs/generated/public-api.md`.
3. **Spec vocabulary** — Arazzo calls the Step target an *Operation*; the
   emerging 1.2 binding work (OAI/Arazzo-Specification#523) names the protocol
   profile a *Binding*. We adopt these words and the repo's established word
   *Executor* (see `docs/generated/ubiquitous-language-audit.md`).
4. **Protocols are vertical slices** — a protocol touches the source
   normalizer, the operation executor, the response mapping, replacement
   targets and schema validation. Corralling all of that for one protocol in
   one package is the only way to add a protocol **without editing core**.
   Core is never touched by a new protocol; it only ever consumes ports.

## Vocabulary (locked)

- **Operation** — the Arazzo domain noun a Step targets (`operationId`,
  `operationPath`, `workflowId`; future `functionId` + binding).
- **Executor** — the runtime unit that runs an Operation for a given protocol.
- **Binding** — the protocol profile that parameterizes how an Operation
  executes (HTTP, SOAP, gRPC, GraphQL; MCP/A2A later).
- **Plugin** — a named, priority-ordered extension unit behind a contracts SPI.
- **Protocol package** — a single `alama/arazzo-protocol-*` package that carries
  *all* ports a protocol implements (normalizer + executor + transfer mapper +
  replacement resolver + validator). Core never imports protocol packages.
- **Transfer bag** — the generic protocol-specific reference surface on
  `ResponseTransfer` (`meta`), the only grammar extension a protocol needs.

SPI seam: `OperationExecutorPluginInterface` in `contracts`; the existing
`StepProtocolExecutorInterface` remains as a `@deprecated` alias.

## Goals

- Steps can target Operations over SOAP, gRPC and GraphQL in addition to
  HTTP/OpenAPI and AsyncAPI, selected by protocol-agnostic registries.
- **Adding a protocol = new `arazzo-protocol-<X>` package, zero changes to
  `arazzo-contracts`, `arazzo-expression`, `arazzo-runner` or
  `arazzo-document`.** Core only owns ports, registries, the engine, and the
  embedded HTTP/AsyncAPI defaults.
- `arazzo-contracts` and the `arazzo-runner` engine hot path stay vendor-free.
- Step execution is an explicit OMS state machine — `PENDING → EXECUTING_REQUEST
  → EVALUATING_CRITERIA → (AWAITING_ACTOR_INPUT → ACTOR_INPUT_RECEIVED) …
  → COMPLETED / FAILED` — with the `WorkflowContextInterface` transfer serialized
  by a `WorkflowStateRepositoryInterface` for safe pause/resume.
- The sync (`WorkflowExecutor`) and async (`StepExecutionWorker` /
  `StepOutcomeHandler`) loop carriers are consolidated onto one state machine,
  killing the sync/async divergence tracked in the roadmap (C11).
- Laravel auto-registers protocol packages via container tagging and a
  `PluginRegistry`.
- An arch test mechanically enforces the invariant: core imports zero
  `arazzo-protocol-*` types.

## Non-goals

- No composer-package renames (`arazzo-runner`/`arazzo-expression` keep their
  published names; roles are mapped, not renamed).
- No changes to existing public faces' signatures (`RunnerFacadeInterface`,
  `ExpressionEngineInterface`, `DocumentInterface`) — additive only.
- No MCP/A2A Step targets in this iteration (they are just another protocol
  package later).
- No OTEL `grpc-trace-bin` propagation in this iteration (lives in the gRPC
  protocol package when it lands).
- No changes to `arazzo-document`'s parsing deps (document is the parsing
  layer for its embedded OpenAPI/AsyncAPI defaults).

## Decisions (locked with stakeholders)

| # | Decision |
|---|---|
| D1 | Map roles onto existing packages; no composer renames. New SPI via new interfaces. |
| D2 | `WorkflowStateRepositoryInterface` persists the serialized `WorkflowContextInterface` transfer plus a versioned envelope with the current `StepState`. |
| D3 | Zero-vendor core = `contracts` + `runner` engine hot path. Document/CLI keep parsing deps. |
| D4 | SOAP is the reference protocol slice, fully fleshed, zero-vendor (DOM + PSR-18). |
| D5 | gRPC is planned fully: source normalizer + executor + codec + transport live in one gRPC protocol package; optional `grpc/grpc` + `google/protobuf` vendor allowed only there. |
| D6 | Protocol-typed response handling is **additive**: new `ResponseTransfer` value type; existing `EvaluationInput` DTO untouched. |
| D7 | **Protocols are vertical packages.** Every seam a protocol touches (source normalizer, executor, transfer mapper, replacement resolver, schema validator) ships in `alama/arazzo-protocol-<X>`. Core edits nothing when a protocol is added. |
| D8 | **Grammar is closed.** Protocol-specific references surface through one generic `$response.meta.<key>` bag case, added once. Protocol packages populate the transfer; they never extend the lexer/parser. |
| D9 | HTTP/OpenAPI + AsyncAPI normalizers/executors remain **embedded core defaults** (implementing the same ports, replaceable). Extracting them into `arazzo-protocol-http` is a later, purely mechanical split — not a prerequisite for pluggability. |

## Architecture

### Layering (core never imports protocols)

```
arazzo-contracts          ports + transfers + enums (PSR-only)
      │  implements / consumes
arazzo-expression         closed grammar · transfer-view resolution · registries
arazzo-document           SourceNormalizerRegistry · embedded openapi/asyncapi defaults · validation
arazzo-runner             StepStateMachineEngine · OperationExecutorRegistry · state repository
      ▲                            ▲                            ▲
      │  ports                    │  ports                     │  ports
alama/arazzo-protocol-soap        │                            │
alama/arazzo-protocol-grpc        │  — full vertical slice per  │
alama/arazzo-protocol-graphql     │    protocol package:        │
alama/arazzo-protocol-http (later)│    normalizer + executor +  │
                                  │    transfer mapper +        │
                                  │    replacement resolver +   │
                                  │    validator + codec        │
      └────────────────── registered via composer/laravel tags ─┘
```

### Package map

| Package | Role | Dependency change |
|---|---|---|
| `alama/arazzo-contracts` | ports + value DTOs + Transfers + enums | none (PSR-only) |
| `alama/arazzo-expression` | closed grammar, transfer-view resolution, registries + core evaluator | drops `softcreatr/jsonpath` |
| `alama/arazzo-evaluator-jsonpath` (new) | JSONPath expression + criterion plugins | owns `softcreatr/jsonpath` |
| `alama/arazzo-runner` | engine: OMS state machine, `OperationExecutorRegistry`, state repository | drops Guzzle/OTEL from hot path |
| `alama/arazzo-document` | `SourceNormalizerRegistry`; OpenAPI/AsyncAPI defaults; validation | adds registry + normalizer port |
| `alama/arazzo-protocol-soap` (new) | full SOAP slice (normalizer + executor + mapper + validator) — **reference** | DOM + PSR-18 only |
| `alama/arazzo-protocol-grpc` (new) | full gRPC slice incl. `.proto` normalizer + codec + transport | allows `grpc/grpc` + `google/protobuf` vendor here only |
| `alama/arazzo-protocol-graphql` (new) | full GraphQL slice | PSR-18 only |
| `alama/arazzo-protocol-http` (later) | mechanical extraction of embedded HTTP defaults | — |
| `alama/arazzo-cli` / `alama/arazzo-core` / `alama/laravel-arazzo` | console / umbrella / bridge | require protocol packages; tagging |

### Ports (contracts) added in this effort

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

interface SourceNormalizerInterface extends PluginInterface
{
    public function supports(string $sourceType): bool;
    public function normalize(array $document, string $location): ResolvedOperation;
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

interface ReplacementTargetResolverInterface extends PluginInterface
{
    public function supports(string $targetType): bool;   // json-pointer | xpath | proto-field
    public function resolve(mixed $container, string $target, mixed $value): mixed;
}

interface ResponseValidatorInterface
{
    /** @throws SchemaValidationException */
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void;
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

### ResponseTransfer (additive public value type; the protocol fill-slot)

```php
final readonly class ResponseTransfer
{
    public function __construct(
        public readonly mixed $status,                 // mapped per protocol (HTTP status / grpc-status / SOAP fault status)
        public readonly array $headers,                // HTTP headers / SOAP headers / gRPC metadata
        public readonly mixed $rawBody,
        public readonly ?array $json = null,           // HTTP/GraphQL body view
        public readonly ?DOMDocument $xml = null,      // SOAP envelope view
        public readonly ?object $proto = null,         // decoded gRPC message
        public readonly ?array $graphQlErrors = null,  // GraphQL top-level errors
        public readonly array $meta = [],              // protocol-specific keys, see grammar below
    ) {}
}
```

## The transfer-encoding axis (closed grammar)

Core grammar is **not** extended per protocol. One generic reference is added
once and reused by every protocol:

- `$response.meta.<key>` → resolved from `ResponseTransfer->meta[$key]`.

Protocol packages populate the transfer and document their keys:

| Transfer facet | Grammar (unchanged) | HTTP/GraphQL | SOAP | gRPC |
|---|---|---|---|---|
| body | `$response.body` | `json` view | `xml` view (DOM) | `proto` view (decoded message) |
| status | `$response.status` | HTTP status | HTTP 200 + fault state | `grpc-status` (mapped or in meta) |
| headers | `$response.header.*` | HTTP headers | SOAP headers | gRPC metadata |
| extras | `$response.meta.*` | — | `soap.faultcode`, `soap.faultstring` | `grpc.status`, `grpc.message` |
| errors | `$response.meta.*` | — | — | — |
| GraphQL top-level errors | `$response.meta.graphql.errors` | — | — | — |
| selectors | unchanged (`type: jsonpath` / `xpath`) | jsonpath | xpath on DOM | jsonpath on decoded message |
| replacement | `ReplacementTargetResolverInterface` | json-pointer | xpath | proto-field |

`ReferenceKind` gains exactly one case (a generic transfer/`meta` reference).
Nothing else in the lexer/parser/AST changes, now or for future protocols.

## Phase tickets

### Phase A — Contracts ports (zero-dep preserved)
- **A1** `PluginInterface` + `OperationExecutorPluginInterface`; deprecate
  `StepProtocolExecutorInterface`.
- **A2** `CriterionEvaluatorPluginInterface` + `ExpressionEvaluatorPluginInterface`
  + `ReplacementTargetResolverInterface`.
- **A3** `SourceNormalizerInterface` (+ `SourceNormalizerRegistry` port).
- **A4** `StepState` enum (above); `Retrying` mapped to an edge
  `EVALUATING_CRITERIA → PENDING`, not a state.
- **A5** `WorkflowStateRepositoryInterface` (versioned envelope).
- **A6** `ResponseTransfer` value type incl. `meta` bag.

### Phase B — Expression: closed grammar + transfer-view resolution
- **B1** Add the single generic `$response.meta.*` reference case to
  `ReferenceKind`/grammar. No per-protocol grammar after this ticket.
- **B2** `ExpressionEngine`/`SelectorEvaluator`/`CriteriaEvaluator` resolve
  against `ResponseTransfer` views when present (json/xml/proto/meta).
- **B3** `PayloadReplacer` target resolution through
  `ReplacementTargetResolverInterface` + registry (core default = json-pointer).
- **B4** Criteria evaluation receives the typed view (DOM node / decoded
  message) from the transfer.

### Phase C — Evaluator plugins + vendor isolation
- **C1** New `alama/arazzo-evaluator-jsonpath`: move `JsonPathEvaluator` +
  `softcreatr/jsonpath`; expose jsonpath expression + criterion plugins.
  `arazzo-expression` drops the vendor dep.
- **C2** `ExpressionEvaluatorRegistry` + `CriterionEvaluatorRegistry`
  (priority-ordered, first-match) assembled by the `ExpressionEngine` facade.
- **C3** Refactor `CriteriaEvaluator`'s hard-coded `match`: simple/regex/xpath
  in-core; jsonpath + future types via plugins (typed "unsupported criterion"
  error without the plugin).

### Phase D — Document: normalizer port + registry (defaults only)
- **D1** `SourceNormalizerRegistry`: `SourceResolver`/`SourceRegistry`
  resolve source types through registered `SourceNormalizerInterface` plugins.
- **D2** OpenAPI (+ AsyncAPI) normalizers refactored to implement the port and
  stay as **embedded core defaults**.
- **D3** `ResolvedOperation` gains a `binding` string (http/soap/grpc/graphql).
- **D4** `RuleSet` validation rules for new step forms/source types.
  *(WSDL / proto / GraphQL-SDL normalizers do NOT land here — they are
  protocol packages, Phase F.)*

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
- **E5** Binding-aware request compilation + `ResponseValidatorInterface`
  dispatch per protocol (embedded default = JSON-schema/OpenAPI).

### Phase F — Protocol packages (vertical slices; core untouched)
- **F1** `alama/arazzo-protocol-soap` **(reference slice, proves the pattern)**:
  `WsdlSourceNormalizer` (DOM, no vendor) → operations; `SoapOperationExecutor`
  (DOM envelope, SOAPAction, fault → outcome); SOAP `ResponseTransfer` mapper
  (`soap.faultcode`/`faultstring` in `meta`, `xml` view); xpath
  `ReplacementTargetResolver`; XSD-flavoured `ResponseValidator`. Zero vendor.
- **F2** `alama/arazzo-protocol-grpc` **(full)**:
  `ProtoSourceNormalizer` (proto3 → operations/messages/methods),
  `GrpcOperationExecutor` (gRPC transport via the optional `grpc/grpc` client,
  PSR-18/grpc-web fallback where only HTTP/1.1 is available, `ProtoCodec` port,
  `grpc-status` trailer mapping), `ResponseTransfer` mapper
  (`grpc.status`/`grpc.message` in `meta`, `proto` view), proto-field
  `ReplacementTargetResolver`, proto-message `ResponseValidator`. Optional
  `grpc/protobuf` vendor allowed here only.
- **F3** `alama/arazzo-protocol-graphql`:
  `GraphQlSchemaNormalizer` (SDL tokenizer → operations),
  `GraphQlOperationExecutor` (query+variables over PSR-18, `errors` → meta),
  JSON `ResponseTransfer` mapper, `ResponseValidator` against SDL types.

### Phase G — Composition: Laravel tagging + CLI + umbrella
- **G1** Tags `arazzo.plugins.operation-executor` / `arazzo.plugins.source-normalizer`
  / `arazzo.plugins.expression` / `arazzo.plugins.criterion` /
  `arazzo.plugins.replacement-target`; `PluginRegistry` collector feeds all
  registries; third-party protocol packages register via tags. Adding a
  protocol package to a Laravel app = `composer require` + nothing else.
- **G2** `AsyncGraphResolver`/`HttpBindings` extended for executors + source
  types; queue jobs/controllers unchanged.
- **G3** CLI: `RunCommand` accepts source-type/protocol flags; autoloaded
  protocol packages discovered via a preset collection.
- **G4** Root umbrella + monorepo-builder: new sub-packages added to root
  `composer.json`; `arazzo-core` requires the protocol packages.

### Phase H — Conformance, arch, docs, tests
- **H1** Fixtures: WSDL/SDL/proto source fixtures + SOAP/GraphQL/gRPC step
  fixtures + invalid forms, feeding the conformance matrix (`arazzo-core`).
- **H2** Arch constraints (pest-plugin-arch): **core (`contracts`, `expression`,
  `runner`, `document`) imports zero `arazzo-protocol-*` types**; contracts ≤
  PSR deps; `arazzo-expression` no `Flow\JSONPath`; runner core no
  `GuzzleHttp`/`Flow\JSONPath`.
- **H3** OMS tests: every `StepState` transition; pause → persist → resume →
  `ACTOR_INPUT_RECEIVED → EVALUATING_CRITERIA` round-trip via repository;
  sync/async parity.
- **H4** Plugin tests: priority ordering, first-match resolution, criterion
  chain replacing the `match`, jsonpath plugin isolation, SOAP envelope
  correctness (PSR-18 in-memory client), `grpc-status` mapping.
- **H5** Laravel tagging tests; regenerate `public-api.md` + `package-contracts.md`;
  `make verify` gate.
- **H6** Protocol-package authoring guide (the "add a protocol without touching
  core" checklist) in `docs/`.

## Sequencing

A → C → B → D → E → F → G → H.

- C before B: jsonpath extraction frees the language work.
- D before F: the normalizer port/registry must exist before protocol packages
  ship normalizers.
- F1 (SOAP reference slice) lands first to prove the vertical pattern; F2/F3
  copy the shape.
- Each phase ends green on `make verify` (docs regen, pint, phpstan, Pest).

## Public API impact

- **Additive** contract faces: `PluginInterface`, `OperationExecutorPluginInterface`,
  `SourceNormalizerInterface`, `CriterionEvaluatorPluginInterface`,
  `ExpressionEvaluatorPluginInterface`, `ReplacementTargetResolverInterface`,
  `WorkflowStateRepositoryInterface`, `StepState`, `ResponseTransfer`.
- `SourceNormalizerInterface` (contracts) returns the **existing**
  `ResolvedOperation` (document); `OperationExecutorPluginInterface` returns the
  **existing** `StepExecutionOutcome` (contracts). Neither type is new.
- `ExpressionReference` stays the single value used by expression plugins
  (existing interface kept; new evaluation input is additive).
- `StepProtocolExecutorInterface` / `OpenApiExecutorInterface` become
  deprecated aliases (kept for BC, removed from active BC-tracking in a later
  minor release).
- `docs/generated/public-api.md` + `package-contracts.md` regenerate with the
  new faces/value types; reviewed like #63.

## Out of scope / deferred

- MCP/A2A Step targets (future protocol packages on the same shape).
- OTEL `grpc-trace-bin` propagation (lives in the gRPC protocol package).
- Extracting embedded HTTP/OpenAPI defaults into `arazzo-protocol-http`
  (mechanical, post-hoc; not a prerequisite for pluggability — D9).

## Risks

- The generic `$response.meta.*` bag trades per-protocol sugar for a closed
  grammar. Namespaced key conventions (`soap.*`, `grpc.*`, `graphql.*`) are
  documented to keep it deterministic and collision-free.
- Re-homing `JsonPathEvaluator` out of `arazzo-expression` changes default
  `ExpressionEngine` wiring; standalone expression users without the jsonpath
  sub-package get a typed "criteria unsupported" error.
- OMS persistence envelope must stay backward-compatible with in-flight async
  payloads (versioned load path).
- gRPC `.proto` normalizer + codec is the largest single piece; isolated in its
  own protocol package so it can land without blocking the rest.