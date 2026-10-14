# 05: Support cross-document workflow references ($sourceDescriptions.{name}.{workflowId})

**What to build:**
Enable workflow references targeting workflows defined in external Arazzo documents via `$sourceDescriptions.{name}.{workflowId}` runtime expressions. This allows sub-workflow steps, `dependsOn` declarations, retry repair targets, and goto one-way transfers to cleanly reference and execute workflows defined across multiple federated Arazzo documents.

**Blocked by:** 03: Support step-level goto to workflowId as one-way transfer

**Status:** ready-for-agent

- [ ] Create `ArazzoWorkflowLocator` and `WorkflowLocatorNormalizer` in `packages/runner/src/Locator/`.
- [ ] Parse workflow references: distinguish local identifiers (`workflow_id`) from sourced runtime expressions (`$sourceDescriptions.{name}.{workflowId}`).
- [ ] Resolve named source documents through `SourceRegistry` / `SourceNormalizerRegistry`, confirming the source description has type `arazzo` (or parses to an Arazzo document).
- [ ] Update `SubWorkflowExecutor`, dependency graph resolution (`dependsOn`), `retry` references, and `goto` transfers to accept `ArazzoWorkflowLocator`.
- [ ] Provide clear execution errors when a referenced source does not exist or does not define the target workflow.
- [ ] Add multi-document fixtures and integration tests executing cross-document workflows.
