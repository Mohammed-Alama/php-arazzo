# Arazzo Specification Fixtures

This directory contains test fixtures for the Arazzo specification parser and validator.

## Directory Structure

*   `valid/` - Documents that MUST parse and validate successfully.
*   `valid/v1.1.0/` - Valid documents specifically testing Arazzo v1.1.0 features.
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

### Invalid

*   `invalid/syntax-error.arazzo.yaml` - Malformed YAML.
*   `invalid/missing-required-field.arazzo.yaml` - Omitted `info.version` or similar required field.
*   `invalid/invalid-workflow-id.arazzo.yaml` - Duplicate or malformed workflowId.
*   `invalid/unresolvable-ref.arazzo.yaml` - `$ref` pointing nowhere.
*   `invalid/circular-workflow-reference.arazzo.yaml` - Workflow A → B → A.
*   `invalid/scope-violation.arazzo.yaml` - Root workflow referencing imported workflow's components (illegal).
*   `invalid/invalid-runtime-expression.arazzo.yaml` - Bad `$response.`/`$inputs.` syntax.

### Edge Cases

*   `edge-cases/empty-workflows-array.arazzo.yaml` - Testing array behavior when empty.
*   `edge-cases/deeply-nested-outputs.arazzo.yaml` - Deeply nested objects in step outputs.
*   `edge-cases/extension-fields.arazzo.yaml` - Presence of `x-*` specification extensions.
*   `edge-cases/json-format.arazzo.json` - JSON encoding equivalent to test both parsers.
