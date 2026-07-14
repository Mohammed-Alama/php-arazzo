# Laravel Arazzo — Parser + Validator (v1) Design

**Status**: Draft
**Created**: 2026-07-14
**Package**: `alama/laravel-arazzo`
**Namespace**: `Alama\LaravelArazzo`
**Slice**: Foundation (parser + validator only). Runner, generator, and React Flow UI are deferred to later specs.

---

## 1. Goals & Non-Goals

### Goals

- Load Arazzo 1.0.0 documents from local YAML or JSON files into typed, immutable PHP DTOs.
- Validate documents against the Arazzo 1.0.0 specification and return a structured error list (no exceptions for spec violations).
- Validate runtime-expression syntax (`{$inputs.x}`, `{$steps.s.outputs.o}`, etc.) and resolve every reference against the document's own symbol table.
- Ship as a Laravel package with a Facade, an Artisan command, and a publishable config file. Core stays framework-agnostic.
- Pass `larastan` at strict level so downstream Laravel apps get accurate type information.

### Non-goals (deferred)

- Executing workflows (runner). Deferred to a later spec.
- Generating Arazzo specs from OpenAPI (generator). Deferred.
- React Flow UI. Deferred.
- Resolving `sourceDescriptions` (loading and parsing referenced OpenAPI docs). Parser records the reference; resolution belongs to the runner.
- Loading Arazzo files from URLs / remote sources.
- Property-based / fuzz testing.
- Precise line numbers for errors in JSON input (YAML best-effort only, and only if cheap).

---

## 2. Architecture

Three-stage pipeline, framework-agnostic core plus a thin Laravel layer.

```
┌─────────────────────────── Laravel Layer ──────────────────────────┐
│  LaravelArazzoServiceProvider  •  Facade `Arazzo`                  │
│  Artisan `arazzo:validate`     •  config/arazzo.php                │
└────────────────┬───────────────────────────────────────────────────┘
                 │
┌────────────────▼───────────────── Core (pure PHP) ─────────────────┐
│                                                                    │
│  Loader ──► Parser ──► Validator ──► ValidationResult              │
│   │           │           │                                        │
│   │           │           └─► Rule[]  (visitor per rule)           │
│   │           └─► ArazzoDocument DTO tree (readonly)               │
│   └─► RawDocument { data, path, format }                           │
└────────────────────────────────────────────────────────────────────┘
```

### Boundaries

- **Loader** — filesystem + YAML/JSON decode only. In: filesystem path. Out: `RawDocument`. Throws `LoaderException` on I/O or decode failure (no DTO can be produced, so throwing is the right shape).
- **Parser** — raw array → DTO tree. In: `RawDocument`. Out: `ArazzoDocument`. Throws `ParserException` on structural errors (missing required key, wrong primitive type, invalid enum value). Contains no spec-semantics logic.
- **Validator** — spec conformance. In: `ArazzoDocument`. Out: `ValidationResult`. Never throws for spec violations; collects them into a typed `Error` list. Runs each `Rule` in sequence.
- **Rule** — one spec constraint per class. `check(ArazzoDocument, SymbolTable, ErrorCollector): void`. Pluggable via config (`arazzo.rules.disabled`).

### Public API surface

```php
Arazzo::parse(string $path): ArazzoDocument;              // throws Loader/Parser
Arazzo::validate(string $path): ValidationResult;         // throws Loader/Parser, collects spec errors
Arazzo::assertValid(string $path): ArazzoDocument;        // throws ValidationException on any spec error
```

CLI:

```
php artisan arazzo:validate <file> [--format=human|json] [--fail-on-warning]
```

Exit codes: `0` valid, `1` spec errors, `2` load/parse errors, `3` file not found.

---

## 3. DTO Tree

All DTOs are `final readonly` classes, strict types, no Illuminate types. Collections are typed via PHPDoc `@var list<T>`.

