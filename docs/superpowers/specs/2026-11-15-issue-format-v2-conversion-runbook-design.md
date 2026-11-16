# Issue Format v2 — Conversion Runbook Design

> **Status:** Draft (awaiting approval)
> **Base:** `main` @ `2a592824` (PRs #319, #320, #321 merged)
> **Related:** `docs/superpowers/specs/2026-11-15-issue-format-v2-migration-design.md` (approved migration design)

## 1. Intent and why

The v2 tooling (schema, engine, prompts, gate ratchet) is merged and CI-green. The 62 draft issues in `docs/issues/drafts/` are still in the old v1 "heavy" dialect — numbered headings, `<details>`-wrapped permalinks, no front matter, no `R#`/`S#`/`Test:`/`coverage table`. They cannot be filed until they pass the v2 validator.

This runbook defines how to convert the 62 drafts (31 story+task pairs) into v2-conforming issues and file them, one unit at a time, without blocking on converting all 62 up front.

## 2. What actually exists (the facts that set the effort)

| Fact | Detail |
|------|--------|
| Draft count | 62 files in `docs/issues/drafts/`, numbered 01–62 |
| Draft dialect | v1 heavy: numbered headings (`### 🏗️ 1. Core Metadata & Alignment`), `<details>`-wrapped permalinks pinned to `ea8c55ad`, no front matter, draft-specific sections (6–9) not in v2 |
| Story/task pairing | Not yet recorded — `docs/issues/drafts/pairs.yaml` does not exist |
| Draft header bullets | `Suggested title`, `Kind`, `Level`, `Packages` — enough to derive kind/level/parent/packages |
| v2 schema | Merged (PR #319): 11 sections, per-check severities, `demote`/`demote_sections`, `title_checks` |
| v2 prompts | Merged (PR #320): one prompt per section in `.agents/skills/file-issue/prompts/` |
| v2 engine | Merged (PR #319): `Validator` with 18 content checks + readiness |
| v2 filing | Merged (PR #319): `file-issue.php --kind --level --title --sections` validates before calling `gh` |
| GitHub access | `gh` CLI authenticated; PR #319/#320/#321 merged |

## 3. Goals, non-goals, anti-goals

### Goals

- Convert all 62 drafts (31 story+task pairs) to v2-conforming bodies.
- File each unit as story-first, then its tasks — a unit is filed only on approval.
- Record the story→task pairing once in `docs/issues/drafts/pairs.yaml`.
- Leave unconverted drafts untouched in `docs/issues/drafts/` until their turn.
- Move a converted draft to `docs/issues/filed/` only when the issue is actually filed on GitHub.

### Non-goals

- Do not convert all 62 in one batch — pilot first, then batch by elapsed time.
- Do not write code (implementation code, tests, packages) — conversion is issue-body authoring only.
- Do not flip `INTAKE_CUTOFF` / apply the ratchet until Phase 6 (after conversion).
- Do not change the schema, engine, or prompts during conversion (they are frozen).

### Anti-goals

- Do not file a task before its story (a task that should have been a story loses its own requirements).
- Do not file a story that should have been a task (duplicates parent requirements).
- Do not leave a converted draft in `docs/issues/drafts/` after it is filed — it must move to `docs/issues/filed/` or be deleted.

## 4. Constraints

- PHP 8.4; `declare(strict_types=1)` in any new PHP; namespace `Alama\Tools\\IssueIntake` (not applicable — conversion is agent-driven, no new PHP).
- The v2 schema, engine, prompts, and gate are frozen — no changes during conversion.
- A unit files only when: body passes `Validator` with zero errors, readiness is acceptable, and a human approves the reviewer comment.
- `file-issue.php` refuses to call `gh` when validation fails — the shim and gate agree (spec D12).
- `core-metadata` is scripted, not prompted (D22): package, spec version, PHP target, parent, related issues come from labels and the sub-issue link.
- Draft front matter names its parent by draft slug (D17): `parent: 15-deterministic-replay`, not issue number.

## 5. Approaches considered

### Approach A — Agent-driven, file-as-you-go (chosen)

An agent converts one draft unit at a time using the v2 prompts (`file-issue.php --prompt=<section-id>`), in the orchestrator's order: R# from problem statement, S# per requirement, C# where a rule spans scenarios, D# for unresolved choices, coverage table, Test: lines, bare permalinks, Verified: date. The assembled body is posted as a review comment (drafts: as a diff in the PR), never applied first. On approval, the body is written, front matter added, `format:v2` label added, and the draft moves to `docs/issues/filed/`.

**Trade-off:** Slower than batch conversion (one agent turn per section), but every unit is individually reviewed and the pipeline is validated before batching. Matches spec §7.5 (Gather → Locate → Derive → Review → Write → Verify).

### Approach B — Batch convert all 62, then file

Convert every draft to v2 locally, then file them in dependency order.

**Rejected:** No per-unit review gate; a bad prompt or schema misunderstanding poisons all 62 bodies at once. Cannot pilot and renegotiate before Phase 3 (spec requirement).

### Approach C — Manual conversion without prompts

A human writes each v2 body by hand, copying from drafts.

**Rejected:** Defeats the purpose of the prompt suite; slow and error-prone; no anti-drift mechanism.

## 6. The conversion pipeline (spec §7.5)

Per body, whether draft or live issue:

1. **Gather** — inventory row, current body (`git show HEAD:` for drafts), the working-tree prose diff for drafts (D18), a frozen SHA, and the relevant package's test names.
2. **Locate** — find the code the body talks about and produce permalinks with `bin/code-permalink`, or an `arazzo-detective` report when the body does not say where to look. For drafts, the 164 existing links point at the right SHAs but are wrapped in `<details>`/`<a>`, so they are reformatted to the bare-line form (D24), not re-resolved.
3. **Derive** — an agent runs one prompt per section (`file-issue.php --prompt=<section-id>`, §7.7), in the orchestrator's order: `R#` from the problem statement, `S#` per requirement with one `When` and a named `Then`, `C#` where a rule spans scenarios, `D#` for every unresolved choice, the nine-row coverage table, `Test:` lines naming tests that will exist.
4. **Review** — posted as a comment (drafts: as a diff in the PR), never applied to the body first.
5. **Write** — on approval: body replaced, previous body preserved as a `<details>` comment, title prefix and labels fixed in the same step, `format:v2` label added.
6. **Verify** — `bin/validate-issue` on the result; readiness re-checked if the issue carried `ready-for-agent`.

Draft conversion additionally writes front matter (`title`, `kind`, `level`, `parent`, `packages`, `pair`) in place of the `# Draft issue NN` header, and moves the file to `docs/issues/filed/` only when the issue is actually filed.

## 7. The 31 draft units and pairing

The pairing is recorded once, in `docs/issues/drafts/pairs.yaml`, not rediscovered per conversion:

```yaml
- story: 15-deterministic-replay
  tasks: [57-cli-replay]
- story: 13-workflow-test-runner
  story_engine: 46-test-runner-engine   # engine lands first; both stories, task-child of 13
  tasks: [55-cli-test-runner]
- story: 18-single-step
  tasks: [51-single-step-execution, 60-cli-single-step]
```

The manifest is reviewed before any conversion starts. Getting the tree wrong is the expensive mistake: a task that should have been a story loses its own requirements, and a story that should have been a task duplicates its parent.

### Draft classification observed

| Range | Kind/level observed | Notes |
|-------|---------------------|-------|
| 01–13 | kind:bugfix/chore, level:story | Pure stories (no CLI port) |
| 14–31 | kind:feature, level:story | Pure stories (traces, validation, etc.) |
| 32–40 | kind:chore, level:story | Chore stories (mtls, html, etc.) |
| 41–45 | kind:feature/chore, level:story | CLI command stories (validator-policy, mtls, har, etc.) |
| 46–62 | kind:feature/chore, level:task | Task/CLI ports (engine→CLI, trace→export, etc.) |

**Note:** Drafts 15 ("Deterministic replay") and 57 ("CLI: arazzo replay") appear to be duplicate story+task pairs for the same work — draft 48 ("Deterministic replay engine") is the core story, draft 57 is its CLI task. Draft 15 may be a superseded duplicate. This must be resolved during pairing review.

## 8. v1 → v2 section mapping

| v1 section (numbered heading) | v2 section id | v2 heading | Notes |
|------------------------------|---------------|------------|-------|
| 1. Core Metadata & Alignment | core-metadata | `## 🏗️ Core Metadata & Alignment` | Scripted (D22): from labels + parent link |
| 2. Problem Statement & Intent | problem-statement | `## 📝 Problem Statement & Intent` | R# lines |
| 3. Feature Scope & Prior Art | feature-scope | `## 🎯 Feature Scope & Prior Art` | Non-goals: required (warning), D# lines |
| 3. System Dependency Matrix | dependency-matrix | `## 📦 System Dependency Matrix` | Bugfix only |
| 3. Affected Surface & Rollback | affected-surface | `## 🛠️ Affected Surface & Rollback` | Chore/release only; Rollback: required |
| 4. Structural Artifacts & Raw Inputs | artifacts | `## 📄 Structural Artifacts & Raw Inputs` | Fenced block required |
| 5. Runtime Execution State Context | runtime-state | `## 🌀 Runtime Execution State Context` | No check |
| 6. Issue Context & Architectural Intent | — | — | **Dropped** → becomes "Design Notes" (optional section) |
| 7. arc42 Quality Matrix | acceptance-criteria | `## 🧪 Acceptance Criteria & Behavioral Specification` | Coverage table rows |
| 8. Behavioral Specification | acceptance-criteria | `## 🧪 Acceptance Criteria & Behavioral Specification` | S# blocks, Test:, C# |
| 9. Actionable Checklist | acceptance-criteria | `## 🧪 Acceptance Criteria & Behavioral Specification` | Chore/release checklist items |
| 10. Source Grounding & Code Location | source-grounding | `## 🧬 Source Grounding & Code Location` | Bare permalink, unwrap `<details>` |
| 11. Public Domains & Impact Profile | impact-profile | `## 🌍 Public Domains & Impact Profile` | BC break: yes\|no |
| 12. Epistemic Verification Matrix | epistemic-matrix | `## 📊 Epistemic Verification Matrix` | T1–T4 tiers |

## 9. Section-specific conversion rules

### core-metadata (scripted, no prompt)
Derived from draft header bullets: `Package: <packages>` from `Packages:`, `Parent: #<slug>` from `Level: ... (suggested parent: #<n>)`, `Related: #<n>` from related drafts. `spec version` and `PHP target` from the bullets. The agent must not author this section — it is computed.

### problem-statement → R# lines
Extract the "Expected:" paragraph and the numbered issues into `R1..Rn` lines. One sentence per requirement, observable in behavior.

### feature-scope → Non-goals: + D#
Extract the "Non-goals" list. Convert open choices to `D#. <question>? Options: <a>, <b>. Default: <a>.` format.

### acceptance-criteria → S# blocks + C# + coverage table
Convert the "Behavioral Specification" scenarios to `S<n> (R<m>, <quality>):` blocks with Given/When/Then/Test:. Convert the arc42 matrix rows to the 9-quality coverage table. Convert the checklist to `C#` constraints.

### source-grounding → bare permalinks
Unwrap every `<details><summary><a href="...">...</a></details>` into: claim bullet, bare URL on its own line. Resolve the SHA to a **pushed** commit via `bin/code-permalink --ref=origin/main` — never local HEAD, because GitHub returns 404 for a SHA it does not have (incident 2026-11-15: issue #322 was filed with permalinks pinned to local-only `3f98e6e4`, all three 404'd; re-pinned to pushed `2a592824`). All original links point at `ea8c55ad`, an ancestor of HEAD. Verify each permalink SHA is on the remote before filing (`git merge-base --is-ancestor <sha> origin/main`, or an HTTP 200 check). Add `Verified: 2026-11-15`.

### impact-profile → BC break: yes\|no
Extract the "Severity:" line into `BC break: yes\|no`.

### epistemic-matrix → T1–T4 tiers
Convert the matrix rows. Every tier needs evidence. Unverified claims → T4 (blocks ready-for-agent).

### Design Notes (optional section)
Section 6 content ("Issue Context & Architectural Intent") that has no v2 home is preserved in an ungated trailing section `## Design Notes`. Added to schema.yaml as optional for every kind with no checks.

## 10. Phases

| Phase | Scope | Exit criterion |
|-------|-------|----------------|
| 0 | Preserve dirty tree (done — parked on `wip/pre-v2-working-tree`) | Tree clean on main |
| 1 | Tooling (done — PRs #319–#321 merged, CI green) | Suite green, gate ships |
| 2 | Write `pairs.yaml` pairing manifest; review it | Manifest approved |
| 3 | Pilot conversion: one story+task unit, file as you go | Pilot issue filed, body v2-conforming |
| 4 | Batch conversion: remaining units, dependency order | All 62 drafts converted or filed |
| 5 | Verify: inventory shows all filed issues v2-conforming | `issue-inventory` reports zero errors on filed issues |
| 6 | Apply ratchet: set `INTAKE_CUTOFF`, gate starts needs-info | Gate warns/errors on non-conforming |

This runbook covers Phases 2–6. Phase 0–1 are complete.

## 11. Risks

| Risk | Mitigation |
|------|------------|
| Draft 15 vs 57 duplicate (same story) | Resolve during pairs.yaml review; keep one story, one task |
| Draft says level:story but is really a task (CLI port of draft 48) | Check "Core engine is draft X" note; demote to task, make child of engine story |
| Permalink SHA `ea8c55ad` not an ancestor of HEAD | All 61 drafts pin to `ea8c55ad` which is confirmed ancestor; verify per-file |
| Permalink SHA not on the remote (404 on GitHub) | Pin to a pushed ref via `bin/code-permalink --ref=origin/main`; verify each SHA is on the remote before filing (incident #322) |
| `<details>` unwrap produces non-bare line | Agent reformats to bare URL; engine validates |
| Task filed before its story | Enforce story-first ordering in pairs.yaml |
| Agent invents permalinks | Prohibited by prompt; engine rejects non-bare/non-pinned links |

## 12. Left to the builder

- Write `docs/issues/drafts/pairs.yaml` pairing manifest (31 units)
- Select pilot unit and run Phase 3
- Run Phase 4 batch conversion
- Run Phase 5 inventory verification
- Set `INTAKE_CUTOFF` in Phase 6

---

**Approvals needed before building:**
1. The pairing manifest approach (story→task in pairs.yaml)
2. File-as-you-go (one unit filed on approval, then move to filed/)
3. Pilot selection (which story+task pair goes first)

This is a **project** per the brainstorming skill — it requires the full written design doc. The next step after approval is the implementation plan (`writing-plans` skill) with TDD tasks for the conversion tooling and agent prompts.
