
# OpenAPI → Arazzo Deterministic Generator — Design

Stub: [`docs/superpowers/roadmap/backend/phase-0-ai/ai-30-openapi-deterministic-gen.md`](../roadmap/backend/phase-0-ai/ai-30-openapi-deterministic-gen.md)
Category: **ai** · Phase: **0-ai** · Tier: **OSS**
Depends on: parser + validator (shipped), `DependencyGraph` (core-37, shipped)

## Problem

Turning an OpenAPI spec into an Arazzo scaffold today is manual copy-paste. The only
existing generator (`Alama\Arazzo\Generator\ArazzoGenerator`) is LLM-backed — it sends the
whole OpenAPI spec + a natural-language trace to an `AiClientInterface` and returns
whatever YAML text the model produces, with no structural guarantee it's even valid Arazzo.
There is no deterministic, no-LLM path from "here is my openapi.yaml" to "here is a valid
draft workflow.yaml" — and no such path can exist without one missing piece: the parser
only goes one direction. `Loader` decodes YAML/JSON to array, `Parser` builds array to
`ArazzoDocument`. Nothing goes DTO to array to YAML. This spec has to build that reverse
path as much as it has to build the generator itself.

## Approach

Two new components in `alama/arazzo-core`, both framework-agnostic:

1. `DeterministicGenerator` walks a parsed OpenAPI document, emits one Arazzo `Step`
   per selected operation, wires parameters/request-body fields to prior step outputs by
   exact name match, defaults a success criterion from the operation's documented 2xx
   response, and extracts top-level response fields as outputs. Builds a real
   `ArazzoDocument` DTO tree, not a hand-rolled array, so every value it produces is the
   same typed shape the `Parser` would have built from hand-written YAML.
2. `ArazzoDocumentWriter` serializes an `ArazzoDocument` DTO tree back into a plain array
   matching Arazzo's document schema, then hands that array to a format-specific
   `DocumentEncoder` — `YamlDocumentEncoder` (default, backed by
   `Symfony\Component\Yaml\Yaml::dump()`, already a core dependency) or `JsonDocumentEncoder`
   (backed by `json_encode()`). The writer itself never touches YAML or JSON syntax — it
   only builds the normalized array and picks an encoder by `Format` (the same
   `Alama\Arazzo\Dto\Enum\Format` enum `Loader` already uses for the opposite direction,
   reused rather than duplicated). This mirrors `Loader`'s own `YamlDecoder`/`JsonDecoder`
   split — the writer is `Loader`'s reverse counterpart, so it takes the same shape. This is
   new infrastructure, not generator-specific plumbing: core-35's CLI needs it for
   `-o out.yaml` (and presumably `-o out.json`), and ai-31 (LLM-refined generator) and ai-32
   (designer agent) will both want to emit structured Arazzo documents instead of raw LLM
   text. It lives at `Generator/Support/ArazzoDocumentWriter` (+ `Generator/Support/Encoding/*`)
   for this stub; if ai-31/ai-32/core-35 all end up depending on it, promoting it to a
   top-level `Alama\Arazzo\Serialization\*` namespace is a one-file move, not a redesign.

Determinism strategy: no heuristic in this generator is allowed to depend on iteration
order that isn't already fixed by the input file. Operation order comes from
cebe/php-openapi's parse of `paths` (insertion-order preserving, i.e. document order),
method order within a path uses the same fixed method list `OpenApiParser::findOperation`
already uses (get, put, post, delete, options, head, patch, trace), and parameter/property
order comes straight from the OpenAPI schema's own declared order.

Why DTO-first, not array-first: building an `ArazzoDocument` object tree (rather than a
raw associative array matching the YAML shape) means the generator's output is
structurally identical, at the type level, to what `Parser::parse()` produces from
hand-written YAML. That closes the loop on the stub's acceptance criterion (produces a
workflow.yaml that validates green through the existing validator) because the writer's
round-trip (`ArazzoDocument` to array to YAML to `Loader` to `Parser` to `ArazzoDocument`)
is exercised as a real regression test, not just asserted by inspection. The DTO-to-array
normalization step is also what keeps the writer format-agnostic: it happens once, in
`ArazzoDocumentWriter` itself, before any encoder sees the data — `YamlDocumentEncoder`
and `JsonDocumentEncoder` both receive the identical plain array and differ only in how
they serialize it to a string.

