# ADR-004: Cross-workflow dependsOn and source qualification

## Status

Accepted

## Date

2026-11-17

## Issue

#388 — `fix(document): cross-workflow dependsOn and source qualification`

## Context

Validator RuleSet in `packages/document/src/Validator/RuleSet.php` needs to:

- Accept `$workflows.<id>.steps.<stepId>` in `dependsOn` (cross-workflow step dependency).
- Require source-qualified `operationId` only when several non-arazo sources exist; single non-arazo source allows unqualified.
- Keep `1.1-invalid-unqualified-operation-id-multiple-sources` invalid.

## Decision

Implement validation semantics in the RuleSet seam:

```
Validator → RuleSet::default() → StepDependsOnNoCycleRule (cross-workflow resolution)
Validator → RuleSet::default() → StepOperationIdSourceScopedRule (source qualification gating)
```

### Cross-workflow dependsOn

`StepDependsOnNoCycleRule` accepts `$workflows.<id>.steps.<stepId>` references, resolving step dependencies across workflow boundaries within a single document. Rejected documents that reference undefined workflows/steps fail validation.

### Source qualification

`StepOperationIdSourceScopedRule` gates operationId qualification on the count of non-arazo sources:

- Single non-arazo source → unqualified `operationId` allowed.
- Multiple non-arazo sources → source-qualified `operationId` required (`sourceName.operationId`).
- `1.1-invalid-unqualified-operation-id-multiple-sources` stays invalid.

## Orthogonality

Orthogonal to #341's `ExecutionState` workflows-map ownership fix:

- #388 is **document-level validation** — does the document's dependsOn reference resolve statically?
- #341 is **runtime execution** — does the runtime own and propagate workflow outputs?

They share the `workflows` concept but operate at different layers. #388 does not depend on #341's runtime changes.

## Coupling

Balanced. Integration strength is medium (model coupling to symbol table and source detection), distance is low (same package, `packages/document`); XOR = modular. Volatility is low — validation rules change rarely.

## Deepening opportunities (flagged, not yet implemented)

1. **Private ref classifier + non-arazo source count on SymbolTable** — cache the source-count classification so the qualification rule doesn't recompute it per step.
2. **No new seam unless a second consumer appears** — don't extract a separate interface until RuleSet has more than one dependsOn/qualification consumer.

## Consequences

- Cross-workflow step dependencies are accepted at validation time.
- Unqualified `operationId` with multiple non-arazo sources is rejected; single source allows it.
- No runtime behavior change — validation-only fix.
- BC break: none.

## Location note

Created under `docs/adr/` per the repository's ADR directory convention.
