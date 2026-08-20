# Runtime Expressions, Selectors, Outputs, and Errors

## Goal

Implement one standards-compliant runtime layer for inputs, parameters, request bodies, outputs, criteria, actions, and workflow outputs.

## Expressions and selectors

Expressions use a typed AST and distinguish workflow inputs, step outputs, response body and headers, status code, request data, URL, method, source descriptions, components, and workflow outputs. Syntax errors include expression text, workflow, step, execution, pointer, and category. JSON Pointer escaping follows RFC semantics. `null`, `false`, `0`, empty strings, and empty arrays remain distinct from missing values.

Every selector accepted by parsing and validation must have a runtime path for parameters, request-body replacements, outputs, success criteria, and action criteria. Evaluators are injectable. Missing capabilities produce typed errors, not generic runtime exceptions.

## Outputs and results

Step outputs resolve after response capture. Workflow outputs resolve after terminal state. Nested workflow outputs are preserved in structured sub-workflow results. Context timing follows official Arazzo semantics.

Results distinguish completed, ended, failed, cancelled, transferred, and suspended/pending-correlation states. Results include execution and workflow identity, status, settled status where relevant, outputs, step trace, attempts, requests/responses, nested results, dependencies, errors, and timing data.

## Error taxonomy

Separate authoring errors, transport errors, protocol errors, execution failures, and cancellation. Transport failures must not become synthetic HTTP responses. Errors are typed and include stable machine-readable categories plus workflow, step, execution, and document context.

## Acceptance

Selector fixtures work in synchronous and queued paths. Transport failures cannot satisfy response criteria. Workflow outputs work after every terminal action. Error categories are stable. Nested results preserve both called workflow identity and terminal result. Known capability failures never use generic exceptions.
