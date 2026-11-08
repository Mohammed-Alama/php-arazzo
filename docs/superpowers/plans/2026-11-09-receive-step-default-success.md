# AsyncAPI `receive` Step Default Success Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give an AsyncAPI `receive` step with no `successCriteria` a defined default outcome ("a message was received"), make that default effective in the runner, and report missing criteria from the validator.

**Architecture:** `CorrelationResumer` records an explicit `messageReceived => true` marker when a message resumes a step. `CriteriaEvaluator::evaluateSuccessCriteria()` treats an empty-criteria receive step as successful only when that marker is present. `EvaluationEngine::evaluateSuccessCriteria()` delegates back to the criteria evaluator so defaults run in production. A strict-aware validator rule reports empty-criteria receive steps.

**Tech Stack:** PHP 8.4, Pest, PHPStan, Pint, PHPMD.

**Spec:** `docs/superpowers/specs/2026-11-09-receive-step-default-success-design.md`

## Global Constraints

- PHP 8.4; every new file declares `strict_types=1`.
- Receive steps require Arazzo 1.1 (`action`/`channelPath`/`correlationId` are 1.1-only). The issue metadata says 1.0.1; the spec reconciles this by treating 1.0 documents as out of scope for the new rule.
- Do not change the HTTP 2xx rule itself; only make it reachable again through `EvaluationEngine`.
- Missing criteria remains legal at runtime; the validator rule is strict-aware advice, not an unconditional error.
- Run `vendor/bin/pest <path>` for focused tests; full gates are `composer test`, `composer analyse`, `make phpmd`, and `vendor/bin/pint --test`.
- Commit after each task.

## Review Focus

These are the inputs the spec implies but whose tests are easy to skip; each is pinned in the task that owns the code.

1. A receive step with a step record that exists but has no `messageReceived` key (pre-existing persisted state) must fail, not crash.
2. A malformed step record (non-array) must fail the default safely.
3. An empty-criteria **non-operation, non-receive** step (workflow/RPC/interaction) must still pass; the new default must not leak.
4. A 1.0 document with `action: receive` must produce only the existing `asyncapi.fields_require_11` diagnostic, not the new rule's.
5. Disabling the rule through `RuleSet::$disabled` must suppress its diagnostic.

---

### Task 1: Receive default in `CriteriaEvaluator`

**Files:**
- Modify: `packages/evaluation/src/CriteriaEvaluator.php:70-83,231-246`
- Test: `packages/evaluation/tests/ReceiveStepDefaultTest.php`

**Interfaces:**
- Consumes: `StepTarget::$action`, `StepIo::$successCriteria`, `WorkflowContextInterface::getSteps(): array`.
- Produces: `private static function hasReceivedMessage(WorkflowContextInterface $context, string $stepId): bool` on `CriteriaEvaluator`.

- [ ] **Step 1: Write the failing test**

Create `packages/evaluation/tests/ReceiveStepDefaultTest.php` in namespace `Alama\Arazzo\Tests\Evaluation` with unique helpers:

```php
function receiveDefaultStep(array $criteria = []): Step
{
    return StepFactory::async('wait', null, new StepFlow(), new StepIo(successCriteria: $criteria),
        'receive', 'channels/rides/created');
}

function receiveDefaultContext(array $record): WorkflowContext
{
    return (new WorkflowContext('def_1'))->withStepResult('wait', $record);
}
```

Assertions:

```php
it('passes an empty-criteria receive step when a message arrived', function (): void {
    $context = receiveDefaultContext(['messageReceived' => true]);
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $context))->toBeTrue();
});

it('fails an empty-criteria receive step when no record exists', function (): void {
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), new WorkflowContext('def_1')))->toBeFalse();
});

it('fails an empty-criteria receive step when the marker is false', function (): void {
    $context = receiveDefaultContext(['messageReceived' => false]);
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $context))->toBeFalse();
});

it('fails safely when the step record is not an array', function (): void {
    $malformed = new WorkflowContext('def_1', steps: ['wait' => 'not-an-array']);
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $malformed))->toBeFalse();
});

it('still lets explicit receive criteria decide the outcome', function (): void {
    $fail = new SuccessCriterion(null, '$response.body.ok == false', CriterionType::Simple);
    $context = receiveDefaultContext([
        'messageReceived' => true,
        'response' => ['statusCode' => 200, 'headers' => [], 'body' => ['ok' => true]],
    ]);
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep([$fail]), $context))->toBeFalse();
});

it('keeps the operation 2xx default', function (): void {
    $step = StepFactory::http('op', null, new StepFlow(), new StepIo(), operationId: 'findPet');
    $ok = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 200, 'headers' => [], 'body' => []]);
    $error = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, $ok))->toBeTrue()
        ->and(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, $error))->toBeFalse();
});

it('does not apply a default to other empty-criteria targets', function (): void {
    $step = StepFactory::workflow('child', null, new StepFlow(), new StepIo(), 'child_workflow');
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, new WorkflowContext('def_1')))->toBeTrue();
});
```

`receiveDefaultEvaluator()` returns `new CriteriaEvaluator(new ExpressionEngine())`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests/ReceiveStepDefaultTest.php`
Expected: FAIL — the marker/no-record cases return `true` because empty criteria pass unconditionally.

- [ ] **Step 3: Implement the receive branch and marker guard**

In `evaluateSuccessCriteria()`, after the operation branch:

```php
if ($step->target->action === 'receive' && $step->io->successCriteria === []) {
    return self::hasReceivedMessage($context, $step->stepId);
}
```

Add:

```php
private static function hasReceivedMessage(WorkflowContextInterface $context, string $stepId): bool
{
    $record = $context->getSteps()[$stepId] ?? null;

    return is_array($record) && ($record['messageReceived'] ?? false) === true;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests/ReceiveStepDefaultTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add packages/evaluation/src/CriteriaEvaluator.php packages/evaluation/tests/ReceiveStepDefaultTest.php
git commit -m "feat(evaluation): define default success for receive steps"
```

---

### Task 2: Restore default-outcome wiring in `EvaluationEngine`

**Files:**
- Modify: `packages/evaluation/src/EvaluationEngine.php:80-83`
- Test: `packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php`

**Interfaces:**
- Consumes: `CriteriaEvaluator::evaluateSuccessCriteria(Step, WorkflowContextInterface, ?ArazzoDocument): bool`.
- Produces: `EvaluationEngine::evaluateSuccessCriteria()` delegates to the criteria evaluator.

- [ ] **Step 1: Write the failing test**

Create `packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php` (unique helper names, e.g. `engineDefaultStep`):

```php
it('applies the receive default through the evaluation engine', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $step = StepFactory::async('wait', null, new StepFlow(), new StepIo(), 'receive', 'channels/x');

    $received = (new WorkflowContext('def_1'))->withStepResult('wait', ['messageReceived' => true]);

    expect($engine->evaluateSuccessCriteria($step, $received))->toBeTrue()
        ->and($engine->evaluateSuccessCriteria($step, new WorkflowContext('def_1')))->toBeFalse();
});

