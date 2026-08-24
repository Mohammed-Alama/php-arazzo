# Compatibility

## PHP

| Package | PHP |
| --- | --- |
| `alama/arazzo-core` | `^8.4` |
| `alama/laravel-arazzo` | `^8.4` |

## Laravel

`alama/laravel-arazzo` supports the same major versions as its
`illuminate/contracts` constraint:

| Illuminate contracts | Status |
| --- | --- |
| `^11.0` | Supported |
| `^12.0` | Supported |
| `^13.0` | Supported |

The core package has no framework dependency and runs anywhere PHP 8.4
runs (guarded by `CoreIsFrameworkAgnosticTest`).

## Specification support

| Spec | Version(s) | Notes |
| --- | --- | --- |
| Arazzo | 1.0.x, 1.1.x | 1.1-only fields (`x-` gates, timeouts, query-string operation shapes) enforced via dedicated rules |
| OpenAPI (sources) | Swagger 2.0, OpenAPI 3.0.x, 3.1.x | Version-aware normalizer pipeline; unsupported versions are rejected in preflight |
| Runtime expressions | `{token}` / `${token}` spellings, JSON Pointer segments with `~0`/`~1` escapes | Single lexer shared by criteria, outputs, and interpolation |
| Selectors | JSONPath, JSON Pointer, XPath 1.0 | Additional XPath versions can be enabled by binding a custom `XpathEvaluator`; capability errors name the requested version and document location |
| Events | PSR-14 | 9 canonical lifecycle events; `SimpleEventDispatcher`, `NullEventDispatcher`, Laravel bridge adapter |

## Protocol coverage

| Step kind | Executor | Notes |
| --- | --- | --- |
| OpenAPI HTTP | `HttpStepExecutor` / sync `StepExecutor` | Parameter styles incl. deepObject, idempotency injection, ms timeouts, schema validation |
| AsyncAPI send/receive | `AsyncApiStepExecutor` | Suspend/resume via pending correlations + webhook resume controller |
| Sub-workflow | `SubWorkflowStepExecutor` / invoke transitions | Shared step budget and call-stack depth guards |