No explicit `dependsOn`: because `DependencyGraph` (core-37, already shipped) mines
implicit `{$steps.X.outputs.Y}` references from every step field automatically, wiring a
parameter or request-body value to a prior step's output is sufficient for correct
execution order. The generator never needs to populate `Step::$dependsOn`.

## Architecture

New files, all `alama/arazzo-core` (post-extraction path: `packages/core/src/Generator/...`):

- `Generator/DeterministicGenerator.php` — the public entry point.
- `Generator/Support/OperationCollector.php` — enumerates/selects OpenAPI operations in
  deterministic order.
- `Generator/Support/StepScaffolder.php` — builds one `Step` DTO from one `Operation` +
  the running "prior outputs" name index.
- `Generator/Support/ArazzoDocumentWriter.php` — `ArazzoDocument` to normalized array,
  then delegates to a `DocumentEncoder`.
- `Generator/Support/Encoding/DocumentEncoder.php` — `encode(array $document): string`
  interface.
- `Generator/Support/Encoding/YamlDocumentEncoder.php` — default; wraps `Yaml::dump()`.
- `Generator/Support/Encoding/JsonDocumentEncoder.php` — wraps `json_encode()` with
  pretty-print + unescaped slashes/unicode flags.
- `Generator/Exceptions/GeneratorException.php` — wraps operation-not-found, unreadable
  spec, empty-selection failures. Extends `Alama\Arazzo\Exceptions\ArazzoException` for
  consistency with the rest of core's exception hierarchy.

No changes to `Dto/*`, `Parser/*`, `Validation/*`, or `Loader/*`. This is additive.

## API

```php
namespace Alama\Arazzo\Generator;

use Alama\Arazzo\Dto\ArazzoDocument;

final class DeterministicGenerator
{
    /**
     * @param array{
     *   workflowId?: string,
     *   title?: string,
     *   version?: string,
     *   sourceName?: string,
     *   specVersion?: '1.0.0'|'1.1.0',
     *   steps?: list<string>,
     * } $hints
     */
    public function fromSpec(string $path, array $hints = []): ArazzoDocument;

    /**
     * Convenience: fromSpec() + ArazzoDocumentWriter in one call.
     * Defaults to Format::Yaml, matching ArazzoDocumentWriter's own default.
     */
    public function fromSpecToString(string $path, array $hints = [], Format $format = Format::Yaml): string;
}
```

```php
namespace Alama\Arazzo\Generator\Support;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

final class OperationCollector
{
    /** @return list<array{0: string, 1: string, 2: Operation}> [method, path, Operation], document order, operationId required (unnamed ops skipped) */
    public static function all(OpenApi $openApi): array;

    /**
     * @param list<string> $operationIds  order defines generated step order
     * @return list<array{0: string, 1: string, 2: Operation}>
     * @throws GeneratorException if any id is not found (delegates to OpenApiParser::findOperation per id)
     */
    public static function forIds(OpenApi $openApi, array $operationIds): array;
}
```

```php
namespace Alama\Arazzo\Generator\Support\Encoding;

interface DocumentEncoder
{
    /** @param array<string, mixed> $document normalized, format-independent */
    public function encode(array $document): string;
}

final class YamlDocumentEncoder implements DocumentEncoder { /* Yaml::dump() */ }

final class JsonDocumentEncoder implements DocumentEncoder { /* json_encode() */ }
```

```php
namespace Alama\Arazzo\Generator\Support;

use Alama\Arazzo\Dto\Enum\Format;

final class ArazzoDocumentWriter
{
    public function __construct(
        private readonly Encoding\DocumentEncoder $yamlEncoder = new Encoding\YamlDocumentEncoder(),
        private readonly Encoding\DocumentEncoder $jsonEncoder = new Encoding\JsonDocumentEncoder(),
    ) {
    }

    public function write(\Alama\Arazzo\Dto\ArazzoDocument $doc, Format $format = Format::Yaml): string
    {
        $normalized = $this->toArray($doc);

        return match ($format) {
            Format::Yaml => $this->yamlEncoder->encode($normalized),
            Format::Json => $this->jsonEncoder->encode($normalized),
        };
    }

    /** @return array<string, mixed> */
    private function toArray(\Alama\Arazzo\Dto\ArazzoDocument $doc): array; // format-independent normalization
}
```

