# Architecture Health & Dual-Mode Doc Generator Design

## Goal

Transform `scripts/generate-docs/` from passive markdown generators into a dual-mode **Diagnostic Engine + Doc Renderer**. This provides:
1. **For Humans**: A unified executive dashboard (`docs/generated/architecture-health.md`) that highlights system health, active errors, and prioritized, prompt-ready directives for agents.
2. **For Agents**: A deterministic `--check` CLI mode that acts as an architectural linter, outputting file:line violations and exiting non-zero when boundaries or layering rules are broken.

---

## Architecture Overview

Currently, `scripts/generate-docs.php` iterates through 36 generator modules, each formatting markdown tables and writing directly to `docs/generated/*.md`.

The new architecture decouples **rule evaluation & diagnostics collection** from **markdown presentation**:

```text
               +-------------------------------------+
               |         Scanner.php (AST)           |
               +-------------------------------------+
                                  |
                                  v
+-------------------------------------------------------------------------+
|                        Generator Modules                                |
|                                                                         |
|  Active Guardrails (return AnalysisResult):                             |
|    - BoundariesAuditDoc: forbidden vendor imports, monorepo integrity   |
|    - LayeringDoc: downward dependency hierarchy                         |
|    - SolidMetricsDoc: god classes (>300 LOC), fat interfaces (>7)       |
|    - CouplingMetricsDoc: instability traps, circular couplings          |
|    - FailureModesDoc: untyped/raw exceptions thrown                     |
|                                                                         |
|  Descriptive Renderers (return string):                                 |
|    - DatabaseSchemaDoc, CliReferenceDoc, ExpressionAstDoc, etc.         |
+-------------------------------------------------------------------------+
                                  |
                                  v
               +-------------------------------------+
               |        Diagnostics Collector        |
               +-------------------------------------+
                     /                         \
                    v                           v
     [Default Mode: composer docs]    [Check Mode: composer docs:check]
     - Renders all 36 docs            - CLI linter output (file:line)
     - Renders architecture-health.md - Exits 1 on ERROR, 0 on clean
     - Preserves byte-determinism
```

---

## Data Structures (`scripts/generate-docs/Diagnostics.php`)

### `Severity`
```php
namespace ArazzoDocs;

enum Severity: string
{
    case ERROR = 'ERROR';     // Architectural policy violation (blocks check)
    case WARNING = 'WARNING'; // Code smell or debt (e.g. god class, high instability)
    case INFO = 'INFO';       // Informational observation
}
```

### `Diagnostic`
```php
final readonly class Diagnostic
{
    public function __construct(
        public Severity $severity,
        public string $category,              // e.g. 'boundary', 'layering', 'solid', 'coupling'
        public string $rule,                  // e.g. 'forbidden-vendor-import', 'god-class'
        public string $message,               // Human explanation of the issue
        public string $file,                  // Repo-relative path (e.g. packages/document/src/...)
        public int $line = 0,                 // Offending line number (0 if file-level)
        public string $suggestedAction = '',  // Formatted directive prompt for an AI agent
    ) {}
}
```

