# Arazzo Protocol-Spec PR Impact Analysis

**Date:** 2026-09-08
**Purpose:** Determine what php-arazzo must change to support four open OAI/Arazzo-Specification PRs targeting Arazzo 1.2.0, mapped against the internal plugin-stack / multi-protocol design doc.
**Method:** Primary sources — GitHub PR pages + diffs (`gh pr view`, `gh pr diff` against `OAI/Arazzo-Specification`), fetched 2026-09-08. Internal design doc at `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`.

---

## PR #533 — SOAP Support (feat(spec): add SOAP support)

### Status & target version

- **State:** OPEN (not draft, not merged)
- **Target branch:** `v1.2-dev` ([PR page](https://github.com/OAI/Arazzo-Specification/pull/533))
- **Files changed:** 13 (2 `.wsdl`, 4 `.arazzo.yaml`, 1 `.openapi.yaml`, `src/arazzo.md`, `src/schemas/validation/schema.yaml`, 5 test fixtures)

### Spec changes

**Source Description Object** — adds `"wsdl"` to the `type` enum:
```yaml
type:
  type: string
  enum:
    - arazzo
    - openapi
    - asyncapi
    - wsdl        # NEW
```
([schema.yaml diff, line ~86](https://github.com/OAI/Arazzo-Specification/pull/533.diff))

**Step Object** — adds `operationName` field, mutually exclusive with `operationId`, `operationPath`, `channelPath`, `workflowId`:
```yaml
operationName:
  description: The name of an existing, resolvable WSDL operation...
  type: string
```
WSDL steps MUST NOT use `operationPath` (prose only — schema cannot enforce this; documented as a known false-positive in characterization test `characterization-wsdl-step-with-operationPath.arazzo.yaml`).

**New JSON Schema definition** — `wsdl-step-object`:
```yaml
wsdl-step-object:
  allOf:
    - $ref: '#/$defs/step-object-base'
    - type: object
      properties:
        operationName: { type: string }
        parameters: { $ref: '#/$defs/operation-step-parameters' }
      required:
        - operationName
  $ref: '#/$defs/specification-extensions'
  unevaluatedProperties: false
```

**Runtime Expressions** — WSDL operation names added to source description resolution priority:
```
; WSDL operation names conform to the XML NCNames
; Resolution priority defined in spec text: (1) operationId/workflowId/wsdl:operation name, (2) field names
```

**Step Object description** — updated to mention WSDL operations alongside OpenAPI/AsyncAPI.

**request-body-object** — description updated to include `operationName` as a valid referencing field.

### Protocol execution model

SOAP operates over HTTP. The spec adds a prose subsection "Authoring Steps for WSDL Source Descriptions" covering:
- HTTP method/endpoint resolved from WSDL binding (not specified by author)
- SOAPAction header (SOAP 1.1) vs Content-Type action parameter (SOAP 1.2)
- Fault detection: SOAP 1.1 HTTP 500 + `<faultcode>`; SOAP 1.2 HTTP 400/500 + `<env:Code>`
- Envelope namespace differences (1.1 vs 1.2)
- XPath-based success criteria on XML response body

No new runtime expression types; uses existing `$statusCode`, `$response.body`, xpath selectors.

### "Binding" usage

The word "binding" appears **only in WSDL example content** (`wsdl:binding` elements) and in the prose "tools MUST resolve the HTTP method and endpoint URL from the WSDL binding definition." It does NOT use "binding" as an Arazzo vocabulary term. This PR does not reference issue #523 or the emerging binding concept.

### JSON Schema changes

`src/schemas/validation/schema.yaml` — adds `wsdl` to source type enum, adds `wsdl-step-object` to `step-object` oneOf, adds `operationName` property to WSDL step.

---

## PR #556 — Protocol Buffer RPC Support (feat(spec): add Protocol Buffer RPC support)

### Status & target version

- **State:** OPEN (not draft, not merged)
- **Target branch:** `v1.2-dev` ([PR page](https://github.com/OAI/Arazzo-Specification/pull/556))
- **Files changed:** 35 (7 examples, `src/arazzo.md`, `src/schemas/validation/schema.yaml`, 27 test fixtures)

### Spec changes

**Source Description Object** — adds `"protobuf"` to the `type` enum:
```yaml
- protobuf    # NEW
```

**Step Object** — adds two new fields:
```yaml
rpcMethod:
  description: A source-qualified RPC method identifier...
  type: string
  pattern: '^\$sourceDescriptions\.[A-Za-z0-9_-]+(?:\.[A-Za-z_][A-Za-z0-9_]*)+/[A-Za-z_][A-Za-z0-9_]*$'

rpcProtocol:
  description: The RPC protocol and execution semantics used to invoke rpcMethod
  type: string
  enum: [grpc, grpc-web, twirp, connect]
```

**Step Object oneOf** — adds `rpc-step-object`:
```yaml
rpc-step-object:
  oneOf:
    - grpc-step-object
    - grpc-web-step-object
    - twirp-step-object
    - connect-step-object
```

Each protocol variant has its own step definition with protocol-specific request body types:
- `grpc-step-object`: `grpc-request-body` (single or array of Request Body Objects, including empty array for zero-message streams)
- `grpc-web-step-object`: `grpc-web-request-body-object` (single only, restricted content types)
- `twirp-step-object`: `twirp-request-body-object` (single only, protobuf or JSON)
- `connect-step-object`: `connect-request-body` (single or array, restricted content types)

**Parameter Object** — adds `"metadata"` to `in` enum:
```yaml
in:
  enum: [path, query, querystring, header, cookie, channel, metadata]
```

**Runtime Expressions** — major additions:
```
request-source = ( header-reference / query-reference / path-reference / body-reference / payload-reference / request-metadata-reference )
response-source = ( header-reference / body-reference / payload-reference / status-reference / response-metadata-reference )

status-reference = "status" ["#" json-pointer ]
request-metadata-reference = "metadata." metadata-name
response-metadata-reference = ( "metadata." / "trailingMetadata." ) metadata-name
metadata-name = 1*( %x61-7A / DIGIT / "-" / "_" / "." )
```

New expression examples:
| Expression | Description |
|---|---|
| `$response.status#/code` | gRPC status code (`0` = OK) |
| `$request.metadata.authorization` | gRPC/gRPC-Web/Connect request metadata |
| `$response.metadata.x-request-id` | Initial response metadata |
| `$response.trailingMetadata.trace-id` | Trailing metadata |

These productions are "defined only for RPC Steps" — their use for OpenAPI/AsyncAPI/Workflow Steps is undefined.

**Source description resolution** — adds `rpcMethod` priority:
```
; RPC method identifiers use <fully-qualified-service-name>/<method-name>
; Resolution priority: (1) operationId/rpcMethod/workflowId, (2) field names
```

**Payload Replacement** — adds JSON Pointer interpretation for ProtoJSON payloads on RPC steps.

### Protocol execution model

Covers four RPC protocols with distinct wire behaviors:
- **gRPC**: unary, server-streaming, client-streaming, bidirectional-streaming; HTTP/2; protobuf wire encoding; trailing metadata; grpc-status trailers
- **gRPC-Web**: unary and server-streaming only; HTTP/1.1 or HTTP/2; grpc-web content types
- **Twirp**: unary only; HTTP/1.1; protobuf or JSON
- **Connect**: unary, server-streaming, client-streaming, bidirectional-streaming; multiple content types (proto, json, connect+proto, connect+json)

Protocol-specific status is accessed via `$response.status#/code` (integer for gRPC/gRPC-Web, string for Twirp/Connect error codes).

Client/bidirectional streaming request messages are authored as ordered arrays. Interactive bidirectional sequencing is out of scope.

### "Binding" usage

The word "binding" does **not** appear in this PR's additions to `arazzo.md` or `schema.yaml`. The PR uses `rpcProtocol` as its protocol discriminator, not "binding."

### JSON Schema changes

`src/schemas/validation/schema.yaml` — significant additions: `protobuf` source type, `rpc-step-object` oneOf with four protocol variants, `rpc-method-reference` pattern, protocol-specific request body types, `metadata` parameter location, `requestBody` moved from `step-object-base` into individual step-object variants (openapi, asyncapi, workflow, and each RPC variant).

---

## PR #567 — GraphQL Operation Support (feat(spec): add GraphQL operation support)

### Status & target version

- **State:** OPEN (not draft, not merged)
- **Target branch:** `v1.2-dev` ([PR page](https://github.com/OAI/Arazzo-Specification/pull/567))
- **Files changed:** 22 (4 examples, `src/arazzo.md`, `src/schemas/validation/schema.yaml`, 16 test fixtures)

### Spec changes

**Source Description Object** — adds `"graphql"` to the `type` enum.

**Step Object** — adds `graphqlOperation` field, mutually exclusive with `operationId`, `operationPath`, `channelPath`, `workflowId`:
```yaml
graphql-step-object:
  allOf:
    - $ref: '#/$defs/step-object-base'
    - type: object
      properties:
        graphqlOperation:
          $ref: '#/$defs/graphql-operation-object'
        parameters:
          $ref: '#/$defs/operation-step-parameters'
      required:
        - graphqlOperation
```

**GraphQL Operation Object** (new):
```yaml
graphql-operation-object:
  type: object
  properties:
    schema:
      description: A whole Source Description reference in the form $sourceDescriptions.<name>
      type: string
      pattern: '^\$sourceDescriptions\.[A-Za-z0-9_-]+$'
    operation:
      description: Inline GraphQL source text or $sourceDescriptions.<name>.<operationName>
      type: string
    extensions:
      type: object
    extensionsSelector:
      $ref: '#/$defs/selector-object'
  required: [schema, operation]
  not:
    required: [extensions, extensionsSelector]   # mutually exclusive
```

**Parameter Object** — adds `"variable"` to `in` enum and adds `valueMode` field:
```yaml
valueMode:
  enum: [literal, selector]
  default: selector
```
When `valueMode: literal`, the `value` field MAY be an object (for GraphQL input objects). Without `valueMode`, an object value is interpreted as a Selector Object.

**Runtime Expressions** — adds whole-source-description reference:
```
source-reference = source-name [ "." source-reference-id ]  ; optional segment
```
New expression: `$sourceDescriptions.shopSchema` (references the complete source).

Source description resolution adds GraphQL operation name matching:
```
; An omitted source-reference-id references the whole Source Description, applicable to GraphQL
```

### Protocol execution model

GraphQL steps identify a schema (SDL) and an executable operation (inline or external). Endpoint, transport, auth, and client construction are implementation-defined (out of scope).

- **Queries/Mutations**: `$response.body` exposes the full GraphQL response map (`data`, `errors`, `extensions`)
- **Subscriptions**: response stream must complete naturally within `timeout`; `$response.body` is the ordered sequence of result maps
- Request errors (parse, validation, variable coercion) MUST fail the step
- Execution errors do NOT automatically fail the step; authors use `successCriteria`

### "Binding" usage

The word "binding" appears once in prose: "execution-environment configuration MAY be required to **bind** the referenced API description to a concrete service endpoint." This is ordinary English, not the Arazzo vocabulary term from #523.

### JSON Schema changes

`src/schemas/validation/schema.yaml` — adds `graphql` source type, `graphql-step-object` and `graphql-operation-object` definitions, `variable` parameter location, `valueMode` field with complex `oneOf` schema for value interpretation.

---

## PR #568 — Actor-in-the-loop Support (feat(spec): add actor-in-the-loop support)

### Status & target version

- **State:** OPEN (not draft, not merged)
- **Target branch:** `v1.2-dev` ([PR page](https://github.com/OAI/Arazzo-Specification/pull/568))
- **Files changed:** 31 (13 examples, `src/arazzo.md`, `src/schemas/validation/schema.yaml`, 17 test fixtures)

### Spec changes

**Step Object** — adds `interaction` field (mutually exclusive with `operationId`, `operationPath`, `channelPath`, `workflowId`), plus `onTimeout` and `onCancel`:
```yaml
interaction-step-object:
  allOf:
    - $ref: '#/$defs/step-object-base'
    - type: object
      properties:
        interaction:
          oneOf:
            - $ref: '#/$defs/interaction-object'
            - $ref: '#/$defs/reusable-object'
        onCancel:
          oneOf:
            - $ref: '#/$defs/failure-action-object'
            - type: array
              minItems: 1
              items:
                oneOf:
                  - $ref: '#/$defs/failure-action-object'
                  - $ref: '#/$defs/reusable-object'
      required: [interaction]
```

**Interaction Object** (new):
```yaml
interaction-object:
  properties:
    prompt:        { type: string }           # REQUIRED
    context:       { type: object }           # Map[string, string|expression|Selector]
    inputSchema:   { type: object }           # JSON Schema 2020-12
    mode:          { enum: [form, redirect, acknowledge], default: form }
    operationId:   { type: string }           # redirect mode only
    operationPath: { type: string }           # redirect mode only
    url:           { type: string }           # redirect mode only
    parameters:    { type: array }            # redirect mode only
  required: [prompt]
```

Schema enforcement:
- `form` and `redirect` modes require `inputSchema`; `acknowledge` mode has a default schema `{ "acknowledged": boolean }`
- `redirect` mode requires exactly one of `operationId`, `operationPath`, or `url`

**Step Object `timeout`** — broadened to accept ISO 8601 duration strings:
```yaml
timeout:
  oneOf:
    - type: integer
      minimum: 0
    - type: string
      pattern: '^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d+)?S)?)?$'
```

**`onTimeout` field** — new on `step-object-base`:
```yaml
onTimeout:
  oneOf:
    - $ref: '#/$defs/failure-action-object'
    - type: array
      minItems: 1
      items:
        oneOf:
          - $ref: '#/$defs/failure-action-object'
          - $ref: '#/$defs/reusable-object'
```

**Components Object** — adds `interactions` map:
```yaml
interactions:
  type: object
  patternProperties:
    '^[a-zA-Z0-9\.\-_]+$':
      $ref: '#/$defs/interaction-object'
  additionalProperties: false
```

**Runtime Expressions** — adds `$interaction` prefix:
```
interaction-source = payload-reference
component-type = "parameters" / "successActions" / "failureActions" / "interactions"
```

New expressions:
| Expression | Description |
|---|---|
| `$interaction.payload` | Complete actor response object (all modes) |
| `$interaction.payload#/<json-pointer>` | Field within actor response |
| `$components.interactions.<name>` | Reusable Interaction Object |

**`workflowState`** — the spec standardizes the resume token name but leaves its format/transport opaque; executors MUST protect it against forgery (HMAC-SHA256 or AEAD).

### Protocol execution model

Interaction steps do NOT invoke any API operation. `$response.*`, `$statusCode`, `$request.*`, `$url`, and `requestBody` are not applicable.

Three modes:
- **form** (default): present prompt + context, collect structured response validated against `inputSchema`
- **redirect**: redirect actor's client to a URL, resume on callback with extracted data
- **acknowledge**: present prompt, wait for proceed/decline decision

State preservation: "When an interaction step suspends, the executor must preserve sufficient state to resume the workflow when actor input arrives." Long-lived interactions SHOULD use durable storage. The storage mechanism, callback registration, and resumption protocol are implementation-defined.

### "Binding" usage

The word "binding" appears once in example OpenAPI content (`oauth-as.openapi.yaml` — "binding the state and code to the authorization server's"). NOT used as an Arazzo vocabulary term.

### JSON Schema changes

`src/schemas/validation/schema.yaml` — significant additions: `interaction-step-object`, `interaction-object` (with allOf conditional logic for redirect mode and inputSchema requirements), `interactions` in components-object, `timeout` broadened to `oneOf` integer|string, `onTimeout` added to step-object-base, all `$comment` URLs updated to v1.2.

---

## Cross-PR synthesis

### Common source-type / step-shape pattern

All four PRs follow a consistent shape: a new `sourceDescription.type` enum value paired with a new step-object variant in the `step-object` oneOf. The step variant carries protocol-specific fields that identify the operation to invoke:

| PR | sourceType | Step field | Discriminator |
|---|---|---|---|
| #533 SOAP | `wsdl` | `operationName` | source type alone |
| #556 RPC | `protobuf` | `rpcMethod` + `rpcProtocol` | source type + protocol enum |
| #567 GraphQL | `graphql` | `graphqlOperation` (object with `schema` + `operation`) | source type alone |
| #568 Actor | (none — no source) | `interaction` (object with `mode`) | step type alone |

The PRs do NOT use the word "binding" as an Arazzo vocabulary term. Our design's `ResolvedOperation.binding` string (http/soap/grpc/graphql) maps onto the **source type** in #533/#556/#567 and onto the **step type** in #568.

### Concrete changes our Phase tickets must absorb

#### D3 — `ResolvedOperation.binding` values must align with PR source types

Our design uses `binding` as a string: `http`, `soap`, `grpc`, `graphql`. The PRs define source types `wsdl`, `protobuf`, `graphql` and (for RPC) a separate `rpcProtocol` discriminator with values `grpc`, `grpc-web`, `twirp`, `connect`. **Conflict:** our `grpc` binding value does not distinguish gRPC from gRPC-Web/Twirp/Connect — the PRs treat these as separate protocols with different wire semantics, content types, and streaming capabilities.

**Adjustment needed:** `ResolvedOperation.binding` must be able to carry both the source-level protocol family (e.g. `protobuf`) and the execution-level protocol variant (e.g. `grpc`, `twirp`). Options:
1. Two fields: `sourceType` (from the PR) + `rpcProtocol` (the PR's discriminator) — the `binding` string becomes a derived/convenience value
2. A richer binding value that encodes both (e.g. `protobuf/grpc`, `protobuf/twirp`)

Ticket D3 must be revised to reflect this two-axis model.

#### D4 — `RuleSet` validation rules for new step forms

The PRs add:
- `wsdl-step-object` requiring `operationName` (#533)
- `rpc-step-object` → four protocol variants requiring `rpcMethod` + `rpcProtocol` (#556)
- `graphql-step-object` requiring `graphqlOperation` (#567)
- `interaction-step-object` requiring `interaction` (#568)

Each has mutual-exclusion constraints (e.g. `rpcMethod` vs `operationId` vs `workflowId`). The JSON Schema enforces most of this via `unevaluatedProperties: false` on each step variant, but the `wsdl` `operationPath` prohibition is prose-only (documented false-positive in characterization test). D4 must add validation rules for:
- WSDL steps: MUST NOT use `operationPath` (semantic rule beyond JSON Schema)
- RPC steps: `rpcMethod` pattern validation, `rpcProtocol` enum, protocol-specific content-type constraints
- GraphQL steps: `schema` field must be a whole source reference (no JSON Pointer fragment), `extensions` vs `extensionsSelector` mutual exclusion
- Interaction steps: `inputSchema` required for form/redirect, redirect mode target exclusivity

#### E1 — `StepStateMachineEngine` transition table: interaction steps

Our StepState enum is: `PENDING → EXECUTING_REQUEST → EVALUATING_CRITERIA → (AWAITING_ACTOR_INPUT → ACTOR_INPUT_RECEIVED) → COMPLETED / FAILED`.

PR #568 defines interaction step semantics that align closely but add details:
- Interaction steps skip `EXECUTING_REQUEST` entirely (no API call) — they go `PENDING → AWAITING_ACTOR_INPUT` directly
- Two timeout/cancel mechanisms: `onTimeout` (ordered list of failure actions) and `onCancel` (separate action list)
- Three interaction modes with different resumption mechanics
- `workflowState` token with cryptographic integrity requirements

**Our design already covers** the core pause/resume via `AWAITING_ACTOR_INPUT` + `WorkflowStateRepositoryInterface`. **Adjustments needed:**
1. The transition table must allow `PENDING → AWAITING_ACTOR_INPUT` (bypassing `EXECUTING_REQUEST`) for interaction steps
2. `onTimeout` is a new step-level field (previously timeout just failed the step); our failure action handling must support ordered action lists on timeout
3. `onCancel` is a new action category distinct from `onFailure` — the state machine needs a cancellation path
4. ISO 8601 duration string parsing for `timeout` — the current integer-millisecond assumption must be extended

#### E1 cont. — `onTimeout` as a first-class action list

Our design's timeout behavior is implicit (step fails → `onFailure`). PR #568 makes `onTimeout` an ordered list of `FailureActionObject`s evaluated in order. This is a structural change to the Step model, not just a runtime behavior.

#### E5 — `ResponseValidatorInterface` dispatch: GraphQL validation

PR #567 requires GraphQL-aware tooling to parse, validate, and execute operations against the schema. This goes beyond response validation — it includes:
- Parsing inline/referenced Executable Documents
- Validating operations against the schema
- Variable coercion
- Request-error detection (parse/validation errors MUST fail the step before execution)

Our `ResponseValidatorInterface` handles post-execution validation. GraphQL requires pre-execution validation too. The GraphQL protocol package (F3) must add a pre-execution validator that runs before the transport call.

#### F1 — SOAP protocol package: `operationName` field support

PR #533 uses `operationName` (not `operationId`) for WSDL steps. Our design's `ResolvedOperation` currently maps from `operationId`/`operationPath`. The SOAP source normalizer must:
1. Parse WSDL to extract operation names from `portType`/`interface` definitions
2. Resolve HTTP method and endpoint from the WSDL `binding` element
3. Populate `ResolvedOperation` with the WSDL operation name (the field mapping from `operationName` → the executor's operation reference)

The WSDL source type uses `operationName` while our current `SourceNormalizerInterface` returns `ResolvedOperation` which presumably carries `operationId`. Need to verify whether `ResolvedOperation` needs a new field or whether `operationName` maps onto the existing `operationId` field in the resolved operation.

#### F2 — gRPC protocol package: four sub-protocols, not one

PR #556 defines **four** distinct RPC protocols (gRPC, gRPC-Web, Twirp, Connect) with different:
- Streaming capabilities (gRPC: all four; gRPC-Web: unary + server-streaming; Twirp: unary only; Connect: all four)
- Content types (gRPC: `application/grpc`; gRPC-Web: `application/grpc-web*`; Twirp: `application/protobuf` or `application/json`; Connect: `application/proto`, `application/json`, `application/connect+proto`, `application/connect+json`)
- Wire encodings (gRPC uses gRPC framing; Twirp uses standard HTTP; Connect supports both)
- Status semantics (gRPC: integer status code; Twirp: string error code; Connect: string error code)

Our design has a single `alama/arazzo-protocol-grpc` package. The PR implies this package must handle all four variants, or we need separate packages. The `rpcProtocol` discriminator on the step object is the routing key.

**Decision needed:** Does `alama/arazzo-protocol-grpc` handle all four RPC protocols (since they all use Protocol Buffer source descriptions), or do we split into `arazzo-protocol-grpc`, `arazzo-protocol-grpc-web`, `arazzo-protocol-twirp`, `arazzo-protocol-connect`? Given they share `.proto` parsing, a single package with internal protocol dispatch seems cleaner.

#### F3 — GraphQL protocol package: `$sourceDescriptions.<name>` whole-source reference

PR #567 adds `$sourceDescriptions.shopSchema` (no operation fragment) as a valid runtime expression for referencing the complete GraphQL schema source. Our expression grammar and `ExpressionEngine` must support this form — currently `$sourceDescriptions.<name>` without a trailing segment may not be a valid expression in our parser.

#### H1 — Conformance fixtures from PR examples

The PRs ship extensive test fixtures that should be pulled into our conformance matrix:
- **#533**: `soap-customer-crud.arazzo.yaml`, `hybrid-order-fulfillment.arazzo.yaml`, `minimal-wsdl.arazzo.yaml`, `wsdl-step-with-soap-request.arazzo.yaml`, `characterization-wsdl-step-with-operationPath.arazzo.yaml` (false positive), `invalid-wsdl-source-description-type.arazzo.yaml`, `wsdl-step-with-operationId.arazzo.yaml`
- **#556**: `protobuf-grpc-workflows.arazzo.yaml`, `protobuf-grpc-web-steps.arazzo.yaml`, `protobuf-twirp-step.arazzo.yaml`, `protobuf-connectrpc-steps.arazzo.yaml`, `protobuf-hybrid.arazzo.yaml`, `protobuf-request-body-shapes.arazzo.yaml`, `protobuf-streaming-request-items.arazzo.yaml`, `protobuf-parameters-and-common-fields.arazzo.yaml`, plus 17 fail fixtures
- **#567**: `graphql-inline-query.arazzo.yaml`, `graphql-external-mutation.arazzo.yaml`, `graphql-literal-object-variable.arazzo.yaml`, `graphql-explicit-selector-variable.arazzo.yaml`, `graphql-selector-object-structure.arazzo.yaml`, `graphql-reusable-variable-parameter.arazzo.yaml`, `graphql-selector-shaped-literal-extensions.arazzo.yaml`, plus 9 fail fixtures
- **#568**: `interaction-form-with-input-schema.arazzo.yaml`, `interaction-acknowledge.arazzo.yaml`, `interaction-redirect-operation-id.arazzo.yaml`, `interaction-redirect-operation-path.arazzo.yaml`, `interaction-redirect-url.arazzo.yaml`, `interaction-in-components.arazzo.yaml`, `interaction-acknowledge-with-input-schema.arazzo.yaml`, `timeout-iso8601.arazzo.yaml`, plus 8 fail fixtures and 7 example files

#### Grammar conflicts: `$response.status` and metadata expressions

**Major conflict with our closed grammar design.** PR #556 adds:
- `$response.status` — for gRPC status (integer code with `#/code` pointer)
- `$response.metadata.<name>` — for response metadata
- `$response.trailingMetadata.<name>` — for trailing metadata
- `$request.metadata.<name>` — for request metadata

Our design uses `$response.status` for HTTP status codes (a number, not a structured object). The PR uses `$response.status` as a structured object with `#/code`, `#/message`, `#/details` sub-fields. **These semantics clash.**

Our design also uses `$response.meta.*` as a single generic bag for protocol-specific keys. The PR introduces three new expression prefixes (`$response.status`, `$response.metadata`, `$response.trailingMetadata`) with distinct semantics that go beyond a generic bag.

**Options:**
1. Adopt the PR's richer expression grammar for RPC steps (which means extending the grammar beyond our "closed" `$response.meta.*` model)
2. Map the PR's expressions into our `$response.meta.*` bag at normalization time (e.g. `$response.status#/code` becomes `$response.meta.grpcStatus` — loses structure)
3. Split: `$response.status` for HTTP status (existing) and `$response.status.grpc` / `$response.meta.*` for protocol-specific status — adds grammar cases

This is the highest-impact design conflict. Our "Grammar is closed" decision (D8) assumed protocol-specific references surface through one generic `$response.meta.<key>` case. The RPC PR's `$response.status#/code` and `$response.metadata.*` / `$response.trailingMetadata.*` are three additional grammar cases with specific structural semantics.

#### `$interaction.payload` — new expression prefix

PR #568 adds `$interaction.payload` and `$interaction.payload#/<json-pointer>` as new runtime expression prefixes. These are used within interaction step outputs, successCriteria, and action criteria. Our expression engine must support this new prefix and scope it to the containing interaction step (not accessible from other steps).

#### `valueMode` on Parameter Object

PR #567 adds `valueMode` (`literal` | `selector`) to the Parameter Object, changing how object-valued parameters are interpreted. Without `valueMode`, an object is a Selector Object; with `valueMode: literal`, it's a literal GraphQL input object. This affects our Parameter DTO and value resolution logic.

#### `onTimeout` and `onCancel` on Step Object

PR #568 adds both `onTimeout` (ordered failure action list) and `onCancel` (failure action list) to the step base. `onTimeout` is on `step-object-base` (applies to all step types). `onCancel` is on `interaction-step-object` only. Our Step model must accommodate both.

---

### Summary of design doc decisions requiring revision

| Decision | PR(s) | Issue |
|---|---|---|
| D3 `ResolvedOperation.binding` = single string | #556 | gRPC vs gRPC-Web vs Twirp vs Connect need separate binding values or a two-axis model |
| D8 Grammar is closed (`$response.meta.*` only) | #556, #567, #568 | PRs add `$response.status`, `$response.metadata.*`, `$response.trailingMetadata.*`, `$sourceDescriptions.<name>` (whole), `$interaction.payload` — at least 5 new grammar cases |
| StepState transition table | #568 | Interaction steps bypass `EXECUTING_REQUEST`; `onTimeout`/`onCancel` add new transition triggers |
| `timeout` = integer milliseconds | #568 | Now also accepts ISO 8601 duration strings |
| `SourceNormalizerInterface::normalize()` returns `ResolvedOperation` | #533, #556, #567 | WSDL uses `operationName`, RPC uses `rpcMethod`+`rpcProtocol`, GraphQL uses `graphqlOperation` — `ResolvedOperation` may need protocol-specific fields or the normalizer must map them into existing fields |

### Open questions

1. **Does `$response.status` in #556 conflict with `$statusCode` (existing)?** The PR says `$response.status#/code` is for RPC status; `$statusCode` remains for HTTP status. For gRPC over HTTP/2, both exist. Need clarity on which expression to use in success criteria for gRPC steps.

2. **Should `rpcProtocol` values (grpc/grpc-web/twirp/connect) be our `binding` values?** Or should `binding` be `protobuf` (the source type) with `rpcProtocol` as a separate execution-detail field?

3. **GraphQL subscription timeout model** — PR #567 says `timeout` bounds both setup and stream lifetime. Our timeout model currently applies to the entire step execution. Is this compatible, or do we need a two-phase timeout (setup timeout + stream timeout)?

4. **Interaction step's `$response.*` inapplicability** — PR #568 explicitly says `$response.*` is not applicable to interaction steps. Our expression engine must scope expressions per step type.

5. **`requestBody` moved out of `step-object-base`** — PR #556 moves `requestBody` from `step-object-base` into individual step variants (openapi, asyncapi, workflow, each RPC variant). This is a structural schema change that affects how we parse/validate step objects.

6. **All four PRs are OPEN and targeting `v1.2-dev`** — none are merged. The content should be treated as **proposal/draft status**, not normative spec. Final field names, enum values, and semantics may change before merge.
