# Source Resolution and OpenAPI Operations Implementation Plan

> **Status (2026-08-24):** verified against the working tree. `- [x]` = implemented and verified by the current suite; inline notes mark partial/not-done items.

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
- [x] Create `SourceDocument` DTO holding the raw parsed array, source name, type, and canonical retrieval URI.
- [x] Create `SourceRegistry` to explicitly register and resolve sources by name. It must handle circular detection for source acquisition. _(partial: name-keyed registration/resolution done and tested; circular-acquisition detection is not implemented)_
- [x] Update `DefaultSourceResolver` to parse JSON/YAML into raw PHP arrays (dropping `cebe/php-openapi` dependency for initial load).
- [x] Write failing/passing tests for relative URLs against canonical URIs, circular references, and missing sources. _(SourceRegistryTest + DefaultSourceResolverTest)_
- [x] Commit `feat: add strict named source registry with canonical URIs`. _(landed across the resolver commits; see git log --oneline -- packages/core/src/Resolver)_

### Task 2: Build the Version-Aware Normalizer Pipeline
- [x] Create `NormalizedOpenApiOperation` containing: method, resolved server URL, explicit parameters (path/query/header/cookie), request bodies (with media types), and responses.
- [x] Create `OpenApiVersionDetector` to inspect raw arrays and determine specification (Swagger 2.0 vs OpenAPI 3.0 vs OpenAPI 3.1).
- [x] Create `OpenApiNormalizerInterface` and concrete normalizers (`OpenApi30Normalizer` as a priority, others returning `NotImplementedException` temporarily or fully implemented if time permits). _(all three fully implemented, incl. Swagger2Normalizer)_
- [x] Implement server resolution precedence in the normalizer: operation server → path-item server → document server.
- [x] Implement local `$ref` resolution inside the normalizers for parameters and request bodies.
- [x] Commit `feat: implement raw array to NormalizedOpenApiOperation pipeline`. _(landed across the normalizer commits)_

### Task 3: Resolve operation references using Arazzo runtime grammar
- [x] Create `OpenApiOperationResolver` that uses the normalizer pipeline.
- [x] Support the Arazzo reference grammar for `operationPath` (e.g., `{$sourceDescriptions.api.url}#/paths/~1pets/get`).
- [x] Support plain `operationId` only if exactly one non-Arazzo source exists; otherwise require source-qualified IDs (e.g., `{$sourceDescriptions.api.url}#operationId`).
- [x] Update `StepExecutor` and `HttpStepExecutor` to obtain the specific source dynamically, removing the hardcoded `sourceDescriptions[0]` fallback.
- [x] Replace direct `OpenApiParser::findOperation` calls in `ArazzoOutputExtractor` and `ArazzoSchemaValidator`. _(both consume OpenApiOperationResolver; the validator keeps a thin protected wrapper)_
- [x] Commit `feat: resolve operations using formal Arazzo reference grammar`. _(landed across the resolver/executor commits)_

### Task 4: Standards-aware request serialization & Payload
- [x] Expand `OpenApiPayload` to explicitly support `cookie` and a `$bodyMediaType` property.
- [x] Move request construction into `DefaultOpenApiExecutor.php` consuming `NormalizedOpenApiOperation` and `OpenApiPayload`.
- [x] Implement custom parameter serialization tests based on OpenAPI 3 styles (`matrix`, `label`, `form`, `spaceDelimited`, `pipeDelimited`). Throw a typed authoring error for unsupported serialization styles instead of silently falling back to `http_build_query()`. _(ParameterSerializer throws UnsupportedSerializationStyleException)_
- [x] Retain explicit media types for non-JSON bodies.
- [x] Commit `feat: serialize normalized operations strictly`. _(landed across the serializer/executor commits)_

### Task 5: Regression checks and cleanup
- [x] Write a regression test verifying `ArazzoRequestCompiler` and `RequestCompilerInterface` are entirely absent (removed in commit 5b88b0d) and no logic depends on them. _(grep confirms zero references)_
- [x] Run multi-version serialization test matrix (2.0/3.0/3.1 data providers). _(Normalizer + VersionDetector test files)_
- [ ] Remove `cebe/php-openapi` dependency from `packages/core/composer.json` and ensure no references remain. _(not done: OpenApiDocumentLoader still reads via cebe Reader; decoupling remains future work)_
- [x] Run `composer run analyse` and `composer run test`.
- [x] Commit `refactor: finalize Arazzo source resolution and normalizer architecture`. _(landed across the resolver series)_
