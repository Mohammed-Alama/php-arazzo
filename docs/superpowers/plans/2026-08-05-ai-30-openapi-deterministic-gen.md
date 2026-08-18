
# OpenAPI → Arazzo Deterministic Generator — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a no-LLM `DeterministicGenerator` that turns an OpenAPI 3.x spec into a valid, deterministic Arazzo scaffold, plus the `ArazzoDocumentWriter` (+ YAML/JSON encoders) needed to serialize it — the first DTO-to-document path in `alama/arazzo-core` (`Loader`/`Parser` only go document-to-DTO).

**Architecture:** `DeterministicGenerator::fromSpec()` loads an OpenAPI file via `cebe\openapi\Reader` directly, selects operations via `OperationCollector` (document order or a `steps` hint), scaffolds one `Step` DTO per operation via `StepScaffolder` (parameters/request body wired to prior step outputs by name, success criterion from the first declared 2xx response, outputs from that response's top-level schema properties), and assembles a real `ArazzoDocument`. `ArazzoDocumentWriter::write()` normalizes that DTO tree to a plain array (format-independent) and hands it to a `DocumentEncoder` (`YamlDocumentEncoder` default, `JsonDocumentEncoder`), reusing the existing `Alama\Arazzo\Dto\Enum\Format` enum `Loader` already uses for the opposite direction.

**Tech Stack:** PHP 8.4, Pest 4, PHPStan (larastan) max level, Laravel Pint, `cebe/php-openapi ^1.7`, `symfony/yaml ^7`, package `alama/arazzo-core`.

**Spec:** `docs/superpowers/specs/2026-08-03-ai-30-openapi-deterministic-gen-design.md`
**Stub:** `docs/superpowers/roadmap/backend/phase-0-ai/ai-30-openapi-deterministic-gen.md`

## Global Constraints

- PHP version: `^8.4`. Namespace root: `Alama\Arazzo\` → `packages/core/src/`. Test namespace: `Alama\Arazzo\Tests\` → `packages/core/tests/`.
- Test framework: Pest 4, run from `packages/core/`: `vendor/bin/pest`.
- Static analysis gate: PHPStan max level, must stay clean: `vendor/bin/phpstan analyse` (run from `packages/core/`).
- Formatter: Laravel Pint: `vendor/bin/pint`.
- This plan targets the **current, already-extracted** structure (`packages/core/src/...`, namespace `Alama\Arazzo\*`, package `alama/arazzo-core`) — confirmed live in the repo as of this plan's writing (core-34/37/38 have already shipped under this structure).
- Confirmed from source (no longer "TBD" — see spec's two flagged unknowns):
  - `Selector` raw document shape (`Parser::parseValueOrSelector`, `packages/core/src/Parser/Parser.php`): a plain map `{selector: string, type: string, context?: string, version?: string}`. `type` is a `CriterionType`/`ExpressionType`-style string value (e.g. `'jsonpath'`).
  - `StepIdPatternRule` regex (`packages/core/src/Validation/Rules/StepIdPatternRule.php`): `/^[A-Za-z0-9_\-]+$/`. A lowercase, `-`-joined slug always satisfies this — no further verification needed.
  - `SourceType` enum case for OpenAPI is `SourceType::Openapi` (lowercase `p`, not `OpenApi`) — `packages/core/src/Dto/Enum/SourceType.php`.
  - `ArazzoException` constructor shape (`packages/core/src/Exceptions/ArazzoException.php`): `__construct(string $message, string $path = '', string $codeId = '', ?Throwable $previous = null)`. All new exceptions in this plan follow this shape (see `LoaderException` for the established pattern).
- Not yet confirmed — resolved in Task 0 before it's needed: how the *full* (all ~40+ rules) `RuleSet` is assembled elsewhere in the codebase, since `RuleSet::default()` returns an **empty** ruleset (`new self([], ...)`) and `Fx.php` doesn't build one either. Needed for Task 6's round-trip regression test.
- Commit convention: Conventional Commits.
- New namespace additions only — no changes to `Dto/*`, `Parser/*`, `Validation/*`, or `Loader/*`.

---

## File Structure

**New files (source), all under `packages/core/src/`:**

- `Generator/Support/Encoding/DocumentEncoder.php`
- `Generator/Support/Encoding/YamlDocumentEncoder.php`
- `Generator/Support/Encoding/JsonDocumentEncoder.php`
- `Generator/Support/ArazzoDocumentWriter.php`
- `Generator/Support/OperationCollector.php`
- `Generator/Support/StepScaffolder.php`
- `Generator/DeterministicGenerator.php`
- `Generator/Exceptions/GeneratorException.php`

**New fixtures**, `packages/core/tests/fixtures/generator/`:

- `petstore-minimal.yaml`
- `petstore-path-level-params.yaml`
- `no-operation-id.yaml`
- `no-json-response.yaml`

**New test files**, `packages/core/tests/Generator/...` (one per new class + round-trip + determinism).

---

### Task 0: Recon — locate the full `RuleSet` assembly

**Files:** none created; this is a pure investigation task whose output feeds Task 6.

- [ ] **Step 1: Find how a full (all-rules) RuleSet is built elsewhere**

Run: `grep -rln "new RuleSet(\[" packages/core/src packages/core/tests`
Run: `grep -rln "withRule(new " packages/core/tests | head -5`
Run: `grep -rn "RuleSet" packages/laravel/src packages/laravel/config 2>/dev/null`

One of these will reveal the pattern: either a config array (`packages/laravel/config/arazzo.php`) listing every `Rules\*` class, a hardcoded array literal somewhere in a Validator-wiring test, or a `RuleSet` factory not yet seen. Record the exact construction snippet — Task 6's round-trip test needs to build a `RuleSet` containing every shipped rule (or at minimum `StepIdPatternRule` + the structural rules that would reject a malformed generator scaffold — `StepUniqueIdRule`, `WorkflowAtLeastOneRule`, `StepAtLeastOneRule`, `DocumentInfoRequiredRule`, `DocumentArazzoVersionRule`, `SourceUniqueNameRule`, `SourceTypeMatchesRule` at minimum, since those are the rules a generator bug would most plausibly trip).

- [ ] **Step 2: Note the finding inline for Task 6**

No commit for this task — it's recon. Carry the exact construction snippet forward into Task 6 Step 2.

---

### Task 1: `DocumentEncoder` interface + `YamlDocumentEncoder` + `JsonDocumentEncoder`

**Files:**
- Create: `packages/core/src/Generator/Support/Encoding/DocumentEncoder.php`
- Create: `packages/core/src/Generator/Support/Encoding/YamlDocumentEncoder.php`
- Create: `packages/core/src/Generator/Support/Encoding/JsonDocumentEncoder.php`
- Test: `packages/core/tests/Generator/Encoding/YamlDocumentEncoderTest.php`
- Test: `packages/core/tests/Generator/Encoding/JsonDocumentEncoderTest.php`

**Interfaces:**
- Produces: `DocumentEncoder::encode(array $document): string`. Both concrete encoders take a plain `array<string,mixed>` and return a string — no knowledge of `ArazzoDocument` at all, deliberately (keeps them reusable outside the generator, per the design's "shared infra" framing).

- [ ] **Step 1: Write failing tests**

Create `packages/core/tests/Generator/Encoding/YamlDocumentEncoderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator\Encoding;

use Alama\Arazzo\Generator\Support\Encoding\YamlDocumentEncoder;

it('encodes a plain array to YAML', function () {
    $encoder = new YamlDocumentEncoder();
    $yaml = $encoder->encode(['arazzo' => '1.1.0', 'info' => ['title' => 'T', 'version' => '1']]);

    expect($yaml)->toContain('arazzo: 1.1.0')
        ->and($yaml)->toContain('title: T');
});

it('produces no trailing whitespace on any line', function () {
    $encoder = new YamlDocumentEncoder();
    $yaml = $encoder->encode(['a' => ['b' => 'c', 'd' => ['e', 'f']]]);

    foreach (explode("\n", $yaml) as $line) {
        expect($line)->toBe(rtrim($line));
    }
});

it('is deterministic for the same input', function () {
    $encoder = new YamlDocumentEncoder();
    $doc = ['z' => 1, 'a' => 2, 'm' => ['x' => 1, 'y' => 2]];

    expect($encoder->encode($doc))->toBe($encoder->encode($doc));
});

it('preserves array insertion order (not alphabetical)', function () {
    $encoder = new YamlDocumentEncoder();
    $yaml = $encoder->encode(['z' => 1, 'a' => 2]);

    expect(strpos($yaml, 'z:'))->toBeLessThan(strpos($yaml, 'a:'));
});
```

Create `packages/core/tests/Generator/Encoding/JsonDocumentEncoderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator\Encoding;

use Alama\Arazzo\Generator\Support\Encoding\JsonDocumentEncoder;

it('encodes a plain array to pretty JSON', function () {
    $encoder = new JsonDocumentEncoder();
    $json = $encoder->encode(['arazzo' => '1.1.0', 'info' => ['title' => 'T']]);

    $decoded = json_decode($json, true);
    expect($decoded)->toBe(['arazzo' => '1.1.0', 'info' => ['title' => 'T']])
        ->and($json)->toContain("\n"); // pretty-printed, not single-line
});

it('does not escape slashes', function () {
    $encoder = new JsonDocumentEncoder();
    $json = $encoder->encode(['url' => 'https://example.com/api']);

    expect($json)->toContain('https://example.com/api')
        ->and($json)->not->toContain('\\/');
});

it('is deterministic for the same input', function () {
    $encoder = new JsonDocumentEncoder();
    $doc = ['z' => 1, 'a' => 2];

    expect($encoder->encode($doc))->toBe($encoder->encode($doc));
});
```

- [ ] **Step 2: Run to see them fail**

Run: `cd packages/core && vendor/bin/pest tests/Generator/Encoding/`
Expected: FAIL — classes not found.

- [ ] **Step 3: Create the interface + two encoders**

Create `packages/core/src/Generator/Support/Encoding/DocumentEncoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support\Encoding;

interface DocumentEncoder
{
    /** @param array<string, mixed> $document normalized, format-independent */
    public function encode(array $document): string;
}
```

Create `packages/core/src/Generator/Support/Encoding/YamlDocumentEncoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support\Encoding;

