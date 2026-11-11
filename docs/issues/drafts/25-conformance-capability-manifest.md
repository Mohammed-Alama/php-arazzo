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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Emit a machine-readable conformance capability manifest with a drift guard so conformance claims are testable and silent regressions are caught in CI.

**Architectural Boundary:** Bounded context = conformance tooling + CI. Packages: `packages/core` (conformance harness), `scripts` (generate/check scripts), `.github` (CI workflow). Tooling and docs only; no production impact.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** Manifest generated from the same corpus run as the Markdown table; no duplicate execution; each URL/document processed once.
- [ ] **Flexible:** JSON manifest with versioned schema; status vocabulary (`supported`, `partial`, `unsupported`, `not-applicable`); extensible capability list.
- [ ] **Maintainable:** Single generator script writes both Markdown and manifest; drift check script compares manifest against current run; stable manifest for downstream consumption.
- [ ] **Operable:** CI runs drift check on every PR and fails on drift; rollback is removing the CI step and manifest (Markdown matrix remains).
- [ ] **Reliable:** Deterministic manifest output; capabilities marked `supported` must link at least one passing test or corpus row.
- [ ] **Safe:** Tooling only; wrong manifest fails CI, not production; no state mutation.
- [ ] **Secure:** No authorization/ACL impact; no sensitive data in manifest.
- [ ] **Suitable:** Makes conformance claims testable; prevents silent regressions; connects `SelectorTypeSupportedRule` claims to test evidence (draft 23).
- [ ] **Usable:** Machine-readable JSON; `docs/CONFORMANCE.md` links to manifest; status vocabulary explained.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: Manifest is JSON with required structure**
- **Given** the conformance matrix generation pipeline is run; `scripts/generate-conformance-matrix.php` executes against the vendored OAI corpus; no manifest currently exists.
- **When** `scripts/generate-conformance-matrix.php` runs.
- **Then** a JSON manifest is emitted (`docs/conformance/manifest.json`) with `schemaVersion` (e.g., `"1.0"`), `arazzoVersions` (e.g., `["1.0.0", "1.0.1", "1.1.0"]`), `capabilities[]` array, and `corpus[]` array; the manifest is machine-readable and stable for downstream consumption.

**Scenario 2: Each capability has required fields**
- **Given** a capability entry in the manifest (e.g., `{ id: "parse", title: "Document Parsing", ... }`).
- **When** the manifest entry is inspected.
- **Then** it has `id` (string), `title` (string), `status` (one of: `supported`, `partial`, `unsupported`, `not-applicable`), `evidence` (array of test IDs or corpus document paths), and `notes` (string).

**Scenario 3: Minimum capabilities are present**
- **Given** the generated manifest; `#90`, `#86`, `#98`, `#99` cover the conformance suite and fixtures.
- **When** the manifest capabilities are inspected.
- **Then** capabilities include at minimum: parse, validate (structural), validate (semantic), execute (sync), execute (async/queue), selectors, expressions, criteria, dry-run, replay, trace, parallel, test-runner.

**Scenario 4: Drift check fails on stale manifest**
- **Given** a manifest that doesn't match the current corpus run (e.g., a capability was removed or its status changed); `scripts/check-conformance-manifest.php` is run in CI.
- **When** `scripts/check-conformance-manifest.php` compares the manifest against the current corpus run results.
- **Then** the check fails (non-zero exit code); CI fails on drift; the manifest must be regenerated before the PR can merge.

**Scenario 5: CI fails on drift**
- **Given** a PR that changes conformance behavior (e.g., a new validation rule makes a previously-supported capability `partial`) without updating the manifest; `.github/workflows/ci.yml` runs `scripts/check-conformance-manifest.php`.
- **When** CI runs the drift check on the PR.
- **Then** CI fails; the manifest drift is detected; the contributor must update the manifest to reflect the new conformance state.

**Scenario 6: `supported` capability links to evidence**
- **Given** a capability marked `supported` (e.g., `{ id: "execute.sync", status: "supported" }`).
- **When** the manifest entry is inspected.
- **Then** it links at least one passing test or corpus document in `evidence` (e.g., `["packages/core/tests/Conformance/OaiCorpusRunner.php#testExecuteSync"]`); an unsupported claim requires evidence; `docs/CONFORMANCE.md` links to the manifest and explains the status vocabulary.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] Manifest is JSON with `schemaVersion`, `arazzoVersions`, `capabilities[]`, `corpus[]`
- [ ] Each capability has `id`, `title`, `status` (`supported`|`partial`|`unsupported`|`not-applicable`), `evidence`, `notes`
- [ ] Capabilities include at minimum: parse, validate (structural), validate (semantic), execute (sync), execute (async/queue), selectors, expressions, criteria, dry-run, replay, trace, parallel, test-runner
- [ ] `scripts/generate-conformance-matrix.php` emits manifest and Markdown table from one run
- [ ] `scripts/check-conformance-manifest.php` fails when manifest is stale relative to current run
- [ ] CI runs drift check on every PR
- [ ] Capability marked `supported` links at least one passing test or corpus row
- [ ] `docs/CONFORMANCE.md` links to manifest and explains status vocabulary
- [ ] Manifest stable enough for downstream tools to consume
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Rollback: delete manifest and CI step; Markdown matrix remains

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** none; tooling and docs.
- **Blast radius:** CI and the conformance script.
- **Severity:** low — but the manifest becomes the contract other implementations can compare against.
- **Rollback:** remove the CI step and manifest; the Markdown matrix remains.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| The matrix is Markdown-only | T1 | `docs/CONFORMANCE.md` | direct read |
| The generator writes no JSON/manifest | T1 | `generate-conformance-matrix.php`; no JSON branch | direct read |
| A corpus harness exists | T1 | `packages/core/tests/Conformance` | direct read |
| CI has no manifest drift check | T1 | `.github/workflows/ci.yml` | direct read |
| `#90`/`#86`/`#98`/`#99` cover the suite and fixtures | T1 | issue titles and bodies | read |
