# Four-Level Issue Hierarchy (Package-epic → Epic → User-story → Sub-task) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fourth level above `epic` — one long-lived `package-epic` per package, whose body is a *generated* index of the epics under it, kept current by a second CI workflow.

**Architecture:** The schema gains the new level and two per-level facts (`kind: forbidden`, `sections: exempt`). The **one** validator (`tools/issue-intake`) learns to honour them, so the shim, the CI gate and `file-issue` agree without a second engine. A new pure renderer (`PackageIndex`) turns a list of child epics into a body; a new script + workflow (`package-index.sh` / `package-index.yml`) feeds it from GraphQL and writes the result back — only on a difference, so a rewrite never loops.

**Tech Stack:** PHP 8.4, Pest 5, `symfony/yaml`, POSIX `sh` + `bash`, `jq`, GitHub Actions, `gh` CLI 2.97+, GitHub GraphQL (`parent`, `subIssues`).

**Spec:** `docs/superpowers/specs/2026-10-14-issue-intake-and-detective-design.md` (**Revision 5**; D14–D19, §A1, §A6, §A7)

## Global Constraints

- PHP floor: root `composer.json` requires `php: ^8.4`.
- No new runtime dependencies. `symfony/yaml` is already in `require-dev`.
- All new PHP: `declare(strict_types=1);`, PSR-4 under `Alama\Tools\IssueIntake\`, no `Illuminate` imports.
- Level labels, exact strings: `level:package-epic`, `level:epic`, `level:user-story`, `level:sub-task`. Kind labels: `bug`, `enhancement`, `chore`.
- A `level:package-epic` carries **no kind** and is **exempt from every section**; it carries **exactly one** `package:*` label.
- The chain is **mandatory**: `user-story` → `epic` → `package-epic` → (nothing). **`level:epic` flips from `parent: forbidden` to parent-required** — this is the single widest-reaching change in the plan.
- The index writer writes **only on a difference**; GitHub events raised with a workflow's own `GITHUB_TOKEN` do not start new runs. Both together are the loop guard.
- The index writer and the gate **never close and never assign**.
- The shim **never fails a read-only command**.
- Validator exit codes: `0` = conforms, `1` = findings, `2` = usage/internal error.
- Tests stay POSIX/GNU-portable: stub binaries use `#!/usr/bin/env bash`; never assert on system `echo`/coreutils behaviour.
- **Code lands and merges before any live issue is touched** (Tasks 7–9): the CI gate validates with the code on `main`, so a `level:package-epic` created early would be flagged `missing-level-label` by the old validator.

## File Structure

```
config/issues/schema.yaml                      # + level:package-epic and its facts
tools/issue-intake/
├── src/Schema.php                             # + level(), formLevels(), levelKindForbidden(), levelSectionsExempt(), levelRequiresPackageLabel()
├── src/Validator.php                          # + level-aware kind, section exemption, package-label invariant
├── src/FormGenerator.php                      # dropdown from formLevels(); new copy
├── src/PackageIndex.php                       # NEW — render a package-epic body from its epics
├── bin/render-package-index                   # NEW — CLI: stdin JSON in, {"body","changed"} out
└── tests/
    ├── SchemaTest.php                         # + the level facts
    ├── ValidatorTest.php                      # + package-epic cases
    ├── FormGeneratorTest.php                  # + exclusion
    ├── FileIssueTest.php                      # + package mode; epic cases re-homed
    ├── GhShimTest.php                         # fixtures re-homed on package-epic
    ├── IssueIntakeGateTest.php                # fixtures re-homed on package-epic
    ├── ValidateIssueCliTest.php               # fixtures re-homed on package-epic
    ├── PackageIndexTest.php                   # NEW
    ├── RenderPackageIndexCliTest.php          # NEW
    └── PackageIndexWorkflowTest.php           # NEW — stub-gh harness for the shell script
.github/
├── ISSUE_TEMPLATE/{bug,feature,chore}.yml     # regenerated
├── scripts/package-index.sh                   # NEW
└── workflows/package-index.yml                # NEW
.agents/skills/file-issue/{SKILL.md,scripts/file-issue.php}
docs/issues/triage-kinds.md
```

## Review Focus

The input classes the spec implies but no task tests first — each pinned to the task that owns the code:

1. **A package-epic with no sections at all conforms** — `sections: exempt` must beat `requiredIdsFor()` (Task 2).
2. **A package-epic with two `package:*` labels** — `multiple-package-labels`, not a crash or a pass (Task 2).
3. **Splice keeps human text outside the markers** — text above *and* below survives a rewrite (Task 4).
4. **Splice appends when there are no markers** — a seeded body is adopted, never refused (Task 4).
5. **Render is stable** — splicing the same render twice yields an identical body, so no write is issued (Tasks 4, 6).
6. **An `epic` with no parent is now an error** — `parent-missing` (Task 2).
7. **`level:package-epic` is absent from the web form** — `formLevels()` excludes it (Task 3).
8. **The old names are gone** — `level:story` / `level:subtask` no longer resolve (Tasks 1–2).

---

### Task 1: The vocabulary flip — schema, loader, fixtures

The level vocabulary changes everywhere at once. Every fixture that names a level is renamed here; the *behaviour* changes those renames expose are Tasks 2, 3 and 5.

**Files:**
- Modify: `config/issues/schema.yaml` (the `levels:` block, lines 30–42)
- Modify: `tools/issue-intake/src/Schema.php`
- Modify: `tools/issue-intake/tests/{SchemaTest,ValidatorTest,FormGeneratorTest,FileIssueTest,GhShimTest,IssueIntakeGateTest,ValidateIssueCliTest}.php`
- Test: `tools/issue-intake/tests/SchemaTest.php`

**Interfaces:**
- Produces: `Schema::level(string $level): array<string, mixed>` (throws `InvalidArgumentException` on an unknown level); `Schema::formLevels(): list<string>`; `Schema::levelKindForbidden(string $level): bool`; `Schema::levelSectionsExempt(string $level): bool`; `Schema::levelRequiresPackageLabel(string $level): bool`.
- `Schema::levels()` keeps its signature.

