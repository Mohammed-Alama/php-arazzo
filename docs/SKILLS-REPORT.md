# Skills Usage Report — php-arazzo monorepo

> **Purpose:** A single, actionable map of which skill to reach for at each stage of developing `alama/arazzo-core` + `alama/laravel-arazzo`, with the project's GitHub issue-intake system (`file-issue` + `gh`) treated as the spine of project management.
>
> **Scope:** All skills available in this workspace (`.agents/skills/` + superpowers + upstream Matt Pocock), filtered to those that actually apply to this PHP monorepo. Skills that do not apply (e.g. TypeScript-only, exercise scaffolding) are listed under "Not applicable" so they are not reached for by habit.
>
> **Companion docs:** `CLAUDE.md`, `CONTEXT-MAP.md`, `docs/issues/triage-kinds.md`, `.agents/skills/file-issue/SKILL.md`, `.agents/skills/ask-matt/SKILL.md`.

---

## 1. Project at a glance (why the skill map looks the way it does)

- **Monorepo:** `packages/{core,laravel}` (plus tooling in `tools/issue-intake`, `scripts/`, `config/`). Core is PSR-only (no `Illuminate`); Laravel is the bridge.
- **Layering (diamond):** `contracts → (document, expression) → evaluation → runner → (cli, laravel)`. Cross-package edges must target `*Interface` only (see `CONTEXT-MAP.md`).
- **Toolchain:** PHP 8.4 tooling / PHP 8.2–8.5 published; Pest 4; PHPStan `level: max`; Pint (laravel preset); Infection (mutation); `make verify` = `pint --test && composer analyse && composer test`.
- **Isolation:** `scripts/apptree create <branch>` for Dockerised dev; tests run via `scripts/apptree test`.
- **Issue management:** GitHub Issues with a 4-kind × 4-level hierarchy, enforced by `tools/issue-intake` (single validator engine) consumed by the `gh` shim, the CI soft gate, and the `file-issue` skill. See §4.
- **Epistemic discipline:** `.agents/rules/epistemic.md` (always-on) + `arazzo-detective` for grounded investigation.

**Rule of thumb for this repo:** every change either (a) touches a spec conformance surface (parser/validator/runner) or (b) is tooling/docs. Category (a) demands `falsification-testing` + conformance fixtures; category (b) demands `file-issue` + the intake schema.

---

## 2. Skill inventory — where each skill lives

| Source | Location | Count | Notes |
|---|---|---|---|
| Project-local | `.agents/skills/` | ~54 | Includes project-specific skills (`add-rule`, `add-fixture`, `apptree`, `arazzo-detective`, `ecosystem-triage`, `falsification-testing`, `file-issue`, `coverage-insights`, `deps-update`, `pr-prep`, `release`, `sync-doc`). |
| Superpowers | `superpowers:*` (system) | ~14 | Process skills: brainstorming, TDD, debugging, plans, review, finishing branch, worktrees, verification. |
| Upstream Matt Pocock | `mattpocock/skills` (locked in `skills-lock.json`) | ~40 | Engineering/productivity flows: grill, to-spec, to-tickets, triage, implement, wayfinder, research, handoff, etc. |
| Universal | (system) | varies | `using-superpowers`, `find-skills`, `writing-skills`, etc. |

**Locked vs. editable:** upstream Matt Pocock skills (including `triage`, `implement`, `tdd`) are lock-tracked in `skills-lock.json` and **must not be edited**. Project-local skills (`.agents/skills/*`) are editable. When a locked skill's vocabulary differs from ours (e.g. `triage` uses `bug`/`enhancement`, we use `kind:bugfix`/`kind:feature`), the mapping lives in `docs/issues/triage-kinds.md` rather than in the skill.

---

## 3. Development lifecycle → recommended skills

### 3.1 Phase 0 — One-time setup (run once per clone/machine)

