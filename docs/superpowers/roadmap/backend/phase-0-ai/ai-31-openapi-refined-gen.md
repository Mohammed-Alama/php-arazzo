# OpenAPI → Arazzo AI-Refined Generator

Category: **ai** · Phase: **0-ai** · Tier: **Pro** (`arazzo-pro-ai`)
Depends on: [ai-30 deterministic generator](./ai-30-openapi-deterministic-gen.md), OSS `AiClientInterface` (shipped)

## Problem

A deterministic scaffold (ai-30) knows syntax but nothing about **intent**. It cannot decide
that "list orders → cancel one → refund" is the right sequence, that `total = sum(items.price)`
belongs on step 2 not step 4, or that a 404 on the refund step should compensate rather than
fail. That's the job of a language model with the OpenAPI, the deterministic scaffold, and a
natural-language brief from the operator all in the same prompt.

## Feature

`Alama\Arazzo\Pro\Ai\RefinedGenerator` extends ai-30's output using pluggable LLM backends:

- Backends: `OpenAiBackend`, `AnthropicBackend`, `OllamaBackend` (self-host), all behind a
  single `LlmBackendInterface` (`generate(prompt, tools[], schema): string`).
- Input: (a) OpenAPI spec, (b) deterministic scaffold, (c) natural-language intent
  ("build a workflow that provisions a new tenant, seeds their catalog, then emails welcome").
- Output: refined Arazzo workflow with reasoned step order, cross-step output wiring,
  compensation branches, and rich success/failure actions.
- Guardrails:
  - **Schema-validated** — refined output re-runs through the OSS 39-rule validator; any
    failure → automatic single-shot repair prompt; second failure → return the deterministic
    scaffold with a diagnostic block.
  - **No hallucinated operationIds** — validator rejects refs the OpenAPI doesn't define; the
    refiner is prompt-constrained to the enumerated `operationId` list from ai-30's output.
  - **Prompt caching** — the OpenAPI + rulebook are cached (Anthropic + OpenAI both support);
    only the intent + scaffold vary per call. Materially cheaper across a designer session.

Filament `WorkflowDesignerPage` (ai-32) invokes this generator via a chat pane.

## Why pro / why phase 0-ai

Arazzo's whole differentiation vs. hand-coded orchestrators is that it's LLM-writable. Not
shipping AI refinement in phase 0 leaves the flagship story on the shelf. Pro tier because
LLM API calls have marginal cost and this is the primary "why pay" moment for agencies.

## Acceptance

- Refined output validates green with no post-edit in ≥80% of a curated test set of 20
  common OpenAPI shapes.
- Same input + same seed = same output (deterministic mode for tests) using a local Ollama
  fixture; live-backend tests skipped by default in CI.
- Refiner never emits a workflow that ai-30 couldn't have produced in structure — it only
  adds compensation, output-wiring, and reordering.

## Out of scope

- Fine-tuned model shipping: OSS models loaded through Ollama are enough.
- Interactive multi-turn refinement inside a single request: that's ai-32.
