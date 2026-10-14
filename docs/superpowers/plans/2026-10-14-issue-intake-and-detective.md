# Issue Intake Enforcement & Arazzo Detective — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make it impossible for an agent to file a structurally incomplete GitHub issue, chase vague ones through triage, and give agents an epistemically-grounded way to investigate php-arazzo code before filing.

**Architecture:** One YAML schema is the source of truth. A single PHP validator implements its semantics; three consumers call it — a `gh` shim (hard gate on the agent path), a GitHub Actions workflow (soft gate, universal), and a `file-issue` skill (paved path). A second, independent deliverable adapts the `code-detective` skill to php-arazzo so bug reports carry verified evidence instead of guesses.

**Tech Stack:** PHP 8.4, Pest 5, `symfony/yaml` (already installed transitively), POSIX `sh`, GitHub Actions, `gh` CLI 2.97+.

**Spec:** `docs/superpowers/specs/2026-10-14-issue-intake-and-detective-design.md`

## Global Constraints

- PHP floor: root `composer.json` requires `php: ^8.4` — the tooling runs under 8.4. (The published `packages/*` support 8.2–8.5; the tooling does not need to.)
- No new runtime dependencies. `symfony/yaml` is already present transitively; add it explicitly to `require-dev` only.
- All new PHP: `declare(strict_types=1);`, PSR-4, no `Illuminate` imports (see `packages/core/tests/ArchTest.php`).
- Kind labels, exact strings: `bug`, `enhancement`, `chore`. Level labels, exact strings: `level:epic`, `level:story`, `level:subtask`.
- The CI workflow **never closes and never assigns** an issue.
- The shim **never fails a read-only command** — anything that is not `issue create`, `issue edit`, or `gh api …/issues` passes through untouched.
- Validator exit codes: `0` = conforms, `1` = findings, `2` = usage/internal error.
- Heading matching is normalised (strip emoji, leading numerals, `Section`/`§` tokens, punctuation, collapse whitespace, case-insensitive). Headings found inside fenced code blocks are ignored.

## File Structure

```
config/issues/schema.yaml                      # the source of truth (data)
tools/issue-intake/
├── src/Schema.php                             # load + validate the schema (fail loud)
├── src/SectionParser.php                      # markdown body -> normalised sections
├── src/Validator.php                          # body + labels + parent -> findings
├── src/Finding.php                            # value object
├── src/FormGenerator.php                      # schema -> GitHub issue forms
├── bin/validate-issue                         # CLI: the one engine (D12)
├── bin/generate-forms                         # CLI: emit .github/ISSUE_TEMPLATE/*.yml
└── tests/{SchemaTest,SectionParserTest,ValidatorTest,FormGeneratorTest}.php
scripts/bin/gh                                 # the shim (thin shell)
.agents/skills/file-issue/{SKILL.md,bin/file-issue.php}
.agents/skills/arazzo-detective/{SKILL.md,references/grounding-operations.md,templates/investigation-report.md.template,examples/README.md}
.github/workflows/issue-intake.yml
.github/ISSUE_TEMPLATE/{bug,feature,chore}.yml  # generated
docs/issues/triage-kinds.md                    # local, non-locked triage mapping (O2)
```

**Placement decision (flagged):** the tool lives in `tools/issue-intake/`, registered in `autoload-dev` — *not* under `packages/`, because `packages/*` are published path-repos split by `monorepo-builder.php` and this tool must not ship. Tests join a new `tools` suite.

## Review Focus

These are the input classes the spec implies but no task tests first — each is pinned to the owning task's tests:

1. **Placeholder-only section** — a heading with only the placeholder or an HTML comment must count as *empty* (Task 3).
2. **Headings inside fenced code blocks** — a pasted `arazzo.yaml` or `openapi.yaml` containing `#`/`##` lines must not be mistaken for sections (Task 2).
3. **`--body-file -` (stdin)** — the shim must read stdin once and still hand it to the real `gh` (Task 9).
4. **Duplicate headings** — the same section twice must not error; satisfied if any occurrence has content (Task 2).
5. **Legacy parent without a `level:*` label** — a `level:story` sub-issue whose parent predates the scheme must produce an actionable error, not a crash (Task 3).

