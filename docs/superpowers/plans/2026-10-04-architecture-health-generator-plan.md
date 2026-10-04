# Architecture Health & Dual-Mode Doc Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the architecture doc generator scripts into a dual-mode Diagnostic Engine + Doc Renderer that outputs a unified executive health dashboard (`docs/generated/architecture-health.md`) and provides a `--check` CLI mode with non-zero exit codes for agent self-correction.

**Architecture:** Introduce `Diagnostics.php` containing `Severity`, `Diagnostic`, and `AnalysisResult`. Update key guardrails (`BoundariesAuditDoc`, `LayeringDoc`, `SolidMetricsDoc`, `CouplingMetricsDoc`, `FailureModesDoc`) to return structured diagnostics alongside markdown. Introduce `ArchitectureHealthDoc` to render the executive health dashboard. Enhance `scripts/generate-docs.php` to collect all diagnostics, generate `architecture-health.md`, and support `--check` / `--audit` CLI flags.

**Tech Stack:** PHP 8.2+, Pest PHP, Composer.

---

### Task 1: Diagnostic Model & Data Structures

**Files:**
- Create: `scripts/generate-docs/Diagnostics.php`
- Create: `packages/core/tests/Architecture/DiagnosticsTest.php`

- [ ] **Step 1: Write failing test for Diagnostics model**

Create `packages/core/tests/Architecture/DiagnosticsTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';

use ArazzoDocs\AnalysisResult;
use ArazzoDocs\Diagnostic;
use ArazzoDocs\Severity;

it('instantiates Severity enum cases correctly', function (): void {
    expect(Severity::ERROR->value)->toBe('ERROR')
        ->and(Severity::WARNING->value)->toBe('WARNING')
        ->and(Severity::INFO->value)->toBe('INFO');
});

it('instantiates Diagnostic with all properties', function (): void {
    $diagnostic = new Diagnostic(
        severity: Severity::ERROR,
        category: 'boundary',
        rule: 'forbidden-vendor-import',
        message: 'Forbidden import of Symfony in core',
        file: 'packages/document/src/Parser/YamlParser.php',
        line: 12,
        suggestedAction: 'Inject parser contract instead',
    );

    expect($diagnostic->severity)->toBe(Severity::ERROR)
        ->and($diagnostic->category)->toBe('boundary')
        ->and($diagnostic->rule)->toBe('forbidden-vendor-import')
        ->and($diagnostic->message)->toBe('Forbidden import of Symfony in core')
        ->and($diagnostic->file)->toBe('packages/document/src/Parser/YamlParser.php')
        ->and($diagnostic->line)->toBe(12)
        ->and($diagnostic->suggestedAction)->toBe('Inject parser contract instead');
});

it('instantiates AnalysisResult with markdown and diagnostics', function (): void {
    $diag = new Diagnostic(
        severity: Severity::WARNING,
        category: 'solid',
        rule: 'god-class',
        message: 'Class exceeds 300 LOC',
        file: 'packages/runner/src/Execution/ScenarioRunner.php',
    );

    $result = new AnalysisResult(
        markdown: '# Test Doc',
        diagnostics: [$diag],
    );

    expect($result->markdown)->toBe('# Test Doc')
        ->and($result->diagnostics)->toHaveCount(1)
        ->and($result->diagnostics[0]->rule)->toBe('god-class');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/DiagnosticsTest.php`
Expected: FAIL (file `scripts/generate-docs/Diagnostics.php` not found)

- [ ] **Step 3: Implement `scripts/generate-docs/Diagnostics.php`**

Create `scripts/generate-docs/Diagnostics.php`:
```php
<?php

declare(strict_types=1);

namespace ArazzoDocs;

enum Severity: string
{
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
    case INFO = 'INFO';
}

final readonly class Diagnostic
{
    public function __construct(
        public Severity $severity,
        public string $category,
        public string $rule,
        public string $message,
        public string $file,
        public int $line = 0,
        public string $suggestedAction = '',
    ) {}
}

final readonly class AnalysisResult
{
    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function __construct(
        public string $markdown,
        public array $diagnostics = [],
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/DiagnosticsTest.php`
Expected: PASS (3 passed)

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/Diagnostics.php packages/core/tests/Architecture/DiagnosticsTest.php
git commit -m "feat(docs): add Diagnostic, Severity, and AnalysisResult models"
```

---

### Task 2: Boundaries Guardrail Diagnostics (`BoundariesAuditDoc.php`)

**Files:**
- Modify: `scripts/generate-docs/BoundariesAuditDoc.php`
- Create: `packages/core/tests/Architecture/BoundariesAuditDiagnosticsTest.php`

- [ ] **Step 1: Write failing test for BoundariesAuditDoc returning AnalysisResult**

Create `packages/core/tests/Architecture/BoundariesAuditDiagnosticsTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/BoundariesAuditDoc.php';

