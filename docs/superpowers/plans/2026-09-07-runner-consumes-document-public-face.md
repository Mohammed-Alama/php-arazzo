# Runner consumes document/expression via public faces — issue #60

Branch: `feat/runner-consumes-document-face` off `main` (`d333664`).

## Acceptance criteria (from #60)

1. Runner execution code (`packages/runner/src`) imports no expression/document internal service types; it uses the public faces (`ExpressionEngineInterface`, `DocumentInterface` + value shapes).
2. Execution/output behaviour unchanged for same inputs.
3. Tests, static analysis and format pass.

## Current state (surveyed)

- The expression side is already face-only in `packages/runner/src` (`ExpressionEngineInterface`, `ExpressionResolverInterface`, `EvaluationInputInterface`, `Enum\ReferenceKind`).
- The remaining concrete document-service imports in `packages/runner/src`:
  - `Document\Normalizer\OpenApiOperationResolver` — in `StepExecutor`, `StepOutputExtractor`, `ResponseSchemaValidator` (as `OpenApiOperationResolver|DocumentInterface` union) and `Protocol\HttpStepExecutor` (concrete only, calls `->resolve()`).
  - `Document\Validator\PreflightValidator` — in `StepExecutionWorker`, `Async\PreflightGuard`, `Execution\Data\RunControlFlow` (concrete only), and `WorkflowExecutor` (as `PreflightValidator|DocumentInterface|null` union).
- `DocumentInterface` already exposes `resolveOperation()`, `preflight()` and `preflightInputs()`; `Document::preflight()` == `PreflightValidator::validate()` and `Document::preflightInputs()` == `PreflightValidator::validateInputs()` (verified in `packages/document/src/Document.php`), so swapping to the face is behaviour-preserving.
- Ideal parity facts: `Document::resolveOperation()` == `OpenApiOperationResolver::resolve()` (verified).

## Design