## Behavior

1. Load. `fromSpec()` reads the file, sniffs YAML vs JSON (reuses the same
`!str_starts_with(trim($content), '{')` heuristic `OpenApiSourceParser` already uses),
parses via `cebe\openapi\Reader::readFromYaml()` / `readFromJson()` directly, not
through `Resolution\Parsers\OpenApiSourceParser`, because that class returns an
`OpenApiResolvedSource` whose only public surface is JSON-Pointer `extract()`, built for
runtime step-output extraction, not structural operation enumeration.

2. Select operations.
- `$hints['steps']` present: `OperationCollector::forIds($openApi, $hints['steps'])`,
  step order = hint order.
- Absent: `OperationCollector::all($openApi)`, step order = document order.
- Each `Operation` without an `operationId` is skipped (Arazzo step generation needs a
  stable id to reference — see Out of Scope for the unnamed-operation case).

3. Per-operation step scaffold (`StepScaffolder`), given a running index of
`priorOutputs: array<string /* output name */, string /* stepId */>` seeded empty and
extended after each step is built:

- `stepId`: `slugify(operationId)` — lowercase, non-`[a-z0-9_-]` runs collapsed to a
  single `-`, trimmed. Implementation note: verify the resulting pattern against
  `StepIdPatternRule`'s actual regex before shipping; if `slugify()` can produce a string
  the rule rejects, tighten the slugger, don't loosen the rule.
- `parameters`: union of the operation's own `parameters` and its `PathItem`-level
  `parameters` (OpenAPI allows path-level parameters shared across all methods on that
  path; operation-level entries with the same name+in override the path-level one).
  `OperationCollector` must return the owning `PathItem` alongside the `Operation` so this
  merge is possible. For each merged parameter: `name` = param name,
  `in` = `ParameterIn::from($param->in)` (OpenAPI's query/header/path/cookie map directly
  onto existing `ParameterIn` cases), `value` = if `priorOutputs[$param->name]` exists,
  `new Expression("{\$steps.{$priorOutputs[$param->name]}.outputs.{$param->name}}")`;
  else `new Expression("{\$inputs.{$param->name}}")`, recording the name (+ declared
  schema type, default 'string') into the workflow-level input accumulator.
- `requestBody`: only when `Operation::$requestBody` is set and its content includes
  `application/json`. Walk the media type's schema: if `type === 'object'` and
  `properties` non-empty, build a `payload` array keyed by property name, each value
  resolved by the same prior-output-else-input rule as parameters, matched by property
  name. `contentType = 'application/json'`, `replacements = []` (no partial-payload
  patching in v1). Non-object / non-JSON request bodies: emit no `requestBody` for that
  step — documented gap, not a crash.
- `successCriteria`: single `SuccessCriterion(context: null, condition: "\$statusCode == {$code}", type: CriterionType::Simple, version: null)` where `$code` is the first
  declared 2xx status code in `Operation::$responses` (document order), falling back to
  200 if none declared. This is a deliberate refinement over the stub's literal
  `$statusCode == 200` example — a 201 Created or 204 No Content operation with a
  hardcoded `== 200` check would generate a scaffold that always fails its own success
  criterion.
- `outputs`: only from the same response used for the success criterion, only when its
  content includes `application/json` with an object schema with declared `properties`.
  For each top-level property `name`:
  `outputs[$name] = new Selector(context: '$response.body', selector: "\$.{$name}", type: ExpressionType::JsonPath)`.
  Implementation note: the exact raw-document shape a `Selector` value serializes to is not
  yet established anywhere in the codebase — `ArazzoDocumentWriter::toArray()` must
  reverse-engineer this against `Parser::parse()`'s actual Selector-building logic and add
  a round-trip fixture that proves it (in both YAML and JSON), rather than guessing a shape.
  No object-schema response, or no properties: `outputs = []` for that step (not an error).
