# 10. AI Agent & Epistemic Protocol Routing

**Category:** Backend — Modular Systems & AI Integration
**Phase:** 3 — Modular systems & AI integration
**Depends on:** [04 — Strict Runtime Schema Validation](04-strict-runtime-schema-validation.md)
**Status:** Not started — needs brainstorming

## Description

Native logic loops designed for generative AI pipelines. The engine uses schema validation to
evaluate LLM outputs; if validation fails, it automatically routes backward, feeding the
explicit error back to the LLM for a self-correction loop.

**1.1.0 delta:** don't invent new Arazzo syntax for the self-correction loop — model it as
`onFailure` with `type: retry` + `retryLimit`, where "failure" is schema validation ([04](04-strict-runtime-schema-validation.md))
rejecting the LLM's output. Inject a synthetic `{$steps.<id>.validationError}` expression
variable, populated only on the schema-validation failure path, so the retried prompt can
reference it. Recommended vendor extensions to parse alongside this: `x-ai-fallback-model`
(swap model on transport/rate-limit failure — a different failure class from validation retry,
keep them separate) and `x-ai-max-self-correction-attempts` (tighter bound than generic
`retryLimit`, since LLM retries cost more). Also the reason this roadmap item now matters
sooner than "not started" implies: MCP/A2A both route through cross-protocol steps
([03](03-native-async-control-flow.md)'s 1.1.0 delta), so an AI node is just another step
protocol — reuse `StepProtocolExecutorInterface`, don't build a parallel AI-specific engine
path.