use Symfony\Component\Yaml\Yaml;

final class YamlDocumentEncoder implements DocumentEncoder
{
    /** @param array<string, mixed> $document */
    public function encode(array $document): string
    {
        return rtrim(Yaml::dump($document, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)) . "\n";
    }
}
```

Create `packages/core/src/Generator/Support/Encoding/JsonDocumentEncoder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support\Encoding;

use JsonException;

final class JsonDocumentEncoder implements DocumentEncoder
{
    /** @param array<string, mixed> $document */
    public function encode(array $document): string
    {
        try {
            return json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (JsonException $e) {
            throw new \RuntimeException('Failed to encode document as JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
```

- [ ] **Step 4: Run tests + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/Encoding/`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Generator/Support/Encoding/ packages/core/tests/Generator/Encoding/
git commit -m "feat(generator): DocumentEncoder + Yaml/Json implementations"
```

---

### Task 2: `ArazzoDocumentWriter` — `ArazzoDocument` → normalized array → encoded string

**Files:**
- Create: `packages/core/src/Generator/Support/ArazzoDocumentWriter.php`
- Test: `packages/core/tests/Generator/ArazzoDocumentWriterTest.php` (unit-level here; the real round-trip-through-Parser test is Task 6)

**Interfaces:**
- Consumes: `DocumentEncoder` (Task 1), `Alama\Arazzo\Dto\*` (existing), `Alama\Arazzo\Dto\Enum\Format` (existing).
- Produces: `ArazzoDocumentWriter::write(ArazzoDocument $doc, Format $format = Format::Yaml): string`.

- [ ] **Step 1: Write failing tests**

Create `packages/core/tests/Generator/ArazzoDocumentWriterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Generator\Support\ArazzoDocumentWriter;
use Alama\Arazzo\Tests\Support\Fx;

it('writes a minimal document to YAML by default', function () {
    $step = Fx::step('getPet', 'getPet');
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf], [new SourceDescription('api', 'openapi.yaml', SourceType::Openapi)]);

    $yaml = (new ArazzoDocumentWriter())->write($doc);

    expect($yaml)->toContain('arazzo:')
        ->and($yaml)->toContain('workflowId: w')
        ->and($yaml)->toContain('stepId: getPet');
});

it('writes JSON when Format::Json is given', function () {
    $step = Fx::step('getPet', 'getPet');
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf]);

    $json = (new ArazzoDocumentWriter())->write($doc, Format::Json);
    $decoded = json_decode($json, true);

    expect($decoded['workflows'][0]['workflowId'])->toBe('w');
});

it('serializes an Expression to its raw string', function () {
    $step = Fx::step('c', 'opC', params: [
        new Parameter('p', ParameterIn::Query, new Expression('{$steps.b.outputs.x}')),
    ]);
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf]);

    $yaml = (new ArazzoDocumentWriter())->write($doc);

    expect($yaml)->toContain('{$steps.b.outputs.x}');
});

it('serializes a Selector to {selector, type, context?} matching Parser::parseValueOrSelector shape', function () {
    $step = Fx::step('c', 'opC', outputs: [
        'id' => new Selector(context: '$response.body', selector: '$.id', type: ExpressionType::JsonPath),
    ]);
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf]);

    $json = (new ArazzoDocumentWriter())->write($doc, Format::Json);
    $decoded = json_decode($json, true);
    $out = $decoded['workflows'][0]['steps'][0]['outputs']['id'];

    expect($out)->toBe(['context' => '$response.body', 'selector' => '$.id', 'type' => 'jsonpath']);
});

it('serializes a SuccessCriterion with condition + simple type', function () {
    $step = Fx::step('c', 'opC', crit: [
        new SuccessCriterion(null, '$statusCode == 201', CriterionType::Simple, null),
    ]);
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf]);

    $yaml = (new ArazzoDocumentWriter())->write($doc);

    expect($yaml)->toContain('$statusCode == 201');
});
```

Check `Fx::step()`'s exact param names before finalizing (`params:`, `crit:`, `outputs:` per the read `Fx.php` above) — adjust call sites if the file has drifted since this plan was written.

- [ ] **Step 2: Run to see them fail**

Run: `cd packages/core && vendor/bin/pest tests/Generator/ArazzoDocumentWriterTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `ArazzoDocumentWriter`**

