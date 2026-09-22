# Phase B: Expression Grammar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:
> executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the spec-mandated, step-scoped expression grammar (D8/D10) and transfer-view resolution in
`alama/arazzo-expression`: the `$response.status#/…`, `$response.metadata.*`, `$response.trailingMetadata.*`,
`$request.metadata.*` (RPC), `$interaction.payload[#/…]` (interaction), `$sourceDescriptions.<name>` whole-source (
GraphQL), and generic `$response.meta.*` escape-hatch forms — parsed by the shared `Lexer`/`Parser`, projected onto six
new `ReferenceKind` cases via a new `ReferenceProjector`, resolved against `ResponseTransfer` views when a step records
one, gated per step type so unknown-for-type expressions are parse errors, and routed through the
`ReplacementTargetResolverInterface` registry (core default = json-pointer) for payload replacement.

**Architecture:** The spec splits the expression package into a reference model (lexer/parser/AST + `ReferenceKind` +
`ExpressionReference`) and an evaluation device (`ExpressionEngine`, evaluators, `PayloadReplacer`) — see Phase C (D11).
This phase works against the **current single `packages/expression` package** where the whole engine already lives, so
every FQCN (`Alama\Arazzo\Expression\…`) is today's FQCN and no namespace changes appear. Phase C relocates classes
wholesale between the two packages; nothing in this phase invents a parallel vocabulary.

- **B1 (grammar):** `Lexer::KEYWORDS` gains `status`, `metadata`, `trailingMetadata`, `meta`, `interaction`. `Parser`
  gains an `interaction` root (`InteractionRef` AST node), the four response/request facets (`status` with optional
  pointer, `metadata.*`, `trailingMetadata.*`, `meta.<dotted>`), a `reassembleDotted()` helper for opaque `meta` keys,
  and request/response-side rejection guards. `ReferenceKind` gains six cases. A new `@internal ReferenceProjector`
  extracts the AST→`ExpressionReference` projection out of `ExpressionEngine` (shared with the criteria evaluator in
  B4). The whole-source form reuses the existing `$sourceDescriptions.<name>` grammar (subPath-null `SourceRef`) — D8
  makes it a distinct `WholeSource` kind.
- **B2 (transfer views):** New `@internal Evaluation\Data\ResponseTransferView` adapts a step-recorded
  `ResponseTransfer` (`response.transfer` bag key). `ExpressionEvaluator`, `SelectorEvaluator` and `CriteriaEvaluator`
  read the facet views (json/xml/proto/meta) when present, falling back to today's raw context shapes otherwise (BC
  additive). Request metadata resolves from `request.metadata`; interaction payload from `interaction.payload`.
- **B3 (replacement registry):** New `ReplacementTargetResolverRegistry` (priority-ordered first-match) + core
  `JsonPointerReplacementTargetResolver` (`arazzo.json-pointer`). `PayloadReplacer::apply()` gains an optional registry
  parameter routed through the `ExpressionEngine` constructor; pointer targets resolve via the registry, `xpath`/
  `proto-field` selector targets resolve via registered resolvers (xpath keeps the legacy in-core fallback), `jsonpath`
  stays in-core until Phase C1.
- **B4 (scope gate):** New `@internal StepExpressionScope::allows(ExpressionReference, Step)`; new additive seam
  `ExpressionEngineInterface::parseStepExpression(string, Step): ?ExpressionSyntaxException`; `CriteriaEvaluator` fails
  out-of-scope criteria deterministically. Simple-condition culture: interpolated out-of-scope references fail closed
  via null resolution (documented, not gated).

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), PHPStan ^2.0 level max (`packages/expression/phpstan.neon.dist`),
Laravel Pint, `softcreatr/jsonpath` (unchanged until Phase C1).

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`

## Global Constraints

- **Prerequisite:** Phase A (`docs/superpowers/plans/2026-09-08-phase-a-contracts-ports.md`) must be merged first: it
  supplies `ResponseTransfer` (`Alama\Arazzo\Contracts\Spec\ResponseTransfer`, A6),
  `ReplacementTargetResolverInterface` (`Alama\Arazzo\Contracts\Interfaces`, A2 — `name()`/`priority()` from
  `PluginInterface`, `supports(string $targetType)` with `json-pointer | xpath | proto-field` semantics,
  `resolve(mixed $container, string $target, mixed $value): mixed`), and the A7 `Step` fields this plan scopes on (
  `rpcMethod`, `rpcProtocol`, `graphqlOperation`, `interaction`).
- New/changed files live in `packages/expression` only (namespace `Alama\Arazzo\Expression\…`),
  `declare(strict_types=1)`. No new composer `require` entries; no changes outside `packages/expression` except the
  final spec checkmark (B5).
- This phase targets the **single** `packages/expression` package — the spec's expression/evaluation split (D11/C0) is
  Phase C and relocates classes wholesale; namespaces stay identical. The plan notes each new class's D11 home in its
  docblock.
- Keyword additions are BC-safe: every parser name position already accepts `TokenKind::Keyword` (`parseSimpleRef`,
  `parseNamedPart`, `parseStepRef`, `parseSourceRef`, `parseHeaderName`), so a word turning into a keyword does not
  break existing expressions.
- `ReferenceKind` and the `ExpressionReference` fields are **additive only**; the docblock in
  `Data/ExpressionReference.php` is extended, never rewritten.
- Unknown-for-type expressions are parse errors (D8): well-formed==but-wrong-step-type forms surface as
  `ExpressionSyntaxException` through the new `parseStepExpression` seam (B4). The grammar layer (B1) only rejects
  request/response-side mismatches; step-type scope is applied in B4.
- Every task ends with `composer run test-expression` green (runs `vendor/bin/pest packages/expression/tests` from the
  repo root — all monorepo composer scripts are root-run).
- Every task's `--filter` runs: `vendor/bin/pest packages/expression/tests --filter "<name>"` from the repo root.
- Static analysis per task: `composer run analyse-expression` (PHPStan with `packages/expression/phpstan.neon.dist`,
  level max).
- Keep source comments minimal; docblocks explain spec nuance only (transfer bag key homes, scope matrix, D8/D11
  anchors). No decorative comments.
- Sequencing (spec): C → B → D. Assume C0's split ran (D11); if C has not landed when B starts, no change is needed —
  the classes being touched are all in `packages/expression` either way.

---

### Task B1: Step-scoped grammar cases + reference projection

Add the grammar forms from the 1.2 PRs (D8/D10, spec "expression axis"): `$response.status#/…`, `$response.status`,
`$response.metadata.*`, `$response.trailingMetadata.*`, `$request.metadata.*`, `$interaction.payload[#/…]`, and
`$response.meta.<dotted>`. Projection of the whole-source `$sourceDescriptions.<name>` (subPath-null) becomes the new
`WholeSource` kind. The projection logic moves out of `ExpressionEngine` into `ReferenceProjector` so B4 can reuse it.

**Files:**

- Modify: `packages/expression/src/Lexer.php` (KEYWORDS)
- Modify: `packages/expression/src/Parser.php` (root match, `parseInteractionRef`, `parseHttpPart` match,
  `reassembleDotted`)
- Create: `packages/expression/src/Ast/InteractionRef.php`
- Modify: `packages/expression/src/Enum/ReferenceKind.php`
- Create: `packages/expression/src/ReferenceProjector.php`
- Modify: `packages/expression/src/ExpressionEngine.php` (delegate projection; drop unused AST imports)
- Modify: `packages/expression/src/Data/ExpressionReference.php` (docblock only)
- Test: `packages/expression/tests/Expression/LexerTest.php`
- Test: `packages/expression/tests/Expression/ParserTest.php`
- Test: `packages/expression/tests/ExpressionEngineCapabilitiesTest.php`

**Interfaces:**

- Consumes: `ExpressionAst`/AST nodes, `Token`/`TokenKind` (`Alama\Arazzo\Expression\…`), `ExpressionReference` (
  `Alama\Arazzo\Expression\Data`).
- Produces: six new `ReferenceKind` cases (`ResponseStatus`, `ResponseMetadata`, `RequestMetadata`,
  `InteractionPayload`, `WholeSource`, `Meta`); `InteractionRef` AST node; root `interaction` grammar; response facets
  `status`/`metadata`/`trailingMetadata`/`meta`; request facet `metadata`;
  `ReferenceProjector::project(ExpressionAst): ExpressionReference`.

- [ ] **Step 1: Write the failing projection test**

Open `packages/expression/tests/ExpressionEngineCapabilitiesTest.php` and append these rows to the `->with([...])` table
of the `'projects every reference kind without leaking the AST'` test:

```php
    'response status' => ['{$response.status}', ReferenceKind::ResponseStatus, null, 'response', null, 'status', null],
    'step response status pointer' => ['{$steps.rpc.response.status#/code}', ReferenceKind::ResponseStatus, 'rpc', 'response', null, 'status', '/code'],
    'response metadata' => ['{$response.metadata.k1}', ReferenceKind::ResponseMetadata, null, 'response', 'k1', 'metadata', null],
    'step response metadata' => ['{$steps.rpc.response.metadata.auth}', ReferenceKind::ResponseMetadata, 'rpc', 'response', 'auth', 'metadata', null],
    'response trailing metadata' => ['{$response.trailingMetadata.k2}', ReferenceKind::ResponseMetadata, null, 'response', 'k2', 'trailingMetadata', null],
    'request metadata' => ['{$request.metadata.k3}', ReferenceKind::RequestMetadata, null, 'request', 'k3', 'metadata', null],
    'response meta dotted key' => ['{$response.meta.soap.faultcode}', ReferenceKind::Meta, null, 'response', 'soap.faultcode', 'meta', null],
    'interaction payload pointer' => ['{$interaction.payload#/approval/ok}', ReferenceKind::InteractionPayload, null, 'payload', null, null, '/approval/ok'],
    'whole source' => ['{$sourceDescriptions.sdl}', ReferenceKind::WholeSource, 'sdl', null, null, null, null],
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/expression/tests --filter "projects every reference kind"` (repo root)