```
ArazzoDocument
├── string arazzo                     // "1.0.0"
├── Info info
│   ├── string title
│   ├── ?string summary
│   ├── ?string description
│   └── string version
├── list<SourceDescription> sourceDescriptions
│   ├── string name                   // unique key
│   ├── string url                    // lazy — never resolved by parser/validator
│   └── SourceType type               // enum: Openapi | Arazzo
├── list<Workflow> workflows
│   ├── string workflowId             // unique across doc
│   ├── ?string summary
│   ├── ?string description
│   ├── ?array inputs                 // JSON Schema, kept as raw array
│   ├── list<string> dependsOn        // workflowIds
│   ├── list<Step> steps
│   │   ├── string stepId             // unique within workflow
│   │   ├── ?string description
│   │   ├── ?string operationId
│   │   ├── ?string operationPath
│   │   ├── ?string workflowId        // nested workflow ref
│   │   ├── list<Parameter> parameters
│   │   ├── ?RequestBody requestBody
│   │   ├── list<SuccessCriterion> successCriteria
│   │   ├── list<SuccessAction|Reusable> onSuccess
│   │   ├── list<FailureAction|Reusable> onFailure
│   │   └── array<string,Expression> outputs
│   ├── list<SuccessAction|Reusable> successActions
│   ├── list<FailureAction|Reusable> failureActions
│   ├── array<string,Expression> outputs
│   └── list<Parameter> parameters
├── Components components
│   ├── array<string,array> inputs             // named JSON Schemas
│   ├── array<string,Parameter> parameters
│   ├── array<string,SuccessAction> successActions
│   └── array<string,FailureAction> failureActions
└── array<string,mixed> specificationExtensions  // x-* keys
```

### Supporting types

- `Expression` — value object wrapping a raw `{$...}` string plus a lazy `ast(): ExpressionAst` accessor.
- `Reusable` — `$ref` to `components.*`: `{ reference: '$components.parameters.foo', value?: mixed }`.
- `Parameter` — `{ name: string, in: ?ParameterIn, value: Expression|scalar }`.
- `RequestBody` — `{ contentType: ?string, payload: Expression|mixed, replacements: list<PayloadReplacement> }`.
- `PayloadReplacement` — `{ target: string /* JSON Pointer */, value: Expression|scalar }`.
- `SuccessCriterion` — `{ context: ?string, condition: string, type: ?CriterionType }`.
- Sum types via abstract base + concrete subclasses:
  - `SuccessAction` → `GotoAction | EndAction`
  - `FailureAction` → `GotoAction | EndAction | RetryAction`
- Enums: `SourceType`, `ParameterIn` (path|query|header|cookie|body), `CriterionType` (simple|regex|jsonpath|xpath), `Format` (yaml|json).

Each top-level DTO lives in its own file under `src/Dto/`. Enums under `src/Dto/Enum/`. Action sum types under `src/Dto/Action/`.

---

## 4. Loader

`src/Loader/Loader.php`:

```php
final class Loader
{
    public function __construct(
        private YamlDecoder $yaml,
        private JsonDecoder $json,
    ) {}

    public function load(string $path): RawDocument
    {
        if (!is_file($path))     throw LoaderException::notFound($path);
        if (!is_readable($path)) throw LoaderException::notReadable($path);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $format = match ($ext) {
            'yaml', 'yml' => Format::Yaml,
            'json'        => Format::Json,
            default       => throw LoaderException::unsupportedExtension($ext),
        };

        $raw = @file_get_contents($path);
        if ($raw === false) throw LoaderException::readFailed($path);

        try {
            $data = $format === Format::Yaml
                ? $this->yaml->decode($raw)
                : $this->json->decode($raw);
        } catch (DecodeException $e) {
            throw LoaderException::decodeFailed($path, $e);
        }

        if (!is_array($data)) throw LoaderException::rootNotObject($path);

        return new RawDocument($data, $path, $format);
    }
}
```