### `AnalysisResult`
```php
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

---

## Active Guardrail Rules & Severity Mapping

### 1. `BoundariesAuditDoc`
- **Forbidden Vendor Import (`ERROR`)**: Library package (`contracts`, `expression`, `document`, `runner`) imports a third-party vendor marked forbidden in `POLICY` (e.g. `Symfony`, `GuzzleHttp`, `cebe`).
  - *Action*: Suggest replacing with PSR contract or injected adapter.
- **Facade-Seam Violation (`ERROR`)**: Cross-package reference targets a concrete class instead of an interface or allowlisted value type (`Contracts\Spec\*`, `Contracts\State\*`, `Contracts\Support\*`).
  - *Action*: Point to the corresponding interface or contract abstraction.
- **Empty Core Aggregator (`ERROR`)**: Any PHP source file present under `packages/core/src/`.
  - *Action*: Move class into its appropriate split package.
- **Unclassified Vendor Import (`WARNING`)**: Vendor imported that is not categorized in `POLICY`.
  - *Action*: Categorize vendor in `BoundariesAuditDoc::POLICY`.

### 2. `LayeringDoc`
- **Inverted Layer Dependency (`ERROR`)**: A package imports from a package above it in `PACKAGE_LAYER_ORDER` (`contracts` <- `expression` <- `document` <- `runner` <- `cli` <- `laravel`).
  - *Action*: Invert dependency using contracts or relocate the dependent interface.

### 3. `SolidMetricsDoc`
- **God Class (`WARNING`)**: Class exceeds 300 LOC (`GOD_CLASS_LOC`).
  - *Action*: Decompose into single-responsibility collaborators.
- **Fat Interface (`WARNING`)**: Interface defines > 7 methods (`FAT_INTERFACE_METHODS`).
  - *Action*: Segregate into role-specific sub-interfaces.
- **Concrete Hub (`WARNING`)**: Module with Fan-In $\ge 4$ and 0 abstractness.
  - *Action*: Introduce abstractions for heavily depended-upon components.

### 4. `CouplingMetricsDoc`
- **Circular / High-Instability Trap (`WARNING`)**: Unstable module ($I > 0.7$) heavily depended upon (Fan-In $\ge 3$).
  - *Action*: Decouple dependents using dependency inversion.

### 5. `FailureModesDoc`
- **Generic Exception Thrown (`WARNING`)**: Source throws base `\Exception` or `\RuntimeException` instead of package domain exception.
  - *Action*: Replace with domain-specific exception class.

---

## Executive Dashboard: `docs/generated/architecture-health.md`

Generated automatically during normal runs (`composer docs`), this file provides:
1. **Health Scorecard**: Deterministic scoring (no timestamps):
   - Health Grade (A/B/C/F) & Percentage.
   - Count of Critical Errors.
   - Count of Architectural Warnings.
   - Guardrails Enforced ratio.
2. **Priority Action Items Table**:
   - Lists all `ERROR` and `WARNING` diagnostics.
   - Includes exact `File:Line`, `Category`, `Rule`, and a copy-pasteable **Agent Prompt** column.
3. **Deep-Dive Navigation**:
   - Quick links to specific reports (`boundaries-audit.md`, `layering.md`, `solid-metrics.md`, etc.).

---

## CLI & Agent Integration

### 1. `scripts/generate-docs.php` CLI Modes
- **Generation Mode**: `php scripts/generate-docs.php`
  - Runs all generators.
  - Emits all 36 docs + `architecture-health.md`.
  - Preserves byte-identical determinism.
  - Always returns exit code 0.
- **Audit / Check Mode**: `php scripts/generate-docs.php --check` (or `--audit`)
  - Runs all static scans in read-only mode (writes no files).
  - Collects all diagnostics.
  - Formats errors and warnings to console output (standard linter format).
  - Returns **exit code 1** if any `ERROR` diagnostic exists; returns **exit code 0** if 0 errors.

### 2. Composer Script Integration
In root `composer.json`:
```json
"scripts": {
    "docs": "php scripts/generate-docs.php",
    "docs:check": "php scripts/generate-docs.php --check"
}
```

### 3. Agent Workflow
- Before declaring a refactor or feature complete, agents execute `composer docs:check`.
- If an architectural boundary or layer order was broken, the agent sees the error and prompt-ready suggested action immediately, preventing regressions from slipping into git history.

---

## Testing & Verification Plan

1. **Snapshot Tests**: Update `packages/core/tests/GeneratedDocsSnapshotTest.php` to include `architecture-health.md`.
2. **Deterministic Output**: Run `php scripts/generate-docs.php` multiple times on a clean git working copy and confirm `git status --porcelain` is empty.
3. **Check Mode Test**:
   - Run `php scripts/generate-docs.php --check` on current code and verify exit code.
   - Intentionally introduce a dummy forbidden import in a test/fixture, verify `--check` exits with 1 and displays the exact file, line, and suggested fix.
