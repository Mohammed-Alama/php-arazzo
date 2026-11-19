---
name: arazzo-detective
description: Grounded investigation of php-arazzo — root-cause a bug, map an architecture boundary, or scan for performance or conformance problems — by running the Epistemic Protocol's five operations in order and writing a tiered report to docs/superpowers/investigations/. Use when asked to investigate, analyse, diagnose, or explain "why does X…" / "how does X resolve…" anything in this monorepo.
---

# Arazzo Detective

An investigation is not an opinion. This skill runs the five operations of the
Epistemic Protocol **in order and in full**, grounds every claim in this
repository's own tools, and writes the result to a file **before** it says a
word.

It is the **execution engine** for the protocol already in this repo.
`docs/epistemic-protocol.md` holds the theory; `.agents/rules/epistemic.md` is
the always-on summary every response already follows. This skill is what turns
that theory into a repeatable artefact, so an operation cannot be quietly
skipped.

## The five operations — run in order, in full

Every non-mechanical investigation runs all five. Each produces one section of
the report, so nothing can be dropped.

1. **Ontological Map** — read the code. Enumerate the real entities (packages,
   classes, rules, fixtures, registered error codes) with `file:line`. Never
   reason from memory of "what usually exists".
2. **Source Reliability Rank** — place every fact on the rank table (rank 1
   direct read / Pest result … rank 6 user statement). Never dress rank-4
   knowledge in rank-1 language.
3. **Bayesian Credence** — for each significant claim write *prior → evidence →
   posterior*. Confidence comes from the posterior, never the prior.
4. **Falsifiability Check** — state the evidence that would falsify each
   conclusion, then go look for it. Unfalsifiable ⇒ it is an assumption, not a
   finding.
5. **Metacognitive Audit** — the pre-output checklist (familiarity vs accuracy,
   memory vs code, confidence vs evidence, suppressed uncertainty).

The theory lives in `docs/epistemic-protocol.md` and is **not restated here**.
The per-operation **execution guide** — the resolution decision tree, the
php-arazzo rank table, the worked prior/evidence/posterior example, and the
falsification searches — is `references/grounding-operations.md`.

## When to use

