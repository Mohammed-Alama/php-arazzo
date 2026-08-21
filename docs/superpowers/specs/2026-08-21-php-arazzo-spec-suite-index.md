# PHP-First Arazzo Spec Suite

## Decision baseline

These specifications improve the PHP packages in this repository. The official Arazzo specification is authoritative. The `arazzo-toolkit` project is used only as a reference for useful behavior, fixtures, API boundaries, and test coverage. Breaking changes are allowed because both PHP packages are alpha releases.

The selected architecture is a canonical PHP execution core with synchronous, queue, and Laravel adapters.

## Specification order

1. [Canonical Execution Core](2026-08-21-php-arazzo-canonical-execution-core-design.md)
2. [Source Resolution and OpenAPI Operations](2026-08-21-php-arazzo-source-resolution-openapi-design.md)
3. [Runtime Expressions, Selectors, Outputs, and Errors](2026-08-21-php-arazzo-runtime-semantics-design.md)
4. [Parser, Validator, and Conformance](2026-08-21-php-arazzo-parser-validator-conformance-design.md)
5. [Testing and Cross-Language Conformance](2026-08-21-php-arazzo-testing-conformance-design.md)
6. [Documentation, Packaging, CI, and Release Readiness](2026-08-21-php-arazzo-release-readiness-design.md)

## Dependencies

Spec 1 establishes the state machine and adapter boundaries. Specs 2–4 provide the domain capabilities consumed by that core. Spec 5 verifies the behavior. Spec 6 makes the resulting packages consumable and releasable.

## Cross-cutting rules

- Arazzo semantics take precedence over existing implementation behavior.
- Public API changes may be breaking.
- Synchronous and queued execution must share transition logic.
- Adapters may add infrastructure behavior but may not redefine workflow semantics.
- Errors must remain typed, contextual, and machine-readable.
