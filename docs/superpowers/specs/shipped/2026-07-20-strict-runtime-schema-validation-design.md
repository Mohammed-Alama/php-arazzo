# Strict Runtime Schema Validation — Design

Roadmap seed: [docs/superpowers/roadmap/04-strict-runtime-schema-validation.md](../roadmap/04-strict-runtime-schema-validation.md).
Depends on: [01 — Zero-Code Data Pipelining](../roadmap/01-zero-code-data-pipelining.md) (already
landed — `ArazzoExpressionResolver` is the real `compileRequest`/`extractOutputs`/
`evaluateSuccessCriteria` implementation, shared by both the sync (`StepExecutor`) and async
(`StepExecutionWorker`) paths).

> **Addendum (2026-07-20) — 03 merged mid-brainstorm:** [03 — Native Async Control Flow](../roadmap/03-native-async-control-flow.md)
> landed while this spec was being written, and it changed the shape of the sync/async split assumed
> below. It's no longer "one shared resolver behind two execution shells where only one is wired" —
> it introduced `HttpStepExecutor` (`src/Execution/HttpStepExecutor.php`), a **second, independently
> wired** `compileRequest → send → withStepResponse → extractOutputs` flow, live in the service
> provider and driving the async `StepExecutionWorker` via `StepProtocolExecutorInterface[]`. Both
> `StepExecutor` (sync) and `HttpStepExecutor` (async) are real, wired execution paths now. Every
> place below that said the async path "picks up validation for free later" or was "out of scope"
> is superseded — this item hooks `validateResponseSchema()` into **both**.

## Starting point