| Task | Skill | Command / notes |
|---|---|---|
| Configure issue tracker, triage labels, domain docs layout | `setup-matt-pocock-skills` | Run before first engineering flow. Writes `docs/agents/issue-tracker.md`, `domain.md`, `triage-labels.md`. |
| Install pre-commit hooks (Pint + typecheck + tests) | `setup-pre-commit` | Husky + lint-staged. Mirrors `make verify`. |
| Block dangerous git ops | `git-guardrails-claude-code` | Hook-based guardrails. |
| Docker isolation | `apptree` | `scripts/apptree create <branch>` → `shell` → `test` → `down`. |

### 3.2 Phase 1 — Understand / investigate

| Situation | Skill | Why |
|---|---|---|
| "Why does X resolve empty?" / "How does Y work?" / architecture boundary scan | `arazzo-detective` | Runs the Epistemic Protocol's 5 operations; writes `docs/superpowers/investigations/YYYY-MM-DD-<slug>.md`; emits a paste-ready `source-grounding` + `epistemic-matrix` block for `file-issue`. |
| Hard bug, flaky test, regression between two known-good states | `diagnosing-bugs` | Refuses to theorise until a tight red feedback loop exists; fixes with a regression test; hands off to `improve-codebase-architecture` if the real finding is a missing seam. |
| Need primary-source reading legwork while you keep working | `research` | Background agent; leaves a cited Markdown file to feed `grill-with-docs`. |
| Assess modularity / coupling / "why is this hard to change" | `review` (+ `balanced-coupling`, `document`) | Balanced Coupling model; produces Markdown+HTML review. |
| Find deepening opportunities | `improve-codebase-architecture` | Survey → candidate → take into `grill-with-docs`. |
| Design a module's shape (interface, seam, adapter) | `codebase-design` | Vocabulary layer; `tdd` and `improve-codebase-architecture` speak it. |
| Sharpen domain language / ADRs | `domain-modeling` | Keeps `CONTEXT.md` a clean glossary; `grill-with-docs` drives it. |

### 3.3 Phase 2 — Plan / design

| Situation | Skill |
|---|---|
| Sharpen an idea, stateful, in a working directory | `grill-with-docs` (preferred over `grill-me`) |
| Sharpen an idea, no repo / stateless | `grill-me` |
| Raw interview primitive only | `grilling` |
| Turn a thread into a spec | `to-spec` |
| Split a spec into tracer-bullet tickets with blocking edges | `to-tickets` |
| Huge, foggy, multi-session effort | `wayfinder` (then `to-spec` → `to-tickets`) |
| Need an answer that requires runnable code | `prototype` (detour, bridged by `handoff`) |
| Multi-session learning | `teach` |
| Need a decision from someone else | `to-questionnaire` |
| Design a modular high-level architecture from requirements | `design` (+ `balanced-coupling`) |

**Context hygiene (from `ask-matt`):** keep grilling → spec → tickets in **one unbroken context window**; `/clear` between each `/implement`; `/compact` at phase boundaries, not mid-phase.

### 3.4 Phase 3 — Implement

| Situation | Skill |
|---|---|
| Build a ticket/issue | `implement` (drives `tdd` internally, closes with `code-review`) |
| Implement from a written spec | `implement-spec` |
| Build a concrete behaviour test-first | `tdd` |
| Add a validator rule (Rule class + Pest test + RuleSet registration) | `add-rule` |
| Add a conformance fixture (valid/invalid/edge case) | `add-fixture` |
| Write/modify skills, AGENTS.md, CLAUDE.md | `writing-for-agents` |
| Long-form writing flows | `writing-fragments` → `writing-beats` → `writing-shape` |
| Refactor `as` assertions to shoehorn (TS only — **not applicable here**) | `migrate-to-shoehorn` (skip) |
| Update composer deps across root/core/laravel + re-run gates | `deps-update` |

### 3.5 Phase 4 — Test

