# Draft issue 11 — Wire `arazzo run` to the real execution path

- **Suggested title:** `[Bug] `arazzo run` uses a fake HTTP client and no-op output/response handling`
- **Kind:** `kind:bugfix`
- **Level:** `level:story` (suggested parent: #137 Runner execution semantics)
- **Packages:** `cli`, `protocol-http`, `runner`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RunCommand`; `packages/protocol-http` execution services; `packages/runner` `RunnerFacade`.
- **Arazzo spec version:** independent of spec version; affects every `arazzo run` invocation.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core CLI.
- **Kind / level:** `kind:bugfix`, `level:story`, under #137 (Runner execution semantics & cross-document references).
- **Priority:** P0 — the CLI currently reports success without executing anything.

### 📝 2. Problem Statement & Intent

`arazzo run` builds a runner with three stand-ins instead of the real HTTP execution services:

- a `FakePsr18Client` (`RunCommand.php:80`),
- an anonymous `OpenApiExecutorInterface` that ignores the resolved operation and payload, sends `POST http://test`, and returns the fake client's response (`RunCommand.php:83-96`),
- an `OutputExtractorInterface` whose `extractOutputs()` always returns `[]` (`RunCommand.php:98-104`),
- a `ResponseValidatorInterface` whose `validateResponseSchema()` is empty (`RunCommand.php:106-109`).

The real implementations already exist and are used by the Laravel host:
`DefaultOpenApiExecutor`, `StepOutputExtractor` and `ResponseSchemaValidator`
under `packages/protocol-http/src/Execution/`. Because they are not wired here:

- no request is built from the OpenAPI operation, parameters or body;
- no output is extracted from the response, so downstream `$steps.<id>.outputs.*` references resolve to nothing;
- no response is validated against the OpenAPI response schema;
- the command still prints `succeeded` and returns exit code `0`.

`RunCommandTest` hides the defect because it also depends on the fake path.

There is a second defect on the same line: `FakePsr18Client` lives in
`packages/core/tests/Support/FakePsr18Client.php` and is autoloaded only through
the root `autoload-dev` map (`Alama\Arazzo\Tests\` → `packages/core/tests/`).
Production `src/` code therefore depends on a test-only class; in a
`--no-dev` install the class does not exist and `arazzo run` fatals before it
executes anything.

Expected: `arazzo run` uses the same production execution graph as the Laravel
host, sends real requests through the injected PSR-18 client, extracts outputs,
validates responses, and reports the true status.

### 📦 3. System Dependency Matrix

- **Upstream:** PSR-18 `ClientInterface`, PSR-17 `RequestFactoryInterface`; `Alama\Arazzo\Protocol\Http` execution services; `Alama\Arazzo\Sources` operation resolver; `Alama\Arazzo\Evaluation`.
- **Downstream:** every CLI consumer (`run`), CI scripts, and the forthcoming `test`/`trace` commands (drafts 12-15).
- **Internal seams used:** `RunnerFacade` already accepts `OpenApiExecutorInterface`, `OutputExtractorInterface` and `ResponseValidatorInterface` (`packages/runner/src/RunnerFacade.php:31-43`).
- **Packages touched:** `packages/cli`; no public contract change expected.

### 📄 4. Structural Artifacts & Raw Inputs

Minimal document and command that reproduce the defect:

```yaml
arazzo: 1.0.1
info: { title: Repro, version: "1" }
sourceDescriptions:
  - name: petstore
    url: ./openapi.yaml
    type: openapi
workflows:
  - workflowId: ping
    steps:
      - stepId: ping
        operationId: ping
        successCriteria:
          - condition: $statusCode == 200
        outputs:
          ok: $response.body#/ok
```

```bash
arazzo run repro.arazzo.yaml -w ping
```

Observed: `workflow ping: succeeded` with no request sent and no `outputs`.
Expected: a real request to the resolved server/path, extracted `ok`, and a
response schema check.

### 🌀 5. Runtime Execution State Context

- Workflow: first workflow unless `--workflow` is supplied.
- Step: every HTTP/OpenAPI step.
- Inputs: `--input` JSON (currently unvalidated; see draft 19).
- Environment: any PSR-18 client is accepted but ignored for execution.
- State: `RunnerFacade::execute()` returns `steps` and `outputs`, but `outputs` is always empty because the extractor is a no-op.

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Wire `arazzo run` to the real HTTP execution path by replacing the fake client, no-op output extractor, and no-op response validator with the real `DefaultOpenApiExecutor`, `StepOutputExtractor`, and `ResponseSchemaValidator`, so the command actually executes workflows instead of always reporting success.

**Architectural Boundary:** Bounded context = CLI command wiring + HTTP execution. Packages: `packages/cli` (`RunCommand`), `packages/protocol-http` (execution services), `packages/runner` (`RunnerFacade`). The runner already accepts all three seams via constructor injection; only `RunCommand` needs rewiring.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** No added overhead; replaces no-op stubs with real implementations already used by tests; one HTTP request per step as before.
- [ ] **Flexible:** `RunnerFacade` accepts injectable seams (`OpenApiExecutorInterface`, `OutputExtractorInterface`, `ResponseValidatorInterface`); swappable per entry point (CLI, MCP, UI).
- [ ] **Maintainable:** Removes four anonymous no-op classes from `RunCommand`; uses real, tested implementations; `SchemaValidationException` and `ExecuteStepException` are typed.
- [ ] **Operable:** Command returns `Command::FAILURE` on execution errors; `arazzo run` works in `--no-dev` installs (no test-support dependencies).
- [ ] **Reliable:** Real response validation fails steps on schema violations; 500 responses with failing criteria return non-zero; no silent success.
- [ ] **Safe:** No production code references `FakePsr18Client` (stays test-only); no side effects beyond the intended HTTP calls.
- [ ] **Secure:** Response schema validation rejects malformed responses; no new authorization surface.
- [ ] **Suitable:** Closes a P0 correctness gap: the CLI previously reported success without executing anything.
- [ ] **Usable:** `arazzo run` now behaves as users expect: executes the workflow and reports real results.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `arazzo run` executes the workflow and returns the run result**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0 with step index 0 (`operationId: $sourceDescriptions.api.getThing`); `RunCommand` is configured with a real PSR-18 client (`GuzzleHttp\Client`) and PSR-17 factory; `DefaultOpenApiExecutor`, `StepOutputExtractor`, and `ResponseSchemaValidator` are constructed from the real operation resolver and evaluation engine; `RunnerFacade` accepts all three seams via constructor injection.
- **When** `php bin/console arazzo run doc.arazzo` is invoked.
- **Then** `RunCommand` constructs `DefaultOpenApiExecutor` with the configured PSR-18 client; the HTTP request described by the resolved OpenAPI operation is sent (method, URL, path/query/header/cookie parameters, body); `StepOutputExtractor` extracts declared step outputs from the response; the run result contains `workflowId`, `status: succeeded`, `outputs`, and per-step `steps[]` with `stepId`, `status`, `attempts`, `statusCode`; the command returns `Command::SUCCESS` (exit code 0).

**Scenario 2: A response violating its OpenAPI schema fails the step and returns non-zero**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with operation `createThing` declaring a response schema `application/json` requiring property `id: integer`; the server returns HTTP 200 with body `{ "id": "not-an-integer" }` (type violation); `ResponseSchemaValidator` is active; `SchemaValidationException` is typed.
- **When** `DefaultOpenApiExecutor` dispatches the request and receives the response; `ResponseSchemaValidator::validateResponseSchema()` is called.
- **Then** `ResponseSchemaValidator` throws `SchemaValidationException` with step index 0; the step is marked failed; `Command::FAILURE` is returned (exit code non-zero); no silent success is reported.

**Scenario 3: No production reference to `FakePsr18Client`**
- **Given** a `--no-dev` PHP installation where `Alama\Arazzo\Tests\Support\FakePsr18Client` is absent (test support classes not autoloaded); `RunCommand` has been rewired to use `DefaultOpenApiExecutor` with a real PSR-18 client.
- **When** `php bin/console arazzo run doc.arazzo` is invoked in the `--no-dev` environment.
- **Then** no production code references `Alama\Arazzo\Tests\Support\FakePsr18Client`; `FakePsr18Client` stays test-only; the command executes successfully without class-not-found errors.

**Scenario 4: Regression — 500 response with failing criterion returns `Command::FAILURE`**
- **Given** an Arazzo 1.1.0 document containing workflow index 0, step index 0 with `successCriteria: [{ condition: "$statusCode == 200" }]`; the server returns HTTP 500 with an error body; `ResponseSchemaValidator` validates the response; `ExecuteStepException` is typed.
- **When** the request is dispatched and the 500 response is received; the success criterion `$statusCode == 200` evaluates to `false`.
- **Then** `ExecuteStepException` is thrown; the step fails; `Command::FAILURE` is returned (exit code non-zero); the run result records the step failure with `failureCategory`.

**Scenario 3: No production reference to `FakePsr18Client`**
- **Given** a `--no-dev` install where test support classes are absent
- **When** `arazzo run` runs
- **Then** no production code references `Alama\Arazzo\Tests\Support\FakePsr18Client`

**Scenario 4: Regression — 500 response with failing criterion returns FAILURE**
- **Given** a step returning HTTP 500 with a failing `successCriteria`
- **When** the run executes
- **Then** the command returns `Command::FAILURE`

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RunCommand` constructs `DefaultOpenApiExecutor` with the configured PSR-18 client and PSR-17 factory
- [ ] `RunCommand` constructs `StepOutputExtractor` and `ResponseSchemaValidator` from the real operation resolver and evaluation engine
- [ ] `arazzo run` sends the request described by the resolved OpenAPI operation (method, URL, parameters, body)
- [ ] Declared step `outputs` are extracted from the response and appear in the run result
- [ ] A response violating its OpenAPI response schema fails the step; command returns non-zero
- [ ] No production code references `FakePsr18Client`; it stays test-only
- [ ] `arazzo run` works in a `--no-dev` install
- [ ] `RunCommandTest` exercises the real path, using a fake PSR-18 client only at the HTTP boundary
- [ ] Regression test: 500 response with failing criterion returns `Command::FAILURE`
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] No telemetry/logging changes needed (existing error handling suffices)


### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RunCommand.php:80-119` — fake client, fake executor, no-op extractor and validator.
- `packages/protocol-http/src/Execution/DefaultOpenApiExecutor.php:25-150` — real executor.
- `packages/protocol-http/src/Execution/StepOutputExtractor.php:30-65` — real output extraction.
- `packages/protocol-http/src/Execution/ResponseSchemaValidator.php:21-60` — real response validation.
- `packages/runner/src/RunnerFacade.php:31-43` — injection seams already exist.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/packages/cli/src/Console/Command/RunCommand.php#L80-L119"><code>RunCommand.php:80-119</code></a> @ <code>ea8c55a</code></summary>

```php
$fakeClient = new FakePsr18Client();
$fakeFactory = new HttpFactory();

$openApiExecutor = new class($fakeClient, $fakeFactory) implements OpenApiExecutorInterface
{
    public function execute(OpenApiOperationHandle $operation, OpenApiPayload $payload, ?callable $requestInterceptor = null, ?float $timeoutSeconds = null): ResponseInterface
    {
        $request = $this->factory->createRequest('POST', 'http://test');

        return $this->client->sendRequest($request);
    }
};

$outputExtractor = new class() implements OutputExtractorInterface
{
    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }
};

$schemaValidator = new class() implements ResponseValidatorInterface
{
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}
};
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; `RunCommand` is CLI-internal. Behaviour changes from "always succeeds" to "actually executes".
- **Blast radius:** `packages/cli`; test fixtures that relied on the fake path.
- **Severity:** high — the command's current output is misleading and any workflow automation built on it is unsound.
- **Rollback:** revert the command wiring; no data migration.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `RunCommand` uses `FakePsr18Client` | T1 | `RunCommand.php:80` | direct read |
| The fake class is test-only | T1 | `packages/core/tests/Support/FakePsr18Client.php`; root `autoload-dev` maps `Alama\Arazzo\Tests\` | direct read |
| The executor ignores operation and payload | T1 | `RunCommand.php:90-95` | direct read |
| Output extraction always returns `[]` | T1 | `RunCommand.php:98-104` | direct read |
| Response validation is a no-op | T1 | `RunCommand.php:106-109` | direct read |
| Real implementations exist | T1 | `DefaultOpenApiExecutor.php`, `StepOutputExtractor.php`, `ResponseSchemaValidator.php` | direct read |
| `RunnerFacade` accepts all three seams | T1 | `RunnerFacade.php:31-43` | direct read |
| The test suite masks the defect | T2 | `RunCommandTest` builds the same command without asserting outbound requests | inferred from test read |