Expected: FAIL — `ReferenceKind::ResponseStatus` etc. do not exist yet, and
`expressionReferences('{$response.metadata.k1}')` returns a `Step` projection.

- [ ] **Step 3: Write the failing lexer + parser tests**

Append to `packages/expression/tests/Expression/LexerTest.php`:

```php
it('tokenises the step-scoped grammar keywords', function (): void {
    $t = (new Lexer())->tokenize('{$response.trailingMetadata.auth}');
    $values = array_map(fn ($tok) => $tok->value, $t);

    expect($values)->toBe(['response', '.', 'trailingMetadata', '.', 'auth']);
});
```

Append to `packages/expression/tests/Expression/ParserTest.php` (add the `InteractionRef` import to the `use` block):

```php
use Alama\Arazzo\Expression\Ast\InteractionRef;

it('parses $response.status with a pointer and $steps.s.response.status#/...', function (): void {
    $parser = new Parser();

    $implicit = $parser->parse('$response.status#/code');
    expect($implicit)->toBeInstanceOf(StepRef::class)
        ->and($implicit->part)->toBeInstanceOf(ResponsePart::class)
        ->and($implicit->part->httpPart)->toBe('status')
        ->and($implicit->part->jsonPointer)->toBe('/code');

    $step = $parser->parse('$steps.rpc.response.status#/code');
    expect($step->part->httpPart)->toBe('status')
        ->and($step->part->jsonPointer)->toBe('/code');
});

it('parses $response.metadata.<name> and $response.trailingMetadata.<name>', function (): void {
    $parser = new Parser();

    $meta = $parser->parse('$response.metadata.trace-id');
    expect($meta->part)->toBeInstanceOf(ResponsePart::class)
        ->and($meta->part->httpPart)->toBe('metadata')
        ->and($meta->part->headerName)->toBe('trace-id');

    $trailing = $parser->parse('$response.trailingMetadata.balance');
    expect($trailing->part->httpPart)->toBe('trailingMetadata')
        ->and($trailing->part->headerName)->toBe('balance');
});

it('parses $request.metadata.<name>', function (): void {
    $ast = (new Parser())->parse('$request.metadata.request-id');
    expect($ast)->toBeInstanceOf(StepRef::class)
        ->and($ast->part)->toBeInstanceOf(RequestPart::class)
        ->and($ast->part->httpPart)->toBe('metadata')
        ->and($ast->part->headerName)->toBe('request-id');
});

it('parses $response.meta.<dotted> as one opaque key', function (): void {
    $ast = (new Parser())->parse('$response.meta.soap.faultcode');
    expect($ast->part->httpPart)->toBe('meta')
        ->and($ast->part->headerName)->toBe('soap.faultcode');
});

it('parses $interaction.payload with an optional pointer', function (): void {
    $ast = (new Parser())->parse('$interaction.payload#/approval/ok');
    expect($ast)->toBeInstanceOf(InteractionRef::class)
        ->and($ast->jsonPointer)->toBe('/approval/ok');

    $bare = (new Parser())->parse('$interaction.payload');
    expect($bare)->toBeInstanceOf(InteractionRef::class)
        ->and($bare->jsonPointer)->toBeNull();
});

it('rejects request-side status, trailingMetadata and meta', function (string $raw): void {
    (new Parser())->parse($raw);
})->throws(ExpressionSyntaxException::class)->with([
    '$request.status',
    '$request.trailingMetadata.x',
    '$request.meta.x',
]);
```

- [ ] **Step 4: Run tests to verify they fail**

Run:
`vendor/bin/pest packages/expression/tests --filter "step-scoped grammar|response.status|interaction.payload|request-side"` (
repo root)

Expected: FAIL — the keywords are not recognized, so the head/part tokens come back as `Name`/unknown and the parser
throws.

- [ ] **Step 5: Implement the grammar (Lexer + Parser + AST)**

Edit `packages/expression/src/Lexer.php` — extend `KEYWORDS`:

```php
    private const KEYWORDS = [
        'inputs', 'outputs', 'steps', 'workflows', 'sourceDescriptions',
        'components', 'response', 'request', 'url', 'method', 'statusCode',
        'status', 'metadata', 'trailingMetadata', 'meta', 'interaction',
        'body', 'header', 'query', 'path', 'message', 'payload', 'self',
    ];
```

