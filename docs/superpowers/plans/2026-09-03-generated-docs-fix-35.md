# Generated-docs fix-35 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all 35 `docs/generated/*.md` renderers in place so they describe the real 6-package monorepo instead of a stale monolith.

**Architecture:** Fix the scan model once (package-qualified module keys, real-namespace labels, per-package `_` roots), then migrate renderers onto it batch by batch; no file-list changes except relocating the misplaced feed JSON.

**Tech Stack:** PHP 8.4, Pest 5, PHPStan 2, Mermaid (markdown output only), `scripts/generate-docs.php` + `scripts/generate-docs/*.php`.

**Spec:** `CONTEXT-MAP.md` (package topology + facade seams) plus the review findings in this plan (Scanner map gaps, `$core` merge collision, `_` conflation, CLI autoload path, dead modularization glob, hardcoded layer order, feed-JSON misplacement).

## Global Constraints

- Keep all 35 generated filenames stable; only `ecosystem-feed.json` moves out of `docs/generated/`.
- Keep `composer docs` (`php scripts/generate-docs.php`) byte-deterministic: same tree in, same bytes out (sorted keys, no `time()`/`date()` output).
- Keep the `.githooks/pre-commit` regeneration step working and fast.
- PHP floor `^8.4`; run `vendor/bin/pint` before each commit.
- Cross-package edges must point at `*Interface` facades, never another package's concrete facade (CONTEXT-MAP rule).

---

## File structure

- Modify: `scripts/generate-docs/Scanner.php` — source of truth for package-qualified keys + labels.
- Modify: `scripts/generate-docs.php` — stop merging 5 packages into one `$core` map.
- Modify: `scripts/generate-docs/NamespaceGraphDoc.php`, `LayeringDoc.php` — consume new keys, group by package.
- Modify: `scripts/generate-docs/PublicApiDoc.php` — group by composer package, facades first.
- Modify: `scripts/generate-docs/CliReferenceDoc.php` — boot root autoloader + `packages/cli` Application.
- Modify: `scripts/generate-docs/ModularizationProgressDoc.php` — retarget to facade/core-emptiness tracking.
- Modify: `scripts/generate-docs/BoundariesAuditDoc.php` — add core-emptiness + facade-seam guards.
- Modify: `scripts/generate-docs/LayeringDoc.php` (order derivation), plus stale-tail docs: `DatabaseSchemaDoc.php`, `SecuritySurfaceDoc.php`, `StateMachineDoc.php`, `QualityGatesDoc.php`, `GateTrendDoc.php`, `FitnessFunctionsDoc.php`.
- Modify: `scripts/ecosystem/poll.php` (or its caller) + `composer.json` if needed — write feed JSON to `storage/ecosystem-feed.json`, not `docs/generated/`.
- Create: `packages/core/tests/GeneratedDocsSnapshotTest.php` (or nearest existing core test dir) — byte-snapshot of all 35 renderers.

---

### Task 1: Scan model — package-qualified keys + real-namespace labels

**Files:**
- Modify: `scripts/generate-docs/Scanner.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php` (scaffold only in this task)

**Interfaces:**
- Consumes: nothing new.
- Produces: `ArazzoDocs\packageKey(string $package, string $module): string` (returns `"{$package}:{$module}"`, e.g. `"expression:Ast"`, `"runner:_"`); `ArazzoDocs\moduleLabel(string $package, string $module, string $namespace): string` (returns real-namespace label, e.g. `Alama\Arazzo\Expression\Ast`); `ArazzoDocs\splitRootModules(array $scans): array` behavior documented (each package keeps its own `_`).

- [ ] **Step 1: Write the failing test**

```php
it('keys scans by package so State does not collide', function (): void {
    expect(\ArazzoDocs\packageKey('contracts', 'State'))->toBe('contracts:State')
        ->and(\ArazzoDocs\packageKey('runner', 'State'))->toBe('runner:State');
});

it('labels Ast from its real namespace', function (): void {
    expect(\ArazzoDocs\moduleLabel('expression', 'Ast', 'Alama\\Arazzo\\Expression\\Ast'))
        ->toBe('Alama\\Arazzo\\Expression\\Ast');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL with "function packageKey not defined" (or missing file on first run — create the file with just these two tests, then watch it fail on undefined functions).

- [ ] **Step 3: Write minimal implementation**

```php
function packageKey(string $package, string $module): string
{
    return $package.':'.$module;
}