| Situation | Skill |
|---|---|
| Write or review tests (always) | `falsification-testing` — the 4-pass workflow (Popper/Hume/Socrates/Descartes) + 12 scripts. |
| Query coverage gaps | `coverage-insights` (or `falsification-testing` scripts `query-coverage.php`) |
| Ask "is this well tested?" | `falsification-testing` (report corroboration, not proof) |

**Gates:** `make verify` (Pint + PHPStan max + Pest random-order) before any PR; `make test-mutate` for mutation; conformance `OaiConformanceTest` + `FixtureTest` (sync + `QueueFixtureRunner` parity) when touching execution/transition logic.

### 3.6 Phase 5 — Review

| Situation | Skill |
|---|---|
| Review changes since a fixed point (Standards + Spec axes) | `code-review` |
| Modularity/coupling review | `review` (+ `balanced-coupling`, `document`) |
| Receive review feedback | `receiving-code-review` |
| Request review before merge | `requesting-code-review` |
| Review a PR's diff (compressed) | `caveman-review` |

### 3.7 Phase 6 — Ship / release

| Situation | Skill |
|---|---|
| Prepare a PR (run gates, draft title/body from commits) | `pr-prep` |
| Cut a release (version bump, changelog promotion) | `release` |
| Resolve in-progress merge/rebase conflict | `resolving-merge-conflicts` |
| Finish a development branch (merge/PR/keep/discard) | `finishing-a-development-branch` |
| Fix failing CI on a PR/branch | `fix-ci` |
| Regenerate architecture diagrams / detect drift | `sync-doc` |

### 3.8 Phase 7 — Project management (GitHub) — see §4

| Situation | Skill |
|---|---|
| File/rewrite an issue (bug/feature/chore/release) | `file-issue` (paved path; never hand-write `gh issue create --body`) |
| Triage incoming issues/PRs (raw, not created by you) | `triage` |
| Turn ecosystem feed events into prioritised tasks | `ecosystem-triage` |
| Huge effort as decision-ticket map | `wayfinder` |
| Retro on a session | `retro` |
| Hand off context to another agent/session | `handoff` / `claude-handoff` |

### 3.9 Cross-cutting

| Situation | Skill |
|---|---|
| "Which skill/flow fits?" | `ask-matt` (router) |
| Provision infra / secrets / dashboards (human-only steps) | `wizard` |
| Stop / re-pitch a message that didn't land | `wait-what` |
| Compressed communication | `caveman` (and sub-skills) |

---

## 4. Issue management with `gh` + `file-issue` (the project spine)

This repo does **not** use GitHub's native issue types. Kind and level live on labels, enforced by one validator engine (`tools/issue-intake/bin/validate-issue`) consumed by three surfaces: the `gh` shim, the CI soft gate, and `file-issue`. **All three agree; an issue that fails locally also fails in CI.**

### 4.1 Hierarchy (Package → Epic → Story → Task)

| Level label | Parent rule | Kind? |
|---|---|---|
| `level:package` | **no** parent; exactly one `package:<name>` label | **no** kind (generated index body) |
| `level:epic` | must sit under a `level:package` | one kind |
| `level:story` | must sit under a `level:epic` | one kind |
| `level:task` | must sit under a `level:story`; can never be a parent | one kind |

**File parents first, then children.** Pass `--parent=<n>` for story/task; the script reads `#n`'s `level:*` label and refuses a link that breaks an invariant (e.g. a story under a legacy issue with no level label).

### 4.2 Kinds

| Kind label | Meaning | Upstream triage category |
|---|---|---|
| `kind:bugfix` | broken/incorrect behaviour | `bug` |
| `kind:feature` | new capability / improvement | `enhancement` |
| `kind:chore` | maintenance/tooling/cleanup | (none) |
| `kind:release` | publish artifacts, promote changelogs | (none) |

### 4.3 Required sections per kind (from `config/issues/schema.yaml`)

