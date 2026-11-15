# Prompt suite — `.agents/skills/file-issue/prompts/`

One prompt per issue section, validated against the gate by
`tools/issue-intake/tests/PromptContractTest.php`: every worked example in a
`write-*.md` file must pass `Validator` for its section with zero findings on
the checks that section owns. `file-issue.php --prompt=<section-id>` prints
any indexed prompt to stdout.

## Index: section → prompt → checks owned

| Section | Prompt | Gate codes owned |
| --- | --- | --- |
| `acceptance-criteria` | `write-acceptance-criteria.md` | `scenario-shape`, `scenario-no-test`, `requirement-uncovered`, `quality-coverage-missing`, `vague-then` |
| (shared rules) | `prompt-preamble.md` | — citation rule, stop rule (`D#`), tier rule, prohibitions |
| `core-metadata` | none — scripted | `parent-mismatch` (read from labels and the sub-issue link; no agent writes this section) |

The remaining section prompts — `write-problem-statement.md`,
`write-feature-scope.md`, `write-dependency-matrix.md`,
`write-affected-surface.md`, `write-artifacts.md`, `write-runtime-state.md`,
`write-source-grounding.md`, `write-impact-profile.md`,
`write-epistemic-matrix.md` — plus the orchestrator and reviewer prompts are
added by later tasks of the tooling plan; this index grows with them.

## Reachable through `--prompt`

```
php .agents/skills/file-issue/scripts/file-issue.php --prompt=acceptance-criteria
```

prints `write-acceptance-criteria.md` and exits `0`. `--prompt=preamble` and
`--prompt=readme` print the two cross-cutting files. Any unknown id exits `2`
with the list of available ids.
