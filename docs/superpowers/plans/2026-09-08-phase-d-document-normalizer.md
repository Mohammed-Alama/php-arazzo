# Phase D — Document: normalizer port + registry

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> You are free to commit directly to this branch (`main`) while landing this plan. Do not use worktrees or the apptree harness — the repo layout is the one you are working in.
>
> This plan assumes that **Phase A (contracts ports)**, **Phase B (expression grammar)**, **Phase C (expression split)**, and **#20 (JSON Schema validation)** have already landed. If any of those are not yet on `main`, stop and flag it before executing the first task.
>
> The plan is the boss. If you can find a way to execute a task exactly as written, do it that way. Only deviate where the code forces you to, and note the deviation in the commit message. Follow the exact task order — do not reorder tasks.

## Goal

Port the OpenAPI normalization pipeline and the per-protocol step-validation rules for the 1.2 step variants so that source normalization is reachable through a pluggable `SourceNormalizerInterface` registry, and `ResolvedOperation` becomes the two-axis (source type + RPC protocol) value that later phases (F) relay on. Privileges for the normalizers during D; **F1 relocates** the OpenAPI normalizer classes, `OpenApiDocumentLoader`, `OpenApiOperationResolver`, `ResolvedOperation`, and `NormalizedOpenApiOperation` to `alama/arazzo-protocol-http`. The registry, parsing primitives, `Validator`, and `RuleSet` stay in `document`.

## Architecture

```
SourceNormalizerInterface (contracts, Phase A)
        ▲
        │ implements
OpenApiSourceNormalizer (document/src/Normalizer — D2)
        │ composes
        ├─ OpenApiDocumentLoader
        ├─ OpenApiVersionDetector
        ├─ OpenApi30Normalizer
        └─ OpenApi31Normalizer

SourceNormalizerRegistryInterface (contracts, Phase A)
        ▲
SourceNormalizerRegistry (document/src/Resolver — D1)
        └─ get(SourceType): registers → OpenApiSourceNormalizer (D2)

ResolvedOperation (document/src/Normalizer — D3c)
        ├─ sourceType(): SourceType        (derived from $source->type)
        ├─ binding(): string              (derived: http/soap/grpc/... )
        └─ + rpcProtocol, operationName, rpcMethod, graphqlOperation, interaction

Parser (document/src/Parser — D4a) ──populates──▶ Step 1.2 fields + Components.interactions

Rules (document/src/Validator/Rules — D4b..f)
  └─ registered in RuleSet::default
```

## Tech Stack

- **PHP 8.4** with strict types, readonly classes (contracts), plain classes with promoted readonly props (`ResolvedOperation`).
- **Pest 5** tests, one test file per class. Tests live in `packages/{pkg}/tests`. The shared test helper `Alama\Arazzo\Tests\Support\Fx` (in `packages/core/tests/Support/Fx.php`) is autoloaded across all packages.
- **PHPStan** analysis per package: `composer run analyse-document`, `composer run analyse-contracts`.
- **cebe/openapi** for the loaded `OpenApi`/`Operation` models (versions 3.0/3.1 share the cebe object model).
- **pint** for formatting (`composer run format`).
- Tests run from repo root: `composer run test-document`, `composer run test-contracts`. Final gate: `make verify`.

## Spec

Master spec: [`2026-09-08-plugin-stack-oms-multiprotocol-design.md`](../specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md) — Phase D section (rows D1–D11), decision table (D2, D3, D9), sequencing (`#23` superseded, `#20` before D), Public API impact, Risks.

Research: [`2026-09-08-arazzo-protocol-spec-prs-impact.md`](../../research/2026-09-08-arazzo-protocol-spec-prs-impact.md) — PR #533 (SOAP/WSDL), #556 (RPC), #567 (GraphQL), #568 (interaction steps).

## Global constraints

1. **Only one deliverable**: this plan file's execution. Do not change the parent spec; do not run `git push`. Each task commits its own work with a descriptive message.
2. **No placeholders.** Every class, test, method, command, and error code below is real, was verified against the current tree, and must exist verbatim by the end of its task.
3. **`SourceNormalizerInterface` signature is frozen** (Phase A): `normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array`. `OpenApiSourceNormalizer::normalize()` returns an **operation index** whose *values* are `ResolvedOperation` objects (D3c) — this is a `mixed` subtype of `array<string, mixed>` and is the documented reconciliation of the two-axis model with the contracts signature. F1 relocates `ResolvedOperation` with the normalizer, so the FQCNs stay coherent.
4. **BC across dependencies is preserved within the phase**: `ResolvedOperation`'s constructor only grows *trailing defaulted* parameters (runner tests construct it with 5 positional args); the internal per-version normalizer method `OpenApiNormalizerInterface::normalize(array, string, string)` is **not** renamed or re-typed; `DocumentInterface` is **not** extended (new entry points go on the concrete `Document` facade only).
5. **Deviations from earlier phases are documented**, they are: `Step::$graphqlOperation` revised from `?string` (Phase A7) to `?GraphQlOperation` (D3b); `Components` gains `interactions` (D4a).

## Sequencing

Execution order (each task lists its prereqs explicitly):

| # | Task | Prereqs |
|---|------|---------|
| D1 | `SourceNormalizerRegistry` | A3 landed |
| D3a | `SourceType` cases `Wsdl`, `Protobuf`, `Graphql` | contracts |
| D3b | contracts model: `GraphQlOperation`, `InteractionMode`, `Interaction` extension, `Step::$graphqlOperation` | A7 landed |
| D3c | two-axis `ResolvedOperation` | D3b |
| D2 | `OpenApiSourceNormalizer` | D1, D3c |
| D4a | parser: 1.2 step fields + `components.interactions` | D3b, A7 |
| D4b | `StepOperationTargetPresentRule` six-target mutual exclusion | D3b |
| D4c | `WsdlStepRule` | D4b |
| D4d | `RpcStepRule` | D4b |
| D4e | `GraphQlStepRule` | D4b |
| D4f | `InteractionStepRule` | D4b |
| D5 | Wire registry into `Document`, final gate | all of the above |

`D3a` and `D3b` can be interleaved freely; `D4b` must land before `D4c..f` because each new rule reuses the extended mutual-exclusion step, and the six-target rule needs the D3b Step fields.

---

## Task D1 — `SourceNormalizerRegistry`

**Prereqs:** Phase A landed (`SourceNormalizerInterface` + `SourceNormalizerRegistryInterface` exist in `packages/contracts/src/Interfaces`). If they do not exist on `main`, stop and flag that Phase A has not landed.

**Files:**
- Create `packages/document/src/Resolver/SourceNormalizerRegistry.php`
- Create `packages/document/tests/Resolver/SourceNormalizerRegistryTest.php`

**Interfaces:**
```
Consumes: SourceNormalizerInterface (name(), priority(), supports(SourceType), normalize(...) : array)
Produces: SourceNormalizerRegistryInterface
```

### Steps

1. Write the failing registry test.

