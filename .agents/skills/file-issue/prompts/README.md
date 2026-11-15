# Prompt suite — `.agents/skills/file-issue/prompts/`

One prompt per issue section, validated against the gate by
`tools/issue-intake/tests/PromptContractTest.php`: every worked example in a
`write-*.md` file must pass `Validator` for its section with zero findings on
the checks that section owns. `file-issue.php --prompt=<section-id>` prints
any indexed prompt to stdout.

## Index: section → prompt → checks owned

| Section | Prompt | Gate codes owned |
| --- | --- | --- |
| `acceptance-criteria` | `write-acceptance-criteria.md` | `scenario-shape`, `scenario-no-test`, `blocked-by-unknown-decision`, `requirement-uncovered`, `quality-coverage-missing`, `vague-then`, `checklist-unverifiable` |
| (shared rules) | `prompt-preamble.md` | — citation rule, stop rule (`D#`), tier rule, prohibitions |
| `core-metadata` | none — scripted | `parent-mismatch` (read from labels and the sub-issue link; no agent writes this section) |
| `problem-statement` | `write-problem-statement.md` | `missing-requirement` |
| `feature-scope` | `write-feature-scope.md` | `non-goals-missing` and the `D#` shape (`D#. <question>? Options: <a>, <b>. Default: <a>.`) |
| `dependency-matrix` | `write-dependency-matrix.md` | none — lists only packages the change touches, never "just in case" |
| `affected-surface` | `write-affected-surface.md` | `rollback-missing` |
| `artifacts` | `write-artifacts.md` | `artifact-block-missing` |
| `runtime-state` | `write-runtime-state.md` | none |
| `impact-profile` | `write-impact-profile.md` | `bc-line-missing` |
| `epistemic-matrix` | `write-epistemic-matrix.md` | `tier-invalid` |
| `source-grounding` | `write-source-grounding.md` | `permalink-invalid`, `permalink-not-bare`, `permalink-unverifiable`, `source-grounding-empty` |
| `orchestrate-issue-conversion` | `orchestrate-issue-conversion.md` | none — fixes the §7.7 writing order and assembles the final body (no section code) |
| `review-issue-body` | `review-issue-body.md` | none — adversarial read of the assembled body; lists rejectable lines (no section code) |

## Reachable through `--prompt`

```
php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria
```

prints `write-acceptance-criteria.md` and exits `0`. `--prompt=prompt-preamble`,
`--prompt=readme`, `--prompt=orchestrate-issue-conversion` and
`--prompt=review-issue-body` print the cross-cutting and conversion prompts.
Any unknown id exits `2` with the list of available ids.
