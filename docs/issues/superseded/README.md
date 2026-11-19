# Superseded drafts

Drafts `12`–`20` are the older combined drafts that the `45`–`53` core /
`54`–`62` CLI split replaced. They were reviewed during the issue-format-v2
conversion runbook (Phase 2) and are **superseded: they must never be filed**.
Their replacement units were filed as the issue numbers below.

| Superseded draft | Replacement story (issue) | Replacement task (issue) | Notes |
|---|---|---|---|
| `12-machine-readable-run-report.md` | #360 `45-run-report-contract` | #361 `54-cli-run-report` | Core split of the old combined draft |
| `13-workflow-test-runner.md` | #362 `46-test-runner-engine` | #363 `55-cli-test-runner` | Core split |
| `14-execution-traces.md` | #364 `47-execution-trace-artifact` | #365 `56-cli-trace-export` | Core split |
| `15-deterministic-replay.md` | #322 `48-deterministic-replay-engine` | #323 `57-cli-replay` | Filed earlier (Tasks 2–3) |
| `16-dry-run.md` | #366 `49-dry-run-execution-mode` | #367 `58-cli-dry-run` | Core split |
| `17-parallel-dag-execution.md` | #368 `50-parallel-dag-scheduler` | #369 `59-cli-parallel` | Core split |
| `18-single-step-execution.md` | #370 `51-single-step-execution` | #371 `60-cli-single-step` | Core split |
| `19-input-validation-and-defaults.md` | #372 `52-input-validation-defaults` | #373 `61-cli-input-validation` | Core split |
| `20-outbound-http-hardening.md` | #374 `53-http-execution-policy` | #375 `62-cli-http-policy` | Core split |

Partition rule (from `docs/issues/drafts/pairs.yaml`): every draft `01`–`62`
appears exactly once, either as a unit's `story`, as one of a unit's `tasks`,
or under `superseded` naming the unit that replaces it. Drafts `12`–`20` are
recorded under `superseded` and are intentionally absent from
`docs/issues/filed/`.

These files are kept for reference only. Do not file them as issues.
