# Draft issue 44 — CLI: `render --format html` with themes and WCAG audit

- **Suggested title:** `[Feature] `arazzo render` produce themed standalone HTML with WCAG contrast audit`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #89 Interactive Visual Workflow Viewer & Standalone HTML Renderer)
- **Packages:** `cli`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `RenderCommand` options; `packages/cli` `Renderer` (draft 37 core HTML rendering).
- **Arazzo spec version:** independent of spec version; affects rendering of any `arazzo: 1.0.x` / `1.1.x` document.
- **PHP target:** 8.4.
- **Ecosystem:** CLI entry point only; the core HTML renderer lives in draft 37.
- **Kind / level:** `kind:feature`, `level:story`, under #89 (Interactive Visual Workflow Viewer & Standalone HTML Renderer).
- **Priority:** P2 — non-developer stakeholders (product, support, reviewers) cannot inspect a workflow without running PHP or reading raw YAML.

### 📝 2. Problem Statement & Intent

Draft 37 adds core themed standalone HTML rendering with WCAG audit to `Renderer`. This draft exposes it via CLI flags:

- `render` supports only `markdown`/`mermaid` (`RenderCommand.php:45-51`); no `html` format.
- No `--theme`, `--themes`, `--layout`, `--list-themes` flags.

Expected: `arazzo render --format html <file>` writes a standalone HTML file (embedded CSS, no external network requests); `--theme`, `--themes`, `--layout`, `--list-themes` provide rendering ergonomics.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `RenderCommand` gains `--format html` producing standalone HTML with embedded CSS (no external network requests).
- `--theme <name>` selects a theme; default `light`.
- `--themes <path>` loads a custom themes YAML file.
- `--list-themes` lists available themes and exits.
- `--layout portrait|landscape` controls diagram orientation.
- WCAG 2.2 AA contrast audit runs at theme load (draft 37 core); insufficient contrast reported.

**Non-goals**

- Core HTML renderer — that is draft 37.
- Interactive visual workflow viewer — tracked separately under #89.
- PDF/print export; real-time collaboration.

**Prior art in this repository**

- `packages/cli/src/Console/Command/RenderCommand.php:45-51` — `--format markdown|mermaid` only; no `html`, `--theme`, `--themes`, `--layout`, `--list-themes`.
- `packages/cli/src/Renderer/Renderer.php:1-60` — `toMarkdown()` / `toMermaid()`; no `toHtml()` (draft 37 core adds it).

### 📄 4. Structural Artifacts & Raw Inputs

```bash
arazzo render doc.arazzo.yaml --format html --theme dark --layout portrait --output workflow.html
arazzo render doc.arazzo.yaml --list-themes
```

### 🌀 5. Runtime Execution State Context

- Command: `arazzo render <file> --format html [--theme name] [--themes path] [--layout portrait|landscape] [--list-themes] [--output path]`.
- `RenderCommand` builds `Renderer`, calls `toHtml()` with theme/layout options.
- `--output` writes to file instead of stdout (already exists; reuse).
- `--workflow` restricts output to one workflow (already exists; reuse).

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Expose core themed HTML rendering via CLI flags so `arazzo render` can produce standalone HTML with WCAG contrast audit.

**Architectural Boundary:** Bounded context = CLI `render` command options. Packages: `packages/cli` (`RenderCommand` options), `packages/cli` (`Renderer` — draft 37 core). CLI port only; core HTML renderer is draft 37.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** CLI flag parsing O(flags); HTML generation O(document size); embedded CSS (zero network requests).
- [ ] **Flexible:** `--format html`, `--theme <name>`, `--themes <path>`, `--layout portrait|landscape`, `--list-themes`; additive options.
- [ ] **Maintainable:** Additive CLI options; `Renderer::toHtml()` (`@internal`); `--workflow`, `--output`, `--format mermaid` unchanged.
- [ ] **Operable:** WCAG 2.2 AA contrast audit at theme load (draft 37 core); insufficient contrast reported; rollback by removing options.
- [ ] **Reliable:** Deterministic HTML output; `--format html` produces standalone HTML with embedded CSS.
- [ ] **Safe:** Read-only rendering; no state mutation; no network requests.
- [ ] **Secure:** No authorization impact; static HTML presentation; no XSS surface from document content.
- [ ] **Suitable:** Non-developer stakeholders can inspect workflows without reading raw YAML.
- [ ] **Usable:** `arazzo render doc.yaml --format html --theme dark --layout portrait --output workflow.html`.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `--format html` produces standalone HTML**
- **Given** `arazzo render doc.yaml --format html`; `RenderCommand` (draft 44 core) accepts `--format html`; `Renderer::toHtml()` (draft 37 core) produces standalone HTML with embedded CSS.
- **When** `php bin/console arazzo render doc.yaml --format html` executes.
- **Then** standalone HTML with embedded CSS is produced; zero external network requests (all CSS is embedded inline); the HTML contains the rendered workflow; `toMarkdown()`/`toMermaid()` behavior is unchanged.