`ArazzoExpressionResolver` already does best-effort OpenAPI-schema-driven **type casting** —
`castToSchemaType()` for request parameters/body replacements, `castOutputAgainstResponseSchema()`
for extracted outputs. Both deliberately fall back to the raw, uncast value and log a warning on
cast failure (see the 01 design's Error Handling section — "failing loudly here would duplicate
roadmap item 04's job"). That's the gap this item fills: an explicit, fail-fast check of the whole
response body against its declared OpenAPI schema, run before that body is touched by output
extraction or success-criteria evaluation — so a type mismatch produces one clear exception instead
of a confusing failure two or three calls deep in the stack.

## Scope

**In scope:**
- A hand-rolled `SchemaValidator` that checks a decoded JSON value against a `cebe\openapi\spec\Schema`:
  `type`, `required` (object properties), `enum`, `nullable`, `pattern`, `format` (known formats
  only), numeric/length/collection bounds (`minimum`/`maximum`/`exclusiveMinimum`/`exclusiveMaximum`,
  `minLength`/`maxLength`, `minItems`/`maxItems`/`uniqueItems`, `minProperties`/`maxProperties`,
  `multipleOf`), and composition (`allOf`/`oneOf`/`anyOf`) — recursing into `properties`, `items`,
  and each composition branch.
- A dedicated `SchemaValidationException` carrying the step ID, status code, and a structured list
  of violations.
- Wiring: a new `ExpressionResolverInterface::validateResponseSchema()` method, called from both
  `StepExecutor::execute()` (sync path) and `HttpStepExecutor::execute()` (async path), in each
  case between storing the response and extracting outputs.
- Opt-in config: global default off, per-step override via an `x-strict-validation` extension.
- The Arazzo 1.1.0 delta: `SuccessCriterion` gains a `version` field (parsing the `{type, version}`
  object form for `type`), and a new parse-time validation rule rejects `xpath` criteria pinned to
  `xpath-30`/`xpath-31` (no PHP stdlib support — `DOMXPath` is XPath 1.0 only).

**Out of scope (explicitly deferred):**
- Validating request bodies before sending — this item covers *incoming* (response) payloads only,
  per the roadmap wording. Request-side validation would be a separate, later addition if needed.
- `not` (JSON Schema negation) and `discriminator` — not requested, and `discriminator` needs
  polymorphic-schema resolution rules beyond what this item's scope calls for.
- Turning a validation failure into a soft step-failure (routed through `onFailure`/retry) — it's a
  hard exception that propagates out of `StepExecutor::execute()`/`HttpStepExecutor::execute()`
  uncaught, the same way an unresolvable operation already does today.
- Any other change to `StepExecutionWorker`, `StepOutcomeHandler`, or the retry/goto/end decision
  logic 03 built — this item only adds one call to `HttpStepExecutor::execute()`, it doesn't touch
  what happens after that call returns or throws.
- The rest of the 1.1.0 Selector Object (output `selector`/`context` object syntax, `querystring`
  params, `$self`, AsyncAPI `sourceDescriptions`) — only the success-criteria `{type, version}` form
  is in scope here, because it's the specific validation surface the roadmap stub calls out.

## Architecture

### `SchemaValidator` (new — `src/Execution/SchemaValidator.php`)

Static, stateless, matching the existing `TypeCaster`/`JsonPointer` style (no new dependency):

```php
final class SchemaValidator
{
    /** @return list<array{path: string, message: string}> */
    public static function validate(Schema $schema, mixed $value, string $path = ''): array
}
```

Recursion rules:
- **`nullable`**: if `$value === null`, valid iff `$schema->nullable === true` (or `type` is absent);
  otherwise short-circuits with one violation, no further checks at that path.
- **`type`**: checked via the same PHP-type mapping `TypeCaster`/`castToSchemaType` already use
  (`integer` → `is_int`-or-numeric-string rules mirrored from `TypeCaster::asInteger`, etc.) — a
  mismatch is one violation, but recursion into `properties`/`items` still runs on a best-effort
  basis where the shape allows it (e.g. still checks object properties even if `type` itself
  mismatched but the value happens to be an array).
- **`enum`**: value must be `in_array($value, $schema->enum, true)` when `enum` is non-empty.
- **`required`** (object schemas): each name in `$schema->required` must exist as a key in `$value`
  when `$value` is an array; missing keys are one violation each, path-qualified
  (`{$path}/{$name}`).
- **`pattern`** (string schemas): `preg_match('/' . str_replace('/', '\/', $schema->pattern) . '/u',
  $value)` — same delimiter-escaping approach `evaluateSuccessCriteria`'s `Regex` criterion already
  uses, for consistency. No ECMA-262-to-PCRE translation layer; PHP's PCRE is a superset for the
  patterns OpenAPI specs realistically use.
- **`format`** (string schemas): checked against a fixed allowlist of recognized formats —
  `date` (`Y-m-d` via `DateTime::createFromFormat`, rejecting overflow like `2024-02-30`),
  `date-time` (RFC 3339, via `DateTime::createFromFormat(DateTime::RFC3339_EXTENDED)` falling back to
  non-fractional `RFC3339`), `email` (`FILTER_VALIDATE_EMAIL`), `uuid` (regex), `uri`
  (`FILTER_VALIDATE_URL`), `ipv4`/`ipv6` (`FILTER_VALIDATE_IP` with the matching flag). **Any format
  not in this list is silently ignored** (no violation either way) — per the JSON Schema spec,
  `format` is an annotation that unrecognized values MAY be skipped, not a MUST-validate keyword;
  this avoids false failures on formats like `password`/`byte`/`int64` that are hints, not checks.
- **Numeric/length/collection bounds**: `minimum`/`maximum` compared with `<`/`>`; `exclusiveMinimum`/
  `exclusiveMaximum` are **booleans that modify `minimum`/`maximum`** in OAS 3.0 (not standalone
  numeric bounds like later JSON Schema drafts) — `exclusiveMinimum: true` means `$value >
  $schema->minimum` (strict) rather than `>=`, and symmetrically for `exclusiveMaximum`/`maximum`.
  `minLength`/`maxLength` use `mb_strlen`. `minItems`/`maxItems` use `count()`; `uniqueItems` checks
  `count($value) === count(array_unique($value, SORT_REGULAR))`. `minProperties`/`maxProperties`
  use `count()` on object schemas. `multipleOf` checks `fmod($value, $schema->multipleOf) === 0.0`
  (with a small epsilon tolerance for float drift, e.g. `abs(fmod(...)) < 1e-9`).
- **`allOf`**: value must satisfy every subschema — recurse into each and merge (union) all
  violations; failing any one subschema is a violation of the whole.
- **`anyOf`**: value must satisfy at least one subschema — recurse into each; if all fail, emit one
  violation at `$path` (`"value did not match any of N anyOf schemas"`) rather than surfacing every
  branch's internal violations (which would be noise for branches that were never the intended
  match).
- **`oneOf`**: value must satisfy **exactly one** subschema — recurse into each; zero matches is a
  violation (`"matched none of N oneOf schemas"`), and **more than one** match is also a violation
  (`"matched N of M oneOf schemas, expected exactly one"`) — this is `oneOf`'s defining difference
  from `anyOf`.
- **`properties`**: for each key present in both `$value` and `$schema->properties`, recurse with
  `$path` extended by `/{$key}` (resolving `Reference` schemas first, same as
  `ArazzoExpressionResolver` already does elsewhere).
- **`items`** (array schemas): recurse into each element of `$value` with `$path` extended by
  `/{$index}`.

### `SchemaValidationException` (new — `src/Execution/Exceptions/SchemaValidationException.php`)