- [ ] **Step 1: Write the failing test**

Append to `tools/issue-intake/tests/SchemaTest.php`:

```php
it('exposes the four levels and their per-level facts', function () {
    $schema = Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');

    expect(array_keys($schema->levels()))->toBe([
        'level:package-epic', 'level:epic', 'level:user-story', 'level:sub-task',
    ])
        ->and($schema->formLevels())->toBe(['level:epic', 'level:user-story', 'level:sub-task'])
        ->and($schema->levelKindForbidden('level:package-epic'))->toBeTrue()
        ->and($schema->levelSectionsExempt('level:package-epic'))->toBeTrue()
        ->and($schema->levelRequiresPackageLabel('level:package-epic'))->toBeTrue()
        ->and($schema->levelKindForbidden('level:epic'))->toBeFalse()
        ->and($schema->levelSectionsExempt('level:epic'))->toBeFalse();

    expect($schema->level('level:epic')['parent']['allowed_parent_levels'])
        ->toBe(['level:package-epic']);
});

it('rejects a level whose sections fact is neither absent nor exempt', function () {
    Schema::fromArray([
        'version' => 1,
        'kinds' => [],
        'levels' => ['level:x' => ['sections' => 'sometimes']],
        'skeleton' => [],
        'kind_sections' => [],
        'sections' => [],
    ]);
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=SchemaTest`
Expected: FAIL — `formLevels()` and `levelKindForbidden()` are undefined, and `levels()` still returns the old three.

- [ ] **Step 3: Replace the `levels:` block**

In `config/issues/schema.yaml`, replace lines 30–42 with:

```yaml
# Level invariants the shim and CI enforce (GitHub will not).
# `level:package-epic` is the root of a package's tree: it names the package,
# carries no kind, and its body is generated (spec Revision 5, §A7).
levels:
  level:package-epic:
    parent: forbidden
    kind: forbidden
    sections: exempt
    requires_package_label: true
    form: false
  level:epic:
    parent:
      required: true
      allowed_parent_levels: [level:package-epic]
  level:user-story:
    parent:
      required: true
      allowed_parent_levels: [level:epic]
  level:sub-task:
    parent:
      required: true
      allowed_parent_levels: [level:user-story]
    children: forbidden
```

- [ ] **Step 4: Validate and expose the new facts in `Schema.php`**

Inside the `foreach ($levels as $label => $spec)` loop, after the existing `allowed_parent_levels` check (before the loop closes at line 123), add:

```php
            $sections = $spec['sections'] ?? null;

            if ($sections !== null && $sections !== 'exempt') {
                throw new InvalidArgumentException("levels.\"{$label}\".sections must be \"exempt\" when set.");
            }

            $children = $spec['children'] ?? null;

            if ($children !== null && $children !== 'forbidden') {
                throw new InvalidArgumentException("levels.\"{$label}\".children must be \"forbidden\" when set.");
            }

            foreach (['kind', 'requires_package_label', 'form'] as $flag) {
                $value = $spec[$flag] ?? null;

                if ($value !== null && $value !== 'forbidden' && !is_bool($value)) {
                    throw new InvalidArgumentException(
                        "levels.\"{$label}\".{$flag} must be a boolean or \"forbidden\".",
                    );
                }
            }
```

After `levels()` (line 152), add:

```php
    /** @return array<string, mixed> */
    public function level(string $level): array
    {
        if (!array_key_exists($level, $this->levels)) {
            throw new InvalidArgumentException("Unknown level \"{$level}\".");
        }

        return $this->levels[$level];
    }

    /**
     * The levels the web form offers. A package-epic is excluded by design: it
     * carries no kind and no sections, and `file-issue` is its only creation
     * path (spec Revision 5, §A6).
     *
     * @return list<string>
     */
    public function formLevels(): array
    {
        return array_values(array_filter(
            array_keys($this->levels),
            fn (string $level): bool => ($this->levels[$level]['form'] ?? true) !== false,
        ));
    }

    public function levelKindForbidden(string $level): bool
    {
        return ($this->levels[$level]['kind'] ?? null) === 'forbidden';
    }

    public function levelSectionsExempt(string $level): bool
    {
        return ($this->levels[$level]['sections'] ?? null) === 'exempt';
    }

    public function levelRequiresPackageLabel(string $level): bool
    {
        return ($this->levels[$level]['requires_package_label'] ?? false) === true;
    }
```

- [ ] **Step 5: Rename every level fixture in the suite**

Two mechanical substitutions across `tools/issue-intake/tests/`: `level:story` → `level:user-story`, and `level:subtask` → `level:sub-task`. Leave every `level:epic` fixture alone; Task 2 re-homes the ones whose expectation changes.

```bash
grep -rn 'level:story\|level:subtask' tools/issue-intake/tests
# after editing:
grep -rn 'level:story\|level:subtask' tools/issue-intake/tests   # expect: no output
```

- [ ] **Step 6: Run the suite and record exactly what is still red**

Run: `composer run test-tools`
Expected: `SchemaTest` PASS. These are **expected red, and each is fixed by the stated task**:
- `FormGeneratorTest` — the dropdown now lists four levels (Task 3).
- `ValidatorTest` — the epic-with-no-parent case (Task 2).
- `FileIssueTest` — the epic-with-a-parent case (Task 2), the package mode (Task 5).
- `GhShimTest`, `IssueIntakeGateTest`, `ValidateIssueCliTest` — conforming fixtures that pair `level:epic` with no parent (Task 2).

- [ ] **Step 7: Commit**

```bash
git add config/issues/schema.yaml tools/issue-intake/src/Schema.php tools/issue-intake/tests
git commit -m "feat(issue-intake): add level:package-epic and rename the lower levels"
```

---

### Task 2: Validator — level-aware kind, section exemption, package label

**Files:**
- Modify: `tools/issue-intake/src/Validator.php`
- Modify: `tools/issue-intake/tests/{ValidatorTest,FileIssueTest,GhShimTest,IssueIntakeGateTest,ValidateIssueCliTest}.php`
- Test: `tools/issue-intake/tests/ValidatorTest.php`