Create `packages/expression/src/Ast/InteractionRef.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * Actor-in-the-loop payload reference: `{$interaction.payload[#/ptr]}` (D8).
 *
 * Resolved against the current step's recorded interaction payload (B2).
 * Grammar side of `alama/arazzo-evaluation` after the Phase C split (D11).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class InteractionRef extends ExpressionAst
{
    public function __construct(public ?string $jsonPointer = null) {}
}
```

Edit `packages/expression/src/Parser.php`:

1. Add the `interaction` root to the `parse()` match:

```php
            'interaction' => $this->parseInteractionRef(array_slice($tokens, $i), $raw),
```

2. Extend the `parseHttpPart()` match — replace the whole `return match ($kw) {` block:

```php
        return match ($kw) {
            'body' => new $cls('body', null, $this->parseJsonPointer($tail, $raw)),
            'header', 'query', 'path' => new $cls($kw, $this->parseHeaderName($tail, $raw), null),
            'metadata' => new $cls('metadata', $this->parseHeaderName($tail, $raw), null),
            'status' => $cls === ResponsePart::class
                ? new $cls('status', null, $this->parseJsonPointer($tail, $raw))
                : throw new ExpressionSyntaxException("'status' is not a valid request part in: {$raw}", $raw, -1, '', 'expr.syntax'),
            'trailingMetadata' => $cls === ResponsePart::class
                ? new $cls('trailingMetadata', $this->parseHeaderName($tail, $raw), null)
                : throw new ExpressionSyntaxException("'trailingMetadata' is not a valid request part in: {$raw}", $raw, -1, '', 'expr.syntax'),
            'meta' => $cls === ResponsePart::class
                ? new $cls('meta', $this->reassembleDotted($tail, $raw), null)
                : throw new ExpressionSyntaxException("'meta' is not a valid request part in: {$raw}", $raw, -1, '', 'expr.syntax'),
            'url', 'method', 'statusCode' => (function () use ($cls, $kw, $tail, $raw) {
                if ($tail !== []) {
                    throw new ExpressionSyntaxException("Unexpected tokens after '{$kw}' in: {$raw}", $raw, -1, '', 'expr.syntax');
                }

                return new $cls($kw, null, null);
            })(),
            default => throw new ExpressionSyntaxException("Unknown http part '{$kw}' in: {$raw}", $raw, -1, '', 'expr.syntax'),
        };
```

3. Add the two new private methods (place them next to `parseMessageRef`):

```php
    /** @param list<Token> $rest */
    private function parseInteractionRef(array $rest, string $raw): InteractionRef
    {
        // interaction . payload [ # /pointer ]
        if (count($rest) < 3
            || $rest[1]->kind !== TokenKind::Dot
            || $rest[2]->kind !== TokenKind::Keyword
            || $rest[2]->value !== 'payload') {
            throw new ExpressionSyntaxException("Interaction part must be payload in: {$raw}", $raw, -1, '', 'expr.syntax');
        }

        return new InteractionRef($this->parseJsonPointer(array_slice($rest, 3), $raw));
    }

    /**
     * Reassembles a dotted `meta` key from `.a.b.c` tail tokens so opaque
     * execution-environment keys (soap.faultcode, grpc.message, ...) survive
     * the lexer as a single `ExpressionReference` name (D8 meta escape hatch).
     *
     * @param  list<Token>  $tail
     */
    private function reassembleDotted(array $tail, string $raw): string
    {
        if ($tail === [] || $tail[0]->kind !== TokenKind::Dot) {
            throw new ExpressionSyntaxException("Expected '.key' after meta in: {$raw}", $raw, -1, '', 'expr.syntax');
        }

        $segments = [];
        $expectName = true;
        foreach (array_slice($tail, 1) as $t) {
            if ($expectName) {
                if ($t->kind !== TokenKind::Name && $t->kind !== TokenKind::Keyword) {
                    throw new ExpressionSyntaxException("Expected name segment in meta key in: {$raw}", $raw, -1, '', 'expr.syntax');
                }
                $segments[] = $t->value;
            } elseif ($t->kind !== TokenKind::Dot) {
                throw new ExpressionSyntaxException("Expected '.' between meta segments in: {$raw}", $raw, -1, '', 'expr.syntax');
            }
            $expectName = !$expectName;
        }

        if ($segments === [] || $expectName) {
            throw new ExpressionSyntaxException("Malformed meta key in: {$raw}", $raw, -1, '', 'expr.syntax');
        }

        return implode('.', $segments);
    }
```

- [ ] **Step 6: Implement the ReferenceKind cases + ReferenceProjector**

Edit `packages/expression/src/Enum/ReferenceKind.php` — append before the closing brace:

```php
    case ResponseStatus;
    case ResponseMetadata;
    case RequestMetadata;
    case InteractionPayload;
    case WholeSource;
    case Meta;
```

Create `packages/expression/src/ReferenceProjector.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Ast\ComponentRef;use Alama\Arazzo\Expression\Ast\ExpressionAst;use Alama\Arazzo\Expression\Ast\HttpMetaRef;use Alama\Arazzo\Expression\Ast\InputPart;use Alama\Arazzo\Expression\Ast\InputRef;use Alama\Arazzo\Expression\Ast\InteractionRef;use Alama\Arazzo\Expression\Ast\MessageRef;use Alama\Arazzo\Expression\Ast\OutputPart;use Alama\Arazzo\Expression\Ast\OutputRef;use Alama\Arazzo\Expression\Ast\RequestPart;use Alama\Arazzo\Expression\Ast\ResponsePart;use Alama\Arazzo\Expression\Ast\SourceRef;use Alama\Arazzo\Expression\Ast\StepRef;use Alama\Arazzo\Expression\Ast\WorkflowRef;use Alama\Arazzo\Expression\Data\ExpressionReference;use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * Projects a parsed expression AST onto the public {@see ExpressionReference}
 * surface so downstream consumers never touch the (internal) AST.
 *
 * Reference model side after the Phase C split (D11). Shared by the facade
 * and the criteria evaluator (B4).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ReferenceProjector
{
    public function project(ExpressionAst $ast): ExpressionReference
    {
        if ($ast instanceof InputRef) {
            return new ExpressionReference(ReferenceKind::Input, $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof OutputRef) {
            return new ExpressionReference(ReferenceKind::Output, $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof StepRef) {
            return $this->projectStep($ast);
        }

        if ($ast instanceof WorkflowRef) {
            return new ExpressionReference(ReferenceKind::Workflow, $ast->workflowId, $ast->partKind, $ast->name);
        }

        if ($ast instanceof SourceRef) {
            if ($ast->subPath === null) {
                // Whole-source reference (GraphQL SDL): the complete source
                // description object, not one of its scalar facets (D8).
                return new ExpressionReference(ReferenceKind::WholeSource, $ast->name);
            }

            return new ExpressionReference(ReferenceKind::Source, $ast->name, name: $ast->subPath);
        }

        if ($ast instanceof ComponentRef) {
            return new ExpressionReference(ReferenceKind::Component, $ast->type, name: $ast->name);
        }

        if ($ast instanceof MessageRef) {
            return new ExpressionReference(ReferenceKind::Message, part: $ast->part, name: $ast->name, jsonPointer: $ast->jsonPointer);
        }

        if ($ast instanceof HttpMetaRef) {
            return new ExpressionReference(ReferenceKind::HttpMeta, httpPart: $ast->field);
        }

        if ($ast instanceof InteractionRef) {
            return new ExpressionReference(ReferenceKind::InteractionPayload, part: 'payload', jsonPointer: $ast->jsonPointer);
        }

        return new ExpressionReference(ReferenceKind::Self);
    }

    private function projectStep(StepRef $ast): ExpressionReference
    {
        $part = $ast->part;

        if ($part instanceof OutputPart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'outputs', $part->name, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof InputPart) {
            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'inputs', $part->name);
        }

        if ($part instanceof RequestPart) {
            if ($part->httpPart === 'metadata') {
                return new ExpressionReference(ReferenceKind::RequestMetadata, $ast->stepId, 'request', $part->headerName, 'metadata');
            }

            return new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'request', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof ResponsePart) {
            return match ($part->httpPart) {
                'status' => new ExpressionReference(ReferenceKind::ResponseStatus, $ast->stepId, 'response', $part->headerName, 'status', jsonPointer: $part->jsonPointer),
                'metadata' => new ExpressionReference(ReferenceKind::ResponseMetadata, $ast->stepId, 'response', $part->headerName, 'metadata'),
                'trailingMetadata' => new ExpressionReference(ReferenceKind::ResponseMetadata, $ast->stepId, 'response', $part->headerName, 'trailingMetadata'),
                'meta' => new ExpressionReference(ReferenceKind::Meta, $ast->stepId, 'response', $part->headerName, 'meta'),
                default => new ExpressionReference(ReferenceKind::Step, $ast->stepId, 'response', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer),
            };
        }

        return new ExpressionReference(ReferenceKind::Step, $ast->stepId);
    }
}
```

- [ ] **Step 7: Delegate the engine projection + update the docblock**

Edit `packages/expression/src/ExpressionEngine.php` — replace the body of `expressionReferences()` and delete the
private `referenceFor()`/`stepReference()` methods:

```php
    public function expressionReferences(string $raw): ?ExpressionReference
    {
        $result = $this->parser->parseOrError($raw);
        if ($result instanceof ExpressionSyntaxException) {
            return null;
        }

        return $this->projector->project($result);
    }
```

Add the projector to the constructor and the class property:

```php
    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
        private readonly ExpressionParser $parser = new ExpressionParser(),
        private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
        private readonly ReferenceProjector $projector = new ReferenceProjector(),
    ) {}
```

Update the `use` block: remove the now-unused `Ast\*` and `Enum\ReferenceKind` imports (the projector owns them); keep
`Data\ExpressionReference`.

Edit `packages/expression/src/Data/ExpressionReference.php` — extend the field-semantics docblock with the new kinds:

```php
 * - `ResponseStatus`: {@see $target} is the step id (null = current step),
 *   {@see $part} is `response`, {@see $httpPart} is `status`,
 *   {@see $jsonPointer} an optional pointer into the structured status.
 * - `ResponseMetadata`/`RequestMetadata`: {@see $httpPart} is `metadata` or
 *   `trailingMetadata`, {@see $name} the metadata key.
 * - `Meta`: {@see $name} is the opaque dotted `meta` bag key.
 * - `InteractionPayload`: {@see $part} is `payload`, {@see $jsonPointer}
 *   an optional pointer into the actor payload.
 * - `WholeSource`: {@see $target} is the source description name (GraphQL SDL).
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `composer run test-expression` (repo root)

Expected: PASS (lexer/parser/projection/new rows + the pre-existing suite).

Note: the newly parsed forms do **not** evaluate yet (they resolve to `null`) — evaluation lands in B2.

- [ ] **Step 9: Run static analysis**

Run: `composer run analyse-expression` (repo root)

Expected: PASS (0 errors). If PHPStan flags unused imports left over in `ExpressionEngine.php`, remove them and re-run.

- [ ] **Step 10: Commit**

```bash
git add packages/expression/src/Lexer.php packages/expression/src/Parser.php packages/expression/src/Ast/InteractionRef.php packages/expression/src/Enum/ReferenceKind.php packages/expression/src/ReferenceProjector.php packages/expression/src/ExpressionEngine.php packages/expression/src/Data/ExpressionReference.php packages/expression/tests/Expression/LexerTest.php packages/expression/tests/Expression/ParserTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php
git commit -m "feat(expression): add step-scoped grammar cases and reference projection"
```

---

### Task B2: Transfer-view resolution

Make `ExpressionEvaluator`, `SelectorEvaluator` and `CriteriaEvaluator` resolve the new forms (and the existing ones)
against a step-recorded `ResponseTransfer` when present — the protocol fill-slot phase (spec B2, `ResponseTransfer`
lines 278-293). The transfer is recorded on the workflow context as `response.transfer`; the new `ResponseTransferView`
adapts it. Without a transfer, every resolution path falls back to today's raw context shapes unchanged (BC additive).

**Files:**

- Create: `packages/expression/src/Evaluation/Data/ResponseTransferView.php`
- Modify: `packages/expression/src/ExpressionEvaluator.php`
- Modify: `packages/expression/src/SelectorEvaluator.php`
- Modify: `packages/expression/src/Evaluation/CriteriaEvaluator.php`
- Test: create `packages/expression/tests/Evaluation/ResponseTransferViewTest.php`
- Test: `packages/expression/tests/ExpressionEngineCapabilitiesTest.php`

**Interfaces:**

- Consumes: `ResponseTransferInterface` (`Alama\Arazzo\Contracts\Interfaces`, A6), `ResponseTransfer` (
  `Alama\Arazzo\Contracts\Spec`), `JsonPointer`, `SourceDescription`.
- Produces: `ResponseTransferView` (from/statusCode/get facet reader); evaluated `$response.status[#/…]`,
  `$response.metadata.*`, `$response.trailingMetadata.*`, `$response.meta.<dotted>`, `$request.metadata.*`,
  `$interaction.payload[#/…]`, `$sourceDescriptions.<name>` whole-source; transfer-rooted selectors and criteria.

- [ ] **Step 1: Write the failing view test**

Create `packages/expression/tests/Evaluation/ResponseTransferViewTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ResponseTransfer;
use Alama\Arazzo\Expression\Evaluation\Data\ResponseTransferView;

it('is null when no transfer is recorded', function (): void {
    expect(ResponseTransferView::from(null))->toBeNull()
        ->and(ResponseTransferView::from(['response' => ['statusCode' => 200]]))->toBeNull();
});

it('reads a transfer recorded under response.transfer', function (): void {
    $transfer = new ResponseTransfer(status: 200, headers: ['Content-Type' => 'application/json'], rawBody: '{}');
    $view = ResponseTransferView::from(['response' => ['transfer' => $transfer]]);

    expect($view)->toBeInstanceOf(ResponseTransferView::class)
        ->and($view->statusCode())->toBe(200);
});

it('returns null when the recorded status is a structured object', function (): void {
    $transfer = new ResponseTransfer(status: (object) ['code' => 4], headers: [], rawBody: '');

    expect(ResponseTransferView::from(['response' => ['transfer' => $transfer]])->statusCode())->toBeNull();
});

it('exposes json, meta, metadata, trailing metadata and header facets', function (): void {
    $transfer = new ResponseTransfer(
        status: (object) ['code' => 4],
        headers: ['X-Mode' => 'Live'],
        rawBody: '',
        views: ['json' => ['users' => [['id' => 1]]]],
        meta: ['soap.faultcode' => 'SOAP-ENV:Server', 'metadata' => ['trace-id' => 'abc'], 'trailingMetadata' => ['balance' => '42']],
    );
    $view = ResponseTransferView::from(['response' => ['transfer' => $transfer]]);

    expect($view->get('status', null, '/code'))->toBe(4)
        ->and($view->get('header', 'X-Mode', null))->toBe('Live')
        ->and($view->get('body', null, '/users/0/id'))->toBe(1)
        ->and($view->get('metadata', 'trace-id', null))->toBe('abc')
        ->and($view->get('trailingMetadata', 'balance', null))->toBe('42')
        ->and($view->get('meta', 'soap.faultcode', null))->toBe('SOAP-ENV:Server');
});
```

- [ ] **Step 2: Run the view test to verify it fails**

Run: `vendor/bin/pest packages/expression/tests --filter ResponseTransferViewTest` (repo root)

Expected: FAIL with "Class ResponseTransferView not found".

- [ ] **Step 3: Write the failing engine resolution tests**

Append to `packages/expression/tests/ExpressionEngineCapabilitiesTest.php` (imports to add:
`Alama\Arazzo\Contracts\Spec\ResponseTransfer`, `Alama\Arazzo\Contracts\Spec\SourceDescription`,
`Alama\Arazzo\Expression\Data\EvaluationInput`):

```php
it('resolves $response.status#/… from the transfer status object', function (): void {
    $engine = new ExpressionEngine();
    $transfer = new ResponseTransfer(
        status: (object) ['code' => 4, 'message' => 'DEADLINE_EXCEEDED', 'details' => []],
        headers: [],
        rawBody: '',
    );
    $context = (new WorkflowContext('def_1'))->withStepResponse('s1', ['transfer' => $transfer]);

    expect($engine->evaluate(new Expression('{$response.status#/code}'), new EvaluationInput($context, 's1')))->toBe(4)
        ->and($engine->evaluate(new Expression('{$response.status#/message}'), new EvaluationInput($context, 's1')))->toBe('DEADLINE_EXCEEDED')
        ->and($engine->evaluate(new Expression('{$steps.s1.response.status#/code}'), new EvaluationInput($context, 's1')))->toBe(4);
});

it('resolves $response.metadata.* and $response.trailingMetadata.* from the transfer meta bag', function (): void {
    $engine = new ExpressionEngine();
    $transfer = new ResponseTransfer(
        status: (object) ['code' => 0],
        headers: [],
        rawBody: '',
        meta: ['metadata' => ['trace-id' => 'abc'], 'trailingMetadata' => ['balance' => '42']],
    );
    $context = (new WorkflowContext('def_1'))->withStepResponse('s1', ['transfer' => $transfer]);

    expect($engine->evaluate(new Expression('{$response.metadata.trace-id}'), new EvaluationInput($context, 's1')))->toBe('abc')
        ->and($engine->evaluate(new Expression('{$response.trailingMetadata.balance}'), new EvaluationInput($context, 's1')))->toBe('42');
});

it('resolves the $response.meta escape hatch, $request.metadata.* and $interaction.payload', function (): void {
    $engine = new ExpressionEngine();
    $soap = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['transfer' => new ResponseTransfer(status: 200, headers: [], rawBody: '', meta: ['soap.faultcode' => 'SOAP-ENV:Server'])]);
    $rpcReq = (new WorkflowContext('def_1'))->withStepRequest('s1', ['metadata' => ['request-id' => 'req-7']]);
    $interaction = (new WorkflowContext('def_1'))->withStepResult('s1', ['interaction' => ['payload' => ['approval' => ['ok' => true]]]]);

    expect($engine->evaluate(new Expression('{$response.meta.soap.faultcode}'), new EvaluationInput($soap, 's1')))->toBe('SOAP-ENV:Server')
        ->and($engine->evaluate(new Expression('{$request.metadata.request-id}'), new EvaluationInput($rpcReq, 's1')))->toBe('req-7')
        ->and($engine->evaluate(new Expression('{$interaction.payload#/approval/ok}'), new EvaluationInput($interaction, 's1')))->toBeTrue();
});

it('returns the whole source description for $sourceDescriptions.<name>', function (): void {
    $engine = new ExpressionEngine();
    $context = new WorkflowContext('def_1');
    $ref = $engine->evaluate(new Expression('{$sourceDescriptions.api}'), new EvaluationInput($context, 's1', capabilityDocument()));

    expect($ref)->toBeInstanceOf(SourceDescription::class)
        ->and($ref->url)->toBe('/x');
});

it('roots selectors and jsonpath criteria on the transfer body view', function (): void {
    $engine = new ExpressionEngine();
    $transfer = new ResponseTransfer(status: 200, headers: [], rawBody: '', views: ['json' => ['users' => [['id' => 1], ['id' => 2]]]]);
    $context = (new WorkflowContext('def_1'))->withStepResponse('s1', ['transfer' => $transfer]);

    $selector = new Selector(null, '$.users[0].id', ExpressionType::JsonPath);
    expect($engine->evaluateSelector($selector, $context, 's1'))->toBe(1);

    $step = capabilityStep(criteria: [new SuccessCriterion(null, '$.users[1].id', CriterionType::JsonPath)]);
    expect($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('roots xpath criteria on the transfer xml view', function (): void {
    $engine = new ExpressionEngine();
    $xml = new DOMDocument();
    $xml->loadXML('<root><ok>true</ok></root>');
    $transfer = new ResponseTransfer(status: 200, headers: [], rawBody: '', views: ['xml' => $xml]);
    $context = (new WorkflowContext('def_1'))->withStepResponse('s1', ['transfer' => $transfer]);

    $step = capabilityStep(criteria: [new SuccessCriterion(null, '/root/ok', CriterionType::XPath)]);
    expect($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
});
```

- [ ] **Step 4: Run the engine tests to verify they fail**

Run:
`vendor/bin/pest packages/expression/tests --filter "transfer status object|transfer meta bag|escape hatch|whole source description|transfer body view|transfer xml view"` (
repo root)

Expected: FAIL — the new forms evaluate to `null` after B1.

- [ ] **Step 5: Implement the ResponseTransferView**

Create `packages/expression/src/Evaluation/Data/ResponseTransferView.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Data;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;
use Alama\Arazzo\Contracts\Spec\ResponseTransfer;
use Alama\Arazzo\Expression\JsonPointer;

/**
 * Read adapter over a {@see ResponseTransfer} recorded on a step as
 * `response.transfer` in the workflow context (B2).
 *
 * The adapter programs against the {@see ResponseTransferInterface} seam only:
 * decoded facets live behind `hasView()`/`view()` (key homes filled by the
 * per-protocol DTOs in Phase F), raw status/headers on `status()`/`headers()`,
 * and protocol metadata in the `meta()` bag.
 *
 * Evaluation device side after the Phase C split (D11).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ResponseTransferView
{
    public static function from(mixed $stepData): ?self
    {
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
        $transfer = is_array($response) ? ($response['transfer'] ?? null) : null;

        return $transfer instanceof ResponseTransferInterface ? new self($transfer) : null;
    }

    public function __construct(public ResponseTransferInterface $transfer) {}

    public function statusCode(): int|string|null
    {
        $status = $this->transfer->status();

        return is_int($status) || is_string($status) ? $status : null;
    }

    public function get(string $httpPart, ?string $name, ?string $jsonPointer): mixed
    {
        return match ($httpPart) {
            'status', 'statusCode' => $this->statusView($jsonPointer),
            'header' => $this->transfer->headers()[$name] ?? null,
            'metadata' => $this->transfer->meta()['metadata'][$name] ?? null,
            'trailingMetadata' => $this->transfer->meta()['trailingMetadata'][$name] ?? null,
            'meta' => $this->transfer->meta()[$name] ?? null,
            'body' => $this->bodyView($jsonPointer),
            default => null,
        };
    }

    private function bodyView(?string $pointer): mixed
    {
        if ($this->transfer->hasView('json')) {
            return JsonPointer::resolve($this->transfer->view('json'), $pointer);
        }
        if ($this->transfer->hasView('xml')) {
            return $pointer === null ? $this->transfer->view('xml') : null;
        }
        if ($this->transfer->hasView('proto')) {
            return $pointer === null
                ? $this->transfer->view('proto')
                : JsonPointer::resolve((array) $this->transfer->view('proto'), $pointer);
        }

        return null;
    }

    private function statusView(?string $pointer): mixed
    {
        $status = $this->transfer->status();
        if (is_object($status)) {
            $fields = get_object_vars($status);

            return $pointer !== null ? JsonPointer::resolve($fields, $pointer) : $status;
        }

        return $pointer === null ? $status : null;
    }
}
```

- [ ] **Step 6: Wire the evaluator**

Edit `packages/expression/src/ExpressionEvaluator.php` — add imports and rework the `StepRef` request/response branches
plus the new reference branches. Add to the `use` block:

```php
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Expression\Ast\InteractionRef;
use Alama\Arazzo\Expression\Evaluation\Data\ResponseTransferView;
```

Replace the `RequestPart` branch inside `evaluateAst()`:

```php
            if ($part instanceof RequestPart) {
                $req = is_array($stepData['request'] ?? null) ? $stepData['request'] : [];

                return match ($part->httpPart) {
                    'header' => $this->mapOrEmpty($req, 'headers')[$part->headerName] ?? null,
                    'query' => $this->mapOrEmpty($req, 'query')[$part->headerName] ?? null,
                    'path' => $this->mapOrEmpty($req, 'path')[$part->headerName] ?? null,
                    'metadata' => $this->mapOrEmpty($req, 'metadata')[$part->headerName] ?? null,
                    'body' => JsonPointer::resolve(is_array($req['body'] ?? null) ? $req['body'] : [], $part->jsonPointer),
                    default => null,
                };
            }
```

Replace the `ResponsePart` branch inside `evaluateAst()`:

```php
            if ($part instanceof ResponsePart) {
                $res = is_array($stepData['response'] ?? null) ? $stepData['response'] : [];
                $view = ResponseTransferView::from($stepData);

                return match ($part->httpPart) {
                    'status', 'statusCode' => $view !== null
                        ? $view->get('status', null, $part->httpPart === 'status' ? $part->jsonPointer : null)
                        : (static function () use ($res, $part) {
                            $status = $res['statusCode'] ?? $res['status'] ?? null;
                            if ($part->httpPart === 'status' && $part->jsonPointer !== null && is_object($status)) {
                                return JsonPointer::resolve(get_object_vars($status), $part->jsonPointer);
                            }

                            return $status;
                        })(),
                    'header' => $view?->get('header', $part->headerName, null) ?? $this->mapOrEmpty($res, 'headers')[$part->headerName] ?? null,
                    'metadata' => $view?->get('metadata', $part->headerName, null) ?? $this->mapOrEmpty($res, 'metadata')[$part->headerName] ?? null,
                    'trailingMetadata' => $view?->get('trailingMetadata', $part->headerName, null) ?? $this->mapOrEmpty($res, 'trailingMetadata')[$part->headerName] ?? null,
                    'meta' => $view !== null
                        ? $view->get('meta', $part->headerName, null)
                        : (is_array($res['meta'] ?? null) && $part->headerName !== null ? ($res['meta'][$part->headerName] ?? null) : null),
                    'body' => $view !== null
                        ? $view->get('body', null, $part->jsonPointer)
                        : JsonPointer::resolve(is_array($res['body'] ?? null) ? $res['body'] : [], $part->jsonPointer),
                    default => null,
                };
            }
```

Add the `InteractionRef` branch (before the `WorkflowRef` branch):

```php
        if ($ast instanceof InteractionRef) {
            $steps = $context->getWorkflowContext()->getSteps();
            $stepData = $context->getCurrentStepId() !== null ? ($steps[$context->getCurrentStepId()] ?? null) : null;
            $interaction = is_array($stepData) ? ($stepData['interaction'] ?? null) : null;
            $payload = is_array($interaction) ? ($interaction['payload'] ?? null) : null;

            return $ast->jsonPointer !== null && (is_array($payload) || $payload === null)
                ? JsonPointer::resolve(is_array($payload) ? $payload : [], $ast->jsonPointer)
                : $payload;
        }
```

Extend the `SourceRef` branch so whole-source returns the `SourceDescription`:

```php
        if ($ast instanceof SourceRef && $context->getDocument()) {
            foreach ($context->getDocument()->sourceDescriptions as $sourceDesc) {
                if ($sourceDesc->name === $ast->name) {
                    if ($ast->subPath === null) {
                        return $sourceDesc;
                    }
                    if ($ast->subPath === 'url') {
                        return $sourceDesc->url;
                    }
                    if ($ast->subPath === 'type') {
                        return $sourceDesc->type->value;
                    }
                }
            }

            return null;
        }
```

- [ ] **Step 7: Root the selector evaluator on the transfer view**

Edit `packages/expression/src/SelectorEvaluator.php` — add the import and replace the default-root block inside
`evaluate()`:

```php
use Alama\Arazzo\Expression\Evaluation\Data\ResponseTransferView;
```

```php
        } else {
            $steps = $wf->getSteps();
            $stepData = $steps[$stepId] ?? null;
            $view = ResponseTransferView::from($stepData);
            if ($view !== null) {
                $root = $view->get('body', null, null);
            } else {
                $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
                $body = is_array($response) ? ($response['body'] ?? []) : [];

                $root = is_array($body) ? $body : [];
            }
        }
```

- [ ] **Step 8: Root the criteria evaluator on the transfer view**

Edit `packages/expression/src/Evaluation/CriteriaEvaluator.php`:

1. Add the import and a `responseRoot()` helper:

```php
use Alama\Arazzo\Expression\Evaluation\Data\ResponseTransferView;
```

```php
    private function responseRoot(WorkflowContextInterface $context, string $stepId): mixed
    {
        $steps = $context->getSteps();
        $stepData = $steps[$stepId] ?? null;
        $view = ResponseTransferView::from($stepData);
        if ($view !== null) {
            return $view->get('body', null, null);
        }

        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
        $body = is_array($response) ? ($response['body'] ?? []) : [];

        return is_array($body) ? $body : [];
    }
```

2. Rewrite `evaluateSuccessCriteria()`'s default branch to read the transfer status:

```php
        if ($this->hasOperationTarget($step) && $step->io->successCriteria === []) {
            $steps = $context->getSteps();
            $stepData = $steps[$step->stepId] ?? null;
            $view = ResponseTransferView::from($stepData);
            if ($view !== null) {
                // Structured RPC status (statusCode() null) already failed the
                // step at the transport layer — default success is fine there.
                return self::isSuccessStatusCode($view->statusCode() ?? 200);
            }
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

            return self::isSuccessStatusCode(is_array($response) ? ($response['statusCode'] ?? null) : null);
        }
```

3. In `evaluateCriteria()`, compute the root once and pass it to the jsonpath/xpath branches (replace the
   `$responseBody` computation):

```php
        $responseRoot = $this->responseRoot($context, $step->stepId);

        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            // Evaluation errors inside each branch fail the criterion deterministically.
            $passed = match ($type) {
                CriterionType::Simple => $this->evaluateSimple($criterion, $context, $step->stepId, $document),
                CriterionType::Regex => $this->evaluateRegex($criterion, $context, $step->stepId, $document),
                CriterionType::JsonPath => $this->evaluateJsonPath($criterion, $responseRoot, $context, $step->stepId, $document),
                CriterionType::XPath => $this->evaluateXPath($criterion, $responseRoot, $context, $step->stepId, $document),
            };
```

4. Update `evaluateJsonPath()`/`evaluateXPath()` to use the passed root in the no-context case:

```php
    private function evaluateJsonPath(SuccessCriterion $criterion, mixed $responseRoot, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
    {
        if ($criterion->context !== null) {
            try {
                $root = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $stepId, $document));
            } catch (\Throwable) {
                // Evaluation errors fail the criterion deterministically.
                return false;
            }
        } else {
            $root = $responseRoot;
        }

        $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($root) ? $root : []);

        return !empty($result);
    }
```

```php
    private function evaluateXPath(SuccessCriterion $criterion, mixed $responseRoot, WorkflowContextInterface $context, string $stepId, ?ArazzoDocument $document): bool
    {
        if ($criterion->context !== null) {
            try {
                $root = $this->evaluator->evaluate(new Expression($criterion->context), new EvaluationContext($context, $stepId, $document));
            } catch (\Throwable) {
                return false;
            }
        } else {
            $root = $responseRoot;
        }
```

5. Extend `hasOperationTarget()` so RPC/GraphQL operation steps get default success:

```php
    private function hasOperationTarget(Step $step): bool
    {
        // RPC/GraphQL steps target operations too (A7 target axis); interaction
        // steps do not — they pass via empty criteria in evaluateCriteria().
        return $step->target->operationId !== null
            || $step->target->operationPath !== null
            || $step->target->rpcMethod !== null
            || $step->target->graphqlOperation !== null;
    }
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `composer run test-expression` (repo root)

Expected: PASS — the new engine resolution tests plus the full pre-existing suite (all legacy raw-shape paths still
work).

- [ ] **Step 10: Run static analysis**

Run: `composer run analyse-expression` (repo root)

Expected: PASS (0 errors). If PHPStan complains about `$result` from the anonymous-class closure typing in
`ExpressionEvaluator`, adjust the closure signature/annotations per its message.

- [ ] **Step 11: Commit**

```bash
git add packages/expression/src/Evaluation/Data/ResponseTransferView.php packages/expression/src/ExpressionEvaluator.php packages/expression/src/SelectorEvaluator.php packages/expression/src/Evaluation/CriteriaEvaluator.php packages/expression/tests/Evaluation/ResponseTransferViewTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php
git commit -m "feat(expression): resolve expressions against ResponseTransfer views"
```

---

### Task B3: PayloadReplacer resolver registry

Point-replacement goes through the `ReplacementTargetResolverInterface` registry (spec B3, port lines 214-218), seeded
with the core json-pointer resolver. `payload.targetSelectorType` maps onto SPI target types (
`jsonpointer → json-pointer`, `xpath`, `proto-field`); `jsonpath` stays in-core until Phase C1.

**Files:**

- Create: `packages/expression/src/Evaluation/ReplacementTargetResolverRegistry.php`
- Create: `packages/expression/src/Evaluation/JsonPointerReplacementTargetResolver.php`
- Modify: `packages/expression/src/Evaluation/PayloadReplacer.php`
- Modify: `packages/expression/src/ExpressionEngine.php`
- Test: create `packages/expression/tests/Evaluation/ReplacementTargetResolverRegistryTest.php`
- Test: `packages/expression/tests/ExpressionEngineCapabilitiesTest.php`

**Interfaces:**

- Consumes: `ReplacementTargetResolverInterface` (`Alama\Arazzo\Contracts\Interfaces`), `PayloadReplacement`, `Step`,
  `WorkflowContext`, `JsonPathEvaluator`, `DomXpathEvaluator`.
- Produces: `ReplacementTargetResolverRegistry` (priority-ordered `find(string): ?ReplacementTargetResolverInterface`);
  `JsonPointerReplacementTargetResolver` (`name() = 'arazzo.json-pointer'`, `priority() = 0`,
  `supports('json-pointer')`); `PayloadReplacer::apply(..., ?ReplacementTargetResolverRegistry $registry = null)`;
  `ExpressionEngine` constructor wiring.

- [ ] **Step 1: Write the failing registry test**

Create `packages/expression/tests/Evaluation/ReplacementTargetResolverRegistryTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;
use Alama\Arazzo\Expression\Evaluation\JsonPointerReplacementTargetResolver;
use Alama\Arazzo\Expression\Evaluation\ReplacementTargetResolverRegistry;

it('finds the highest-priority resolver that supports a target type', function (): void {
    $low = new class implements ReplacementTargetResolverInterface {
        public function name(): string
        {
            return 'low';
        }

        public function priority(): int
        {
            return 5;
        }

        public function supports(string $targetType): bool
        {
            return $targetType === 'json-pointer';
        }

        public function resolve(mixed $container, string $target, mixed $value): mixed
        {
            return ['from' => 'low'];
        }
    };
    $high = new class implements ReplacementTargetResolverInterface {
        public function name(): string
        {
            return 'high';
        }

        public function priority(): int
        {
            return 10;
        }

        public function supports(string $targetType): bool
        {
            return $targetType === 'json-pointer';
        }

        public function resolve(mixed $container, string $target, mixed $value): mixed
        {
            return ['from' => 'high'];
        }
    };

    $registry = new ReplacementTargetResolverRegistry($low, $high);
    expect($registry->find('json-pointer'))->toBe($high);
});

it('returns null when no resolver supports the target type', function (): void {
    $registry = new ReplacementTargetResolverRegistry(new JsonPointerReplacementTargetResolver());
    expect($registry->find('proto-field'))->toBeNull();
});

it('ships a core json-pointer resolver', function (): void {
    $resolver = new JsonPointerReplacementTargetResolver();
    expect($resolver->name())->toBe('arazzo.json-pointer')
        ->and($resolver->priority())->toBe(0)
        ->and($resolver->supports('json-pointer'))->toBeTrue();

    expect($resolver->resolve(['a' => ['b' => 'old']], '/a/b', 'set'))->toBe(['a' => ['b' => 'set']]);
});
```

- [ ] **Step 2: Run the registry test to verify it fails**

Run: `vendor/bin/pest packages/expression/tests --filter ReplacementTargetResolverRegistryTest` (repo root)

Expected: FAIL with "Class ReplacementTargetResolverRegistry not found".

- [ ] **Step 3: Write the failing engine replacement tests**

Append to `packages/expression/tests/ExpressionEngineCapabilitiesTest.php` (import to add:
`Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface`):

```php
it('routes pointer replacements through the seeded json-pointer resolver', function (): void {
    $engine = new ExpressionEngine();
    $step = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('/a/b', 'set')]));

    expect($engine->replacePayload($step, ['a' => ['b' => 'old']]))->toBe(['a' => ['b' => 'set']]);
});