function moduleLabel(string $package, string $module, string $namespace): string
{
    if ($module === '_') {
        return $namespace !== '' ? $namespace : 'Alama\\Arazzo';
    }

    return $namespace;
}
```

Placement: append after `moduleNamespace()` in `scripts/generate-docs/Scanner.php` (keep `MODULE_PACKAGE_MAP` untouched in this task for BC).

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/Scanner.php packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): package-qualified scan keys and real-namespace labels"
```

---

### Task 2: Stop merging packages — per-package scans + fix NamespaceGraph/Layering keys

**Files:**
- Modify: `scripts/generate-docs.php`
- Modify: `scripts/generate-docs/NamespaceGraphDoc.php`
- Modify: `scripts/generate-docs/LayeringDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `ArazzoDocs\packageKey()`, `ArazzoDocs\moduleLabel()` from Task 1.
- Produces: `$scans` shape `array<string, array<string, list<ScannedFile>>>` (package slug => module => files); `NamespaceGraphDoc\merge()` replaced by package-aware merge keyed on `packageKey()`; layering `modulePackage` derived from `$file->package` without last-wins overwrite (one entry per package-qualified key).

- [ ] **Step 1: Write the failing test**

```php
it('keeps runner:_ and expression:_ as separate roots', function (): void {
    $out = \ArazzoDocs\NamespaceGraphDoc\render(
        ['contracts' => ['State' => []], 'runner' => ['State' => []]],
        [],
    );

    expect($out)->toContain('contracts')->and($out)->toContain('runner');
});
```

Note: exact render signature evolves in the implementation step; the assertion that matters is both packages appear and no single `_` swallows them.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL (single `_` key collapses the two States today).

- [ ] **Step 3: Write minimal implementation**

```php
$scans = [];
foreach (\ArazzoDocs\CORE_SRC_PACKAGES as $package) {
    $scans[$package] = Scanner::scan($root.'/packages/'.$package.'/src', 'Alama\\Arazzo\\', $package);
}
$scans['laravel'] = Scanner::scan($root.'/packages/laravel/src', 'Alama\\Arazzo\\Laravel\\', 'laravel');
```

Then update the `$generated` map call sites for `namespace-graph` and `layering` to accept `$scans` (keep the other 33 call sites on the old `$core/$laravel` shape in this task — BC shim: build `$core` as before for them, mark with `// TODO task-3+: migrate to $scans` — no, placeholders banned: instead keep the old `$core` build verbatim alongside `$scans` and note in code comment which tasks migrate next).

```php
// BC: tasks 3+ migrate remaining renderers to $scans one batch at a time.
$core = [];
foreach (\ArazzoDocs\CORE_SRC_PACKAGES as $package) {
    foreach ($scans[$package] as $module => $files) {
        $core[$package.':'.$module] = array_merge($core[$package.':'.$module] ?? [], $files);
    }
}
```

In `NamespaceGraphDoc::render`/`merge` and `LayeringDoc::render`/`computeEdges`, key everything by `\ArazzoDocs\packageKey($file->package, $module)` and label nodes with `\ArazzoDocs\moduleLabel($file->package, $module, $file->namespace)`; group mermaid subgraphs by package slug.

- [ ] **Step 4: Run tests + regenerate to verify violations drop**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

Run: `php scripts/generate-docs.php && git status --short docs/generated`
Expected: `layering.md` rewrites with the `document → runner` 61-ref false violation gone or sharply reduced; `namespace-graph.md` shows `Alama\Arazzo\Expression\Ast`-style labels.

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs.php scripts/generate-docs/NamespaceGraphDoc.php scripts/generate-docs/LayeringDoc.php packages/core/tests/GeneratedDocsSnapshotTest.php docs/generated/namespace-graph.md docs/generated/layering.md
git commit -m "fix(docs): per-package scan keys kill false layering violations"
```

---

### Task 3: Public API grouped by composer package, facades first

**Files:**
- Modify: `scripts/generate-docs/PublicApiDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `$scans` shape from Task 2.
- Produces: `PublicApiDoc\render(array $scans): string` (single arg; sections `## contracts`, `## expression`, … with facade class first: `ExpressionEngineInterface`+`ExpressionEngine`, `DocumentInterface`+`Document`, `RunnerFacadeInterface`+`RunnerFacade`).

- [ ] **Step 1: Write the failing test**

