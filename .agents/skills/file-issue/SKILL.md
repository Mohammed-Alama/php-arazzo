---
name: file-issue
description: File a php-arazzo issue (bug, feature, or chore) at the right hierarchy level with a structurally complete body. Use whenever you are about to create or substantially rewrite an issue with `gh issue create`/`gh issue edit`, so the intake structure is satisfied before GitHub ever sees it.
---

# File Issue

A paved path to a **conforming** php-arazzo issue. It renders a body from
per-section content, validates it with the same engine the `gh` shim and the CI
gate use (`tools/issue-intake`), and only then delegates to `gh`. An issue that
would not conform **never reaches GitHub** — the command exits `1` with the
exact findings the other gates would produce.

Reach for this instead of hand-writing `gh issue create --body "…"`. Hand-rolled
bodies are the root cause of missing-field issues: the `gh` CLI bypasses the
web issue form entirely.

The structure itself lives in `config/issues/schema.yaml` — the single source of
truth for every section, kind and level. It is not restated here. The per-section
instructions the writer follows live in
`.agents/skills/file-issue/prompts/`; this skill points at them, it does not
duplicate their prose.

## When to use

- You are about to open a `kind:bugfix`, `kind:feature`, or `kind:chore` issue.
- You are rewriting an existing issue so it conforms (use `--edit`).
- You have an investigation report from `arazzo-detective` and want to turn its
  Source-Grounding and Epistemic-Matrix blocks into a real issue.

## Run

```bash
php .agents/skills/file-issue/scripts/file-issue.php \
    --kind=kind:bugfix \
    --level=level:story \
    --parent=123 \
    --title="fix(core): Expression $steps.x.outputs.body#/id resolves empty" \
    --sections=sections.json
```

**Package index (no kind, no sections):**

```bash
php .agents/skills/file-issue/scripts/file-issue.php \
    --level=level:package \
    --package=runner \
    --title="chore(runner): runner"
```

| Argument | Required | Meaning |
| --- | --- | --- |
| `--kind=` | yes, unless `--level=level:package` | `kind:bugfix`, `kind:feature`, `kind:chore`, or `kind:release`. Becomes the kind label. |
| `--level=` | yes | `level:package`, `level:epic`, `level:story`, or `level:task`. Becomes the level label. |
| `--title=` | yes | The issue title. |
| `--sections=` | yes, unless `--level=level:package` | Path to a JSON object mapping section id → markdown (`-` reads stdin). |
| `--package=` | required for `level:package` | Primary package name (lowercase, digits, hyphens). Adds `package:<name>` label. |
| `--parent=` | for story/task | Issue number to file under. Its `level:*` label is checked. |
| `--edit=` | no | Edit issue `#n` instead of creating a new one. |
| `--repo=` | no | `owner/name`; defaults to the current repository. |
| `--prompt=` | no | Print a prompt file to stdout and exit `0` — see "The `--prompt` flag" below. Nothing is validated or filed in this mode. |

## The `--sections` payload

A flat JSON object. Keys are **section ids** from the schema; values are the
markdown for that section (a list of strings is joined with newlines). Unknown
keys are ignored — the validator names any required section you left out.