- `dependsOn`: always `[]` — see Approach.
- Every output name this step produces is added to `priorOutputs` before the next
  operation is scaffolded.

4. Workflow assembly.
- `workflowId` = `$hints['workflowId']` or, if exactly one operation was selected,
  `slugify(operation.operationId)`, else a generic fallback (`'generated-workflow'`).
- `inputs`: a JSON-Schema object assembled from every parameter/property that fell back
  to `{$inputs.*}` during scaffolding — one property per unique input name, `type` copied
  from the originating OpenAPI schema when known, else 'string'.
- `steps` = the scaffolded list, in selection order.
- `dependsOn`, `successActions`, `failureActions`, workflow-level `outputs`,
  workflow-level `parameters` = `[]` — v1 does not attempt to guess workflow-level
  success/failure routing.
- `sourceDescriptions` = one entry: `name = $hints['sourceName'] ?? 'api'`,
  `url = $path` (or a hint override), `type = SourceType::OpenApi`.
- `info` = `title: $hints['title'] ?? "Generated: {$workflowId}"`,
  `version: $hints['version'] ?? '1.0.0'`.
- `arazzo` = `$hints['specVersion'] ?? '1.1.0'` (defaults to 1.1.0 since that's the
  natively supported version as of core-34; 1.0.0 remains available via hint).

5. Write. `ArazzoDocumentWriter::toArray($document)` converts the `ArazzoDocument` tree to
a plain, format-independent array — `Expression` values serialize to their `->raw` string,
`Selector` values to whatever shape step 3's implementation note resolves, enums to their
`->value`. `write($document, $format)` then hands that array to the selected
`DocumentEncoder`. `YamlDocumentEncoder` calls `Yaml::dump()` (exact flags TBD during
implementation to match existing hand-written fixture style; no trailing whitespace, LF
line endings). `JsonDocumentEncoder` calls `json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`. Both encoders receive array key order
fixed by construction order above, so both are deterministic regardless of format.

## CLI Integration (forward reference, implemented in core-35)