```php
final class SchemaValidationException extends RuntimeException
{
    /** @param list<array{path: string, message: string}> $violations */
    public function __construct(
        public readonly string $stepId,
        public readonly int $statusCode,
        public readonly array $violations,
    ) {
        parent::__construct(self::formatMessage($stepId, $statusCode, $violations));
    }
}
```

Message format: `Response for step '{stepId}' ({statusCode}) failed schema validation: {path}
{message}; {path} {message}; ...` — every violation on one line, so the exception itself is the
whole diagnosis (no need to go dig through logs).

### `ExpressionResolverInterface` / `ArazzoExpressionResolver`

New interface method:

```php
public function validateResponseSchema(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): void;
```

Implementation reuses operation/response-schema resolution already present in
`castOutputAgainstResponseSchema()` — that method's inline lookup (`OpenApiParser::findOperation`
→ `$operation->responses->getResponse($statusCode)` → `content['application/json']->schema`) is
extracted into a shared private helper, `findResponseSchema(Operation $operation, string
$statusCode): ?Schema`, used by both the existing casting code and the new validator. If no
operation, no matching response, or no JSON schema can be resolved, `validateResponseSchema()` is a
no-op — same "can't validate what isn't declared" fallback the casting code already follows.

When violations are found: throws `SchemaValidationException`. When none: returns normally.

### `Step` DTO

Gains one nullable field:

```php
public ?bool $strictValidation, // parsed from x-strict-validation, null = "use global default"
```

`Parser`'s step-parsing method reads `x-strict-validation` as an optional bool (same
`optionalString`/`optionalBool`-style helper pattern already used for other optional fields),
defaulting to `null` when absent.

### Config

`config/arazzo.php` gains:

```php
'strict_schema_validation' => env('ARAZZO_STRICT_SCHEMA_VALIDATION', false),
```

### 1.1.0 delta: `SuccessCriterion` version field + rejection rule

`SuccessCriterion` gains:

```php
public ?string $version, // e.g. 'rfc9535', 'xpath-30' — only meaningful when $type is set
```

`Parser::parseSuccessCriterion` currently reads `type` as a plain string via
`optionalString($obj, 'type', $ctx)`. It's extended to also accept an object form —
`{type: <string>, version: <string>}` — matching the shape already present in
`tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml`. When `type` is a string, `version`
stays `null`.

New validation rule, `SuccessCriteriaVersionSupportedRule` (registered in `RuleSet` alongside
`StepCriteriaTypeContextRule`):

```php
final class SuccessCriteriaVersionSupportedRule implements Rule
{
    private const UNSUPPORTED = ['xpath' => ['xpath-30', 'xpath-31']];
    // errors when $c->type === CriterionType::XPath && in_array($c->version, self::UNSUPPORTED['xpath'], true)
}
```

Error message names the concrete gap: `"criterion type 'xpath' version 'xpath-30' is not supported
— DOMXPath only implements XPath 1.0 (use 'xpath-10' or omit version)."` This fires at
document-validation time (`Validator`), independent of and prior to any runtime execution — a
document pinning an unsupported version never gets as far as `StepExecutor`.

## Data Flow

`StepExecutor::execute()` gains one line between steps 2 and 3:

```
$request  = $resolver->compileRequest($step, $context, $document)
$context  = $context->withStepRequest($step->stepId, [...])
$response = $httpClient->sendRequest($request)          // existing try/catch → synthetic 500 preserved
$context  = $context->withStepResponse($step->stepId, [...])

if ($this->shouldValidate($step)) {                       // step->strictValidation ?? config default
    $resolver->validateResponseSchema($step, $context, $document);   // throws SchemaValidationException
}

$outputs  = $resolver->extractOutputs($step, $context, $document)
...
$success  = $resolver->evaluateSuccessCriteria($step, $context, $document)
```

`shouldValidate()` is a small private helper: `$step->strictValidation ?? $this->strictValidationDefault`
— a constructor-injected `bool`, matching how `retry_ceiling`/`state_ttl` are already threaded from
config into framework-agnostic `Execution/` classes via the service provider (`(int)
config('arazzo.retry_ceiling', 10)` passed as a constructor arg, not read via a `config()` call
inside `Execution/` code). Both `StepExecutor` and `HttpStepExecutor` gain this constructor
parameter and this helper.

Not run on the synthetic-500 network-failure path — there's no real API response body to check
against a schema in that case, and `evaluateSuccessCriteria` already correctly reports failure for
it.

`HttpStepExecutor::execute()` gains the identical insertion point, between `withStepResponse` and
`extractOutputs`:

```
$contextWithResponse = $context->withStepResponse($step->stepId, [...])

if ($this->shouldValidate($step)) {
    $this->expressionResolver->validateResponseSchema($step, $contextWithResponse, $document);
}

$outputs = $this->expressionResolver->extractOutputs($step, $contextWithResponse, $document)
```

