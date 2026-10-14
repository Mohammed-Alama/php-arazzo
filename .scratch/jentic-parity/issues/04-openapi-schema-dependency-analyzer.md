# 04: Analyze OpenAPI operation schemas to discover CRUD entity lifecycles and ID dependencies

## Parent
Epic: Deterministic OpenAPI-to-Arazzo Heuristic Workflow Generator

## What to build
Build a deterministic OpenAPI dependency analyzer that models entity relationships across endpoints. It detects producer operations (e.g. `POST /pets` returning an `id`) and consumer operations (e.g. `GET /pets/{petId}`, `PUT /pets/{petId}`, `DELETE /pets/{petId}`), constructing an operation graph that defines valid workflow sequences.

## Acceptance criteria
- [ ] Implement `OpenApiDependencyAnalyzer` in `packages/document/src/Analyzer/`.
- [ ] Classify endpoints into CRUD roles using path tokens, HTTP verbs, and response schemas.
- [ ] Match path parameter names (`{id}`, `{itemId}`, `{petId}`) to property names in creation request/response schemas.
- [ ] Output a directed graph of ordered operations representing valid entity lifecycles (Create -> Read -> Update -> Delete).
- [ ] Test analyzer on Petstore, GitHub, and Stripe OpenAPI specs to verify accurate dependency graphs.

## Blocked by
None (can start immediately).