**Scenario 2: `--theme` selects theme**
- **Given** `--theme dark`; `Renderer` has built-in themes (`light`, `dark`, `pb33f`) loaded in a theme registry; default theme is `light`.
- **When** `php bin/console arazzo render doc.yaml --format html --theme dark` executes.
- **Then** dark theme colors are applied to the HTML output; `--theme light` is the default if no theme is specified; custom themes can be loaded via `--themes path/to/themes.yml` (CLI port).

**Scenario 3: `--themes` loads custom themes YAML**
- **Given** `--themes path/to/themes.yml` defining custom themes; `Renderer` loads the custom themes YAML file.
- **When** `php bin/console arazzo render doc.yaml --format html --themes path/to/themes.yml` executes.
- **Then** custom themes are available for selection; built-in themes are extended with custom themes.

**Scenario 4: `--list-themes` lists themes**
- **Given** `--list-themes` flag is passed; `Renderer` has built-in themes loaded.
- **When** `php bin/console arazzo render doc.yaml --list-themes` executes.
- **Then** available themes are listed (e.g., `light`, `dark`, `pb33f`); the command exits after listing; no HTML is generated.

**Scenario 5: `--layout` controls orientation**
- **Given** `--layout portrait|landscape`; Mermaid diagrams are rendered in the HTML output.
- **When** `php bin/console arazzo render doc.yaml --format html --layout landscape` executes.
- **Then** the specified orientation is applied to Mermaid diagrams; `portrait` is the default.

**Scenario 6: WCAG contrast audit**
- **Given** a theme with insufficient contrast ratio (e.g., light gray text on white background, contrast ratio < 4.5:1); WCAG 2.2 AA contrast audit (draft 37 core) runs at theme load.
- **When** the theme is loaded and contrast ratios are computed.
- **Then** insufficient contrast is reported; the audit identifies which color pairs fail WCAG 2.2 AA; theme authors are alerted before publishing.

**Scenario 7: Existing behavior unchanged**
- **Given** `--workflow`, `--output`, `--format mermaid` options are still supported; `Renderer::toMarkdown()`/`toMermaid()` are unchanged.
- **When** `php bin/console arazzo render doc.yaml --format mermaid --workflow wf-1 --output output.md` executes.
- **Then** behavior is unchanged; `--workflow`, `--output`, `--format mermaid` work as before; `toMarkdown()`/`toMermaid()` produce the same output as before `toHtml()` was added.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `RenderCommand` gains `--format html` producing standalone HTML with embedded CSS (no external network requests)
- [ ] `--theme <name>` selects theme; default `light`
- [ ] `--themes <path>` loads custom themes YAML file
- [ ] `--list-themes` lists available themes and exits
- [ ] `--layout portrait|landscape` controls diagram orientation
- [ ] WCAG 2.2 AA contrast audit runs at theme load (draft 37 core); insufficient contrast reported
- [ ] `--workflow`, `--output`, `--format mermaid` behavior unchanged
- [ ] `RenderCommandTest` cases: HTML output shape, theme selection, `--list-themes`, custom themes
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] Core HTML renderer (draft 37) is prerequisite; this draft is CLI port only

### 🧬 10. Source Grounding & Code Location

- `packages/cli/src/Console/Command/RenderCommand.php:45-51` — `configure()`: `--format markdown|mermaid` only; no `html`, `--theme`, `--themes`, `--layout`, `--list-themes`.
- `packages/cli/src/Console/Command/RenderCommand.php:53-90` — `execute()`: builds Markdown or Mermaid; no HTML branch.
- `packages/cli/src/Renderer/Renderer.php:1-60` — `toMarkdown()` / `toMermaid()`; no `toHtml()` (draft 37 core adds it).

<details><summary><code>RenderCommand.php:45-51</code></summary>

```php
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to an Arazzo YAML/JSON document')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: markdown or mermaid', 'markdown')
            ->addOption('workflow', 'w', InputOption::VALUE_REQUIRED, 'Restrict Mermaid output to one workflowId')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to file instead of stdout');
    }
```

</details>

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; `Renderer::toHtml()` (draft 37 core, `@internal` class).
- **Blast radius:** `packages/cli` `render`; consumers of Markdown/Mermaid output unaffected.
- **Severity:** medium — improves stakeholder accessibility; no behaviour change by default.
- **Rollback:** remove options; Markdown/Mermaid unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `render` supports only `markdown`/`mermaid` | T1 | `RenderCommand.php:45-51` | direct read |
| `Renderer` has no HTML, theme, or contrast-audit capability | T1 | `Renderer.php:1-60` | direct read |
| `--format html`, `--theme`, `--themes`, `--layout`, `--list-themes` do not exist | T1 | `RenderCommand.php` configure() | direct read |
| Draft 37 is the core dependency | T2 | draft 37 (core HTML renderer) | cross-ref |