it('routes xpath selector targets through a registered resolver', function (): void {
    $xpathResolver = new class implements ReplacementTargetResolverInterface {
        public function name(): string
        {
            return 'test-xpath';
        }

        public function priority(): int
        {
            return 0;
        }

        public function supports(string $targetType): bool
        {
            return $targetType === 'xpath';
        }

        public function resolve(mixed $container, string $target, mixed $value): mixed
        {
            if (is_array($container)) {
                $container['patched'] = $value;
            }

            return $container;
        }
    };
    $engine = new ExpressionEngine(replacementTargets: new ReplacementTargetResolverRegistry($xpathResolver));
    $step = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('/root/name', 'Ada', 'xpath')]));

    expect($engine->replacePayload($step, ['user' => ['name' => 'old']], null, new WorkflowContext('def_1')))
        ->toBe(['user' => ['name' => 'old'], 'patched' => 'Ada']);
});
```

- [ ] **Step 4: Run the engine tests to verify they fail**

Run: `vendor/bin/pest packages/expression/tests --filter "seeded json-pointer|registered resolver"` (repo root)

Expected: FAIL — `ExpressionEngine` has no `replacementTargets` parameter and the pointer replacement still works
inline (the first test may pass until the constructor is changed; the xpath-resolver test fails with "Unknown named
parameter $replacementTargets").

- [ ] **Step 5: Implement the registry + core resolver**

Create `packages/expression/src/Evaluation/ReplacementTargetResolverRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;