- A bug needs a cause, not a guess ("`$steps.x.outputs.body#/id` resolves
  empty — why?").
- A boundary needs mapping ("how does `packages/evaluation` reach a JSONPath?").
- A concern needs scanning (performance of a large fixture parse, conformance of
  a spec corner, a security review of a source loader).

## When not to use — the mechanical exception

Skip the five operations only when the task is purely mechanical (rename this
variable, format this string) or all knowledge is already Tier 1 axiomata.
**Never** skip when the task involves architecture, factual claims about
external systems, debugging unknown behaviour, or ambiguous requirements. When
in doubt, run the operations — the cost is a few reads.

## Invocation

| Form | Meaning |
| --- | --- |
| `/detect <question>` | narrowest investigation that still grounds the answer |
| `/analyze <target>` | map the structure of a package, class, or boundary |
| `/investigate <target>` | full five-operation pass over a target |
| natural language | "why does…", "how does… resolve…" — treated as `/detect` |

## Depth

- **quick** — one entity, one direct read, one Pest run. The narrowest
  defensible answer. Use for a single symbol or a single failing assertion.
- **standard** (default) — all five operations over the affected package, with a
  Pest result as the empirical anchor.
- **deep** — standard plus a cross-package call-graph and coverage survey
  (`coverage-insights`), for architectural or performance questions.

## Focus

`general` · `bug` · `architecture` · `security` · `performance` · `conformance`

The focus tilts the ontology and selects which scan rules apply (below). A
`bug` focus must produce a reproduction; a `conformance` focus must cite the
profile and the rule that fails.

## The php-arazzo grounding surface

Ground every operation in these, not in memory (replaces the ancestor's MCP
tools):

| Question | Tool |
| --- | --- |
| Does this behaviour actually happen? | targeted Pest: `composer run test-core`, `--filter`, `scripts/manual_test.php`, `scripts/test_checkout.php` |
| What does the CLI do with a real document? | run it against `LoginAndRetrievePets.arazzo.yaml` and the fixtures under `packages/core/tests/fixtures` |
| Convention / correctness | `composer run analyse` (phpstan), `phpmd`, `vendor/bin/pint`, `php scripts/quality-gates.php` |
| What is covered? | the `coverage-insights` skill |
| Version facts | `composer.json` + `composer.lock` (e.g. `cebe/php-openapi`, `guzzlehttp/guzzle`, `softcreatr/jsonpath`) |
| Conformance state | `php scripts/generate-conformance-matrix.php` |
| Isolation | `scripts/apptree` — never run destructive things on the host |

## Scan rules (php-arazzo conventions)

Check these while mapping; they are where "correct" is a repo convention, not a
language rule.

- **Parser vs. validator boundary.** Parsing lives in
  `packages/document/src/Parser`; semantic rules live in
  `packages/document/src/Validator`. A rule that re-parses raw JSON has crossed
  the boundary.
- **Validator additions** ship a `Rule` + a Pest test + a `RuleSet::default()`
  registration (`add-rule` scaffolds all three — but its hard-coded paths still
  point at the pre-extraction `packages/core` layout; the live files are under
  `packages/document`, so verify placement).
- **Fixture changes** need a README entry (`add-fixture`).
- **No cross-package leakage** — a package must not reach into a sibling's
  internals (`package:*` labels; see `balanced-coupling`).
- **Public `alama/*` API stability** — an added public method is a contract.
- **PHP targets** are 8.4 in this repo (`composer.json`); do not assume 8.2.
- **Expression evaluation parity** — JSONPath and XPath must agree
  (`packages/expression`, `packages/evaluation`).
- **Error codes** must be registered and namespaced (`source.*`, `step.*`,
  `workflow.*`, `action.*`, `expression.*`).

## Failure-mode catalog

The protocol catalogs the general modes (hallucination, overconfidence,
anchoring, availability, scope creep, Gettier corruption, temporal staleness,
false coherence). The two this skill is built to catch:

- **Plausible-but-unfounded finding** — a cause asserted without a
  reproduction. Caught by Operation 1 (read the code) + Operation 4 (name the
  falsifier).
- **Tier laundering** — an inference reported as an observed fact. Caught by
  Operation 2 (rank every fact) + the audit.

## Report structure

One section per operation, plus the framing sections:

`Target` · `Epistemic Tier Summary` · `1 Ontological Map` · `2 Source
Reliability Map` · `3 Justification Chain` · `4 Failure Modes Detected` ·
`5 Confidence Summary` · `6 Recommendations` · `7 Epistemic Gaps`.

The skeleton, the placeholder slots, and the operation → section mapping are in
`templates/investigation-report.md.template`.

## Intake linkage

After §7 the report ends with a fenced ```` ```markdown ```` block: the exact
section payload a `file-issue` bug or enhancement issue pastes in. Its two
headings normalise to the schema's `source-grounding` and `epistemic-matrix`
section ids — populated from §2/§3 and from §1/§5/§7 respectively. The report is
the source of the issue's grounding, so open a bug or enhancement with
`file-issue` and paste the block; never hand-write those two sections. Build
§7's link with `php tools/issue-intake/bin/code-permalink <path>:<start>-<end>
--fold` — it emits the permalink and the folded snippet, because a hand-typed
`file:line` is not a permanent link.

## Mandatory output rule

1. Write the report to
   `docs/superpowers/investigations/YYYY-MM-DD-<target-slug>.md`, following
   `templates/investigation-report.md.template`.
2. **Only then** give a terminal summary of 3–5 lines: the answer, its tier, the
   single most important caveat, and the report path.

The file is the deliverable; the summary is the pointer. A summary without a
file is not an investigation.
