# Software Factory Playbook — V1

> **Purpose:** An operable, stage-by-stage runbook for the idea→ship loop in the `php-arazzo` monorepo. For each stage: which skill(s) to invoke, what artifact it produces, the exact handoff into `file-issue`/`gh` and the intake gate, and how to verify it's done.
>
> **Audience:** solo maintainer, human-in-the-loop. AFK autonomy is a future aspiration, not V1.
>
> **Companions:** [`docs/SKILLS-REPORT.md`](SKILLS-REPORT.md) (general skills map — this playbook cross-references it, does not duplicate it) · [`docs/intent/software-factory-playbook.md`](intent/software-factory-playbook.md) (confirmed intent) · [`docs/issues/triage-kinds.md`](issues/triage-kinds.md) (kind/level mapping) · [`CLAUDE.md`](../CLAUDE.md) (commands) · [`CONTEXT-MAP.md`](../CONTEXT-MAP.md) (layering).

---

## Quick path

| # | Stage | Primary skill(s) | Artifact | Next |
|---|---|---|---|---|
| 0 | Intake spine (entry) | `arazzo-detective` → `file-issue` → `gh` shim → CI gate → `triage` | Conforming GitHub issue | Stage 1 or 7 |
| 1 | Idea → Spec | `grill-with-docs`, `to-spec`, `spec-driven-development` | Spec (`docs/superpowers/specs/...`) | Stage 2 |
| 2 | Spec → Tickets | `to-tickets`, `planning-and-task-breakdown` | Tickets (issues via `file-issue`) | Stage 3 |
| 3 | Design Review (Architecture) | `balanced-coupling`, `improve-codebase-architecture`, `codebase-design`, `domain-modeling` | Architecture decision record + design sign-off | Stage 4 |
| 4 | Ticket → Code | `implement`, `tdd`, `incremental-implementation`, `falsification-testing` | Code + tests on a branch | Stage 5 |
| 5 | Code → Review | `code-review`, `code-review-and-quality` | Review verdict | Stage 6 |
| 6 | Review → PR | `pr-prep`, `git-workflow-and-versioning` | Pull request | Stage 7 |
| 7 | PR → Release | `release`, `shipping-and-launch` | Tagged release | — |
| 8 | Bug path | `diagnosing-bugs`, `debugging-and-error-recovery` | Fix + regression test | Stage 5 |
| 9 | Quality bar | `constraint-driven-development`, `falsification-testing` | CONSTRAINTS.md / verified suite | Gates stages 4–6 |

Supplement seams are wired inline as `> Supplement:` callouts at stages 4, 5, 7 (and the quality bar at stage 9).

---

## How to use this playbook

1. Open the stage you're at. Each stage lists **Skill(s)**, **Inputs**, **Procedure**, **Artifact**, **Handoff**, **Verification**.
2. Invoke skills via the OpenCode `skill` tool with the bare id (e.g. `id: "file-issue"`). Drop any `superpowers:` prefix.
3. Copy the fenced commands verbatim; they use the repo's real paths and flags.
4. Do not skip **Verification** — it is the gate between stages.
5. When a stage produces an issue, route it through the **Intake spine (§0)** so it conforms before it reaches GitHub.
6. For anything not covered here, see [`docs/SKILLS-REPORT.md`](SKILLS-REPORT.md) (skill map) and [`docs/issues/triage-kinds.md`](issues/triage-kinds.md) (kind/level mapping).

---

## 0. The intake spine (entry point)

All work enters the tracker through this spine. An issue that bypasses it is rejected by the `gh` shim or flagged `needs-info` by CI.

**Flow:**

```
investigate (arazzo-detective) ──▶ file (file-issue) ──▶ gh (shimmed) ──HARD gate──▶ GitHub
                                                                         │ fail → fix, retry
                                                                         ▼
                                                                  CI soft gate
                                                                         │ non-conforming → needs-info + marker comment (never close)
                                                                         ▼
                                                                  triage skill
                                                                         └─ bug/feature/chore, level, needs-info → chase, or ready-for-agent
```

### 0.1 Investigate (when the cause is unknown)

- **Skill:** `arazzo-detective` (focus: `bug`/`architecture`/`conformance`; depth: `standard`/`deep`).
- **Output:** `docs/superpowers/investigations/YYYY-MM-DD-<slug>.md` plus a final fenced block whose headings normalise to `source-grounding` and `epistemic-matrix`.
- **Verify:** report file exists; terminal summary ≤ 5 lines; all five operations present.