/**
 * Priority-ordered (highest `priority()` wins) registry resolving a
 * replacement target type to a {@see ReplacementTargetResolverInterface}(B3).
 *
 * Evaluation device side after the Phase C split (D11).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ReplacementTargetResolverRegistry
{
    /** @var list<ReplacementTargetResolverInterface> */
    private array $resolvers;

    public function __construct(ReplacementTargetResolverInterface ...$resolvers)
    {
        $this->resolvers = $resolvers;
        usort($this->resolvers, static fn (ReplacementTargetResolverInterface $a, ReplacementTargetResolverInterface $b): int => $b->priority() <=> $a->priority());
    }

    public function find(string $targetType): ?ReplacementTargetResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($targetType)) {
                return $resolver;
            }
        }

        return null;
    }
}
```

Create `packages/expression/src/Evaluation/JsonPointerReplacementTargetResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;

/**
 * Core JSON-pointer replacement target (B3); the seeded default shared by
 * every protocol executor. Arrays are copy-on-write, so the returned
 * container is a modified copy of the input body.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class JsonPointerReplacementTargetResolver implements ReplacementTargetResolverInterface
{
    public function name(): string
    {
        return 'arazzo.json-pointer';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(string $targetType): bool
    {
        return $targetType === 'json-pointer';
    }

    public function resolve(mixed $container, string $target, mixed $value): mixed
    {
        if (!is_array($container)) {
            return $container;
        }

        $segments = explode('/', ltrim($target, '/'));
        $current = &$container;

        foreach ($segments as $i => $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if ($i === count($segments) - 1) {
                $current[$segment] = $value;

                return $container;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        return $container;
    }
}
```

- [ ] **Step 6: Rewire PayloadReplacer through the registry**

Replace `packages/expression/src/Evaluation/PayloadReplacer.php` with:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\JsonPathEvaluator;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;

/**
 * Applies a step's payload replacements to an array-shaped body.
 *
 * Two target forms (Arazzo 1.1 Payload Replacement Object):
 * - JSON Pointer targets ("/a/b") write into the body directly through the
 *   registry's `json-pointer` resolver (B3; core default seeded).
 * - Selector targets declared via targetSelectorType (jsonpointer/xpath/
 *   proto-field) route through registered `ReplacementTargetResolverInterface`
 *   resolvers; `jsonpath` stays in-core until the Phase C1 plugin split.
 *   XPath keeps a legacy in-core fallback until the SOAP slice (F2) ships.
 *
 * Shared by every protocol executor so replacement semantics stay identical
 * across HTTP, AsyncAPI and sub-workflow steps.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class PayloadReplacer
{
    /**
     * @param  array<array-key, mixed>  $body
     * @param  callable(PayloadReplacement): mixed|null  $resolveValue  invoked for each replacement (Expression evaluation etc.)
     * @return array<array-key, mixed>
     */
    public static function apply(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null, ?ReplacementTargetResolverRegistry $registry = null): array
    {
        $requestBody = $step->io->requestBody;
        if ($requestBody === null || $requestBody->replacements === []) {
            return $body;
        }

        foreach ($requestBody->replacements as $replacement) {
            $value = $resolveValue !== null ? $resolveValue($replacement) : $replacement->value;

            if (str_starts_with($replacement->target, '/')) {
                $body = self::resolvePointerTarget($body, $replacement->target, $value, $registry);

                continue;
            }

            if ($context !== null && self::isSelectorTarget($replacement)) {
                self::applySelectorTarget($body, $replacement, $context, $resolveValue, $registry);
            }
        }

        return $body;
    }

    private static function isSelectorTarget(PayloadReplacement $replacement): bool
    {
        return in_array(self::selectorType($replacement), ['jsonpointer', 'jsonpath', 'xpath', 'proto-field'], true);
    }

    /**
     * @return 'jsonpointer'|'jsonpath'|'xpath'|'proto-field'|null
     */
    private static function selectorType(PayloadReplacement $replacement): ?string
    {
        if (is_array($replacement->targetSelectorType)) {
            $type = $replacement->targetSelectorType['type'] ?? null;

            return is_string($type) && in_array($type, ['jsonpointer', 'jsonpath', 'xpath', 'proto-field'], true) ? $type : null;
        }

        $type = $replacement->targetSelectorType;

        return is_string($type) && in_array($type, ['jsonpointer', 'jsonpath', 'xpath', 'proto-field'], true) ? $type : null;
    }

    /**
     * Overwrites scalar leaves matched by the selector expression with the
     * replacement value. Best-effort mapping of JSONPath dot notation onto
     * nested keys; unmatched expressions are ignored silently.
     *
     * @param  array<array-key, mixed>  $body
     */
    private static function applySelectorTarget(
        array &$body,
        PayloadReplacement $replacement,
        WorkflowContext $context,
        ?callable $resolveValue,
        ?ReplacementTargetResolverRegistry $registry,
    ): void {
        $value = $resolveValue !== null ? $resolveValue($replacement) : $replacement->value;
        $type = self::selectorType($replacement);

        if ($type === 'jsonpointer') {
            $body = self::resolvePointerTarget($body, $replacement->target, $value, $registry);

            return;
        }

        if ($type === 'xpath') {
            $resolver = $registry?->find('xpath');
            if ($resolver !== null) {
                $result = $resolver->resolve($body, $replacement->target, $value);
                if (is_array($result)) {
                    $body = $result;
                }

                return;
            }
            // Legacy in-core fallback (DOM query + best-effort leaf overwrite);
            // replaced by the SOAP slice's xpath resolver in F2.
            $found = (new DomXpathEvaluator())->query($body, $replacement->target, 'xpath-10');
            if ($found === null || is_array($found)) {
                return;
            }
            self::overwriteLeaf($body, $replacement->target, $value);

            return;
        }

        if ($type === 'proto-field') {
            $resolver = $registry?->find('proto-field');
            if ($resolver !== null) {
                $result = $resolver->resolve($body, $replacement->target, $value);
                if (is_array($result)) {
                    $body = $result;
                }
            }

            return;
        }

        if ($type !== 'jsonpath') {
            return;
        }

        $found = JsonPathEvaluator::evaluate($replacement->target, $body);
        if ($found === null || is_array($found)) {
            return;
        }

        self::overwriteLeaf($body, $replacement->target, $value);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private static function overwriteLeaf(array &$body, string $target, mixed $value): void
    {
        $segments = preg_split('/[.\']/', trim($target, '$.')) ?: [];
        $current = &$body;
        foreach ($segments as $segment) {
            $segment = str_replace(['@.', '[', ']', '"'], '', $segment);
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private static function resolvePointerTarget(array $body, string $target, mixed $value, ?ReplacementTargetResolverRegistry $registry): array
    {
        $resolver = $registry?->find('json-pointer') ?? new JsonPointerReplacementTargetResolver();
        $result = $resolver->resolve($body, $target, $value);

        return is_array($result) ? $result : $body;
    }
}
```

- [ ] **Step 7: Wire the engine**

Edit `packages/expression/src/ExpressionEngine.php` — add the registry to the constructor and thread it into
`replacePayload()`:

```php
use Alama\Arazzo\Expression\Evaluation\JsonPointerReplacementTargetResolver;
use Alama\Arazzo\Expression\Evaluation\ReplacementTargetResolverRegistry;

    public function __construct(
        private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator(),
        private readonly ExpressionParser $parser = new ExpressionParser(),
        private readonly DomXpathEvaluator $xpath = new DomXpathEvaluator(),
        private readonly ReferenceProjector $projector = new ReferenceProjector(),
        ?ReplacementTargetResolverRegistry $replacementTargets = null,
    ) {
        $this->replacementTargets = $replacementTargets ?? new ReplacementTargetResolverRegistry(new JsonPointerReplacementTargetResolver());
    }

    private readonly ReplacementTargetResolverRegistry $replacementTargets;
```

```php
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return PayloadReplacer::apply($step, $body, $resolveValue, $context, $this->replacementTargets);
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `composer run test-expression` (repo root)

Expected: PASS — the registry tests, the seeded pointer replacement, the registered-xpath routing, and the full suite (
the existing "delegates selector targets" capability test keeps its legacy jsonpath semantics).

- [ ] **Step 9: Run static analysis**

Run: `composer run analyse-expression` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 10: Commit**

```bash
git add packages/expression/src/Evaluation/ReplacementTargetResolverRegistry.php packages/expression/src/Evaluation/JsonPointerReplacementTargetResolver.php packages/expression/src/Evaluation/PayloadReplacer.php packages/expression/src/ExpressionEngine.php packages/expression/tests/Evaluation/ReplacementTargetResolverRegistryTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php
git commit -m "feat(expression): route replacement targets through the resolver registry"
```

---

### Task B4: Step-type scope enforcement

Every grammar form is only valid on the step type the Arazzo spec defines it for (D8/B4); out-of-scope expressions are
parse errors. This task adds the scope gate (`StepExpressionScope`), exposes it through a new additive seam (
`parseStepExpression`), and makes `CriteriaEvaluator` fail out-of-scope criteria deterministically.

**Files:**

- Create: `packages/expression/src/StepExpressionScope.php`
- Modify: `packages/expression/src/ExpressionEngineInterface.php`
- Modify: `packages/expression/src/ExpressionEngine.php`
- Modify: `packages/expression/src/Evaluation/CriteriaEvaluator.php`
- Test: create `packages/expression/tests/StepExpressionScopeTest.php`
- Test: `packages/expression/tests/ExpressionEngineCapabilitiesTest.php`

**Interfaces:**

- Consumes: `ExpressionReference`, `ReferenceKind`, `Step` (A7 fields), `ReferenceProjector`, `ExpressionParser`,
  `ExpressionSyntaxException`.
- Produces: `StepExpressionScope::allows(ExpressionReference, Step): bool`;
  `ExpressionEngineInterface::parseStepExpression(string $raw, Step $step): ?ExpressionSyntaxException`.

Scope matrix (encoded in `StepExpressionScope`):

| ReferenceKind                                                                                         | Allowed on                                       |
|-------------------------------------------------------------------------------------------------------|--------------------------------------------------|
| `InteractionPayload`                                                                                  | interaction steps (`step->interaction !== null`) |
| `ResponseStatus`, `Meta`                                                                              | operation steps (`interaction === null`)         |
| `ResponseMetadata`, `RequestMetadata`                                                                 | RPC steps (`rpcMethod` + `rpcProtocol`)          |
| `WholeSource`                                                                                         | GraphQL steps (`graphqlOperation`)               |
| `Step` (part `request`/`response`)                                                                    | non-interaction steps                            |
| everything else (`Input`, `Output`, `Workflow`, `Component`, `Message`, `Self`, `HttpMeta`, `Source`) | any step (BC)                                    |

- [ ] **Step 1: Write the failing scope test**

Create `packages/expression/tests/StepExpressionScopeTest.php` (flat file — it declares the `scopeStep` helper globally,
matching `ExpressionEngineCapabilitiesTest`/`ExpressionEngineTest`):

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\StepExpressionScope;

/**
 * @param  array<string, mixed>  $with
 */
function scopeStep(array $with = []): Step
{
    return new Step(
        stepId: 's1',
        description: null,
        operationId: $with['operationId'] ?? null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        rpcMethod: $with['rpcMethod'] ?? null,
        rpcProtocol: $with['rpcProtocol'] ?? null,
        graphqlOperation: $with['graphqlOperation'] ?? null,
        interaction: $with['interaction'] ?? null,
    );
}

it('allows response status on operation steps', function (): void {
    $ref = new ExpressionReference(ReferenceKind::ResponseStatus, null, 'response', null, 'status', null);
    expect(StepExpressionScope::allows($ref, scopeStep(['operationId' => 'op'])))->toBeTrue();
});

it('allows response and request metadata only on RPC steps', function (): void {
    $rpc = scopeStep(['rpcMethod' => 'GetToken', 'rpcProtocol' => RpcProtocol::Grpc]);
    expect(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::ResponseMetadata), $rpc))->toBeTrue()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::RequestMetadata), $rpc))->toBeTrue();

    $http = scopeStep(['operationId' => 'op']);
    expect(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::ResponseMetadata), $http))->toBeFalse()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::RequestMetadata), $http))->toBeFalse();
});

