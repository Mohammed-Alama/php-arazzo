# Design — Document Package Split (Fowler / RCM Boundary)

Date: 2026-09-26
Status: approved section-by-section; implementation not yet planned
Issue: to be created during `writing-plans`
Related: `2026-09-08-plugin-stack-oms-multiprotocol-design.md`,
`2026-09-23-expression-evaluation-package-separation-design.md`,
`2026-09-07-migrate-cli-laravel-public-faces-design.md`

## Context

`packages/document` is a single package carrying five concerns that change for
different reasons and are consumed together only by accident. Fowler's
package-by-feature complaint applies literally: the package's dependency
surface is the *union* of YAML parsing, JSON-Schema validation, HTTP transport,
caching, OpenAPI normalization, and expression evaluation, so every consumer
pays for all of it.

The current concern breakdown:

| Concern | Files | ~LOC | Changes when |
| --- | --- | --- | --- |
| `Validator/` (49 rules) | 65 | 3392 | the Arazzo spec revises |
| `Parser/` | 11 | 1143 | the Arazzo spec revises |
| `Normalizer/` | 9 | 599 | an upstream source format revises |
| `Resolver/` | 12 | 394 | a transport or cache backend changes |
| `Document` + `DocumentInterface` | 2 | 220 | the public face changes |

Measured against the balanced-coupling axes, the package mixes two very
different volatility profiles and one pure coupling smell:

- **Parser + Validator** are high integration strength at zero distance and
  share the Spec AST. They change together on every spec revision, so splitting
  them would raise distance without lowering strength — the quadrant the balance
  rule warns against. They stay together.
- **Normalizer + Resolver** are OpenAPI/transport concerns. They change
  independently of the Arazzo spec.
- **`Document` is a hidden composition root**: it constructs its own Guzzle
  client and wires its own normalizer chain, so a single model class depends
  inward and outward at once.

Three forces shape this design:

1. **The model package must be vendor-free.** The engine hot path stays
   PSR-only. A model package that requires `cebe/php-openapi` cannot be
   depended on by a vendor-free consumer.
2. **Ports and adapters, not a face split.** The dependency rule is fixed by
   where types are *owned*, not by how many interfaces exist. Splitting
   `DocumentInterface` would damage consumers without improving the boundary.
3. **The split lands before the Phase D rewrite.** Phase D
   (`2026-09-08-phase-d-document-normalizer.md`, 2375 lines, unimplemented)
   must be written against the final boundary, not against a boundary that
   Phase D then has to move.

## Evidence

Every claim below was read out of the tree, not inferred.

### The load-bearing defect: cebe leaks through the public face

`ResolvedOperation` is returned by `DocumentInterface::resolveOperation()` and
imported by `runner` in four places. Its public surface is:

```php
public readonly SourceDescription $source,
public readonly NormalizedOpenApiOperation $normalized,  // cebe-free
public readonly OpenApi $openApi,                        // cebe\openapi\spec\OpenApi
public readonly array $rawDocument,
public readonly Operation $cebeOperation,                // cebe\openapi\spec\Operation
```

So the model package's public face exposes `cebe/php-openapi` to every
consumer. This single fact explains why `runner` needs a package it barely
uses.

The leak is narrow, and every leaking consumer is already scheduled to move:

| Member | Consumers | Verdict |
| --- | --- | --- |
| `->normalized` | 14 sites, `DefaultOpenApiExecutor` | cebe-free; stays, it is the real contract |
| `->cebeOperation` | 2 sites: `StepOutputExtractor:99`, `ResponseSchemaValidator:91` | both OpenAPI-specific |
| `->openApi` | 1 site: `DefaultOpenApiExecutor` | OpenAPI-specific |
| `->rawDocument` | **nobody** | dead |

`StepOutputExtractor:99` reads `$operation->responses`; `ResponseSchemaValidator:91`
returns the cebe `Operation` for schema validation. Both are OpenAPI-specific
behaviours that Phase E's E3 had scheduled into `alama-request-pipeline` — a
package that must stay vendor-free, with `protocol → pipeline` as the only
allowed direction. **E3 is therefore wrong about these two classes** (§2).

### A cycle in the existing Phase F plan

Phase F task D9/F1.1 moves `ResolvedOperation` into `protocol-http`, preserving
its FQCN for stability. But `DocumentInterface::resolveOperation()` keeps
returning it while `protocol-http` depends on `arazzo-document`. That makes a
model type owned by a package that depends on its own consumer — the dependency
rule inverted, and PHPStan would need `arazzo-document` to scan
`arazzo-protocol-http` to resolve its own public face. **D9 must be rewritten**
(§2).