## Error Handling

- **Schema violation (sync path)** — throws `SchemaValidationException` immediately; propagates out
  of `StepExecutor::execute()` uncaught (`WorkflowExecutor::execute()` has no try/catch around the
  step-executor call today, so this is consistent with how an unresolvable-operation
  `RuntimeException` already behaves — no new catch/rethrow plumbing needed).
- **Schema violation (async path)** — throws the same `SchemaValidationException` out of
  `HttpStepExecutor::execute()`, uncaught by this item's code. What happens next (does
  `StepExecutionWorker`'s queue job retry, dead-letter, or fail the execution) is governed entirely
  by 03's existing job-failure handling — not modified here.
- **No resolvable schema** (operation/response/content-type not found) — silent no-op, matching the
  existing casting code's fallback philosophy.
- **Validation disabled** (default) — `validateResponseSchema()` is never called; zero behavior
  change for existing workflows, on either path.

## Testing

- `SchemaValidatorTest.php` (new) — type mismatch, missing required property, enum violation,
  nested object, nested array, nullable accepting/rejecting `null`, unresolvable schema pieces
  (missing `properties`/`items`) treated as "can't check further, not a violation"; `pattern`
  match/mismatch; each recognized `format` (valid + invalid per format) plus an unrecognized format
  passing through unchecked; `minimum`/`maximum` with `exclusiveMinimum`/`exclusiveMaximum` true and
  false; `minLength`/`maxLength`; `minItems`/`maxItems`/`uniqueItems` (with a duplicate-elements
  case); `minProperties`/`maxProperties`; `multipleOf` (including a float case near the epsilon
  tolerance); `allOf` (single failing branch fails the whole); `anyOf` (one passing branch is
  enough); `oneOf` (zero matches, exactly one match, and more-than-one-match all exercised).
- `ArazzoExpressionResolverTest.php` — extended with `validateResponseSchema()` cases: violation
  throws with expected message/violations shape; no-op when schema unresolvable; passes clean
  payload.
- `StepExecutorTest.php` (new or extended) — validation on (global config) + valid/invalid response;
  validation off by default; step-level `x-strict-validation: true` override when global default is
  off; step-level `x-strict-validation: false` override when global default is on.
- `ParserTest`/fixture test — `{type, version}` object form parses into `SuccessCriterion::$version`;
  bare string `type` still parses with `version === null`.
- `SuccessCriteriaVersionSupportedRuleTest.php` (new) — `xpath-30`/`xpath-31` rejected;
  `xpath-10`/`xpath-20`/omitted version accepted; non-`xpath` types with a `version` set are
  ignored by this rule (not this rule's concern).

## Key decisions (for future reference)

- **Response-only, not request-body** — matches the roadmap's literal "incoming API payloads"
  wording; request-side validation is a separate, unrequested feature.
- **Hard exception, not a soft step-failure** — a schema violation is a programming/spec-drift bug,
  not a normal business-logic failure path; it shouldn't be swallowed into `onFailure`/retry.
- **Hand-rolled validator, not a JSON Schema library** — keeps the same "no new dependency, only the
  subset of JSON Schema OpenAPI actually needs" philosophy as `TypeCaster`/`JsonPointer`, and avoids
  a lossy conversion from `cebe/openapi`'s already-`$ref`-resolved `Schema` objects into generic JSON
  Schema arrays just to hand them to a third-party validator.
- **Global default + per-step override**, not a single global switch — lets a workflow author
  enable strict validation broadly while carving out exceptions for a few known-loose endpoints,
  without needing a second document-level mechanism.
- **Version-pin rejection is parse-time, not runtime** — an unsupported `xpath` version is a static
  property of the document, detectable without ever executing a step; failing fast at validation
  time (like `StepCriteriaTypeContextRule` already does for missing `context`) is strictly better
  than discovering it mid-execution.
- **Unrecognized `format` values are ignored, not rejected** — matches the JSON Schema spec's own
  treatment of `format` as an annotation; failing on `password`/`byte`/vendor-specific formats would
  punish specs for using format as a hint, which is legitimate per spec.
- **`exclusiveMinimum`/`exclusiveMaximum` follow OAS 3.0 boolean semantics**, not the later JSON
  Schema draft's standalone-numeric-bound semantics — `cebe/php-openapi` models OAS 3.0, and this
  validator is checking OAS 3.0 `Schema` objects, so following any other semantics would silently
  misvalidate every spec that sets these keywords.
- **`oneOf` enforces exclusivity, `anyOf` doesn't** — the one behavioral difference between the two
  keywords; collapsing them to the same "at least one" check would silently accept a value that
  matches two `oneOf` branches when the spec author meant them to be mutually exclusive.