- `YamlDecoder` and `JsonDecoder` are interfaces; default implementations are `SymfonyYamlDecoder` (wraps `symfony/yaml`) and `NativeJsonDecoder` (`json_decode` with `JSON_THROW_ON_ERROR`).
- Loader knows nothing about Arazzo semantics.
- Loader errors are always thrown, never collected.

---

## 5. Parser

`src/Parser/Parser.php`. Recursive descent over the raw array. One method per DTO type. Structural checks only — required keys, primitive types, enum membership. No uniqueness, no reference resolution.

```php
final class Parser
{
    public function parse(RawDocument $raw): ArazzoDocument
    {
        $d = $raw->data;
        $ctx = new ParseContext($raw->path);   // tracks JSON Pointer

        return new ArazzoDocument(
            arazzo:             $this->requireString($d, 'arazzo', $ctx),
            info:               $this->parseInfo($d['info'] ?? null, $ctx->push('info')),
            sourceDescriptions: $this->parseSourceList($d['sourceDescriptions'] ?? [], $ctx->push('sourceDescriptions')),
            workflows:          $this->parseWorkflowList($d['workflows'] ?? [], $ctx->push('workflows')),
            components:         $this->parseComponents($d['components'] ?? null, $ctx->push('components')),
            specificationExtensions: $this->collectExtensions($d),
        );
    }
}
```

### Helpers

- `requireString($arr, $key, $ctx)` throws `ParserException::missingField($ctx->push($key))` or `wrongType(...)`.
- `optionalString`, `optionalArray`, `requireList` — same shape.
- `ParseContext` carries the JSON Pointer path (`/workflows/0/steps/2/stepId`) that later appears in `ValidationResult::$errors[*]->path`.

### Sum-type parsing

```php
private function parseSuccessAction(mixed $node, ParseContext $ctx): SuccessAction|Reusable
{
    if (!is_array($node)) throw ParserException::wrongType($ctx, 'object', $node);
    if (isset($node['reference'])) return $this->parseReusable($node, $ctx);

    $type = $this->requireString($node, 'type', $ctx);
    return match ($type) {
        'goto' => new GotoAction(/*…*/),
        'end'  => new EndAction(/*…*/),
        default => throw ParserException::invalidActionType($ctx, $type),
    };
}
```

Same shape for `FailureAction` (adds `retry`).

---

## 6. Expression Grammar & Reference Resolution

Arazzo 1.0.0 runtime expressions carry data between steps: `$inputs.foo`, `$steps.step1.outputs.id`, `$response.body#/data/0/id`, `$sourceDescriptions.api.url`, `$workflows.wf1.outputs.token`, `$statusCode`.

### Grammar (from Arazzo 1.0.0 §4.8.4)

```
expression      = "$" ( source-token / "response" / "request" / "url"
                       / "method" / "statusCode" )
source-token    = "inputs"       "." name
                / "outputs"      "." name
                / "steps"        "." name "." step-ref
                / "workflows"    "." name "." workflow-ref
                / "sourceDescriptions" "." name [ "." source-ref ]
                / "components"   "." component-type "." name
step-ref        = "outputs" "." name
                / "inputs"  "." name
                / "request" [ "." http-part ]
                / "response" [ "." http-part ]
workflow-ref    = "outputs" "." name / "inputs" "." name
http-part       = "body" [ "#" json-pointer ]
                / "header" "." name
                / "url" / "method" / "statusCode"
name            = 1*( ALPHA / DIGIT / "_" / "-" )
json-pointer    = per RFC 6901
```

### Parser