### Dead public API

`DocumentInterface::resolveSource()` and `detectOpenApiVersion()` are called by
nobody outside `document/src`. They are dead surface on a public face.

### Manifests do not tell the truth

- `guzzlehttp/guzzle` is **not declared** in `packages/document/composer.json`,
  yet `packages/document/src/Document.php:32` imports `GuzzleHttp\Client`. It
  resolves today only by hoisting from `runner`, `laravel`, `core` and `cli`.
- `softcreatr/jsonpath` **is declared** in `packages/document/composer.json`.
  The vendor symbol `Softcreatr\JsonPath\JsonPath` appears nowhere in
  `document/src` or `document/tests`, so the dependency is droppable — but the
  removal must be gated on a grep for the **`Softcreatr` symbol specifically**,
  not the string `jsonpath`. The tree is full of `jsonpath` *selector-type enum
  values* (`SelectorTypeSupportedRule.php`, `Parser.php`, both bundled JSON
  schemas, several tests) that have nothing to do with this library, and
  grepping `jsonpath` would wrongly conclude the dependency is load-bearing.
  The `phpstan-baseline.neon` hit is an unrelated WordPress `jsonpath` array
  key.

### The face cannot be usefully split

Runner's actual calls on `DocumentInterface`: `resolveOperation()` ×5,
`preflight()` ×3, `load()` ×3, `preflightInputs()` ×2. `load()` is I/O and
`resolveOperation()` is OpenAPI-specific, yet both are load-bearing for the
engine. Laravel calls only `parse()` and `validate()`. Cli calls `parse()`,
`validate()` and `load()`. Splitting the face would force every consumer to
take two injected ports to remove a distinction none of them act on.

## Decisions

| Decision | Choice | Rejected alternatives |
| --- | --- | --- |
| Ordering | Split first, then rewrite Phase D | rewrite D against today's boundary and split after |
| Renames | Clean FQCN rename, breaking changes accepted | FQCN-stability shims and deprecations |
| Boundary | Two packages, model made pure | three packages (model/validator/source); no split, purify in place |
| Source package name | `alama/arazzo-sources`, namespace `Alama\Arazzo\Sources\` | `arazzo-document-io`, `arazzo-document-adapters`, `arazzo-source-resolution` |
| Face | `DocumentInterface` kept whole, as a port in the model package | split by model/I/O; keep face with impl |
| cebe wrapper home | `Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle` | `Alama\Arazzo\Protocol\Http\Normalizer` (blocks the split on Phase F) |

## Architecture

Dependency direction, outer to inner:

```
laravel, cli                          ← composition roots
     │
     ├── arazzo-sources   resolver, fetchers, cache, OpenAPI normalizers, cebe, Guzzle
     │        │
     │        ▼
     └── arazzo-document  Parser · Spec AST · Validator(49 rules) · DocumentInterface
              │           · ResolvedOperation · NormalizedOpenApiOperation
              │           · ValidationResult · all exceptions
              ▼
          contracts / expression          ← zero vendor below here
