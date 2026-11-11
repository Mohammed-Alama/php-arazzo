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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RunCommand` constructs `DefaultOpenApiExecutor` with the configured PSR-18 client and PSR-17 factory.
- [ ] `RunCommand` constructs `StepOutputExtractor` and `ResponseSchemaValidator` from the real operation resolver and evaluation engine.
- [ ] `arazzo run` sends the request described by the resolved OpenAPI operation (method, URL, path/query/header/cookie parameters, body).
- [ ] Declared step `outputs` are extracted from the response and appear in the run result.
- [ ] A response that violates its OpenAPI response schema fails the step and the command returns non-zero.
- [ ] No production code references `Alama\Arazzo\Tests\Support\FakePsr18Client`; it stays test-only.
- [ ] `arazzo run` works in a `--no-dev` install, where test support classes are absent.
- [ ] `RunCommandTest` exercises the real path, using a fake PSR-18 client only at the HTTP boundary.
- [ ] A regression test asserts that a 500 response with a failing criterion returns `Command::FAILURE`.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; `RunCommand` is CLI-internal. Behaviour changes from "always succeeds" to "actually executes".
- **Blast radius:** `packages/cli`; test fixtures that relied on the fake path.
- **Severity:** high — the command's current output is misleading and any workflow automation built on it is unsound.
- **Rollback:** revert the command wiring; no data migration.

### 📊 9. Epistemic Verification Matrix

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
