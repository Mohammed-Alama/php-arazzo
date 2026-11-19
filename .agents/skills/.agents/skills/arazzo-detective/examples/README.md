# Worked examples

Four investigations, one per focus. Each shows how the five operations play out
and ends at the mandated report path. They are illustrations, not transcripts —
your entities and `file:line` will differ.

---

## 1. Bug — "why does `$steps.x.outputs.body#/id` resolve empty?"

**Focus** `bug` · **Depth** `quick`

- **Ontological Map** — read the resolver, do not recall it. Grep
  `packages/evaluation/src/Plugins` for the JSONPath plugin and
  `packages/expression/src/Ast` for `OutputRef`; list the classes that touch
  `#/`. Protects against solving a phantom problem.
- **Source Reliability Rank** — the class bodies are rank 1; a Pest reproduction
  is rank 1; "JSONPath usually handles `#/`" is rank 4 and may not be spoken as
  fact.
- **Bayesian Credence** — prior: *the suffix is stripped before the evaluator
  sees it*. Evidence: a `--filter` Pest run that prints the evaluated path.
  Posterior: high, or contradicted — either way the tier language follows the
  posterior.
- **Falsifiability Check** — *falsified if a fixture whose body is a JSON
  **object** resolves correctly.* Add that fixture and run it. If it passes, the
  cause is elsewhere; say so.
- **Metacognitive Audit** — did I read the code or recall it? Does my confidence
  match the Pest result?

**Report** `docs/superpowers/investigations/2026-10-14-jsonpath-output-body.md`

---

## 2. Architecture — "may `packages/evaluation` depend on `packages/expression`?"

**Focus** `architecture` · **Depth** `standard`

- **Ontological Map** — read both `composer.json` files and the `use`
  statements; draw the real edge, not the documented one.
- **Source Reliability Rank** — `composer.json`/`composer.lock` outrank the
  architecture doc; a diagram is rank 3 until confirmed by a read.
- **Bayesian Credence** — prior from the layering convention; evidence from the
  actual `use` list; posterior per edge.
- **Falsifiability Check** — *falsified if `ArchTest.php` already asserts this
  boundary.* Run it.
- **Metacognitive Audit** — am I pattern-matching a generic monorepo onto this
  one?

Scan against the **no cross-package leakage** rule and cite `package:*` labels.

**Report** `docs/superpowers/investigations/2026-10-14-evaluation-expression-boundary.md`

---

## 3. Performance — "a large Arazzo fixture parses slowly"

**Focus** `performance` · **Depth** `deep`

- **Ontological Map** — follow `packages/document/src/Parser` from `Loader` to
  `Parser` and into the decoders; name the hot method with `file:line`.
- **Source Reliability Rank** — a measured timing is rank 1; a guess about the
  decoder is rank 5.
- **Bayesian Credence** — prior: repeated decoding dominates. Evidence: a timing
  around the suspected loop. Posterior updates on the number, not the hunch.
- **Falsifiability Check** — *falsified if a fixture with a single decoder is
  equally slow.* Construct both.
- **Metacognitive Audit** — did I optimise the thing I measured?

Pull coverage (`coverage-insights`) before recommending a refactor.

**Report** `docs/superpowers/investigations/2026-10-14-large-fixture-parse.md`

---

## 4. Conformance — "does the validator reject an OpenAPI 3.1 input document?"

**Focus** `conformance` · **Depth** `standard`

- **Ontological Map** — read `packages/document/src/Validator/RuleSet.php` and
  the relevant `Rules/*` classes; find the rule that owns inputs, or find that
  none does (an absence is a finding too).
- **Source Reliability Rank** — the official Arazzo/OpenAPI spec is rank 3; a
  rule's `check()` is rank 1.
- **Bayesian Credence** — prior from the spec text; evidence from a corpus run;
  posterior per clause.
- **Falsifiability Check** — *falsified if the OAI corpus already contains a 3.1
  input fixture.* Search it before asserting a gap.
- **Metacognitive Audit** — am I citing the spec's intent, or the repo's rule?

Anchor on `php scripts/generate-conformance-matrix.php` and the corpus under
`packages/core/tests/Conformance`.

**Report** `docs/superpowers/investigations/2026-10-14-openapi-31-input-conformance.md`