---

### Task 1: Schema file + loader

**Files:**
- Create: `config/issues/schema.yaml`
- Create: `tools/issue-intake/src/Schema.php`
- Test: `tools/issue-intake/tests/SchemaTest.php`
- Modify: `composer.json` (add `Alama\Tools\IssueIntake\` → `tools/issue-intake/src/` in `autoload-dev`; add `symfony/yaml` to `require-dev`; add `"test-tools": "vendor/bin/pest tools/issue-intake/tests"`)
- Modify: `phpunit.xml.dist` (add testsuite `Arazzo Tools Test Suite` → `tools/issue-intake/tests`)

**Interfaces:**
- Produces: `Schema::fromFile(string $path): self`; `Schema::kinds(): list<string>`; `Schema::levels(): array<string, array>`; `Schema::skeleton(): list<string>`; `Schema::requiredFor(string $kind): list<string>`; `Schema::optionalFor(string $kind): list<string>`; `Schema::section(string $id): array{heading:string,label:string,placeholder:string}`; `Schema::sectionIds(): list<string>`. Throws `InvalidArgumentException` on any inconsistency.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
use Alama\Tools\IssueIntake\Schema;

it('loads the repo schema and exposes the skeleton', function () {
    $schema = Schema::fromFile(__DIR__ . '/../../../config/issues/schema.yaml');
    expect($schema->skeleton())->toBe([
        'core-metadata', 'problem-statement', 'acceptance-criteria', 'source-grounding', 'impact-profile',
    ]);
    expect($schema->requiredFor('bug'))->toContain('artifacts', 'runtime-state', 'epistemic-matrix');
    expect($schema->requiredFor('chore'))->toBe(['affected-surface']);
});

it('rejects a kind that both requires and exempts the same section', function () {
    Schema::fromArray(['version' => 1, 'kinds' => ['bug'], 'levels' => [], 'skeleton' => [],
        'kind_sections' => ['bug' => ['requires' => ['x'], 'exempt' => ['x']]], 'sections' => ['x' => []]]);
})->throws(InvalidArgumentException::class);

it('rejects a kind referencing an undeclared section', function () {
    Schema::fromArray(['version' => 1, 'kinds' => ['bug'], 'levels' => [], 'skeleton' => [],
        'kind_sections' => ['bug' => ['requires' => ['ghost']]], 'sections' => []]);
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/SchemaTest.php`
Expected: FAIL — `Class "Alama\Tools\IssueIntake\Schema" not found`

- [ ] **Step 3: Write `config/issues/schema.yaml`**

Declare **all** section copy once under `sections:`; `skeleton:` holds ids only (resolves the duplication in the spec's sketch). Every section carries `heading`, `label`, `placeholder`:

```yaml
version: 1
kinds: [bug, enhancement, chore]
levels:
  level:epic:    { parent: forbidden }
  level:story:   { parent: { required: true, allowed: [level:epic] } }
  level:subtask: { parent: { required: true, allowed: [level:story] }, children: forbidden }
skeleton: [core-metadata, problem-statement, acceptance-criteria, source-grounding, impact-profile]
kind_sections:
  bug:         { requires: [dependency-matrix, artifacts, runtime-state, epistemic-matrix] }
  enhancement: { requires: [feature-scope, epistemic-matrix], optional: [source-grounding] }
  chore:       { requires: [affected-surface] }
sections:
  core-metadata:
    heading: "🏗️ 1. Core Metadata & Alignment"
    label: "Core Metadata & Alignment"
    placeholder: "Component / subsystem, Arazzo spec version, PHP target, framework ecosystem"
  # ... one entry per id above, each with heading/label/placeholder ...
```

- [ ] **Step 4: Implement `Schema`**

`fromFile()` reads via `Symfony\Component\Yaml\Yaml::parseFile()` and delegates to `fromArray()`. `fromArray()` asserts: every id in `skeleton` and every `requires`/`optional`/`exempt` entry exists in `sections`; no id appears in both `requires` and `exempt`/`optional` for the same kind; every kind in `kinds` has a `kind_sections` entry; `levels` keys are all in a declared `level:*` set. Throw `InvalidArgumentException` with the offending id in the message.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests/SchemaTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add config/issues/schema.yaml tools/issue-intake composer.json phpunit.xml.dist
git commit -m "feat(issue-intake): add schema source of truth and loader"
```

---

### Task 2: Section parser

**Files:**
- Create: `tools/issue-intake/src/SectionParser.php`
- Test: `tools/issue-intake/tests/SectionParserTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `SectionParser::parse(string $body): array<string, list<string>>` — normalised heading → list of content blocks (one per occurrence). `SectionParser::normalise(string $heading): string`.

- [ ] **Step 1: Write the failing test**

```php
it('matches headings despite emoji, numbering and case', function () {
    $s = SectionParser::parse("### 🧬 7. Source Grounding & Code Location\ncommit abc123\n");
    expect($s[SectionParser::normalise('🧬 7. Source Grounding & Code Location')])
        ->toBe(["commit abc123"]);
});

it('ignores headings inside fenced code blocks', function () {
    $body = "### Problem Statement\n```yaml\n# Not A Section\n## Neither Is This\n```\nreal content";
    $s = SectionParser::parse($body);
    expect(array_keys($s))->toBe([SectionParser::normalise('Problem Statement')]);
});

