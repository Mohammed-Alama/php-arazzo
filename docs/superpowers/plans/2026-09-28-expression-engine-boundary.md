# Expression-engine boundary: evaluation returns to the expression package

> Date: 2026-09-28 · Branch: `phase-e-runner-oms` · Base: `124191f` (green)

## Context

The expression package owns the Arazzo expression grammar (parser + AST) and the public
`ExpressionEngine` facade, but the interpreter that turns that AST into values lives in the
evaluation package:

| Class (currently evaluation)                                     | Role                                                            |
| ---------------------------------------------------------------- | --------------------------------------------------------------- |
| `ExpressionEvaluator`                                            | the AST interpreter (expression→runtime value)                  |
| `SelectorEvaluator`                                              | selector dispatch (JSONPath / JSON-pointer / XPath)             |
| `JsonPathEvaluator`                                              | JSONPath primitive (wraps softcreatr/jsonpath)                  |
| `JsonPointer`                                                    | JSON-pointer primitive                                          |
| `Xpath\XpathEvaluator` + `Xpath\DomXpathEvaluator`               | XPath primitive (ext-dom)                                       |
| `Exceptions\SelectorEvaluationException`                         | selector failure signal                                         |
| `Interfaces\EvaluationInputInterface` + `Data\EvaluationContext` | the shared evaluation input contract all consumers type against |

This is a cross-package inconsistency, not just a naming one:

- `packages/expression/phpstan-baseline.neon` pre-seeds entries for
  `src/ExpressionEvaluator.php` and `src/JsonPathEvaluator.php` (and `src/Evaluation/Condition/*`)
  — stale relics of the _original_ layout where these files lived in expression before the
  evaluation package was sliced out of it.