Create `packages/core/src/Generator/Support/ArazzoDocumentWriter.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support;

use Alama\Arazzo\Dto\Action\FailureAction;
use Alama\Arazzo\Dto\Action\SuccessAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\RequestBody;
use Alama\Arazzo\Dto\Reusable;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Generator\Support\Encoding\DocumentEncoder;
use Alama\Arazzo\Generator\Support\Encoding\JsonDocumentEncoder;
use Alama\Arazzo\Generator\Support\Encoding\YamlDocumentEncoder;

final class ArazzoDocumentWriter
{
    public function __construct(
        private readonly DocumentEncoder $yamlEncoder = new YamlDocumentEncoder(),
        private readonly DocumentEncoder $jsonEncoder = new JsonDocumentEncoder(),
    ) {
    }

    public function write(ArazzoDocument $doc, Format $format = Format::Yaml): string
    {
        $normalized = $this->toArray($doc);

        return match ($format) {
            Format::Yaml => $this->yamlEncoder->encode($normalized),
            Format::Json => $this->jsonEncoder->encode($normalized),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(ArazzoDocument $doc): array
    {
        $out = [
            'arazzo' => $doc->arazzo,
            'info' => array_filter([
                'title' => $doc->info->title,
                'summary' => $doc->info->summary,
                'description' => $doc->info->description,
                'version' => $doc->info->version,
            ], static fn ($v) => $v !== null),
        ];

        if ($doc->self !== null) {
            $out['$self'] = $doc->self;
        }

        if ($doc->sourceDescriptions !== []) {
            $out['sourceDescriptions'] = array_map(
                fn (SourceDescription $s) => ['name' => $s->name, 'url' => $s->url, 'type' => $s->type->value],
                $doc->sourceDescriptions,
            );
        }

        $out['workflows'] = array_map(fn (Workflow $w) => $this->workflowToArray($w), $doc->workflows);

        return $out;
    }

    /** @return array<string, mixed> */
    private function workflowToArray(Workflow $wf): array
    {
        $out = ['workflowId' => $wf->workflowId];

        if ($wf->summary !== null) {
            $out['summary'] = $wf->summary;
        }
        if ($wf->description !== null) {
            $out['description'] = $wf->description;
        }
        if ($wf->inputs !== null && $wf->inputs !== []) {
            $out['inputs'] = $wf->inputs;
        }
        if ($wf->dependsOn !== []) {
            $out['dependsOn'] = $wf->dependsOn;
        }

        $out['steps'] = array_map(fn (Step $s) => $this->stepToArray($s), $wf->steps);

        if ($wf->successActions !== []) {
            $out['successActions'] = array_map(fn ($a) => $this->actionToArray($a), $wf->successActions);
        }
        if ($wf->failureActions !== []) {
            $out['failureActions'] = array_map(fn ($a) => $this->actionToArray($a), $wf->failureActions);
        }
        if ($wf->outputs !== []) {
            $out['outputs'] = $this->mapToArray($wf->outputs);
        }
        if ($wf->parameters !== []) {
            $out['parameters'] = array_map(fn (Parameter $p) => $this->parameterToArray($p), $wf->parameters);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function stepToArray(Step $s): array
    {
        $out = ['stepId' => $s->stepId];

        if ($s->description !== null) {
            $out['description'] = $s->description;
        }
        if ($s->operationId !== null) {
            $out['operationId'] = $s->operationId;
        }
        if ($s->operationPath !== null) {
            $out['operationPath'] = $s->operationPath;
        }
        if ($s->workflowId !== null) {
            $out['workflowId'] = $s->workflowId;
        }
        if ($s->parameters !== []) {
            $out['parameters'] = array_map(fn (Parameter $p) => $this->parameterToArray($p), $s->parameters);
        }
        if ($s->requestBody !== null) {
            $out['requestBody'] = $this->requestBodyToArray($s->requestBody);
        }
        if ($s->successCriteria !== []) {
            $out['successCriteria'] = array_map(
                fn (SuccessCriterion $c) => $this->criterionToArray($c),
                $s->successCriteria,
            );
        }
        if ($s->onSuccess !== []) {
            $out['onSuccess'] = array_map(fn ($a) => $this->actionToArray($a), $s->onSuccess);
        }
        if ($s->onFailure !== []) {
            $out['onFailure'] = array_map(fn ($a) => $this->actionToArray($a), $s->onFailure);
        }
        if ($s->outputs !== []) {
            $out['outputs'] = $this->mapToArray($s->outputs);
        }
        if ($s->dependsOn !== []) {
            $out['dependsOn'] = array_values($s->dependsOn);
        }
        if ($s->action !== null) {
            $out['action'] = $s->action;
        }
        if ($s->channelPath !== null) {
            $out['channelPath'] = $s->channelPath;
        }
        if ($s->correlationId !== null) {
            $out['correlationId'] = $s->correlationId->raw;
        }
        if ($s->strictValidation !== null) {
            $out['x-strict-validation'] = $s->strictValidation;
        }
        if ($s->idempotencyKey !== null) {
            $out['x-idempotency-key'] = $s->idempotencyKey;
        }
        if ($s->idempotencyHeader !== null) {
            $out['x-idempotency-header'] = $s->idempotencyHeader;
        }

        return $out;
    }

    private function parameterToArray(Parameter $p): array
    {
        $out = ['name' => $p->name];
        if ($p->in !== null) {
            $out['in'] = $p->in->value;
        }
        $out['value'] = $this->valueToArray($p->value);

        return $out;
    }

    private function requestBodyToArray(RequestBody $rb): array
    {
        $out = [];
        if ($rb->contentType !== null) {
            $out['contentType'] = $rb->contentType;
        }
        $out['payload'] = $this->valueToArray($rb->payload);
        if ($rb->replacements !== []) {
            $out['replacements'] = array_map(
                fn ($r) => ['target' => $r->target, 'value' => $this->valueToArray($r->value)],
                $rb->replacements,
            );
        }

        return $out;
    }

    private function criterionToArray(SuccessCriterion $c): array
    {
        $out = [];
        if ($c->context !== null) {
            $out['context'] = $c->context;
        }
        $out['condition'] = $c->condition;
        if ($c->type !== null) {
            $out['type'] = $c->version !== null
                ? ['type' => $c->type->value, 'version' => $c->version]
                : $c->type->value;
        }

        return $out;
    }

    private function actionToArray(SuccessAction|FailureAction|Reusable $a): array
    {
        if ($a instanceof Reusable) {
            $out = ['reference' => $a->reference];
            if ($a->value !== null) {
                $out['value'] = $a->value;
            }

            return $out;
        }

        // Actions carry their own shape via public readonly props; property_exists guards
        // fields that only some Action subtypes have (SuccessGotoAction vs SuccessEndAction, etc.)
        $out = ['name' => $a->name];
        $out['type'] = match (true) {
            str_ends_with($a::class, 'GotoAction') => 'goto',
            str_ends_with($a::class, 'EndAction') => 'end',
            str_ends_with($a::class, 'RetryAction') => 'retry',
            str_ends_with($a::class, 'SubWorkflowSuccessAction'), str_ends_with($a::class, 'SubWorkflowFailureAction') => 'invoke',
            default => throw new \LogicException('Unknown action subtype: ' . $a::class),
        };

        foreach (['stepId', 'workflowId', 'retryAfter', 'retryLimit', 'version'] as $prop) {
            if (property_exists($a, $prop) && $a->{$prop} !== null) {
                $out[$prop] = $a->{$prop};
            }
        }
        if (property_exists($a, 'parameters') && is_array($a->parameters) && $a->parameters !== []) {
            $out['parameters'] = $this->mapToArray($a->parameters);
        }
        if (property_exists($a, 'criteria') && is_array($a->criteria) && $a->criteria !== []) {
            $out['criteria'] = array_map(fn ($c) => $this->criterionToArray($c), $a->criteria);
        }

        return $out;
    }

    /** @param array<string, mixed> $map */
    private function mapToArray(array $map): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            $out[$k] = $this->valueToArray($v);
        }

        return $out;
    }

    private function valueToArray(mixed $v): mixed
    {
        if ($v instanceof Expression) {
            return $v->raw;
        }
        if ($v instanceof Selector) {
            $out = ['selector' => $v->selector, 'type' => $v->type->value];
            if ($v->context !== null) {
                $out = ['context' => $v->context] + $out;
            }
            if ($v->version !== null) {
                $out['version'] = $v->version;
            }

            return $out;
        }
        if (is_array($v)) {
            return array_is_list($v) ? array_map(fn ($x) => $this->valueToArray($x), $v) : $this->mapToArray($v);
        }

        return $v;
    }
}
```

Note on `actionToArray`: the design's Behavior section doesn't describe action serialization at all (v1 never emits `successActions`/`failureActions`/`onSuccess`/`onFailure` — see spec Out of Scope), so this branch is dead code for `DeterministicGenerator`'s own output today, but `ArazzoDocumentWriter` is documented shared infra (spec's Approach §2) that must handle whatever `ArazzoDocument` legitimately contains, since `ai-31`/`ai-32`/`core-35`'s `-o` flag will feed it hand-constructed or hand-edited documents that do have actions. Covered by dedicated tests below rather than left untested.

- [ ] **Step 4: Add action-serialization test coverage**

Append to `packages/core/tests/Generator/ArazzoDocumentWriterTest.php`:

```php
use Alama\Arazzo\Dto\Action\SuccessGotoAction;
use Alama\Arazzo\Dto\Action\SuccessEndAction;
use Alama\Arazzo\Dto\Reusable;

it('serializes a SuccessGotoAction', function () {
    $step = Fx::step('c', 'opC');
    $wf = Fx::wf('w', [$step]);
    $doc = Fx::doc([$wf]);
    // successActions live on Workflow via Fx::wf's default []; construct manually instead:
    $wfWithAction = new \Alama\Arazzo\Dto\Workflow(
        'w', null, null, null, [], [$step],
        [new SuccessGotoAction('goNext', 'other', null, [])], [], [], [],
    );
    $doc2 = Fx::doc([$wfWithAction]);

    $yaml = (new ArazzoDocumentWriter())->write($doc2);
    expect($yaml)->toContain('type: goto')
        ->and($yaml)->toContain('stepId: other');
});

it('serializes a Reusable reference', function () {
    $step = Fx::step('c', 'opC');
    $wfWithAction = new \Alama\Arazzo\Dto\Workflow(
        'w', null, null, null, [], [$step],
        [new Reusable('#/components/successActions/shared', null)], [], [], [],
    );
    $doc = Fx::doc([$wfWithAction]);

    $yaml = (new ArazzoDocumentWriter())->write($doc);
    expect($yaml)->toContain('reference: \'#/components/successActions/shared\'');
});
```

Check `SuccessGotoAction`'s actual constructor argument order (`name, stepId, workflowId, criteria` per the reference `Parser::parseSuccessAction` call site read earlier: `new SuccessGotoAction(name: $name, stepId: ..., workflowId: ..., criteria: ...)`) before finalizing — adjust positional args to named args if uncertain.

- [ ] **Step 5: Run tests + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/ArazzoDocumentWriterTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors. (The `match (true)` class-suffix dispatch in `actionToArray` may need a `@phpstan-ignore` if PHPStan flags the `LogicException` arm as unreachable — leave a one-line reason comment if so.)

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Generator/Support/ArazzoDocumentWriter.php packages/core/tests/Generator/ArazzoDocumentWriterTest.php
git commit -m "feat(generator): ArazzoDocumentWriter — ArazzoDocument to normalized array to YAML/JSON"
```

---

### Task 3: `OperationCollector` — enumerate/select OpenAPI operations deterministically

**Files:**
- Create: `packages/core/src/Generator/Support/OperationCollector.php`
- Create: `packages/core/src/Generator/Exceptions/GeneratorException.php` (needed now for `forIds`'s not-found case)
- Test: `packages/core/tests/Generator/OperationCollectorTest.php`
- Test: `packages/core/tests/Generator/Exceptions/GeneratorExceptionTest.php`

**Interfaces:**
- Consumes: `cebe\openapi\spec\OpenApi`, `Operation`, `PathItem`; `Alama\Arazzo\Exceptions\ArazzoException`.
- Produces:
  - `GeneratorException` — factory methods `operationNotFound(string $operationId)`, `specNotReadable(string $path)`, `emptySelection()`.
  - `OperationCollector::all(OpenApi $openApi): list<array{0: string, 1: string, 2: Operation, 3: PathItem}>` — `[method, path, Operation, PathItem]`, document order, unnamed operations skipped.
  - `OperationCollector::forIds(OpenApi $openApi, list<string> $operationIds): list<array{0: string, 1: string, 2: Operation, 3: PathItem}>` — hint order; throws `GeneratorException::operationNotFound()` per missing id.

- [ ] **Step 1: Write failing tests**

Create `packages/core/tests/Generator/Exceptions/GeneratorExceptionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator\Exceptions;

use Alama\Arazzo\Exceptions\ArazzoException;
use Alama\Arazzo\Generator\Exceptions\GeneratorException;

it('is an ArazzoException with a stable code for each factory', function () {
    expect(GeneratorException::operationNotFound('getPet'))->toBeInstanceOf(ArazzoException::class)
        ->and(GeneratorException::operationNotFound('getPet')->codeId)->toBe('generator.operation_not_found')
        ->and(GeneratorException::operationNotFound('getPet')->getMessage())->toContain('getPet')
        ->and(GeneratorException::specNotReadable('/x/y.yaml')->codeId)->toBe('generator.spec_not_readable')
        ->and(GeneratorException::emptySelection()->codeId)->toBe('generator.empty_selection');
});
```

Create `packages/core/tests/Generator/OperationCollectorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Generator\Exceptions\GeneratorException;
use Alama\Arazzo\Generator\Support\OperationCollector;
use cebe\openapi\Reader;

const FIXTURE_DIR = __DIR__ . '/../fixtures/generator/';

it('collects all named operations in document order', function () {
    $openapi = Reader::readFromYamlFile(FIXTURE_DIR . 'petstore-minimal.yaml');
    $ops = OperationCollector::all($openapi);

    $ids = array_map(fn ($tuple) => $tuple[2]->operationId, $ops);
    expect($ids)->toBe(['getPet', 'createOrder', 'payOrder']);
});

it('skips operations without an operationId', function () {
    $openapi = Reader::readFromYamlFile(FIXTURE_DIR . 'no-operation-id.yaml');
    $ops = OperationCollector::all($openapi);

    $ids = array_map(fn ($tuple) => $tuple[2]->operationId, $ops);
    expect($ids)->toBe(['named']);
});

it('selects by hint order via forIds', function () {
    $openapi = Reader::readFromYamlFile(FIXTURE_DIR . 'petstore-minimal.yaml');
    $ops = OperationCollector::forIds($openapi, ['createOrder', 'getPet']);

    $ids = array_map(fn ($tuple) => $tuple[2]->operationId, $ops);
    expect($ids)->toBe(['createOrder', 'getPet']);
});

it('throws GeneratorException when a hinted id is not found', function () {
    $openapi = Reader::readFromYamlFile(FIXTURE_DIR . 'petstore-minimal.yaml');
    OperationCollector::forIds($openapi, ['ghost']);
})->throws(GeneratorException::class);

it('returns the owning PathItem alongside each operation', function () {
    $openapi = Reader::readFromYamlFile(FIXTURE_DIR . 'petstore-path-level-params.yaml');
    $ops = OperationCollector::all($openapi);

    expect($ops[0][3])->toBeInstanceOf(\cebe\openapi\spec\PathItem::class);
});
```

- [ ] **Step 2: Create fixtures needed by this task**

Create `packages/core/tests/fixtures/generator/petstore-minimal.yaml`:

```yaml
openapi: 3.1.0
info:
  title: Petstore Minimal
  version: '1.0'
paths:
  /pets/{petId}:
    get:
      operationId: getPet
      parameters:
        - name: petId
          in: path
          required: true
          schema: { type: string }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  id: { type: string }
                  name: { type: string }
  /orders:
    post:
      operationId: createOrder
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                petId: { type: string }
                quantity: { type: integer }
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object
                properties:
                  orderId: { type: string }
                  status: { type: string }
  /orders/{orderId}/pay:
    post:
      operationId: payOrder
      parameters:
        - name: orderId
          in: path
          required: true
          schema: { type: string }
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                amount: { type: number }
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  paid: { type: boolean }
```

Create `packages/core/tests/fixtures/generator/no-operation-id.yaml`:

```yaml
openapi: 3.1.0
info: { title: No OpId, version: '1.0' }
paths:
  /named:
    get:
      operationId: named
      responses:
        '200': { description: OK }
  /unnamed:
    get:
      responses:
        '200': { description: OK }
```

Create `packages/core/tests/fixtures/generator/petstore-path-level-params.yaml`:

```yaml
openapi: 3.1.0
info: { title: Path Level Params, version: '1.0' }
paths:
  /items/{itemId}:
    parameters:
      - name: itemId
        in: path
        required: true
        schema: { type: string }
    get:
      operationId: getItem
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema: { type: object, properties: { id: { type: string } } }
    delete:
      operationId: deleteItem
      parameters:
        - name: itemId
          in: path
          required: true
          schema: { type: string, format: uuid }
      responses:
        '204': { description: No Content }
```

- [ ] **Step 3: Run to see them fail**

Run: `cd packages/core && vendor/bin/pest tests/Generator/OperationCollectorTest.php tests/Generator/Exceptions/GeneratorExceptionTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 4: Create `GeneratorException`**

