# Source Resolution and OpenAPI Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve named Arazzo sources and normalize OpenAPI operations before dispatch.

**Architecture:** A named `SourceRegistry` resolves and caches source documents. An `OpenApiOperationResolver` converts an operation reference into a normalized operation. `DefaultOpenApiExecutor` consumes the normalized operation and an evaluated payload; it does not decide Arazzo control flow.

**Tech Stack:** PHP 8.4, PSR-7/PSR-18, `cebe/php-openapi`, Symfony YAML, Pest PHP.

---

## File map

- Create `packages/core/src/Resolver/SourceRegistry.php` and `SourceDocument.php`.
- Modify `packages/core/src/Resolver/DefaultSourceResolver.php`, `SourceResolver.php`, fetchers, and parsers.
- Create `packages/core/src/Runner/OpenApiOperationResolver.php` and `NormalizedOpenApiOperation.php`.
- Modify `packages/core/src/Runner/DefaultOpenApiExecutor.php`, `OpenApiParser.php`, and `Dto/OpenApiPayload.php`.
- Modify `packages/core/src/Runner/ArazzoOutputExtractor.php` and `ArazzoSchemaValidator.php` to use named source resolution.
- Modify `packages/core/tests/Resolver/DefaultSourceResolverTest.php`, parser tests, `DefaultOpenApiExecutorTest.php`, and `OpenApiParserTest.php`.
- Add multi-source and OpenAPI-version fixtures under `packages/core/tests/fixtures/`.

### Task 1: Define named source registry contracts

- [ ] Write failing tests for two named sources, relative URLs, cache hits, missing names, and circular references.
- [ ] Create `SourceDocument` with source name, type, canonical URI, parsed document, and retrieval metadata.
- [ ] Create `SourceRegistry` with `register`, `resolveByName`, `acquire`, and `clear` methods; inject fetchers/parsers rather than using globals.
- [ ] Update `DefaultSourceResolver` to resolve relative URLs against the caller’s retrieval URI, not `getcwd()`.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Resolver/DefaultSourceResolverTest.php`; expect PASS.
- [ ] Commit `feat: add named source registry`.

### Task 2: Resolve operation references structurally

- [ ] Add failing tests for plain operation IDs, `$sourceDescriptions.api.operationId`, operation paths, unknown sources, ambiguous references, and missing operations.
- [ ] Create `NormalizedOpenApiOperation` with source identity, method, URI template, parameters, request bodies, security, and responses.
- [ ] Create `OpenApiOperationResolver` that parses reference syntax and looks up the source by name; never use the first source description as a fallback.
- [ ] Replace direct `OpenApiParser::findOperation` calls in `DefaultOpenApiExecutor`, `ArazzoOutputExtractor`, and `ArazzoSchemaValidator`.
- [ ] Run focused resolver and runner tests; expect PASS.
- [ ] Commit `feat: resolve OpenAPI operations by source name`.

### Task 3: Normalize OpenAPI versions and inherited data

- [ ] Add fixtures and failing tests for OpenAPI 2.0, 3.0, and 3.1 documents, path-level parameters, operation-level parameters, servers, and referenced schemas.
- [ ] Implement version-specific normalization in `OpenApiOperationResolver`; retain a stable normalized contract for the executor.
- [ ] Resolve server variables and the selected server explicitly; make server selection injectable/configurable.
- [ ] Preserve content types, response schemas, and security requirements in the normalized object.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/DefaultOpenApiExecutorTest.php tests/Unit/Execution/OpenApiParserTest.php`; expect PASS.
- [ ] Commit `feat: normalize supported OpenAPI operation versions`.

### Task 4: Implement standards-aware request serialization

- [ ] Add failing tests for path/query/header/cookie parameters, arrays, objects, reserved characters, booleans, numbers, request bodies, content types, and JSON Pointer replacements.
- [ ] Move request construction into `DefaultOpenApiExecutor.php` using normalized parameter definitions and `OpenApiPayload`.
- [ ] Implement explicit parameter serialization instead of relying only on `http_build_query()`.
- [ ] Preserve non-JSON request bodies and select the declared media type.
- [ ] Propagate HTTP client failures unchanged through typed transport exceptions.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Runner/DefaultOpenApiExecutorTest.php`; expect PASS.
- [ ] Commit `feat: serialize OpenAPI requests from normalized operations`.

### Task 5: Update integrations and remove obsolete compiler logic

- [ ] Update `StepExecutor.php`, `HttpStepExecutor.php`, and Laravel service bindings to use the normalized executor contract.
- [ ] Remove `ArazzoRequestCompiler.php` and `RequestCompilerInterface.php` only after `rg -n 'ArazzoRequestCompiler|RequestCompilerInterface' packages` returns no runtime references.
- [ ] Update schema/output resolution to obtain the operation through `OpenApiOperationResolver`.
- [ ] Run `composer run analyse` and `composer run test`.
- [ ] Commit `refactor: complete OpenAPI operation executor migration`.
