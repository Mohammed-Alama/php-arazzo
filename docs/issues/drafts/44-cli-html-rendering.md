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

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `RenderCommand` gains `--format html` producing standalone HTML with embedded CSS (no external network requests).
- [ ] `--theme <name>` selects a theme; default `light`.
- [ ] `--themes <path>` loads a custom themes YAML file.
- [ ] `--list-themes` lists available themes and exits.
- [ ] `--layout portrait|landscape` controls diagram orientation.
- [ ] WCAG 2.2 AA contrast audit runs at theme load (draft 37 core); insufficient contrast reported.
- [ ] `--workflow`, `--output`, `--format mermaid` behaviour unchanged.
- [ ] `RenderCommandTest` gains cases: HTML output shape, theme selection, `--list-themes`, custom themes.
- [ ] PHPStan max and Pint pass.
- [ ] **Core HTML renderer** (draft 37) is a prerequisite; this draft is the CLI port only.

### 🧬 7. Source Grounding & Code Location

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

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive CLI options; `Renderer::toHtml()` (draft 37 core, `@internal` class).
- **Blast radius:** `packages/cli` `render`; consumers of Markdown/Mermaid output unaffected.
- **Severity:** medium — improves stakeholder accessibility; no behaviour change by default.
- **Rollback:** remove options; Markdown/Mermaid unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `render` supports only `markdown`/`mermaid` | T1 | `RenderCommand.php:45-51` | direct read |
| `Renderer` has no HTML, theme, or contrast-audit capability | T1 | `Renderer.php:1-60` | direct read |
| `--format html`, `--theme`, `--themes`, `--layout`, `--list-themes` do not exist | T1 | `RenderCommand.php` configure() | direct read |
| Draft 37 is the core dependency | T2 | draft 37 (core HTML renderer) | cross-ref |