- `Expression\Lexer` — tokenises the raw string inside `{$...}` into `Token[]`.
- `Expression\Parser` — recursive descent → `ExpressionAst` sum type:
  - `InputRef { name }`
  - `OutputRef { name }` (workflow-level)
  - `StepRef { stepId, part: StepPart }` where `StepPart` = `OutputPart{name} | RequestPart{...} | ResponsePart{jsonPointer?, header?, ...} | InputPart{name}`
  - `WorkflowRef { workflowId, part }`
  - `SourceRef { name, subPath? }`
  - `ComponentRef { type, name }`
  - `HttpMetaRef { field }` (`$url`, `$method`, `$statusCode`)
- Malformed expressions produce an entry in `ValidationResult::$errors`. They do not throw.

Expressions are parsed lazily. `Expression::ast()` caches the result. This keeps the Parser stage fast and pushes expression syntax errors into validation output where they belong.

### Symbol table (built once per validation)

```
SymbolTable {
  workflows:          Map<workflowId, WorkflowSymbols>
  sourceDescriptions: Set<name>
  components:         Map<type, Set<name>>
}
WorkflowSymbols {
  inputs:     Set<name>              // from JSON Schema `properties`
  parameters: Set<name>
  stepsById:  OrderedMap<stepId, StepSymbols>
  outputs:    Set<name>
  dependsOn:  Set<workflowId>
}
StepSymbols {
  outputs: Set<name>
  index:   int                       // execution order
}
```

### Reference resolution rules

- `$inputs.X` — `X` declared in the current workflow's `inputs` JSON Schema `properties` or in `workflow.parameters`.
- `$steps.S.outputs.O` — step `S` exists in the **same** workflow, `S.index < currentStep.index`, output `O` declared on `S`.
- `$workflows.W.outputs.O` — workflow `W` exists, `W ∈ currentWorkflow.dependsOn`, output `O` declared on `W`.
- `$sourceDescriptions.N` — `N ∈ SymbolTable.sourceDescriptions`.
- `$components.T.N` — `SymbolTable.components[T]` contains `N`.
- `$response.*`, `$request.*`, `$statusCode`, `$url`, `$method` — only valid inside `successCriteria.condition`, step / workflow `outputs`, and `onSuccess`/`onFailure` criteria. Misuse (e.g. in `parameters.value`) is a rule violation.

### JSON Pointer fragments

`$response.body#/...` — the `#/...` fragment must be a syntactically valid RFC 6901 pointer. Semantic resolution against OpenAPI schemas is deferred (requires `sourceDescriptions` resolution, which lives in the runner).

### `sourceDescriptions` sub-references

`$sourceDescriptions.N.<sub>` — the `<sub>` tail is source-type-specific (e.g. `.workflows.X` on an Arazzo source, `.paths./users.get` on an OpenAPI source). v1 validates only that `N` is a declared source. The tail is captured in `SourceRef.subPath` as a raw string; semantic resolution belongs to the runner, which resolves the source.

---

## 7. Validation Rules

### Rule interface

```php
interface Rule
{
    public function code(): string;   // stable machine ID, e.g. "workflow.unique_id"
    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
```

Rules are held by `RuleSet` (ordered list). Config `arazzo.rules.disabled` silences individual rules by code.

### Catalog (v1)