### 0.2 File the issue (never hand-write `gh issue create --body`)

```bash
php .agents/skills/file-issue/scripts/file-issue.php \
  --kind=kind:bugfix \
  --level=level:story \
  --parent=123 \
  --title="fix(core): Expression \$steps.x.outputs.body#/id resolves empty" \
  --sections=sections.json
```

- Package index (no kind, no sections): `--level=level:package --package=runner --title="chore(runner): runner"`.
- Edit existing: add `--edit=42`.
- Section writing order: `problem-statement → feature-scope/dependency-matrix → artifacts/runtime-state → acceptance-criteria → source-grounding → impact-profile → epistemic-matrix`.
- Read a section's instruction before writing it: `php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria` (cross-cutting ids: `prompt-preamble`, `readme`, `orchestrate-issue-conversion`, `review-issue-body`).

**Permanent code links (source-grounding):** must be blob URLs pinned to a commit, on their own bare line, followed by `Verified: YYYY-MM-DD`. Build with:

```bash
php tools/issue-intake/bin/code-permalink packages/evaluation/src/JsonPath.php:88-92
```

**Detective → issue linkage:** paste the detective's fenced block straight into the `--sections` payload (keys `source-grounding`, `epistemic-matrix`). Validate before filing:

```bash
php tools/issue-intake/bin/validate-issue <<< '{"body":"…","labels":["bug","level:story"],"parentLevel":"level:epic"}'
# expect: {"conforming":true,...}
```

### 0.3 The `gh` shim (hard gate)

- `scripts/bin/gh` intercepts `issue create`, `issue edit`, and `gh api …/issues`; validates before delegating; passes everything else through.
- Install on the host: `scripts/bin/install-gh-shim` (host `PATH`, since the container has no `gh`).
- Honours `GATE_GH_BIN` for tests.
- **Escape hatch:** label-only edits (`gh issue edit 5 --add-label needs-info`) carry no body and pass through — keeps `triage` and the CI labeller working.

### 0.4 CI soft gate

- Workflow: `.github/workflows/issue-intake.yml` (triggers: `issues: [opened, edited, labeled]` + schedule + dispatch).
- States: **grandfathered** (number ≤ cutoff, no `format:v2`) → skip; **converted** (`format:v2`) → errors block; **new** (number > cutoff) → errors block.
- Exempt: ecosystem-labelled issues and `level:package` issues.
- On failure: adds `needs-info` + one `<!-- intake-gate -->` marker comment. **Never closes, never assigns.**
- On pass: removes `needs-info` (only if our marker exists) and deletes the marker comment.
- Readiness (`ready-for-agent`) is evaluated independently; a failing readiness removes that label and adds `needs-info`.

### 0.5 Triage (for issues you didn't create)

```
needs-triage ──▶ needs-info ──▶ needs-triage   (reporter replied)
             ├─▶ ready-for-agent
             ├─▶ ready-for-human
             └─▶ wontfix
```

- `triage` is for raw incoming issues only. Tickets produced by `to-tickets` are already agent-ready — **do not triage them**.
- `kind:release` and `kind:chore` flow through the same machine.
- `needs-info` is owned by `triage`; the gate reuses it compatibly and only removes it when its own marker is present.

### 0.6 Hierarchy (Package → Epic → Story → Task)

| Level | Parent rule | Kind? |
|---|---|---|
| `level:package` | no parent; exactly one `package:<name>` label | no kind (generated index) |
| `level:epic` | under `level:package` | one kind |
| `level:story` | under `level:epic` | one kind |
| `level:task` | under `level:story`; never a parent | one kind |

**File parents first, then children.** `--parent=<n>` for story/task; the script reads `#n`'s level label and refuses a link that breaks an invariant.

Kinds: `kind:bugfix` / `kind:feature` / `kind:chore` / `kind:release`. Exactly one kind and one level label per issue (except `level:package`).

---

## Stage 1 — Idea → Spec

**Goal:** Turn a vague idea into a written, reviewed spec before any code.

**Skill(s):** `grill-with-docs` (stateful; preferred when in a working directory) · `grill-me` (stateless) · `to-spec` · `spec-driven-development` · `idea-refine` (divergent thinking before grilling) · `interview-me` (when the ask is underspecified).

**Inputs:** a raw idea or request.