- The seam guards were written for the restore: `RunnerFaceSeamTest` and the document
  `ExpressionSeamTest` already forbid runner/document code from importing
  `Expression\ExpressionEvaluator`, `Expression\JsonPathEvaluator`, `Expression\JsonPointer`,
  `Expression\SelectorEvaluator`, `Expression\Xpath\`, `Expression\Evaluation\`.
- Because `expression` compiles `evaluation`'s `ExpressionEvaluator`, the current layout makes
  the _evaluation_ package a dependency of the _expression_ package's own core capability, and
  evaluation's public face (`EvaluationEngineInterface`) is today the only way to run an
  expression without leaking evaluation access — yet `ExpressionEvaluator`'s own collaborator
  (`Interfaces\EvaluationInputInterface`) lives in evaluation, so expression cannot host it.
- The user directive: expose expression evaluation as **public methods on the expression package's
  public class** (`ExpressionEngine`), and move `ExpressionEvaluator`, `SelectorEvaluator`,
  `JsonPointer` — plus their transitive closure and the shared evaluation input types — into the
  expression package. Client audit (during planning) shows **every in-tree runtime caller of
  `evaluate`/`evaluateSelector` goes through `EvaluationEngine`** (`StepOutputExtractor`,
  `SubWorkflowInvoker`, `WorkflowEngine`, `StepExecutionWorker`, `CliRunner`, laravel job), while
  `ExpressionEngine` is today a static parse/analysis seam (`parseExpression` +
  `expressionReferences`) consumed by the document validator, runner inspectors, cli `ValidateCommand`,
  and laravel `FacadeBindings`. Per the user decision, `ExpressionEngine::evaluate` /
  `evaluateSelector` are shipped as the expression package's **public library API** for
  expression-level consumers (standalone tooling, document preflight, external users) — guarded by
  the package contract + `ExpressionEngineTest`; **no in-tree runtime caller is expected** and the
  two facades stay separate.

## Target architecture

- **Expression** owns all expression semantics: parser + AST (existing), the public face
  `ExpressionEngineInterface` / `ExpressionEngine` (existing, gaining `evaluate()` and
  `evaluateSelector()`), and the moved collaborators `ExpressionEvaluator`,
  `SelectorEvaluator`, `JsonPathEvaluator`, `JsonPointer`, `Xpath\XpathEvaluator`,
  `Xpath\DomXpathEvaluator`, `Exceptions\SelectorEvaluationException`, and the shared input
  types `Interfaces\EvaluationInputInterface` + `Data\EvaluationContext`.
- **Evaluation** keeps its cross-package seam `EvaluationEngineInterface` and the facade
  `EvaluationEngine`, plus the evaluation-only composition: `CriteriaEvaluator`,
  `ConditionEvaluator`, `StringInterpolator`, `PayloadReplacer`, and the JsonPath
  criterion/expression plugins + registries. `EvaluationEngine::evaluate()` /
  `evaluateSelector()` are implemented **directly on the moved expression collaborators**
  (`ExpressionEvaluator`, `SelectorEvaluator`) — no facade-to-facade delegation. Criteria /
  interpolation / payload replacement and the raw jsonPath/jsonPointer/xpath helpers compose
  the same moved primitives.
- `softcreatr/jsonpath` moves from the evaluation require to the expression require (the moved
  `JsonPathEvaluator` is its only consumer; evaluation now reaches it through expression).
- Downstream consumers (runner src + tests, cli, laravel) change exactly one import each:
  `Alama\Arazzo\Evaluation\Data\EvaluationContext` → `Alama\Arazzo\Expression\Data\EvaluationContext`
  (and the input-interface import where a double implements `evaluate`). No behavioral change.

### Architectural invariants that must hold afterward

1. `expression does not use evaluation` (`packages/expression/tests/ArchTest.php`) — critical:
   no moved file may import `Alama\Arazzo\Evaluation`.
2. `evaluation does not touch expression internals` — evaluation composes the moved expression
   collaborators (`ExpressionEvaluator`, `SelectorEvaluator`, primitives, `Data\*`,
   `Interfaces\*`) directly, but stays off `Expression\ExpressionEngine`, `Expression\Lexer`,
   and `Expression\Ast` (guard renamed + strengthened in `packages/evaluation/tests/ArchTest.php`).
3. Runner/document seam tests: runner may import `ExpressionEngine` / `ExpressionEngineInterface`
   / `Expression\Data\EvaluationContext` / `Expression\Interfaces\EvaluationInputInterface` but
   nothing else expression-internal. The forbidden lists already name these classes — they now
   become _active_, so no guard edit is required (only a verification pass).
4. **The two facades never call each other**: `EvaluationEngine` does not import or construct
   `ExpressionEngine`, and `ExpressionEngine` (expression package) never imports
   `Alama\Arazzo\Evaluation`. `ExpressionEngine::evaluate()` / `evaluateSelector()` are the
   expression package's standalone public API, exercised by expression's own tests.

## Design decisions (locked)

1. **Full move** (user-confirmed): `ExpressionEvaluator`, `SelectorEvaluator`, `JsonPointer` +
   transitive closure (`JsonPathEvaluator`, `Xpath\XpathEvaluator`, `Xpath\DomXpathEvaluator`,
   `Exceptions\SelectorEvaluationException`).
2. **Shared input types move to expression** (user-confirmed): `Interfaces\EvaluationInputInterface`
   and `Data\EvaluationContext` land in expression (`Alama\Arazzo\Expression\Interfaces\` /
   `Alama\Arazzo\Expression\Data\`). This is what lets `ExpressionEvaluator` type its input without
   depending on evaluation.
3. **Public methods on the public class (library API)**: `ExpressionEngine` gains
   `evaluate(Expression $expression, EvaluationInputInterface $context): mixed` and
   `evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed`,
   declared on `ExpressionEngineInterface` (its only implementor is `ExpressionEngine`). These are
   the expression package's public API for **expression-level consumers**; the interface docblock
   ("static Arazzo expression parsing and analysis") is updated to reflect that evaluation is also
   exposed. `ExpressionEngine`'s ctor becomes
   `(?ExpressionParser $parser = null, ?ExpressionEvaluator $evaluator = null, ?SelectorEvaluator $selectors = null)`,
   keeping `new ExpressionEngine()` valid (laravel `FacadeBindings` binds
   `ExpressionEngineInterface` → `ExpressionEngine` singleton and picks up the new methods
   automatically), and sharing one `ExpressionEvaluator` between the evaluator and the selector
   collaborators (all stateless; shared wiring avoids drift).
4. **EvaluationEngine keeps its full public face** (`evaluate`, `resolveValue`, `evaluateCriteria`,
   `evaluateSuccessCriteria`, `evaluateSelector`, `queryXPath`, `supportedXPathVersions`,
   `interpolate`, `replacePayload`, `jsonPath`, `jsonPointer`) and its existing 4-param ctor
   (defaults, order, and names unchanged — every named-arg call site keeps working).
   `evaluate`/`evaluateSelector` are implemented **directly on the moved collaborators**: the
   plugin branch stays inside `evaluate()`, `evaluateSelector()` keeps its lazy `selectors()`
   helper, and `jsonPath`/`jsonPointer`/`queryXPath` call the moved primitives through imports.
   `EvaluationEngine` never imports or constructs `ExpressionEngine`.
5. **Tests travel with the code**: `SelectorEvaluatorTest`, `JsonPathEvaluatorTest`,
   `Xpath/DomXpathEvaluatorTest`, and runner's `Execution/ExpressionEvaluatorTest` move into
   `packages/expression/tests/` under `Alama\Arazzo\Tests\Expression`. Every other evaluation test
   that touches the moved classes just repoints its imports.
6. `softcreatr/jsonpath` require moves evaluation → expression; `composer.lock` refreshed for the
   two path repos (`composer update alama/arazzo-expression alama/arazzo-evaluation --no-scripts`).
7. Baselines: expression's `src/ExpressionEvaluator.php` (7) + `src/JsonPathEvaluator.php` (2)
   entries become **real** (they were phantoms) — expect them to match. The 3
   `src/Evaluation/Condition/*` phantom entries stay untouched. Evaluation's baseline has no
   entries for the moving files. Never delete a tracked baseline; refresh counts/messages only.
8. Docs regenerate via `composer run docs`; `GeneratedDocsSnapshotTest` pins expectations and may
   need its pinned snapshot updated in the same commit (public-api gains the two methods;
   package-contracts reflects the new class owners).

## Verification (gates)

Run from the worktree root after each task; a task is done only when its suites pass.

```bash
composer run test          # core + runner + laravel suites
composer run format        # pint
composer run analyse       # phpstan (tracked baseline, do not delete)
composer run docs          # regenerate docs/generated/* (no hand-edits)
make verify                # full gate stack
```

Nothing is committed or pushed until the user approves.

## Tasks

### Task 1 — Move the classes into the expression package (git mv + namespace + self-import surgery)

`git mv` from `packages/evaluation/src` into `packages/expression/src`, then rewrite each
namespace to `Alama\Arazzo\Expression` (keeping the sub-paths:

| From (`Alama\Arazzo\Evaluation\...`)             | To (`Alama\Arazzo\Expression\...`)               |
| ------------------------------------------------ | ------------------------------------------------ |
| `src/ExpressionEvaluator.php`                    | `src/ExpressionEvaluator.php`                    |
| `src/SelectorEvaluator.php`                      | `src/SelectorEvaluator.php`                      |
| `src/JsonPointer.php`                            | `src/JsonPointer.php`                            |
| `src/JsonPathEvaluator.php`                      | `src/JsonPathEvaluator.php`                      |
| `src/Xpath/XpathEvaluator.php`                   | `src/Xpath/XpathEvaluator.php`                   |
| `src/Xpath/DomXpathEvaluator.php`                | `src/Xpath/DomXpathEvaluator.php`                |
| `src/Exceptions/SelectorEvaluationException.php` | `src/Exceptions/SelectorEvaluationException.php` |
| `src/Interfaces/EvaluationInputInterface.php`    | `src/Interfaces/EvaluationInputInterface.php`    |
| `src/Data/EvaluationContext.php`                 | `src/Data/EvaluationContext.php`                 |

Per-file surgery after the namespace rewrite:

- `ExpressionEvaluator.php`: only `use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;`
  → `use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;`. Its fourteen
  `Alama\Arazzo\Expression\{Parser, Ast\*}` imports become same-namespace — they still resolve;
  leave them (pint run will clean nothing, that is fine).
- `SelectorEvaluator.php`: delete the three lines
  `use Alama\Arazzo\Evaluation\{Data\EvaluationContext, Exceptions\SelectorEvaluationException, Xpath\XpathEvaluator};`
  — now same-namespace. `JsonPathEvaluator`, `JsonPointer`, `ExpressionEvaluator`, and
  `SelectorEvaluationException` are referenced unqualified and stay valid. Keep the
  `Alama\Arazzo\Contracts\Spec\*` imports.
- `JsonPathEvaluator.php`, `Xpath/*.php`, `Exceptions/SelectorEvaluationException.php`,
  `Data/EvaluationContext.php`, `Interfaces/EvaluationInputInterface.php`: namespace rewrite only
  (the sub-namespaces `Xpath`, `Exceptions`, `Data`, `Interfaces` are preserved under
  `Alama\Arazzo\Expression`).
- Verify green standalone: `composer run test-expression`, `composer run format`.
- **Do not touch any consumer yet** — `git mv` must land first so `git` records the renames and
  the diff stays readable.

### Task 2 — Expose evaluation on the expression public face

- `packages/expression/src/Interfaces/ExpressionEngineInterface.php`: add
  `evaluate(Expression $expression, EvaluationInputInterface $context): mixed` and
  `evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed`
  with docblocks mirroring the evaluation-interface wording. Imports:
  `Alama\Arazzo\Contracts\Spec\{Expression, Selector}`,
  `Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface`,
  `Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface`.
- `packages/expression/src/ExpressionEngine.php`: change ctor to
  `(?ExpressionParser $parser = null, ?ExpressionEvaluator $evaluator = null, ?SelectorEvaluator $selectors = null)`
  with readonly props assigned in the body (`$this->selectors ??= new SelectorEvaluator(new Xpath\DomXpathEvaluator(), $this->evaluator)`).
  Add the two methods delegating to `$this->evaluator->evaluate(...)` and
  `$this->selectors->evaluate(...)`. Imports as above + no `Alama\Arazzo\Evaluation` anywhere
  (arch rule 1 must stay green).
- **Rationale for adding these methods**: they are the expression package's public library API for
  expression-level consumers; the audit confirms no in-tree runtime evaluator needs them (all use
  `EvaluationEngineInterface`) — do **not** wire `EvaluationEngine` to them (invariant 4). The
  laravel `FacadeBindings` `ExpressionEngineInterface` singleton (line 31) picks the methods up
  automatically; existing cli `ValidateCommand` `new ExpressionEngine()` keeps working via the
  defaulted ctor.
- Add facade-coverage assertions in `packages/expression/tests/ExpressionEngineTest.php`: a real
  `$engine->{parse,lookup}` expression evaluated through `evaluate()`; a selector evaluated
  through `evaluateSelector()`; assert the methods are declared on `ExpressionEngineInterface`.
- Verify: `composer run test-expression`, `composer run format`, `composer run analyse`.
- Note: runner/document seam tests already tolerate `Expression\*` imports of the _public face_ +
  `Data\*` + `Interfaces\*`; nothing to change at this point.

### Task 3 — Rewire the evaluation package

- `packages/evaluation/src/EvaluationEngineInterface.php`: `use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;`
  → `use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;` (signatures unchanged).
- `packages/evaluation/src/EvaluationEngine.php`:
  - Repoint imports: `Evaluation\Data\EvaluationContext`, `Evaluation\Interfaces\EvaluationInputInterface`,
    `Evaluation\Xpath\DomXpathEvaluator` → the `Expression\...` equivalents.
  - Add imports: `Alama\Arazzo\Expression\ExpressionEvaluator`, `Alama\Arazzo\Expression\SelectorEvaluator`,
    `Alama\Arazzo\Expression\JsonPathEvaluator`, `Alama\Arazzo\Expression\JsonPointer`.
    **No `ExpressionEngine` import** — the facades stay separate (invariant 4).
  - Ctor: **unchanged** — the existing 4 params (defaults / order / names) stay exactly as-is;
    `$evaluator` / `$xpath` types now resolve to the expression classes via imports.
  - `evaluate()`: keep the plugin branch (registry resolve → `$plugin->evaluate(...)`); the
    fallback line 47 stays `return $this->evaluator->evaluate($expression, $context);`.
  - `evaluateSelector()`: keep the `selectors()` helper (line 134), the
    `private ?SelectorEvaluator $selectorEvaluator` property (line 36), and the body
    `$this->selectors()->evaluate($selector, $context, $stepId)` — the collaborators now resolve
    to expression classes.
  - `jsonPath()` (line 121) and `jsonPointer()` (line 126) stay as-is; they now resolve through the
    new imports. `criteria()` / `interpolator()` keep composing `$this->evaluator`.
- `packages/evaluation/src/CriteriaEvaluator.php`: repoint
  `Evaluation\Data\EvaluationContext` → `Expression\Data\EvaluationContext` and
  `Evaluation\Xpath\{DomXpathEvaluator,XpathEvaluator}` → `Expression\Xpath\{DomXpathEvaluator,XpathEvaluator}`;
  add `use Alama\Arazzo\Expression\ExpressionEvaluator;` and `use Alama\Arazzo\Expression\JsonPathEvaluator;`
  (used unqualified at lines 33, 147).
- `packages/evaluation/src/Condition/ConditionEvaluator.php`: repoint `Evaluation\Data\EvaluationContext`
  → `Expression\Data\EvaluationContext` and `Evaluation\ExpressionEvaluator` → `Expression\ExpressionEvaluator`.
- `packages/evaluation/src/StringInterpolator.php`: repoint `Evaluation\Data\EvaluationContext` →
  `Expression\Data\EvaluationContext`; add `use Alama\Arazzo\Expression\ExpressionEvaluator;` for the
  ctor param.
- `packages/evaluation/src/PayloadReplacer.php`: repoint `Evaluation\Xpath\DomXpathEvaluator` →
  `Expression\Xpath\DomXpathEvaluator`; add `use Alama\Arazzo\Expression\JsonPathEvaluator;` (line 95).
- `packages/evaluation/src/Plugins/JsonPathCriterionPlugin.php` + `JsonPathExpressionPlugin.php`:
  `use Alama\Arazzo\Evaluation\JsonPathEvaluator;` → `use Alama\Arazzo\Expression\JsonPathEvaluator;`.
- Registries: unchanged (they reference the plugins in-namespace).
- Verify: `composer run test-core` (evaluation lives in the core suite), `composer run format`,
  `composer run analyse`.

### Task 4 — Shield consumers (runner src, runner/cli/laravel tests, doubles)

- 7 runner src files: replace `use Alama\Arazzo\Evaluation\Data\EvaluationContext;` with
  `use Alama\Arazzo\Expression\Data\EvaluationContext;`:
  `packages/runner/src/Execution/{StepOutcomeHandler,WorkflowEngine,StepExecutionWorker,SubWorkflowInvoker,StepOutputExtractor}.php`
  and `packages/runner/src/Protocol/{AsyncApiStepExecutor,SubWorkflowStepExecutor}.php`.
- Test doubles (the 8 `EvaluationEngineInterface` fakes + any `evaluate(Expression,
EvaluationInputInterface)` implementers) that import
  `Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface` or
  `Alama\Arazzo\Evaluation\Data\EvaluationContext` — repoint to the `Expression\...` equivalents
  (hit list from `rg -n 'Evaluation\\\\(Interfaces\\\\EvaluationInputInterface|Data\\\\EvaluationContext)' packages/runner packages/cli packages/laravel --glob '!composer.lock'`).
- `packages/runner/tests/Execution/ArazzoCriteriaEvaluatorTest.php`: `Alama\Arazzo\Evaluation\ExpressionEvaluator`
  → `Alama\Arazzo\Expression\ExpressionEvaluator`.
- Any remaining `rg -n 'Alama\\\\Arazzo\\\\Evaluation\\\\' packages/runner packages/cli packages/laravel tests scripts --glob '!composer.lock'`
  hits that name the moved classes must be repointed; the only allowed
  `Alama\Arazzo\Evaluation\*` references left are the evaluation _package-internal_ ones and the
  `EvaluationEngineInterface` import.
- Verify: `composer run test`, `composer run format`, `composer run analyse`.

### Task 5 — Move tests with their code + add facade tests + update arch guards

- `git mv` into expression tests, namespace → `Alama\Arazzo\Tests\Expression` (+ `\Xpath` for the
  xpath test):
    - `packages/evaluation/tests/SelectorEvaluatorTest.php` → `packages/expression/tests/SelectorEvaluatorTest.php`
    - `packages/evaluation/tests/JsonPathEvaluatorTest.php` → `packages/expression/tests/JsonPathEvaluatorTest.php`
    - `packages/evaluation/tests/Xpath/DomXpathEvaluatorTest.php` → `packages/expression/tests/Xpath/DomXpathEvaluatorTest.php`
    - `packages/runner/tests/Execution/ExpressionEvaluatorTest.php` → `packages/expression/tests/ExpressionEvaluatorTest.php`
      Repoint their `Alama\Arazzo\Evaluation\{Data\EvaluationContext, ExpressionEvaluator, Xpath\*, ...}`
      imports to the expression FQCNs.
- Remaining evaluation tests that reference moved classes (analyse/grep will confirm the exact set,
  expected: `ConditionEvaluatorTest`, `EvaluationEngineTest`, `StringInterpolatorTest`,
  `EvaluationEngineCapabilitiesTest`, `EvaluationEngineValueResolutionTest`) — repoint imports only.
- `packages/expression/tests/ExpressionEngineTest.php`: post-Task-2 facade assertions cover
  `ExpressionEngine::evaluate()` / `evaluateSelector()` as the expression package's public API.
  `packages/evaluation/tests/EvaluationEngineTest.php`: **no routing assertion** — keep its
  existing direct-composition coverage (plugin branch + raw `ExpressionEvaluator` fallback)
  green exactly as-is.
- `packages/evaluation/tests/ArchTest.php`: rename `'evaluation consumes only parser from expression'`
  → `'evaluation uses expression collaborators without touching the engine, lexer, or AST'`, add
  `->not->toUse('Alama\Arazzo\Expression\ExpressionEngine')`, keep
  `->not->toUse('Alama\Arazzo\Expression\Lexer')`, and add
  `->not->toUse('Alama\Arazzo\Expression\Ast')`. Leave the expression ArchTest untouched.
- `RunnerFaceSeamTest` + document `ExpressionSeamTest`: no edits needed (forbidden lists already
  name these classes); confirm they still pass — this is the first change where those entries
  become live.
- Verify: `composer run test`, `composer run format`, `composer run analyse`.

### Task 6 — Composer deps, lock, docs, full gates

- `packages/expression/composer.json` `require`: add `"softcreatr/jsonpath": "^0.10.0"` (after
  `psr/log`, following the sort).
- `packages/evaluation/composer.json` `require`: remove `"softcreatr/jsonpath": "^0.10.0"`.
- `composer update alama/arazzo-expression alama/arazzo-evaluation --no-scripts --no-interaction`
  (refreshes path-repo metadata; softcreatr is already installed so no downloads).
- `composer run docs`; then run the docs test suite — if `GeneratedDocsSnapshotTest` fails on
  pinned expectations, regenerate + commit the updated pinned snapshot (public-api gains
  `ExpressionEngine::evaluate`/`evaluateSelector`; package-contracts/docgen reflect new owners).
  Follow `docs/` conventions: never hand-edit `docs/generated`.
- `composer run analyse`: confirm expression baseline's `src/ExpressionEvaluator.php` (7 entries)
    - `src/JsonPathEvaluator.php` (2 entries) now match; leave the 3 `src/Evaluation/Condition/*`
      phantom entries. If analyse flags any moved file in either package (e.g. `SelectorEvaluator`,
      `Xpath/*`, `EvaluationContext`), refresh the owning package's baseline (update counts/messages —
      never delete the tracked file).
- Sweep for the final state (expect zero hits outside evaluation package internals and the
  evaluation-facade import in runner/laravel):
  `rg -n 'Alama\\\\Arazzo\\\\Evaluation\\\\' packages scripts --glob '!composer.lock'`.
- `make verify` (exit 0).
- Optional hygiene: `docs/superpowers/plans/2026-09-28-request-pipeline-boundaries.md` stays as an
  historical record; no update needed.

### Task 7 — Final checkpoint (approval + commit)

1. Run all gates (see Verification) and confirm green + `git status`/`git diff` scoped to this work.
2. Present the change summary to the user (moved-file table, arch diff, gates output).
3. Commit in small logical commits (Task 1 … Task 6) with conventional messages
   (`chore(expression): ...`, `refactor(evaluation): ...`); push only after explicit user approval.

## Rollback

We are in an isolated worktree on branch `phase-e-runner-oms` over green base `124191f`. Any
failed step is reverted with `git restore .` + `git clean` of untracked stubs; the tracked
`phpstan-baseline.neon` files are never deleted (restore from HEAD first if touched). `git mv`
is used throughout so the series of moves can be inspected as renames, not delete+add.