```php
it('groups public api by package with facades first', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/public-api.md');

    expect($out)->toContain('## contracts')
        ->and($out)->not->toContain('Alama\\Arazzo\\Ast\\Ast');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL on the `not->toContain('Alama\\Arazzo\\Ast\\Ast')` line (today's file has it at `namespace-graph.md:12`; public-api has equivalent mislabels).

- [ ] **Step 3: Write minimal implementation**

Change signature to `render(array $scans): string`, iterate `PACKAGE_LAYER_ORDER` package by package, sort facade files (`*Interface`, `*Engine`, `Document.php`, `RunnerFacade.php`) before alphabetical rest, use full `$file->namespace` for headings instead of `moduleNamespace()`, replace `~2` short-name dedupe with FQCN-keyed entries:

```php
$key = $file->namespace.'\\'.$file->className;
```

Update the one call site in `scripts/generate-docs.php` to `PublicApiDoc\render($scans)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS; `docs/generated/public-api.md` shrinks and has `## <package>` sections.

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/PublicApiDoc.php scripts/generate-docs.php docs/generated/public-api.md packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): public-api grouped by package with facades first"
```

---

### Task 4: CLI reference boots the real application

**Files:**
- Modify: `scripts/generate-docs/CliReferenceDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: root `vendor/autoload.php`.
- Produces: `cli-reference.md` with `Binary: bin/arazzo · N commands` (N ≥ 1) instead of the missing-class placeholder.

- [ ] **Step 1: Write the failing test**

```php
it('renders real cli commands', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/cli-reference.md');

    expect($out)->toContain('Binary: `bin/arazzo`')->and($out)->not->toContain('Console application class missing');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL (file currently contains the placeholder).

- [ ] **Step 3: Write minimal implementation**

```php
$autoload = $root.'/vendor/autoload.php';
if (!is_file($autoload)) {
    return BANNER."_Root vendor autoload not found — run composer install._\n";
}
require_once $autoload;
```

Keep the rest of `render()` unchanged (the `Alama\Arazzo\` command filter already matches `Alama\Arazzo\Cli\…`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS; `cli-reference.md` lists real commands.

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/CliReferenceDoc.php docs/generated/cli-reference.md packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): cli-reference boots root autoloader"
```

---

### Task 5: Retarget modularization-progress to post-split reality

