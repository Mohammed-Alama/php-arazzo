# Grounding operations — the execution guide

The five operations are non-negotiable and ordered. The **theory** (why each
exists, the failure modes it counters) is in `docs/epistemic-protocol.md`; this
file is the **how**, for this repository. Run them in order; each writes one
section of the report.

---

## Operation 1 — Ontological Map

**Rule: read the code. Never reason from memory of "what usually exists".**

### Resolution decision tree

| The question is… | Do this first |
| --- | --- |
| "Why does this misbehave?" (a bug) | **Reproduce it before anything else** — a targeted Pest run, `scripts/manual_test.php`, or a fixture under `packages/core/tests/fixtures`. No reproduction, no finding. |
| "What does this file do?" | Read it. Whole file. Cheapest rank-1 evidence there is. |
| "Where is class / rule / symbol X?" | `grep -rn "class X" packages/*/src` (and `packages/*/tests` for the test). |
| "How does feature X work?" | `grep -rni "<keyword>" packages/*/src`, then read the hits. |
| "Is this a valid Arazzo document?" | Run the CLI / validator against `LoginAndRetrievePets.arazzo.yaml` or a corpus fixture. |

### Entity checklist (write these down, with `file:line`)

- [ ] The classes involved and their **exact paths** (`file:line`).
- [ ] **Injected dependencies** — what each constructor receives (it reveals the
      seam: `packages/expression` vs `packages/evaluation`, etc.).
- [ ] **Guard clauses** — every early `return`/`throw`; most "it doesn't fire"
      bugs are a guard clause returning early.
- [ ] **Registered error codes** — a rule that exists but is not registered is
      dead code. Grep the `RuleSet`.
- [ ] The **boundary** the code sits on (parser vs. validator, package A vs.
      package B) — see the scan rules below.

---

## Operation 2 — Source Reliability Rank

Rank **every** fact by where it came from, then speak in the language its rank
earns.

| Rank | Source | May be spoken as |
| --- | --- | --- |
| **1** | A direct read of the code; a green/red Pest result | fact |
| **2** | `composer.json` + `composer.lock`; phpstan / pint / quality-gate output; `docs/` | fact |
| **3** | The upstream spec (OAI Arazzo / OpenAPI), read from source | "the spec says…" |
| **4** | Training memory | "I believe… — **verify with `<command>`**" (you **must** name the command) |
| **5** | Inference from a sibling file | "by analogy…" |
| **6** | A user statement | "the report says…" |

**Mechanical rule.** Never dress rank-4-or-lower knowledge in rank-1/2 language.
If a sentence starts with "the parser does…" but the source is memory, it is a
defect — fix the sentence, not the confidence.

---

## Operation 3 — Bayesian Credence

For each significant claim, write three lines:

```
Prior:      <what you believed before looking, and why>
Evidence:   <the rank-1/2 observation>
Posterior:  <very high | high | medium | low | contradicted> → <tier language>
```

**Worked example (php-arazzo).**