Create `packages/core/src/Generator/Exceptions/GeneratorException.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Exceptions;

use Alama\Arazzo\Exceptions\ArazzoException;

final class GeneratorException extends ArazzoException
{
    public static function operationNotFound(string $operationId): self
    {
        return new self("Operation '{$operationId}' not found in OpenAPI specification.", '', 'generator.operation_not_found');
    }

    public static function specNotReadable(string $path): self
    {
        return new self("OpenAPI spec not readable: {$path}", $path, 'generator.spec_not_readable');
    }

    public static function emptySelection(): self
    {
        return new self('No operations selected — spec has zero named operations and no steps hint was given.', '', 'generator.empty_selection');
    }
}
```

- [ ] **Step 5: Create `OperationCollector`**

Create `packages/core/src/Generator/Support/OperationCollector.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support;

use Alama\Arazzo\Generator\Exceptions\GeneratorException;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;

final class OperationCollector
{
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /** @return list<array{0: string, 1: string, 2: Operation, 3: PathItem}> */
    public static function all(OpenApi $openApi): array
    {
        $out = [];
        foreach ($openApi->paths as $path => $pathItem) {
            /** @var PathItem $pathItem */
            foreach (self::METHODS as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method;
                if ($operation !== null && $operation->operationId !== null) {
                    $out[] = [strtoupper($method), (string) $path, $operation, $pathItem];
                }
            }
        }

        return $out;
    }

    /**
     * @param list<string> $operationIds
     * @return list<array{0: string, 1: string, 2: Operation, 3: PathItem}>
     */
    public static function forIds(OpenApi $openApi, array $operationIds): array
    {
        $byId = [];
        foreach (self::all($openApi) as $tuple) {
            $byId[$tuple[2]->operationId] = $tuple;
        }

        $out = [];
        foreach ($operationIds as $id) {
            if (!isset($byId[$id])) {
                throw GeneratorException::operationNotFound($id);
            }
            $out[] = $byId[$id];
        }

        return $out;
    }
}
```

- [ ] **Step 6: Run tests + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/OperationCollectorTest.php tests/Generator/Exceptions/GeneratorExceptionTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 7: Commit**

```bash
git add packages/core/src/Generator/Support/OperationCollector.php packages/core/src/Generator/Exceptions/GeneratorException.php packages/core/tests/Generator/OperationCollectorTest.php packages/core/tests/Generator/Exceptions/ packages/core/tests/fixtures/generator/
git commit -m "feat(generator): OperationCollector + GeneratorException"
```

---

### Task 4: `StepScaffolder` — one `Step` DTO per operation

**Files:**
- Create: `packages/core/src/Generator/Support/StepScaffolder.php`
- Test: `packages/core/tests/Generator/StepScaffolderTest.php`
- Modify: `packages/core/tests/fixtures/generator/no-json-response.yaml` (create)

**Interfaces:**
- Consumes: `[method, path, Operation, PathItem]` tuple (Task 3), running `priorOutputs: array<string,string>` map.
- Produces: `StepScaffolder::scaffold(array $tuple, array &$priorOutputs, array &$inputAccumulator): Step`. `$inputAccumulator` is `array<string, array{type: string}>`, mutated in place — every parameter/property name that fell back to `{$inputs.*}` is recorded here for workflow-level `inputs` assembly in Task 5.

- [ ] **Step 1: Create the remaining fixture**

Create `packages/core/tests/fixtures/generator/no-json-response.yaml`:

```yaml
openapi: 3.1.0
info: { title: No Json Response, version: '1.0' }
paths:
  /ping:
    get:
      operationId: ping
      responses:
        '200':
          description: OK
          content:
            text/plain:
              schema: { type: string }
  /delete-thing/{id}:
    delete:
      operationId: deleteThing
      parameters:
        - name: id
          in: path
          required: true
          schema: { type: string }
      responses:
        '204':
          description: No Content
```

- [ ] **Step 2: Write failing tests**

Create `packages/core/tests/Generator/StepScaffolderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Generator\Support\OperationCollector;
use Alama\Arazzo\Generator\Support\StepScaffolder;
use cebe\openapi\Reader;

const SCAFFOLD_FIXTURE_DIR = __DIR__ . '/../fixtures/generator/';

it('slugifies stepId from operationId', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['getPet'])[0];

    $priorOutputs = [];
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->stepId)->toBe('getpet');
});

it('wires a path parameter to $inputs when no prior output matches', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['getPet'])[0];

    $priorOutputs = [];
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->parameters[0]->name)->toBe('petId')
        ->and($step->parameters[0]->in)->toBe(ParameterIn::Path)
        ->and($step->parameters[0]->value->raw)->toBe('{$inputs.petId}')
        ->and($inputs)->toHaveKey('petId');
});

it('wires a parameter to a prior step output when the name matches', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['payOrder'])[0];

    $priorOutputs = ['orderId' => 'createorder']; // simulates createOrder having already run
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    $orderIdParam = collect($step->parameters)->first(fn ($p) => $p->name === 'orderId');
    // Fixture's payOrder path param is actually 'orderId' — adjust assertion to real param name.
    expect($step->parameters[0]->value->raw)->toBe('{$steps.createorder.outputs.orderId}');
});

it('builds a requestBody payload from an object schema, wiring matched names', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['createOrder'])[0];

    $priorOutputs = ['id' => 'getpet']; // getPet's output 'id' should wire into createOrder's petId? no match by name -> falls to inputs
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->requestBody->contentType)->toBe('application/json')
        ->and($step->requestBody->payload)->toHaveKeys(['petId', 'quantity'])
        ->and($step->requestBody->replacements)->toBe([]);
});

it('selects the first declared 2xx status for the success criterion', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['createOrder'])[0]; // declares 201 only
    $priorOutputs = [];
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->successCriteria[0]->condition)->toBe('$statusCode == 201');
});

it('falls back to 200 when no 2xx response is declared', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'no-json-response.yaml');
    $tuple = OperationCollector::forIds($openapi, ['deleteThing'])[0]; // declares 204 only
    $priorOutputs = [];
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->successCriteria[0]->condition)->toBe('$statusCode == 204'); // 204 IS a declared 2xx, so it wins over the 200 fallback
});

it('produces empty outputs when the success response has no JSON object schema', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'no-json-response.yaml');
    $tuple = OperationCollector::forIds($openapi, ['ping'])[0]; // text/plain, not application/json
    $priorOutputs = [];
    $inputs = [];
    $step = StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($step->outputs)->toBe([]);
});

it('emits a Selector output per top-level response property and registers it in priorOutputs', function () {
    $openapi = Reader::readFromYamlFile(SCAFFOLD_FIXTURE_DIR . 'petstore-minimal.yaml');
    $tuple = OperationCollector::forIds($openapi, ['getPet'])[0];
    $priorOutputs = [];
    $inputs = [];
    StepScaffolder::scaffold($tuple, $priorOutputs, $inputs);

    expect($priorOutputs)->toBe(['id' => 'getpet', 'name' => 'getpet']);
});
```

The `->toBeInstanceOf`/`->raw` assertions on `Expression` assume `StepScaffolder` wires parameter values as `new Expression(...)` per the design — confirm against the actual implementation written in Step 3 below and adjust test expectations if a detail (e.g. wrapping order, `$inputs` key casing) differs once written.

- [ ] **Step 3: Run to see them fail**

Run: `cd packages/core && vendor/bin/pest tests/Generator/StepScaffolderTest.php`
Expected: FAIL — class not found.

- [ ] **Step 4: Create `StepScaffolder`**