Narrow every runner constructor param to `DocumentInterface` and drop the `instanceof DocumentInterface` fallback branches. Because conforming harnesses and tests build resolvers over custom/seeded `SourceRegistry`/`SourceResolver` objects, the `Document` facade gains an optional third constructor arg `?SourceRegistry $sources = null` (default keeps today's self-contained wiring; injected registry is used for operations + preflight). This keeps test code from importing internals where it previously had to.

## Tasks

### Task 1: Narrow runner execution onto DocumentInterface (packages/runner/src, 8 files)

- `packages/runner/src/Execution/StepExecutor.php`
- `packages/runner/src/Execution/StepOutputExtractor.php`
- `packages/runner/src/Execution/ResponseSchemaValidator.php`
  Swap `use ...\OpenApiOperationResolver;` → `use Alama\Arazzo\Document\DocumentInterface;`; param `OpenApiOperationResolver|DocumentInterface` → `DocumentInterface`; in private `resolveOperation()` delete the `instanceof DocumentInterface` branch, always return `$this->operationResolver->resolveOperation($step, $document)`.
- `packages/runner/src/Protocol/HttpStepExecutor.php`
  Import swap; param → `DocumentInterface`; call `$this->operationResolver->resolve($step, $document)` → `->resolveOperation($step, $document)`.
- `packages/runner/src/Execution/StepExecutionWorker.php`
  Import swap; property `?PreflightValidator` → `?DocumentInterface`; call `$this->preflight->validate($documentForPreflight)` → `->preflight(...)`.
- `packages/runner/src/Async/PreflightGuard.php`
  Import swap; ctor `?PreflightValidator` → `?DocumentInterface`; `->validate($document)` → `->preflight($document)`.
- `packages/runner/src/Execution/WorkflowExecutor.php`
  Import swap; ctor `PreflightValidator|DocumentInterface|null $preflight` → `?DocumentInterface $preflight`; delete both `instanceof` branches in `preflightDocument()`/`preflightInputs()` (always `->preflight()` / `->preflightInputs()`).
- `packages/runner/src/Execution/Data/RunControlFlow.php`
  Import swap; `public ?PreflightValidator $preflight = null` → `public ?DocumentInterface $preflight = null`.

After this, `packages/runner/src` imports of document = `DocumentInterface` + value/data shapes only.

### Task 2: Face bridge + wiring

- `packages/document/src/Document.php` — add optional `?SourceRegistry $sources = null` to ctor; `$this->sources = $sources ?? new SourceRegistry(...)` (operations + preflight built off `$this->sources` afterwards, unchanged).
- `packages/cli/src/Console/Command/RunCommand.php` — drop `OpenApiOperationResolver` + direct normalizer/loader/registry construction entirely; build the face `$documents = new Document($client, $factory)`; pass `$documents` to `StepOutputExtractor($documents, $engine)`, `ResponseSchemaValidator($documents)` and `StepExecutor(..., $documents, engine: $engine)`.
- `packages/laravel/src/Bindings/ExecutionBindings.php` — replace `$app->make(OpenApiOperationResolver::class)` (3 sites) and `$app->make(PreflightValidator::class)` (2 sites) with `$app->make(DocumentInterface::class)`; update imports (drop `OpenApiOperationResolver`, `PreflightValidator`; add `Alama\Arazzo\Document\DocumentInterface`).

### Task 3: Conformance harness + test updates

- `packages/core/tests/Conformance/ConformanceHarness.php` — add
  `protected function documents(SourceRegistry $registry): DocumentInterface { return new Document(null, null, $registry); }`;
  change `resolver(OpenApiOperationResolver $operationResolver)` → `resolver(DocumentInterface $documents)` passing `$documents` to `StepOutputExtractor`/`ResponseSchemaValidator`. Remove `operationResolver()` + now-unused normalizer imports if unused.
- `packages/core/tests/Conformance/FixtureRunner.php`, `OaiFixtureRunner.php`, `OaiQueueFixtureRunner.php`, `QueueFixtureRunner.php` — `$operationResolver = $this->operationResolver($this->sourceRegistry);` → `$documents = $this->documents($this->sourceRegistry);` and use `$documents` at every `$operationResolver` arg position.
- `packages/core/tests/Validator/InputsPreValidationTest.php` — build one `$documents = new Document(null, null, $registry)` and use it for StepOutputExtractor/ResponseSchemaValidator/StepExecutor/preflight; drop `preflightForInputsDoc()`.
- `packages/runner/tests/Execution/HttpStepExecutorTest.php` — `createMockOperationResolver()` → `createMockDocumentResolver(): DocumentInterface` (Mockery `DocumentInterface::class`, stub `resolveOperation` returning the same `ResolvedOperation`); update 5 call sites.
- `packages/runner/tests/Execution/StepExecutorTest.php` — same mock rename + 2 call sites.
- `packages/runner/tests/Execution/RuntimeFailureClassificationTest.php` — harness `ops(): DocumentInterface { return $this->documents($this->sourceRegistry); }`, `res(DocumentInterface $r)`; drop `OpenApiOperationResolver` import.
- `packages/runner/tests/Execution/WorkflowExecutorTest.php` — replace manual `OpenApiOperationResolver` with `new Document(null, null, new SourceRegistry($sourceResolver))`; pass it everywhere; drop manual loader/normalizer construction.
- `packages/runner/tests/Execution/AdapterParityTest.php` — same pattern (anonymous `SourceResolver` wraps in `SourceRegistry` + `Document`).
- `packages/runner/tests/Execution/ArazzoOutputExtractorTest.php` — `new Document(null, null, new SourceRegistry(new DefaultSourceResolver(['file' => new LocalFetcher()])))` (openapi fixture file) → StepOutputExtractor.
- `packages/runner/tests/Execution/ArazzoSchemaValidatorTest.php` — replace `OpenApiOperationResolver` build with `Mockery::mock(DocumentInterface::class)` (subclass only uses `findOperation` override, never resolves).
- `packages/runner/tests/Async/PreflightGuardTest.php` — `guardValidator(): DocumentInterface` returning `new Document(null, null, $registry)` (hits `preflight()`).

### Task 4: Seam guard + docs + gate + PR

- Create `packages/runner/tests/Validation/RunnerFaceSeamTest.php` scanning `packages/runner/src` for imports of expression internals (`Ast\`, `Parser`, `Lexer`, `ExpressionEvaluator`, `JsonPathEvaluator`, `JsonPointer`, `SelectorEvaluator`, `StringInterpolator`, `Xpath\`, `Evaluation\`) and document concretes (`Normalizer\OpenApiOperationResolver`, `Validator\PreflightValidator`) → expect none. Run + sanity-fail check.
- Regenerate docs (`php scripts/generate-docs.php`), run `make verify`.
- Commits (style `— refs #60`, final `— Closes #60`):
  1. `refactor(runner): consume document public face in execution internals — refs #60` (Task 1)
  2. `feat(document): allow facade construction over injected source registry; rewire cli/laravel — refs #60` (Task 2 + Task 3 harness/tests)
  3. `test(runner): enforce document public-face seam — refs #60` (Task 4 guard)
  4. `chore(docs): refresh generated docs — Closes #60` (docs drift, if any)
- Push `feat/runner-consumes-document-face`, open PR against `main`, body `Closes #60`.

## Verification

- `vendor/bin/pest packages/runner/tests` and `packages/core/tests/Conformance`, plus `make verify` after all edits.
- All behaviour-equivalent swaps rely on: `Document::preflight()==PreflightValidator::validate()`, `Document::preflightInputs()==PreflightValidator::validateInputs()`, `Document::resolveOperation()==OpenApiOperationResolver::resolve()` (verified while surveying).