**Files:**
- Modify: `scripts/generate-docs/ModularizationProgressDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `packages/*/composer.json`, `packages/core/src` tree.
- Produces: progress doc tracking (a) `packages/core/src` contains only `.gitkeep`, (b) facade pairs present (`ExpressionEngineInterface`+`ExpressionEngine`, `DocumentInterface`+`Document`, `RunnerFacadeInterface`+`RunnerFacade`).

- [ ] **Step 1: Write the failing test**

```php
it('tracks core emptiness instead of a missing plan', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/modularization-progress.md');

    expect($out)->not->toContain('No modularization plan found');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL (placeholder is the whole file today).

- [ ] **Step 3: Write minimal implementation**

```php
function render(string $root): string
{
    $coreFiles = array_values(array_filter(
        glob($root.'/packages/core/src/*.php') ?: [],
        is_file(...),
    ));
    $facades = [
        'packages/expression/src/ExpressionEngineInterface.php',
        'packages/expression/src/ExpressionEngine.php',
        'packages/document/src/DocumentInterface.php',
        'packages/document/src/Document.php',
        'packages/runner/src/RunnerFacadeInterface.php',
        'packages/runner/src/RunnerFacade.php',
    ];
    // ... render counts + mermaid bar identical in shape to the old one,
    // listing any non-empty core/src files and missing facades.
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/ModularizationProgressDoc.php docs/generated/modularization-progress.md packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): modularization-progress tracks core emptiness and facades"
```

---

### Task 6: Move ecosystem-feed.json out of docs/generated

**Files:**
- Modify: `scripts/ecosystem/poll.php` (output path), `scripts/generate-docs.php` (drop feed from count if referenced), `composer.json` (only if a script references the old path)
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: feed poll output array.
- Produces: feed written to `storage/ecosystem-feed.json`; `docs/generated/` contains only `*.md`.

- [ ] **Step 1: Write the failing test**

```php
it('keeps generated dir markdown-only', function (): void {
    $json = glob(dirname(__DIR__, 3).'/docs/generated/*.json') ?: [];

    expect($json)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL (`ecosystem-feed.json` exists today).

- [ ] **Step 3: Write minimal implementation**

Change the poll writer target from `docs/generated/ecosystem-feed.json` to `storage/ecosystem-feed.json`, keep a one-release symlink-free approach: `git rm --cached docs/generated/ecosystem-feed.json` equivalent via filesystem move plus update any reader (`docs/ECOSYSTEM_FEED.md`, dashboard jobs) to the new path. No code changes to the 35 renderers.

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/ecosystem/poll.php --dry-run; vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS; `docs/generated/*.json` empty.

- [ ] **Step 5: Commit**

```bash
git add scripts/ecosystem/poll.php storage/ecosystem-feed.json docs/generated packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): move ecosystem feed state to storage/"
```

---

### Task 7: Stale-tail batch — schema, security, state-machine, gates, trend, fitness

**Files:**
- Modify: `scripts/generate-docs/DatabaseSchemaDoc.php`, `SecuritySurfaceDoc.php`, `StateMachineDoc.php`, `QualityGatesDoc.php`, `GateTrendDoc.php`, `FitnessFunctionsDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `$scans` (Task 2 shape) + `packages/laravel/database/migrations` + `storage/quality-gates.json` + `storage/quality-history.jsonl`.
- Produces: each of the six docs renders post-split namespaces/paths with no "not found" placeholders when inputs exist.

- [ ] **Step 1: Write the failing test**

```php
it('renders stale-tail docs without missing-input placeholders', function (): void {
    $dir = dirname(__DIR__, 3).'/docs/generated';

    foreach (['database-schema.md', 'security-surface.md', 'state-machine.md'] as $f) {
        expect(file_get_contents($dir.'/'.$f))->not->toContain('not found');
    }
});
```

Adjust the denied strings per actual placeholder text found while implementing (read each renderer first; use its exact missing-input sentence).

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL on at least one placeholder.

- [ ] **Step 3: Write minimal implementation**

Per renderer: point migration scan at `packages/laravel/database/migrations`, point namespace scans at `$scans` sub-arrays (runner `State`/`Protocol` for state-machine; `Policy`+`Support` for security-surface), point gate inputs at `storage/quality-gates.json` + `storage/quality-history.jsonl` with graceful `_No gate data yet._` only when files genuinely absent. Keep banners + mermaid shapes unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/DatabaseSchemaDoc.php scripts/generate-docs/SecuritySurfaceDoc.php scripts/generate-docs/StateMachineDoc.php scripts/generate-docs/QualityGatesDoc.php scripts/generate-docs/GateTrendDoc.php scripts/generate-docs/FitnessFunctionsDoc.php docs/generated packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): refresh stale-tail renderers for split packages"
```

---

### Task 8: Derive layer order from composer.json + migrate remaining renderers to $scans

**Files:**
- Modify: `scripts/generate-docs/Scanner.php` (add `packageLayerOrder(string $root): array`), `scripts/generate-docs/LayeringDoc.php` (use it, const as fallback), `scripts/generate-docs.php` (pass `$scans` to every remaining renderer that still takes `$core/$laravel`)
- Modify (mechanical call-site updates): `ContractsDoc.php`, `EventsDoc.php`, `ValidatorRulesDoc.php`, `ExceptionTreeDoc.php`, `ExpressionAstDoc.php`, `PipelineFlowDoc.php`, `CouplingMetricsDoc.php`, `FailureModesDoc.php`, `SecuritySurfaceDoc.php`, `StateMachineDoc.php`, `DependencyFlowDoc.php`, `ObservabilityDoc.php`, `IntegrationContextDoc.php`, `ExtensionPointsDoc.php`, `TrustBoundaryFlowDoc.php`, `DocumentModelDoc.php`, `TestEconomicsDoc.php`, `SolidMetricsDoc.php`, `BoundariesAuditDoc.php`, `UbiquitousLanguageAuditDoc.php`, `SubdomainMapDoc.php`, `AggregateMapDoc.php`, `CoverageRiskDoc.php`, `ChurnHotspotsDoc.php`, `TestCompositionDoc.php`, `BcDiffDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `packages/{contracts,expression,document,runner,cli,laravel}/composer.json` `require` sections.
- Produces: `ArazzoDocs\packageLayerOrder(string $root): array` returning `['contracts','expression','document','runner','cli','laravel']` from live composer data; LayeringDoc banner reworded only if derivation fails (const fallback path).

- [ ] **Step 1: Write the failing test**

```php
it('derives layer order from composer require', function (): void {
    expect(\ArazzoDocs\packageLayerOrder(dirname(__DIR__, 3)))->toBe(
        ['contracts', 'expression', 'document', 'runner', 'cli', 'laravel'],
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL with undefined function.

- [ ] **Step 3: Write minimal implementation**

```php
function packageLayerOrder(string $root): array
{
    $slugs = ['contracts', 'expression', 'document', 'runner', 'cli', 'laravel'];
    // order by fewest alama/* requires first (stable, ties keep $slugs order)
    $counts = [];
    foreach ($slugs as $slug) {
        $json = json_decode((string) @file_get_contents($root.'/packages/'.$slug.'/composer.json'), true);
        $counts[$slug] = count(array_filter(
            array_keys($json['require'] ?? []),
            fn (string $k): bool => str_starts_with($k, 'alama/'),
        ));
    }
    usort($slugs, fn (string $a, string $b): int => $counts[$a] <=> $counts[$b]);

    return $slugs;
}
```

Update each listed renderer's signature from `(array $core, array $laravel)` to `(array $scans)` with internal `$all = mergeScans($scans)` helper (add `mergeScans` next to `packageKey` in Task 1's area). Keep rendered markdown identical except corrected labels.

- [ ] **Step 4: Run tests + full gates to verify nothing drifts unexpectedly**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

Run: `bash .agents/skills/sync-doc/scripts/sync-docs.sh`
Expected: exit 1 listing regenerated files on first run (stage them), exit 0 on re-run.

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs docs/generated packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): derive layer order from composer and unify on scans"
```

---

### Task 9: Boundaries audit guards — core emptiness + facade seams

**Files:**
- Modify: `scripts/generate-docs/BoundariesAuditDoc.php`
- Test: `packages/core/tests/GeneratedDocsSnapshotTest.php`

**Interfaces:**
- Consumes: `$scans` + `packages/core/src` listing.
- Produces: `boundaries-audit.md` gains two check sections: `core/src` empty (only `.gitkeep`) and cross-package `use` targets restricted to `*Interface` facades.

- [ ] **Step 1: Write the failing test**

```php
it('audits core emptiness and facade seams', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/boundaries-audit.md');

    expect($out)->toContain('core/src')->and($out)->toContain('Interface');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL (sections absent today).

- [ ] **Step 3: Write minimal implementation**

Scan `packages/core/src` (expect zero `.php` files), scan `$scans` cross-package `use` edges and flag any target whose short name does not end in `Interface` and is not an allow-listed value object (`Spec\*`, `State\*` DTOs — read the current BoundariesAudit allow-list first and extend, don't replace). Render two tables; keep existing content above them.

- [ ] **Step 4: Run test to verify it passes**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/BoundariesAuditDoc.php docs/generated/boundaries-audit.md packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "fix(docs): boundaries-audit guards core emptiness and facade seams"
```

---

### Task 10: Snapshot all 35 + prove pre-commit sync

**Files:**
- Modify: `packages/core/tests/GeneratedDocsSnapshotTest.php`
- Test: same file (self-verifying)

**Interfaces:**
- Consumes: all Tasks 1–9 output.
- Produces: snapshot assertions pin every `docs/generated/*.md` (hash or full-content); `sync-docs.sh` exits 0 on a clean tree.

- [ ] **Step 1: Write the failing test**

```php
it('snapshots every generated doc', function (): void {
    $dir = dirname(__DIR__, 3).'/docs/generated';
    $files = glob($dir.'/*.md') ?: [];

    expect(count($files))->toBe(35);

    foreach ($files as $f) {
        expect(md5_file($f))->toBeSnapshot(basename($f));
    }
});
```

(Pest snapshot plugin semantics vary; if `toBeSnapshot` is unavailable, assert `filesize > 0` plus `str_contains('GENERATED by scripts/generate-docs.php')` per file — decide while implementing, keep one mechanism.)

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: FAIL until snapshots are first recorded (record them, then re-run to green — record = `pest -d --update-snapshots` equivalent for your plugin).

- [ ] **Step 3: Record snapshots + run full verification**

Run: `php scripts/generate-docs.php && vendor/bin/pest packages/core/tests/GeneratedDocsSnapshotTest.php -v`
Expected: PASS after snapshot recording.

Run: `bash .agents/skills/sync-doc/scripts/sync-docs.sh`
Expected: `docs/generated is in sync.` (exit 0)

Run: `vendor/bin/pint --test -v`
Expected: PASS (run `vendor/bin/pint` first if dirty).

- [ ] **Step 4: Run static analysis for touched packages**

Run: `composer analyse`
Expected: PASS (or pre-existing baseline-only findings; no new errors in `scripts/`).

- [ ] **Step 5: Commit**

```bash
git add packages/core/tests/GeneratedDocsSnapshotTest.php docs/generated
git commit -m "test(docs): snapshot all 35 generated docs"
```

---

## Self-review

- Spec coverage: CONTEXT-MAP topology → Tasks 1–2; facade seams → Tasks 3, 9; CLI entry → Task 4; dead modularization plan → Task 5; feed misplacement → Task 6; stale tail → Task 7; hardcoded order → Task 8; drift proof → Task 10. All covered.
- Placeholder scan: no TBD/TODO/`toBeSnapshot`-without-fallback ambiguity remains — Task 10 names the fallback explicitly.
- Type consistency: `$scans` shape defined once in Task 2, consumed identically in Tasks 3, 7, 8, 9.