`packages/document/tests/Resolver/SourceNormalizerRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Resolver\SourceNormalizerRegistry;

final class FakeNormalizer implements SourceNormalizerInterface
{
    /** @param list<SourceType> $types */
    public function __construct(
        private readonly string $fakeName,
        private readonly int $fakePriority,
        private readonly array $types,
    ) {}

    public function name(): string
    {
        return $this->fakeName;
    }

    public function priority(): int
    {
        return $this->fakePriority;
    }

    public function supports(SourceType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        return [];
    }
}

it('returns null when no normalizer supports the requested source type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $registry->register($openapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBeNull();
});

it('returns the first registered normalizer that supports the requested type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $asyncapi = new FakeNormalizer('asyncapi', 0, [SourceType::Asyncapi]);
    $registry->register($openapi);
    $registry->register($asyncapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBe($asyncapi);
});

it('prefers the highest-priority normalizer, then registration order for ties', function (): void {
    $registry = new SourceNormalizerRegistry();
    $lenient = new FakeNormalizer('openapi-lenient', 0, [SourceType::Openapi]);
    $strict = new FakeNormalizer('openapi-strict', 100, [SourceType::Openapi]);
    $registry->register($lenient);
    $registry->register($strict);

    expect($registry->get(SourceType::Openapi))->toBe($strict);

    $registry2 = new SourceNormalizerRegistry();
    $first = new FakeNormalizer('first', 0, [SourceType::Openapi]);
    $second = new FakeNormalizer('second', 0, [SourceType::Openapi]);
    $registry2->register($first);
    $registry2->register($second);

    expect($registry2->get(SourceType::Openapi))->toBe($first);
});
```

2. Run it — it must fail to load (`SourceNormalizerRegistry` not found):

```bash
composer run test-document -- --filter=SourceNormalizerRegistry
```

3. Implement the registry.

`packages/document/src/Resolver/SourceNormalizerRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SourceNormalizerRegistry implements SourceNormalizerRegistryInterface
{
    /** @var list<SourceNormalizerInterface> */
    private array $normalizers = [];

    public function register(SourceNormalizerInterface $normalizer): void
    {
        $this->normalizers[] = $normalizer;
    }

    public function get(SourceType $type): ?SourceNormalizerInterface
    {
        foreach ($this->ordered() as $normalizer) {
            if ($normalizer->supports($type)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * Highest priority first; stable so registration order breaks ties.
     *
     * @return list<SourceNormalizerInterface>
     */
    private function ordered(): array
    {
        $normalizers = $this->normalizers;
        usort(
            $normalizers,
            static fn (SourceNormalizerInterface $a, SourceNormalizerInterface $b): int => $b->priority() <=> $a->priority(),
        );

        return $normalizers;
    }
}
```

4. Re-run the test — it passes:

```bash
composer run test-document -- --filter=SourceNormalizerRegistry
composer run analyse-document
```

5. Commit:

```bash
git add packages/document/src/Resolver/SourceNormalizerRegistry.php packages/document/tests/Resolver/SourceNormalizerRegistryTest.php
git commit -m "feat(document): add SourceNormalizerRegistry (D1)"
```

---

## Task D3a — `SourceType` gains `Wsdl`, `Protobuf`, `Graphql`

**Prereqs:** contracts package present. Verified today that no code exhaustively `match`es `SourceType` — the only uses are `===`/`!==` comparisons, so adding cases is safe.

**Files:**
- Edit `packages/contracts/src/Spec/Enum/SourceType.php`
- Edit `packages/document/src/Parser/Parser.php` (`parseSourceDescription` enum error message only)
- Create `packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php`

### Steps

1. Write the failing enum test.

`packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

it('exposes the six standard source types with their wire values', function (): void {
    expect(array_column(SourceType::cases(), 'value'))->toBe([
        'openapi',
        'arazzo',
        'asyncapi',
        'wsdl',
        'protobuf',
        'graphql',
    ]);
});

it('backs each protocol reference source type with its enum value', function (): void {
    expect(SourceType::Wsdl->value)->toBe('wsdl');
    expect(SourceType::Protobuf->value)->toBe('protobuf');
    expect(SourceType::Graphql->value)->toBe('graphql');
});
```

2. Run it — fails (no `Wsdl` case).

```bash
composer run test-contracts -- --filter=SourceTypeTest
```

3. Extend the enum.

`packages/contracts/src/Spec/Enum/SourceType.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
    case Wsdl = 'wsdl';
    case Protobuf = 'protobuf';
    case Graphql = 'graphql';
}
```

4. Fix the now-stale enum error message in the parser (was already out of sync — it says `openapi|arazzo` while `asyncapi` also parses). In `packages/document/src/Parser/Parser.php`, `parseSourceDescription`:

```php
        $enum = SourceType::tryFrom($type)
            ?? throw ParserException::invalidEnum(
                $ctx->push('type'),
                'openapi|arazzo|asyncapi|wsdl|protobuf|graphql',
                $type,
            );
```

5. Re-run tests, add a parser round-trip assertion for a wsdl source description inside `packages/document/tests/Parser/ParserTest.php`:

```php
it('parses wsdl source descriptions', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions:
      - name: legacy
        type: wsdl
        url: ./service.wsdl
    workflows:
      - workflowId: w
        steps: []
    YAML;

    $document = (new Parser())->parse(new RawDocument(
        (new SymfonyYamlDecoder())->decode($yaml),
        'memory://wsdl',
        Format::Yaml,
    ));

    expect($document->sourceDescriptions[0]->type)->toBe(SourceType::Wsdl);
});
```

Verify the new test's imports are present (`SourceType`, `Format`, `RawDocument`, `SymfonyYamlDecoder`, `Parser` — all already imported in `ParserTest.php`).

6. Gates + commit:

```bash
composer run test-document -- --filter=ParserTest
composer run test-contracts
composer run analyse-contracts
composer run analyse-document
git add -A packages/contracts/src/Spec/Enum/SourceType.php packages/contracts/tests/Contracts/Spec/Enum/SourceTypeTest.php packages/document/src/Parser/Parser.php packages/document/tests/Parser/ParserTest.php
git commit -m "feat(contracts): extend SourceType with wsdl/protobuf/graphql (D3a)"
```

---

## Task D3b — contracts model for 1.2 step variants

**Prereqs:** Phase A landed (the decomposed model already carries these on the axes: `StepTarget` has `operationId`, `operationPath`, `workflowId`, `action`, `channelPath`, `correlationId`, `operationName`, `rpcMethod: ?RpcProtocol`, `graphqlOperation: ?string`, `interaction: ?Interaction`; `StepFlow` has `onTimeout`, `onCancel`, `timeoutDuration`; `Interaction` has `expectedPayload` + `timeout`; `RpcProtocol` enum exists with `grpc`/`grpc-web`/`twirp`/`connect`).

**This task revises one Phase A field.** `StepTarget::$graphqlOperation` is changed from `?string` to `?GraphQlOperation`. Rationale (documented deviation, kept in this commit message): PR #567 defines a GraphQL operation *object* (`schema`, `operation`, `extensions`, `extensionsSelector`); the D4 rule requires those sub-fields and cannot operate on a plain string. The A7-shipped string form is a simplification that D must revise before any rule can enforce the proposal.