it('allows interaction payload only on interaction steps and forbids response parts there', function (): void {
    $interaction = scopeStep(['interaction' => new Interaction()]);

    expect(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::InteractionPayload, part: 'payload'), $interaction))->toBeTrue()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::InteractionPayload), scopeStep()))->toBeFalse()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::Step, null, 'response', null, 'statusCode'), $interaction))->toBeFalse()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::Step, null, 'request', null, 'body'), $interaction))->toBeFalse();
});

it('allows the whole-source reference only on GraphQL steps', function (): void {
    $gql = scopeStep(['graphqlOperation' => 'query GetToken']);
    expect(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::WholeSource, 'schema'), $gql))->toBeTrue()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::WholeSource, 'schema'), scopeStep()))->toBeFalse();
});

it('treats $response.meta as the operation-step escape hatch not available on interactions', function (): void {
    expect(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::Meta, name: 'soap.faultcode'), scopeStep(['operationId' => 'op'])))->toBeTrue()
        ->and(StepExpressionScope::allows(new ExpressionReference(ReferenceKind::Meta, name: 'soap.faultcode'), scopeStep(['interaction' => new Interaction()])))->toBeFalse();
});

it('exposes step-scoped parsing through the engine seam', function (): void {
    $engine = new ExpressionEngine();
    $rpc = scopeStep(['rpcMethod' => 'GetToken', 'rpcProtocol' => RpcProtocol::Grpc]);

    expect($engine->parseStepExpression('{$response.metadata.k}', $rpc))->toBeNull()
        ->and($engine->parseStepExpression('{$response.metadata.k}', scopeStep(['operationId' => 'op'])))->toBeInstanceOf(ExpressionSyntaxException::class)
        ->and($engine->parseStepExpression('{$response.meta.soap.faultcode}', scopeStep(['interaction' => new Interaction()])))->toBeInstanceOf(ExpressionSyntaxException::class);
});