**Procedure:**
1. If the idea is vague, run `interview-me` first to extract intent (one question at a time, with guesses, until explicit yes).
2. Run `grill-with-docs` to sharpen the idea and retain decisions in `CONTEXT.md`/ADRs.
3. If a question needs a runnable answer, detour: `handoff` out → `prototype` → `handoff` back.
4. Run `to-spec` to turn the thread into a spec; `spec-driven-development` writes it to `docs/superpowers/specs/YYYY-MM-DD-<slug>-design.md`.
5. Surface assumptions; get explicit human approval; **stop the turn** (gated workflow).

**Artifact:** spec file in `docs/superpowers/specs/`.

**Handoff:** spec approved → Stage 2. If the effort is huge/foggy, use `wayfinder` instead of `to-spec` and merge onto the main flow at `to-spec` when the map clears.

**Verification:** spec covers the six core areas (objective, commands, structure, style, testing, boundaries); success criteria specific and testable; human approved; turn ended after saving (no planning in the same turn).

> Supplement: `context-engineering` — load only the relevant spec sections into context at each downstream stage rather than flooding the agent with the whole spec.

---

## Stage 2 — Spec → Tickets

**Goal:** Break the spec into tracer-bullet tickets, each declaring blocking edges, and publish them as conforming issues.

**Skill(s):** `to-tickets` · `planning-and-task-breakdown`.

**Inputs:** approved spec.

**Procedure:**
1. Run `planning-and-task-breakdown` to size tasks (each ≤ ~5 files, explicit acceptance + verify).
2. Run `to-tickets` to split into tracer-bullet tickets with blocking edges.
3. For each ticket, create a conforming issue via the intake spine (§0.2): choose kind (`kind:feature`/`kind:bugfix`/`kind:chore`) and level (`level:epic` → `level:story` → `level:task`), file parents first.
4. Publish with `file-issue`:
   ```bash
   php .agents/skills/file-issue/scripts/file-issue.php \
     --kind=kind:feature --level=level:story --parent=<epic#> \
     --title="feat(core): ..." --sections=sections.json
   ```
5. Validate each before filing: `php tools/issue-intake/bin/validate-issue <<< '{...}'`.

**Artifact:** conforming GitHub issues (tracer bullets), each with blocking edges.

**Handoff:** `ready-for-agent` issues → Stage 3 (design review). Do **not** triage tickets you produced.

**Verification:** every ticket conforms (validator returns `conforming: true`); parent links present; blocking edges recorded; no `needs-info`.

---

## Stage 3 — Design Review (Architecture)

**Goal:** Before any code is written, review the proposed design against the codebase's coupling, depth, and domain-model constraints. This stage runs *after* tickets are filed via `file-issue` and *before* any implementation code is written. It prevents architectural drift and ensures the solution fits the codebase's seams.

**Skill(s):** `balanced-coupling` · `improve-codebase-architecture` · `codebase-design` · `domain-modeling`.

**Inputs:**
- A conforming GitHub issue filed via `file-issue` (§0.2), with `kind`, `level`, `parent`, and schema sections (`problem-statement`, `dependency-matrix`, `feature-scope`, `affected-surface`, `acceptance-criteria`, `source-grounding`, `impact-profile`, `epistemic-matrix` as required by kind).
- Approved spec (from Stage 1) or tracer-bullet tickets (from Stage 2).
- The issue's `source-grounding` section with pinned code permalinks (§0.2).

**Procedure:**
1. **Read the issue:** `gh issue view <n> --comments`. Confirm it is conforming (validator returns `conforming: true`).
2. **Run `domain-modeling`**: challenge fuzzy terms in the issue, resolve overloaded words, record hard-to-reverse decisions as ADRs if the design introduces new domain concepts.
3. **Run `codebase-design`**: define the module interface, depth, seam, and adapter boundaries for the proposed change. Determine what behavior goes behind a small interface at a clean seam.
4. **Run `balanced-coupling`**: evaluate the proposed change's coupling on three dimensions — integration strength, distance, volatility. Apply the balance rule: is the coupling balanced or unbalanced? Does it create a distributed monolith risk?
5. **Run `improve-codebase-architecture`**: scan for deepening opportunities. If the change touches a module that is hard to change, identify the deepening opportunity before implementing.
6. **Post the design review to the issue:** write a comment on the GitHub issue summarizing the design decision. Include:
   - The design approach (module interfaces, seams, adapters)
   - Coupling classification (balanced/unbalanced) with integration strength, distance, volatility ratings
   - Any ADR link (if an irreversible decision was made)
   - Any `CONTEXT.md` glossary updates

   Post via:
   ```bash
   gh issue comment <n> --body "## Design Review
   - **Approach:** <summary>
   - **Coupling:** balanced/unbalanced — integration strength, distance, volatility ratings
   - **ADR:** <link-to-adr-or-none>
   - **Sign-off:** <reviewer>
   "
   ```