Create `packages/core/src/Generator/Support/StepScaffolder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator\Support;

use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\RequestBody;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Parameter as OaParameter;
use cebe\openapi\spec\Schema;

final class StepScaffolder
{
    /**
     * @param array{0: string, 1: string, 2: Operation, 3: PathItem} $tuple
     * @param array<string, string> $priorOutputs output name -> stepId, mutated in place
     * @param array<string, array{type: string}> $inputAccumulator mutated in place
     */
    public static function scaffold(array $tuple, array &$priorOutputs, array &$inputAccumulator): Step
    {
        [, , $operation, $pathItem] = $tuple;

        $stepId = self::slugify((string) $operation->operationId);

        $parameters = self::scaffoldParameters($operation, $pathItem, $priorOutputs, $inputAccumulator);
        $requestBody = self::scaffoldRequestBody($operation, $priorOutputs, $inputAccumulator);

        [$statusCode, $successSchema] = self::firstTwoXxWithSchema($operation);
        $criteria = [new SuccessCriterion(null, "\$statusCode == {$statusCode}", CriterionType::Simple, null)];
        $outputs = self::scaffoldOutputs($successSchema, $stepId, $priorOutputs);

        return new Step(
            stepId: $stepId,
            description: null,
            operationId: $operation->operationId,
            operationPath: null,
            workflowId: null,
            parameters: $parameters,
            requestBody: $requestBody,
            successCriteria: $criteria,
            onSuccess: [],
            onFailure: [],
            outputs: $outputs,
            dependsOn: [],
        );
    }

    private static function slugify(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9_\-]+/', '-', $s) ?? $s;
        $s = trim($s, '-');

        return $s === '' ? 'step' : $s;
    }

    /**
     * @param array<string, string> $priorOutputs
     * @param array<string, array{type: string}> $inputAccumulator
     * @return list<Parameter>
     */
    private static function scaffoldParameters(
        Operation $operation,
        PathItem $pathItem,
        array &$priorOutputs,
        array &$inputAccumulator,
    ): array {
        /** @var array<string, OaParameter> $byNameIn */
        $byNameIn = [];
        foreach ($pathItem->parameters as $p) {
            /** @var OaParameter $p */
            $byNameIn["{$p->name}|{$p->in}"] = $p;
        }
        foreach ($operation->parameters as $p) {
            /** @var OaParameter $p */
            $byNameIn["{$p->name}|{$p->in}"] = $p; // operation-level overrides path-level
        }

        $out = [];
        foreach ($byNameIn as $p) {
            $in = ParameterIn::from($p->in);
            $type = ($p->schema instanceof Schema && is_string($p->schema->type)) ? $p->schema->type : 'string';

            $out[] = new Parameter($p->name, $in, self::wireValue($p->name, $type, $priorOutputs, $inputAccumulator));
        }

        return $out;
    }

    /**
     * @param array<string, string> $priorOutputs
     * @param array<string, array{type: string}> $inputAccumulator
     */
    private static function scaffoldRequestBody(
        Operation $operation,
        array &$priorOutputs,
        array &$inputAccumulator,
    ): ?RequestBody {
        if ($operation->requestBody === null) {
            return null;
        }
        $media = $operation->requestBody->content['application/json'] ?? null;
        if ($media === null || !($media->schema instanceof Schema)) {
            return null;
        }
        $schema = $media->schema;
        if ($schema->type !== 'object' || $schema->properties === []) {
            return null;
        }

        $payload = [];
        foreach ($schema->properties as $name => $propSchema) {
            $type = ($propSchema instanceof Schema && is_string($propSchema->type)) ? $propSchema->type : 'string';
            $payload[$name] = self::wireValue($name, $type, $priorOutputs, $inputAccumulator);
        }

        return new RequestBody('application/json', $payload, []);
    }

    /**
     * @param array<string, string> $priorOutputs
     * @param array<string, array{type: string}> $inputAccumulator
     */
    private static function wireValue(string $name, string $type, array &$priorOutputs, array &$inputAccumulator): Expression
    {
        if (isset($priorOutputs[$name])) {
            return new Expression("{\$steps.{$priorOutputs[$name]}.outputs.{$name}}");
        }

        $inputAccumulator[$name] = ['type' => $type];

        return new Expression("{\$inputs.{$name}}");
    }

    /** @return array{0: int, 1: ?Schema} [statusCode, jsonObjectSchemaOrNull] */
    private static function firstTwoXxWithSchema(Operation $operation): array
    {
        $firstTwoXx = null;
        foreach ($operation->responses ?? [] as $code => $response) {
            if (is_string($code) && str_starts_with($code, '2')) {
                $firstTwoXx = ['code' => (int) $code, 'response' => $response];
                break;
            }
        }

        if ($firstTwoXx === null) {
            return [200, null];
        }

        $media = $firstTwoXx['response']->content['application/json'] ?? null;
        $schema = ($media !== null && $media->schema instanceof Schema) ? $media->schema : null;

        return [$firstTwoXx['code'], $schema];
    }

    /**
     * @param array<string, string> $priorOutputs mutated in place: adds this step's output names
     * @return array<string, Selector>
     */
    private static function scaffoldOutputs(?Schema $schema, string $stepId, array &$priorOutputs): array
    {
        if ($schema === null || $schema->type !== 'object' || $schema->properties === []) {
            return [];
        }

        $outputs = [];
        foreach (array_keys($schema->properties) as $name) {
            $outputs[$name] = new Selector('$response.body', "\$.{$name}", ExpressionType::JsonPath, null);
            $priorOutputs[$name] = $stepId;
        }

        return $outputs;
    }
}
```

Verify `cebe\openapi\spec\Operation::$responses` iteration shape (associative `code => Response`) and `Response::$content` (associative `mediaType => MediaType`) against the installed `cebe/php-openapi` version before finalizing — these are stable across 1.7.x but confirm with `composer show cebe/php-openapi` if anything in this step misbehaves.

- [ ] **Step 5: Run tests + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/StepScaffolderTest.php`
Expected: PASS. Fix any test assertions that assumed a wrong fixture detail (e.g. `payOrder`'s actual path parameter name) against the real fixture written in Task 3.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Generator/Support/StepScaffolder.php packages/core/tests/Generator/StepScaffolderTest.php packages/core/tests/fixtures/generator/no-json-response.yaml
git commit -m "feat(generator): StepScaffolder — one Step DTO per OpenAPI operation"
```

---

### Task 5: `DeterministicGenerator` — the public entry point

**Files:**
- Create: `packages/core/src/Generator/DeterministicGenerator.php`
- Test: `packages/core/tests/Generator/DeterministicGeneratorTest.php`

**Interfaces:**
- Consumes: `OperationCollector`, `StepScaffolder`, `ArazzoDocumentWriter` (Tasks 1-4), `GeneratorException`.
- Produces:
  - `DeterministicGenerator::fromSpec(string $path, array $hints = []): ArazzoDocument`.
  - `DeterministicGenerator::fromSpecToString(string $path, array $hints = [], Format $format = Format::Yaml): string`.

- [ ] **Step 1: Write failing tests**

Create `packages/core/tests/Generator/DeterministicGeneratorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Generator\DeterministicGenerator;
use Alama\Arazzo\Generator\Exceptions\GeneratorException;

const GEN_FIXTURE_DIR = __DIR__ . '/../fixtures/generator/';

it('generates a single-step document for one hinted operation', function () {
    $doc = (new DeterministicGenerator())->fromSpec(GEN_FIXTURE_DIR . 'petstore-minimal.yaml', [
        'steps' => ['getPet'],
    ]);

    expect($doc->arazzo)->toBe('1.1.0')
        ->and($doc->workflows)->toHaveCount(1)
        ->and($doc->workflows[0]->workflowId)->toBe('getpet')
        ->and($doc->workflows[0]->steps)->toHaveCount(1);
});

it('generates all named operations in document order when no steps hint is given', function () {
    $doc = (new DeterministicGenerator())->fromSpec(GEN_FIXTURE_DIR . 'petstore-minimal.yaml');

    $stepIds = array_map(fn ($s) => $s->stepId, $doc->workflows[0]->steps);
    expect($stepIds)->toBe(['getpet', 'createorder', 'payorder'])
        ->and($doc->workflows[0]->workflowId)->toBe('generated-workflow');
});

it('respects title/version/workflowId/specVersion hints', function () {
    $doc = (new DeterministicGenerator())->fromSpec(GEN_FIXTURE_DIR . 'petstore-minimal.yaml', [
        'workflowId' => 'my-flow',
        'title' => 'My Flow',
        'version' => '2.0.0',
        'specVersion' => '1.0.0',
    ]);

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->workflows[0]->workflowId)->toBe('my-flow')
        ->and($doc->info->title)->toBe('My Flow')
        ->and($doc->info->version)->toBe('2.0.0');
});

it('accumulates workflow-level inputs from every input-wired field', function () {
    $doc = (new DeterministicGenerator())->fromSpec(GEN_FIXTURE_DIR . 'petstore-minimal.yaml', [
        'steps' => ['getPet'],
    ]);

    expect($doc->workflows[0]->inputs)->toHaveKey('petId');
});

it('throws when a steps hint names an unknown operation', function () {
    (new DeterministicGenerator())->fromSpec(GEN_FIXTURE_DIR . 'petstore-minimal.yaml', ['steps' => ['ghost']]);
})->throws(GeneratorException::class);

it('fromSpecToString defaults to YAML', function () {
    $yaml = (new DeterministicGenerator())->fromSpecToString(GEN_FIXTURE_DIR . 'petstore-minimal.yaml', ['steps' => ['getPet']]);

    expect($yaml)->toContain('arazzo:');
});

it('fromSpecToString honors an explicit Format::Json', function () {
    $json = (new DeterministicGenerator())->fromSpecToString(
        GEN_FIXTURE_DIR . 'petstore-minimal.yaml',
        ['steps' => ['getPet']],
        Format::Json,
    );

    expect(json_decode($json, true))->toHaveKey('arazzo');
});
```

