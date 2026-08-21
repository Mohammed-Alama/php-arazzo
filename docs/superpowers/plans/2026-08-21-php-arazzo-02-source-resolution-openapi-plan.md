# Source Resolution and OpenAPI Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve named Arazzo sources, decouple from `cebe/php-openapi`, and normalize multi-version OpenAPI operations (2.0/3.0/3.1) before dispatch.

**Architecture:** A named `SourceRegistry` manages raw source documents and their canonical URIs. A version-aware pipeline (`VersionDetector` → `NormalizerStrategy`) parses raw arrays into a `NormalizedOpenApiOperation`. Consumers (`StepExecutor`, `HttpStepExecutor`) use this normalized object to execute requests.

**Tech Stack:** PHP 8.4, PSR-7/PSR-18, Symfony YAML, Pest PHP.

---

## File map

- Create `packages/core/src/Resolver/SourceRegistry.php` and `SourceDocument.php`.
- Modify `packages/core/src/Resolver/DefaultSourceResolver.php`.
- Create `packages/core/src/Runner/Normalizer/NormalizedOpenApiOperation.php`, `OpenApiVersionDetector.php`, and `OpenApiNormalizerInterface.php`.
- Create Normalizers: `Swagger2Normalizer.php`, `OpenApi30Normalizer.php`, `OpenApi31Normalizer.php`.
- Create `packages/core/src/Runner/OpenApiOperationResolver.php`.
- Modify `packages/core/src/Runner/DefaultOpenApiExecutor.php`, `Dto/OpenApiPayload.php`.
- Modify `packages/core/src/Runner/StepExecutor.php` and `HttpStepExecutor.php`.
- Modify `packages/core/src/Runner/ArazzoOutputExtractor.php` and `ArazzoSchemaValidator.php`.

### Task 1: Define explicit named source registry boundaries
- [ ] Create `SourceDocument` DTO holding the raw parsed array, source name, type, and canonical retrieval URI.
- [ ] Create `SourceRegistry` to explicitly register and resolve sources by name. It must handle circular detection for source acquisition.
- [ ] Update `DefaultSourceResolver` to parse JSON/YAML into raw PHP arrays (dropping `cebe/php-openapi` dependency for initial load).
- [ ] Write failing/passing tests for relative URLs against canonical URIs, circular references, and missing sources.
- [ ] Commit `feat: add strict named source registry with canonical URIs`.

### Task 2: Build the Version-Aware Normalizer Pipeline
- [ ] Create `NormalizedOpenApiOperation` containing: method, resolved server URL, explicit parameters (path/query/header/cookie), request bodies (with media types), and responses.
- [ ] Create `OpenApiVersionDetector` to inspect raw arrays and determine specification (Swagger 2.0 vs OpenAPI 3.0 vs OpenAPI 3.1).
- [ ] Create `OpenApiNormalizerInterface` and concrete normalizers (`OpenApi30Normalizer` as a priority, others returning `NotImplementedException` temporarily or fully implemented if time permits).
- [ ] Implement server resolution precedence in the normalizer: operation server → path-item server → document server.
- [ ] Implement local `$ref` resolution inside the normalizers for parameters and request bodies.
- [ ] Commit `feat: implement raw array to NormalizedOpenApiOperation pipeline`.

### Task 3: Resolve operation references using Arazzo runtime grammar
- [ ] Create `OpenApiOperationResolver` that uses the normalizer pipeline.
- [ ] Support the Arazzo reference grammar for `operationPath` (e.g., `{$sourceDescriptions.api.url}#/paths/~1pets/get`).
- [ ] Support plain `operationId` only if exactly one non-Arazzo source exists; otherwise require source-qualified IDs (e.g., `{$sourceDescriptions.api.url}#operationId`).
- [ ] Update `StepExecutor` and `HttpStepExecutor` to obtain the specific source dynamically, removing the hardcoded `sourceDescriptions[0]` fallback.
- [ ] Replace direct `OpenApiParser::findOperation` calls in `ArazzoOutputExtractor` and `ArazzoSchemaValidator`.
- [ ] Commit `feat: resolve operations using formal Arazzo reference grammar`.

### Task 4: Standards-aware request serialization & Payload
- [ ] Expand `OpenApiPayload` to explicitly support `cookie` and a `$bodyMediaType` property.
- [ ] Move request construction into `DefaultOpenApiExecutor.php` consuming `NormalizedOpenApiOperation` and `OpenApiPayload`.
- [ ] Implement custom parameter serialization tests based on OpenAPI 3 styles (`matrix`, `label`, `form`, `spaceDelimited`, `pipeDelimited`). Throw a typed authoring error for unsupported serialization styles instead of silently falling back to `http_build_query()`.
- [ ] Retain explicit media types for non-JSON bodies.
- [ ] Commit `feat: serialize normalized operations strictly`.

### Task 5: Regression checks and cleanup
- [ ] Write a regression test verifying `ArazzoRequestCompiler` and `RequestCompilerInterface` are entirely absent (removed in commit 5b88b0d) and no logic depends on them.
- [ ] Run multi-version serialization test matrix (2.0/3.0/3.1 data providers).
- [ ] Remove `cebe/php-openapi` dependency from `packages/core/composer.json` and ensure no references remain.
- [ ] Run `composer run analyse` and `composer run test`.
- [ ] Commit `refactor: finalize Arazzo source resolution and normalizer architecture`.