7. **Get explicit sign-off:** the design review comment must be posted on the issue before proceeding to Stage 4. Confirm it appears in `gh issue view <n> --comments`.

**Artifact:** architecture decision record (ADR) or design summary comment posted on the GitHub issue, plus any updated `CONTEXT.md` glossary entries.

**Handoff:** design approved → Stage 4 (Ticket → Code). If the design reveals the ticket is mis-scoped, file a new issue or amend the existing one via `file-issue --edit`.

**Verification:** design review comment exists on the issue (`gh issue view <n> --comments` shows it); coupling classified as balanced; no unaddressed deepening opportunity; all four skills invoked.

> Supplement — **context-engineering**: load only the relevant issue sections and source files into context at this stage rather than flooding the agent with the whole codebase.

---

## Stage 4 — Ticket → Code

**Goal:** Implement a ticket test-first, in thin vertical slices.

**Skill(s):** `implement` (drives `tdd` internally, closes with `code-review`) · `tdd` · `incremental-implementation` · `falsification-testing`.

**Inputs:** a `ready-for-agent` issue with approved design review (Stage 3 complete).

**Procedure:**
1. Read the issue: `gh issue view <n> --comments`. Confirm the design review output (ADR or comment) is linked.
2. Run `implement` (it drives `tdd`: red → green → refactor, one slice at a time).
3. For each test, scaffold with `falsification-testing` (`scaffold-falsification-test.php`) starting from a falsifiable claim: "The system will `<behavior>` when `<condition>`. False if `<observable>` occurs."
4. For each source file under test, run `audit-boundaries.php` and add missing boundary tests: empty/null, one, max/ceiling (`maxSteps`, `maxWorkflowDepth`, `retryLimit` vs `retry_ceiling`), exactly-equal (`stepsSpent == maxSteps`), discontinuity (OpenAPI 3.0 vs 3.1, YAML `{$...}` vs literal).
5. Use `incremental-implementation` to land changes in thin, verifiable slices behind flags where risky.
6. Keep `packages/core` free of `Illuminate\*`; Laravel code goes in `packages/laravel`.
7. Run `detect-fake-tests.php` on every new/changed test file; fix any FAKE-# red flag before proceeding.
8. Run `delete-fix-check.sh` on the fix to prove tests falsify it (suite goes RED without the fix, GREEN with).
9. Run `make verify` (Pint + PHPStan max + Pest random-order) before declaring done.

**Artifact:** code + tests on a branch.

**Handoff:** branch ready → Stage 5.

