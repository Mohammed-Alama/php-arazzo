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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] A resolved request body is validated against the operation’s declared request schema when one exists.
- [ ] Path, query, header and cookie parameters are validated against their declared parameter schemas.
- [ ] A violation throws `SchemaValidationException` with step id, request part, and JSON pointer; no HTTP request is sent.
- [ ] Steps whose operation declares no schema are unaffected.
- [ ] The validator is enabled by default in strict mode and can be disabled by configuration.
- [ ] OpenAPI 3.0 nullable semantics and 3.1 JSON Schema semantics behave consistently with response validation.
- [ ] Tests cover: valid body; invalid body type; missing required body property; invalid query/path/header parameter; no-schema operation; disabled validator; exception message/pointer.
- [ ] Documentation describes the new seam and the strict/lenient switch.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive interface and configuration; strict mode may reject requests that previously reached the server.
- **Blast radius:** `packages/protocol-http`, `packages/engine`, `packages/runner`, Laravel wiring.
- **Severity:** medium — prevents invalid calls and makes execution deterministic.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| Responses are validated | T1 | `ResponseSchemaValidator.php:27-57` | direct read |
| Requests are not validated | T1 | grep for `validateRequest`/request schema validation | direct read |
| The operation model is available before dispatch | T1 | `OpenApiOperationResolver::resolveModel()` | direct read |
| `RequestCompiler` builds the payload | T1 | `RequestCompiler.php:47-75` | direct read |
| Request validation is not a spec MUST | T2 | Arazzo 1.1.0 Step Object `requestBody` row | spec text |