```

### `alama/arazzo-document` — the model

Owns `Parser/`, `Validator/`, the Spec AST, `DocumentInterface`, and all
validator exceptions.

The purity argument rests on the fact that `DocumentInterface` resolves
entirely within `contracts` + `document`, with no outward reference. Its return
types split as:

- already inner, in `Alama\Arazzo\Contracts\Spec\`: `ArazzoDocument`,
  `RawDocument`, `SourceDocument`, `SourceDescription`, `Step`
- owned by this package: `ValidationResult`
  (`Alama\Arazzo\Document\Validator\Data\ValidationResult`) and
  `ResolvedOperation`

`ResolvedOperation` is the only one of these that reaches cebe today, which is
why purging it is sufficient to make the model package vendor-free.

Requires: `alama-arazzo-contracts`, `alama-arazzo-expression`,
`justinrainbow/json-schema`, `symfony/yaml`. **No `cebe`, no `psr/*`, no
`guzzlehttp/guzzle`.**

Revises when: the Arazzo spec revises.

### `alama/arazzo-sources` — source resolution

Owns `Resolver/`, `Normalizer/`, the `Document` implementation, the fetchers,
the cebe-touching resolution, and the transport composition root.

Requires: `alama-arazzo-document`, `cebe/php-openapi`, `guzzlehttp/guzzle`,
`psr/http-client`, `psr/http-message`, `psr/simple-cache`.

Revises when: an upstream source format or a transport backend changes.

### Consumer impact

| Consumer | Effect |
| --- | --- |
| `runner` | depends on `arazzo-document` only; drops `cebe`, `symfony/yaml`, `psr/*` from its transitive closure. Needs `arazzo-sources` only for bare-metal wiring. |
| `laravel` | depends on both; the service provider becomes the real composition root |
| `cli` | depends on both; `DocumentLoader` moves to the sources builder |

## The `ResolvedOperation` purge

`ResolvedOperation` becomes a pure model type in `arazzo-document`:

```php
namespace Alama\Arazzo\Document;   // model package root, alongside DocumentInterface

final class ResolvedOperation
{
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
    ) {}
}
```

Both DTOs move to the model package's **root** namespace, not to
`Alama\Arazzo\Document\Normalizer\` — that directory is the one leaving for
`arazzo-sources`. The root already holds `DocumentInterface`, so no new
layer-named sub-namespace is invented.

Dropped: `$openApi`, `$cebeOperation`, `$rawDocument`. Phase D's D3c two-axis
work then adds `sourceType` / `rpcProtocol` on top of this shell. This design
deliberately does **not** pre-empt D3c's shape decision.

The cebe handles move behind a wrapper in `arazzo-sources`:

```php
namespace Alama\Arazzo\Sources\Normalizer;

final class OpenApiOperationHandle
{
    public function __construct(
        public readonly ResolvedOperation $operation,  // the model
        public readonly OpenApi $openApi,               // cebe
        public readonly Operation $cebeOperation,       // cebe
    ) {}
}
```

Putting the wrapper in `arazzo-sources` — already the owner of cebe and OpenAPI
normalization — is what lets the split land as a **final, self-consistent
state** rather than a promise. `arazzo-document` is cebe-free the moment the
split completes, and `arazzo-sources` owns every cebe touchpoint. Phase F only
relocates the handle if protocol-http later needs it.

### Consequences for the existing plans

- **Phase F D9/F1.1 is rewritten.** `ResolvedOperation` is no longer moved into
  `protocol-http`, and no FQCN-stability shim is needed.
- **Phase F F1.2 gains two classes** from E3: `StepOutputExtractor` and
  `ResponseSchemaValidator`, because both are OpenAPI-specific and cannot live
  in vendor-free `arazzo-request-pipeline`.
- **Phase E E3 drops those two** from its migration list.
- **Dead surface is removed:** `resolveSource()` and `detectOpenApiVersion()`
  come off the public face.

## Composition root and manifest hygiene

`Document::__construct` (`packages/document/src/Document.php:62`) takes nullable
`$httpClient`/`$httpFactory` and silently defaults to `new Client()` (:67) and
`new HttpFactory()` (:68), wiring the whole OpenAPI normalizer chain inline.
`new Document()` is used in ~12 places (the Laravel singleton in
`FacadeBindings.php`, plus runner and core tests), so the zero-arg path is
load-bearing.

The governing line: **inline construction of pure model collaborators is fine;
inline construction of transport clients is not.** A `Parser` or a `Validator`
has no vendor behind it, so self-construction is not a violation.
`new GuzzleHttp\Client()` is.

1. `arazzo-document` gains a pure model factory building `Parser` + `Loader` +
   `Validator` + `RuleSet` + `PreflightValidator` + `ExpressionEngine`.
   `Document` receives it.
2. `arazzo-sources` owns the transport graph and the Guzzle/cebe default behind
   one named builder — the single place that knows about Guzzle.
3. `Document`'s constructor takes all collaborators explicitly. The ~12 zero-arg
   call sites move to the builder. This is the Fowler-correct outcome and rides
   along on the rename this design already accepts.
4. `arazzo-sources/composer.json` declares `guzzlehttp/guzzle` and
   `cebe/php-openapi`. `arazzo-document/composer.json` drops `cebe`, `psr/*` and
   `softcreatr/jsonpath`.

The trade-off is accepted knowingly: requiring a builder call is slightly less
convenient than `new Document()`. The alternative is a default graph that
silently pulls Guzzle into anyone who forgets an argument — which is precisely
how `guzzlehttp/guzzle` ended up undeclared today.

## Sequencing

The split runs before Phases D, E and F, because Phase D must be written against
the final boundary. `arazzo-runtime`, `arazzo-engine`, `arazzo-events`,
`arazzo-request-pipeline` and `arazzo-protocol-http` do not exist on disk yet —
`packages/core` is still the monolith — so the split has to interleave with
D/E/F rather than precede them cleanly.

Ordering constraints that fall out:

- The split phase is a green state on its own. It creates `arazzo-sources`,
  moves `Normalizer/` and `Resolver/` plus the `Document` implementation into
  it, renames namespaces, fixes manifests, extracts the builder, drops
  `rawDocument`, and removes the dead face methods. Nothing waits on a later
  phase to be correct.
- The `OpenApiOperationHandle` wrapper must land **in the same phase** as the
  package move, because `arazzo-sources` owns cebe from the moment it exists.
- The cebe-dependent runner classes are not touched by the split; they keep
  working through the handle.
- E3 and F1/F1.2 are amended as described above, and Phase D is rewritten
  against the final boundary.

## Guards

The repo already keeps a Pest `arch()` block per package (`contracts`,
`expression`, `evaluation`, `document`, `runner`, `cli`, `core`), so this
design extends that convention rather than introducing deptrac.

**1. `packages/document/tests/ArchTest.php`** — makes the purity claim
executable:

```php
arch('document model is vendor-free')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('cebe')
    ->not->toUse('GuzzleHttp')
    ->not->toUse('Psr\Http')
    ->not->toUse('Psr\SimpleCache');
```

Guard 1 is confirmed achievable: cebe appears in exactly three files under
`document/src` — `ResolvedOperation.php`, `OpenApiDocumentLoader.php` and
`OpenApiOperationResolver.php` — all three in `Normalizer/`, all three leaving
for `arazzo-sources`. `Parser/` and `Validator/` contain **zero** cebe
references, so the model package is cebe-free the moment the move lands.

**2. New `packages/sources/tests/ArchTest.php`** — sources must not point at
outer layers:

```php
arch('sources does not depend on outer layers')
    ->expect('Alama\Arazzo\Sources')
    ->not->toUse('Alama\Arazzo\Runner')
    ->not->toUse('Alama\Arazzo\Cli')
    ->not->toUse('Alama\Arazzo\Protocol')
    ->not->toUse('Alama\Arazzo\Runtime')
    ->not->toUse('Illuminate');
```

**3. One place knows Guzzle** — a namespace rule, not a grep:

```php
arch('only the sources builder constructs http clients')
    ->expect('Alama\Arazzo\Sources\Document')
    ->not->toUse('GuzzleHttp');
```

**4. `packages/runner/tests/ArchTest.php`** — extend with
`->not->toUse('cebe')`. The F1.2 rule (protocol packages carry zero runner
imports) is already drafted in Phase F.

**5. Manifest truth** — assert that `arazzo-document/composer.json` lists
neither `cebe/php-openapi`, `psr/*` nor `guzzlehttp/guzzle`. This is the guard
that catches today's undeclared-Guzzle bug in mirror image.
`GeneratedDocsSnapshotTest.php` shows the repo already does file-level snapshot
assertions, so this fits the house style.

Housekeeping following the existing `analyse-*` / `test-*` composer script
pattern: `analyse-sources`, `test-sources`, and
`packages/sources/phpstan.neon.dist` mirroring
`packages/document/phpstan.neon.dist`.

**Hard sequencing consequence of guard 1:** `arazzo-document` cannot reference
`Softcreatr\JsonPath` either, so dropping `softcreatr/jsonpath` must happen in
the **same commit** as the split. Otherwise the arch test blocks the split.

## Out of scope

- **D3c's two-axis shape.** Whether `ResolvedOperation` grows `sourceType` /
  `rpcProtocol`, and whether `NormalizedOpenApiOperation` generalizes into a
  protocol-neutral operation model, is decided in Phase D against the final
  boundary. This design leaves the shell in place for that work.
- **Generalizing `NormalizedOpenApiOperation`.** It stays OpenAPI-named and
  cebe-free in the model package.
- **A three-way model/validator/source split.** Rejected: parser and validator
  change together on every spec revision.
- **Any change to `packages/core`'s own decomposition.** That is Phase E's job.

## Consequences summary

- `arazzo-document` goes from 8 vendor requirements to 4, and from being
  unusable by a vendor-free consumer to being exactly that.
- `runner` sheds `cebe/php-openapi`, `symfony/yaml` and `psr/*` from its
  transitive closure.
- A dead dependency (`softcreatr/jsonpath`) and an undeclared one
  (`guzzlehttp/guzzle`) are both resolved.
- Two dead public methods leave the `DocumentInterface` face.
- The cebe leak through the public face is closed and guarded.
- An inverted dependency rule in Phase F's D9 and a misfiled pair of classes in
  Phase E's E3 are corrected before either plan is implemented.