| Code | What it checks | Severity |
|---|---|---|
| `document.arazzo_version` | `arazzo` field equals `"1.0.0"` | error |
| `document.info_required` | `info.title`, `info.version` non-empty | error |
| `source.unique_name` | `sourceDescriptions[*].name` unique | error |
| `source.url_syntax` | `url` is a valid URI or relative path token | error |
| `source.type_matches` | `type ∈ {openapi, arazzo}` | error |
| `workflow.at_least_one` | `workflows` non-empty | error |
| `workflow.unique_id` | `workflowId` unique across doc | error |
| `workflow.id_pattern` | matches `^[A-Za-z0-9_\-]+$` | error |
| `workflow.dependson_exists` | each `dependsOn` id resolves to a workflow | error |
| `workflow.dependson_no_cycle` | topological sort succeeds | error |
| `workflow.inputs_valid_schema` | `inputs` parses as JSON Schema (structural only) | error |
| `step.at_least_one` | `workflow.steps` non-empty | error |
| `step.unique_id` | `stepId` unique within workflow | error |
| `step.id_pattern` | matches `^[A-Za-z0-9_\-]+$` | error |
| `step.operation_target_present` | exactly one of `operationId`, `operationPath`, `workflowId` set | error |
| `step.operationid_source_scoped` | unqualified `operationId` requires exactly one source; qualified `source#op` requires source to exist | error |
| `step.operationpath_syntax` | JSON Pointer well-formed, referenced source present | error |
| `step.nested_workflow_exists` | `step.workflowId` resolves to a declared workflow | error |
| `step.parameters_have_name` | each param has `name` | error |
| `step.parameter_in_valid` | `in ∈ {path, query, header, cookie, body}` when set | error |
| `step.request_body_replacements_target` | `PayloadReplacement.target` is a well-formed JSON Pointer | error |
| `step.success_criteria_condition` | `condition` non-empty string | error |
| `step.criteria_type_context` | when `type ∈ {jsonpath, xpath, regex}`, `context` present | error |
| `step.outputs_unique` | output names unique per step | error |
| `expr.syntax` | every `Expression` string parses per grammar | error |
| `expr.unresolved_input_ref` | `$inputs.X` — `X` declared | error |
| `expr.unresolved_step_ref` | `$steps.S.outputs.O` — S exists before current step, O declared | error |
| `expr.unresolved_workflow_ref` | `$workflows.W.outputs.O` — W in `dependsOn`, O declared | error |
| `expr.unresolved_source_ref` | `$sourceDescriptions.N` — N exists | error |
| `expr.unresolved_component_ref` | `$components.T.N` — present | error |
| `expr.context_misuse` | `$response`/`$request`/`$statusCode`/`$url`/`$method` used outside criteria / outputs / actions | error |
| `expr.jsonpointer_syntax` | `#/...` fragment is RFC 6901 valid | error |
| `action.type_valid` | `SuccessAction.type ∈ {goto, end}`, `FailureAction.type ∈ {goto, retry, end}` | error |
| `action.goto_target_resolves` | `stepId` or `workflowId` target exists | error |
| `action.retry_limits` | `retryLimit >= 0`, `retryAfter >= 0` when set | error |
| `action.reusable_ref_resolves` | `$ref` resolves to `components.successActions.X` / `components.failureActions.X` | error |
| `components.unique_names` | each component-type map has unique keys (YAML permits dupes) | error |
| `extensions.x_prefix` | specification-extension keys start with `x-` | warning |
| `doc.unknown_field` | unknown top-level / DTO fields | warning; in strict mode → error |

Total ≈ 35 rules. Each rule is one class in `src/Validation/Rules/`, one Pest test in `tests/Validation/Rules/`, and at least one valid + one invalid fixture.

### ValidationResult

```php
final readonly class ValidationResult
{
    /**
     * @param list<Error>   $errors
     * @param list<Warning> $warnings
     */
    public function __construct(
        public ArazzoDocument $document,
        public array $errors,
        public array $warnings,
    ) {}

    public function isValid(): bool { return $this->errors === []; }
    public function toArray(): array;   // CLI JSON output
}

final readonly class Error
{
    public function __construct(
        public string  $code,     // stable ID, e.g. "step.unique_id"
        public string  $message,  // human-readable
        public string  $path,     // JSON Pointer, e.g. "/workflows/0/steps/2/stepId"
        public ?int    $line = null,  // best-effort from YAML lexer; null for JSON
    ) {}
}

final readonly class Warning { /* identical shape */ }
```

- `path` uses JSON Pointer (RFC 6901). Enables IDE integration and precise error jumps in the future UI.
- `line` is best-effort. Symfony YAML does not expose line info cheaply; if the cost of adding it (custom Tag handling) proves high, ship v1 with `line = null` everywhere and revisit in v1.1.