**Verification:** `make verify` green; no `Illuminate` leak in core; tests pass in random order; `detect-fake-tests.php` clean (no FAKE-#); `delete-fix-check.sh` goes RED without fix / GREEN with fix; `audit-boundaries.php` boundary classes covered.

> Supplement — **quality bar** (`constraint-driven-development`): before the first ship, establish `CONSTRAINTS.md` (coverage thresholds, lint rules, perf budgets). Watch the diff for weakened bars (new `@ts-ignore`/suppressions, skipped tests, lowered thresholds). Applies to stages 4–6.
>
> Supplement — **source-grounding** (`source-driven-development`): verify implementation decisions against official docs (PHP, PSR, OpenAPI, Arazzo spec) before coding; cite sources in the PR.

---

## Stage 5 — Code → Review

**Goal:** Review the diff on two axes (Standards + Spec) before it enters main.

**Skill(s):** `code-review` · `code-review-and-quality` · `falsification-testing`.

**Inputs:** branch/PR against a fixed point (commit, branch, tag, or merge-base).

**Procedure:**
1. Run `code-review` (Standards axis: does it follow repo coding standards? Spec axis: does it match the originating issue/spec?).
2. Run `code-review-and-quality` for the multi-axis quality pass.
3. Fix findings; re-run `make verify`.
4. Run `falsification-testing` verification: `verify-falsification.sh --quick` (fake-detector → pint → phpstan → pest → hume dry-run → conformance). No fake-test red flags; mutation checked where relevant.
5. If a modularity/coupling concern arises, run `review` (+ `balanced-coupling`).

**Artifact:** review verdict (findings resolved or explicitly accepted).

**Handoff:** clean review → Stage 6.

**Verification:** both review axes pass; `make verify` green; `falsification-testing` green (no fake-test red flags, boundaries covered); no unresolved blocking findings.

> Supplement — **adversarial pre-ship** (`doubt-driven-development`): for high-stakes changes (auth, security, migrations, irreversible ops), run a fresh-context adversarial review before the change stands. Use when correctness matters more than speed.

---

## Stage 6 — Review → PR

**Goal:** Prepare and open a pull request with gates green and a drafted title/body.

**Skill(s):** `pr-prep` · `git-workflow-and-versioning`.

**Inputs:** reviewed branch.

**Procedure:**
1. Run `pr-prep` (runs gates, drafts title and body from commits).
2. Use `git-workflow-and-versioning` for commit hygiene, branching, and PR conventions.
3. Push and open the PR: `gh pr create` (passes through the shim — no body validation on PRs).
4. Link the PR to the originating issue(s) in the body.

**Artifact:** pull request.

**Handoff:** PR opened → Stage 7 (on merge) or Stage 4 (on review feedback).

**Verification:** gates green in CI; PR body links to issue(s); no failing checks.

---

## Stage 7 — PR → Release

**Goal:** Cut a release: version bump, changelog promotion, tag.

**Skill(s):** `release` · `shipping-and-launch`.

**Inputs:** merged PR(s) on main.

**Procedure:**
1. Run `release` (gates, version bump, changelog promotion for `alama/arazzo-core` or `alama/laravel-arazzo`).
2. Use `shipping-and-launch` for the pre-launch checklist, monitoring, staged rollout, rollback strategy.
3. Tag and publish per `release`.

**Artifact:** tagged release + promoted changelog.

**Handoff:** none (terminal). New work restarts at Stage 1.

**Verification:** release gates green; changelog promoted; tag pushed.

---

## Stage 8 — Bug path

**Goal:** Diagnose and fix a bug with a regression test, then re-enter the main flow at review.

**Skill(s):** `diagnosing-bugs` · `debugging-and-error-recovery` · `falsification-testing`.

**Inputs:** a bug report or failing test.

**Procedure:**
1. If the bug is hard/intermittent/regression-between-states, run `diagnosing-bugs` (refuses to theorise until a tight red feedback loop exists; fixes with a regression test).
2. Use `debugging-and-error-recovery` for systematic root-cause debugging when builds break or behavior doesn't match expectations.
3. If the cause is unknown and evidence is needed, run `arazzo-detective` (§0.1) first, then file via §0.2.
4. Write the regression test first (TDD), then fix.
5. Run `make verify`; if touching parser/validator/runner, also run conformance (`OaiConformanceTest` + `FixtureTest`, sync + `QueueFixtureRunner` parity).
6. Re-enter at Stage 5 (Code → Review).

**Artifact:** fix + regression test on a branch.

**Handoff:** → Stage 5.

**Verification:** regression test fails without the fix and passes with it (`delete-fix-check.sh`); `detect-fake-tests.php` clean on the regression test; `make verify` green.

> Supplement — **debugging depth** (`debugging-and-error-recovery`): use when the cause is non-obvious (builds break, unexpected errors, "worked yesterday"); complements `diagnosing-bugs` for harder cases.

---

## Stage 9 — Quality bar (cross-cutting)

**Goal:** Establish and hold the project's quality bar so agents don't quietly lower it.

**Skill(s):** `constraint-driven-development` · `falsification-testing`.

**Procedure:**
1. Run `constraint-driven-development` to interview on which dimensions matter and record them in `CONSTRAINTS.md` (coverage thresholds, lint rules, perf budgets). Supplies sane defaults when you have no number in mind.
2. Watch the diff for a weakened bar: new suppressions, skipped/deleted tests, stripped assertions, unimplemented stubs, lowered thresholds.
3. For every test written or reviewed, run `falsification-testing` (4-pass): Popper (falsifiable claim via `scaffold-falsification-test.php`), Hume (boundaries via `audit-boundaries.php`, mutation via `hume-audit.sh`), Socrates (adversarial fuzz via `socratic-fuzz.php`), Descartes (order/time dependence via `demon-sim.php`, property gaps via `property-audit.php`) — never "coverage theater".
4. Gates:
   - `make verify` (Pint + PHPStan max + Pest random-order) before any PR.
   - `make test-mutate` (Infection) when touching execution/transition logic.
   - Conformance: `OaiConformanceTest` + `FixtureTest` (sync + `QueueFixtureRunner` parity) for parser/validator/runner changes.
   - `falsification-testing` scripts: `detect-fake-tests.php`, `audit-boundaries.php`, `delete-fix-check.sh`, `verify-falsification.sh`, `severity-audit.php`, `property-audit.php`, `socratic-fuzz.php`, `demon-sim.php`.

**Artifact:** `CONSTRAINTS.md` + a verified, non-theatrical test suite.

**Handoff:** gates stages 4–6; a failing gate blocks progression.

**Verification:** `make verify` green; mutation checked where relevant; no fake-test red flags; results reported as corroboration, not proof.

---

## Appendix A — Failure modes per gate

| Gate | Failure | Action |
|---|---|---|
| `make verify` (Pint) | style drift | `composer run format`, re-run |
| `make verify` (PHPStan) | type error | fix or justify via baseline policy (`docs/BASELINE-POLICY.md`) |
| `make verify` (Pest) | test failure | fix code or test; never skip |
| `gh` shim (issue create/edit) | missing/empty section or level invariant | print findings; use `file-issue`; retry |
| CI intake gate | `needs-info` label + marker comment | fix listed findings; re-save; label clears automatically |
| Conformance tests | parity break | fix execution/transition logic; re-run sync + `QueueFixtureRunner` |
| Mutation (`make test-mutate`) | surviving mutants | strengthen tests or accept with rationale |
| `falsification-testing` (`detect-fake-tests`) | fake-test red flag | rewrite test per the 4-pass workflow |

## Appendix B — See also

- `git-workflow-and-versioning` — commit/branch/PR/release hygiene and changelog writing.
- `idea-refine` — structured divergent/convergent thinking before committing to a plan.
- `context-engineering` — context setup for sessions; rules files; avoiding context rot.
- `incremental-implementation` — thin, verifiable slices; feature flags; rollout.
- `planning-and-task-breakdown` — task sizing, dependency ordering, vertical slices.
- `arazzo-detective` — grounded investigation (§0.1).
- `ecosystem-triage` — turn ecosystem feed events into prioritised tasks grouped by roadmap ref.
- `deps-update` — bump composer deps across root/core/laravel and re-run gates.
- `sync-doc` — regenerate `docs/generated` architecture diagrams; detect drift.
- `fix-ci` — diagnose and fix failing GitHub Actions CI on a PR/branch.
- `resolving-merge-conflicts` — resolve an in-progress merge/rebase conflict by intent.
- `handoff` / `claude-handoff` — compact context for another agent/session.
- `retro` — retrospective on a coding session.

## Appendix C — Reference: key commands

```bash
# Isolated dev environment
scripts/apptree create <branch>
scripts/apptree shell <branch>
scripts/apptree test <branch>
scripts/apptree down <branch>

# Quality gates
make verify
composer run test / test-core / test-laravel
composer run analyse / analyse-core / analyse-laravel
composer run format
make test-coverage / make test-mutate

# Issue management
php .agents/skills/file-issue/scripts/file-issue.php --kind=kind:bugfix --level=level:story --parent=123 --title="..." --sections=sections.json
php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria
php tools/issue-intake/bin/code-permalink packages/evaluation/src/JsonPath.php:88-92
php tools/issue-intake/bin/validate-issue   # stdin: {"body":..,"labels":..,"parentLevel":..}
scripts/bin/gh issue create --title "..." --body-file body.md --label kind:bugfix --label level:story

# Conformance / coverage / falsification-testing
php .agents/skills/falsification-testing/scripts/detect-fake-tests.php --all --json
php .agents/skills/falsification-testing/scripts/audit-boundaries.php packages/runner/src/Sync/StepExecutor.php --json
php .agents/skills/falsification-testing/scripts/scaffold-falsification-test.php runner MyFeatureTest "claim" --dry-run
php .agents/skills/falsification-testing/scripts/delete-fix-check.sh --filter "..." --path packages/runner
php .agents/skills/falsification-testing/scripts/hume-audit.sh --dry-run --all
php .agents/skills/falsification-testing/scripts/property-audit.php --json --package core
php .agents/skills/falsification-testing/scripts/severity-audit.php --json --filter "..."
php .agents/skills/falsification-testing/scripts/socratic-fuzz.php --iterations 50 --json
php .agents/skills/falsification-testing/scripts/demon-sim.php --seeds 5 --json --package runner
php .agents/skills/falsification-testing/scripts/query-coverage.php --overview --json
php .agents/skills/falsification-testing/scripts/verify-falsification.sh --quick
```

---

*V1 — human-in-the-loop pipeline. AFK autonomy, machine-executable playbooks, and per-package playbooks are deferred to V2 (see spec Out of Scope).*