| Kind | Required sections (in schema order) |
|---|---|
| `kind:bugfix` | core-metadata, problem-statement, **dependency-matrix**, **artifacts**, **runtime-state**, acceptance-criteria, source-grounding, impact-profile, **epistemic-matrix** |
| `kind:feature` | core-metadata, problem-statement, **feature-scope**, acceptance-criteria, source-grounding, impact-profile, **epistemic-matrix** |
| `kind:chore` | core-metadata, problem-statement, **affected-surface**, acceptance-criteria, source-grounding, impact-profile |
| `kind:release` | core-metadata, problem-statement, **affected-surface**, acceptance-criteria, source-grounding, impact-profile, **epistemic-matrix** |
| `level:package` | (none — generated index) |

`core-metadata` is **scripted** from labels + parent link (D22); never supply it in the payload.

### 4.4 The `file-issue` paved path

**Never hand-write `gh issue create --body "…"`.** Hand-rolled bodies are the root cause of missing-field issues because `gh` bypasses the web form entirely.

```bash
php .agents/skills/file-issue/scripts/file-issue.php \
  --kind=kind:bugfix \
  --level=level:story \
  --parent=123 \
  --title="fix(core): Expression \$steps.x.outputs.body#/id resolves empty" \
  --sections=sections.json
```

Package index (no kind, no sections):

```bash
php .agents/skills/file-issue/scripts/file-issue.php \
  --level=level:package \
  --package=runner \
  --title="chore(runner): runner"
```

Edit an existing issue:

```bash
php .agents/skills/file-issue/scripts/file-issue.php --edit=42 --kind=kind:feature --level=level:story --title="..." --sections=sections.json
```

**Section writing order** (later prompts cite ids earlier ones define):

```
problem-statement → feature-scope/dependency-matrix →
artifacts/runtime-state → acceptance-criteria → source-grounding →
impact-profile → epistemic-matrix
```

Read each section's instruction before writing it:

```bash
php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria
# cross-cutting ids: prompt-preamble, readme, orchestrate-issue-conversion, review-issue-body
```

**Permanent code links (§7 source-grounding):** must be blob URLs pinned to a commit, on their own bare line, followed by `Verified: YYYY-MM-DD`. Build with:

```bash
php tools/issue-intake/bin/code-permalink packages/evaluation/src/JsonPath.php:88-92
```

### 4.5 The `gh` shim (hard gate on the agent path)

`scripts/bin/gh` intercepts exactly `issue create`, `issue edit`, and `gh api …/issues`, validates before delegating, and passes everything else through untouched. It honours `GATE_GH_BIN` (real `gh`) for tests. Install on the host with `scripts/bin/install-gh-shim`.

**Consequence:** even a raw `gh issue create` is blocked if the body doesn't conform. The shim and CI can never disagree (same engine).

### 4.6 CI soft gate (`issue-intake.yml`)

- Triggers: `issues: [opened, edited, labeled]` + schedule + dispatch.
- States: **grandfathered** (number ≤ cutoff, no `format:v2`) → skip; **converted** (`format:v2`) → errors block; **new** (number > cutoff) → errors block.
- Ecosystem-labelled issues and `level:package` issues are exempt.
- On conformance failure: adds `needs-info` + one `<!-- intake-gate -->` marker comment listing findings. **Never closes, never assigns.**
- On conformance pass: removes `needs-info` (only if our marker exists) and deletes the marker comment.
- Readiness (`ready-for-agent`) is evaluated independently; a failing readiness removes that label and adds `needs-info`.

### 4.7 Triage state machine (for issues you didn't create)

```
needs-triage ──▶ needs-info ──▶ needs-triage   (reporter replied)
             ├─▶ ready-for-agent
             ├─▶ ready-for-human
             └─▶ wontfix
```

- `needs-info` is owned by `triage` ("waiting on reporter"); the intake gate reuses it compatibly and only removes it when its own marker is present.
- Triage is **only for issues you didn't create** (bug reports, incoming requests). Tickets produced by `to-tickets` are already agent-ready — **don't triage them**.
- `kind:release` and `kind:chore` flow through the same machine.

### 4.8 Detective → issue linkage