---

## 8. Errors

```
Alama\LaravelArazzo\Exceptions\
├── ArazzoException          (abstract base)
├── LoaderException          (I/O, decode, extension, root-not-object)
├── ParserException          (structural — missing required, wrong type, invalid enum)
└── ValidationException      (only thrown by Arazzo::assertValid)
```

Loader and Parser throw because failure at those stages means no DTO can be produced. Spec-conformance errors are collected into `ValidationResult`. Consumers who want fail-fast semantics call `Arazzo::assertValid(...)`.

Every exception carries a JSON Pointer `path` and a stable code. Named constructors (`::missingField(...)`, `::invalidActionType(...)`) keep tests off string matching.

---

## 9. Laravel Wiring

### Service provider

`src/LaravelArazzoServiceProvider.php` (uses `spatie/laravel-package-tools`):

```php
final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-arazzo')
            ->hasConfigFile()
            ->hasCommand(ValidateArazzoCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(YamlDecoder::class, SymfonyYamlDecoder::class);
        $this->app->singleton(JsonDecoder::class, NativeJsonDecoder::class);
        $this->app->singleton(Loader::class);
        $this->app->singleton(Parser::class);
        $this->app->singleton(RuleSet::class, fn () => RuleSet::default(
            disabled: config('arazzo.rules.disabled', []),
            strict:   config('arazzo.strict', true),
        ));
        $this->app->singleton(Validator::class);
        $this->app->singleton(Arazzo::class);      // core service, Facade target
    }
}
```

### Config (`config/arazzo.php`)

```php
return [
    'strict' => env('ARAZZO_STRICT', true),   // unknown fields → error vs warning
    'rules' => [
        'disabled' => [],                       // list of Rule::code()
    ],
    'output' => [
        'default_format' => 'human',            // human | json
    ],
];
```

### Facade

`src/Facades/Arazzo.php` proxies the core `Arazzo` service class.

### CLI

`src/Commands/ValidateArazzoCommand.php`:

```
php artisan arazzo:validate <file> [--format=human|json] [--fail-on-warning]
```

- Human format: colored table (`path  code  message`) + summary line.
- JSON format: `{ valid, errors: [...], warnings: [...] }` — CI-parseable.
- Exit codes: `0` valid, `1` spec errors, `2` load/parse errors, `3` file not found.
- `--fail-on-warning`: warnings promote to exit code `1`. Without the flag, warnings never affect exit code.

---

## 10. Repo Layout

