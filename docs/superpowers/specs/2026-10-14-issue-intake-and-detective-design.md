# Issue Intake Enforcement & Arazzo Detective — Design

> **Status:** Proposed · **Date:** 2026-10-14 · **Revision 5 (2026-10-14):** the hierarchy gains a level
> above `epic` — **Package-epic → Epic → User-story → Sub-task**. A `package-epic` is one long-lived issue
> per package, carrying exactly one `package:*` label as its *primary* package (a cross-package epic picks
> one). Its body is a **generated index of the epics under it**, exempt from the section model, rewritten by
> a second CI workflow off `issues` events (§A7). Supersedes D6; revises G5 and §A1, §A4, §A5.
> **Revision 4 (2026-10-14):** the CI gate deletes its marker
> comment once the issue conforms — a resolved note is stale copy, and keeping it let the gate read its own
> history as a live ownership claim (§A4).
> **Revision 3 (2026-10-14):** Part B made explicit as the
> **execution engine** for the protocol's five operations (Operation 3 had been implied but unnamed);
> O1–O3 decided.
> **Revision 2 (2026-10-14):** builder check incorporated —
> container has **no** `gh` (correction to D2's install note; the agent runs `gh` on the host), one shared
> validator engine, section-precedence rules, heading normalisation, CI label ownership, grandfathering.
> **Revision 1 (2026-10-14):** initial.
> **Evidence base (direct reads):** `Dockerfile`, `scripts/apptree`, `.gitignore`;
> `.github/ISSUE_TEMPLATE/{bug.yml,config.yml,ecosystem-update.yml}`;
> `.agents/rules/epistemic.md`; `docs/epistemic-protocol.md`; `.agents/skills/triage/SKILL.md` +
> `.agents/skills/setup-matt-pocock-skills/{SKILL.md,triage-labels.md}`; `scripts/`; `CLAUDE.md`;
> `docs/superpowers/specs/2026-08-24-epistemic-rule-design.md`; live `gh label list` (46 labels);
> live `gh issue create --help` / `gh issue edit --help` (v2.97.0); live
> `gh api repos/Mohammed-Alama/php-arazzo/issue-types`; and — on the same machine —
> an external `code-detective` skill on this machine — the skill this project adapts (all files).
> **Not verified from here:** GitHub Actions runtime behaviour, the live body of the ~54 currently-open
> issues, and whether custom (non-default) issue types can be created on a personal account.
> **Corrected in Revision 2:** the apptree container image *was* verified — it is `php:8.4-cli-alpine`
> and installs **no `gh`**; the agent's shell therefore invokes the **host** `gh`.
>
> Confidence markers follow `.agents/rules/epistemic.md`: **[T2]** established by direct read ·
> **[T3]** inferred from evidence (verification given) · **[T4]** speculative.

---

## 1. Summary

Two failure modes hurt issue intake, and they need different machinery:

1. **Vagueness** — an agent files an issue that cannot be reproduced or acted on.
2. **Structural drift** — an agent files an issue that is missing required sections.

The root cause of (2) is that the repo's only enforcement is a GitHub **issue form** (`.github/ISSUE_TEMPLATE/bug.yml`), and issue forms only constrain the **web chooser**. Every agent creates issues with `gh issue create` / `gh issue edit`, which bypasses the form entirely — and the "Unified Technical Tracker" template is a **legacy markdown blob** (`about:`/`title:`/`labels:`), not a validating form. So today there is *zero* agent-side enforcement.

The fix has two parts:

- **Part A — Issue intake enforcement.** A single **schema** is the source of truth. It generates three consumers: the human-facing issue forms, a hard **`gh` shim** on the agent path, and a soft **CI gate**. Structure is deterministic, so it can *block*; vagueness is semantic, so it can only be *chased*.
- **Part B — `arazzo-detective`,** a php-arazzo adaptation of the `code-detective` skill. It produces tiered investigation reports whose two hardest sections ("Source Grounding", "Epistemic Verification Matrix") are exactly what the issue template demands — so the detective is what turns a *vague* bug into a *reproducible* one.

The pipeline is: **investigate (`arazzo-detective`) → file (`file-issue` + shim) → triage (soft CI + `triage` skill).**

---

## 2. Goals / Non-goals / Anti-goals

**Goals**

| # | Goal |
|---|---|
| G1 | Any agent that creates or edits an issue with `gh` cannot produce a *structurally* incomplete issue. |
| G2 | Issues from *any* source (human web, rogue agent, `gh api`, automation) are caught by a universal backstop. |
| G3 | Bug and enhancement issues carry evidence: verified ground-truths separated from inferred assumptions. |
| G4 | One edit to the required-fields contract propagates to agents, humans, and CI — no drift. |
| G5 | Package-epic → Epic → User-story → Sub-task hierarchy exists with real parent/child links *and* labels, consistent with each other. |
| G6 | A repeatable, epistemically-grounded way to investigate php-arazzo code before filing work. |
| G7 | Each package has one long-lived `package-epic` whose body is a *generated* index of the epics under it. |

**Non-goals**

- No LLM in the CI path. The CI gate is deterministic (parse headings, check required sections); semantic judgement stays with the `triage` skill / a human.
- No native GitHub **issue type** for kind or level (decided: types stay dark; kind lives in labels).
- No change to the public `alama/*` package APIs.
- No replacement of GitHub as the system of record.
- No new priority scheme — `P0`–`P3` already exist.

**Anti-goals** (would make this a failure even if it technically works)

- **A1 — Over-ceremony.** A one-line chore must not cost ten minutes of form-filling. This is why chore gets a *deliberately tiny* extension.
- **A2 — Blocking legitimate work.** The shim must never fail a read-only operation, and CI must never close an issue.
- **A3 — A second source of truth.** If the schema and the templates can drift, we have rebuilt the problem.
- **A4 — Report noise.** Detective output that is not saved, not tiered, or not actionable is worse than nothing.
- **A5 — A hand-maintained index.** The package-epic's body must be regenerated from the hierarchy. If a human has to keep it current, it is a lie within a week.

---

## 3. Locked decisions

| ID | Decision | Rationale / evidence |
|---|---|---|
| **D1** | Enforcement split: **hard structural gate on the agent path, soft semantic gate in CI.** | Structure is deterministic and can block; vagueness is semantic and cannot be blocked without also blocking legitimate reports. |
| **D2** | The hard gate is a **`gh` shim** on `PATH` (`scripts/bin/gh`) shadowing the real binary. | All issues go through `gh` CLI; a shim is the only CLI-level chokepoint. Confirmed `gh` 2.97.0 at `/opt/homebrew/bin/gh`. |
| **D3** | CI gate is **soft**: label `needs-info`, comment what's missing, **leave open**, let `triage` chase. | Matches the repo's existing triage state machine (`needs-info`). |
| **D4** | **Kind = labels**, three typed templates: `bug`, `enhancement` (feature), `chore`. | Keeps the `triage` skill's category roles intact (`bug`/`enhancement` are unprefixed); avoids the native-type/label fork. |
| **D5** | **Native issue types stay unused.** | Decided. Note: the repo *does* have them enabled (`Bug`/`Feature`/`Task`) — verified via API — but we deliberately do not depend on them. |
| **D6** | **Level = `level:*` labels + real `--parent` links**, four levels: **Package-epic → Epic → User-story → Sub-task**. *(Revision 5)* | `gh issue create --parent` / `gh issue edit --add-sub-issue` are native in 2.97.0. [T2] GitHub nests to **8 levels**, allows **100 sub-issues per parent**, and each issue has **exactly one parent** — so the chain fits with room to spare. |
| **D7** | The pasted **"Unified Technical Tracker" is a shared skeleton** that each kind extends with its own sections. | Chosen over "it is the bug template". |
| **D8** | **Epistemic Verification Matrix required for `bug` and `enhancement`; exempt for `chore`.** | It earns its keep where evidence matters. |
| **D9** | One **YAML schema** is the single source of truth for all three consumers. | Anti-goal A3. |
| **D10** | Part B skill is named **`arazzo-detective`**, lives at `.agents/skills/arazzo-detective/`. | Frees `code-detective` as the generic ancestor; `.claude/skills/*` symlinks into `.agents/skills/` so one copy serves every agent. |
| **D11** | One project, one document (Parts A and B together). | Decided. |
| **D12** | **One validator engine** — a PHP CLI — is the single implementation of the schema semantics; the shim, CI, and `file-issue` all invoke it. | Anti-goal A3, extended from *schema* to *semantics*: three independent reimplementations would whipsaw agents (shim passes, CI flags). |
| **D13** | **Agents invoke `gh` on the host**, not in the container; the shim's primary install target is therefore the **host `PATH`**. | [T2] `Dockerfile` is `php:8.4-cli-alpine` and installs no `gh`; `scripts/apptree` contains no `gh`. Host `gh` 2.97.0 at `/opt/homebrew/bin/gh`. |
| **D14** | A `package-epic` names **one primary package** via exactly one `package:*` label. A cross-package epic picks the package it is *mostly* about; the others stay labels. *(Revision 5)* | [T2] Each issue has exactly one parent, so a cross-package node *cannot* have two. And 33 of the 55 open issues already carry two `package:*` labels — "mostly about" is the common case, not the corner. |
| **D15** | The `package-epic` body is a **generated index of its child epics**, and the level is **exempt from the section model**. *(Revision 5)* | The body is a derived view; the seven-section template would fight the writer on every rewrite (anti-goal A3). |
| **D16** | The index is rewritten by a **second CI workflow reacting to `issues` events**, with a scheduled sweep as backstop. *(Revision 5)* | "Living" rules out hand-maintenance (anti-goal A5); the sweep heals whatever the event set misses. |
| **D17** | The chain is **mandatory**: `level:user-story`'s only allowed parent is `level:epic`. *(Revision 5, decided with the human)* | Keeps "name the primary package" confined to the handful of epics instead of forcing it on ~33 cross-package backlog items. |
| **D18** | The two lower levels are **renamed**: `level:story` → `level:user-story`, `level:subtask` → `level:sub-task`. *(Revision 5, decided with the human)* | The vocabulary is read by humans; "user-story"/"sub-task" is what the words mean. Costs one live migration (#79) and a sweep of schema, forms, tests, `file-issue` CLI and the `triage-kinds` doc. |
| **D19** | The bootstrap creates a `package-epic` for **all 15** packages. *(Revision 5, decided with the human)* | `packages/` and `package:*` are already 1:1 (verified: 15 directories, 15 labels). Each body is generated-and-thin, and it stops the next epic stalling on a missing parent. |

---

## 4. Part A — Issue intake enforcement

### A1. The schema (single source of truth)

**Location:** `config/issues/schema.yaml` [T4 — proposed; `config/` already exists and holds `config/ecosystem/sources.json`].

Shape:

```yaml
version: 1

labels:
  kind: [bug, enhancement, chore]           # exactly one required
  level: [level:package-epic, level:epic, level:user-story, level:sub-task]  # exactly one required

# Sections present on EVERY kind (the shared skeleton).
skeleton:
  - id: core-metadata
    heading: "🏗️ 1. Core Metadata & Alignment"
    required: true
  - id: problem-statement
    heading: "📝 2. Problem Statement & Intent"
    required: true
  - id: acceptance-criteria
    heading: "🧪 6. Acceptance Criteria & Behavioral Specification"
    required: true
  - id: source-grounding
    heading: "🧬 7. Source Grounding & Code Location"
    required: true
  - id: impact-profile
    heading: "🌍 8. Public Domains & Impact Profile"
    required: true

# Kind-specific sections appended to the skeleton.
kinds:
  bug:
    requires: [dependency-matrix, artifacts, runtime-state, epistemic-matrix]
  enhancement:
    requires: [feature-scope, epistemic-matrix]
    optional: [source-grounding]
  chore:
    requires: [affected-surface]   # epistemic-matrix is simply not listed → not required (D8)

sections:
  dependency-matrix:
    heading: "📦 3. System Dependency Matrix"
  artifacts:
    heading: "📄 4. Structural Artifacts & Raw Inputs"
  runtime-state:
    heading: "🌀 5. Runtime Execution State Context"
  epistemic-matrix:
    heading: "📊 9. Epistemic Verification Matrix"

# Level invariants the shim enforces (GitHub will not).
levels:
  level:package-epic:
    parent: forbidden
    sections: exempt                # body is a generated index (D15), not the template
    requires_package_label: true    # exactly one package:* label = the primary package (D14)
  level:epic:       { parent: { required: true, allowed_parent_levels: [level:package-epic] } }
  level:user-story: { parent: { required: true, allowed_parent_levels: [level:epic] } }
  level:sub-task:   { parent: { required: true, allowed_parent_levels: [level:user-story] }, children: forbidden }
```

**Validation semantics (implemented once, in the validator CLI — D12; the shim, CI, and `file-issue` all call it):**

- **Heading matching is normalised, not exact** — strip emoji, leading numerals and `§`, punctuation, and case before comparing. This is load-bearing: GitHub issue forms emit `### <field label>` while the schema headings are emoji-numbered (`🧬 7. …`). Without normalisation, every human-filed issue would be flagged (the two paths must round-trip — see A2).
- **Section copy lives in the schema** — the labels, descriptions and placeholders the form generator emits are schema fields, not hard-coded in the generator. Otherwise the schema is not the single source of truth (anti-goal A3).
- A body *satisfies* a section if a normalised heading matches **and** the content beneath it is non-empty — "non-empty" excludes whitespace, HTML comments (`<!-- … -->`), unedited placeholder text, and unchecked-only checkbox lists.
- **Precedence — most specific wins.** A kind may *downgrade* a skeleton section to `optional` or `exempt`; it may not remove it from the schema, nor add a section that is not declared under `sections:`. A kind that both `requires` and `exempt`s the same section is a schema error (fail loud at load). A selector naming a section `optional`/`exempt` is a **legitimate override**, not a contradiction.
- Exactly one `level` label must be present, and exactly one `kind` label — **except on `level:package-epic`**, which carries no kind. The level alone is the exemption: a package-epic reports no work, it indexes it.
- **Kind and level are independent across the hierarchy.** An Epic carries a kind; a Story's kind need not match its Epic's. No inheritance rule is imposed.
- Level invariants in `levels:` must hold against the parent issue's labels.
- **A `package-epic` is exempt from every section** (`sections: exempt`) — its body is generated (D15). Every other level requires whatever its kind requires.
- **A `package-epic` must carry exactly one `package:*` label** — that label *is* its primary package (D14). A node that has to name a package cannot also stay silent about which.
- **At most one open `package-epic` per `package:*` label.** This is a *cross-issue* invariant, so the single-issue validator cannot see it; the CI index writer owns it (§A7).

> **Left to the builder:** the exact `requires`/`optional`/`exempt` split per kind is transcribed from the pasted template plus reasonable additions for `enhancement` (`feature-scope`: goals, non-goals, prior art, proposed interface) and `chore` (`affected-surface`: packages touched, risk, rollback). Confirm against the template and adjust. Section numbering in the headings is decorative and copies the pasted template verbatim.

### A2. The typed templates

Three human-facing GitHub **issue forms**, generated from the schema (not hand-maintained): `.github/ISSUE_TEMPLATE/{bug,feature,chore}.yml` using the modern `description:`/`body:` form schema. They supersede the legacy `bug.yml`.

`config.yml` keeps `blank_issues_enabled: false`, so humans must use a form; agents use the CLI and hit the shim.

> **Left to the builder:** whether to keep `ecosystem-update.yml` as-is (it is automation-owned and out of scope) and how the generator emits the `body:` blocks.

### A3. The `gh` shim (hard gate)

**Location:** `scripts/bin/gh` — a **thin POSIX `sh` script**. It inspects the arguments, delegates all validation to the validator CLI (D12), and `exec`s the real `gh`. Shell is chosen so the shim carries **no parsing logic of its own** (anti-goal A3).

**Behaviour:**

1. Resolve the *real* `gh`: scan `PATH` for the first `gh` that is not this script.
2. If the command is `gh api` **and** the path matches `/issues` (create or edit) → treat identically to `issue create`/`issue edit`: validate body, then delegate.
3. If `$1..$2` is not `issue create` / `issue edit` (and not the guarded `gh api`) → `exec` the real `gh` with all args unchanged. **Every other command passes through untouched** (anti-goal A2).
4. If it *is* `issue create` / `issue edit` **and** a body is supplied (`--body`, `--body-file`, `-F`, or stdin `--body-file -`):
   - Parse the body into sections by heading.
   - Resolve the kind label and level label from `--label` / `--add-label` (and, for `edit`, the existing labels via one `gh issue view --json labels` call).
   - Invoke the **validator CLI** (D12) with the body, the resolved labels, and the parent number; assert every required section is satisfied and the invariants hold.
   - On failure: print the specific missing/invalid sections and the `file-issue` escape hatch, then `exit 1` — the real `gh` never runs.
   - On success: `exec` the real `gh`.
4. Label-only edits (`gh issue edit 5 --add-label needs-info`) carry no body → pass through. **This is the deliberate escape hatch** that keeps `triage` state changes and the CI labeller working.

**Installation (corrected in Revision 2 — see D13):**

- **Primary: the host `PATH`.** Agents invoke `gh` from the *host* shell. The container image is `php:8.4-cli-alpine` and ships no `gh`, so a container-only shim would never fire. The shim installs into a `PATH` directory that precedes the real `gh` (e.g. `~/.arazzo/bin`), added by the shell profile — with a documented one-line install.
- **`CLAUDE.md`'s "work inside the container" is about tests/builds, not issue operations.** Issue creation is a host activity; the shim lives on the host.
- **Secondary (optional):** if `gh` is ever baked into the apptree image, install the shim there too. Not required for the gate to work.

**Known bypass vectors** (documented, not hidden):

| Vector | Mitigation |
|---|---|
| `gh api -X POST .../issues` | **Guarded by the shim** (O1) — body validated before delegating to real `gh`. |
| Editing the issue body in the **GitHub web UI** | Caught by the CI backstop (A4). |
| An agent that does not use the shimmed `PATH` | Caught by the CI backstop (A4). |

### A4. The CI soft gate

**Workflow:** `.github/workflows/issue-intake.yml`, triggered on `issues: [opened, edited]`.

Deterministic, no LLM, no secrets; calls the **validator CLI** (D12):

1. Validate the issue body with the same semantics as the shim.
2. If it does not conform → add the label and post **one** marker comment listing exactly which sections
   are missing/empty and what the level invariant expects.
3. If it *does* conform → remove that label **while our own marker comment is present** (see ownership), then **delete that comment**. A resolved notice is stale copy, and leaving it behind would let the gate later mistake its own history for a live ownership claim (Revision 4).
4. **Never close. Never assign.**

**Label ownership (Revision 2).** `needs-info` is owned by the `triage` skill (meaning *"waiting on the reporter"*) — and the intake gate's meaning is close enough to be compatible. The gate therefore reuses `needs-info` as decided, and records ownership with an HTML marker (`<!-- intake-gate -->`) in the comment it posts. It only ever removes `needs-info` when its own marker is present and the body now conforms, so it can never strip a label `triage` set for a different reason.

**Resolved markers are deleted (Revision 4).** The ownership test above only holds while a marker means *"we are flagging this now"*. Left in place after the body conforms, the same marker also means *"we once did"* — and the gate cannot tell the two apart. So a marker that has outlived its purpose would let the gate claim a `needs-info` that `triage` set later, for a reason of its own. The gate therefore deletes its marker comment as soon as it clears the label (and whenever the marker is found without the label, closing the same hole for a label cleared by hand). Consequence: at most one marker comment exists at any time, and only while the gate is actively flagging.

**Grandfathering (Revision 2).** There are ~54 pre-existing open issues with none of the new labels, and the workflow also fires on `edited`. To avoid mass false-positive labelling:

- On `opened` → always validate.
- On `edited` → validate **only if the issue already carries a `level:*` label** (a reliable "new-style issue" signal). Revision 5 leaves this key as it is: it now matches any of the four levels, and `level:package-epic` counts. Legacy issues stay quiet until someone opts them in.
- Issues labelled `ecosystem` are **exempt** (automation-owned; out of scope).

### A5. Label taxonomy (final)

| Axis | Labels | Status |
|---|---|---|
| Kind | `bug`, `enhancement`, `chore` | `bug`/`enhancement` exist; **add `chore`** |
| Level | `level:package-epic`, `level:epic`, `level:user-story`, `level:sub-task` | **new** (Revision 5; the two lower names are renames — D18) |
| Priority | `P0`, `P1`, `P2`, `P3` | exists — unchanged |
| State | `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `ask-human`, `wontfix` | exists (per `triage` skill) |
| Surface | `package:*` | exists — unchanged |

The new labels use the `level:` prefix to match the existing `package:` convention. **`triage`'s category roles keep reading `bug`/`enhancement`** and gain `chore` as a third kind, delivered through a local non-locked mapping layer (O2).

> **Left to the builder:** exact label colours/descriptions.

**Upstream-managed skill (Revision 2).** `triage` is tracked in `skills-lock.json` with a `computedHash`, so editing its `SKILL.md` role list can be silently reverted by a future skills sync. The `chore` kind is therefore integrated through a **local, non-locked layer** (`config/issues/schema.yaml` plus a local mapping note) rather than by editing the upstream skill — see O2.

### A6. The `file-issue` generator skill

**Location:** `.agents/skills/file-issue/SKILL.md` (+ a small generator script).

Purpose: give agents a **paved path** to a conforming body, so the shim rejects only genuine sloppiness rather than punishing agents for guessing the format.

- Takes structured args: kind, level, parent (for story/sub-task), title, and the section contents.
- Renders the body from the schema and invokes `gh issue create`/`gh issue edit` with the right kind/level labels and `--parent`.
- Refuses to emit a body missing required sections *before* it ever reaches `gh` (defence in depth with the shim).
- Is the documented remediation printed by the shim on failure.
- **It is also the only way a `package-epic` is born (Revision 5).** `--level=level:package-epic --package=<name>` is a kind-less mode: it renders the stub index body with the ownership marker and applies `level:package-epic` + `package:<name>`. `Validator::resolveKind()` stays strict everywhere else. No second creation path exists, so the paved path stays paved.

### A7. The package index writer (Revision 5)

A second workflow, `.github/workflows/package-index.yml`, regenerates each `package-epic`'s body from the hierarchy. It mirrors the intake gate: the gate *reads* a body and writes a label; the index writer *reads* children and writes a body.

- **Trigger:** `issues` (`opened`, `edited`, `closed`, `reopened`, `deleted`, `transferred`, `labeled`, `unlabeled`) + `workflow_dispatch` + a `schedule` sweep. Event coverage for *parent-link* changes is the uncertain part — the `issues` event set does not obviously carry a sub-issue-added action [T4] — so the sweep is not a nicety, it is how the index stays true.
- **Scope:** for the affected issue, walk upward to its `package-epic` (at most three hops) and re-render that one body. `workflow_dispatch` takes an `issue_number` input, like the gate.
- **Body:** a generated index — for each child epic: number, title, state, and its story count — under a short generated preamble (the package label, the open-epic count). No hand-written region, therefore nothing to keep current by hand (anti-goal A5).
- **Ownership marker:** the body is wrapped in `<!-- package-index: generated -->` … `<!-- /package-index -->`. Ownership is **regional, not whole-body**: the writer replaces only the text between the markers, so anything a human puts above or below survives. If no marker pair is present — a bootstrapped or hand-seeded body — the writer **adopts** it by appending a marker pair and leaving the existing text untouched. It never refuses to index a `package-epic`, and it never clobbers text outside its markers.
- **Uniqueness scan (the one cross-issue invariant lives here):** every run opens with a query of all open `level:package-epic` issues, grouped by `package:*`. Two sharing a package → respond exactly as §A4 does: add `needs-info`, post **one** `<!-- package-index -->`-marked comment naming both, delete the marker when it resolves. Never close, never assign. A `package:*` label with no `package-epic` is fine — not every package is live yet.
- **Idempotence and loop-safety:** the writer compares the rendered body with the current one and **writes only on a difference**. A rewrite does not itself re-trigger the workflow, because events raised with a workflow's own `GITHUB_TOKEN` do not start new workflow runs [T3 — GitHub's documented recursion guard]. The no-op guard is the belt to that braces.
- **Interaction with the intake gate:** the gate now sees `level:package-epic` edits, and would flag a generated body for lacking sections. The `sections: exempt` rule in §A1 is what stops the two workflows fighting — the gate must resolve the level exemption *before* it checks sections.

---

## 5. Part B — `arazzo-detective`

A php-arazzo adaptation of the `code-detective` skill. Base directory: `.agents/skills/arazzo-detective/`.

```
arazzo-detective/
├── SKILL.md                       # the skill
├── references/grounding-operations.md   # adapted from epistemic-operations.md
├── templates/investigation-report.md.template
└── examples/README.md
```

**Epistemic base (already in-repo — do not duplicate):** `docs/epistemic-protocol.md` (the theory) and `.agents/rules/epistemic.md` (the always-on distilled rule). The skill is the **execution engine**: it runs the protocol's five operations, in order, mechanically, so none can be skipped —

```
1 Ontological Map ─▶ 2 Source Reliability Rank ─▶ 3 Bayesian Credence ─▶ 4 Falsifiability Check ─▶ 5 Metacognitive Audit
```

— grounds each in php-arazzo tools, and points at the protocol for the theory rather than restating it.

**Grounding surface** (replaces the ancestor's Laravel Boost MCP tools):

| Question | php-arazzo tool |
|---|---|
| Does this behaviour actually happen? | targeted Pest: `composer run test-core`, `--filter`, `scripts/manual_test.php`, `scripts/test_checkout.php` |
| What does the CLI do with a real doc? | run the CLI against `LoginAndRetrievePets.arazzo.yaml` and fixtures in `packages/core/tests/fixtures` |
| Convention / correctness | `composer run analyse` (`phpstan`), `phpmd`, `pint`, `scripts/quality-gates.php` |
| What is covered? | `.agents/skills/coverage-insights` |
| Version facts | `composer.json` + `composer.lock` (e.g. `cebe/php-openapi`, `guzzlehttp/guzzle`) |
| Conformance state | `scripts/generate-conformance-matrix.php` output |
| Isolation | `scripts/apptree` (never run destructive things on the host) |

**Scan rules (php-arazzo conventions):** parser vs. validator boundary in `packages/core`; validator additions must ship a `Rule` + Pest test + `RuleSet` registration (`add-rule`); fixture changes need a README entry (`add-fixture`); no cross-package leakage (`package:*` labels, `balanced-coupling`); public `alama/*` API stability; PHP 8.2–8.5 targets; expression evaluation (JSONPath/XPath) parity; error codes registered.

**Profiles:** depth `quick` / `standard` / `deep`; focus `general` / `bug` / `architecture` / `security` / `performance` / **`conformance`**.

**Mandatory output rule (kept from the ancestor):** every investigation is written to a file —

```
docs/superpowers/investigations/YYYY-MM-DD-<target-slug>.md
```

— and only then is a 3–5 line terminal summary given.

**Report structure** (adapted): Target · Epistemic Tier Summary · 1 Ontological Map · 2 Source Reliability Map · 3 Justification Chain · 4 Failure Modes Detected · 5 Confidence Summary · 6 Recommendations · 7 Epistemic Gaps.

**Linkage to Part A (the point of the whole thing):** §3 (Justification Chain) + §2 (Source Reliability Map) are the raw material for the template's **Source Grounding** section; §1 constraints + §5 confidence + §7 gaps are the raw material for the **Epistemic Verification Matrix** (verified ground-truths vs. inferred assumptions). The skill therefore emits, as a final block, the exact template text for those two sections so the agent can paste-and-file.

> **Left to the builder:** whether to reuse `code-detective`'s `SKILL.yaml`/`SKILL.toon` machine-readable pair (this repo's skills are plain `SKILL.md` only — recommend `SKILL.md` only) and whether to align explicitly with the existing `falsification-testing` skill or fold §4 into it.

---

## 6. The pipeline end to end

```
agent hits a problem
  └─ arazzo-detective            → tiered report + Source Grounding + Epistemic Matrix blocks
       └─ file-issue             → renders schema-conformant body
            └─ gh (shimmed)      → HARD gate: structure + kind + level invariants  ──fail──▶ agent fixes, retries
                 └─ GitHub
                      └─ CI soft gate  → non-conforming? label needs-info + comment (never close)
                           └─ triage skill → bug/feature/chore, level, needs-info → chase, or ready-for-agent
```

**Failure it prevents, concretely:**

- An agent that "knows" the template but omits §4 Artifacts → shim exits non-zero on `gh issue create`, names the missing section.
- An agent that runs `gh api` or edits in the web UI → CI labels `needs-info` with the exact gap; `triage` chases.
- An agent that writes a plausible-but-unfounded bug → the detective forces Tier separation, so the issue states what is *verified* vs *assumed*.

---

## 7. Open decisions (need a human, not a builder)

Revision 2 resolved the container-vs-host question by evidence (D13), so it is no longer open. What remains:

| # | Decision | Why it matters | Recommendation |
|---|---|---|---|
| **O1** | Should the shim *also* guard `gh api` calls targeting issue endpoints, or rely on the CI backstop only? | Guarding closes the biggest bypass but adds parser surface to a shell script. | **Guarded** — the shim intercepts `gh api` paths matching `/issues` and validates the body before delegating. |
| **O2** | `triage`'s category set: two-valued (`bug`/`enhancement`, `chore` folded in) or three-valued? | Changes the schema's `kinds` and how `chore` issues get categorised — and `triage` is lock-tracked, so it must be a *local* layer either way. | **Three-valued** (`bug`/`enhancement`/`chore`), implemented in a local non-locked mapping layer so skills sync cannot revert it. |
| **O3** | Investigations directory: `docs/superpowers/investigations/` (committed) vs `.scratch/` (ephemeral)? | Decides whether investigations are durable team artifacts or scratch. | **Committed** (`docs/superpowers/investigations/`), matching the ancestor skill's "reports must be filed, not terminal-only" rule. |

## 8. Left to the builder

- Exact `requires`/`optional`/`exempt` set per kind (§A1), transcribed from the pasted template.
- The **validator CLI** interface (body + labels + parent in → exit code + structured findings out) and its unit tests.
- **Shim form:** a thin shell script that delegates all parsing/validation to the validator CLI (D12) — chosen so the shim carries no logic of its own.
- The **host** install: which `PATH` directory, and the documented one-line setup.
- The schema→form generator and the CI workflow's exact implementation.
- Whether `arazzo-detective`/`file-issue` ship `SKILL.md` only (recommended) or also `SKILL.yaml`/`SKILL.toon`; and adding the `.claude/skills` symlinks + `skills-lock.json` entries for the two new skills.
- Label colours/descriptions; `ecosystem-update.yml` left untouched.

---

## 9. Self-Review Checklist

- [x] **Goals traceable to decisions:** G1→D1/D2, G2→D3/A4, G3→D8/Part B, G4→D9, G5→D6, G6→Part B, G7→D15/D16/§A7.
- [x] **Anti-goals have mechanisms:** A1→D8 + tiny chore extension; A2→shim passthrough + CI never closes; A3→D9 single schema; A4→mandatory save-to-file; A5→D15/D16 generated index (§A7).
- [x] **No placeholders** (no TBD/TODO).
- [x] **Evidence tiered** per `.agents/rules/epistemic.md`; unverified items listed in the header and marked [T4].
- [x] **Scope:** one project, two coupled deliverables; `ecosystem` surfaces explicitly out of scope.
- [x] **Ambiguity surfaced:** open decisions O1–O3 are the only questions that need a human.

## 10. Next Steps

On approval of this spec, transition to `writing-plans` to produce the implementation plan. Suggested phase order:

1. **Schema + validator CLI** (single source of truth + the one implementation of its semantics, with unit tests).
2. **CI soft gate** (immediately useful, zero risk, catches everything).
3. **`file-issue` skill** (gives agents the paved path).
4. **`gh` shim** + **host** `PATH` install (the hard gate).
5. **`arazzo-detective`** (standalone; can ship before or after 2–4).
6. **Label set + triage integration** (`chore`, `level:*`, local `triage` mapping).
7. **Four-level revision (Revision 5):** schema `levels` + the `package-epic` exemption → validator → generated forms → shim → the gate's grandfathering key (already level-prefixed, so unchanged) → `package-index.yml` and its renderer → bootstrap the 15 `package-epic`s (D19) → migrate live labels (#118 gains a parent; #79's level label renamed per D18).
