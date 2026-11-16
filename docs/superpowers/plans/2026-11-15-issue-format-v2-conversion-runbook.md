# Issue Format v2 — Conversion Runbook Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the 62 draft issues (31 story+task pairs) in `docs/issues/drafts/` from the v1 heavy dialect to v2-conforming bodies, and file each unit as story-first then tasks using the merged v2 tooling (PRs #319–#321).

**Architecture:** Agent-driven conversion per spec §7.5 pipeline: Gather → Locate → Derive → Review → Write → Verify. Each draft is read, its v1 sections are mapped to v2 sections (section 6 → ungated "Design Notes", `<details>` permalinks unwrapped to bare lines, arc42 matrix → coverage table, behavioral spec → S# blocks), the body is assembled using `file-issue.php --prompt=<section-id>`, posted as a review comment, approved, then filed via `file-issue.php` with `format:v2` label, and the draft moves to `docs/issues/filed/`. Pairings are recorded once in `docs/issues/drafts/pairs.yaml`.

**Tech Stack:** PHP 8.4 (file-issue.php, validate-issue, issue-inventory), bash, `gh` CLI for filing, git for draft file moves.

**Spec:** `docs/superpowers/specs/2026-11-15-issue-format-v2-conversion-runbook-design.md` (approved design); implements Phases 2–6 of `docs/superpowers/specs/2026-11-15-issue-format-v2-migration-design.md`.

## Global Constraints

- v2 schema, engine, prompts, and gate are **frozen** — no changes during conversion.
- `core-metadata` is scripted, not prompted (D22): package/spec version/PHP target/parent/related come from labels and sub-issue link; the agent must not author this section.
- Draft front matter names parent by draft slug (D17): `parent: 15-deterministic-replay`, not issue number.
- A unit files only when: body passes `Validator` with zero errors, readiness is acceptable, and a human approves the reviewer comment.
- `file-issue.php` refuses to call `gh` when validation fails — no body reaches GitHub non-conforming.
- Draft front matter: `title`, `kind`, `level`, `parent`, `packages`, `pair`.
- File each unit as story-first, then its tasks.
- Move converted draft to `docs/issues/filed/` only when the issue is actually filed.
- `INTAKE_CUTOFF` stays unset until Phase 6 (after conversion).

## File Structure

```
docs/issues/drafts/pairs.yaml          NEW — 31 story→task pairings, one manifest
docs/issues/drafts/<NN>-<slug>.md      EXISTING — 62 v1 drafts; converted in place until filed
docs/issues/filed/                     NEW directory — converted drafts moved here when filed
docs/superpowers/plans/                NEW — this plan file
```

## Global Constraints (from spec)

- PHP 8.4; `declare(strict_types=1)` in every new/modified PHP file; namespace `Alama\Tools\IssueIntake`
- `Validator` stays pure: no network, no filesystem
- `validate-issue` exit codes: `0` no error (warnings allowed), `1` at least one error, `2` usage/internal error
- Gate never closes, never assigns; only ever removes a `needs-info` it set itself
- Source-grounding format is a bare permalink on its own line; `code-permalink --fold` is removed
- **Permalinks pin to a pushed commit** — `bin/code-permalink <path>:<range> --ref=origin/main`, never local HEAD. GitHub 404s a SHA it does not have (incident #322). Verify each SHA is on the remote before filing.
- `INTAKE_CUTOFF` stays unset until Phase 6

## Review Focus

Inputs and conditions the spec implies but no single task's tests would otherwise cover:

1. **Draft 15 vs 57 duplicate** — both appear to be story+task pairs for the same replay work; draft 48 is the core engine story, draft 57 is its CLI task. Draft 15 may be a superseded duplicate. Resolve during pairs.yaml review: keep one story, one task; mark the other as superseded or merge. (Task 1)
2. **Task vs story misclassification** — a CLI port draft (e.g., 57) says `level:story` but is really a task (its core engine is draft 48). Must be demoted to task, made child of engine story. (Task 1)
3. **Permalink SHA must be on the remote** — all 61 drafts with permalinks pin to `ea8c55ad`; but the *new* permalinks (`--ref=origin/main`) must resolve to a SHA GitHub has, or they 404 (incident #322: pinned to local-only `3f98e6e4`). Verify `git merge-base --is-ancestor <sha> origin/main` before filing. (Task 2)
4. **`<details>` unwrap produces non-bare line** — agent must reformat to bare URL on its own line; engine rejects `[label](url)`, `<details>`, `blob/main`. (Task 2)
5. **Section 6 ("Issue Context") has no v2 home** — becomes ungated "Design Notes" trailing section; added to schema.yaml as optional for every kind with no checks. (Task 2)
6. **A unit must file story before its task** — enforce story-first ordering in pairs.yaml. (Tasks 2–4)

## Review Focus (tests pinning each)

| Focus | Test location |
|-------|---------------|
| pairs.yaml has 31 units, every draft referenced exactly once | Task 1 — `pairs.yaml` validates against draft filenames |
| Pilot body passes `Validator` with zero errors | Task 2 — `bin/validate-issue` on pilot body |
| Pilot body passes `readiness()` | Task 2 — `bin/validate-issue` readiness output |
| Draft moved to `docs/issues/filed/` after filing | Task 2 — file exists in filed/, absent from drafts/ |
| Remaining units converted in dependency order | Task 4 — inventory shows no missing stories before tasks |
| Inventory shows all filed issues v2-conforming (zero errors) | Task 5 — `bin/issue-inventory` reports zero errors on filed |
| `INTAKE_CUTOFF` applied in Phase 6 | Task 6 — gate warns/errors on non-conforming |

---

### Task 0: Create `docs/issues/filed/` directory

**Files:**
- Create: `docs/issues/filed/` (directory)

- [ ] **Step 1: Create the directory**

```bash
mkdir -p docs/issues/filed
```

Expected: directory exists; empty.

- [ ] **Step 2: Commit**

```bash
git add docs/issues/filed/.gitkeep  # if needed; directory may be tracked via gitkeep
git commit -m "docs(issue-intake): create filed directory for converted drafts"
```

---

### Task 1: Write `pairs.yaml` pairing manifest (31 units)

**Files:**
- Create: `docs/issues/drafts/pairs.yaml`

**Interfaces:**
- Produces: YAML manifest mapping each story draft to its task drafts (31 units total, one per story).

- [ ] **Step 1: Read all 62 drafts and classify kind/level/parent**

Read each draft's header bullets (`Kind:`, `Level:`, `Packages:`, `Suggested parent: #n`). Classify as:
  - Story: `level:story` with a suggested parent epic (#137 Runner execution semantics)
  - Task: `level:story` but notes "Core engine is draft X" or "CLI port only" → demote to `level:task`, child of engine story
  - Chore: `kind:chore`, `level:story`

- [ ] **Step 2: Resolve draft 15 vs 57 duplicate**

Draft 15 ("Deterministic replay from a trace") and draft 57 ("CLI: arazzo replay from a trace") both describe replay. Draft 48 ("Deterministic replay engine") is the core engine story; draft 57 is its CLI task. Draft 15 appears to be a superseded duplicate — mark it as superseded or merge into 48/57.

Decision: keep 48 as engine story, 57 as CLI task child of 48; mark 15 as superseded by 48 (do not convert separately).

- [ ] **Step 3: Write `pairs.yaml`**

Format per spec §7.6:

```yaml
- story: 48-deterministic-replay-engine
  tasks: [57-cli-replay]
- story: 13-workflow-test-runner
  story_engine: 46-test-runner-engine
  tasks: [55-cli-test-runner]
- story: 18-single-step
  tasks: [51-single-step-execution, 60-cli-single-step]
...
```

31 units total (one per story, each with 0+ tasks). Every draft 01–62 referenced exactly once (except superseded 15).

- [ ] **Step 4: Validate pairs.yaml against draft filenames**

Every draft file 01–62 appears exactly once in the manifest (except superseded 15). No orphaned drafts.

- [ ] **Step 5: Commit**

```bash
git add docs/issues/drafts/pairs.yaml
git commit -m "docs(issue-intake): pairs.yaml pairing manifest for 31 draft units"
```

---

### Task 2: Pilot conversion — draft 48 (engine story) → file as issue

**Files:**
- Read: `docs/issues/drafts/48-deterministic-replay-engine.md`
- Create: v2 body for issue #48 (story)
- Modify: move `docs/issues/drafts/48-deterministic-replay-engine.md` → `docs/issues/filed/48-deterministic-replay-engine.md` (when filed)
- GitHub: create issue #N, add `format:v2` label

**Interfaces:**
- Consumes: draft 48 body (v1), `file-issue.php --prompt=<section-id>` for each section
- Produces: v2-conforming issue body, filed on GitHub with `kind:feature`, `level:story`, `format:v2`

- [ ] **Step 1: Gather — read draft 48 and identify code locations**

Read the full draft body. Identify all code references (CliRunner.php:138-170, StepResult.php:21-34, OpenApiExecutorInterface.php, FakePsr18Client, FakerOpenApiExecutor).

- [ ] **Step 2: Locate — produce bare permalinks pinned to a PUSHED commit**

Run `bin/code-permalink <path>:<start>-<end> --ref=origin/main` for each code reference. **Never** use the default (local HEAD): GitHub returns 404 for a SHA not on the remote. Verify each produced SHA is on the remote before filing:

```bash
git merge-base --is-ancestor <sha> origin/main && echo "on remote" || echo "NOT on remote — re-pin"
```

Unwrap any `<details>` wrappers to bare URL lines.

- [ ] **Step 3: Derive — run prompts section by section**

Run `file-issue.php --prompt=<section-id>` for each section in orchestrator order:
  1. problem-statement → R# lines
  2. feature-scope → Non-goals: + D# lines
  3. artifacts → fenced block
  4. runtime-state → phase/step/action/environment
  5. acceptance-criteria → S# blocks, C#, coverage table, Test: lines
  6. source-grounding → claim bullet + bare permalink + Verified: date
  7. impact-profile → BC break: yes\|no
  8. epistemic-matrix → claim/tier/evidence/verification rows

`core-metadata` is scripted (D22) — not prompted.

- [ ] **Step 4: Review — post assembled body as review comment**

Post the assembled v2 body as a comment (diff in PR for drafts). Do not apply to the body first.

- [ ] **Step 5: Write — file via file-issue.php**

On approval: `file-issue.php --kind=kind:feature --level=level:story --parent=137 --title="feat(runner): ..." --sections=<file>` with `format:v2` label added.

- [ ] **Step 6: Verify — `bin/validate-issue` on result**

Run `bin/validate-issue` on the filed body; confirm zero errors, readiness acceptable.

- [ ] **Step 7: Move draft to filed/**

```bash
git mv docs/issues/drafts/48-deterministic-replay-engine.md docs/issues/filed/48-deterministic-replay-engine.md
```

- [ ] **Step 8: Commit**

```bash
git add docs/issues/filed/ docs/issues/drafts/
git commit -m "docs(issue-intake): convert draft 48 (engine story) to v2 and file"
```

---

### Task 3: Pilot conversion — draft 57 (CLI task) → file as child of 48

**Files:**
- Read: `docs/issues/drafts/57-cli-replay.md`
- Create: v2 body for issue #57 (task, child of 48)
- Modify: move `docs/issues/drafts/57-cli-replay.md` → `docs/issues/filed/57-cli-replay.md`
- GitHub: create issue #M, add `format:v2`, parent = issue #N (draft 48's issue number)

**Interfaces:**
- Consumes: draft 57 body, issue #N number from Task 2
- Produces: v2-conforming issue body, filed as sub-issue of 48

- [ ] **Step 1–8: Same pipeline as Task 2, but task-level**

Demote to `level:task` (CLI port of draft 48 core engine). Parent = issue #N (draft 48's GitHub issue number). `core-metadata` Related: cites #N.

- [ ] **Step 9: Commit**

```bash
git add docs/issues/filed/ docs/issues/drafts/
git commit -m "docs(issue-intake): convert draft 57 (CLI task) to v2 and file as child of 48"
```

---

### Task 4: Batch convert remaining drafts

**Files:**
- Read: remaining 60 drafts (01–47, 49–56, 58–62, minus 15 superseded)
- Create: v2 bodies for each
- Modify: move filed drafts to `docs/issues/filed/`
- GitHub: create issues, add `format:v2` labels

**Interfaces:**
- Consumes: remaining drafts, pairs.yaml manifest, parent issue numbers from previous filings
- Produces: all remaining units filed as v2-conforming issues

- [ ] **Step 1: Order units by dependency**

Order stories before their tasks. For pure stories (no CLI port), order by dependency (e.g., traces draft 14 before replay draft 48/57). For task/CLI ports, file after their parent story.

- [ ] **Step 2: Convert each unit (story first, then tasks)**

For each unit in order: Steps 1–7 from Task 2 (gather, locate, derive, review, write, verify, move to filed/). Story files first, then its task files.

- [ ] **Step 3: Commit per unit**

After each unit is filed and moved to filed/, commit:

```bash
git add docs/issues/filed/ docs/issues/drafts/
git commit -m "docs(issue-intake): convert draft <NN> (<slug>) to v2 and file"
```

---

### Task 5: Inventory verification — all filed issues v2-conforming

**Files:**
- Modify: none (read-only)
- GitHub: `gh issue list` for all filed issues

**Interfaces:**
- Consumes: `bin/issue-inventory` over gh JSON dump or filed dir
- Produces: verification report — one row per filed issue, zero errors

- [ ] **Step 1: Generate inventory from filed dir**

```bash
php tools/issue-intake/bin/issue-inventory --dir=docs/issues/filed --format=tsv
```

- [ ] **Step 2: Verify zero errors**

Every row: `errors` empty, `kind` and `level` set (not null), dialect = `v2`.

- [ ] **Step 3: Verify against live backlog**

```bash
gh issue list --state open --json number,title,body,labels > /tmp/live-issues.json
php tools/issue-intake/bin/issue-inventory /tmp/live-issues.json --format=tsv
```

Cross-check: every filed draft has a corresponding live issue with `format:v2` label.

- [ ] **Step 4: Commit any verification notes**

```bash
git add docs/  # any verification notes
git commit -m "docs(issue-intake): inventory verification — all 62 drafts converted and filed"
```

---

### Task 6: Apply ratchet — set `INTAKE_CUTOFF`

**Files:**
- Modify: `.github/workflows/issue-intake.yml` (set `INTAKE_CUTOFF` env var)
- GitHub: repository variable `INTAKE_CUTOFF`

**Interfaces:**
- Consumes: none
- Produces: gate starts emitting `needs-info` on non-conforming non-converted issues

- [ ] **Step 1: Set `INTAKE_CUTOFF`**

Set repository variable `INTAKE_CUTOFF` to the highest filed issue number (or a value above all filed issues so all filed issues are "converted" and everything else is grandfathered until converted).

Decision: set to a value above all filed issue numbers so all filed issues carry `format:v2` and are checked as converted; all older live issues are grandfathered.

- [ ] **Step 2: Verify gate behavior**

Trigger the gate manually on a non-conforming live issue; confirm it emits `needs-info` (errors) or a warnings marker (warnings only).

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/issue-intake.yml
git commit -m "docs(issue-intake): set INTAKE_CUTOFF for gate ratchet (Phase 6)"
```

---

## What this plan deliberately does not do

- **Write conversion code beyond file-issue.php prompts** — conversion is agent-driven using existing prompts; no new PHP conversion tooling.
- **File issues without `gh` access** — if `gh` is not authenticated in this environment, filing is blocked; conversion bodies are still prepared and ready to file.
- **Change schema/engine/prompts during conversion** — they are frozen.

---

**Execution approach:** Subagent-driven (recommended) — 7 tasks with clear interfaces, each independently reviewable. Or native inline.

Please review the plan: does it capture what you want, and which execution approach should we use?
