# PHP Arazzo vs Arazzo Toolkit

## 1. Purpose

This report compares the PHP Arazzo packages in this repository with the `/Users/mohammedalama/Code/Me/arazzo-toolkit` project.

The report answers:

- What the PHP package has.
- What the Arazzo Toolkit has.
- What is missing from each project.
- What is stronger or weaker in each project.
- What the approved specifications and tickets must address.
- What the PHP package is expected to provide after the tickets are implemented.

The official Arazzo specification is the authority. Toolkit behavior is used as a reference for useful APIs, fixtures, and cross-language implementation patterns.

---

## 2. Projects Reviewed

### PHP package

- Repository: `php-arazzo`
- Architecture: Decoupled monorepo of **15 packages**:
  - `alama/arazzo-contracts`
  - `alama/arazzo-document`
  - `alama/arazzo-expression`
  - `alama/arazzo-evaluation`
  - `alama/arazzo-sources`
  - `alama/arazzo-runtime`
  - `alama/arazzo-runner`
  - `alama/arazzo-engine`
  - `alama/arazzo-events`
  - `alama/arazzo-cli`
  - `alama/arazzo-protocol-http`
  - `alama/arazzo-protocol-soap`
  - `alama/arazzo-protocol-rpc`
  - `alama/arazzo-core` (umbrella)
  - `alama/laravel-arazzo`
- Runtime: PHP 8.4, Laravel 11/12
- Current branch: `main`
- License: MIT
- Test suites: Pest v5 across all 15 packages with unit, feature, integration, and conformance suites

### Arazzo Toolkit