**Files:**
- Create `packages/contracts/src/Spec/GraphQlOperation.php`
- Create `packages/contracts/src/Spec/Enum/InteractionMode.php`
- Edit `packages/contracts/src/Spec/Interaction.php` (append authoring fields)
- Edit `packages/contracts/src/Spec/StepTarget.php` (`graphqlOperation` type + `StepTarget::graphql()` factory signature)
- Edit `packages/contracts/src/Spec/StepFactory.php` (`graphql` factory signature)
- Edit `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php` (graphql test uses GraphQlOperation)
- Create `packages/contracts/tests/Contracts/Spec/GraphQlOperationTest.php`
- Create `packages/contracts/tests/Contracts/Spec/Enum/InteractionModeTest.php`
- Edit `packages/contracts/tests/Contracts/Spec/InteractionTest.php` (extend for #568 fields)

### Steps

1. Write the model tests first.

`packages/contracts/tests/Contracts/Spec/GraphQlOperationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;

it('models a GraphQL operation object', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensions: ['contentType' => 'application/json'],
    );

    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
    expect($op->extensionsSelector)->toBeNull();
});

it('accepts an extensionsSelector form', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/operations/0', ExpressionType::JsonPointer),
        extensions: null,
    );

    expect($op->extensionsSelector?->type)->toBe(ExpressionType::JsonPointer);
});
```

`packages/contracts/tests/Contracts/Spec/Enum/InteractionModeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;

it('exposes the three interaction modes', function (): void {
    expect(InteractionMode::Form->value)->toBe('form');
    expect(InteractionMode::Redirect->value)->toBe('redirect');
    expect(InteractionMode::Acknowledge->value)->toBe('acknowledge');
});
```

Extend `packages/contracts/tests/Contracts/Spec/InteractionTest.php` (append to the existing file):

```php
it('carries the interaction-step authoring fields', function (): void {
    $interaction = new Interaction(
        expectedPayload: ['ok'],
        timeout: '5s',
        prompt: 'Confirm the transfer',
        context: ['account' => '1234'],
        inputSchema: ['type' => 'object'],
        mode: InteractionMode::Form,
        redirectOperationId: 'confirm',
        parameters: [new Parameter('token', 'in')],
    );

    expect($interaction->prompt)->toBe('Confirm the transfer');
    expect($interaction->context)->toBe(['account' => '1234']);
    expect($interaction->inputSchema)->toBe(['type' => 'object']);
    expect($interaction->mode)->toBe(InteractionMode::Form);
    expect($interaction->redirectOperationId)->toBe('confirm');
    expect($interaction->parameters)->toHaveCount(1);
});
```

2. Run — fails to compile. Implement the model.

`packages/contracts/src/Spec/GraphQlOperation.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * The graphql-operation object (PR #567): a GraphQL step targets a whole
 * source and names an operation, optionally with extension headers.
 */
final readonly class GraphQlOperation
{
    /**
     * @param  array<string,mixed>|null  $extensions
     */
    public function __construct(
        public ?string $schema = null,
        public ?string $operation = null,
        public ?array $extensions = null,
        public ?Selector $extensionsSelector = null,
    ) {}
}
```

`packages/contracts/src/Spec/Enum/InteractionMode.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum InteractionMode: string
{
    case Form = 'form';
    case Redirect = 'redirect';
    case Acknowledge = 'acknowledge';
}
```

`packages/contracts/src/Spec/Interaction.php` (append after `timeout`; **keep** `expectedPayload` and `timeout` for BC with A7):

```php
final readonly class Interaction
{
    /**
     * @param  array<string,mixed>|null  $context
     * @param  array<string,mixed>|null  $inputSchema
     * @param  list<Parameter|Reusable>  $parameters
     */
    public function __construct(
        public mixed $expectedPayload = null,
        public ?string $timeout = null,
        public ?string $prompt = null,
        public ?array $context = null,
        public ?array $inputSchema = null,
        public ?InteractionMode $mode = null,
        public ?string $redirectOperationId = null,
        public ?string $redirectOperationPath = null,
        public ?string $redirectUrl = null,
        public array $parameters = [],
    ) {}
}
```

`packages/contracts/src/Spec/StepTarget.php` — change the trailing `graphqlOperation` field from `?string` to `?GraphQlOperation` and update the `StepTarget::graphql()` factory:

```php
        public ?GraphQlOperation $graphqlOperation = null,
```

and update `StepTarget::graphql()`:
```php
    public static function graphql(GraphQlOperation $graphqlOperation): self
    {
        return new self(graphqlOperation: $graphqlOperation);
    }
```

Also update `StepFactory::graphql()` to accept `GraphQlOperation`:
```php
    public static function graphql(string $stepId, ?string $description, StepFlow $flow, StepIo $io, GraphQlOperation $graphqlOperation): Step
    {
        return new Step($stepId, $description, StepTarget::graphql($graphqlOperation), $flow, $io);
    }
```

3. Update the Phase A StepFactory test that used the string form. In `packages/contracts/tests/Contracts/Spec/StepFactoryTest.php`, replace the `graphqlOperation: 'query Load'` argument with:

```php
            graphqlOperation: new GraphQlOperation(operation: 'query Load'),
```

and update the assertion to:

```php
    expect($step->target->graphqlOperation)->toBeInstanceOf(GraphQlOperation::class);
```

Add `use Alama\Arazzo\Contracts\Spec\GraphQlOperation;` to the test imports.

4. Run the gates + commit:

```bash
composer run test-contracts
composer run analyse-contracts
composer run test-document
git add -A packages/contracts/src/Spec packages/contracts/tests/Contracts/Spec
git commit -m "feat(contracts): model GraphQL operations and interaction-step fields (D3b)"
```

---

## Task D3c — two-axis `ResolvedOperation`

**Prereqs:** D3b landed (`GraphQlOperation` exists; `Interaction` extended).

**Files:**
- Edit `packages/document/src/Normalizer/ResolvedOperation.php`
- Create `packages/document/tests/Normalizer/ResolvedOperationTest.php`

### Steps

1. Write the failing test.

`packages/document/tests/Normalizer/ResolvedOperationTest.php`. Reuse the minimal OpenAPI fixture built inline (mirrors `OpenApiOperationResolverVersionTest`):

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

function makeResolvedOperation(?RpcProtocol $rpcProtocol = null, ?string $operationName = null, ?string $rpcMethod = null, ?string $graphqlOperation = null, ?Interaction $interaction = null): ResolvedOperation
{
    $source = new SourceDescription('api', '/u', SourceType::Wsdl);
    $normalized = new NormalizedOpenApiOperation(
        path: '/pets',
        method: 'get',
        resolvedServerUrl: 'https://example.test',
        pathParameters: [],
        queryParameters: [],
        headerParameters: [],
        cookieParameters: [],
        requestBodies: [],
        responses: [],
    );

    return new ResolvedOperation(
        source: $source,
        normalized: $normalized,
        openApi: new OpenApi([]),
        rawDocument: [],
        cebeOperation: new Operation([]),
        rpcProtocol: $rpcProtocol,
        operationName: $operationName,
        rpcMethod: $rpcMethod,
        graphqlOperation: $graphqlOperation,
        interaction: $interaction,
    );
}

it('keeps constructing with positional 5-arity for runner BC', function (): void {
    $source = new SourceDescription('api', '/u', SourceType::Openapi);
    $op = new ResolvedOperation(
        $source,
        new NormalizedOpenApiOperation('/', 'get', 'https://example.test', [], [], [], [], [], []),
        new OpenApi([]),
        [],
        new Operation([]),
    );

    expect($op->sourceType())->toBe(SourceType::Openapi);
    expect($op->binding())->toBe('http');
    expect($op->rpcProtocol)->toBeNull();
});

it('derives sourceType from the source description', function (): void {
    expect(makeResolvedOperation()->sourceType())->toBe(SourceType::Wsdl);
});

it('derives binding from rpcProtocol, then source type', function (): void {
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Grpc)->binding())->toBe('grpc');
    expect(makeResolvedOperation()->binding())->toBe('soap');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::GrpcWeb)->binding())->toBe('grpc-web');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Twirp)->binding())->toBe('twirp');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Connect)->binding())->toBe('connect');
});