```
src/
├── Arazzo.php                          # core service (Facade target)
├── Dto/
│   ├── ArazzoDocument.php
│   ├── Info.php
│   ├── SourceDescription.php
│   ├── Workflow.php
│   ├── Step.php
│   ├── Parameter.php
│   ├── RequestBody.php
│   ├── PayloadReplacement.php
│   ├── SuccessCriterion.php
│   ├── Action/
│   │   ├── SuccessAction.php           # abstract
│   │   ├── FailureAction.php           # abstract
│   │   ├── GotoAction.php
│   │   ├── EndAction.php
│   │   └── RetryAction.php
│   ├── Reusable.php
│   ├── Components.php
│   ├── RawDocument.php
│   └── Enum/
│       ├── SourceType.php
│       ├── ParameterIn.php
│       ├── CriterionType.php
│       └── Format.php
├── Loader/
│   ├── Loader.php
│   ├── YamlDecoder.php                 # interface
│   ├── SymfonyYamlDecoder.php
│   ├── JsonDecoder.php                 # interface
│   ├── NativeJsonDecoder.php
│   └── DecodeException.php
├── Parser/
│   ├── Parser.php
│   └── ParseContext.php
├── Expression/
│   ├── Expression.php                  # value object (raw + lazy AST)
│   ├── Lexer.php
│   ├── Parser.php
│   ├── SymbolTable.php
│   └── Ast/
│       ├── ExpressionAst.php           # abstract
│       ├── InputRef.php
│       ├── OutputRef.php
│       ├── StepRef.php
│       ├── WorkflowRef.php
│       ├── SourceRef.php
│       ├── ComponentRef.php
│       └── HttpMetaRef.php
├── Validation/
│   ├── Validator.php
│   ├── Rule.php                        # interface
│   ├── RuleSet.php
│   ├── ValidationResult.php
│   ├── Error.php
│   ├── Warning.php
│   ├── ErrorCollector.php
│   └── Rules/
│       ├── DocumentArazzoVersionRule.php
│       ├── ...                         # ~35 rule classes
│       └── ExpressionReferencesResolveRule.php
├── Exceptions/
│   ├── ArazzoException.php
│   ├── LoaderException.php
│   ├── ParserException.php
│   └── ValidationException.php
├── Commands/
│   └── ValidateArazzoCommand.php
├── Facades/
│   └── Arazzo.php
└── LaravelArazzoServiceProvider.php

config/
└── arazzo.php

tests/
├── Pest.php
├── TestCase.php                        # extends Orchestra\Testbench\TestCase
├── fixtures/
│   ├── valid/
│   │   ├── minimal.yaml
│   │   ├── login-workflow.yaml
│   │   ├── bnpl.yaml                   # from Arazzo spec examples
│   │   └── ...
│   ├── invalid/
│   │   └── <rule-code>/
│   │       ├── input.yaml
│   │       └── expected-errors.json    # list of { code, path }
│   └── malformed/                      # Loader / Parser failures
│       ├── not-yaml.yaml
│       └── missing-required.yaml
├── Loader/LoaderTest.php
├── Parser/ParserTest.php
├── Expression/
│   ├── LexerTest.php
│   └── ParserTest.php
├── Validation/
│   ├── ValidatorTest.php
│   └── Rules/                          # one file per rule
├── Commands/ValidateArazzoCommandTest.php
└── Feature/EndToEndTest.php
```

---

## 11. Testing Strategy

- **Unit tests** per unit (Loader, Parser, Expression Lexer/Parser, each Rule) with dependencies mocked.
- **Fixture-driven rule tests**: a Pest data-provider iterates `tests/fixtures/invalid/<rule-code>/` — asserts `ValidationResult::errors` contains entries whose `(code, path)` pairs match `expected-errors.json`.
- **Corpus tests**: every file in `tests/fixtures/valid/` must yield `ValidationResult::isValid() === true` with zero errors. Guards regressions when adding rules.
- **Feature tests**: boot Testbench, resolve the Facade, run `Arazzo::validate($path)` against fixtures.
- **CLI test**: `Artisan::call('arazzo:validate', [...])`; assert exit code + stdout format for both `human` and `json`.
- **Static analysis**: `larastan` at level 8. All parse methods return concrete types (no `mixed`). Collections carry `@var list<T>` PHPDoc.
- **Style**: `laravel/pint` in CI.

---

## 12. Dependencies

Add to `composer.json`:

- `symfony/yaml: ^7.0` — only runtime dep beyond the skeleton's existing `spatie/laravel-package-tools` and `illuminate/contracts`.

Update skeleton placeholders:

- `name`: `alama/laravel-arazzo`
- Namespaces: `Alama\LaravelArazzo\` (src) and `Alama\LaravelArazzo\Tests\` (tests)
- Description, keywords, author.

---

## 13. Deferred (Not in v1)

- OpenAPI resolution / `sourceDescriptions` fetching.
- Runner / executor.
- Generator (OpenAPI → Arazzo).
- React Flow UI.
- Line numbers for JSON errors (YAML best-effort only, and only if cheap).
- URL / HTTP loading of Arazzo files.
- Property-based / fuzz tests.
- Version-agnostic parser for Arazzo versions past 1.0.0.
