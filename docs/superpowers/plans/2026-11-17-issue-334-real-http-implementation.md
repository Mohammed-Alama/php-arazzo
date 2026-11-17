# #334 implementation plan (Stage 4)

Approved design: issue #334 comment 6947153999; sign-off 6947487418; ADR-001.

## Slice A — strict validation default flip

RED tests, then GREEN source changes:

- [x] `packages/runner/tests/Sync/StepExecutorTest.php`: default validates; per-step opt-out still wins; existing "off" test passes `strictValidationDefault: false`.
- [x] `packages/runner/tests/Execution/AsyncGraphSeamsTest.php`: default `strictValidation` true.
- [x] `packages/protocol-http/tests/Protocol/HttpStepExecutorTest.php`: default validates.
- [x] `packages/protocol-http/tests/Registrar/ProtocolHttpRegistrarTest.php`: member default mirrored to the http executor.
- [x] `packages/laravel/tests/Config/StrictValidationDefaultTest.php`: config default + both explicit consumers.
- [x] Source: `StepExecutor`, `AsyncGraphSeams`, `HttpStepExecutor`, `ProtocolHttpRegistrar`, `packages/laravel/config/arazzo.php`, `AsyncGraphResolver`, `ProtocolHttpServiceProvider`.

## Slice B — CLI composition root

- [x] `packages/cli/tests/Console/RunCommandTest.php`: CommandTester over the real graph — outbound request (method/URL/path/query/header/body), step outputs incl. downstream reference, schema-violation failure + rendered message + exit 1, failed criteria exit 1.
- [x] Guard test: no `Alama\Arazzo\Tests` reference under `packages/*/src` (RED before the import is removed).
- [x] Source: `RunCommand` resolves one PSR-18 client + PSR-17 factory, shares it with `SourceGraph::createRegistry()` and `DefaultOpenApiExecutor`, builds `StepOutputExtractor` + `ResponseSchemaValidator`, catches `SchemaValidationException`, drops `FakePsr18Client`.

## Slice C — docs

- [x] Three READMEs (`README.md`, `packages/core/README.md`, `packages/laravel/README.md`).
- [x] `CHANGELOG.md` entry calling out the semantic BC break.

## Gates

- [x] `make verify` — exit 0 (docs, pint, phpstan, baselines, 17 Pest suites: 2109 passed, 3 skipped, 2 deprecated).
- [ ] `make test-mutate` — **blocked by repo plumbing, not by this change.** The target runs `cd packages/{core,laravel} && vendor/bin/pest`, but neither package has its own `vendor/` (the monorepo installs once at the root). Running the equivalent against `packages/core` also aborts because `packages/core/src` is an empty placeholder and its 128M memory limit is exhausted by the Arch suite. The target mutates neither `cli`, `runner` nor `protocol-http`, so it cannot exercise this change.
- [x] Falsification:
  - `detect-fake-tests.php` on the seven touched test files: clean (0 violations).
  - `audit-boundaries.php` on `RunCommand` and `StepExecutor`: exit 0 (advisory misses only).
  - Delete-the-fix (manual because `delete-fix-check.sh` only targets `packages/core|laravel`): restoring the pre-fix `RunCommand` makes 4 new CLI tests fail; HEAD makes all 13 pass.
  - `verify-falsification.sh --quick`: pint, phpstan and Pest pass; exits 1 only on 41 pre-existing fake-detector findings in `packages/{core,laravel}/tests`, none in the touched files. Its conformance step cannot run because it also assumes a package-local `vendor/`.

## Commits

- `f03c468b` feat(runner): default strict response validation (#334)
- `5ab9e1f8` fix(cli): execute workflows through the real HTTP graph (#334)
- `fc51a202` docs: record strict-by-default validation and real CLI HTTP (#334)
- `8d4c01d2` test: harden strict-default opt-out and boundary proof (#334)
