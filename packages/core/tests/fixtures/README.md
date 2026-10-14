# Arazzo Specification Fixtures

This directory contains test fixtures for the Arazzo specification parser and validator.

## Directory Structure

*   `valid/` - Documents that MUST parse and validate successfully.
*   `valid/v1.1.0/` - Valid documents specifically testing Arazzo v1.1.0 features.
*   `valid/v1.2.0/` - Valid documents testing Arazzo v1.2.0 features (WSDL/SOAP).
*   `invalid/` - Documents that MUST fail parsing or validation with a specific error.
*   `edge-cases/` - Documents that test specific edge cases, boundaries, or alternate formats.

## Fixtures

### Valid

*   `valid/minimal.arazzo.yaml` - Smallest possible valid document.
*   `valid/single-step.arazzo.yaml` - One workflow, one step, no reuse.
*   `valid/multi-step-bnpl.arazzo.yaml` - From OAI examples — multi-provider, chained steps.
*   `valid/reusable-components.arazzo.yaml` - From pet-coupons — reusable steps/params via `$ref`.
*   `valid/multiple-source-descriptions.arazzo.yaml` - 2+ sourceDescriptions (openapi + arazzo).
*   `valid/workflow-imports-workflow.arazzo.yaml` - One workflow referencing another.

### Valid v1.1.0

*   `valid/v1.1.0/asyncapi-source.arazzo.yaml` - AsyncAPI sourceDescription.
*   `valid/v1.1.0/selector-object.arazzo.yaml` - New Selector Object usage.

### Valid v1.2.0 (WSDL/SOAP — OAI spec PR #533)

Vendored from the merged OAI Arazzo Specification PR #533 (`feat(spec): add SOAP support`).
Companion `.wsdl` / `.openapi.yaml` documents sit beside the fixtures (ignored by the dataset)
for future execution-level coverage.

*   `valid/v1.2.0/minimal-wsdl.arazzo.yaml` - Minimal `wsdl` sourceDescription + `operationName` step (upstream `tests/schema/pass/minimal-wsdl`).
*   `valid/v1.2.0/wsdl-step-with-soap-request.arazzo.yaml` - WSDL step with SOAP request body and XPath 3.1 success criteria (upstream `tests/schema/pass/wsdl-step-with-soap-request`).
*   `valid/v1.2.0/soap-customer-crud.arazzo.yaml` - SOAP CRUD example against `customer-service.wsdl` (upstream `examples/1.2.0/`).
*   `valid/v1.2.0/hybrid-order-fulfillment.arazzo.yaml` - Hybrid OpenAPI + SOAP workflow (upstream `examples/1.2.0/`).

### Invalid

*   `invalid/syntax-error.arazzo.yaml` - Malformed YAML.
*   `invalid/missing-required-field.arazzo.yaml` - Omitted `info.version` or similar required field.
*   `invalid/invalid-workflow-id.arazzo.yaml` - Duplicate or malformed workflowId.
*   `invalid/unresolvable-ref.arazzo.yaml` - `$ref` pointing nowhere.
*   `invalid/circular-workflow-reference.arazzo.yaml` - Workflow A → B → A.
*   `invalid/scope-violation.arazzo.yaml` - Root workflow referencing imported workflow's components (illegal).
*   `invalid/invalid-runtime-expression.arazzo.yaml` - Bad `$response.`/`$inputs.` syntax.
*   `invalid/v1.2.0/invalid-wsdl-source-description-type.arazzo.yaml` - `type: soap` is not a source type; parse is rejected (upstream `tests/schema/fail/invalid-wsdl-source-description-type`).
*   `invalid/v1.2.0/wsdl-step-with-operationId.arazzo.yaml` - A step MUST NOT set both `operationId` and `operationName` (upstream `tests/schema/fail/wsdl-step-with-operationId`).
*   `invalid/v1.2.0/characterization-wsdl-step-with-operationPath.arazzo.yaml` - Upstream `tests/schema/pass/characterization-wsdl-step-with-operationPath`: schema-valid but spec-violating (operationPath referencing a `wsdl` source). We reject it, reflecting the prose rule the JSON Schema cannot express.

### Edge Cases

*   `edge-cases/empty-workflows-array.arazzo.yaml` - Testing array behavior when empty.
*   `edge-cases/deeply-nested-outputs.arazzo.yaml` - Deeply nested objects in step outputs.
*   `edge-cases/extension-fields.arazzo.yaml` - Presence of `x-*` specification extensions.
*   `edge-cases/json-format.arazzo.json` - JSON encoding equivalent to test both parsers.