**Interfaces:**
- Consumes: `Schema::levelKindForbidden()`, `Schema::levelSectionsExempt()`, `Schema::levelRequiresPackageLabel()` (Task 1).
- Produces: new finding codes `missing-package-label`, `multiple-package-labels`.

- [ ] **Step 1: Write the failing test**

In `tools/issue-intake/tests/ValidatorTest.php`, **replace** the case `it('refuses a parent on an epic', ...)` with the package-epic variant, then append:

```php
it('accepts a package-epic with no kind and no sections', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => "Anything at all — the body is generated.\n",
        'labels' => ['level:package-epic', 'package:runner'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect($findings)->toBe([]);
});

it('demands exactly one package label on a package-epic', function () {
    $schema = arazzoSchema();

    $missing = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package-epic'], 'parent' => null,
    ]);

    expect(codes($missing))->toBe(['missing-package-label']);

    $tooMany = (new Validator($schema))->validate([
        'body' => '', 'labels' => ['level:package-epic', 'package:runner', 'package:runtime'], 'parent' => null,
    ]);

    expect(codes($tooMany))->toBe(['multiple-package-labels']);
});

it('forbids a parent on a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => '', 'labels' => ['level:package-epic', 'package:runner'],
        'parent' => 3, 'parentLevel' => 'level:package-epic',
    ]);

    expect(codes($findings))->toBe(['parent-forbidden']);
});

it('requires an epic to sit under a package-epic', function () {
    $findings = (new Validator(arazzoSchema()))->validate([
        'body' => issueBody(arazzoSchema(), bugSectionIds()),
        'labels' => ['bug', 'level:epic'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect(codes($findings))->toBe(['parent-missing']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=ValidatorTest`
Expected: FAIL — `missing-kind-label` fires on the package-epic, `missing-section` fires on the empty body, and an epic with no parent passes.

- [ ] **Step 3: Reorder `validate()` and thread the level through**

In `tools/issue-intake/src/Validator.php`, replace lines 37–51 with:

```php
        $parsed = SectionParser::parse($body);
        $findings = [];

        $level = $this->resolveLevel($labels, $parsed, $findings);
        $kind = $this->resolveKind($labels, $findings, !$this->kindForbidden($level));

        if ($kind !== null && !$this->sectionsExempt($level)) {
            $this->checkSections($kind, $parsed, $findings);
        }

        if ($level !== null) {
            $this->checkLevel($level, $parent, $parentLevel, $findings);
            $this->checkPackageLabel($level, $labels, $findings);
        }

        return $findings;
```

Add the two helpers:

```php
    /** A package-epic reports no work; it indexes it, so it carries no kind. */
    private function kindForbidden(?string $level): bool
    {
        return $level !== null && $this->schema->levelKindForbidden($level);
    }

    private function sectionsExempt(?string $level): bool
    {
        return $level !== null && $this->schema->levelSectionsExempt($level);
    }
```

- [ ] **Step 4: Make `resolveKind()` respect the flag**

Replace its signature and empty branch (lines 89–99) with:

```php
    private function resolveKind(array $labels, array &$findings, bool $required = true): ?string
    {
        $kinds = array_values(array_intersect($labels, $this->schema->kinds()));

        if ($kinds === []) {
            if ($required) {
                $findings[] = new Finding('missing-kind-label', sprintf(
                    'Add exactly one kind label: %s.', implode(', ', $this->schema->kinds()),
                ));
            }

            return null;
        }
```

Leave the `count($kinds) > 1` branch untouched — two kinds is always wrong.

- [ ] **Step 5: Add the package-label invariant**

Append after `checkLevel()`:

```php
    /**
     * A package-epic names one primary package — the package it is *mostly*
     * about (spec D14). This is the one invariant the level adds beyond the
     * parent chain.
     *
     * @param  list<string>  $labels
     * @param  list<Finding>  $findings
     */
    private function checkPackageLabel(string $level, array $labels, array &$findings): void
    {
        if (!$this->schema->levelRequiresPackageLabel($level)) {
            return;
        }

        $packages = array_values(array_filter(
            $labels,
            static fn (string $label): bool => str_starts_with($label, 'package:'),
        ));

        if ($packages === []) {
            $findings[] = new Finding('missing-package-label', sprintf(
                'A %s issue must name exactly one primary package (add a package:* label).', $level,
            ));

            return;
        }

        if (count($packages) > 1) {
            $findings[] = new Finding('multiple-package-labels', sprintf(
                '%s carries several package labels (%s); keep the package it is mostly about.',
                $level, implode(', ', $packages),
            ));
        }
    }
```

- [ ] **Step 6: Update `classify()` to resolve the level first**

Replace the body of `classify()` (lines 72–79) with:

```php
        $labels = self::strings($input['labels'] ?? []);
        $parsed = SectionParser::parse($input['body'] ?? '');
        $ignored = [];
        $level = $this->resolveLevel($labels, $parsed, $ignored);

        return [
            'kind' => $this->resolveKind($labels, $ignored, !$this->kindForbidden($level)),
            'level' => $level,
        ];
```

- [ ] **Step 7: Re-home the fixtures that paired `level:epic` with no parent**

Only `level:package-epic` may stand without a parent. Every other level needs one.

**`tools/issue-intake/tests/GhShimTest.php`** — the stub answers one label name from `GATE_STUB_PARENT_LABEL` (line 26). Replace that with a whole-JSON env var so a two-label package-epic is expressible:

```bash
        if [ "$1 $2" = "issue view" ]; then
            printf '%s\n' "${GATE_STUB_LABELS:-[{\"name\":\"level:package-epic\"},{\"name\":\"package:core\"}]}"
            exit 0
        fi
```

Then in `runShim`, pass `GATE_STUB_LABELS` instead of `GATE_STUB_PARENT_LABEL`, and set the story case (the test at line 134) to `'GATE_STUB_LABELS' => '[{"name":"chore"}]'`. The cases that create with `--label bug --label level:epic` and no `--parent` (lines 87, 99, 122) become `--label bug --label level:package-epic --label package:core`, which the stub's new default already supplies on the edit path. Leave the `gh api` fixture at line 147 on `level:epic` — it asserts a *refusal*, and `parent-missing` still produces one.

