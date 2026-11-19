# Prompt: review issue body (`review-issue-body`)

Owns no gate code — adversarial read only.

This prompt is the second pass of conversion. It reads **only** the assembled issue body (and the gate's findings, if any) and answers one question: which line here would a maintainer reject? It exists because the agent that wrote the body will defend it.

## Method

Walk the body top to bottom, section by section:

1. For each prompted section, check its content against the gate codes that section's prompt owns — the "Owns the gate codes" line of `prompts/write-<id>.md`. Any line that can trigger one of those codes is a rejectable line; quote the line and name the code.
2. `core-metadata` is scripted, not prompted: check `parent-mismatch` — the parent link and the parent's `level:*` label must agree (package → epic → story → task), and a package-epic must have no parent.
3. `source-grounding`: every code link must be a bare `https://github.com/<owner>/<repo>/blob/<40-hex>/<path>#L<a>-L<b>` line produced by `bin/code-permalink`, and the section must end with `Verified: YYYY-MM-DD` written only after reading the pinned lines. Reject `blob/main`, `file:line`, links inside `<details>`, labels on the URL line, and `Verified:` dates not backed by a read (`permalink-invalid`, `permalink-not-bare`, `permalink-unverifiable`, `source-grounding-empty`).
4. `acceptance-criteria`: exactly one `When` per scenario, every `Then` names a concrete outcome (no "works correctly"), every `R#` is referenced by an `S#`, the nine-quality table is present and every `N/A` row carries a reason, every `C#` carries `Check:` (`scenario-shape`, `scenario-no-test`, `requirement-uncovered`, `quality-coverage-missing`, `vague-then`, `checklist-unverifiable`).
5. `epistemic-matrix`: every row carries T1–T4 with named evidence; `T4` blocks `ready-for-agent` (`tier-invalid`).
6. Prohibitions from `prompt-preamble.md`: no restating, no invented permalinks, no restating the problem statement in the acceptance section, no two `When` clauses in one scenario.

## Output

List each rejectable line as:

```
<line reference> — <why a maintainer rejects it> ([gate code])
```

If nothing is rejectable: `No rejectable lines — the body is conforming.`

Do not suggest rewrites, do not defend lines the writing agent chose, and do not restate prompt prose — name the check code and the line text only.
