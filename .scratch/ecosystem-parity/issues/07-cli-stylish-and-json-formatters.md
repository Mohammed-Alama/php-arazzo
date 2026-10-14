# 07: Add stylish and json formatters to arazzo validate CLI command

**What to build:**
Enhance the `arazzo validate` CLI command with machine-readable `--format=json` output and high-readability `--format=stylish` output, standardized exit codes (0 for valid, 1 for errors), and ruleset override options, matching `@usearazzo/cli`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Add `--format` option (`stylish` default, `json`) to `ValidateCommand` in `packages/cli/src/Console/Command/ValidateCommand.php`.
- [ ] Implement `JsonFormatter` producing structured diagnostic arrays (file, line, column, severity, ruleId, message).
- [ ] Implement `StylishFormatter` providing clean terminal output with color-coded severity markers and tabular alignments.
- [ ] Standardize exit codes: 0 for clean validation, 1 for rule errors, and support `--max-warnings` threshold.
- [ ] Add CLI tests covering stdout formatting and exit codes in `packages/cli/tests/Console/Command/ValidateCommandTest.php`.
