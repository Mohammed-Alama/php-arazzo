# ADR-005: Validation degrades gracefully on unreachable remote sources

## Status

Accepted

## Context

Issue #384 adds reference resolution to `arazzo validate`: an `operationId` must
resolve to an operation in its source description, `$sourceDescriptions.<name>`
must name a declared source, and Reusable references must resolve to components.

Resolving an `operationId` requires loading the source description. Local sources
resolve offline; remote sources may be unreachable.

## Decision

When a remote source cannot be fetched or parsed during validation, it is skipped
and validation continues. Validation never fails solely because a remote source
is unreachable (R5). Source resolution is performed in `cli` (`ValidateCommand`
→ `SourceLoader`); the `document` validator never imports `sources`.

## Consequences

- Unreachable remote sources yield no operationId-existence errors.
- Unknown source qualifiers and unresolved component references are still
  reported, since they require no source loading.
- `SymbolTable` optionally carries resolved operations
  (`array<string, array<string, true>>`).