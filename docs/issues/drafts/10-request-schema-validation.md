# Draft issue 10 — Validate outbound request bodies and parameters

- **Suggested title:** `[Feature] Validate request bodies and parameters against the OpenAPI operation schema before dispatch`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #25 OpenAPI Normalization Gaps, or #137)
- **Packages:** `protocol-http`, `engine`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/protocol-http` (execution), `packages/engine` (request compilation), `packages/runner` (dispatch).
- **Arazzo spec version:** 1.0.x and 1.1.x.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core.
- **Kind / level:** `kind:feature`, `level:story`.
- **Priority:** medium — not a spec MUST, but it closes a real correctness gap.

### 📝 2. Problem Statement & Intent

php-arazzo validates **responses** against the OpenAPI response schema (`ResponseSchemaValidator`), but never validates the **outbound** request. A step can send a body or parameter that violates the operation’s declared schema, and the first signal is the remote server’s error (or silent data corruption).

Because the operation model is already resolved before dispatch (`OpenApiOperationResolver`), the request schema is available. Validating before the call would:

- catch malformed payloads locally with a precise step/pointer;
- make the execution result deterministic in tests;
- prevent unnecessary side effects from requests that cannot succeed.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- Before dispatch, validate the resolved `requestBody.payload` against the operation’s `requestBody.content[contentType].schema` when a schema is declared.
- Validate resolved parameters against the corresponding OpenAPI `parameters` schema for `path`, `query`, `header` and `cookie` locations.
- Support OpenAPI 3.0 and 3.1 schema semantics already available through the JSON Schema engine.
- Emit a typed `SchemaValidationException` that names the step, the request part, and the JSON pointer of the failing value.
- Make the check configurable: default on for strict mode, opt-out for lenient execution.
- Keep response validation unchanged.

**Non-goals**

- AsyncAPI message payload validation (separate follow-up).
- SOAP/XSD request validation (separate protocol).
- Inferring missing request schemas or rewriting payloads.
- Validating server-side business rules.

**Prior art in this repository**

- `ResponseSchemaValidator` already resolves OpenAPI schemas and throws `SchemaValidationException`.
- `OpenApiOperationResolver::resolveModel()` returns a `ResolvedOperation` with the operation object.
- `RequestCompiler` already builds the path/query/header/body payload.
- #20 tracks the JSON Schema 2020-12 engine; this feature should use the same engine and not introduce a second validator.

**Proposed seam**

```php
interface RequestValidatorInterface
{
    /**
     * @throws SchemaValidationException
     */
    public function validateRequest(
        Step $step,
        \Alama\Arazzo\Document\ResolvedOperation $operation,
        array $path,
        array $query,
        array $headers,
        mixed $body,
        string $contentType,
    ): void;
}
```

Wire it next to `ResponseValidatorDispatcher` so protocol packages can supply their own request validators.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Validate outbound request bodies and parameters against the OpenAPI operation schema before dispatch, closing a correctness gap where responses are validated but requests are not.

**Architectural Boundary:** Bounded context = HTTP execution + request compilation. Packages: `packages/protocol-http` (request validator + dispatcher seam), `packages/engine` (request compilation), `packages/runner` (dispatch). Feature-agnostic core; port-agnostic (usable from CLI, MCP, UI). Arazzo does not mandate request validation — this is a quality/correctness feature.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Validation occurs once per step before dispatch; schema compilation cached per operation; no impact on steps without a declared schema.
- [ ] **Flexible:** New `RequestValidatorInterface` seam allows protocol packages to supply their own validators; strict/lenient switch configurable; enabled by default in strict mode.
- [ ] **Maintainable:** Mirrors the existing `ResponseSchemaValidator` pattern; single-responsibility validator; exceptions carry step id, request part, and JSON pointer.
- [ ] **Operable:** `SchemaValidationException` is typed and actionable; no HTTP request is sent on violation; rollback by disabling the validator.
- [ ] **Reliable:** Deterministic validation; idempotent; no side effects (no request sent on violation).
- [ ] **Safe:** Prevents invalid requests from reaching the server; no state mutation before validation passes.
- [ ] **Secure:** Input validation strengthens conformance; OpenAPI 3.0 nullable and 3.1 JSON Schema semantics consistent with response validation.
- [ ] **Suitable:** Closes a real correctness gap; builds on the OpenAPI operation contract already available via `OpenApiOperationResolver`.
- [ ] **Usable:** `SchemaValidationException` includes step id, request part, and JSON pointer; documentation describes the seam and strict/lenient switch.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Valid request body passes validation and request is dispatched**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0, step index 0 with an operation `getThing` that declares a request schema `application/json` with property `name: string`; the request body is `{ "name": "test" }` which conforms to the schema; `RequestValidatorInterface` is implemented and wired next to `ResponseValidatorDispatcher`; the step is configured for strict mode.
- **When** `RequestCompiler::compile()` builds the resolved request payload and `RequestValidatorInterface::validateRequestSchema()` is called before dispatch.
- **Then** validation passes; the HTTP request is dispatched to the OpenAPI operation; no `SchemaValidationException` is thrown; the request method, URL, and body are sent as compiled.

**Scenario 2: Invalid request body throws `SchemaValidationException` and no request is sent**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with operation `createThing` declaring a request schema `application/json` requiring property `name: string`; the request body is `{ "name": 123 }` (type violation: number instead of string); `DefaultOpenApiExecutor` is configured with a real PSR-18 client.
- **When** the request is compiled and validated before dispatch via `RequestValidatorInterface`.
- **Then** `SchemaValidationException` is thrown with step index 0, request part `body`, and JSON pointer `/properties/name/type`; the error message states `Expected type string, got integer`; no HTTP request is sent to the server; `DefaultOpenApiExecutor` does not call `$httpClient->sendRequest()`.

**Scenario 3: Path/query/header/cookie parameters validated against declared schemas**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with operation `getItem` declaring a query parameter `id: integer`; the request sets query parameter `id: "abc"` (string instead of integer).
- **When** `RequestValidatorInterface::validateRequestSchema()` validates the query parameters.
- **Then** validation fails with `SchemaValidationException` naming the offending parameter `id` at pointer `/parameters/query/id`; the error message states `Expected type integer, got string`; no HTTP request is dispatched.

**Scenario 4: Step with no declared schema is unaffected**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with an operation that declares no request schema (no `requestBody` and no parameters with schemas).
- **When** `RequestValidatorInterface::validateRequestSchema()` is called.
- **Then** validation passes; no schema is available to validate against; the HTTP request is dispatched normally; `ValidationResult::isValid()` returns `true` for this step.

**Scenario 5: Validator disabled (lenient mode) — invalid body is dispatched**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with operation `createThing` declaring a request schema; the request body violates the schema; the validator is disabled by configuration (`--disable-rule request.schema_validation` or equivalent).
- **When** the step executes with the validator disabled.
- **Then** no `SchemaValidationException` is thrown; the request is dispatched as before (lenient mode); the invalid body reaches the server; `DefaultOpenApiExecutor` calls `$httpClient->sendRequest()`.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Define `RequestValidatorInterface` (mirroring `ResponseValidatorInterface`) so protocol packages can supply their own request validators
- [ ] Wire the request validator next to `ResponseValidatorDispatcher`
- [ ] Validate resolved request body against the operation's declared request schema when one exists
- [ ] Validate path, query, header, and cookie parameters against their declared parameter schemas
- [ ] On violation, throw `SchemaValidationException` with step id, request part, and JSON pointer; do not send the HTTP request
- [ ] Steps with no declared schema are unaffected
- [ ] Validator enabled by default in strict mode; configurable to disable
- [ ] OpenAPI 3.0 nullable and 3.1 JSON Schema semantics consistent with response validation
- [ ] Tests: valid body; invalid body type; missing required body property; invalid query/path/header parameter; no-schema operation; disabled validator; exception message/pointer
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Exceptions logged/audited via existing error handling
- [ ] Documentation describes the new seam and the strict/lenient switch


### 🧬 10. Source Grounding & Code Location

- `packages/protocol-http/src/Execution/ResponseSchemaValidator.php:21-57` — the response-side implementation to mirror.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/protocol-http/src/Execution/ResponseSchemaValidator.php#L21-L57"><code>ResponseSchemaValidator.php:21-57</code></a> @ <code>ea8c55a</code></summary>

```php
class ResponseSchemaValidator implements ResponseValidatorInterface
{
    public function __construct(
        private OpenApiOperationResolver $operationResolver,
    ) {}

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $schema = $this->findResponseSchema($step, $statusCode, $contentType, $document);
        // ... validate and throw SchemaValidationException ...
    }
}
```

</details>

- `packages/sources/src/Normalizer/OpenApiOperationResolver.php` — resolves the operation model used by both validators.
- `packages/engine/src/Execution/RequestCompiler.php:47-75` — builds the resolved request payload.
- `packages/runner/src/ResponseValidatorDispatcher.php` — the neighbouring dispatcher pattern.
- Spec: Arazzo does not mandate request validation; this is a quality/correctness feature built on the OpenAPI operation contract.

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive interface and configuration; strict mode may reject requests that previously reached the server.
- **Blast radius:** `packages/protocol-http`, `packages/engine`, `packages/runner`, Laravel wiring.
- **Severity:** medium — prevents invalid calls and makes execution deterministic.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Responses are validated | T1 | `ResponseSchemaValidator.php:27-57` | direct read |
| Requests are not validated | T1 | grep for `validateRequest`/request schema validation | direct read |
| The operation model is available before dispatch | T1 | `OpenApiOperationResolver::resolveModel()` | direct read |
| `RequestCompiler` builds the payload | T1 | `RequestCompiler.php:47-75` | direct read |
| Request validation is not a spec MUST | T2 | Arazzo 1.1.0 Step Object `requestBody` row | spec text |