it('carries the protocol-specific operation reference fields', function (): void {
    $op = makeResolvedOperation(
        rpcProtocol: RpcProtocol::Grpc,
        operationName: 'com.acme.PetService/GetPet',
        rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
        graphqlOperation: '$sourceDescriptions.gql.query GetPet',
        interaction: new Interaction(prompt: 'Proceed'),
    );

    expect($op->operationName)->toBe('com.acme.PetService/GetPet');
    expect($op->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($op->graphqlOperation)->toBe('$sourceDescriptions.gql.query GetPet');
    expect($op->interaction?->prompt)->toBe('Proceed');
});
```

2. Run — must fail to compile (`rpcProtocol` etc. do not exist).

```bash
composer run test-document -- --filter=ResolvedOperation
```

3. Implement.

`packages/document/src/Normalizer/ResolvedOperation.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

/**
 * A fully resolved single-operation view over one described source.
 *
 * Two-axis: sourceType() (openapi/arazzo/asyncapi/wsdl/protobuf/graphql) x
 * optional rpcProtocol() (grpc/grpc-web/twirp/connect). binding() is derived.
 *
 * Relocated to alama/arazzo-protocol-http in F1.
 */
class ResolvedOperation
{
    /**
     * @param  array<string, mixed>  $rawDocument
     */
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
        public readonly OpenApi $openApi,
        public readonly array $rawDocument,
        public readonly Operation $cebeOperation,
        public readonly ?RpcProtocol $rpcProtocol = null,
        public readonly ?string $operationName = null,
        public readonly ?string $rpcMethod = null,
        public readonly ?string $graphqlOperation = null,
        public readonly ?Interaction $interaction = null,
    ) {}

    public function sourceType(): SourceType
    {
        return $this->source->type;
    }

    public function binding(): string
    {
        return $this->rpcProtocol?->value ?? match ($this->source->type) {
            SourceType::Openapi => 'http',
            SourceType::Arazzo => 'arazzo',
            SourceType::Asyncapi => 'asyncapi',
            SourceType::Wsdl => 'soap',
            SourceType::Protobuf => 'protobuf',
            SourceType::Graphql => 'graphql',
        };
    }
}
```

4. Re-run, run the runner suite as a BC check (these construct with 5 positional args), then commit.

```bash
composer run test-document -- --filter=ResolvedOperation
composer run test-runner
composer run analyse-document
git add packages/document/src/Normalizer/ResolvedOperation.php packages/document/tests/Normalizer/ResolvedOperationTest.php
git commit -m "feat(document): two-axis ResolvedOperation with derived binding (D3c)"
```

---

## Task D2 — `OpenApiSourceNormalizer` (port implementation)

**Prereqs:** D1 (registry), D3c (`ResolvedOperation` two-axis). The zero-arg per-version normalizers, `OpenApiDocumentLoader`, and `OpenApiVersionDetector` are used as-is; the internal `OpenApiNormalizerInterface` is **not** changed.

**Files:**
- Create `packages/document/src/Normalizer/OpenApiSourceNormalizer.php`
- Create `packages/document/tests/Normalizer/OpenApiSourceNormalizerTest.php`

### Steps

1. Write the failing test (mirrors the `LocalFetcher` + temp-file pattern from `OpenApiOperationResolverVersionTest`).

`packages/document/tests/Normalizer/OpenApiSourceNormalizerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Document\Resolver\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Document\Resolver\Fetchers\LocalFetcher;

function makeOpenApiSourceNormalizer(): OpenApiSourceNormalizer
{
    $resolver = new DefaultSourceResolver(fetchers: ['file' => new LocalFetcher()]);

    return new OpenApiSourceNormalizer(
        new OpenApiDocumentLoader($resolver),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );
}

it('does not advertise support for non-openapi sources', function (): void {
    $normalizer = makeOpenApiSourceNormalizer();

    expect($normalizer->name())->toBe('openapi');
    expect($normalizer->priority())->toBe(0);
    expect($normalizer->supports(SourceType::Openapi))->toBeTrue();
    expect($normalizer->supports(SourceType::Asyncapi))->toBeFalse();
    expect($normalizer->supports(SourceType::Wsdl))->toBeFalse();
});

it('builds an operation index keyed by qualified name and json pointer', function (): void {
    $openapiJson = json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => [
            '/pets' => [
                'get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['operationId' => 'createPet', 'responses' => ['201' => ['description' => 'Created']]],
            ],
        ],
    ]);

    $file = tempnam(sys_get_temp_dir(), 'oas_').'.json';
    file_put_contents($file, $openapiJson);

    try {
        $source = new SourceDescription('pets-api', $file, SourceType::Openapi);
        $index = makeOpenApiSourceNormalizer()->normalize($source, (string) $openapiJson);

        $qualified = $index['$sourceDescriptions.pets-api.listPets'] ?? null;
        expect($qualified)->toBeInstanceOf(ResolvedOperation::class);
        expect($qualified->sourceType())->toBe(SourceType::Openapi);
        expect($qualified->binding())->toBe('http');
        expect($qualified->normalized->method)->toBe('get');

        $pointer = $index['#/paths/pets/post'] ?? null;
        expect($pointer)->toBeInstanceOf(ResolvedOperation::class);
        expect($pointer->normalized->method)->toBe('post');
        expect($index['createPet'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});

it('rejects Swagger 2.0 content instead of mis-routing it', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'swagger_').'.json';
    file_put_contents($file, json_encode(['swagger' => '2.0', 'info' => ['title' => 'L', 'version' => '1'], 'paths' => []]));

    try {
        $source = new SourceDescription('legacy', $file, SourceType::Openapi);
        makeOpenApiSourceNormalizer()->normalize($source, (string) file_get_contents($file));
    } finally {
        @unlink($file);
    }
})->throws(UnsupportedSourceVersionException::class, 'declares version \'2.0\', which is not supported');
```

2. Run — must fail to load.

```bash
composer run test-document -- --filter=OpenApiSourceNormalizer
```

3. Implement the composed normalizer. It needs the sniffing helpers already used by `DefaultSourceResolver`; reuse `SymfonyYamlDecoder`/`NativeJsonDecoder` for decoding:

`packages/document/src/Normalizer/OpenApiSourceNormalizer.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Resolver\Exceptions\UnsupportedSourceVersionException;

