# Spec: Software Factory Playbook — V1

> **Status:** Proposed · **Date:** 2026-11-16
> **Intent:** `docs/intent/software-factory-playbook.md`
> **Companion:** `docs/SKILLS-REPORT.md` (general skills map)

## Objective

Build a single, operable runbook — `docs/SOFTWARE-FACTORY-PLAYBOOK.md` — that maps each stage of the idea→ship loop to the concrete skill(s) to invoke, the artifact each stage produces, and the exact handoff into `file-issue`/`gh` and the intake gate. The playbook is for a solo maintainer running the pipeline deliberately (human-in-the-loop); AFK autonomy is a future aspiration, not V1.

**User stories:**
- As a maintainer, I open the playbook at any stage and know which skill to invoke, what command to run, what artifact to expect, and how to hand it to `gh`/`file-issue` without re-reading each skill doc.
- As a maintainer, I can file a conforming issue from an investigation, triage incoming work, implement, review, PR, and (optionally) release using only the playbook + the linked skill docs.
- As a maintainer, I can verify a stage's output conforms to the intake schema before it reaches GitHub.

**Success criteria:**
- A maintainer with no prior context can run the full idea→ship loop for a small feature using only the playbook, the linked skill docs, and existing repo commands.
- Every `file-issue`/`gh`/validator command in the playbook is copy-pasteable and correct (verified by running).
- The playbook cross-references `docs/SKILLS-REPORT.md` for the general skills map and does not duplicate it.
- The four named supplement seams (quality bar, source-grounding, adversarial pre-ship, debugging depth) are wired inline at their stages.
- All links resolve; all commands exit 0 when run as documented (where runnable).

## Tech Stack

Documentation only. No runtime code. The playbook is Markdown consumed by humans and agents.

- Format: GitHub-flavored Markdown, matching repo conventions (tables, fenced code blocks, `##` headings).
- Location: `docs/SOFTWARE-FACTORY-PLAYBOOK.md`
- Related files: `docs/SKILLS-REPORT.md`, `docs/intent/software-factory-playbook.md`, `docs/issues/triage-kinds.md`, `CLAUDE.md`, `CONTEXT-MAP.md`.
- Commands referenced (must be accurate): skill ids via OpenCode `skill` tool; `php .agents/skills/file-issue/scripts/file-issue.php ...`; `scripts/bin/gh ...`; `php tools/issue-intake/bin/validate-issue`; `php tools/issue-intake/bin/code-permalink path:start-end`; `scripts/apptree ...`; `make verify`; `composer run test-core`/`test-laravel`; `composer run analyse`.

## Commands

Verification commands for the playbook itself (run before declaring V1 done):

```bash
# Link check (manual or via a markdown linter if available)
grep -oE '\[[^]]+\]\([^)]+\)' docs/SOFTWARE-FACTORY-PLAYBOOK.md

# Validate every file-issue example renders a conforming body (dry-run via validator)
php tools/issue-intake/bin/validate-issue <<< '{"body":"…","labels":["bug","level:story"],"parentLevel":"level:epic"}'

# Confirm referenced skills exist
ls .agents/skills/<id>/SKILL.md

# Repo gates still green (playbook touches no code, but verify nothing drifted)
make verify
```

## Project Structure

```
docs/
├── SKILLS-REPORT.md                  # general skills map (existing)
├── SOFTWARE-FACTORY-PLAYBOOK.md      # NEW: this V1 deliverable
├── intent/
│   └── software-factory-playbook.md  # confirmed intent (existing)
└── superpowers/
    └── specs/
        └── 2026-11-16-software-factory-playbook-design.md   # this spec
```

No changes to `packages/`, `tools/`, `scripts/`, or skills.

## Content Style

- **Stage template** (repeated per stage):
  ```
  ## Stage N — <Name>
  **Goal:** one line
  **Skill(s):** `skill-id` (+ supplement)
  **Inputs:** what you bring in
  **Procedure:** numbered steps with exact commands
  **Artifact:** what this stage produces (file/issue/PR)
  **Handoff to next stage:** exact command + expected gate result
  **Verification:** how you know it's done
  ```