it('applies the operation 2xx default through the evaluation engine', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $step = StepFactory::http('op', null, new StepFlow(), new StepIo(), operationId: 'findPet');

    $ok = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 200, 'headers' => [], 'body' => []]);
    $error = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    expect($engine->evaluateSuccessCriteria($step, $ok))->toBeTrue()
        ->and($engine->evaluateSuccessCriteria($step, $error))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php`
Expected: FAIL — the operation 500 case returns `true`, proving the bypass.

- [ ] **Step 3: Delegate**

Replace the body of `EvaluationEngine::evaluateSuccessCriteria()` with:

```php
return $this->criteria()->evaluateSuccessCriteria($step, $context, $document);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add packages/evaluation/src/EvaluationEngine.php packages/evaluation/tests/EvaluationEngineDefaultOutcomeTest.php
git commit -m "fix(evaluation): route engine success checks through default outcomes"
```

---

### Task 3: Record message arrival in `CorrelationResumer`

**Files:**
- Modify: `packages/runner/src/Async/CorrelationResumer.php:123-127`
- Test: `packages/runner/tests/Async/CorrelationResumerTest.php` (extend)

**Interfaces:**
- Produces: the persisted receive step record contains `messageReceived => true` alongside `statusCode`, `response`, and `outputs`.

- [ ] **Step 1: Write the failing test**

Extend the existing "falls back to a 200 status…" test (or add a sibling using the same helpers):

```php
it('marks a resumed receive step as having received a message', function (): void {
    // same setup as "falls back to a 200 status…"
    $resumer->resume('corr_1', ['body' => ['x' => 1]]);

    expect($stateStore->saved['exec_1']['steps']['wait-for-ride']['messageReceived'])->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/runner/tests/Async/CorrelationResumerTest.php`
Expected: FAIL — `Undefined array key "messageReceived"`.

- [ ] **Step 3: Add the marker**

In `CorrelationResumer::resume()`, extend the `withStepResult` array:

```php
'messageReceived' => true,
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/runner/tests/Async/CorrelationResumerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/Async/CorrelationResumer.php packages/runner/tests/Async/CorrelationResumerTest.php
git commit -m "feat(runner): mark resumed receive steps as message-received"
```

---

### Task 4: Strict-aware validator rule

**Files:**
- Create: `packages/document/src/Validator/Rules/ReceiveStepSuccessCriteriaRule.php`
- Modify: `packages/document/src/Validator/RuleSet.php` (import + registration)
- Modify: `packages/document/tests/Validator/RuleSetTest.php` (extend)
- Test: `packages/document/tests/Validator/Rules/ReceiveStepSuccessCriteriaRuleTest.php`

**Interfaces:**
- Consumes: `RuleSet::$strict`, `StepTarget::$action`, `StepIo::$successCriteria`, `ErrorCollector::error()/warning()`.
- Produces: rule code `step.receive_missing_success_criteria`; constructor `__construct(public readonly bool $strict = true)`.

- [ ] **Step 1: Write the failing tests**

Create the rule test file with a unique helper:

```php
function receiveRuleDocument(SpecVersion $sv, ?string $action, array $criteria = []): ArazzoDocument
{
    $step = new Step('wait', null, new StepTarget(action: $action, channelPath: 'channels/x'),
        new StepFlow(), new StepIo(successCriteria: $criteria));

    return new ArazzoDocument(
        arazzo: $sv->value, info: new Info('t', null, null, '1'), sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []), specificationExtensions: [], specVersion: $sv,
    );
}
```

Assertions:

```php
it('errors on an empty-criteria receive step in strict mode', function (): void {
    $errors = new ErrorCollector();
    $doc = receiveRuleDocument(SpecVersion::V1_1, 'receive');
    (new ReceiveStepSuccessCriteriaRule(strict: true))->check($doc, SymbolTable::build($doc), $errors);

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('step.receive_missing_success_criteria')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/successCriteria');
});

it('warns on an empty-criteria receive step in non-strict mode', function (): void {
    // same doc; assert warnings count 1 and errors empty
});

it('accepts a receive step that declares criteria', function (): void {
    $criterion = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    // strict rule; assert errors and warnings are both empty
});

it('ignores send steps', function (): void {
    // action 'send', empty criteria; assert no diagnostics
});

it('ignores 1.0 documents so the version rule owns the error', function (): void {
    // SpecVersion::V1_0 + action 'receive'; assert no diagnostics from this rule
});
```

Extend `RuleSetTest.php`:

```php
it('registers the receive missing-criteria rule', function (): void {
    $codes = array_map(fn (Rule $r) => $r->code(),
        RuleSet::default(new ExpressionEngine())->activeRules());

    expect($codes)->toContain('step.receive_missing_success_criteria');
});

it('lets the receive missing-criteria rule be disabled', function (): void {
    $codes = array_map(fn (Rule $r) => $r->code(),
        RuleSet::default(new ExpressionEngine(), disabled: ['step.receive_missing_success_criteria'])->activeRules());

    expect($codes)->not->toContain('step.receive_missing_success_criteria');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest packages/document/tests/Validator/Rules/ReceiveStepSuccessCriteriaRuleTest.php packages/document/tests/Validator/RuleSetTest.php`
Expected: FAIL — class not found / code absent from the rule set.

- [ ] **Step 3: Implement the rule**

```php
final class ReceiveStepSuccessCriteriaRule implements Rule
{
    public function __construct(public readonly bool $strict = true) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $workflow) {
            foreach ($workflow->steps as $si => $step) {
                if ($step->target->action !== 'receive' || $step->io->successCriteria !== []) {
                    continue;
                }

                $message = "receive step '{$step->stepId}' has no successCriteria; the default only requires a message to arrive.";
                $path = "/workflows/{$wi}/steps/{$si}/successCriteria";

                if ($this->strict) {
                    $errors->error($this->code(), $message, $path);
                } else {
                    $errors->warning($this->code(), $message, $path);
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.receive_missing_success_criteria';
    }
}
```

Register in `RuleSet::default()` as `new ReceiveStepSuccessCriteriaRule($strict),`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest packages/document/tests/Validator/Rules/ReceiveStepSuccessCriteriaRuleTest.php packages/document/tests/Validator/RuleSetTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/document/src/Validator/Rules/ReceiveStepSuccessCriteriaRule.php \
  packages/document/src/Validator/RuleSet.php \
  packages/document/tests/Validator/Rules/ReceiveStepSuccessCriteriaRuleTest.php \
  packages/document/tests/Validator/RuleSetTest.php
git commit -m "feat(document): report receive steps without success criteria"
```

---

### Task 5: Documentation and full gates

**Files:**
- Modify: `docs/architecture/04-expression-evaluation.md` (success-criteria section)
- Modify: `CHANGELOG.md` (Unreleased / next section)

**Interfaces:** none.

- [ ] **Step 1: Document the defaults**

Add to the success-criteria section of `docs/architecture/04-expression-evaluation.md`:

- Operation step + no criteria → any 2xx response.
- Receive step + no criteria → success only when the step record carries `messageReceived => true`; otherwise failure.
- Explicit criteria always win.
- `EvaluationEngine::evaluateSuccessCriteria()` delegates to `CriteriaEvaluator` so both defaults apply at runtime.

Add a `CHANGELOG.md` entry naming the receive default, the `messageReceived` marker, the restored engine delegation, and the new `step.receive_missing_success_criteria` rule.

- [ ] **Step 2: Run focused suites**

Run:
```bash
vendor/bin/pest packages/evaluation/tests packages/document/tests packages/runner/tests
```
Expected: PASS, no failures.

- [ ] **Step 3: Run full gates**

Run:
```bash
composer analyse
make phpmd
vendor/bin/pint --test
composer test
```
Expected: all exit 0. If `composer test` regenerates docs, commit the generated diff.

- [ ] **Step 4: Commit**

```bash
git add docs/architecture/04-expression-evaluation.md CHANGELOG.md docs/generated
git commit -m "docs: document receive-step default success and validator rule"
```

---

## Self-Review Notes

- Spec §3.1/§3.2 → Tasks 1 and 3; §3.4 → Task 2; §4 → Task 4; §3.1 documentation → Task 5.
- Review Focus items map to Task 1 (1, 2, 3), Task 4 (4), and Task 4's RuleSet test (5).
- Types match across tasks: `messageReceived` is the single key name; `hasReceivedMessage()` is private and only used in Task 1.
