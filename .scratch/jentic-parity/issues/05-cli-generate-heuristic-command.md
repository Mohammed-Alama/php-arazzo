# 05: Add deterministic arazzo generate --heuristic command without LLM requirement

## Parent
Epic: Deterministic OpenAPI-to-Arazzo Heuristic Workflow Generator

## What to build
Provide a CLI command that synthesizes valid, fully wired Arazzo 1.0/1.1 workflow documents directly from OpenAPI schemas offline. It takes the output of the dependency analyzer, formats steps, inserts runtime parameter expressions (`value: {$steps.create_step.outputs.id}`), configures success criteria (checking 2xx status codes), and outputs valid YAML.

## Acceptance criteria
- [ ] Add `--heuristic` flag to `packages/cli/src/Console/Command/GenerateCommand.php`.
- [ ] Implement `HeuristicWorkflowSynthesizer` assembling an `ArazzoDocument` from the analyzed operation graph.
- [ ] Automatically wire path parameters between steps using Arazzo runtime expressions (`$steps.<id>.outputs.<prop>`).
- [ ] Add default 2xx `successCriteria` to generated steps.
- [ ] Output clean, validated Arazzo YAML to file or stdout.
- [ ] Add tests verifying that generated workflows validate cleanly against `packages/document` validation rules.

## Blocked by
- #94