- **Quick-path table** at the top: one row per stage → skill → artifact → next.
- **Commands in fenced blocks**, copy-pasteable, with the repo's actual flags.
- **No duplication** of skill internals; link to the skill doc instead. The playbook owns the wiring, not the skill's how-to.
- **Intake spine** is a first-class section: the full idea→issue flow through `arazzo-detective` → `file-issue` → `gh` shim → CI gate → `triage`, with the exact JSON payload shape for the validator.
- **Supplement seams** are inline callouts (`> Supplement:`) at the relevant stage, not separate stages.
- **Appendix:** "See also" listing `git-workflow-and-versioning`, `idea-refine`, `context-engineering`, `incremental-implementation`, `planning-and-task-breakdown` with one-line pointers.

## Testing Strategy

This is a doc deliverable; "tests" are verification checks:

- **Link resolution:** every relative link in the playbook resolves to an existing file.
- **Command accuracy:** every fenced command is run (or dry-run) and exits as documented. `file-issue` examples are validated via `validate-issue` with a conforming body.
- **Skill existence:** every referenced skill id exists under `.agents/skills/` or is a known superpowers/agent-skill.
- **No drift:** the playbook's stage→skill map matches `docs/SKILLS-REPORT.md` §3 (no contradictions).
- **Peer review:** human reads the playbook end-to-end and confirms operability (the Success Criteria).

No Pest/PHPStan/Pint gates apply (no code changed). `make verify` is run as a sanity check that the repo is still green.

## Boundaries

- **Always:**
  - Cross-reference `docs/SKILLS-REPORT.md` rather than duplicating the skills map.
  - Use the repo's actual commands and paths (verified, not invented).
  - Keep Matt Pocock skills primary; new agent-skills as supplements at the four named seams.
  - Include the `file-issue`/`gh`/intake-gate spine as a first-class section.
  - End the spec turn without starting the playbook (gated workflow).
- **Ask first:**
  - Adding a new stage not in the eight agreed (idea→spec→tickets→code→review→PR→release + bug path + quality bar).
  - Changing the stage template format.
  - Promoting a "see also" supplement to an inline seam.
- **Never:**
  - Duplicate skill internals in the playbook.
  - Edit locked upstream skills or the intake tooling.
  - Claim AFK autonomy is in scope for V1.
  - Start writing the playbook in this turn (spec must be approved first).

## Success Criteria (testable)

1. `docs/SOFTWARE-FACTORY-PLAYBOOK.md` exists and follows the stage template for all 8 stages.
2. Quick-path table present at top.
3. Intake spine section present with `arazzo-detective` → `file-issue` → `gh` shim → CI gate → `triage` flow and exact validator JSON shape.
4. Four supplement seams wired inline (`constraint-driven-development`, `source-driven-development`, `doubt-driven-development`, `debugging-and-error-recovery`).
5. All relative links resolve; all fenced commands verified (exit as documented).
6. No contradiction with `docs/SKILLS-REPORT.md` §3 stage map.
7. Human confirms operability end-to-end.

## Out of Scope (Deferred — V2 backlog)

- AFK autonomous runner (scheduler + queue + merge automation).
- A single orchestrating skill (`software-factory`) — playbook stays a doc.
- Migrating primary flows onto the new agent-skills (would break `ask-matt` router assumptions).
- Per-package playbooks (V1 is repo-level).
- Machine-executable playbook (YAML/JSON workflow definitions).
- Inline wiring of `git-workflow-and-versioning`, `idea-refine`, `context-engineering` (appendix only in V1).

## Open Questions

1. Should the playbook's quick-path table also include the supplement seams, or only the primary stage skills? (Recommendation: primary only, seams as inline callouts.)
2. Should the intake spine section reuse the diagram style from `CONTEXT-MAP.md` (ASCII) or a simpler numbered list? (Recommendation: numbered list + one small ASCII flow, matching the issue-intake spec §6 style.)
3. Does the playbook need a "failure modes / what to do when a gate fails" section? (Recommendation: yes, short, one line per gate.)

## Self-Review Checklist

- [x] Goals traceable to success criteria.
- [x] Boundaries defined (Always/Ask/Never).
- [x] Success criteria specific and testable.
- [x] Out of scope captured (not silently dropped).
- [x] Assumptions surfaced up front.
- [x] No placeholders (TBD/TODO).

---

**Stop point:** This spec is saved. Per the gated workflow, I will not start Phase 2 (planning) or write the playbook in this turn. Awaiting your approval or requested changes. (Open questions O1–O3 above are the only items needing a human decision; recommendations are provided.)