- Repository: `arazzo-toolkit`
- Packages (**5 packages**):
  - `@usearazzo/parser` (Published on npm: `v1.0.1-alpha.*`)
  - `@usearazzo/resolver` (Published on npm: `v1.0.1-alpha.*`)
  - `@usearazzo/validator` (Published on npm: `v1.0.1-alpha.*`, built on `@speclynx/api-languageservice`)
  - `@usearazzo/runner` (Private monorepo package)
  - `@usearazzo/cli` (Added in PR #220, provides `usearazzo validate`)
- Runtime: TypeScript / JavaScript (Node.js and browser targets)
- Current branch: `main`
- License: Apache-2.0 with `NOTICE` attribution
- Test suites: Mocha, Chai with extensive runner fixtures

---

## 3. Capability Matrix

| Capability | PHP Package (`php-arazzo`) | TypeScript Toolkit (`arazzo-toolkit`) | Current Parity Status |
|---|---|---|---|
| **Architecture** | 15 modular packages (ports & adapters) | 5 focused packages (ApiDOM-based) | High modularity across both |
| **Arazzo Spec Versions** | 1.0.0, 1.0.1, 1.1.0, 1.2.0 (proposal) | 1.0.0, 1.0.1, 1.1.0 | PHP ahead on 1.2 multi-protocol |
| **Package Distribution** | Composer dev monorepo packages | npm published (`parser`, `resolver`, `validator`) | Toolkit ahead on registry releases |
| **Protocols Supported** | HTTP, SOAP (WSDL), RPC (Protobuf) | HTTP (OpenAPI 2.0, 3.0, 3.1) | PHP broader protocol reach |
| **CLI Tooling** | `arazzo` CLI (`validate`, `run`, `list`, `render`) | `usearazzo` CLI (`validate`) | Parity on validate; PHP has run/render |
| **Validation Engine** | AST / Rule engine + schema rules | Language-service diagnostics (ApiDOM + JSON Schema) | Toolkit has LSP/formatting; PHP has rich rules |
| **Expression Evaluation** | Formal AST grammar + extensible plugins | Dedicated AST runtime-expression evaluator | High parity |
| **Cross-Document Workflows** | ⚠️ Incomplete (local workflows only) | ✅ Full support (`$sourceDescriptions.{name}.{workflowId}`) | **Toolkit Gap for PHP** |
| **Document Bundling & Resolving**| ⚠️ Relative source loading only | ✅ Dedicated `bundle()` & `resolve()` APIs | **Toolkit Gap for PHP** |
| **Retry Action References** | ⚠️ Jumps to target without repair return | ✅ Executes repair step/workflow, then retries | **Toolkit Gap for PHP** |
| **Goto Action Semantics** | ⚠️ Step jump only | ✅ Step jump + one-way workflow transfer | **Toolkit Gap for PHP** |
| **Output Shape Preflight** | ⚠️ Evaluated at completion | ✅ Preflight validated before prerequisites run | **Toolkit Gap for PHP** |
| **Execution State Machine** | Canonical OMS, event ledger, queue/sync engine | In-memory synchronous runner pipeline | PHP ahead on durable/queue execution |
| **Distributed Systems** | Locks, persistence, async worker, Laravel integration | In-memory only | PHP distinct advantage |

---

## 4. What the PHP Package Has

### Multi-Protocol Execution Architecture
The PHP implementation has evolved into a pluggable protocol engine:
- **HTTP / REST**: Full OpenAPI operation normalization and dispatch via PSR-18 (`alama/arazzo-protocol-http`).
- **SOAP / WSDL**: WSDL parsing, SOAP action binding, and XML payload handling (`alama/arazzo-protocol-soap`).
- **RPC / Protobuf**: Protocol Buffers schema normalization and gRPC-style RPC dispatch (`alama/arazzo-protocol-rpc`).
- **Arazzo 1.2 Proposal Support**: Supports proposed multi-protocol fields (`wsdl`, `protobuf`, `operationName`, `rpcMethod`).

### Formal Expression Engine
- Formal lexical grammar and AST parser for Arazzo runtime expressions (`alama/arazzo-expression`).
- Extensible plugin evaluators for JSONPath, XPath, regex, string functions, and arithmetic (`alama/arazzo-evaluation`).

### Durable Workflow Infrastructure
- Canonical OMS execution state machine (`alama/arazzo-runner`).
- Immutable event ledger recording transitions and execution history (`alama/arazzo-events`).
- Resumable asynchronous workflows with correlation keys and distributed locks.
- Laravel service provider and queue worker integration (`alama/laravel-arazzo`).

---

## 5. What the Arazzo Toolkit Has

### npm Distribution & Language Service Validation
- Released `@usearazzo/parser`, `@usearazzo/resolver`, and `@usearazzo/validator` on npm (`v1.0.1-alpha.*`).
- Validator built on `@speclynx/api-languageservice`, offering JSON Schema validation against official Arazzo 1.0 and 1.1 schemas.
- Newly added `@usearazzo/cli` with stylish and JSON formatters.

### Cross-Document Workflow Execution
- Native support for cross-document workflow references (`$sourceDescriptions.{name}.{workflowId}`) across:
  - `sub-workflow` steps
  - `dependsOn` lists
  - `retry` action workflow references
  - `goto` action workflow transfers
- Automatic document locator normalization via `ArazzoWorkflowLocatorNormalizer`.

### Refined Control Flow Semantics
- **Retry Action Repair References**: Executes auxiliary repair steps/workflows before retrying failing steps, passing repaired context into the retry attempt.
- **Goto One-Way Transfer**: Step-level `goto` targeting a `workflowId` cleanly performs a terminal one-way transfer to another workflow.
- **Preflight Output Shape Validation**: Validates the syntax of workflow outputs before executing any prerequisites or steps.
- **Ambiguous Target Guard**: Rejects actions that declare both `stepId` and `workflowId`.

### Document Bundling & Dereferencing
- Dedicated `bundle(arazzoDoc)` and `resolve(arazzoDoc)` APIs in `@usearazzo/resolver` that inline external references and handle circular dependencies cleanly.

---

## 6. What Is Missing from the PHP Package

Following the completion of Phases A–F, the remaining gaps between `php-arazzo` and `arazzo-toolkit` are:

### 6.1 Control Flow & Spec Compliance (Tier 1)
1. **Ambiguous Target Rejection**: `php-arazzo` does not currently reject `goto` or `retry` actions that define both `stepId` and `workflowId`.
2. **Retry Repair References**: In `WorkflowEngine.php`, `retry` with a `stepId` or `workflowId` is treated as a permanent jump instead of executing an auxiliary repair operation before returning to re-attempt the failing step.
3. **Goto One-Way Transfer**: Step-level `goto` with a `workflowId` does not cleanly terminate the caller and transfer control to the target workflow as an independent execution scope.
4. **Output Shape Preflight**: Workflow outputs are evaluated only upon workflow termination. Malformed expressions are not detected before executing network steps.

### 6.2 Multi-Document & Bundling (Tier 2)
1. **Cross-Document Workflow References**: Workflows cannot reference external workflows in other Arazzo documents via `$sourceDescriptions.{name}.{workflowId}`.
2. **Document Bundling**: No standalone `bundle()` utility or CLI command to compile a federated Arazzo document tree into a single self-contained artifact.

### 6.3 Tooling & Conformance (Tier 3)
1. **CLI Output Formatters**: `arazzo validate` does not provide `--format=json` or `--format=stylish` options for automated CI pipelines.
2. **Upstream Conformance Fixtures**: Runner control-flow and cross-document test fixtures from `arazzo-toolkit` are not yet imported into the conformance suite.

---

## 7. What Is Missing from the Arazzo Toolkit

1. **Durable & Queued Execution**: Toolkit runner is exclusively in-memory and synchronous. It has no queue drivers, state persistence, or event ledger.
2. **Multi-Protocol Support**: Toolkit only supports HTTP / OpenAPI. It has no support for SOAP/WSDL, RPC/Protobuf, or Arazzo 1.2 proposals.
3. **Framework Integration**: No native framework bridges (such as Laravel service providers, queues, or cache locks).
4. **Public Runner Package**: `@usearazzo/runner` remains private and unpublished.

---

## 8. Action Plan: Tracer-Bullet Ticket Roadmap

To resolve the remaining gaps, eight tracer-bullet tickets have been specified under `.scratch/ecosystem-parity/issues/` following the `to-tickets` guidelines:

| Ticket | Title | Tier | Blocked By |
|---|---|---|---|
| **01** | Reject ambiguous goto and retry actions declaring both stepId and workflowId | Tier 1 | None |
| **02** | Preflight validate workflow outputs shape before prerequisite/step execution | Tier 1 | None |
| **03** | Support step-level goto to workflowId as one-way transfer | Tier 1 | Ticket 01 |
| **04** | Implement retry action repair reference execution with budget accounting | Tier 1 | Ticket 01 |
| **05** | Support cross-document workflow references (`$sourceDescriptions.{name}.{workflowId}`) | Tier 2 | Ticket 03 |
| **06** | Implement Arazzo document bundling API and CLI command | Tier 2 | None |
| **07** | Add stylish and json formatters to arazzo validate CLI command | Tier 3 | None |
| **08** | Vendor cross-ecosystem control flow, retry, and cross-document test fixtures | Tier 3 | Tickets 03, 04, 05 |

---

## 9. Final Assessment

Both repositories have advanced into mature, production-grade implementations of the Arazzo specification. `php-arazzo` leads significantly in distributed orchestration, persistent state management, and multi-protocol flexibility (HTTP, SOAP, RPC). `arazzo-toolkit` leads in npm package delivery, ApiDOM language diagnostics, cross-document workflow references, and repair retry semantics.

Executing the eight tracer-bullet tickets established in this revalidation will close every remaining control-flow and cross-document gap in `php-arazzo`, establishing complete specification conformance and cross-ecosystem parity.
