# OpenAPI → Arazzo Deterministic Generator

Category: **ai** · Phase: **0-ai** · Tier: **OSS**
Depends on: parser + validator (shipped)

## Problem

Turning an OpenAPI spec into an Arazzo scaffold today is a manual copy-paste job. Every step
(`operationId`, parameters, request body, output extraction, success criteria) is retyped.
A deterministic scaffold — no LLM, no guessing — gets a user from "here is my swagger.yaml"
to "here is a valid draft workflow.yaml" in one CLI call, and is the foundation the pro AI
refiner (ai-31) and interactive designer (ai-32) build on top of.

## Feature

Ship `Alama\Arazzo\Generator\DeterministicGenerator` in the OSS core:

```php
$scaffold = (new DeterministicGenerator($openapiParser))
    ->fromSpec($path, hints: [
        'workflowId' => 'checkout-happy-path',
        'steps'      => ['getCart', 'validateStock', 'createOrder', 'chargeCard'],
    ]);
```

Behaviour:

- Walk `operationId`s in order supplied (or all, in file order, if `steps` omitted).
- For each op: emit one Arazzo step with resolved `operationId`, all parameters mapped from
  the OpenAPI schema, request body wired to `$steps.previous.outputs.*` when the type matches,
  a default success criterion of `$statusCode == 200`.
- Emit `outputs` from all response schema top-level fields via JSONPath.
- Emit deterministic step IDs (`slugify(operationId)`).
- Output valid Arazzo 1.1.0 YAML that passes the existing 39 validators.

CLI: `arazzo generate:from-openapi <spec.yaml> [--workflow-id=<id>] [--steps=a,b,c] [-o out.yaml]`.

## Why prioritize (Phase 0-ai)

Arazzo is LLM-legible on purpose. Every downstream AI feature (refined generator, natural-lang
designer, agent routing) reads/writes Arazzo text. A deterministic scaffold gives the AI stack
a canonical starting point and makes it possible to A/B a "no AI" baseline against pro variants
— which is both a good product story and a good test-suite anchor.

## Acceptance

- Given an OpenAPI 3.1 spec, produces a workflow.yaml that (a) validates green through the
  existing validator, (b) executes end-to-end against a matching mock server when success
  criteria are satisfied.
- Deterministic: same input → byte-identical output.
- No network calls, no LLM, no runtime randomness.

## Out of scope

- Semantic step ordering ("payment before shipping"): that's ai-31 (LLM-refined).
- Multi-workflow generation from one spec: v2.