`arazzo-detective` investigations end with a fenced block whose headings normalise to `source-grounding` and `epistemic-matrix`, populated from the report's §2/§3 and §1/§5/§7. Feed that block straight into a `file-issue` `--sections` payload (or `--edit` on an existing issue). Verify it conforms:

```bash
php tools/issue-intake/bin/validate-issue <<< '{"body":"…","labels":["bug","level:story"],"parentLevel":"level:epic"}'
```

### 4.9 `ready-for-agent` readiness

An issue becomes `ready-for-agent` only when the intake validator reports `ready: true` (no blockers). The gate removes the label on readiness failure. Use `triage` to move issues into this state for AFK agents; `implement` then picks them up.

---

## 5. Decision trees

### 5.1 "I have an idea"

```
Is it in a working directory?
  ├─ yes → grill-with-docs (stateful, leaves CONTEXT.md/ADRs)
  └─ no  → grill-me (stateless)

Needs a runnable answer?
  ├─ yes → handoff out → prototype → handoff back
  └─ no  → continue grilling

Multi-session build?
  ├─ yes → to-spec → to-tickets (tracer bullets, blocking edges) → implement per ticket (clear between)
  └─ no  → implement (drives tdd) → code-review → commit
```

### 5.2 "Something is broken"

```
Hard/intermittent/regression? → diagnosing-bugs (tight red loop first)
Else (clear cause)             → tdd (regression test) → implement → code-review
Unknown cause, need evidence   → arazzo-detective (grounded investigation) → file-issue
```

### 5.3 "I need to file an issue"

```
Is it a bug/feature/chore/release? → file-issue (never raw gh)
  ├─ new work from a spec/ticket → to-tickets already produced agent-ready tickets; use file-issue only for ad-hoc
  ├─ investigation in hand → arazzo-detective linkage block → file-issue --sections
  └─ parent first, then child (package → epic → story → task)
Raw incoming issue (not yours) → triage (not file-issue)
Ecosystem feed event → ecosystem-triage → file-issue for the resulting tickets
```

### 5.4 "I'm about to commit/PR"

```
make verify (pint + phpstan max + pest random)
  ├─ touching parser/validator/runner → also ConformanceTest + FixtureTest (sync + QueueFixtureRunner parity)
  ├─ touching execution/transition → make test-mutate (Infection)
  └─ then pr-prep (gates + draft title/body) → code-review (Standards + Spec) → PR
```

### 5.5 "Which test skill?"

```
Writing tests? → falsification-testing (4-pass; never "coverage theater")
Reviewing a suite? → falsification-testing detect-fake-tests.php
Coverage query? → coverage-insights (or query-coverage.php)
Mutation? → hume-audit.sh / make test-mutate
```

---

## 6. Project-specific skills (the ones that exist *because* of this repo)

| Skill | When | Key commands |
|---|---|---|
| `add-rule` | New validator rule / error code / spec-conformance check | scaffolds Rule class + Pest test + RuleSet registration in `packages/core` |
| `add-fixture` | New conformance fixture (valid/invalid/edge case) | `packages/core/tests/fixtures` + README entry |
| `apptree` | Isolated Docker dev | `scripts/apptree {create,shell,test,down,list}` |
| `arazzo-detective` | Grounded investigation | writes `docs/superpowers/investigations/...` |
| `coverage-insights` | Coverage queries | `--overview`, `--file`, `--hotspots`, `--uncovered`, `--dashboard`, `--json` |
| `deps-update` | Bump composer deps | root + core + laravel, re-run gates |
| `ecosystem-triage` | Plan from Arazzo spec/tooling changes | feed events → prioritised tasks → `decisions.json` |
| `falsification-testing` | Test writing/review | 12 scripts, all `--json` |
| `file-issue` | Conforming issues | `php .agents/skills/file-issue/scripts/file-issue.php ...` |
| `pr-prep` | PR prep | gates + draft title/body |
| `release` | Cut release | version bump + changelog promotion |
| `sync-doc` | Regenerate diagrams / drift | `docs/generated` |
| `falsification-testing` | (see above) | — |

