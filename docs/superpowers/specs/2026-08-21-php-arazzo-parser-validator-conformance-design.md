# Parser, Validator, and Arazzo Conformance

## Goal

Create a layered, official-spec-driven pipeline that prevents invalid documents from reaching execution.

## Parser

The parser decodes JSON/YAML, preserves retrieval metadata and extensions, supports the package's Arazzo 1.0.x and 1.1.x baseline, parses all supported root/workflow/step/action/parameter/request-body/source/component fields, reports source pointers, and keeps reusable references until resolution. Runtime behavior must not be embedded in DTO construction.

## Validation layers

Structural validation covers required fields, types, enums, shapes, and versions. Semantic validation covers uniqueness, references, actions, dependencies, cycles, operations, expression contexts, and replacement targets. Capability validation covers selector versions, OpenAPI versions, and configured protocol/runtime features. Execution preflight resolves sources, operations, and reusable components before side effects.

Validation returns structured errors containing rule code, severity, message, pointer, workflow ID, and step ID. Strict mode rejects unsupported content; permissive mode preserves extensions and reports diagnostics. Parser and validator errors remain distinct.

## Fixtures

Fixtures cover minimal/full documents, supported versions, multiple sources, reusable components, dependencies, all control-flow actions, request-body replacements, pointer escaping, selectors, invalid references, cycles, duplicate IDs, invalid action combinations, unsupported versions, and extensions.

## Acceptance

Every parser construct has validation coverage. Invalid documents fail before network calls. Rules are independently testable and parser output supports both execution adapters. Official Arazzo semantics take precedence over toolkit behavior.