**`tools/issue-intake/tests/IssueIntakeGateTest.php`** — the three conforming fixtures (lines 110, 126, 143) carry `['name' => 'level:epic']`. Change each to `[['name' => 'bug'], ['name' => 'level:package-epic'], ['name' => 'package:core']]` (keeping `needs-info` where it is already present).

**`tools/issue-intake/tests/ValidateIssueCliTest.php`** — adopt the same swap wherever a conforming `level:epic` payload has no parent (lines 20, 38, and the classify fixture at 49).

**`tools/issue-intake/tests/FileIssueTest.php`** — replace the case `it('refuses an epic that has a parent', ...)` (line 116) with:

```php
it('refuses a package-epic that has a parent', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--level=level:package-epic', '--package=core', '--parent=25', '--title=x',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(1)
        ->and(file_get_contents($log))->not->toContain('issue create')
        ->and($process->getErrorOutput())->toContain('parent-forbidden');
});
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `composer run test-tools`
Expected: PASS except `FormGeneratorTest` (Task 3) and the `file-issue` package mode (Task 5).

- [ ] **Step 9: Commit**

```bash
git add tools/issue-intake/src/Validator.php tools/issue-intake/tests
git commit -m "feat(issue-intake): exempt package-epics from kind and sections, require one package label"
```

---

### Task 3: Forms — offer three levels, regenerate the templates

**Files:**
- Modify: `tools/issue-intake/src/FormGenerator.php` (lines 98–100)
- Modify: `.github/ISSUE_TEMPLATE/{bug,feature,chore}.yml` (regenerated)
- Test: `tools/issue-intake/tests/FormGeneratorTest.php`

**Interfaces:**
- Consumes: `Schema::formLevels()` (Task 1).

- [ ] **Step 1: Write the failing test**

In `tools/issue-intake/tests/FormGeneratorTest.php`, replace the options assertion (line 23) with:

```php
        ->and($level['attributes']['options'])->toBe(['level:epic', 'level:user-story', 'level:sub-task'])