```
Claim:      `{$steps.fetch.outputs.user#/profile/email}` resolves through
            packages/evaluation.
Prior:      medium — the sibling JSONPath seam lives in `evaluation`, so it is
            plausible the output suffix does too.
Evidence:   direct read — Parser routes `#/` into OutputPart::$jsonPointer
            (packages/expression/src/Parser.php:135,244-267); the evaluator's
            OutputPart branch calls JsonPointer::resolve
            (packages/expression/src/ExpressionEvaluator.php:109).
            `pest … --filter "json pointer suffixes"` → 1 passed.
Posterior:  contradicted → say "it resolves in packages/expression via
            JsonPointer", and say the evaluation package is *not* on this path.
```

**Anchoring-prevention protocol.** Write the hypothesis down **first**, then go
look for what would falsify it (Operation 4). Looking first and hypothesising
after is how a prior masquerades as a posterior.

---

## Operation 4 — Falsifiability Check

For **every** conclusion, complete this sentence before you believe it:

> "This conclusion would be falsified if … , and I looked for that by … ."

If nothing could falsify it, it is an assumption — label it one and move on.

### php-arazzo falsification searches

| If you concluded… | Falsify by |
| --- | --- |
| "this rule fires" | grep that it is registered: `RuleSet::default()` in `packages/document/src/Validator/RuleSet.php` |
| "the parser rejects X" | feed a fixture containing X to the parser and watch the result, don't read the would-be guard |
| "this expression resolves through JSONPath" | grep `JsonPathEvaluator` in the call path; check `JsonPathExpressionPlugin::supports()` |
| "this is covered" | run `coverage-insights` on the file, not the suite |
| "conformance handles it" | run `php scripts/generate-conformance-matrix.php` and read the row |
| "it's in the spec" | open the spec text — rank 3 is not rank 1 |

---

## Operation 5 — Metacognitive Audit

Answer these before emitting the report:

- [ ] Did I **read** the code, or **recall** it? (Operation 1)
- [ ] Is every fact spoken at its **rank**? (Operation 2)
- [ ] Does my confidence follow the **posterior**, not the prior? (Operation 3)
- [ ] Did I name a **falsifier** and go look? (Operation 4)
- [ ] Did I answer the question **asked**, or one I found more interesting?
- [ ] Did I optimise / refactor the thing I **measured**?
- [ ] **php-arazzo convention audit** — did I check the scan rules below, or did
      I apply a generic monorepo pattern onto this one?
- [ ] Is there a claim I *suppressed* because it was inconvenient?

---

## Failure-mode catalog

The protocol catalogs the general modes (hallucination, overconfidence,
anchoring, availability bias, scope creep, Gettier corruption, temporal
staleness, false coherence) — see `docs/epistemic-protocol.md`. The two this
skill is built to catch:

- **Plausible-but-unfounded finding** — a cause asserted without a reproduction.
  Caught by Operation 1 (reproduce/read) + Operation 4 (name the falsifier).
- **Tier laundering** — an inference reported as an observed fact. Caught by
  Operation 2 (rank every fact) + the audit.

---

## Scan rules — php-arazzo conventions

Where "correct" is a repo convention rather than a language rule. Check these
while mapping.

- **Parser / validator boundary.** `packages/document/src/Parser` parses;
  `packages/document/src/Validator` (rules) judges. A rule that re-parses raw
  JSON has crossed the boundary.
- **Validator additions** ship three coordinated edits: a `Rule`, a Pest test,
  and a `RuleSet::default()` registration. `add-rule` scaffolds all three — but
  its hard-coded paths still point at the pre-extraction `packages/core` layout;
  the live files are under `packages/document`, so verify placement after
  running it.
- **Fixture changes** need a README entry — `add-fixture`.
- **No cross-package leakage.** A package must not reach into a sibling's
  internals; the coupling vocabulary is in the `balanced-coupling` skill and the
  `package:*` labels.
- **Public `alama/*` API stability.** An added public method is a contract; type
  it and document it.
- **PHP target is `^8.4`** (`composer.json`) — do not assume older syntax.
- **Expression parity.** JSONPath and XPath spellings of the same access must
  agree across `packages/expression` and `packages/evaluation`.
- **Error codes are registered and namespaced** (`source.*`, `step.*`,
  `workflow.*`, `action.*`, `expression.*`).

---

## Command index (verified present in this repo)

```bash
composer run test-core                                   # vendor/bin/pest packages/core/tests
vendor/bin/pest <path> --filter "<name>"                 # targeted anchor
composer run analyse                                     # phpstan, all 15 packages
vendor/bin/pint                                          # style
php scripts/quality-gates.php                            # aggregate gate
php scripts/generate-conformance-matrix.php              # conformance state
php scripts/manual_test.php                              # ad-hoc CLI exercise
php scripts/test_checkout.php
scripts/apptree                                          # isolated environment
```