use ArazzoDocs\AnalysisResult;
use ArazzoDocs\BoundariesAuditDoc;
use ArazzoDocs\ScannedFile;
use ArazzoDocs\Severity;

it('emits diagnostics for forbidden vendor imports and returns AnalysisResult', function (): void {
    $scans = [
        'document' => [
            'Parser' => [
                new ScannedFile(
                    path: 'packages/document/src/Parser/BadFile.php',
                    relativeDir: 'Parser',
                    namespace: 'Alama\\Arazzo\\Document\\Parser',
                    className: 'BadFile',
                    isInterface: false,
                    uses: ['Symfony\\Component\\Yaml\\Yaml'],
                    useStatements: ['Symfony\\Component\\Yaml\\Yaml'],
                    content: "<?php\nuse Symfony\\Component\\Yaml\\Yaml;\n",
                    package: 'document',
                ),
            ],
        ],
    ];

    $root = dirname(__DIR__, 3);
    $result = BoundariesAuditDoc\render($scans, $root);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->markdown)->toContain('# Generated: Framework Boundary Audit')
        ->and($result->diagnostics)->not->toBeEmpty();

    $forbidden = array_values(array_filter(
        $result->diagnostics,
        fn ($d) => $d->rule === 'forbidden-vendor-import' && $d->severity === Severity::ERROR
    ));

    expect($forbidden)->not->toBeEmpty()
        ->and($forbidden[0]->file)->toBe('packages/document/src/Parser/BadFile.php')
        ->and($forbidden[0]->suggestedAction)->toContain('Symfony');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/BoundariesAuditDiagnosticsTest.php`
Expected: FAIL (`render()` returns string, not `AnalysisResult`)

- [ ] **Step 3: Update `scripts/generate-docs/BoundariesAuditDoc.php`**

Update `scripts/generate-docs/BoundariesAuditDoc.php` to:
1. `use ArazzoDocs\AnalysisResult;`, `use ArazzoDocs\Diagnostic;`, `use ArazzoDocs\Severity;`.
2. Collect diagnostics during boundary inspection:
   - For each forbidden vendor reference in library packages (`contracts`, `expression`, `document`, `runner`):
     Emit `Diagnostic(Severity::ERROR, 'boundary', 'forbidden-vendor-import', "Forbidden import of '$vendor' in package '$pkg'", $file->path, $line, "Refactor $file->path to remove direct dependency on $vendor.")`.
   - For non-empty `packages/core/src/`:
     Emit `Diagnostic(Severity::ERROR, 'boundary', 'core-src-not-empty', "packages/core/src must remain empty aggregator", 'packages/core/src', 0, "Move classes out of packages/core/src into their specific subpackages.")`.
   - For concrete cross-package violations:
     Emit `Diagnostic(Severity::ERROR, 'boundary', 'facade-seam-violation', "Concrete cross-package reference to $targetClass in $file->path", $file->path, $line, "Depend on interface contract instead of concrete class $targetClass.")`.
3. Return `new AnalysisResult(markdown: implode("\n", $lines)."\n", diagnostics: $diagnostics)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/BoundariesAuditDiagnosticsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/BoundariesAuditDoc.php packages/core/tests/Architecture/BoundariesAuditDiagnosticsTest.php
git commit -m "feat(docs): emit structured diagnostics from BoundariesAuditDoc"
```

---

### Task 3: Layering Guardrail Diagnostics (`LayeringDoc.php`)

**Files:**
- Modify: `scripts/generate-docs/LayeringDoc.php`
- Create: `packages/core/tests/Architecture/LayeringDiagnosticsTest.php`

- [ ] **Step 1: Write failing test for LayeringDoc returning AnalysisResult**

Create `packages/core/tests/Architecture/LayeringDiagnosticsTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/LayeringDoc.php';

use ArazzoDocs\AnalysisResult;
use ArazzoDocs\LayeringDoc;
use ArazzoDocs\ScannedFile;
use ArazzoDocs\Severity;

it('detects inverted layer dependency and returns AnalysisResult', function (): void {
    $scans = [
        'contracts' => [
            'State' => [
                new ScannedFile(
                    path: 'packages/contracts/src/State/BadContract.php',
                    relativeDir: 'State',
                    namespace: 'Alama\\Arazzo\\Contracts\\State',
                    className: 'BadContract',
                    isInterface: false,
                    uses: ['Alama\\Arazzo\\Runner\\Execution\\ScenarioRunner'],
                    useStatements: ['Alama\\Arazzo\\Runner\\Execution\\ScenarioRunner'],
                    content: "<?php\nuse Alama\\Arazzo\\Runner\\Execution\\ScenarioRunner;\n",
                    package: 'contracts',
                ),
            ],
        ],
        'runner' => [
            'Execution' => [
                new ScannedFile(
                    path: 'packages/runner/src/Execution/ScenarioRunner.php',
                    relativeDir: 'Execution',
                    namespace: 'Alama\\Arazzo\\Runner\\Execution',
                    className: 'ScenarioRunner',
                    isInterface: false,
                    uses: [],
                    useStatements: [],
                    content: '',
                    package: 'runner',
                ),
            ],
        ],
    ];

    $layerOrder = ['contracts', 'expression', 'document', 'runner', 'cli', 'laravel'];
    $result = LayeringDoc\render($scans, $layerOrder);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->markdown)->toContain('# Generated: Package Layer Order & Seams')
        ->and($result->diagnostics)->not->toBeEmpty();

    $inverted = array_values(array_filter(
        $result->diagnostics,
        fn ($d) => $d->rule === 'inverted-layer-dependency' && $d->severity === Severity::ERROR
    ));

    expect($inverted)->not->toBeEmpty()
        ->and($inverted[0]->file)->toBe('packages/contracts/src/State/BadContract.php')
        ->and($inverted[0]->message)->toContain('contracts cannot depend on runner');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/LayeringDiagnosticsTest.php`
Expected: FAIL (`render()` returns string, not `AnalysisResult`)

- [ ] **Step 3: Update `scripts/generate-docs/LayeringDoc.php`**

Update `scripts/generate-docs/LayeringDoc.php` to:
1. `use ArazzoDocs\AnalysisResult;`, `use ArazzoDocs\Diagnostic;`, `use ArazzoDocs\Severity;`.
2. When checking cross-package imports against `$layerOrder`:
   - If an import points to a package strictly higher in `$layerOrder`, emit:
     `Diagnostic(Severity::ERROR, 'layering', 'inverted-layer-dependency', "Inverted dependency: '$fromPkg' cannot depend on '$toPkg'", $file->path, 0, "Move shared contract down to '$fromPkg' or decouple using an interface.")`.
3. Return `new AnalysisResult(markdown: implode("\n", $lines)."\n", diagnostics: $diagnostics)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/LayeringDiagnosticsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/LayeringDoc.php packages/core/tests/Architecture/LayeringDiagnosticsTest.php
git commit -m "feat(docs): emit layering diagnostics from LayeringDoc"
```

---

### Task 4: SOLID & Coupling Guardrail Diagnostics (`SolidMetricsDoc.php` & `CouplingMetricsDoc.php`)

**Files:**
- Modify: `scripts/generate-docs/SolidMetricsDoc.php`
- Modify: `scripts/generate-docs/CouplingMetricsDoc.php`
- Create: `packages/core/tests/Architecture/SolidAndCouplingDiagnosticsTest.php`

- [ ] **Step 1: Write failing test for SolidMetricsDoc and CouplingMetricsDoc**

Create `packages/core/tests/Architecture/SolidAndCouplingDiagnosticsTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/NamespaceGraphDoc.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/SolidMetricsDoc.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/CouplingMetricsDoc.php';

use ArazzoDocs\AnalysisResult;
use ArazzoDocs\CouplingMetricsDoc;
use ArazzoDocs\ScannedFile;
use ArazzoDocs\Severity;
use ArazzoDocs\SolidMetricsDoc;

it('emits god-class diagnostic from SolidMetricsDoc', function (): void {
    // 350 lines long file
    $content = "<?php\n".str_repeat("// line\n", 350);
    $scans = [
        'runner' => [
            'Execution' => [
                new ScannedFile(
                    path: 'packages/runner/src/Execution/HugeRunner.php',
                    relativeDir: 'Execution',
                    namespace: 'Alama\\Arazzo\\Runner\\Execution',
                    className: 'HugeRunner',
                    isInterface: false,
                    uses: [],
                    useStatements: [],
                    content: $content,
                    package: 'runner',
                ),
            ],
        ],
    ];

    $result = SolidMetricsDoc\render($scans);

    expect($result)->toBeInstanceOf(AnalysisResult::class);
    $godClasses = array_values(array_filter(
        $result->diagnostics,
        fn ($d) => $d->rule === 'god-class' && $d->severity === Severity::WARNING
    ));

    expect($godClasses)->not->toBeEmpty()
        ->and($godClasses[0]->file)->toBe('packages/runner/src/Execution/HugeRunner.php')
        ->and($godClasses[0]->suggestedAction)->toContain('Decompose');
});

it('returns AnalysisResult from CouplingMetricsDoc', function (): void {
    $scans = [
        'contracts' => [
            'Spec' => [
                new ScannedFile(
                    path: 'packages/contracts/src/Spec/ArazzoDoc.php',
                    relativeDir: 'Spec',
                    namespace: 'Alama\\Arazzo\\Contracts\\Spec',
                    className: 'ArazzoDoc',
                    isInterface: false,
                    uses: [],
                    useStatements: [],
                    content: "<?php\n",
                    package: 'contracts',
                ),
            ],
        ],
    ];

    $result = CouplingMetricsDoc\render($scans);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->markdown)->toContain('# Generated: Coupling Metrics');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/SolidAndCouplingDiagnosticsTest.php`
Expected: FAIL (`render()` returns string, not `AnalysisResult`)

- [ ] **Step 3: Update `scripts/generate-docs/SolidMetricsDoc.php` and `CouplingMetricsDoc.php`**

1. In `SolidMetricsDoc.php`:
   - For every god class (> 300 LOC), emit `Diagnostic(Severity::WARNING, 'solid', 'god-class', "Class $class exceeds " . GOD_CLASS_LOC . " LOC ($loc LOC)", $file->path, 0, "Decompose $class into smaller single-responsibility collaborators.")`.
   - For every fat interface (> 7 methods), emit `Diagnostic(Severity::WARNING, 'solid', 'fat-interface', "Interface $interface has $methods methods (exceeds " . FAT_INTERFACE_METHODS . ")", $file->path, 0, "Split $interface into cohesive role interfaces (ISP).")`.
   - Return `new AnalysisResult(implode("\n", $lines)."\n", $diagnostics)`.
2. In `CouplingMetricsDoc.php`:
   - Return `new AnalysisResult(implode("\n", $lines)."\n", $diagnostics)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/SolidAndCouplingDiagnosticsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/SolidMetricsDoc.php scripts/generate-docs/CouplingMetricsDoc.php packages/core/tests/Architecture/SolidAndCouplingDiagnosticsTest.php
git commit -m "feat(docs): emit solid and coupling diagnostics"
```

---

### Task 5: Failure Modes Guardrail Diagnostics (`FailureModesDoc.php`)

**Files:**
- Modify: `scripts/generate-docs/FailureModesDoc.php`
- Create: `packages/core/tests/Architecture/FailureModesDiagnosticsTest.php`

- [ ] **Step 1: Write failing test for FailureModesDoc diagnostics**

Create `packages/core/tests/Architecture/FailureModesDiagnosticsTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/FailureModesDoc.php';

use ArazzoDocs\AnalysisResult;
use ArazzoDocs\FailureModesDoc;
use ArazzoDocs\ScannedFile;
use ArazzoDocs\Severity;

it('emits raw-exception diagnostic from FailureModesDoc', function (): void {
    $content = "<?php\nnamespace Foo;\nclass Bar {\n  public function test() {\n    throw new \\RuntimeException('bad');\n  }\n}\n";
    $scans = [
        'runner' => [
            'Execution' => [
                new ScannedFile(
                    path: 'packages/runner/src/Execution/BadThrow.php',
                    relativeDir: 'Execution',
                    namespace: 'Alama\\Arazzo\\Runner\\Execution',
                    className: 'BadThrow',
                    isInterface: false,
                    uses: [],
                    useStatements: [],
                    content: $content,
                    package: 'runner',
                ),
            ],
        ],
    ];

    $result = FailureModesDoc\render($scans);

    expect($result)->toBeInstanceOf(AnalysisResult::class);
    $rawExceptions = array_values(array_filter(
        $result->diagnostics,
        fn ($d) => $d->rule === 'raw-exception-thrown' && $d->severity === Severity::WARNING
    ));

    expect($rawExceptions)->not->toBeEmpty()
        ->and($rawExceptions[0]->file)->toBe('packages/runner/src/Execution/BadThrow.php')
        ->and($rawExceptions[0]->suggestedAction)->toContain('domain exception');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/FailureModesDiagnosticsTest.php`
Expected: FAIL (`render()` returns string, not `AnalysisResult`)

- [ ] **Step 3: Update `scripts/generate-docs/FailureModesDoc.php`**

Update `scripts/generate-docs/FailureModesDoc.php` to:
1. `use ArazzoDocs\AnalysisResult;`, `use ArazzoDocs\Diagnostic;`, `use ArazzoDocs\Severity;`.
2. When scanning throw sites:
   - If an un-namespaced or raw SPL exception is thrown (e.g. `\Exception`, `\RuntimeException`, `\InvalidArgumentException`), emit:
     `Diagnostic(Severity::WARNING, 'failure-mode', 'raw-exception-thrown', "Raw '$ex' thrown in $file->path", $file->path, $line, "Replace raw $ex with specialized domain exception from Contracts\\Exceptions.")`.
3. Return `new AnalysisResult(implode("\n", $lines)."\n", $diagnostics)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/FailureModesDiagnosticsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/FailureModesDoc.php packages/core/tests/Architecture/FailureModesDiagnosticsTest.php
git commit -m "feat(docs): emit failure modes diagnostics from FailureModesDoc"
```

---

### Task 6: Architecture Health Dashboard Generator (`ArchitectureHealthDoc.php`)

**Files:**
- Create: `scripts/generate-docs/ArchitectureHealthDoc.php`
- Create: `packages/core/tests/Architecture/ArchitectureHealthDocTest.php`

- [ ] **Step 1: Write failing test for ArchitectureHealthDoc**

Create `packages/core/tests/Architecture/ArchitectureHealthDocTest.php`:
```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Diagnostics.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/ArchitectureHealthDoc.php';

use ArazzoDocs\ArchitectureHealthDoc;
use ArazzoDocs\Diagnostic;
use ArazzoDocs\Severity;

it('renders architecture health markdown deterministically with scorecard and action tables', function (): void {
    $diagnostics = [
        new Diagnostic(
            severity: Severity::ERROR,
            category: 'boundary',
            rule: 'forbidden-vendor-import',
            message: 'Forbidden import of Symfony in document',
            file: 'packages/document/src/Parser/YamlParser.php',
            line: 14,
            suggestedAction: 'Inject parser contract instead',
        ),
        new Diagnostic(
            severity: Severity::WARNING,
            category: 'solid',
            rule: 'god-class',
            message: 'ScenarioRunner exceeds 300 LOC',
            file: 'packages/runner/src/Execution/ScenarioRunner.php',
            line: 1,
            suggestedAction: 'Decompose into step handlers',
        ),
    ];

    $markdown = ArchitectureHealthDoc\render($diagnostics);

    expect($markdown)->toContain('# Generated: Architecture Health Scorecard')
        ->and($markdown)->toContain('Forbidden import of Symfony in document')
        ->and($markdown)->toContain('packages/document/src/Parser/YamlParser.php:14')
        ->and($markdown)->toContain('Inject parser contract instead')
        ->and($markdown)->toContain('ScenarioRunner exceeds 300 LOC')
        ->and($markdown)->toContain('## Priority 1: Critical Violations (Errors)')
        ->and($markdown)->toContain('## Priority 2: Architectural Debt (Warnings)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/ArchitectureHealthDocTest.php`
Expected: FAIL (`ArchitectureHealthDoc.php` not found)

- [ ] **Step 3: Implement `scripts/generate-docs/ArchitectureHealthDoc.php`**

Create `scripts/generate-docs/ArchitectureHealthDoc.php`:
```php
<?php

declare(strict_types=1);

namespace ArazzoDocs\ArchitectureHealthDoc;

use ArazzoDocs\Diagnostic;
use ArazzoDocs\Severity;

const BANNER = <<<'MD'
<!-- GENERATED by scripts/generate-docs.php — DO NOT EDIT.
     Refreshed automatically on every commit (see .githooks/pre-commit). -->

# Generated: Architecture Health Scorecard

Deterministic assessment of system boundaries, layer purity, and SOLID metrics.
Use this executive summary to direct agents to resolve critical errors and debt.

MD;

/**
 * @param list<Diagnostic> $diagnostics
 */
function render(array $diagnostics): string
{
    $errors = array_values(array_filter($diagnostics, fn ($d) => $d->severity === Severity::ERROR));
    $warnings = array_values(array_filter($diagnostics, fn ($d) => $d->severity === Severity::WARNING));

    $errorCount = count($errors);
    $warningCount = count($warnings);

    // Deterministic score calculation
    $score = max(0, 100 - ($errorCount * 15) - ($warningCount * 2));
    $grade = match (true) {
        $score >= 90 => 'A',
        $score >= 80 => 'B',
        $score >= 70 => 'C',
        $score >= 60 => 'D',
        default => 'F',
    };

    $statusIcon = $errorCount === 0 ? 'PASS' : 'FAIL';

    $lines = [
        BANNER,
        '## Executive Scorecard',
        '',
        '| Metric | Value | Status |',
        '|---|---|---|',
        sprintf('| **Architecture Grade** | **%s (%d%%)** | %s |', $grade, $score, $errorCount === 0 ? '✅ Healthy' : '❌ Action Required'),
        sprintf('| **Critical Violations (Errors)** | **%d** | %s |', $errorCount, $errorCount === 0 ? '✅ Clean' : '❌ Fails Check'),
        sprintf('| **Architectural Debt (Warnings)** | **%d** | %s |', $warningCount, $warningCount === 0 ? '✅ None' : '⚠ Review'),
        '',
    ];

    if ($errorCount > 0) {
        $lines[] = '## Priority 1: Critical Violations (Errors)';
        $lines[] = '';
        $lines[] = '| # | Category | Rule | Location | Violation | Suggested Prompt / Directive for Agent |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($errors as $i => $err) {
            $loc = $err->line > 0 ? "{$err->file}:{$err->line}" : $err->file;
            $lines[] = sprintf(
                '| %d | `%s` | `%s` | `%s` | %s | `%s` |',
                $i + 1,
                $err->category,
                $err->rule,
                $loc,
                str_replace('|', '\\|', $err->message),
                str_replace('|', '\\|', $err->suggestedAction),
            );
        }
        $lines[] = '';
    }

    if ($warningCount > 0) {
        $lines[] = '## Priority 2: Architectural Debt (Warnings)';
        $lines[] = '';
        $lines[] = '| # | Category | Rule | Location | Warning | Suggested Action |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($warnings as $i => $warn) {
            $loc = $warn->line > 0 ? "{$warn->file}:{$warn->line}" : $warn->file;
            $lines[] = sprintf(
                '| %d | `%s` | `%s` | `%s` | %s | %s |',
                $i + 1,
                $warn->category,
                $warn->rule,
                $loc,
                str_replace('|', '\\|', $warn->message),
                str_replace('|', '\\|', $warn->suggestedAction),
            );
        }
        $lines[] = '';
    }

    $lines[] = '## Guardrails Deep Dive';
    $lines[] = '';
    $lines[] = '- [Boundary Audit](boundaries-audit.md) — Third-party imports and monorepo seams';
    $lines[] = '- [Package Layer Order](layering.md) — Downward dependency verification';
    $lines[] = '- [SOLID Metrics](solid-metrics.md) — Abstractness vs Instability, god classes, fat interfaces';
    $lines[] = '- [Coupling Metrics](coupling-metrics.md) — Fan-in, fan-out, instability index';
    $lines[] = '- [Failure Modes](failure-modes.md) — Exception tree and throw sites';
    $lines[] = '';

    return implode("\n", $lines)."\n";
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/ArchitectureHealthDocTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs/ArchitectureHealthDoc.php packages/core/tests/Architecture/ArchitectureHealthDocTest.php
git commit -m "feat(docs): add ArchitectureHealthDoc generator"
```

---

### Task 7: Orchestrator Dual-Mode CLI (`--check` / `--audit`) & Composer Script

**Files:**
- Modify: `scripts/generate-docs.php`
- Modify: `composer.json`
- Create: `packages/core/tests/Architecture/GenerateDocsCliTest.php`

- [ ] **Step 1: Write integration test for CLI modes in GenerateDocsCliTest**

Create `packages/core/tests/Architecture/GenerateDocsCliTest.php`:
```php
<?php

declare(strict_types=1);

it('supports --check CLI flag without erroring on script execution', function (): void {
    $script = dirname(__DIR__, 3).'/scripts/generate-docs.php';
    exec("php $script --check 2>&1", $output, $exitCode);

    $outText = implode("\n", $output);
    expect($outText)->toContain('Architecture');
    // Exit code will be 0 (if clean) or 1 (if active errors exist)
    expect($exitCode)->toBeIn([0, 1]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Architecture/GenerateDocsCliTest.php`
Expected: FAIL or unrecognized behavior

- [ ] **Step 3: Update `scripts/generate-docs.php` and `composer.json`**

1. In `scripts/generate-docs.php`:
   - Require `Diagnostics.php` and `ArchitectureHealthDoc.php`.
   - Parse `$argv`: check for `--check` or `--audit`.
   - When evaluating `$generated`, unpack either `AnalysisResult` or `string`:
     ```php
     $allDiagnostics = [];
     $renderedDocs = [];
     foreach ($generated as $filename => $result) {
         if ($result instanceof \ArazzoDocs\AnalysisResult) {
             $renderedDocs[$filename] = $result->markdown;
             array_push($allDiagnostics, ...$result->diagnostics);
         } else {
             $renderedDocs[$filename] = $result;
         }
     }
     $renderedDocs['architecture-health.md'] = \ArazzoDocs\ArchitectureHealthDoc\render($allDiagnostics);
     ```
   - If in check mode (`$isCheckMode`):
     - Do not write files.
     - Print formatted linter output:
       - For each ERROR: `[ERROR] rule: file:line - message (Action: suggestedAction)`
       - For each WARNING: `[WARNING] rule: file:line - message`
       - If errors > 0: `echo "✗ Architecture Check Failed: $errorCount errors.\n"; exit(1);`
       - If errors == 0: `echo "✓ Architecture Check Passed: 0 errors, $warningCount warnings.\n"; exit(0);`
   - If in normal mode:
     - Write files to `docs/generated/*.md`.
     - Output success message.
2. In `composer.json`:
   - Add `"docs:check": "php scripts/generate-docs.php --check"`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Architecture/GenerateDocsCliTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/generate-docs.php composer.json packages/core/tests/Architecture/GenerateDocsCliTest.php
git commit -m "feat(docs): add --check CLI mode and docs:check composer script"
```

---

### Task 8: Snapshot Tests & Docs Generation Verification

**Files:**
- Modify: `packages/core/tests/GeneratedDocsSnapshotTest.php`

- [ ] **Step 1: Update snapshot test to verify `architecture-health.md`**

In `packages/core/tests/GeneratedDocsSnapshotTest.php`, add:
```php
it('generates architecture-health.md with executive scorecard', function (): void {
    $path = dirname(__DIR__, 3).'/docs/generated/architecture-health.md';
    expect(file_exists($path))->toBeTrue();
    $content = file_get_contents($path);
    expect($content)->toContain('# Generated: Architecture Health Scorecard')
        ->and($content)->toContain('## Executive Scorecard');
});
```

- [ ] **Step 2: Run `php scripts/generate-docs.php` to generate all docs**

Run: `php scripts/generate-docs.php`
Expected: Generates 37 docs in `docs/generated/` including `architecture-health.md`.

- [ ] **Step 3: Run the full Pest suite to verify all snapshot tests pass**

Run: `vendor/bin/pest packages/core/tests/Architecture`
Expected: All architecture and diagnostics tests pass.

- [ ] **Step 4: Verify `--check` mode runs smoothly**

Run: `composer docs:check`
Expected: Executes cleanly, outputs architectural scorecard and exits accordingly.

- [ ] **Step 5: Commit**

```bash
git add docs/generated/ packages/core/tests/GeneratedDocsSnapshotTest.php
git commit -m "chore(docs): regenerate docs with architecture-health.md and snapshot assertion"
```
