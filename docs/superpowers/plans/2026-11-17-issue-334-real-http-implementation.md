# #334 implementation plan (Stage 4)

Approved design: issue #334 comment 6947153999; sign-off 6947487418; ADR-001.

## Slice A — strict validation default flip

RED tests, then GREEN source changes:

- [ ] `packages/runner/tests/Sync/StepExecutorTest.php`: default validates; per-step opt-out still wins; existing "off" test passes `strictValidationDefault: false`.
- [ ] `packages/runner/tests/Execution/AsyncGraphSeamsTest.php`: default `strictValidation` true.
- [ ] `packages/protocol-http/tests/Protocol/HttpStepExecutorTest.php`: default validates.
- [ ] `packages/protocol-http/tests/Registrar/ProtocolHttpRegistrarTest.php`: member default mirrored to the http executor.
- [ ] `packages/laravel/tests/Config/StrictValidationDefaultTest.php`: config default + both explicit consumers.
- [ ] Source: `StepExecutor`, `AsyncGraphSeams`, `HttpStepExecutor`, `ProtocolHttpRegistrar`, `packages/laravel/config/arazzo.php`, `AsyncGraphResolver`, `ProtocolHttpServiceProvider`.

## Slice B — CLI composition root

- [ ] `packages/cli/tests/Console/RunCommandTest.php`: CommandTester over the real graph — outbound request (method/URL/path/query/header/body), step outputs incl. downstream reference, schema-violation failure + rendered message + exit 1, failed criteria exit 1.
- [ ] Guard test: no `Alama\Arazzo\Tests` reference under `packages/*/src` (RED before the import is removed).
- [ ] Source: `RunCommand` resolves one PSR-18 client + PSR-17 factory, shares it with `SourceGraph::createRegistry()` and `DefaultOpenApiExecutor`, builds `StepOutputExtractor` + `ResponseSchemaValidator`, catches `SchemaValidationException`, drops `FakePsr18Client`.

## Slice C — docs

- [ ] Three READMEs (`README.md`, `packages/core/README.md`, `packages/laravel/README.md`).
- [ ] `CHANGELOG.md` entry calling out the semantic BC break.

## Gates

- [ ] `make verify`
- [ ] `make test-mutate`
- [ ] falsification scripts (`detect-fake-tests.php`, `audit-boundaries.php`, `delete-fix-check.sh`, `verify-falsification.sh`)