/**
 * Port (D2): turns a described source + raw content into an operation index.
 *
 * Index keys mirror OpenApiOperationResolver's accepted references:
 *   - plain operationId
 *   - "$sourceDescriptions.<name>.<operationId>"
 *   - "#/paths/<escaped-path>/<method>"
 *
 * Values are two-axis ResolvedOperation objects (mixed-compatible with the
 * contracts array signature). Privileged in `document` during D; relocated
 * to alama/arazzo-protocol-http in F1.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OpenApiSourceNormalizer implements SourceNormalizerInterface
{
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(
        private readonly OpenApiDocumentLoader $loader,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly OpenApi30Normalizer $normalizer30,
        private readonly OpenApi31Normalizer $normalizer31,
    ) {}

    public function name(): string
    {
        return 'openapi';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Openapi;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        $decoded = $this->decode($rawContent);
        $version = $this->versionDetector->detect($decoded);
        if ($version !== '3.0' && $version !== '3.1') {
            throw UnsupportedSourceVersionException::forVersion($version, $source->name);
        }

        $openApi = $this->loader->load($source, getcwd() ?: '');
        if ($openApi === null) {
            return [];
        }

        $normalizer = $version === '3.1' ? $this->normalizer31 : $this->normalizer30;
        $index = [];
        foreach ($openApi->paths as $path => $pathItem) {
            foreach (self::METHODS as $method) {
                if (!isset($pathItem->{$method}) || !$pathItem->{$method} instanceof \cebe\openapi\spec\Operation) {
                    continue;
                }

                $operation = $pathItem->{$method};
                $resolved = new ResolvedOperation(
                    source: $source,
                    normalized: $normalizer->normalize($decoded, (string) $path, $method),
                    openApi: $openApi,
                    rawDocument: $decoded,
                    cebeOperation: $operation,
                );

                if ($operation->operationId !== null) {
                    $index[$operation->operationId] = $resolved;
                    $index['$sourceDescriptions.'.$source->name.'.'.$operation->operationId] = $resolved;
                }
                $pointer = '#/paths/'.str_replace(['~', '/'], ['~0', '~1'], (string) $path).'/'.$method;
                $index[$pointer] = $resolved;
            }
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $rawContent): array
    {
        $trimmed = ltrim($rawContent);
        $decoded = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? (new NativeJsonDecoder())->decode($rawContent)
            : (new SymfonyYamlDecoder())->decode($rawContent);

        if (!is_array($decoded)) {
            throw UnsupportedSourceVersionException::forVersion('unknown', '');
        }

        return $decoded;
    }
}
```

Adjust imports at the top when implementing (`NativeJsonDecoder`, `SymfonyYamlDecoder` come from `Alama\Arazzo\Document\Parser\Decoders`). If `OpenApi30Normalizer::normalize` requires arguments that differ, keep whatever the current internal interface declares — the call above already matches the verified `normalize(array $document, string $path, string $method): NormalizedOpenApiOperation`.

4. Run + gates + commit:

```bash
composer run test-document -- --filter=OpenApiSourceNormalizer
composer run analyse-document
git add packages/document/src/Normalizer/OpenApiSourceNormalizer.php packages/document/tests/Normalizer/OpenApiSourceNormalizerTest.php
git commit -m "feat(document): OpenApiSourceNormalizer port implementing SourceNormalizerInterface (D2)"
```

---

## Task D4a — parser: 1.2 step fields + `components.interactions`

**Prereqs:** D3b landed; Phase A landed (Step already has the appended fields; `parseStep` in `Parser.php` currently ends its `new Step(...)` at `timeout:`).

**Files:**
- Edit `packages/document/src/Parser/Parser.php` (`parseStep`, new `parseGraphQlOperation` + `parseInteraction` helpers, `parseComponents` interactions map)
- Edit `packages/contracts/src/Spec/Components.php` (append `interactions` array)
- Edit `packages/core/tests/Support/Fx.php` (`Fx::step` gains the new named params; `Fx::doc` keeps working)
- Create `packages/document/tests/Parser/ProtocolStepFieldsTest.php`

### Steps

1. Write the failing parser test.

`packages/document/tests/Parser/ProtocolStepFieldsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Parser;

$decode = function (string $yaml): RawDocument {
    return new RawDocument((new SymfonyYamlDecoder())->decode($yaml), 'memory://protocol', Format::Yaml);
};

it('parses wsdl-step operationName', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: soap, type: wsdl, url: ./service.wsdl }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            operationName: GetPet
    YAML));

    expect($document->workflows[0]->steps[0]->target->operationName)->toBe('GetPet');
    expect($document->workflows[0]->steps[0]->target->operationId)->toBeNull();
});

it('parses rpc-step rpcMethod and rpcProtocol', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: proto, type: protobuf, url: ./svc.proto }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            rpcMethod: $sourceDescriptions.proto.com.acme.PetService/GetPet
            rpcProtocol: grpc
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($step->target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('parses graphql-step graphqlOperation object', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: gql, type: graphql, url: ./schema.graphql }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            graphqlOperation:
              schema: $sourceDescriptions.gql
              operation: "query GetToken { token }"
              extensions:
                contentType: application/json
    YAML));

    $op = $document->workflows[0]->steps[0]->target->graphqlOperation;
    expect($op)->toBeInstanceOf(GraphQlOperation::class);
    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
});

it('parses interaction-step interaction and components.interactions', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions: []
    components:
      interactions:
        confirmation:
          prompt: Confirm
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            interaction:
              prompt: Confirm transfer
              context: { account: "1234" }
              inputSchema: { type: object }
              mode: redirect
              redirect:
                operationId: confirm
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->interaction)->not->toBeNull();
    expect($step->target->interaction->prompt)->toBe('Confirm transfer');
    expect($step->target->interaction->mode)->toBe(InteractionMode::Redirect);
    expect($step->target->interaction->redirectOperationId)->toBe('confirm');
    expect($step->target->interaction->inputSchema)->toBe(['type' => 'object']);
    expect($document->components->interactions)->toHaveKey('confirmation');
    expect($document->components->interactions['confirmation']->prompt)->toBe('Confirm');
});
```

2. Run — fails (Step has no `operationName` parse; `components` has no `interactions`).

```bash
composer run test-document -- --filter=ProtocolStepFieldsTest
```

3. Contracts first: `packages/contracts/src/Spec/Components.php` gains the interactions map (append):

```php
    /**
     * @param  array<string, mixed>   $inputs
     * @param  array<string, Parameter> $parameters
     * @param  array<string, SuccessAction> $successActions
     * @param  array<string, FailureAction> $failureActions
     * @param  array<string, Interaction>    $interactions
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $successActions,
        public array $failureActions,
        public array $interactions = [],
    ) {}
```

4. Parser edits. Add the four D-only reads to `parseStep` (after the `$idempotencyHeader`/`timeout` reads, before `return new Step(...)`):

```php
        $operationName = $this->optionalString($obj, 'operationName', $ctx);
        $rpcMethod = $this->optionalString($obj, 'rpcMethod', $ctx);

        $rpcProtocol = null;
        $rawRpcProtocol = $this->optionalString($obj, 'rpcProtocol', $ctx);
        if ($rawRpcProtocol !== null) {
            $rpcProtocol = RpcProtocol::tryFrom($rawRpcProtocol)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('rpcProtocol'),
                    'grpc|grpc-web|twirp|connect',
                    $rawRpcProtocol,
                );
        }

        $graphqlOperation = null;
        if (array_key_exists('graphqlOperation', $obj) && $obj['graphqlOperation'] !== null) {
            $graphqlOperation = $this->parseGraphQlOperation($obj['graphqlOperation'], $ctx->push('graphqlOperation'));
        }

        $interaction = null;
        if (array_key_exists('interaction', $obj) && $obj['interaction'] !== null) {
            $interaction = $this->parseInteraction($obj['interaction'], $ctx->push('interaction'));
        }
```

Do **not** touch `onTimeout`/`onCancel`/`timeoutDuration` — those are Phase A's parser concern and land with A7.

Extend the `$target` match from A7 Step 28 (which already handles `workflow`/`async`/`http`/bare) with the four protocol variants, appending after the http arm and before `default`:

```php
            $rpcMethod !== null && $rpcProtocol !== null => StepTarget::rpc($rpcMethod, $rpcProtocol),
            $graphqlOperation !== null => StepTarget::graphql($graphqlOperation),
            $operationName !== null => StepTarget::wsdl($operationName),
            $interaction !== null => StepTarget::interaction($interaction),
            default => new StepTarget(),
```

`$graphqlOperation` here is the `GraphQlOperation` object from `parseGraphQlOperation` (matching the D3b-revised `StepTarget::$graphqlOperation` type); `$rpcMethod`/`$operationName`/`$interaction` flow through `StepTarget::rpc`/`::wsdl`/`::interaction` unchanged. The existing flow/io construction (`new StepFlow(...)`, `new StepIo(...)`) stays untouched from A7.

Add the two helpers near the other `parse*` object helpers (after `parseFailureAction`). `Selector` and `ExpressionType` are already imported by `Parser` (the outputs parser at ~line 420 builds `Selector` inline the same way) — reuse that pattern rather than adding a new helper:

```php
    protected function parseGraphQlOperation(mixed $node, ParseContext $ctx): GraphQlOperation
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $extensionsSelector = null;
        if (array_key_exists('extensionsSelector', $obj) && $obj['extensionsSelector'] !== null) {
            $rawSelector = $obj['extensionsSelector'];
            $selCtx = $ctx->push('extensionsSelector');
            $typeStr = $this->requireString($rawSelector, 'type', $selCtx);
            $type = ExpressionType::tryFrom($typeStr)
                ?? throw ParserException::invalidEnum(
                    $selCtx->push('type'), 'simple|regex|jsonpath|xpath', $typeStr,
                );
            $extensionsSelector = new Selector(
                context: $this->optionalString($rawSelector, 'context', $selCtx),
                selector: $this->requireString($rawSelector, 'selector', $selCtx),
                type: $type,
                version: $this->optionalString($rawSelector, 'version', $selCtx),
            );
        }

        return new GraphQlOperation(
            schema: $this->optionalString($obj, 'schema', $ctx),
            operation: $this->optionalString($obj, 'operation', $ctx),
            extensions: $this->optionalArray($obj, 'extensions', $ctx),
            extensionsSelector: $extensionsSelector,
        );
    }

    protected function parseInteraction(mixed $node, ParseContext $ctx): Interaction
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $mode = null;
        if (($rawMode = $this->optionalString($obj, 'mode', $ctx)) !== null) {
            $mode = InteractionMode::tryFrom($rawMode)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('mode'), 'form|redirect|acknowledge', $rawMode,
                );
        }

        $redirect = null;
        if (array_key_exists('redirect', $obj) && $obj['redirect'] !== null) {
            $redirect = $this->requireObjectMap($obj['redirect'], $ctx->push('redirect'));
        }

        $parameters = [];
        if (($p = $this->optionalList($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        return new Interaction(
            expectedPayload: $obj['expectedPayload'] ?? null,
            timeout: $this->optionalString($obj, 'timeout', $ctx),
            prompt: $this->optionalString($obj, 'prompt', $ctx),
            context: $this->optionalArray($obj, 'context', $ctx),
            inputSchema: $this->optionalArray($obj, 'inputSchema', $ctx),
            mode: $mode,
            redirectOperationId: is_array($redirect) ? $this->optionalString($redirect, 'operationId', $ctx->push('redirect')) : null,
            redirectOperationPath: is_array($redirect) ? $this->optionalString($redirect, 'operationPath', $ctx->push('redirect')) : null,
            redirectUrl: is_array($redirect) ? $this->optionalString($redirect, 'url', $ctx->push('redirect')) : null,
            parameters: $parameters,
        );
    }
```

Update `parseComponents` to read the map and pass it to `Components`:

```php
        $interactions = [];
        if (($i = $this->optionalArray($obj, 'interactions', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                $parsed = $this->parseInteraction($v, $ctx->push('interactions')->push((string) $k));
                if ($parsed instanceof Reusable) {
                    throw ParserException::wrongType(
                        $ctx->push('interactions')->push((string) $k),
                        'interaction (not a reusable ref)', $v,
                    );
                }
                $interactions[(string) $k] = $parsed;
            }
        }

        return new Components($inputs, $parameters, $successActions, $failureActions, $interactions);
```

and the null-node guard above it becomes `return new Components([], [], [], [], []);`.

5. Extend `Fx::step` (`packages/core/tests/Support/Fx.php`) so rule tests can build 1.2 steps without raw Step constructors:

```php
    public static function step(
        string $id = 's',
        ?string $opId = 'op',
        ?string $opPath = null,
        ?string $wfId = null,
        array $params = [],
        ?RequestBody $body = null,
        array $crit = [],
        array $onSuccess = [],
        array $onFailure = [],
        array $outputs = [],
        ?string $operationName = null,
        ?string $rpcMethod = null,
        ?RpcProtocol $rpcProtocol = null,
        ?GraphQlOperation $graphqlOperation = null,
        ?Interaction $interaction = null,
    ): Step {
        $flow = new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure);
        $io = new StepIo(parameters: $params, requestBody: $body, successCriteria: $crit, outputs: $outputs);

        return match (true) {
            $wfId !== null => StepFactory::workflow($id, null, $flow, $io, $wfId),
            $opId !== null || $opPath !== null => StepFactory::http($id, null, $flow, $io, operationId: $opId, operationPath: $opPath),
            $rpcMethod !== null && $rpcProtocol !== null => StepFactory::rpc($id, null, $flow, $io, $rpcMethod, $rpcProtocol),
            $graphqlOperation !== null => StepFactory::graphql($id, null, $flow, $io, $graphqlOperation),
            $operationName !== null => StepFactory::wsdl($id, null, $flow, $io, $operationName),
            $interaction !== null => StepFactory::interaction($id, null, $flow, $io, $interaction),
            default => new Step($id, null, new StepTarget(), $flow, $io),
        };
    }
```

6. Run + gates + commit:

```bash
composer run test-document -- --filter=ProtocolStepFieldsTest
composer run test-document
composer run analyse-document
composer run analyse-contracts
git add -A
git commit -m "feat(document): parse 1.2 step variants and components.interactions (D4a)"
```

If the parser's `new Step(...)` currently lacks `x-arazzo-timeout-duration`/`operationName` etc. (i.e., Phase A has not actually landed), stop and fix the precondition first.

---

## Task D4b — six-target mutual exclusion in `StepOperationTargetPresentRule`

**Prereqs:** D3b landed.

**Files:**
- Edit `packages/document/src/Validator/Rules/StepOperationTargetPresentRule.php`
- Create `packages/document/tests/Validator/Rules/StepOperationTargetPresentSixTargetTest.php`

### Steps

1. Write the failing test (mirror the `StepOperationIdSourceScopedRuleTest` shape using `Fx`):

`packages/document/tests/Validator/Rules/StepOperationTargetPresentSixTargetTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts each of the six targets individually', function (): void {
    $doc = Fx::wf('w', [
        Fx::step('a', 'op'),
        Fx::step('b', null, '#/paths/pets/get'),
        Fx::step('c', null, null, 'other'),
        Fx::step('d', null, null, null, operationName: 'GetPet'),
        Fx::step('e', null, null, null, rpcMethod: '$sourceDescriptions.proto.M/Get', rpcProtocol: RpcProtocol::Grpc),
        Fx::step('f', null, null, null, graphqlOperation: new GraphQlOperation(operation: 'query Q')),
        Fx::step('g', null, null, null, interaction: new Interaction(prompt: 'Go')),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('flags steps that set an http target alongside a protocol target', function (): void {
    $doc = Fx::wf('w', [
        Fx::step('a', 'op', null, null, operationName: 'GetPet'),
        Fx::step('b', 'op', null, null, rpcMethod: '$sourceDescriptions.proto.M/Get'),
        Fx::step('c', null, null, 'other', interaction: new Interaction(prompt: 'Go')),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(3);
    expect($ec->errors()[0]->code)->toBe('step.operation_target_present');
});

it('still flags steps with no target at all', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('ghost')])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});
```

2. Run — first test fails (the six-target rule does not exist in `StepOperationTargetPresentRule`'s logic).

```bash
composer run test-document -- --filter=StepOperationTargetPresentSixTargetTest
```

3. Implement. Replace the `check` in `StepOperationTargetPresentRule` so the exactly-one scan covers all six target fields, keeping the existing message and path style:

```php
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $fields = [
            'operationName',
            'rpcMethod',
            'interaction',
            'operationId',
            'operationPath',
            'workflowId',
        ];
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $set = [];
                foreach ($fields as $field) {
                    if ($s->target->{$field} !== null) {
                        $set[] = $field;
                    }
                }
                if (count($set) !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId, "
                        .'operationName, rpcMethod, interaction (got '.count($set).').',
                        "/workflows/{$i}/steps/{$j}",
                    );
                }
            }
        }
    }
```

Keep the existing `code()` method and `@internal` docblock untouched; only `check` changes.

4. Run + commit:

```bash
composer run test-document -- --filter=StepOperationTargetPresent
composer run analyse-document
git add -A packages/document/src/Validator/Rules/StepOperationTargetPresentRule.php packages/document/tests/Validator/Rules
git commit -m "feat(document): six-target mutual exclusion for steps (D4b)"
```

---

## Task D4c — `WsdlStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/WsdlStepRule.php`
- Create `packages/document/tests/Validator/Rules/WsdlStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.wsdl_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/WsdlStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WsdlStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a wsdl step that names its operation on a wsdl source', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects operationPath on a wsdl step', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', 'op', '#/paths/pets/get', null, operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('requires a wsdl source when operationName is set', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('http', '/api', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('ignores steps without wsdl target fields', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});
```

2. Run — must fail (rule not found).

```bash
composer run test-document -- --filter=WsdlStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/WsdlStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * wsdl-step rules (PR #533):
 *  - an operationName may only be combined with WSDL sources
 *  - operationPath is prohibited on WSDL steps (prose-only per spec; the
 *    meta-schema currently permits it → characterized as a known false positive)
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlStepRule implements Rule
{
    public function code(): string
    {
        return 'step.wsdl_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $hasWsdlSource = false;
        foreach ($doc->sourceDescriptions as $source) {
            if ($source->type === SourceType::Wsdl) {
                $hasWsdlSource = true;
                break;
            }
        }

        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->operationName === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if (!$hasWsdlSource) {
                    $errors->error(
                        'step.wsdl_step',
                        "Step '{$s->stepId}' declares operationName but the document has no wsdl-type sourceDescription.",
                        $path,
                    );
                }

                if ($s->target->operationPath !== null) {
                    $errors->error(
                        'step.wsdl_step',
                        "Step '{$s->stepId}' is a WSDL step and MUST NOT use operationPath.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default` — add the import `use Alama\Arazzo\Document\Validator\Rules\WsdlStepRule;` and append `new WsdlStepRule(),` to the rules list (alphabetical position: after `WorkflowUniqueIdRule`).

5. Existing gate: run the full document suite (the rule only fires when new fields are present, so no existing fixture breaks).

```bash
composer run test-document -- --filter=WsdlStepRule
composer run analyse-document
git add -A packages/document/src/Validator/Rules/WsdlStepRule.php packages/document/tests/Validator/Rules/WsdlStepRuleTest.php packages/document/src/Validator/RuleSet.php
git commit -m "feat(document): wsdl-step validation rule (D4c)"
```

---

## Task D4d — `RpcStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/RpcStepRule.php`
- Create `packages/document/tests/Validator/Rules/RpcStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.rpc_step`.

**Protocol content-type constraints (from PR #556):**

| protocol | allowed request content types |
|----------|------------------------------|
| grpc | `application/grpc` |
| grpc-web | `application/grpc-web+proto`, `application/grpc-web-text` |
| twirp | `application/protobuf`, `application/json` |
| connect | `application/json`, `application/proto`, `application/connect+json`, `application/connect+proto` |

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/RpcStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\RpcStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts qualified rpcMethod with matching protocol and content type', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g', rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet', rpcProtocol: RpcProtocol::Grpc),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects malformed rpcMethod references', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', rpcMethod: 'GetPet', rpcProtocol: RpcProtocol::Grpc),
        Fx::step('b', rpcMethod: '$sourceDescriptions.missing.pkg/M', rpcProtocol: RpcProtocol::Grpc),
    ])]);
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('rejects cross-protocol request content types', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g',
                rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
                rpcProtocol: RpcProtocol::Twirp,
                body: new RequestBody('application/grpc', []),
            ),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.rpc_step');
});
```

(`Fx::step` gains the `body:` named parameter? It already has `$body` — pass `body:`.)

2. Run — fails.

```bash
composer run test-document -- --filter=RpcStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/RpcStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * rpc-step rules (PR #556):
 *  - rpcMethod must be source-qualified: $sourceDescriptions.<name>.<service>/<method>
 *  - the named source must exist in the document
 *  - rpcProtocol must be set whenever rpcMethod is set
 *  - request content-type must be allowed for the declared protocol
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcStepRule implements Rule
{
    private const RPC_METHOD_PATTERN = '/^\$sourceDescriptions\.[A-Za-z0-9_-]+(?:\.[A-Za-z_][A-Za-z0-9_]*)+\\/[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<string, list<string>> */
    private const CONTENT_TYPES = [
        'grpc' => ['application/grpc'],
        'grpc-web' => ['application/grpc-web+proto', 'application/grpc-web-text'],
        'twirp' => ['application/protobuf', 'application/json'],
        'connect' => ['application/json', 'application/proto', 'application/connect+json', 'application/connect+proto'],
    ];

    public function code(): string
    {
        return 'step.rpc_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->target->rpcMethod === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if (preg_match(self::RPC_METHOD_PATTERN, $s->target->rpcMethod) !== 1) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' rpcMethod must be source-qualified as "
                        .'$sourceDescriptions.<name>.<service>/<method>.',
                        $path,
                    );

                    continue;
                }

                $sourceName = explode('.', $s->target->rpcMethod)[1];
                if ($s->target->rpcProtocol === null) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod requires rpcProtocol to be set.", $path);
                } elseif (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error('step.rpc_step', "Step '{$s->stepId}' rpcMethod references unknown source '{$sourceName}'.", $path);
                } elseif (($type = $s->io->requestBody?->contentType) !== null
                    && !in_array($type, self::CONTENT_TYPES[$s->target->rpcProtocol->value], true)) {
                    $errors->error(
                        'step.rpc_step',
                        "Step '{$s->stepId}' content type '{$type}' is not allowed for {$s->target->rpcProtocol->value} RPC.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default` (import + `new RpcStepRule(),` after `WorkflowUniqueIdRule`).

5. Run + gates + commit:

```bash
composer run test-document -- --filter=RpcStepRule
composer run analyse-document
git add -A
git commit -m "feat(document): rpc-step validation rule (D4d)"
```

---

## Task D4e — `GraphQlStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/GraphQlStepRule.php`
- Create `packages/document/tests/Validator/Rules/GraphQlStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.graphql_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/GraphQlStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\GraphQlStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a whole-source schema with an operation', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step(
            's',
            graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: 'query GetPet'),
        )])],
        sources: [new SourceDescription('gql', '/schema.graphql', SourceType::Graphql)],
    );
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects a graphqlOperation without an operation', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql'),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.graphql_step');
});

it('rejects a partial (operation-path) schema reference', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql.url#/operations/0'),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('rejects extensions together with extensionsSelector', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        graphqlOperation: new GraphQlOperation(
            schema: '$sourceDescriptions.gql',
            operation: 'query GetPet',
            extensions: ['contentType' => 'application/json'],
            extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/extensions', ExpressionType::JsonPointer),
        ),
    )])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('ignores non-graphql steps', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new GraphQlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});
```

2. Run — fails.

```bash
composer run test-document -- --filter=GraphQlStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/GraphQlStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * graphql-step rules (PR #567):
 *  - schema is a whole-source reference ($sourceDescriptions.<name>)
 *  - operation is required
 *  - extensions and extensionsSelector are mutually exclusive
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class GraphQlStepRule implements Rule
{
    public function code(): string
    {
        return 'step.graphql_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $operation = $s->target->graphqlOperation;
                if ($operation === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                if ($operation->schema === null || preg_match('/^\$sourceDescriptions\.[A-Za-z0-9_-]+$/', $operation->schema) !== 1) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' GraphQL schema must be a whole-source reference like \$sourceDescriptions.<name>.",
                        $path,
                    );
                }

                if ($operation->operation === null || trim($operation->operation) === '') {
                    $errors->error('step.graphql_step', "Step '{$s->stepId}' GraphQL operation is required.", $path);
                }

                if ($operation->extensions !== null && $operation->extensionsSelector !== null) {
                    $errors->error(
                        'step.graphql_step',
                        "Step '{$s->stepId}' extensions and extensionsSelector are mutually exclusive.",
                        $path,
                    );
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default`.

5. Run + commit.

---

## Task D4f — `InteractionStepRule`

**Prereqs:** D4b landed.

**Files:**
- Create `packages/document/src/Validator/Rules/InteractionStepRule.php`
- Create `packages/document/tests/Validator/Rules/InteractionStepRuleTest.php`
- Edit `packages/document/src/Validator/RuleSet.php` (register)

**Error codes:** `step.interaction_step`.

### Steps

1. Failing test.

`packages/document/tests/Validator/Rules/InteractionStepRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\InteractionStepRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a form interaction with a prompt and input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Confirm', inputSchema: ['type' => 'object'], mode: InteractionMode::Form),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('accepts an acknowledge interaction with no input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Proceed', mode: InteractionMode::Acknowledge),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('requires a prompt', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', interaction: new Interaction(inputSchema: ['type' => 'object'], mode: InteractionMode::Form))])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.interaction_step');
});

it('requires an input schema for form and redirect modes', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Form)),
        Fx::step('b', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Redirect, redirectUrl: 'https://x.test')),
    ])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('forbids input schema on acknowledge', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'P', mode: InteractionMode::Acknowledge, inputSchema: ['type' => 'object']),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('requires exactly one redirect target', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', interaction: new Interaction(
            prompt: 'P',
            mode: InteractionMode::Redirect,
            inputSchema: ['type' => 'object'],
            redirectOperationId: 'x',
            redirectUrl: 'https://x.test',
        )),
    ])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});
```

2. Run — fails.

```bash
composer run test-document -- --filter=InteractionStepRule
```

3. Implement.

`packages/document/src/Validator/Rules/InteractionStepRule.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * interaction-step rules (PR #568):
 *  - prompt is required
 *  - form/redirect modes require an inputSchema; acknowledge forbids it
 *  - redirect mode requires exactly one redirect target
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class InteractionStepRule implements Rule
{
    public function code(): string
    {
        return 'step.interaction_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $interaction = $s->target->interaction;
                if ($interaction === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                $mode = $interaction->mode ?? InteractionMode::Form;

                if ($interaction->prompt === null) {
                    $errors->error('step.interaction_step', "Step '{$s->stepId}' interaction requires a prompt.", $path);
                }

                if ($mode === InteractionMode::Acknowledge) {
                    if ($interaction->inputSchema !== null) {
                        $errors->error(
                            'step.interaction_step',
                            "Step '{$s->stepId}' acknowledge interaction cannot declare an inputSchema.",
                            $path,
                        );
                    }

                    continue;
                }

                if ($interaction->inputSchema === null) {
                    $errors->error(
                        'step.interaction_step',
                        "Step '{$s->stepId}' {$mode->value} interaction requires an inputSchema.",
                        $path,
                    );
                }

                if ($mode === InteractionMode::Redirect) {
                    $targets = array_filter([
                        $interaction->redirectOperationId,
                        $interaction->redirectOperationPath,
                        $interaction->redirectUrl,
                    ], static fn (mixed $v): bool => $v !== null);
                    if (count($targets) !== 1) {
                        $errors->error(
                            'step.interaction_step',
                            "Step '{$s->stepId}' redirect interaction requires exactly one of "
                            .'redirectOperationId, redirectOperationPath, or redirectUrl.',
                            $path,
                        );
                    }
                }
            }
        }
    }
}
```

4. Register in `RuleSet::default`.

5. Run + gates + commit:

```bash
composer run test-document -- --filter=InteractionStepRule
composer run analyse-document
git add -A
git commit -m "feat(document): interaction-step validation rule (D4f)"
```

---

## Task D5 — wire the registry into `Document`, final gate

**Prereqs:** D1, D2, D3a–c, D4a–f all landed.

**Files:**
- Edit `packages/document/src/Document.php`
- Create `packages/document/tests/DocumentFacadeNormalizeTest.php`

### Steps

1. Failing facade test.

`packages/document/tests/DocumentFacadeNormalizeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Tests\Support\Fx;

it('normalizes document sources through the registry', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'facade_').'.json';
    file_put_contents($file, json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => ['/pets' => ['get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]]]],
    ]));

    try {
        $document = new Document();
        $index = $document->normalizeSources(Fx::doc(
            sources: [new SourceDescription('pets-api', $file, SourceType::Openapi)],
        ));

        expect($index['$sourceDescriptions.pets-api.listPets'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});
```

2. Run — fails (`normalizeSources` doesn't exist).

```bash
composer run test-document -- --filter=DocumentFacadeNormalizeTest
```

3. Implement. In `Document.php`:

- new property `private SourceNormalizerRegistryInterface $normalizers;`
- imports `use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;`, `SourceNormalizerRegistry`, `OpenApiSourceNormalizer`, `SourceDocument` already imported.
- constructor adds an optional `?SourceNormalizerRegistryInterface $normalizers = null` param (after `$sources`), then:

```php
        $this->normalizers = $normalizers ?? $this->buildDefaultNormalizers($this->sources, $this->versionDetector);
```

with a private helper:

```php
    private function buildDefaultNormalizers(SourceRegistry $sources, OpenApiVersionDetector $versionDetector): SourceNormalizerRegistryInterface
    {
        $registry = new SourceNormalizerRegistry();
        $registry->register(new OpenApiSourceNormalizer(
            new OpenApiDocumentLoader($sources),
            $versionDetector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ));

        return $registry;
    }
```

- new public method (concrete facade only — **not** on `DocumentInterface`, preserving BC). Note: `SourceResolver` returns a **decoded** `array` (`SourceDocument::$content`), while the port accepts raw text, so `normalizeSources` re-encodes before handing to the normalizer:

```php
    /**
     * Normalize every described source and return an operation index.
     *
     * Keys mirror the resolver's accepted references (operationId,
     * $sourceDescriptions.<name>.<operationId>, and #/paths/.../method).
     *
     * @return array<string, ResolvedOperation>
     */
    public function normalizeSources(ArazzoDocument $document, string $basePath = ''): array
    {
        $index = [];
        foreach ($document->sourceDescriptions as $source) {
            $normalizer = $this->normalizers->get($source->type);
            if ($normalizer === null) {
                continue;
            }
            $resolved = $this->sources->resolve($source, $basePath);
            $rawContent = json_encode($resolved->content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $index += $normalizer->normalize($source, $rawContent, $document);
        }

        return $index;
    }
```

4. Run the full suite, then the final gate.

```bash
composer run test-document -- --filter=DocumentFacadeNormalizeTest
composer run test-document
composer run analyse-document
composer run analyse-contracts
composer run format
make verify
```

5. Commit + mark the spec rows:

```bash
git add -A
git commit -m "feat(document): expose normalizeSources on the facade, typed registry wiring (D5)"
```

6. Update the parent spec's decision table to reflect the completed D rows (D1, D2, D3, D4, D5, D9 privileges note) and mark **#23** as closed/superseded-by-D in the spec's "Related issues" section. This spec edit is part of the phase, not a separate plan.

---

## Final gate

- [ ] `make verify` is green on `main` (docs generation, pint, phpstan all six packages, all pest suites).
- [ ] Every file listed in D1–D5 exists with the names above.
- [ ] No `DocumentInterface` changes beyond those approved in Phase A.
- [ ] `ResolvedOperation` still constructs with 5 positional args (runner BC).
- [ ] `#23` closed as superseded by D in the spec.
- [ ] Commit each task separately with the exact messages above.