# Prompt: orchestrate issue conversion (`orchestrate-issue-conversion`)

Owns no gate code directly — it sequences the section prompts and assembles the final body. The checks each section owns are enforced by that section's own prompt (its "Owns the gate codes" line) and asserted by `PromptContractTest` assertion 4.

This prompt converts a draft issue — or an `arazzo-detective` investigation report — into a conforming v2 issue body. Later sections cite ids defined by earlier ones, so the writing order is fixed by spec §7.7 and must not be changed:

1. `problem-statement` — `R#` requirements, observed vs expected, ≤150 words.
2. `feature-scope` (feature) / `dependency-matrix` (bugfix) — goals, non-goals, prior art, `D#` decisions; packages and spec versions touched.
3. `artifacts` (bugfix) / `runtime-state` (bugfix) — minimal repro document or command; workflow/step/action execution context.
4. `acceptance-criteria` — `S#` scenarios derived from `R#`, `C#` constraints, nine-quality coverage table.
5. `source-grounding` — one bare commit-pinned permalink per claim, then `Verified: YYYY-MM-DD`.
6. `impact-profile` — public API impact, `BC break:`, blast radius, severity.
7. `epistemic-matrix` — claim / tier / evidence / verification rows for every claim the body makes.

`core-metadata` is not written by any prompt: package, spec version, PHP target, parent and related issues come from labels and the sub-issue link (spec D22). Anything an agent writes there is fabrication.

## Assembly

Render every section as a `## <heading>` block with the exact heading from `config/issues/schema.yaml`, in schema (visual) order — `core-metadata` first, then the kind's required sections in declaration order. Do not rename headings, do not add `###` wrappers, and separate blocks with a blank line. That shape is what the web form and the gate expect.

Cite ids across sections (`R3`, `S2`, `D1`, `C1`); never restate a fact. The citation rule, stop rule, tier rule and prohibitions live in `prompt-preamble.md` and are inherited by every section prompt — quote them by name, do not restate them here.

## Validation

Run the assembled body through `file-issue.php` without `--prompt` — the same `Validator` the gh shim and the CI gate use (spec D12). The body is conforming when it yields zero findings on the codes each section owns; the validator names any required section still missing. Only a conforming body is delegated to `gh issue create` / `gh issue edit`.

## Assembly order (spec §7.7)

```
problem-statement
→ feature-scope / dependency-matrix
→ artifacts / runtime-state
→ acceptance-criteria
→ source-grounding
→ impact-profile
→ epistemic-matrix
```

`core-metadata` is scripted, not prompted — it is never part of this order.

The reviewer (`review-issue-body.md`) runs after assembly: it reads only the assembled body and lists the lines a maintainer would reject.