---

## 7. Skills that do NOT apply here (avoid by habit)

| Skill | Why not applicable |
|---|---|
| `migrate-to-shoehorn` | TypeScript-only; this is PHP. |
| `scaffold-exercises` | Course/exercise scaffolding; not this repo. |
| `setup-ts-deep-modules` | TypeScript dependency-cruiser; not applicable. |
| `prototype` for UI | Only if a UI question genuinely arises (CLI/Laravel app); rare. |
| `migrate-to-shoehorn` (again) | — |

Also: never edit locked upstream skills (`triage`, `implement`, `tdd`, etc.) — their vocabulary differs from ours by design; map via `docs/issues/triage-kinds.md`.

---

## 8. Anti-patterns (things that waste time or break gates)

1. **Hand-writing `gh issue create --body "…"`** — bypasses the form and the validator; the shim blocks it anyway. Use `file-issue`.
2. **Supplying `core-metadata` in a `--sections` payload** — it's scripted from labels + parent; any value is ignored.
3. **Filing a child before its parent** — parent must exist first (`level:package` → epic → story → task).
4. **Triaging tickets you produced via `to-tickets`** — they're already agent-ready; triage is for incoming raw issues.
5. **Editing a `level:package` index body** — it's generated by `package-index.yml`; edit children instead.
6. **Claiming a suite "proves" correctness** — report corroboration (which of the 4 passes ran), per `falsification-testing`.
7. **Skipping `make verify` before a PR** — CI runs the same gates; local parity saves round-trips.
8. **Cross-package concrete Facade imports** — must target `*Interface` only (see `CONTEXT-MAP.md`).
9. **Core importing `Illuminate\*`** — boundary violation; move to `laravel`.
10. **Using `as`-style assertions in tests** — repo uses Pest expectations; see `falsification-testing` house style.

---

## 9. Quick reference — "I want to…"

| I want to… | Use |
|---|---|
| Start a feature | `grill-with-docs` → `to-spec` → `to-tickets` → `implement` |
| Fix a bug | `diagnosing-bugs` → `tdd` → `implement` → `code-review` |
| Understand code | `arazzo-detective` or `research` |
| Add a validator rule | `add-rule` (+ `falsification-testing`) |
| Add a fixture | `add-fixture` |
| File an issue | `file-issue` (never raw `gh`) |
| Triage incoming issues | `triage` |
| Review a branch/PR | `code-review` |
| Prepare a PR | `pr-prep` |
| Cut a release | `release` |
| Bump deps | `deps-update` |
| Check coverage | `coverage-insights` |
| Write tests | `falsification-testing` |
| Resolve a conflict | `resolving-merge-conflicts` |
| Hand off context | `handoff` |
| Decide which skill | `ask-matt` |

---

## 10. How skills are invoked in this environment

- **Skill load:** OpenCode native `skill` tool with bare id (e.g. `id: "file-issue"`), dropping any `superpowers:` prefix.
- **file-issue:** `php .agents/skills/file-issue/scripts/file-issue.php ...` (see §4.4).
- **gh:** use `scripts/bin/gh` (shim) so the hard gate applies; install host shim via `scripts/bin/install-gh-shim`.
- **Validator (manual):** `php tools/issue-intake/bin/validate-issue` reading `{body, labels, parentLevel}` JSON on stdin.
- **Code permalink:** `php tools/issue-intake/bin/code-permalink path:start-end`.
- **Issue tracker config:** `docs/agents/issue-tracker.md` (GitHub), `docs/agents/triage-labels.md`, `docs/agents/domain.md` — produced by `setup-matt-pocock-skills` (not yet present in this checkout; run it first).

---

*Generated as a reference for the php-arazzo monorepo. Keep in sync when skills are added/removed (compare against `skills-lock.json` and `.agents/skills/`).*