```

and append:

```php
it('never offers a package-epic in the web form', function () {
    $options = formFields((new FormGenerator(arazzoSchema()))->forKind('bug'), 'dropdown')[0]['attributes']['options'];

    expect($options)->not->toContain('level:package-epic');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=FormGeneratorTest`
Expected: FAIL — the options are `['level:package-epic', 'level:epic', 'level:user-story', 'level:sub-task']`.

- [ ] **Step 3: Point the dropdown at `formLevels()` and refresh the copy**

In `tools/issue-intake/src/FormGenerator.php`, replace lines 98–100:

```php
                'description' => 'Where this sits in the hierarchy: Epic → User-story → Sub-task.',
                'options' => $this->schema->formLevels(),
```

- [ ] **Step 4: Regenerate the templates**

```bash
php tools/issue-intake/bin/generate-forms
git diff --stat .github/ISSUE_TEMPLATE
```

Expected: all three files change — each `level` dropdown loses `level:package-epic` and gains the renamed entries.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=FormGeneratorTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tools/issue-intake/src/FormGenerator.php tools/issue-intake/tests/FormGeneratorTest.php .github/ISSUE_TEMPLATE
git commit -m "feat(issue-intake): exclude package-epics from the web forms"
```

---

### Task 4: `PackageIndex` — render a package-epic body

**Files:**
- Create: `tools/issue-intake/src/PackageIndex.php`
- Create: `tools/issue-intake/bin/render-package-index`
- Test: `tools/issue-intake/tests/{PackageIndexTest,RenderPackageIndexCliTest}.php`

**Interfaces:**
- Produces: `PackageIndex::render(string $package, array $epics): string` (the marker-wrapped block, trailing newline); `PackageIndex::splice(string $currentBody, string $block): string`; `PackageIndex::stub(string $package): string` (equal to `render($package, [])`); constants `PackageIndex::START` and `PackageIndex::END`.
- Produces: `render-package-index` — stdin `{"package": string, "body": string, "epics": list<{number:int,title:string,state:string,stories:int}>}`, stdout `{"body": string, "changed": bool}`, exit `0` / `2`.
- **The epic shape `{number, title, state, stories}` is one contract**, shared by this task's PHP, this task's CLI, and Task 6's `jq`. `state` is `OPEN` or `CLOSED`; `stories` is the count of the epic's own children, defaulting to `0`.

**The rendered format, exactly** (an empty package, and a populated one):

```
<!-- package-index: generated -->
**Every epic under `sources`.** 0 open.

No epics yet. File one with `--level=level:epic --parent=<this issue>`.
<!-- /package-index -->
```

```
<!-- package-index: generated -->
**Every epic under `runner`.** 1 open.

| Epic | State | Stories |
| --- | --- | --- |
| [#87](87) CLI surface | open | 0 |
| [#118](118) Retry accounting | closed | 4 |
<!-- /package-index -->
```

Epic rows are sorted by `number` ascending; `state` is lower-cased; the count in the preamble counts `state === 'OPEN'`. `render()` output always ends with a single `\n`.

- [ ] **Step 1: Write the failing unit test**

Create `tools/issue-intake/tests/PackageIndexTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\PackageIndex;

it('renders the epics under a package, sorted by number', function () {
    $index = new PackageIndex;

    $body = $index->render('runner', [
        ['number' => 118, 'title' => 'Retry accounting', 'state' => 'CLOSED', 'stories' => 4],
        ['number' => 87, 'title' => 'CLI surface', 'state' => 'OPEN', 'stories' => 0],
    ]);

    expect($body)->toContain(PackageIndex::START)
        ->and($body)->toContain(PackageIndex::END)
        ->and($body)->toContain('**Every epic under `runner`.** 1 open.')
        ->and(strpos($body, '#87'))->toBeLessThan(strpos($body, '#118'))
        ->and($body)->toContain('| [#118](118) Retry accounting | closed | 4 |');
});

it('says so when a package has no epics yet', function () {
    $body = (new PackageIndex)->render('sources', []);

    expect($body)->toContain('**Every epic under `sources`.** 0 open.')
        ->and($body)->toContain('No epics yet')
        ->and($body)->not->toContain('| Epic |');
});

it('replaces only between the markers, preserving human text around them', function () {
    $index = new PackageIndex;

    $current = "Hand-written preamble.\n\n"
        .$index->render('runner', [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0]])
        ."\nHand-written epilogue.\n";

    $next = $index->splice($current, $index->render('runner', [
        ['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0],
        ['number' => 88, 'title' => 'Docs', 'state' => 'OPEN', 'stories' => 0],
    ]));

    expect($next)->toContain('Hand-written preamble.')
        ->and($next)->toContain('Hand-written epilogue.')
        ->and($next)->toContain('#88')
        ->and(substr_count($next, PackageIndex::START))->toBe(1);
});

it('adopts a body that has no markers yet', function () {
    $next = (new PackageIndex)->splice("An older hand-written body.\n", (new PackageIndex)->render('runner', []));

    expect($next)->toContain('An older hand-written body.')
        ->and($next)->toContain(PackageIndex::START);
});

it('is stable: splicing the same render twice changes nothing', function () {
    $index = new PackageIndex;
    $epics = [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0]];

    $once = $index->splice('', $index->render('runner', $epics));
    $twice = $index->splice($once, $index->render('runner', $epics));

    expect($twice)->toBe($once);
});

it('stubs a new package-epic with an empty index', function () {
    expect((new PackageIndex)->stub('runner'))->toBe((new PackageIndex)->render('runner', []));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=PackageIndexTest`
Expected: FAIL — `Class "Alama\Tools\IssueIntake\PackageIndex" not found`.

- [ ] **Step 3: Implement `PackageIndex`**

Create `tools/issue-intake/src/PackageIndex.php`. Give it the two marker constants, the three public methods and the private sort from the Interfaces block; render the format shown above. `splice()` replaces the span from `START` through `END` inclusive when both markers exist and `END` follows `START`; otherwise it appends the block to the current body, separated by a blank line (or with no prefix when the body is blank). Keep it pure — no network, no filesystem.

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=PackageIndexTest`
Expected: PASS.

- [ ] **Step 5: Write the failing CLI test**

Create `tools/issue-intake/tests/RenderPackageIndexCliTest.php`:

```php
<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/** @param array<string, mixed> $payload */
function runRenderIndex(array $payload): Process
{
    $process = new Process(['php', 'tools/issue-intake/bin/render-package-index']);
    $process->setInput((string) json_encode($payload));
    $process->run();

    return $process;
}

it('renders a body and reports it as changed', function () {
    $process = runRenderIndex([
        'package' => 'runner',
        'body' => '',
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    $decoded = json_decode($process->getOutput(), true);

    expect($process->getExitCode())->toBe(0)
        ->and($decoded['changed'])->toBeTrue()
        ->and($decoded['body'])->toContain('#87');
});

it('reports no change when the body already holds the render', function () {
    $once = runRenderIndex([
        'package' => 'runner',
        'body' => '',
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    $second = runRenderIndex([
        'package' => 'runner',
        'body' => json_decode($once->getOutput(), true)['body'],
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    expect(json_decode($second->getOutput(), true)['changed'])->toBeFalse();
});

it('exits 2 on a malformed payload', function () {
    $process = new Process(['php', 'tools/issue-intake/bin/render-package-index']);
    $process->setInput('not json');
    $process->run();

    expect($process->getExitCode())->toBe(2);
});
```

- [ ] **Step 6: Run the CLI test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=RenderPackageIndexCliTest`
Expected: FAIL — the bin does not exist.

- [ ] **Step 7: Implement the CLI**

Create `tools/issue-intake/bin/render-package-index` (`chmod +x`), as `#!/usr/bin/env php`. It locates `vendor/autoload.php` three levels up (mirroring `bin/validate-issue`), decodes stdin, rejects anything that is not `{package: string, epics: list<{number:int,title:string,state:string,stories?:int}>}` with `exit 2` and a usage line on stderr, defaults `stories` to `0`, and prints `{"body": splice($current, render($package, $epics)), "changed": $body !== $current}` — where `$current` is `input.body` or `''`.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `vendor/bin/pest tools/issue-intake/tests --filter='PackageIndex|RenderPackageIndex'`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
chmod +x tools/issue-intake/bin/render-package-index
git add tools/issue-intake/src/PackageIndex.php tools/issue-intake/bin/render-package-index tools/issue-intake/tests/PackageIndexTest.php tools/issue-intake/tests/RenderPackageIndexCliTest.php
git commit -m "feat(issue-intake): render a package-epic body from its epics"
```

---

### Task 5: `file-issue` — the only way a package-epic is born

**Files:**
- Modify: `.agents/skills/file-issue/scripts/file-issue.php`
- Modify: `.agents/skills/file-issue/SKILL.md`
- Test: `tools/issue-intake/tests/FileIssueTest.php`

**Interfaces:**
- Consumes: `Schema::levelKindForbidden()`, `Schema::levelRequiresPackageLabel()`; `PackageIndex::stub()` (Task 4).
- Produces: a `--level=level:package-epic --package=<name>` mode — no `--kind`, no `--sections`, the labels `level:package-epic` then `package:<name>`.

- [ ] **Step 1: Write the failing test**

Append to `tools/issue-intake/tests/FileIssueTest.php`, using the file's existing `stubGh()` and `runFileIssue()` helpers:

```php
it('files a package-epic from the level and package alone', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue(
        ['--level=level:package-epic', '--package=runner', '--title=[Package] runner'],
        $gh,
        $log,
    );

    expect($process->getExitCode())->toBe(0);
    $calls = (string) file_get_contents($log);

    expect($calls)->toContain('issue create')
        ->and($calls)->toContain('--label level:package-epic --label package:runner')
        ->and($calls)->not->toContain('--label bug');
});

it('refuses a package-epic with no --package', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue(['--level=level:package-epic', '--title=x'], $gh, $log);

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('--package');
});

it('refuses a --kind on a package-epic', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue(
        ['--kind=chore', '--level=level:package-epic', '--package=runner', '--title=x'],
        $gh,
        $log,
    );

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('kind');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=FileIssueTest`
Expected: FAIL — `--kind` and `--sections` are still required and `--package` is an unknown argument.

- [ ] **Step 3: Implement the package mode**

In `.agents/skills/file-issue/scripts/file-issue.php`:

Replace the required-argument loop (lines 138–143) with only the unconditional ones:

```php
foreach (['level', 'title'] as $required) {
    if (($options[$required] ?? '') === '') {
        usage();
        fail("missing required --{$required}");
    }
}
```

After the level is validated (after line 164), add:

```php
$packageEpic = $schema->levelKindForbidden($level);
$package = $options['package'] ?? '';

if ($packageEpic) {
    if (($options['kind'] ?? '') !== '') {
        usage();
        fail('a package-epic carries no --kind; drop it');
    }

    if (preg_match('/^[a-z0-9-]+$/', $package) !== 1) {
        usage();
        fail('--package=<name> is required for a package-epic (lowercase, digits, hyphens)');
    }
} else {
    foreach (['kind', 'sections'] as $required) {
        if (($options[$required] ?? '') === '') {
            usage();
            fail("missing required --{$required}");
        }
    }
}
```

Guard the `$kind` validation (lines 156–158) with `if (!$packageEpic)`, and read the schema before it. Wrap the sections-reading block (lines 188–228) in `if (! $packageEpic) { … }`; when `$packageEpic`, set:

```php
$body = (new PackageIndex)->stub($package);
```

(`PackageIndex` is already reachable — add `use Alama\Tools\IssueIntake\PackageIndex;` to the imports.) Replace the label construction (lines 256–258) with:

```php
$labels = $packageEpic ? [$level, 'package:'.$package] : [$kind, $level];

$command = $edit === null
    ? [$gh, 'issue', 'create', '--title', $options['title'], '--body-file', $bodyFile, '--label', $labels[0], '--label', $labels[1]]
    : [$gh, 'issue', 'edit', (string) $edit, '--body-file', $bodyFile, '--add-label', $labels[0], '--add-label', $labels[1]];
```

Rewrite the `usage()` heredoc: mark `--kind=` and `--sections=` "unless `--level=level:package-epic`", add `--package=<name>`, and list all four levels.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=FileIssueTest`
Expected: PASS.

- [ ] **Step 5: Update the skill doc**

In `.agents/skills/file-issue/SKILL.md`: the argument table (`--kind` and `--sections` "yes, unless `--level=level:package-epic`"; add `--package=`), the Levels section (four levels; the chain; `level:package-epic` has no parent and exactly one `package:*`), and the example. State plainly that this script is the **only** way a package-epic is created.

- [ ] **Step 6: Run the whole tools suite**

Run: `composer run test-tools`
Expected: PASS — every suite green.

- [ ] **Step 7: Commit**

```bash
git add .agents/skills/file-issue tools/issue-intake/tests/FileIssueTest.php
git commit -m "feat(file-issue): add the kind-less package-epic mode"
```

---

### Task 6: The CI index writer

**Files:**
- Create: `.github/scripts/package-index.sh`
- Create: `.github/workflows/package-index.yml`
- Test: `tools/issue-intake/tests/PackageIndexWorkflowTest.php`

**Interfaces:**
- Consumes: `tools/issue-intake/bin/render-package-index` (Task 4).
- Environment: `GH_TOKEN`, `GITHUB_REPOSITORY`, `ISSUE_NUMBER` (empty ⇒ sweep), `EVENT_NAME`.

**What the script must do**, in order:

1. Resolve targets. `ISSUE_NUMBER` set: query that issue; if its `level:*` label is `level:package-epic`, that is the target; otherwise walk `parent { number }` upward (at most eight hops) until a `level:package-epic` is found. `ISSUE_NUMBER` empty (sweep): every open `level:package-epic`.
2. For each target, query it and its children:

```graphql
query($owner: String!, $repo: String!, $number: Int!) {
  repository(owner: $owner, name: $repo) {
    issue(number: $number) {
      number
      body
      labels(first: 50) { nodes { name } }
      subIssues(first: 100) {
        nodes { number title state subIssues { totalCount } }
      }
    }
  }
}
```

3. Build the CLI payload and render:

```bash
payload=$(jq -n \
    --arg package "$(jq -r '[.data.repository.issue.labels.nodes[].name | select(startswith("package:"))][0] // ""' <<<"$json")" \
    --arg body "$(jq -r '.data.repository.issue.body // ""' <<<"$json")" \
    --argjson epics "$(jq -c '[.data.repository.issue.subIssues.nodes[]? | {number, title, state, stories: (.subIssues.totalCount // 0)}]' <<<"$json")" \
    '{package: $package, body: $body, epics: $epics}')

rendered=$(php "$REPO_ROOT/tools/issue-intake/bin/render-package-index" <<<"$payload")
```

4. Write **only** when `jq -r '.changed' <<<"$rendered"` is `true`: write `.body` to a temp file and `gh issue edit "$number" --repo "$REPO" --body-file "$file"`. Otherwise print that the index is current and move on. This is the loop guard's first half; the second half is GitHub's own rule that events raised with the workflow's `GITHUB_TOKEN` do not start new runs.
5. Uniqueness: `gh issue list --label level:package-epic --state open --json number,labels`, group by the `package:*` label, and for any package with more than one open package-epic do exactly what `issue-intake-gate.sh` lines 79–115 do for the intake gate — `gh issue edit <n> --add-label needs-info` plus **one** comment carrying `<!-- package-index -->`, found-or-created by marker, and deleted once the collision is gone. Never close, never assign. A `package:*` label with no package-epic is **not** flagged.

- [ ] **Step 1: Write the failing workflow test**

Create `tools/issue-intake/tests/PackageIndexWorkflowTest.php`, modelled on `IssueIntakeGateTest::runIntakeGate()` — a stub `gh` on `PATH`, fixture JSON files, a call log, the real script under test. Give the harness this signature and stub contract:

```php
/**
 * @param  array{target?: string, parent?: string, epics?: string, list?: string, comments?: string}  $fixtures
 * @return array{0: Process, 1: string} [process, gh call log]
 */
function runPackageIndex(array $fixtures = [], array $env = []): array
```

The stub answers: `issue view` → `$GH_INDEX_TARGET`; `issue edit` → exit 0 (logged); `issue list` → `$GH_INDEX_LIST`; `api` with `graphql` → `$GH_INDEX_PARENT` when the query asks for `parent`, else `$GH_INDEX_EPICS`; `api` otherwise → `$GH_INDEX_COMMENTS`. Default env: `GITHUB_REPOSITORY=o/r`, `ISSUE_NUMBER=5`, `EVENT_NAME=issues`, `PATH` prefixed with the stub dir.

Implements the stub branches with a `case` on the query text for `graphql` — the script's own queries are the only signals available, so key on a substring:

```bash
        if [ "$1" = "api" ]; then
            case "$3" in
                *parent*) cat "$GH_INDEX_PARENT" ;;
                *)        cat "$GH_INDEX_EPICS" ;;
            esac
            exit 0
        fi
```

Then the three cases:

```php
it('writes the body when the render differs', function () {
    [$process, $log] = runPackageIndex([
        'target' => '{"data":{"repository":{"issue":{"number":5,"body":"stale","labels":{"nodes":[{"name":"level:package-epic"},{"name":"package:runner"}]},"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($log)->toContain('issue edit 5')
        ->and($log)->toContain('--body-file');
});

it('writes nothing when the render is unchanged', function () {
    // The exact body render-package-index produces for this one epic (Task 4).
    $body = "<!-- package-index: generated -->\n"
        ."**Every epic under `runner`.** 1 open.\n\n"
        ."| Epic | State | Stories |\n| --- | --- | --- |\n| [#87](87) CLI | open | 2 |\n"
        ."<!-- /package-index -->\n";

    [$process, $log] = runPackageIndex([
        'target' => '{"data":{"repository":{"issue":{"number":5,"body":"'.addslashes($body).'","labels":{"nodes":[{"name":"level:package-epic"},{"name":"package:runner"}]},"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($log)->not->toContain('issue edit');
});

it('flags two open package-epics for one package, and never closes', function () {
    [$process, $log] = runPackageIndex(
        [
            'target' => '{"data":{"repository":{"issue":{"number":5,"body":"stale","labels":{"nodes":[{"name":"level:package-epic"},{"name":"package:runner"}]},"subIssues":{"nodes":[]}}}}}',
            'list' => '[{"number":5,"labels":[{"name":"package:runner"}]},{"number":6,"labels":[{"name":"package:runner"}]}]',
        ],
        ['SWEEP' => '1', 'ISSUE_NUMBER' => ''],
    );

    expect($process->getExitCode())->toBe(0)
        ->and($log)->toContain('--add-label needs-info')
        ->and($log)->toContain('package-index')
        ->and($log)->not->toContain('issue close');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=PackageIndexWorkflowTest`
Expected: FAIL — the script does not exist.

- [ ] **Step 3: Write `.github/scripts/package-index.sh`**

`#!/usr/bin/env bash`, `set -euo pipefail`, header comment citing spec §A7 / D15 / D16. Resolve `REPO_ROOT` from `$0` exactly as `issue-intake-gate.sh` does. Implement the five behaviours above. Reuse the gate's marker-comment block verbatim in shape; only the marker string differs (`<!-- package-index -->`). Print a one-line summary per target (`package-epic #N: index refreshed.` / `… already current.`). `chmod +x`.

- [ ] **Step 4: Write `.github/workflows/package-index.yml`**

```yaml
name: Package index

# The living index (spec A7). Separate from the intake gate because it reads
# children and writes a body, where the gate reads a body and writes a label.
# The sweep is not optional: the `issues` event set does not reliably cover
# parent-link changes, so the schedule is how the index stays true. The
# concurrency group is prefixed to stay clear of issue-intake's.

on:
  issues:
    types: [opened, edited, closed, reopened, deleted, transferred, labeled, unlabeled]
  workflow_dispatch:
    inputs:
      issue_number:
        description: Package-epic (or a child) to re-index; omit to sweep
        required: false
        type: number
  schedule:
    - cron: '17 4 * * *'

concurrency:
  group: package-index-${{ github.event.issue.number || inputs.issue_number || 'sweep' }}
  cancel-in-progress: true

permissions:
  contents: read
  issues: write

jobs:
  index:
    name: Regenerate the package index
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
          cache: composer

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Re-index
        env:
          GH_TOKEN: ${{ github.token }}
          GITHUB_REPOSITORY: ${{ github.repository }}
          EVENT_NAME: ${{ github.event_name }}
          ISSUE_NUMBER: ${{ github.event.issue.number || inputs.issue_number || '' }}
        run: bash .github/scripts/package-index.sh
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest tools/issue-intake/tests --filter=PackageIndexWorkflowTest`
Expected: PASS.

- [ ] **Step 6: Run the whole suite**

Run: `composer run test`
Expected: PASS (the baseline was 1393 passed, 3 skipped, 1 deprecated — the count will be higher now).

- [ ] **Step 7: Commit**

```bash
chmod +x .github/scripts/package-index.sh
git add .github/scripts/package-index.sh .github/workflows/package-index.yml tools/issue-intake/tests/PackageIndexWorkflowTest.php
git commit -m "feat(issue-intake): regenerate the package index from the hierarchy in CI"
```

> **Token note:** pushing `.github/workflows/**` needs the `workflow` OAuth scope. If the push is rejected, add `package-index.yml` through the GitHub web UI with the same content and commit the script alone.

---

### Task 7: The label set (live — needs your confirmation)

> **Run only after Task 6 is merged to `main`.**

- [ ] **Step 1: Create the new labels**

```bash
gh label create level:package-epic --description "Level 1 — one per package; body is a generated index" --color 5319e7
gh label create level:user-story  --description "Level 3 — child of an epic"                       --color 5319e7
gh label create level:sub-task    --description "Level 4 — child of a story"                       --color 5319e7
```

- [ ] **Step 2: Migrate the one issue carrying an old label**

```bash
gh issue edit 79 --add-label level:user-story --remove-label level:story
```

- [ ] **Step 3: Retire the old labels**

```bash
gh label delete level:story --yes
gh label delete level:subtask --yes
```

- [ ] **Step 4: Verify**

```bash
gh label list --limit 100 | grep '^level:'
gh issue view 79 --json labels --jq '[.labels[].name]'
```

Expected: four `level:*` labels; #79 carries `level:user-story` and no `level:story`.

---

### Task 8: Bootstrap the 15 package-epics (live — needs your confirmation)

- [ ] **Step 1: Create one package-epic per package**

```bash
for p in $(ls packages); do
  php .agents/skills/file-issue/scripts/file-issue.php \
      --level=level:package-epic --package="$p" --title="[Package] $p" || echo "FAILED: $p"
done
```

Expected: 15 new issues, each labelled `level:package-epic` + `package:<name>`, each body the empty index stub; no `FAILED` lines.

- [ ] **Step 2: Verify the set is complete and unique**

```bash
gh issue list --label level:package-epic --state open --limit 100 --json number,labels \
  --jq '[.[] | {n: .number, p: ([.labels[].name | select(startswith("package:"))][0])}] | length'
```

Expected: `15`.

- [ ] **Step 3: Watch one index settle**

```bash
gh workflow run package-index.yml
gh run watch
gh issue view <a package-epic number> --json body --jq .body
```

Expected: the stub body, unchanged — the index of an empty package renders to itself, so no write is issued.

---

### Task 9: Migrate the epics that already exist (live — needs your confirmation)

Each existing `epic` now needs a `package-epic` above it. Where an issue carries several `package:*` labels it attaches to the **first** listed one — that is the "mostly about" judgement D14 asks for.

- [ ] **Step 1: Print the mapping for review before changing anything**

```bash
for n in 87 88 89 90 118; do
  printf '#%s  %s  %s\n' "$n" \
    "$(gh issue view "$n" --json title --jq .title)" \
    "$(gh issue view "$n" --json labels --jq '[.labels[].name | select(startswith("package:"))] | join(",")')"
done
```

- [ ] **Step 2: Attach each epic, and give #90 its missing `level:epic`**

```bash
# Replace each <parent> with the package-epic number for that package.
gh issue edit 87 --parent <package-epic for #87's first package label>
gh issue edit 88 --parent <package-epic for #88's first package label>
gh issue edit 89 --parent <package-epic for #89's first package label>
gh issue edit 90 --add-label level:epic --parent <package-epic-core>
gh issue edit 118 --add-label package:<the package its validator work lives in> \
                  --parent <that package's package-epic>
```

- [ ] **Step 3: Verify an epic now conforms**

```bash
gh workflow run issue-intake.yml -f issue_number=118
gh run watch
gh issue view 118 --json labels --jq '[.labels[].name]'
gh issue view 118 --json parent --jq .parent
```

Expected: no `needs-info`; a `level:epic` label; a parent set to a package-epic.

---

### Task 10: Docs

**Files:**
- Modify: `docs/issues/triage-kinds.md`

- [ ] **Step 1: Replace the level table**

In `docs/issues/triage-kinds.md`, replace lines 20–22 (and the sentence introducing them) with:

```markdown
| Label                | Means              | Invariant                                                         |
| -------------------- | ------------------ | ----------------------------------------------------------------- |
| `level:package-epic` | One per package    | no parent; exactly one `package:*` label; body is generated       |
| `level:epic`         | Spans user-stories | must sit under a `level:package-epic`                             |
| `level:user-story`   | Child of an epic   | must sit under a `level:epic`                                     |
| `level:sub-task`     | Child of a story   | must sit under a `level:user-story`; can never itself be a parent |
```

- [ ] **Step 2: Note the generated index**

Add beneath the table:

```markdown
A `package-epic`'s body is **generated**: `.github/workflows/package-index.yml`
rewrites it from the epics beneath it on every `issues` event, plus a nightly
sweep. Edit a child, never the index — and text written outside the
`<!-- package-index: generated -->` markers is preserved.
```

- [ ] **Step 3: Commit**

```bash
git add docs/issues/triage-kinds.md
git commit -m "docs(issue-intake): document the four-level hierarchy and the generated package index"
```

---

## Self-Review

**1. Spec coverage.** §A1 levels → T1; the kind/sections/package invariants → T2; §A1's cross-issue uniqueness → T6 step 5; §A2 forms → T3; §A6 paved path → T5; §A7 renderer → T4, workflow/sweep/loop guard → T6; §A5 labels → T7; D14 primary package → T2, T9; D15 generated body → T4; D16 → T6; D17 mandatory chain → T1, T2, T9; D18 renames → T1–T3, T5, T7; D19 bootstrap → T8; §A4 grandfathering → unchanged, since the gate already keys on the `level:` prefix (T1 step 6 proves it).

**2. Step scan.** No step says "handle edge cases" or "write tests for the above". Task 4 carries the rendered format because the tests deliberately assert *containment*, not layout — the layout is a decision the implementer cannot read off the tests. Task 6's five numbered behaviours are the decisions; its shell structure is left to the implementer, except where it reads another script's block by name.

**3. Type consistency.** `PackageIndex::{render,splice,stub}`, `Schema::{level,formLevels,levelKindForbidden,levelSectionsExempt,levelRequiresPackageLabel}`, the CLI's `{body,changed}`, and the epic shape `{number,title,state,stories}` are each introduced once and used with the same spelling afterwards — including in the `jq` of Task 6 and the fixtures of Task 6's test.

**4. Review Focus.** Items 1–2 → T2 step 1; 3–5 → T4 step 1 (5 also → T6 step 1); 6 → T2 step 1; 7 → T3 step 1; 8 → T1 step 6 and T2.

**5. Proportion.** Every code block is either data (schema, workflow, GraphQL), a test the plan must pin exactly, or logic whose shape the tests do not determine (the validator's new branches, `file-issue`'s mode switch). `PackageIndex`'s body and `package-index.sh`'s structure are described, not transcribed.

**6. Ordering.** T1–T6 are code and must merge before T7–T9 touch GitHub, because the CI gate validates with the code on `main`. T10 is documentation and can land with the code.
