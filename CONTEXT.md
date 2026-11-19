# php-arazzo — Domain Context & Glossary

This file is the project's shared domain model (CONTEXT.md). It defines the
ubiquitous language used across issues, design reviews, ADRs, and code. Keep it
in sync with the code: when a term's meaning changes, update this file in the
same pull request.

## 1. Project

| Term | Meaning |
|------|---------|
| **php-arazzo** | PHP implementation of the [Arazzo 1.0.1 / 1.1.0](https://www.asyncapi.com/specs/arazzo) spec for describing workflows of API calls. |
| **Monorepo** | Single git repository containing multiple Composer packages under `packages/`, plus `scripts/` and tooling at the root. |
| **`arazzo run`** | CLI command that resolves and executes an Arazzo workflow document: sends HTTP requests, extracts step outputs, evaluates criteria, applies retry/goto/failure semantics, and reports a true result. |
| **`arazzo validate`** | CLI command that lint-checks an Arazzo document: structure, references, expression grammar, parameter/action rules, dependsOn validity, source qualification. |

## 2. Conformance

| Term | Meaning |
|------|---------|
| **arazzo-conformance** | The [`api-evangelist/arazzo-conformance`](https://github.com/api-evangelist/arazzo-conformance) scored test suite (SHA `6e93591` is the conformance oracle). php-arazzo targets 100% of scored validation cases (127) and 100% of scored execution cases (14). |
| **Conformance capability manifest** | Machine-readable JSON at `docs/conformance/manifest.json` recording, per capability, whether php-arazzo supports it (`supported` / `partial` / `unsupported` / `not-applicable`), with `evidence` references. Emitted by `scripts/generate-conformance-matrix.php` alongside `docs/CONFORMANCE.md`. |
| **Drift guard** | `scripts/check-conformance-manifest.php`: re-runs the corpus, rebuilds the manifest model, compares to the committed manifest, exits non-zero on mismatch. Runs in CI on every PR. |
| **OAI corpus** | Vendored official OAI Arazzo examples under `packages/core/tests/Conformance/corpus/oai/`. Source of truth for what the manifest claims. |

## 3. Execution

| Term | Meaning |
|------|---------|
| **Workflow** | A unit of execution in an Arazzo document, identified by `workflowId`, containing steps. |
| **Step** | An action within a workflow: an `operationId` call, a `workflowId` sub-workflow invocation, or a `successAction`/`failureAction`. |
| **dependsOn** | Declares that a step or workflow must wait for another to complete. Workflow-level `dependsOn` (issue #341) runs dependency workflows before the target. Cross-workflow step `dependsOn` (`$workflows.<id>.steps.<stepId>`, issue #388) references steps in sibling workflows. |
| **Retry** | `onFailure.retry.retryLimit: N` means N+1 total requests (issue #382 R2). |
| **Goto** | `onSuccess.goto.stepId` / `onFailure.goto.stepId` transfers execution to a target step, skipping intermediates (issue #382 R3/R4). |
| **Success criteria** | `successCriteria` evaluated via `EvaluationEngine`: regex match, body comparison, simple comparison (issue #382 R5). |
| **Sub-workflow (workflowId step)** | A step that references another workflow by `workflowId`; the child runs and its outputs are exposed as `$steps.<childStepId>.outputs` (issue #382 R6). |
| **ExecutionState** | Canonical owner of workflow-output state: the state↔context mapping in `packages/contracts/src/State/ExecutionState.php` (issue #341 P0). |

## 4. Validation

| Term | Meaning |
|------|---------|
| **RuleSet** | Collection of validator rules in `packages/document/src/Validator/RuleSet.php`. |
| **Source qualification** | Requiring `operationId` to be source-qualified (e.g. `sourceName.operationId`) only when multiple non-arazo sources exist; single source allows unqualified (issue #388 R2/C1). |
| **Cross-workflow dependsOn** | Validator accepts `$workflows.<id>.steps.<stepId>` in `dependsOn` (issue #388 R1). |
| **Strict validation** | Default `true`: a response violating its OpenAPI response schema fails the step (issue #334). Opt-out per step via `x-strict-validation: false`. |
| **Reference resolution (validate)** | `operationId` is resolved against locally-loaded source operations; `$sourceDescriptions.<name>` and `$components.<type>.<name>` are checked against the symbol table. Local sources resolve offline; unreachable remote sources are skipped, never fail validation (issue #384). |
| **Source resolution seam** | Performed in `cli` (`ValidateCommand` → `SourceLoader`); the `document` validator never imports `sources` (issue #384). |

## 5. Packages

| Package | Role |
|---------|------|
| `packages/contracts` | State model: `ExecutionState`, `WorkflowContext`, `WorkflowDataMap` (workflow-output map owner — issue #341). |
| `packages/document` | Document parsing & validation rules (issues #388, document-level bugfixes). |
| `packages/evaluation` | `EvaluationEngine` — success criteria evaluation (issue #382 R5). |
| `packages/expression` | Runtime expression parsing & JSONPath (ABNF strictness, issue #386). |
| `packages/runner` | Execution engine: `WorkflowExecutor`, `RunnerFacade`, `WorkflowDependencyResolver`, `StepExecutor` (issues #341, #382). |
| `packages/protocol-http` | PSR-18 HTTP client services, mirrors of runner defaults (issue #334). |
| `packages/sources` | Source resolution & reachability (issues #330–#332). |
| `packages/cli` | `arazzo run` / `validate` commands, `RunCommand` (issue #334). |
| `packages/laravel` | Laravel host integration, `AsyncGraphResolver`, `ProtocolHttpServiceProvider`. |

## 6. Architecture decisions (ADRs)

| ADR | Decision | Issue |
|-----|----------|-------|
| ADR-001 | Strict response validation default (`true`) — semantic BC break | #334 |
| ADR-002 | Conformance capability manifest + drift guard | #340 |
| ADR-003 | Runner semantics: retry/goto/criteria/workflowId via shared seam | #382 |
| ADR-004 | Cross-workflow dependsOn + source qualification in RuleSet | #388 |
| ADR-005 | Validation degrades gracefully on unreachable remote sources | #384 |

## 7. How to update this file

When a design decision changes a term above, update CONTEXT.md in the same pull request as the implementation. Do not let the glossary drift from the code.