- [ ] **Step 2: Run to see them fail**

Run: `cd packages/core && vendor/bin/pest tests/Generator/DeterministicGeneratorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `DeterministicGenerator`**

Create `packages/core/src/Generator/DeterministicGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Generator;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Generator\Exceptions\GeneratorException;
use Alama\Arazzo\Generator\Support\ArazzoDocumentWriter;
use Alama\Arazzo\Generator\Support\OperationCollector;
use Alama\Arazzo\Generator\Support\StepScaffolder;
use cebe\openapi\Reader;
use Throwable;

final class DeterministicGenerator
{
    public function __construct(private readonly ArazzoDocumentWriter $writer = new ArazzoDocumentWriter())
    {
    }

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
    public function fromSpec(string $path, array $hints = []): ArazzoDocument
    {
        if (!is_file($path) || !is_readable($path)) {
            throw GeneratorException::specNotReadable($path);
        }

        $content = (string) file_get_contents($path);
        $isYaml = !str_starts_with(trim($content), '{');

        try {
            $openapi = $isYaml ? Reader::readFromYaml($content) : Reader::readFromJson($content);
        } catch (Throwable $e) {
            throw GeneratorException::specNotReadable($path);
        }

        $tuples = isset($hints['steps'])
            ? OperationCollector::forIds($openapi, $hints['steps'])
            : OperationCollector::all($openapi);

        if ($tuples === []) {
            throw GeneratorException::emptySelection();
        }

        $priorOutputs = [];
        $inputAccumulator = [];
        $steps = [];
        foreach ($tuples as $tuple) {
            $steps[] = StepScaffolder::scaffold($tuple, $priorOutputs, $inputAccumulator);
        }

        $workflowId = $hints['workflowId']
            ?? (count($tuples) === 1 ? $steps[0]->stepId : 'generated-workflow');

        $inputsSchema = $inputAccumulator === [] ? null : [
            'type' => 'object',
            'properties' => array_map(static fn ($t) => ['type' => $t['type']], $inputAccumulator),
        ];

        $workflow = new Workflow(
            workflowId: $workflowId,
            summary: null,
            description: null,
            inputs: $inputsSchema,
            dependsOn: [],
            steps: $steps,
            successActions: [],
            failureActions: [],
            outputs: [],
            parameters: [],
        );

        return new ArazzoDocument(
            arazzo: $hints['specVersion'] ?? '1.1.0',
            info: new Info(
                title: $hints['title'] ?? "Generated: {$workflowId}",
                summary: null,
                description: null,
                version: $hints['version'] ?? '1.0.0',
            ),
            sourceDescriptions: [
                new SourceDescription($hints['sourceName'] ?? 'api', $path, SourceType::Openapi),
            ],
            workflows: [$workflow],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );
    }

    /**
     * @param array{
     *   workflowId?: string, title?: string, version?: string,
     *   sourceName?: string, specVersion?: '1.0.0'|'1.1.0', steps?: list<string>,
     * } $hints
     */
    public function fromSpecToString(string $path, array $hints = [], Format $format = Format::Yaml): string
    {
        return $this->writer->write($this->fromSpec($path, $hints), $format);
    }
}
```

- [ ] **Step 4: Run tests + full suite + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/`
Expected: PASS. Fix any wiring mismatches against Task 4's actual `StepScaffolder` output.
Run: `vendor/bin/pest`
Expected: full package suite green (no regressions in unrelated tests).
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Generator/DeterministicGenerator.php packages/core/tests/Generator/DeterministicGeneratorTest.php
git commit -m "feat(generator): DeterministicGenerator — public entry point, fromSpec/fromSpecToString"
```

---

### Task 6: Round-trip regression + determinism — the acceptance-proving tests

**Files:**
- Test: `packages/core/tests/Generator/RoundTripValidationTest.php`
- Test: `packages/core/tests/Generator/DeterminismTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1-5, plus `Loader`, `Parser`, `Validator`, `RuleSet` (existing), and the finding from Task 0.

- [ ] **Step 1: Apply Task 0's finding**