```json
{
  "problem-statement": "`$steps.x.outputs.body#/id` resolves to empty when the body is a JSON string.",
  "dependency-matrix": "Depends on packages/expression; consumed by packages/runner.",
  "artifacts": "Minimal Arazzo document + the failing command (see below).",
  "runtime-state": "workflowId=wf, stepId=fetch, expression on line 3.",
  "acceptance-criteria": "- [ ] the expression resolves to `42` for the fixture",
  "source-grounding": "- the resolver returns null for string bodies.\nhttps://github.com/Mohammed-Alama/php-arazzo/blob/abc1234/packages/evaluation/src/JsonPath.php#L88-L92",
  "impact-profile": "Correctness of every output expression; medium blast radius.",
  "epistemic-matrix": "| Claim | Tier | Evidence |\n| --- | --- | --- |\n| … | T1 | Pest result |"
}
```

`core-metadata` is not supplied in the payload: it is scripted from the issue labels and the parent link (D22). Any supplied value is ignored — the gate computes it instead.

## Required section ids per kind

In schema (`config/issues/schema.yaml`) visual order:

- **kind:bugfix** — core-metadata, problem-statement, dependency-matrix, artifacts, runtime-state, acceptance-criteria, source-grounding, impact-profile, epistemic-matrix
- **kind:feature** — core-metadata, problem-statement, feature-scope, acceptance-criteria, source-grounding, impact-profile, epistemic-matrix
- **kind:chore** — core-metadata, problem-statement, affected-surface, acceptance-criteria, source-grounding, impact-profile
- **kind:release** — core-metadata, problem-statement, affected-surface, acceptance-criteria, source-grounding, impact-profile, epistemic-matrix

## Writing the sections

| Section | Prompt | Gate codes the section owns |
| --- | --- | --- |
| `core-metadata` | none — scripted | `parent-mismatch` (read from labels and the sub-issue link; D22, no agent writes this section) |
| `problem-statement` | `prompts/write-problem-statement.md` | `missing-requirement` |
| `feature-scope` | `prompts/write-feature-scope.md` | `non-goals-missing` |
| `dependency-matrix` | `prompts/write-dependency-matrix.md` | — |
| `affected-surface` | `prompts/write-affected-surface.md` | `rollback-missing` |
| `artifacts` | `prompts/write-artifacts.md` | `artifact-block-missing` |
| `runtime-state` | `prompts/write-runtime-state.md` | — |
| `acceptance-criteria` | `prompts/write-acceptance-criteria.md` | `scenario-shape`, `scenario-no-test`, `blocked-by-unknown-decision`, `requirement-uncovered`, `quality-coverage-missing`, `vague-then`, `checklist-unverifiable` |
| `source-grounding` | `prompts/write-source-grounding.md` | `permalink-invalid`, `permalink-not-bare`, `permalink-unverifiable`, `source-grounding-empty` |
| `impact-profile` | `prompts/write-impact-profile.md` | `bc-line-missing` |
| `epistemic-matrix` | `prompts/write-epistemic-matrix.md` | `tier-invalid` |

The full rule text for each section lives in its prompt; this table names the
prompt and the gate codes it owns so filing and editing follow the same path as
migration. `PromptContractTest` asserts that every required section has a
prompt (except `core-metadata`), that every prompt is listed in
`prompts/README.md` and reachable through `--prompt`, and that every code a
section owns is named in that section's prompt.

## The `--prompt` flag

```bash
php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria
```

Prints `.agents/skills/file-issue/prompts/write-acceptance-criteria.md` to
stdout and exits `0`. Cross-cutting ids work the same way:
`prompt-preamble`, `readme`, `orchestrate-issue-conversion`,
`review-issue-body`. Any unknown id exits `2` with the list of available ids.
Use this to read a section's instruction before writing that section; nothing
is validated or filed in `--prompt` mode.

## Conversion order (spec §7.7)

The orchestrator prompt (`prompts/orchestrate-issue-conversion.md`) fixes the
order sections are written in, because later prompts cite ids the earlier ones
define:

problem-statement → feature-scope/dependency-matrix →
artifacts/runtime-state → acceptance-criteria → source-grounding →
impact-profile → epistemic-matrix

The reviewer prompt (`prompts/review-issue-body.md`) is a separate adversarial
pass: it reads only the assembled body and lists the lines a maintainer would
reject. Neither prompt is restated here — read them through `--prompt`.

## Levels and parents

The hierarchy is **Package → Epic → Story → Task**, enforced by parent/child links,
not by nesting labels alone:

- `level:package` must have **no** parent; carries no `--kind` and no `--sections`; its body is a generated index of the epics under it. Created **only** via `file-issue --level=level:package --package=<name>`.
- `level:epic` must sit **under** a `level:package`.
- `level:story` must sit **under** a `level:epic`.
- `level:task` must sit **under** a `level:story` and can never have children.

Pass `--parent=<n>` for a story or task. The script reads `#n`'s `level:*`
label and refuses if the link would break an invariant (a story under a legacy
issue with no level label is refused, with the reason printed). File the parent
first, then the child.

## What it does, in order

1. Loads `config/issues/schema.yaml`.
2. Reads the sections payload and renders `## <heading>` blocks in schema
   order — the same shape a web-filed form produces.
3. Resolves the parent's level (one `gh issue view <n> --json labels`) when
   `--parent` is given.
4. Runs `Validator::validate()` — the **one** engine (spec D12).
5. If anything does not conform: prints `[code] message` for each finding to
   stderr and **exits 1 without calling `gh`**.
6. Otherwise delegates to `gh issue create` (or `gh issue edit #n`) with
   `--label <kind> --label <level>` and `--parent <n>`.

Exit codes: `0` filed/edited, `1` refused (findings printed), `2` bad usage,
unknown kind/level, unreadable sections file, or a parent lookup that failed.

## Grounding a claim in code (§7)

§7 must carry a **permanent link** — a blob URL pinned to a commit, not a bare
`file:line` that rots the moment the file moves:

`https://github.com/<owner>/<repo>/blob/<sha>/<path>#L<start>-L<end>`

The URL goes on its own bare line under its claim — never `[label](url)`,
never inside `<details>`, never an HTML `<a href>`, and never with text after
the URL on the same line. Follow the section with `Verified: YYYY-MM-DD`,
written only after the pinned lines at that SHA have been read in the current
session:

```markdown
- the resolver returns null for string bodies.
https://github.com/Mohammed-Alama/php-arazzo/blob/abc1234/packages/evaluation/src/JsonPath.php#L88-L92

Verified: 2026-11-15
```

`code-permalink` builds this from a `path:start-end` shorthand, resolving the
repo slug and commit SHA from git:

```bash
php tools/issue-intake/bin/code-permalink packages/evaluation/src/JsonPath.php:88-92
```

Without further flags it prints just the permalink. It warns on stderr when the
file has uncommitted changes or the commit is not on a remote — a link to the
working copy, or to an unpushed SHA, is not permanent.

## Notes

- `gh` is also guarded by the hard shim (`scripts/bin/gh`) when installed, so
  this skill and the shim never disagree — both reach their verdict through
  `tools/issue-intake/bin/validate-issue`.
- After filing, the CI soft gate (`needs-info`) is a safety net, not the primary
  check. Get it right here and the gate stays quiet.
- `FILE_ISSUE_GH_BIN` overrides the `gh` binary (used by the tests).
