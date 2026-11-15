# Prompt: write source grounding (`source-grounding`)

Section: `## 🧬 Source Grounding & Code Location`
Owns the gate codes: `permalink-invalid`, `permalink-not-bare`, `permalink-unverifiable`, `source-grounding-empty`.

This prompt turns located code into pinned evidence: one claim per bullet, the bare URL produced by
`bin/code-permalink` on the line under it, then a trailing `Verified: YYYY-MM-DD` line. It never
restates `problem-statement`, `artifacts` or `acceptance-criteria` — it cites their `R#`/`S#` ids and
pins the exact lines those sections talk about.

## Inputs this prompt may read

- The repo at a frozen SHA (the HEAD resolved in this session), located with `git`.
- `bin/code-permalink <path>:<start>-<end>` output — a bare URL only; paste it verbatim, never
  reshape it, label it, or wrap it.
- The claims the body makes elsewhere — cite them by `R#`/`S#` id, never repeat their text.

## Output template

Use exactly this heading — do not rename it:

```markdown
## 🧬 Source Grounding & Code Location

- <one claim, one line: what the code at the URL does>
https://github.com/<owner>/<repo>/blob/<40-hex-sha>/<path>#L<start>-L<end>

- <second claim>
https://github.com/<owner>/<repo>/blob/<40-hex-sha>/<path>#L<start>-L<end>

Verified: YYYY-MM-DD
```

Rules:

- One claim per bullet, and the URL is a bare line under it — never `[label](url)`, never inside
  `<details>`, never an HTML `<a href>`, and never text after the URL on the same line
  (`permalink-not-bare`).
- Every URL is commit-pinned: `blob/<40-hex-sha>` with `#L<start>-L<end>`, produced by
  `bin/code-permalink` from `git` in this session. The commit must be in this clone and the
  path must exist at that SHA — otherwise the permalink fails `permalink-unverifiable`.
  Never `blob/main`, never `file:line`, never a URL typed from memory (`permalink-invalid`).
- The section must contain at least one bare commit-pinned permalink — without one it fails
  `source-grounding-empty`.
- Write `Verified: YYYY-MM-DD` only after reading the pinned lines at that SHA in the current
  session; a date not backed by reading them is fabrication.
- Inherits the shared `prompt-preamble.md`: citation rule, stop rule (`D#` with options and a
  default), tier rule, and the prohibitions — no invented permalinks, no `Verified:` without reading
  the pinned lines, no restating.

## Worked example

A real, conforming section — pinned to this session's HEAD, `Verified:` after reading the pinned lines.

```markdown
## 🧬 Source Grounding & Code Location

- the resolver that enforces every structural code.
https://github.com/Mohammed-Alama/php-arazzo/blob/f217987561f458f5e5c5f4f46b9d184d315b67cc/tools/issue-intake/src/Validator.php#L588-L677

- the bare-permalink matcher used by the gate.
https://github.com/Mohammed-Alama/php-arazzo/blob/f217987561f458f5e5c5f4f46b9d184d315b67cc/tools/issue-intake/src/Content.php#L156-L169

Verified: 2026-11-15
```