Using the `RuleSet` construction pattern found in Task 0, write a small local helper at the top of `RoundTripValidationTest.php` that builds a `RuleSet` containing every shipped rule (or the minimum structural set named in Task 0 Step 1 if a full list isn't readily assembled). Name it `fullRuleSet(): RuleSet`.

- [ ] **Step 2: Write the round-trip regression test**

Create `packages/core/tests/Generator/RoundTripValidationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Generator\DeterministicGenerator;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Validation\RuleSet;
use Alama\Arazzo\Validation\Validator;
// import every Rules\* class per Task 0's finding, or the constructed RuleSet helper

const RT_FIXTURE_DIR = __DIR__ . '/../fixtures/generator/';

function fullRuleSet(): RuleSet
{
    // Filled in per Task 0's finding — placeholder shape:
    // return new RuleSet([new StepIdPatternRule(), new StepUniqueIdRule(), ...]);
    throw new \RuntimeException('Populate from Task 0 finding before running this test.');
}

dataset('generator fixtures', [
    'petstore-minimal (all ops)' => ['petstore-minimal.yaml', []],
    'petstore-minimal (single hinted op)' => ['petstore-minimal.yaml', ['steps' => ['createOrder']]],
    'petstore-path-level-params' => ['petstore-path-level-params.yaml', []],
    'no-json-response' => ['no-json-response.yaml', []],
]);

it('generated YAML round-trips through Loader/Parser/Validator with zero errors', function (string $fixture, array $hints) {
    $yaml = (new DeterministicGenerator())->fromSpecToString(RT_FIXTURE_DIR . $fixture, $hints, Format::Yaml);

    $raw = new RawDocument(\Symfony\Component\Yaml\Yaml::parse($yaml), 'generated.yaml', \Alama\Arazzo\Dto\Enum\Format::Yaml);
    $doc = (new Parser())->parse($raw);
    $result = (new Validator(fullRuleSet()))->validate($doc);

    expect($result->errors())->toBe([]);
})->with('generator fixtures');

it('generated JSON round-trips through Loader/Parser/Validator with zero errors', function (string $fixture, array $hints) {
    $json = (new DeterministicGenerator())->fromSpecToString(RT_FIXTURE_DIR . $fixture, $hints, Format::Json);

    $raw = new RawDocument(json_decode($json, true), 'generated.json', \Alama\Arazzo\Dto\Enum\Format::Json);
    $doc = (new Parser())->parse($raw);
    $result = (new Validator(fullRuleSet()))->validate($doc);

    expect($result->errors())->toBe([]);
})->with('generator fixtures');
```

Check the actual `ValidationResult` accessor name (`->errors()` per `Validator::validate()`'s return type read earlier — `new ValidationResult($doc, $collector->errors(), $collector->warnings())`) before finalizing.

- [ ] **Step 3: Write the determinism test**

Create `packages/core/tests/Generator/DeterminismTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Generator;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Generator\DeterministicGenerator;

const DET_FIXTURE_DIR = __DIR__ . '/../fixtures/generator/';

it('produces byte-identical YAML across fresh generator instances', function () {
    $a = (new DeterministicGenerator())->fromSpecToString(DET_FIXTURE_DIR . 'petstore-minimal.yaml');
    $b = (new DeterministicGenerator())->fromSpecToString(DET_FIXTURE_DIR . 'petstore-minimal.yaml');

    expect($a)->toBe($b);
});

it('produces byte-identical JSON across fresh generator instances', function () {
    $a = (new DeterministicGenerator())->fromSpecToString(DET_FIXTURE_DIR . 'petstore-minimal.yaml', [], Format::Json);
    $b = (new DeterministicGenerator())->fromSpecToString(DET_FIXTURE_DIR . 'petstore-minimal.yaml', [], Format::Json);

    expect($a)->toBe($b);
});
```

- [ ] **Step 4: Run all new tests + full suite + PHPStan**

Run: `cd packages/core && vendor/bin/pest tests/Generator/`
Expected: PASS. This is the step most likely to surface a real mismatch between `StepScaffolder`'s output and what `Parser`/the validator rules actually accept (e.g. a `Selector` field the parser rejects, an `inputs` JSON-Schema shape a rule flags) — debug against the actual `ValidationResult::errors()` output rather than guessing; each error carries a `code` + `path` (per `Error.php`) that pinpoints exactly what's wrong.
Run: `vendor/bin/pest`
Expected: full package suite green.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no new errors.

- [ ] **Step 5: Commit**

```bash
git add packages/core/tests/Generator/RoundTripValidationTest.php packages/core/tests/Generator/DeterminismTest.php
git commit -m "test(generator): round-trip validation + determinism acceptance tests"
```

---

### Task 7: CHANGELOG + Ship

**Files:**
- Modify: `CHANGELOG.md`
- Deleted via `ship-plan.sh`: `docs/superpowers/roadmap/backend/phase-0-ai/ai-30-openapi-deterministic-gen.md`
- Moved via `ship-plan.sh`: this plan + `docs/superpowers/specs/2026-08-03-ai-30-openapi-deterministic-gen-design.md` → `shipped/`

- [ ] **Step 1: Add CHANGELOG entries**

Modify `CHANGELOG.md`. Under `## Unreleased`:

```markdown
### Added

- `Alama\Arazzo\Generator\DeterministicGenerator` — no-LLM OpenAPI 3.x → Arazzo scaffold generator. `fromSpec()` / `fromSpecToString()`. Wires parameters/request-body fields to prior step outputs by name, defaults a success criterion from the operation's first declared 2xx response, extracts top-level response schema fields as outputs. Deterministic: no network calls, no LLM.
- `Generator\Support\OperationCollector`, `Generator\Support\StepScaffolder` — generator internals.
- `Alama\Arazzo\Generator\Support\ArazzoDocumentWriter` + `Encoding\DocumentEncoder` (`YamlDocumentEncoder` default, `JsonDocumentEncoder`) — the first DTO-to-document serialization path in the codebase; `Loader`/`Parser` remain document-to-DTO only, this is their mirror image. Format selected via the existing `Dto\Enum\Format` enum.
- `Generator\Exceptions\GeneratorException`.

No breaking changes — entirely additive.
```

- [ ] **Step 2: Commit CHANGELOG**

```bash
git add CHANGELOG.md
git commit -m "docs(changelog): DeterministicGenerator + ArazzoDocumentWriter landed"
```

- [ ] **Step 3: Run pre-push gate one last time**

Run: `cd packages/core && vendor/bin/pint --test && vendor/bin/phpstan analyse --no-progress && vendor/bin/pest`
Expected: all clean/green.

- [ ] **Step 4: Ship the plan**

Run: `scripts/ship-plan.sh ai-30-openapi-deterministic-gen`
Expected: plan + spec move to `shipped/`, roadmap stub deleted, `## Unreleased` → `### Shipped` bullet appended in `CHANGELOG.md`.

- [ ] **Step 5: Verify final state**

Run: `git status` — expected clean working tree.
Run: `git log --oneline -10` — expected Task 1-7 commits + ship commit visible.

- [ ] **Step 6: Push branch + open PR**

(User decides when to push. Do not push automatically.)

---

## Self-Review

**Spec coverage:**

- Approach §1 (`DeterministicGenerator`) and §2 (`ArazzoDocumentWriter` + encoders, format-agnostic) — Tasks 1, 2, 5.
- "Why DTO-first" rationale — realized by Task 5 building a real `ArazzoDocument`, proven by Task 6's round-trip through `Parser`.
- "No explicit dependsOn" — `StepScaffolder` never sets `dependsOn` (Task 4); relies on shipped `DependencyGraph` at execution time (out of scope for this plan, already shipped).
- Architecture file list — every file in the spec's Architecture section has a corresponding Task 1-5 creation step, using the confirmed `Alama\Arazzo\*` / `packages/core/src/...` paths.
- API — `DeterministicGenerator::fromSpec/fromSpecToString`, `OperationCollector::all/forIds`, `ArazzoDocumentWriter::write` (+ `toArray` made public for testability, not in the original spec API block — reasonable addition, doesn't change the public contract), `DocumentEncoder::encode` — all implemented per spec signatures.
- Behavior §1-5 — Task 5 Step 3 (`fromSpec`'s load/select/assemble), Task 4 (per-operation scaffold), Task 2 (write/serialize) — traced 1:1.
- CLI Integration section — explicitly out of scope for this plan (belongs to `core-35`), not touched.
- Testing section — fixtures (Tasks 3-4), suites (Tasks 1-6) all present; round-trip + determinism tests match spec exactly (Task 6).
- Migration + CHANGELOG — Task 7.
- Acceptance §1-4 — #1 and #3 proven by Task 6's own tests; #2 (mock-server E2E) explicitly deferred — see Gaps below; #4 proven by construction (no `AiClientInterface` import anywhere in the new files).
- Out of Scope section — respected: no semantic ordering, no type-directed wiring beyond the loose type-presence check, no multi-workflow, no non-JSON bodies, no partial payload patching, no unnamed-op handling, no workflow-level action inference.

**Placeholder scan:** searched for TBD / TODO / FIXME / "similar to Task N" / "implement later". Found:
- Task 0 is an intentional recon task whose output is consumed by Task 6 Step 1 — not a placeholder, a sequencing dependency (round-trip test can't be written correctly without knowing how a full `RuleSet` is assembled elsewhere; the reference plans this document follows the style of resolve this kind of thing via a recon step rather than guessing).
- Task 6 Step 2's `fullRuleSet()` stub throws until Task 0's finding is applied — this is the *intended shape* of the handoff from Task 0, not an unresolved gap.
- No other TBDs.

**Type consistency:**

- `[method, path, Operation, PathItem]` 4-tuple shape — consistent across Tasks 3, 4 (design spec's own tuple only had 3 elements; this plan adds `PathItem` as element 3, needed for the path-level-parameter merge the spec's Behavior §3 requires — flagged and resolved during Task 3, not silently diverged).
- `priorOutputs: array<string,string>` and `inputAccumulator: array<string,array{type:string}>` — passed by reference consistently through Task 4 → Task 5.
- `GeneratorException` factory names (`operationNotFound`, `specNotReadable`, `emptySelection`) — consistent across Tasks 3, 5.
- `ArazzoDocumentWriter::write(doc, format)` / `toArray(doc)` — consistent across Tasks 2, 5, 6.

**Gaps found + closed:**

- Spec's 3-element tuple vs this plan's 4-element tuple (adds `PathItem`): documented above — the spec didn't account for path-level parameter merging needing the owning `PathItem`, this plan closes that gap.
- Spec Acceptance #2 (mock-server E2E execution test) is **not** included as a task here — it requires `WorkflowExecutor` + a mock HTTP client harness that doesn't obviously exist yet in `packages/core/tests` (the spec itself flagged "exact harness TBD at plan time"). Recommend a follow-up micro-plan once Task 6 confirms the generator's output is valid, rather than blocking this plan on discovering/building that harness now.
- `fullRuleSet()` in Task 6 depends on Task 0's recon finding, which cannot be resolved until this plan actually executes against the live repo (the pattern may have moved since this plan was written). Flagged explicitly rather than guessed.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-05-ai-30-openapi-deterministic-gen.md`. Two execution options:

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks.

**2. Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