core-35's stub already lists `arazzo generate:from-openapi <spec> [--workflow-id=<id>] [-o out.yaml]` — this spec's `DeterministicGenerator::fromSpecToString()` is the entire
implementation of that command's body, with `-o out.json` naturally supported for free via
`Format::fromExtension()` on the output path (same enum `Loader` already uses to detect
input format — core-35's CLI gets JSON output support without asking for it). No new
design needed there; flagging the dependency direction so core-35's plan doesn't re-derive
this logic.

## Testing

Fixtures (`tests/fixtures/generator/`):
- `petstore-minimal.yaml` — small OpenAPI 3.1 spec, 3 operations
  (getPet, createOrder, payOrder), request bodies + path params + a 201 response on
  createOrder (exercises the non-200 success-criterion refinement).
- `petstore-path-level-params.yaml` — one path with a path-level parameters block
  shared across two methods, one operation overriding one of them (exercises the merge
  rule).
- `no-operation-id.yaml` — one named + one unnamed operation on the same path (exercises
  the skip-unnamed-operations rule).
- `no-json-response.yaml` — an operation whose only 2xx response has no content block
  (exercises empty-outputs path).

Test suites (Pest):
- `tests/Generator/OperationCollectorTest.php` — document order for `all()`, hint order
  + not-found error for `forIds()`, path-level parameter merge.
- `tests/Generator/StepScaffolderTest.php` — parameter wiring (prior-output vs
  `$inputs.*`), request body object walk, success-criterion status-code selection
  (200 default, 201/204 refinement, no-2xx fallback), outputs from object schema, empty
  outputs when no object schema.
- `tests/Generator/DeterministicGeneratorTest.php` — end-to-end `fromSpec()` against each
  fixture; asserts on the resulting `ArazzoDocument` structure directly.
- `tests/Generator/ArazzoDocumentWriterTest.php` — round-trip regression, run for **both**
  `Format::Yaml` and `Format::Json`: for every fixture, `fromSpec()` then
  `ArazzoDocumentWriter::write()` then `Loader::load()` (via a temp file or in-memory
  `RawDocument`) then `Parser::parse()` then `Validator`, assert zero validation errors.
  This is the test that actually proves the stub's #1 acceptance criterion, and proves it
  for both formats, not just the default.
- `tests/Generator/Encoding/YamlDocumentEncoderTest.php` /
  `JsonDocumentEncoderTest.php` — each encoder in isolation against a small fixed array
  (no `ArazzoDocument` involved), asserting exact output string.
- `tests/Generator/DeterminismTest.php` — run `fromSpec()` on the same fixture twice
  (fresh generator instance each time) for both formats, assert byte-identical output per
  format.

Regression sweep: full existing Pest suite green; PHPStan max level clean on all new files.

## Migration + CHANGELOG

`## Unreleased` → `### Added`:

- `DeterministicGenerator` — no-LLM OpenAPI to Arazzo scaffold generator. `fromSpec()` /
  `fromSpecToString()`.
- `OperationCollector`, `StepScaffolder` — generator support classes.
- `ArazzoDocumentWriter` + `DocumentEncoder` (`YamlDocumentEncoder` default,
  `JsonDocumentEncoder`) — `ArazzoDocument` to canonical YAML or JSON, format selected via
  the existing `Format` enum. First DTO-to-document path in the codebase; `Loader`/`Parser`
  remain document-to-DTO only, this is their mirror image.
- `GeneratorException`.

No breaking changes — entirely additive, no existing public API touched.

## Acceptance

Matches stub Acceptance section:

1. Given an OpenAPI 3.1 spec, produces a workflow.yaml (or `.json`) that validates green
   through the existing validator — proven by `ArazzoDocumentWriterTest`'s real round-trip
   in both formats, not inspection.
2. Executes end-to-end against a matching mock server when success criteria are
   satisfied — covered by a follow-up integration test using the existing
   `WorkflowExecutor` against `petstore-minimal.yaml`'s generated output + a mock HTTP
   client (exact harness TBD at plan time).
3. Deterministic: same input to byte-identical output — `DeterminismTest`.
4. No network calls, no LLM, no runtime randomness — enforced by construction (no
   `AiClientInterface` dependency anywhere in this module; `cebe/php-openapi` reads local
   file content only).

## Out of Scope

- Semantic step ordering ("payment before shipping") — ai-31 (LLM-refined generator).
- Type-directed (not just name-directed) parameter/payload wiring — ai-31.
- Multi-workflow generation from one spec — v2.
- Non-JSON request/response content types (XML, form-encoded, etc.) — v2.
- Partial payload patching via `PayloadReplacement` (v1 always emits a full fresh
  payload object) — v2, if a real use case shows up.
- Unnamed-operation handling (auto-deriving a stepId from method+path when no
  operationId is declared) — v2; today those operations are silently skipped.
- Workflow-level successActions/failureActions inference — not attempted.

## References

- Stub: `docs/superpowers/roadmap/backend/phase-0-ai/ai-30-openapi-deterministic-gen.md`
- Existing single-operation lookup: `src/Execution/OpenApiParser.php` (method order list
  reused verbatim for determinism).
- Existing OpenAPI content parsing pattern: `src/Resolution/Parsers/OpenApiSourceParser.php`
  (YAML/JSON sniff heuristic reused; the class itself is not reused — see Behavior 1).
- Existing DTOs consumed as-is: `Dto/Step.php`, `Dto/Workflow.php`, `Dto/Parameter.php`,
  `Dto/RequestBody.php`, `Dto/SuccessCriterion.php`, `Dto/Selector.php`,
  `Dto/Expression.php`, `Dto/SourceDescription.php`, `Dto/ArazzoDocument.php`.
- Dependency-order rationale: `docs/superpowers/specs/shipped/2026-07-25-core-37-dependency-graph-design.md`
  (implicit `$steps.X` reference mining — the reason this generator never emits `dependsOn`).
- LLM-backed sibling (different problem, not reused): `src/Generator/ArazzoGenerator.php`.
