# Draft issue 25 — Machine-readable conformance capability manifest

- **Suggested title:** `[Chore] Emit a machine-readable conformance capability manifest with a drift guard`
- **Kind:** `kind:chore`
- **Level:** `level:story` (suggested parent: #90 Multi-Language Cross-Conformance Test Suite)
- **Packages:** `core`, `scripts`, `.github`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** conformance harness under `packages/core/tests/Conformance`; `scripts/generate-conformance-matrix.php`; CI.
- **Arazzo spec version:** 1.0.x and 1.1.0.
- **PHP target:** 8.4.
- **Ecosystem:** repository tooling and CI.
- **Kind / level:** `kind:chore`, `level:story`, under #90 (Multi-Language Cross-Conformance Test Suite).
- **Priority:** P3 — makes conformance claims testable and prevents silent regressions.

### 📝 2. Problem Statement & Intent

`docs/CONFORMANCE.md` is a generated Markdown table. It records what the vendored
OAI corpus does at a point in time, but it is prose, not a contract:

- there is no machine-readable list of supported capabilities;
- there is no per-capability status (`supported`, `partial`, `unsupported`,
  `not-applicable`) with a reason;
- there is no CI check that fails when a capability regresses;
- the claim in `SelectorTypeSupportedRule` that `rfc9535` is supported is not
  connected to any test evidence (see draft 23).

`#90`, `#86`, `#98` and `#99` build the cross-conformance suite and fixtures.
This draft adds the missing **manifest and drift guard** on top of that evidence
so the suite produces a machine-readable verdict.

### 🛠️ 3. Affected Surface & Rollback

- **Added:** `docs/conformance/manifest.json` (or `conformance/manifest.json`).
- **Changed:** `scripts/generate-conformance-matrix.php` writes both the Markdown
  table and the manifest from the same run.
- **Changed:** `.github/workflows/ci.yml` runs
  `php scripts/check-conformance-manifest.php` and fails on drift.
- **Risk:** low — tooling only. A wrong manifest fails CI, not production.
- **Rollback:** delete the manifest and the CI step; the Markdown matrix remains.

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] The manifest is JSON with `schemaVersion`, `arazzoVersions`, `capabilities[]` and `corpus[]`.
- [ ] Each capability has `id`, `title`, `status` (`supported` | `partial` | `unsupported` | `not-applicable`), `evidence` (test ids or corpus documents) and `notes`.
- [ ] Capabilities include at minimum: parse, validate (structural), validate (semantic), execute (sync), execute (async/queue), selectors, expressions, criteria, dry-run, replay, trace, parallel, test-runner.
- [ ] `scripts/generate-conformance-matrix.php` emits the manifest and the Markdown table from one run.
- [ ] `scripts/check-conformance-manifest.php` fails when the manifest is stale relative to the current run.
- [ ] CI runs the drift check on every PR.
- [ ] A capability marked `supported` must link at least one passing test or corpus row.
- [ ] `docs/CONFORMANCE.md` links to the manifest and explains the status vocabulary.
- [ ] The manifest is stable enough for downstream tools to consume.
- [ ] PHPStan max and Pint pass.

### 🧬 7. Source Grounding & Code Location

- `scripts/generate-conformance-matrix.php` — generates `docs/CONFORMANCE.md` and writes no JSON.
- `docs/CONFORMANCE.md` — Markdown-only matrix.
- `packages/core/tests/Conformance/OaiCorpusRunner.php` — corpus discovery and tier-1/execute harness.
- `.github/workflows/ci.yml` — quality gates; no manifest drift check.

<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/ea8c55ad0bc66c25d27649a6f1a38b7d825461b8/scripts/generate-conformance-matrix.php#L1-L20"><code>generate-conformance-matrix.php:1-20</code></a> @ <code>ea8c55a</code></summary>

```php
/**
 * Generates docs/CONFORMANCE.md - the public conformance matrix - by
 * running the vendored official OAI example corpus through the same
 * stack used by the Pest suite.
 *
 * Usage: php scripts/generate-conformance-matrix.php
 */
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** none; tooling and docs.
- **Blast radius:** CI and the conformance script.
- **Severity:** low — but the manifest becomes the contract other implementations can compare against.
- **Rollback:** remove the CI step and manifest; the Markdown matrix remains.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The matrix is Markdown-only | T1 | `docs/CONFORMANCE.md` | direct read |
| The generator writes no JSON/manifest | T1 | `generate-conformance-matrix.php`; no JSON branch | direct read |
| A corpus harness exists | T1 | `packages/core/tests/Conformance` | direct read |
| CI has no manifest drift check | T1 | `.github/workflows/ci.yml` | direct read |
| `#90`/`#86`/`#98`/`#99` cover the suite and fixtures | T1 | issue titles and bodies | read |
