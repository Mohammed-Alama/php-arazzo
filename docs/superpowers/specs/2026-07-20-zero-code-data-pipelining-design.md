# Zero-Code Data Pipelining (JSONPath Resolver) Design

## Overview
Arazzo workflows require moving data from the output of one step into the inputs of another without requiring developers to write custom PHP middleware. The Zero-Code Data Pipelining engine resolves Arazzo Runtime Expressions (e.g. `$steps.step_id.outputs.body.data.id`) and evaluates complex JSONPath selectors (e.g. `$.users[*].id`) to seamlessly map complex object graphs between HTTP requests.

## Core Requirements

1. **Immutable Workflow Context**
   - The execution state must be completely isolated and immutable per transition.
   - Prevents data bleeding or mutation between parallel steps.
   - Any state update results in a new instance of the `WorkflowContext`.

2. **Native Expression Resolution**
   - Must transparently parse Arazzo Runtime Expressions.
   - Must safely traverse nested PHP arrays/objects derived from JSON payloads.
   - Must gracefully handle missing keys (e.g., nullable types vs strict failures).

3. **Advanced Extraction via JSONPath**
   - Integrate standard JSONPath queries to extract nested data arrays, filter sets, or pluck properties.
   - Example mapping: take an array of orders from Step A, extract all `item_id`s, and pass them as an array input to Step B.
   
4. **Strict Type Casting**
   - Enforce domain boundaries before data hits the PSR-18 HTTP client.
   - Expose casting methods: `asInteger()`, `asString()`, `asArray()`, `asBool()`.
   - Fail early (before dispatching the HTTP request) if types cannot be safely coerced.

## Architecture

### Components

- **`ExpressionResolverInterface`**: The contract for resolving any string expression against a `WorkflowContext`.
- **`JsonPathEvaluator`**: Parses and evaluates `$.` prefixed selectors using a robust JSONPath parser (e.g., `flow/jsonpath` or custom implementation).
- **`TypeCaster`**: A utility class wrapping resolved values to strictly enforce output types.

### Workflow Integration

When the `StepExecutionWorker` prepares an HTTP request, it passes the raw defined parameters (from the Arazzo YAML/JSON) into the `ExpressionResolver`. The resolver interpolates the strings, applies JSONPath extraction if necessary, casts the variables, and returns a fully realized array/DTO ready to be serialized into the HTTP Request Body or Query Parameters.

## Open Questions / Trade-offs
- Should we bundle a robust JSONPath library (e.g., `flow/jsonpath`), or build a lightweight native parser specifically tailored for Arazzo? *Recommendation: Use an existing lightweight, well-tested JSONPath library to save development time and ensure edge-case coverage.*
