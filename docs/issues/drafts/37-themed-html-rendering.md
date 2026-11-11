# Draft issue 37 — Themed standalone HTML rendering with WCAG audit and custom themes

- **Suggested title:** `[Feature] Render Arazzo documents as themed standalone HTML with WCAG contrast audit`
- **Kind:** `kind:feature`
- **Level:** `level:story` (suggested parent: #89 Interactive Visual Workflow Viewer & Standalone HTML Renderer)
- **Packages:** `cli`, `document`

---

### 🏗️ 1. Core Metadata & Alignment

- **Component / subsystem:** `packages/cli` `Renderer` (rendering engine); `packages/document` `ArazzoDocument` contract.
- **Arazzo spec version:** independent of spec version; affects rendering of any `arazzo: 1.0.x` / `1.1.x` document.
- **PHP target:** 8.4.
- **Ecosystem:** framework-agnostic core; port-agnostic (usable from CLI, MCP, or UI).
- **Kind / level:** `kind:feature`, `level:story`, under #89 (Interactive Visual Workflow Viewer & Standalone HTML Renderer).
- **Priority:** P2 — non-developer stakeholders (product, support, reviewers) cannot inspect a workflow without running PHP or reading raw YAML.

### 📝 2. Problem Statement & Intent

php-arazzo's `Renderer` produces Markdown or Mermaid only (`Renderer.php:1-60`), with no theming, no accessibility audit, and no standalone HTML:

- `Renderer` has `toMarkdown()` and `toMermaid()` only; no `toHtml()`.
- No theme registry, no WCAG contrast audit, no custom themes, no self-contained HTML artifact.

Expected: a rendering engine that produces standalone, themed HTML with embedded CSS (zero network requests), one page per workflow plus an index, a WCAG 2.2 AA contrast audit at theme load, custom theme support (`themes.yml`), and portrait/landscape layout. The engine is pure presentation: no parsing, no execution — so it can be reused by CLI, MCP, or UI entry points.

### 🎯 3. Feature Scope & Prior Art

**Goals**

- `Renderer` gains `toHtml()` producing standalone HTML with embedded CSS (zero network requests).
- `--theme <name>` selects a theme; default `light`.
- Custom themes YAML file supported.
- `--layout portrait|landscape` controls diagram orientation.
- WCAG 2.2 AA contrast audit runs at theme load; insufficient contrast is reported.
- One HTML page per workflow plus an index page when rendering all workflows.
- `toMarkdown()` / `toMermaid()` behaviour unchanged.

**Non-goals**

- Interactive visual workflow viewer (clickable nodes, live execution) — tracked separately under #89.
- Real-time collaboration or a dev server.
- PDF/print export.
- CLI flags (`--format html`, `--theme`, `--themes`, `--layout`, `--list-themes` on `RenderCommand`) — tracked separately in draft 44.

**Prior art in this repository**

- `packages/cli/src/Renderer/Renderer.php:1-60` — `toMarkdown()` / `toMermaid()` existing patterns; pure presentation, no parsing/execution.
- `packages/cli/src/Console/Command/RenderCommand.php:45-51` — `render` command with `--format markdown|mermaid`, `--workflow`, `--output` (CLI side, referenced for context).

### 📄 4. Structural Artifacts & Raw Inputs

```php
$html = (new Renderer())->toHtml($document, [
    'theme' => 'light',
    'layout' => 'portrait',
]);
```

Expected output: standalone HTML file with embedded CSS, navigable per-workflow pages, WCAG contrast audit applied to theme colors.

### 🌀 5. Runtime Execution State Context

- The renderer is stateless and pure presentation: no parsing, no execution.
- Theme registry loads built-in themes (light/dark/pb33f) and optional custom `themes.yml`.
- WCAG 2.2 AA contrast audit runs at theme load; insufficient contrast is reported.
- Entry point decides where to write the HTML (stdout, file, etc.).

### 🧪 6. Acceptance Criteria & Behavioral Specification

- [ ] `Renderer` gains `toHtml()` producing standalone HTML with embedded CSS (no external network requests).
- [ ] `--theme <name>` selects a theme; default `light`. (parameter on `toHtml`)
- [ ] Custom themes YAML file supported (`--themes` on CLI port).
- [ ] `--list-themes` lists available themes. (CLI port)
- [ ] `--layout portrait|landscape` controls diagram orientation.
- [ ] WCAG 2.2 AA contrast audit runs at theme load; insufficient contrast is reported.
- [ ] One HTML page per workflow plus an index page when rendering all workflows.
- [ ] `toMarkdown()` / `toMermaid()` behaviour unchanged.
- [ ] `RendererTest` gains cases: HTML output shape, theme selection, contrast audit, custom themes.
- [ ] PHPStan max and Pint pass.
- [ ] **CLI port** (`--format html`, `--theme`, `--themes`, `--layout`, `--list-themes` flags on `RenderCommand`) is tracked separately in draft 44.

### 🧬 7. Source Grounding & Code Location

- `packages/cli/src/Renderer/Renderer.php:1-60` — `toMarkdown()` / `toMermaid()`; no `toHtml()`, no theme registry, no contrast audit.
- `packages/cli/src/Console/Command/RenderCommand.php:45-51` — `--format markdown|mermaid` only (CLI side, referenced for context).
- `packages/cli/src/Console/Command/RenderCommand.php:53-90` — `execute()` builds Markdown or Mermaid; no HTML branch (CLI side).

<details><summary><code>Renderer.php:1-60</code></summary>

```php
/**
 * Renders Arazzo documents as Mermaid flowcharts and Markdown docs.
 * Pure presentation: no parsing, no execution.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class Renderer
{
    public function toMermaid(ArazzoDocument $document, ?string $workflowId = null): string
    {
        $lines = ['flowchart TD'];
```

</details>

### 🌍 8. Public Domains & Impact Profile

- **Public API impact:** additive `Renderer` method; `Renderer` is `@internal`, so no public contract break.
- **Blast radius:** `packages/cli` `render`; consumers of Markdown/Mermaid output unaffected.
- **Severity:** medium — improves stakeholder accessibility; no behaviour change by default.
- **Rollback:** remove `toHtml()` and theme options; Markdown/Mermaid unchanged.

### 📊 9. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `Renderer` has no HTML, theme, or contrast-audit capability | T1 | `Renderer.php:1-60` | direct read |
| `toMarkdown()` / `toMermaid()` are the only output formats | T1 | `Renderer.php` | direct read |
| WCAG 2.2 AA contrast is an established accessibility standard | T2 | WCAG 2.2 success criterion 1.4.3 (contrast) | external spec |
| No `toHtml()` method exists today | T1 | grep for `toHtml` in `Renderer.php` → no match | executed grep |