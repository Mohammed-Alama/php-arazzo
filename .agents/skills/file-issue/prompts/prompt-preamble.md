# Shared preamble — rules every section prompt inherits

This file is not a section prompt. It is the rule set every prompt in
`prompts/` inherits, so the citation, stop, tier and prohibition rules are
written once and the gate can test them in one place. A section prompt may
quote these rules by name (`citation rule`, `stop rule`, `tier rule`,
`prohibitions`) instead of restating them.

## Citation rule

A fact appears exactly once in the body: where it is first established.
Everywhere else, cite it by id — `R3`, `S2`, `D1`, `C1` — never by
repeating the fact. The body is a linked structure, not a stack of echoes.

## Stop rule (`D#`)

An unresolved choice is not a scenario and not an assumption: it becomes a
`D#` line in `feature-scope` — `D#. <question>? Options: <a>, <b>. Default: <a>.`
— and every scenario that depends on it is marked `blocked by D#` in its
`Given`/`When`/`Then` lines. A scenario whose decision is still open does not
get a `Test:` line yet: `blocked by D#` with `D#` defined in `feature-scope`
exempts it from `scenario-no-test`, and the dependent agent files the
`Test:` line after the decision closes — `readiness()` still reports
`open-decision` until then. `blocked by D#` for a decision that is not
defined in `feature-scope` raises `blocked-by-unknown-decision` instead.

A `D#` line that ends with `Decided: <option>.` (case-sensitive) is a closed,
resolved decision and does not count as open.

## Tier rule

Every `epistemic-matrix` row carries a tier the body can evidence: `T1`
executed, `T2` read in source, `T3` inferred, `T4` unverified. No tier
without a named evidence line, and `T4` blocks `ready-for-agent` until the
claim is re-verified.

## Prohibitions (spec §7.7)

- **No "draft NN" citations.** A body must not cite an unfiled draft number
  (`draft NN`, `draft #NN`, `Draft NN core`, etc.). Cite the filed issue as
  `#<n>` instead — otherwise the body triggers `draft-reference` (warning).
- **No invented permalinks.** A `source-grounding` link is only a bare
  `https://github.com/<owner>/<repo>/blob/<40-hex>/<path>#L<a>-L<b>` line
  produced from `git` in this session (via `bin/code-permalink`). Never a
  `blob/main` link, never a `file:line`, never a URL typed from memory.
- **No `Verified:` without reading the pinned lines.** The `Verified:`
  `YYYY-MM-DD` line in `source-grounding` may only be written after the
  pinned lines at that SHA have been read in the current session.
- **No "works correctly".** A `Then` step naming no concrete outcome
  (exception class, return value, event, state, count) fails `vague-then`,
  and "works correctly", "as expected", "gracefully", "properly" are the
  classic violations.
- **No restating.** The acceptance section does not repeat the problem
  statement; it names `R#` ids and derives `S#` scenarios from them.
- **One `When`.** A scenario packs exactly one `When` clause; two choices are
  two scenarios, or a `D#`.