it('leaves plain step references in scope on non-interaction steps', function (): void {
    $ref = new ExpressionReference(ReferenceKind::Step, null, 'response', null, 'statusCode');
    expect(StepExpressionScope::allows($ref, scopeStep(['operationId' => 'op'])))->toBeTrue();
});
```

- [ ] **Step 2: Run the scope test to verify it fails**

Run: `vendor/bin/pest packages/expression/tests --filter StepExpressionScopeTest` (repo root)

Expected: FAIL with "Class StepExpressionScope not found" and "Call to undefined method parseStepExpression".

- [ ] **Step 3: Write the failing criteria gate test**

Append to `packages/expression/tests/ExpressionEngineCapabilitiesTest.php` (import to add:
`Alama\Arazzo\Contracts\Spec\Interaction`):

```php
it('fails out-of-scope criteria deterministically (B4)', function (): void {
    $engine = new ExpressionEngine();
    $interactionStep = new Step(
        stepId: 's1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion('{$response.statusCode}', 'x', CriterionType::Regex)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        interaction: new Interaction(),
    );

    expect($engine->evaluateSuccessCriteria($interactionStep, new WorkflowContext('def_1')))->toBeFalse();
});

it('evaluates step-scoped criteria against the transfer view (B2+B4)', function (): void {
    $engine = new ExpressionEngine();
    $step = new Step(
        stepId: 's1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '{$response.status#/code} == 4', CriterionType::Simple)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        rpcMethod: 'GetToken',
        rpcProtocol: RpcProtocol::Grpc,
    );

    $transfer = new ResponseTransfer(status: (object) ['code' => 4, 'message' => 'DEADLINE_EXCEEDED'], headers: [], rawBody: '');
    $context = (new WorkflowContext('def_1'))->withStepResponse('s1', ['transfer' => $transfer]);

    expect($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
});
```

- [ ] **Step 4: Run the criteria gate tests to verify they fail**

Run: `/vendor/bin/pest packages/expression/tests --filter "out-of-scope criteria|step-scoped criteria"` (repo root —
note the leading slash is a typo to avoid; run
`vendor/bin/pest packages/expression/tests --filter "out-of-scope criteria|step-scoped criteria"`)

Expected: FAIL — without the gate the interaction step's regex criterion evaluates its `{$response.statusCode}` context
to `null` and fails anyway, but the RPC simple-criterion test passes only once B2/B4 both land; the gate is what makes
the interaction case deterministic.

- [ ] **Step 5: Implement the scope gate**

Create `packages/expression/src/StepExpressionScope.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * Step-type scope gate for the spec-mandated grammar (D8, B4).
 *
 * Every expression form is only valid on the step type the Arazzo spec
 * defines it for; unknown-for-type expressions are parse errors, surfaced by
 * {@see ExpressionEngineInterface::parseStepExpression()} and applied by the
 * criteria evaluator as a deterministic fail. The $response.meta.* bag stays
 * the escape hatch for operation-step extras the spec does not define.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepExpressionScope
{
    public static function allows(ExpressionReference $ref, Step $step): bool
    {
        return match ($ref->kind) {
            ReferenceKind::InteractionPayload => $step->target->interaction !== null,
            ReferenceKind::ResponseStatus,
            ReferenceKind::Meta => $step->target->interaction === null,
            ReferenceKind::ResponseMetadata,
            ReferenceKind::RequestMetadata => $step->target->rpcMethod !== null && $step->target->rpcProtocol !== null,
            ReferenceKind::WholeSource => $step->target->graphqlOperation !== null,
            ReferenceKind::Step => self::stepPartAllowed($ref, $step),
            default => true,
        };
    }

    private static function stepPartAllowed(ExpressionReference $ref, Step $step): bool
    {
        if ($step->target->interaction === null) {
            return true;
        }

        return $ref->part !== 'request' && $ref->part !== 'response';
    }
}
```

- [ ] **Step 6: Add the engine seam**

Edit `packages/expression/src/ExpressionEngineInterface.php` — append after `expressionReferences()`:

```php
    /**
     * Parse an expression string scoped to a step type (D8/B4).
     *
     * Returns the syntax error when the expression does not parse, or when it
     * is a well-formed form that is not valid for the given step type.
     */
    public function parseStepExpression(string $raw, Step $step): ?ExpressionSyntaxException;
```

Edit `packages/expression/src/ExpressionEngine.php` — implement it next to `parseExpression()`:

```php
    public function parseStepExpression(string $raw, Step $step): ?ExpressionSyntaxException
    {
        $result = $this->parser->parseOrError($raw);
        if ($result instanceof ExpressionSyntaxException) {
            return $result;
        }

        $ref = $this->projector->project($result);

        return StepExpressionScope::allows($ref, $step)
            ? null
            : new ExpressionSyntaxException(
                "Expression '{$raw}' is not valid for step '{$step->stepId}' (kind {$ref->kind->name})",
                $raw, -1, 'workflows/steps/'.$step->stepId, 'expr.syntax',
            );
    }
```

- [ ] **Step 7: Gate the criteria evaluator**

Edit `packages/expression/src/Evaluation/CriteriaEvaluator.php`:

1. Add imports and a lazily-initialized projector (constructor param appended after `$xpathEvaluator`):

```php
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\ReferenceProjector;
use Alama\Arazzo\Expression\StepExpressionScope;
```

```php
    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?XpathEvaluator $xpathEvaluator = null,
        ?ReferenceProjector $projector = null,
    ) {
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($evaluator);
        $this->xpathEvaluator = $xpathEvaluator;
        $this->projector = $projector;
    }

    private ?ReferenceProjector $projector;
```

2. In `evaluateCriteria()`, run the scope gate before each criterion branch:

```php
        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            // Out-of-scope contexts (wrong step type) fail deterministically.
            if ($criterion->context !== null && !$this->inScope($criterion->context, $step)) {
                return false;
            }

            // Evaluation errors inside each branch fail the criterion deterministically.
            $passed = match ($type) {
```

3. Add the `inScope()` helper:

```php
    private function inScope(string $context, Step $step): bool
    {
        $ast = (new ExpressionParser())->parseOrError($context);
        if ($ast instanceof ExpressionSyntaxException) {
            return false;
        }

        $ref = ($this->projector ??= new ReferenceProjector())->project($ast);

        return StepExpressionScope::allows($ref, $step);
    }
```

Note: simple-criterion *conditions* (interpolated strings) are intentionally not gated — an out-of-scope reference
inside a condition resolves to `null` and the comparison fails closed.

- [ ] **Step 8: Run tests to verify they pass**

Run: `composer run test-expression` (repo root)

Expected: PASS — scope unit tests, engine seam, deterministic out-of-scope fail, in-scope RPC criterion against the
transfer view, and the full suite.

- [ ] **Step 9: Run static analysis**

Run: `composer run analyse-expression` (repo root)

Expected: PASS (0 errors).

- [ ] **Step 10: Commit**

```bash
git add packages/expression/src/StepExpressionScope.php packages/expression/src/ExpressionEngineInterface.php packages/expression/src/ExpressionEngine.php packages/expression/src/Evaluation/CriteriaEvaluator.php packages/expression/tests/StepExpressionScopeTest.php packages/expression/tests/ExpressionEngineCapabilitiesTest.php
git commit -m "feat(expression): enforce step-type scope on expressions and criteria"
```

---

### Task B5: Expression-wide verification + gate

Close out Phase B: full package quality gate and confirm the grammar/resolution matrix holds together (spec "expression
axis" + D8).

**Files:**

- None to modify (unless formatting requires).

**Interfaces:**

- Consumes: all tasks B1–B4.

- [ ] **Step 1: Run the full expression test suite**

Run: `composer run test-expression` (repo root)

Expected: PASS (all tests). Verify the projection `with()` table covers the grammar matrix: status (implicit + step +
pointer), metadata (implicit + step), trailing metadata, request metadata, meta dotted key, interaction payload pointer,
whole source.

- [ ] **Step 2: Run static analysis**

Run: `composer run analyse-expression` (repo root)

Expected: PASS (0 errors, level max).

- [ ] **Step 3: Run the formatter check**

Run: `composer run format` or `vendor/bin/pint --test` (repo root)

Expected: PASS (no style violations). If violations exist, run `vendor/bin/pint` and re-run Step 1.

- [ ] **Step 4: Run the repo-wide gate (if the worker supports it)**

Run: `make verify` (repo root)

Expected: PASS — confirms the additive `ReferenceKind`/grammar changes do not break `document`/`runner`/`core` consumers
of `ExpressionEngineInterface` (only `ExpressionEngine` implements it in-repo; B4 added one seam method to the
interface).

- [ ] **Step 5: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]`.

- [ ] **Step 6: Record completion in the spec**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the "Phase B — Expression:
spec-mandated grammar + transfer-view resolution" heading, and add a status line under it:

```markdown
Phase B status: ✅ Implemented 2026-09-08 — see `plans/2026-09-08-phase-b-expression-grammar.md`.
```

- [ ] **Step 7: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase B expression grammar complete"
```

---

## Related issues

### #16 — XML payload support + XPath targetSelectorType (P1-6)

- **Status**: Open
- **Folded into**: Phase B1 + F2
- **Issue summary**: Add first-class XML payload support and XPath `targetSelectorType` to the Runner. Tasks:
  `XmlBodyParser` for XML content-type detection and parsing, `RequestCompiler` XML routing, `Selector` namespace
  support, `SelectorEvaluator` namespace passing.
- **Investigation findings**:
    - The XPath expression forms in B1 (`$response.status` with XPath selectors) are part of the grammar layer. The
      concrete XML parsing (`XmlBodyParser`) is an implementation detail that lives in the protocol packages (F2 for
      SOAP, F1 for HTTP XML responses).
    - B1 adds the grammar and `ReferenceKind` cases; the XML parsing infrastructure is a separate concern that lands in
      Phase F.
    - **Recommendation**: Land B1 (grammar) first. #16's `XmlBodyParser` and `RequestCompiler` XML routing land in F1 (
      HTTP) and F2 (SOAP) as part of the protocol slice extraction.
    - **Overlap with F2**: #16's WSDL reuse of `XmlBodyParser` is exactly the F2 SOAP slice pattern. No conflict — #16's
      concrete implementation work is absorbed by the protocol packages.

## Dependencies

- Phase A (contracts ports) — `ResponseTransfer`, `ReplacementTargetResolverInterface`, and the A7 `Step` fields (
  `rpcMethod`, `rpcProtocol`, `graphqlOperation`, `interaction`). This plan's code compiles only after A lands.
- Phase C (expression split) — optional to land first, but the plan is written against the pre-split single
  `packages/expression` package and holds either way (D11: FQCNs unchanged).

## Sequencing

- Phase A must land before Phase B (contract types used across every task).
- Phase C can land before or after B; if C has landed, the grammar side (B1) lives in `arazzo-expression` and the
  evaluation side (B2–B4) in `arazzo-evaluation` — the same files, relocated.
- Phase B must land before Phase D (grammar cases feed validation rules).