it('keeps every occurrence of a duplicated heading', function () {
    $s = SectionParser::parse("### Notes\n\n### Notes\nsecond body");
    expect($s[SectionParser::normalise('Notes')])->toBe(['', 'second body']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/SectionParserTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement `SectionParser`**

Split the body into lines. Track fenced-code state (` ``` ` / `~~~`, including info strings); a heading line (`^#{1,6}\s+`) inside a fence is content, not a heading. For each heading, emit `normalise(heading) => []` and append subsequent lines until the next heading. `normalise()`: strip emoji/symbols via a character-class filter, strip a leading `Section`/`§`/digits/`.` run, lowercase, collapse whitespace, trim non-alphanumerics.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests/SectionParserTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tools/issue-intake/src/SectionParser.php tools/issue-intake/tests/SectionParserTest.php
git commit -m "feat(issue-intake): parse and normalise issue body sections"
```

---

### Task 3: Validator

**Files:**
- Create: `tools/issue-intake/src/Finding.php`, `tools/issue-intake/src/Validator.php`
- Test: `tools/issue-intake/tests/ValidatorTest.php`

**Interfaces:**
- Consumes: `Schema`, `SectionParser`.
- Produces: `Validator::__construct(Schema $schema)`; `Validator::validate(ValidationInput $input): list<Finding>` where `ValidationInput` is `array{body:string,labels:list<string>,parentLevel:?string}`; `Finding` is `readonly class Finding { public function __construct(public string $code, public string $message) {} }`. `isConforming(list<Finding>): bool`.

- [ ] **Step 1: Write the failing test**

```php
it('passes a complete bug body', function () { /* all bug sections with content -> [] */ });

it('flags a placeholder-only section as missing', function () {
    $findings = (new Validator(Schema::fromFile($schema_path)))->validate([
        'body' => "### Problem Statement\n\n<!-- explain here -->\n",
        'labels' => ['bug', 'level:epic'], 'parentLevel' => null,
    ]);
    expect(collect($findings)->pluck('code'))->toContain('empty-section');
});

it('requires a story parent to be an epic and names legacy parents', function () {
    $findings = (new Validator($schema))->validate([
        'body' => $complete_story_body, 'labels' => ['enhancement', 'level:story'], 'parentLevel' => null,
    ]);
    expect(collect($findings)->pluck('code'))->toContain('parent-missing');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/ValidatorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement `Validator`**

Required ids = `schema->skeleton()` + `requiredFor(kind)` minus `optionalFor(kind)`/`exempt` (kind override wins). A section is *satisfied* if any parsed occurrence has a non-empty block, where empty = whitespace, `<!-- … -->` only, matches the schema `placeholder` (normalised), or only unchecked `- [ ]` items. Emit codes: `missing-kind-label`, `missing-level-label`, `missing-section`, `empty-section`, `parent-missing`, `parent-level-invalid`, `children-forbidden`. Messages must name the exact heading and, for `parent-missing`, say "parent #N has no level:* label — add one or pick a new-style parent".

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests/ValidatorTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tools/issue-intake/src/{Finding,Validator}.php tools/issue-intake/tests/ValidatorTest.php
git commit -m "feat(issue-intake): validate body, labels and level invariants"
```

---

### Task 4: Validator CLI

**Files:**
- Create: `tools/issue-intake/bin/validate-issue`
- Test: `tools/issue-intake/tests/ValidateIssueCliTest.php`

**Interfaces:**
- Consumes: `Validator`.
- Produces: a CLI contract consumed by the shim and CI. Reads a JSON object from stdin: `{"body": string, "labels": string[], "parentLevel": string|null}`. Prints human findings to stderr, `{"conforming":bool,"findings":[...]}` to stdout. Exit `0`/`1`/`2`.

- [ ] **Step 1: Write the failing test**

```php
it('exits 1 and prints findings for a malformed body', function () {
    $p = Process::run('php tools/issue-intake/bin/validate-issue', json_encode([
        'body' => 'nothing here', 'labels' => ['bug', 'level:epic'], 'parentLevel' => null,
    ]));
    expect($p->exitCode())->toBe(1);
    expect(json_decode($p->output(), true)['conforming'])->toBeFalse();
});

it('exits 2 on invalid JSON', function () {
    expect(Process::run('php tools/issue-intake/bin/validate-issue', 'not json')->exitCode())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/ValidateIssueCliTest.php`
Expected: FAIL — script missing / non-zero unexpected

- [ ] **Step 3: Implement `bin/validate-issue`**

Shebang `#!/usr/bin/env php`; require `vendor/autoload.php` resolved relative to the script; decode stdin; on `json_decode` failure print usage to stderr and `exit(2)`. Otherwise run `Validator`, print findings, exit `1` if any, else `0`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests/ValidateIssueCliTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add tools/issue-intake/bin/validate-issue tools/issue-intake/tests/ValidateIssueCliTest.php
git commit -m "feat(issue-intake): add validator CLI (single engine)"
```

---

### Task 5: Form generator + generated forms

**Files:**
- Create: `tools/issue-intake/src/FormGenerator.php`, `tools/issue-intake/bin/generate-forms`
- Create (generated): `.github/ISSUE_TEMPLATE/bug.yml`, `feature.yml`, `chore.yml`
- Delete: `.github/ISSUE_TEMPLATE/bug.yml` (legacy, replaced by generated output)
- Test: `tools/issue-intake/tests/FormGeneratorTest.php`

**Interfaces:**
- Consumes: `Schema`.
- Produces: `FormGenerator::__construct(Schema $schema)`; `FormGenerator::forKind(string $kind): array` (a valid GitHub issue-form document); `bin/generate-forms` writes one file per kind and is idempotent.

- [ ] **Step 1: Write the failing test**

```php
it('emits one textarea per required section, keyed by the normalised heading', function () {
    $form = (new FormGenerator(Schema::fromFile($schema_path)))->forKind('bug');
    $ids = collect($form['body'])->pluck('attributes.label')->all();
    expect($ids)->toContain('Core Metadata & Alignment', 'Structural Artifacts & Raw Inputs');
});

it('round-trips through the parser', function () {
    $label = (new FormGenerator($schema))->forKind('bug')['body'][0]['attributes']['label'];
    expect(SectionParser::normalise($label))->toBe(SectionParser::normalise('🏗️ 1. Core Metadata & Alignment'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/FormGeneratorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement generator + emit forms**

Build `name`/`description`/`body` from the schema: a `markdown` intro, then one `textarea` (or `dropdown` for kinds/levels) per section, using the section `label` as the field label so generated headings round-trip through `normalise()`. `bin/generate-forms` writes `.github/ISSUE_TEMPLATE/{bug,feature,chore}.yml`, deleting the legacy `bug.yml` first. Run it and commit the output.

- [ ] **Step 4: Run test + verify generated files validate**

Run: `vendor/bin/pest tools/issue-intake/tests/FormGeneratorTest.php && php tools/issue-intake/bin/generate-forms && git diff --exit-code .github/ISSUE_TEMPLATE`
Expected: PASS; idempotent (no diff after the first commit)

- [ ] **Step 5: Commit**

```bash
git add tools/issue-intake/src/FormGenerator.php tools/issue-intake/bin/generate-forms tools/issue-intake/tests/FormGeneratorTest.php .github/ISSUE_TEMPLATE
git commit -m "feat(issue-intake): generate GitHub issue forms from the schema"
```

---

### Task 6: CI soft gate

**Files:**
- Create: `.github/workflows/issue-intake.yml`
- Test: manual dispatch on a scratch issue (documented in the workflow's `run-name`); no unit test (workflow YAML)

**Interfaces:**
- Consumes: `bin/validate-issue` via `gh issue view --json body,labels,number` → JSON stdin.

- [ ] **Step 1: Write the workflow**

Triggers: `issues: [opened, edited]` + `workflow_dispatch`. Steps: checkout; `setup-php` 8.4; `composer install --no-interaction --prefer-dist`; skip if the issue carries `ecosystem`; if it is an `edited` event, skip unless a `level:*` label is present on the issue (grandfathering); pipe `{body, labels, parentLevel}` into `php tools/issue-intake/bin/validate-issue`; on exit `1`, `gh issue edit <n> --add-label needs-info` and post **one** comment — found or created by the marker `<!-- intake-gate -->`; on exit `0`, remove `needs-info` **only if** a comment carrying that marker exists.

- [ ] **Step 2: Verify against a scratch issue**

Open a scratch issue via the form with one section blanked; expect the `needs-info` label plus one marker comment. Fill it in and re-save; expect the label removed and **no** second comment. Then confirm a pre-existing legacy issue edit is untouched.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/issue-intake.yml
git commit -m "ci: soft intake gate labels needs-info, never closes"
```

---

### Task 7: Labels + local triage mapping

**Files:**
- Create: `docs/issues/triage-kinds.md`
- Modify: `.agents/skills/triage/…` — **do not touch** (lock-tracked); the mapping lives in the new doc

**Interfaces:**
- Produces: the label set the schema, shim and CI all assume.

- [ ] **Step 1: Create the labels**

```bash
gh label create chore --description "Routine work: maintenance, tooling, cleanup" --color efa2d2
gh label create level:epic    --description "Level 1 — spans multiple stories" --color 5319e7
gh label create level:story   --description "Level 2 — child of an epic"       --color 5319e7
gh label create level:subtask --description "Level 3 — child of a story"       --color 5319e7
```

- [ ] **Step 2: Write `docs/issues/triage-kinds.md`**

State that categories are three-valued (`bug`/`enhancement`/`chore`, decision O2), that `triage`'s upstream `SKILL.md` is lock-tracked and must not be edited, and that `chore` maps onto the same `needs-*`/`ready-*` state machine.

- [ ] **Step 3: Verify**

Run: `gh label list | grep -E '^(chore|level:)'`
Expected: all four labels listed

- [ ] **Step 4: Commit**

```bash
git add docs/issues/triage-kinds.md
git commit -m "docs(issue-intake): add labels and local triage kind mapping"
```

---

### Task 8: `file-issue` skill

**Files:**
- Create: `.agents/skills/file-issue/SKILL.md`, `.agents/skills/file-issue/bin/file-issue.php`
- Create: `.claude/skills/file-issue` (symlink → `../../.agents/skills/file-issue`)
- Modify: `skills-lock.json` (register the new skill) — or omit if the lock only tracks upstream Matt Pocock skills (verify before editing)

**Interfaces:**
- Consumes: `Schema`, `Validator`, `bin/generate-forms` output.
- Produces: `file-issue.php --kind=bug --level=story --parent=123 --title="…" --sections=sections.json` renders a conforming body, validates it locally, then invokes `gh`. Refuses (exit 1) before reaching `gh` if invalid.

- [ ] **Step 1: Write the skill**

`SKILL.md` frontmatter (`name`, `description`) + body: when to use, the exact invocation, and the argument contract. It exists so agents have a paved path to a conforming body.

- [ ] **Step 2: Implement `bin/file-issue.php`**

Read section content, render `## <heading>` blocks in schema order, run `Validator` locally (fail fast with the same messages as the shim), then `exec` `gh issue create|edit` with `--label <kind> --label <level>` and `--parent` when supplied.

- [ ] **Step 3: Verify end to end**

Run the skill against a scratch issue at each level, including a story under an epic and a sub-task under a story; confirm the parent links appear on GitHub.

- [ ] **Step 4: Commit**

```bash
git add .agents/skills/file-issue .claude/skills/file-issue skills-lock.json
git commit -m "feat(skills): add file-issue paved path"
```

---

### Task 9: `gh` shim

**Files:**
- Create: `scripts/bin/gh`
- Create: `scripts/bin/install-gh-shim` (documented host install)
- Test: `tools/issue-intake/tests/GhShimTest.php`

**Interfaces:**
- Consumes: `bin/validate-issue`.
- Produces: a `gh` that behaves identically except for the three guarded forms. Honours `GATE_GH_BIN` to point at the *real* `gh` (the test seam).

- [ ] **Step 1: Write the failing test**

```php
it('passes --version through untouched', function () {
    $p = Process::run('scripts/bin/gh --version', env: ['GATE_GH_BIN' => '/bin/echo']);
    expect($p->exitCode())->toBe(0);
});

it('blocks an incomplete issue create before calling real gh', function () {
    file_put_contents($marker = tempnam(sys_get_temp_dir(), 'called'), '');
    $p = Process::run('scripts/bin/gh issue create --title x --body "nothing" --label bug --label level:epic',
        env: ['GATE_GH_BIN' => $marker]);
    expect($p->exitCode())->toBe(1)->and(file_get_contents($marker))->toBe('');
});

it('reads --body-file - once and still delegates when valid', function () {
    // feed a complete bug body on stdin; assert GATE_GH_BIN received args (via a stub script that echoes)
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests/GhShimTest.php`
Expected: FAIL — `scripts/bin/gh` not found / not executable

- [ ] **Step 3: Implement the shim**

Resolve the real binary: `$GATE_GH_BIN` if set, else the first `gh` on `$PATH` that is not this script. Guard exactly `issue create`, `issue edit`, and `gh api` whose path contains `/issues`. Capture a body from `--body`, `--body-file`/`-F` (path or `-`, read **once** into a variable), or stdin `--body-file -` and re-supply it downstream. Resolve labels from `--label`/`--add-label`, and for `edit` fetch existing labels with one `gh issue view --json labels`. Pipe `{body,labels,parentLevel}` into `bin/validate-issue`; exit `1` with its stderr on failure; otherwise `exec` the real binary. Everything else `exec`s immediately.

- [ ] **Step 4: Implement the installer**

`scripts/bin/install-gh-shim` creates `~/.arazzo/bin`, symlinks `gh` into it, and prints the line to add to the shell profile (**host**, per D13).

- [ ] **Step 5: Run test to verify it passes + manual check**

Run: `vendor/bin/pest tools/issue-intake/tests/GhShimTest.php`
Expected: PASS. Then with the shim installed: `gh issue list` works, an incomplete `gh issue create` is refused, a complete one succeeds.

- [ ] **Step 6: Commit**

```bash
git add scripts/bin/gh scripts/bin/install-gh-shim tools/issue-intake/tests/GhShimTest.php
git commit -m "feat(issue-intake): hard gh shim gate with host installer"
```

---

### Task 10: `arazzo-detective` skill — core

**Files:**
- Create: `.agents/skills/arazzo-detective/SKILL.md`
- Create: `.agents/skills/arazzo-detective/examples/README.md`
- Create: `.claude/skills/arazzo-detective` (symlink)

**Interfaces:**
- Consumes: `docs/epistemic-protocol.md`, `.agents/rules/epistemic.md`.
- Produces: the skill's invocation contract (`/detect`, `/analyze`, `/investigate` + natural language), depth (`quick`/`standard`/`deep`), and focus (`general`/`bug`/`architecture`/`security`/`performance`/`conformance`) — consumed by the reference and template tasks.

- [ ] **Step 1: Write `SKILL.md`**

Title + one-line purpose; when to use; and **the reasoning engine — the Five Operations, run in order and in full for any non-mechanical task**:

1. **Operation 1 — Ontological Map** — read the code; never reason from memory.
2. **Operation 2 — Source Reliability Rank** — rank every fact by where it came from (php-arazzo rank table in Task 11).
3. **Operation 3 — Bayesian Credence** — prior → evidence → posterior, per significant claim.
4. **Operation 4 — Falsifiability Check** — state the evidence that would falsify each conclusion, then go look for it.
5. **Operation 5 — Metacognitive Audit** — the pre-output checklist.

`SKILL.md` names all five, in order, and links `references/grounding-operations.md` (Task 11) for the per-operation execution guide; it points at `docs/epistemic-protocol.md` for the theory rather than restating it. The rule layer (`.agents/rules/epistemic.md`) stays the lightweight always-on summary; **this skill is the execution engine that runs the five operations and produces the artefact.**

Also: the mandatory output rule (`docs/superpowers/investigations/YYYY-MM-DD-<slug>.md`, *then* a 3–5 line terminal summary); depth and focus profiles; the failure-mode catalog; the exception rule (mechanical tasks).

- [ ] **Step 2: Write `examples/README.md`**

Four worked examples mirroring the ancestor: a bug investigation (e.g. "why does expression `$steps.x.outputs.body#/id` resolve empty"), an architecture analysis (a package boundary), a performance scan (a large fixture parse), and a `conformance` scan.

- [ ] **Step 3: Verify by running it once**

Use it to investigate one real, small question (e.g. how `packages/evaluation` resolves a JSONPath), and confirm a report file appears at the mandated path and the terminal summary is ≤5 lines.

- [ ] **Step 4: Commit**

```bash
git add .agents/skills/arazzo-detective .claude/skills/arazzo-detective
git commit -m "feat(skills): add arazzo-detective grounded investigation skill"
```

---

### Task 11: `arazzo-detective` — grounding operations

**Files:**
- Create: `.agents/skills/arazzo-detective/references/grounding-operations.md`
- Modify: `.agents/skills/arazzo-detective/SKILL.md` (link the reference)

**Interfaces:**
- Consumes: Task 10's profiles.
- Produces: the operation checklists the report template's sections cite.

- [ ] **Step 1: Write the reference**

An execution guide for each of the **Five Operations**, in order, grounded in php-arazzo:

- **Operation 1 — Ontological Map.** Resolution decision tree (bug → reproduce first; file path → read; class/rule → grep `packages/*/src`; description → grep) plus the entity checklist: classes with `file:line`, injected dependencies, guard clauses, registered error codes.
- **Operation 2 — Source Reliability Rank.** The rank table: rank 1 direct read / Pest result; rank 2 `composer.json`+lock, phpstan/pint output, `docs/`; rank 3 upstream spec (OAI Arazzo) docs; rank 4 training memory (**must name the verification command**); rank 5 sibling-file inference; rank 6 user statement. Then the mechanical rule: never assign rank-1/2 language to rank-4+ knowledge.
- **Operation 3 — Bayesian Credence.** prior → evidence → posterior, with a php-arazzo worked example (prior: "this expression resolves through `packages/evaluation`"; evidence: the Pest result; posterior: high/medium/low/contradicted → tier language) and the anchoring-prevention protocol (write the hypothesis down first, then look for what would falsify it).
- **Operation 4 — Falsifiability Check.** The completion template plus php-arazzo falsification searches, e.g. *would be falsified if this rule is not registered* → grep `RuleSet` in `packages/core/src`.
- **Operation 5 — Metacognitive Audit.** The pre-output checklist, including the php-arazzo convention audit.

Then the failure-mode catalog and the scan rules (parser/validator boundary in `packages/core`; `Rule` + Pest test + `RuleSet` registration via `add-rule`; fixture + README via `add-fixture`; no cross-package leakage; `alama/*` API stability; PHP 8.2–8.5; expression (JSONPath/XPath) parity; error codes registered).

- [ ] **Step 2: Verify**

Confirm every command named in the reference exists (`composer run test-core`, `composer run analyse`, `php scripts/quality-gates.php`, `php scripts/generate-conformance-matrix.php`).

- [ ] **Step 3: Commit**

```bash
git add .agents/skills/arazzo-detective/references/grounding-operations.md .agents/skills/arazzo-detective/SKILL.md
git commit -m "docs(skills): add arazzo-detective grounding operations"
```

---

### Task 12: `arazzo-detective` — report template + Part A linkage

**Files:**
- Create: `.agents/skills/arazzo-detective/templates/investigation-report.md.template`
- Modify: `.agents/skills/arazzo-detective/SKILL.md` (mandate the linkage block)

**Interfaces:**
- Consumes: Task 11's checklists.
- Produces: the report skeleton **and** a final fenced block containing the exact `### 🧬 7. Source Grounding & Code Location` and `### 📊 9. Epistemic Verification Matrix` text, ready to paste into a `file-issue` section payload.

- [ ] **Step 1: Write the template**

Sections: Target · Epistemic Tier Summary · 1 Ontological Map · 2 Source Reliability Map · 3 Justification Chain · 4 Failure Modes Detected · 5 Confidence Summary · 6 Recommendations · 7 Epistemic Gaps, each with `{placeholders}` and file:line slots.

**Each section is the artefact of one operation — state this mapping in the template header so nothing is skipped:** §1 ← Operation 1 · §2 ← Operation 2 · §3 ← Operation 3 (prior/evidence/posterior written inline) · §4 ← the scan rules · §5 ← Operations 2+3 · §7 ← Operation 4 · the whole report finalised by Operation 5.

- [ ] **Step 2: Add the linkage block spec to `SKILL.md`**

Rule: after §7, emit a fenced ```` ```markdown ```` block whose headings normalise to the schema's `source-grounding` and `epistemic-matrix` ids, populated from §2/§3 and §1/§5/§7 respectively.

- [ ] **Step 3: Verify**

Run a detective investigation and feed its linkage block through `php tools/issue-intake/bin/validate-issue`; expect `conforming: true`. Confirm the report file shows all five operations: a tier on every claim (§2/§5), at least one prior/evidence/posterior (§3), a stated falsifier (§3/§7), and the completed audit line.

- [ ] **Step 4: Commit**

```bash
git add .agents/skills/arazzo-detective/templates/investigation-report.md.template .agents/skills/arazzo-detective/SKILL.md
git commit -m "feat(skills): add investigation report template and intake linkage"
```

---

## Self-Review

**Spec coverage:** D9/schema → Task 1; D12/single engine → Tasks 1–4; A2/forms → Task 5; D3/A4/soft gate → Task 6; A5/D4/D8/O2/labels → Tasks 1, 7; A6 → Task 8; D2/D13/O1/shim + host install → Task 9; D7/D10/D11/Part B → Tasks 10–12; **G6 / the Five Operations reasoning engine → Tasks 10 (named pipeline), 11 (per-operation guide incl. Operation 3), 12 (operation→report mapping)**; A4 anti-goal → Task 12 step 3. O3 (`docs/superpowers/investigations/`) → Task 10 step 1. All eight spec sections have an owning task.

**Step scan:** every step names a command with an expected result, or a file whose signature is fixed. No "handle edge cases" / "add validation" lines.

**Type consistency:** `Schema::requiredFor()`, `SectionParser::normalise()`, `Validator::validate()`, `Finding::$code`, and the CLI's `{body,labels,parentLevel}` JSON are used identically in Tasks 1–4, 6, 8, 9, 12. Labels are the exact strings from Global Constraints everywhere.

**Review Focus:** the five uncovered input classes each have a test in their owning task — placeholder-only → Task 3; fenced headings → Task 2; stdin body → Task 9 step 1; duplicate headings → Task 2; legacy parent → Task 3.

**Proportion:** no task transcribes an implementation body except `SectionParser`'s fence/normalise algorithm (Tasks 2 step 3), which the tests do not determine.

## Cut point

Tasks 1–9 (Part A) and Tasks 10–12 (Part B) are independently shippable. Part B can be executed first or in parallel; it touches no Part A file.
