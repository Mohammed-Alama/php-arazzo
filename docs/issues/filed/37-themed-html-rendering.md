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

## 6. Issue Context & Architectural Intent

**Core Domain Objective:** Render Arazzo documents as themed standalone HTML with WCAG 2.2 AA contrast audit, so non-developer stakeholders can inspect workflows without reading raw YAML.

**Architectural Boundary:** Bounded context = rendering + presentation. Packages: `packages/cli` (`Renderer`), `packages/document` (`ArazzoDocument` contract). Pure presentation — no parsing, no execution; reusable by CLI, MCP, or UI. CLI port tracked in draft 44.

---

## 7. arc42 Quality Characteristics Compliance Matrix

- [ ] **Efficient:** HTML generation is O(document size); embedded CSS (zero network requests); one page per workflow plus index.
- [ ] **Flexible:** Theme registry with built-in themes (light/dark/pb33f) and custom `themes.yml`; `--theme <name>`, `--layout portrait|landscape`; additive `toHtml()` method.
- [ ] **Maintainable:** `Renderer` is `@internal`; pure presentation separates rendering from parsing/execution; `toMarkdown()`/`toMermaid()` unchanged.
- [ ] **Operable:** WCAG 2.2 AA contrast audit at theme roll; insufficient contrast reported; rollback by removing `toHtml()` and theme options.
- [ ] **Reliable:** Deterministic HTML output; idempotent; no external dependencies (embedded CSS).
- [ ] **Safe:** No state mutation; rendering is read-only; no network requests (embedded CSS).
- [ ] **Secure:** No authorization impact; HTML is static presentation; no XSS surface from document content.
- [ ] **Suitable:** Closes gap where `Renderer` produces Markdown/Mermaid only; non-developer stakeholders need accessible HTML.
- [ ] **Usable:** `--theme` selects theme (default `light`); `--list-themes`; WCAG contrast audit; portrait/landscape layout.

---

## 8. Behavioral Specification (Given / When / Then)

**Scenario 1: `toHtml()` produces standalone HTML with embedded CSS**
- **Given** an Arazzo 1.1.0 document (`doc.arazzo`) containing workflow index 0; `(new Renderer())->toHtml($document, ['theme' => 'light', 'layout' => 'portrait'])` is called; `Renderer` (draft 37 core) has the `toHtml()` method; no external network requests are made.
- **When** `toHtml()` generates the HTML output.
- **Then** standalone HTML with embedded CSS is produced; zero external network requests (all CSS is embedded); the HTML contains the rendered workflow (Mermaid flowchart rendered to SVG or inline); `toMarkdown()`/`toMermaid()` behavior is unchanged.

**Scenario 2: Theme selection**
- **Given** `--theme dark` (or theme parameter `'dark'`); `Renderer` has built-in themes (`light`, `dark`, `pb33f`) loaded in a theme registry; default theme is `light`.
- **When** HTML is generated with the selected theme.
- **Then** dark theme colors are applied to the HTML output; `--theme light` is the default if no theme is specified; custom themes can be loaded via `--themes path/to/themes.yml` (CLI port, draft 44).

**Scenario 3: Custom themes YAML file**
- **Given** a custom themes YAML file (`themes.yml`) defining a `high-contrast` theme with specific colors; `--themes themes.yml` is passed (CLI port).
- **When** `Renderer` loads the custom themes YAML file.
- **Then** custom themes are available for selection; `high-contrast` theme can be selected with `--theme high-contrast`; custom themes extend the built-in theme registry.

**Scenario 4: Layout control**
- **Given** `--layout landscape` (or theme parameter `'layout' => 'landscape'`); Mermaid diagrams are rendered in the HTML output.
- **When** Mermaid diagrams are rendered with the selected layout.
- **Then** landscape orientation is applied to the diagrams; `--layout portrait` is the default; layout controls diagram orientation in the rendered HTML.

**Scenario 5: WCAG 2.2 AA contrast audit**
- **Given** a theme with insufficient contrast ratio (e.g., light gray text on white background, contrast ratio < 4.5:1); WCAG 2.2 AA contrast audit (success criterion 1.4.3) runs at theme load.
- **When** the theme is loaded and contrast ratios are computed.
- **Then** insufficient contrast is reported; the audit identifies which color pairs fail WCAG 2.2 AA; theme authors are alerted before publishing; no HTML is generated with inaccessible contrast unless explicitly overridden.

**Scenario 6: One page per workflow plus index**
- **Given** an Arazzo document with multiple workflows (`wf-1`, `wf-2`, `wf-3`); rendering all workflows to HTML.
- **When** `toHtml()` renders all workflows.
- **Then** one HTML page per workflow is produced (`wf-1.html`, `wf-2.html`, `wf-3.html`) plus an index page (`index.html`) that links to all workflow pages; navigation between workflow pages is available.

**Scenario 7: `toMarkdown()`/`toMermaid()` unchanged**
- **Given** existing Markdown/Mermaid output from `toMarkdown()`/`toMermaid()`.
- **When** `toHtml()` is added to `Renderer`.
- **Then** `toMarkdown()`/`toMermaid()` behavior is unchanged; no regression in existing output formats; `Renderer` remains `@internal`; no public contract break.

---

## 9. Actionable Implementation & Verification Checklist

- [ ] `Renderer` gains `toHtml()` producing standalone HTML with embedded CSS (no external network requests)
- [ ] `--theme <name>` selects theme; default `light` (parameter on `toHtml`)
- [ ] Custom themes YAML file supported (`--themes` on CLI port)
- [ ] `--list-themes` lists available themes (CLI port)
- [ ] `--layout portrait|landscape` controls diagram orientation
- [ ] WCAG 2.2 AA contrast audit runs at theme roll; insufficient contrast reported
- [ ] One HTML page per workflow plus index page when rendering all workflows
- [ ] `toMarkdown()`/`toMermaid()` behavior unchanged
- [ ] `RendererTest` cases: HTML output shape, theme selection, contrast audit, custom themes
- [ ] Unit & integration test coverage written and passing
- [ ] Static analysis & type safety verified (PHPStan max + Pint)
- [ ] CLI port (`--format html`, `--theme`, `--themes`, `--layout`, `--list-themes`) tracked separately in draft 44

### 🧬 10. Source Grounding & Code Location

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

### 🌍 11. Public Domains & Impact Profile

- **Public API impact:** additive `Renderer` method; `Renderer` is `@internal`, so no public contract break.
- **Blast radius:** `packages/cli` `render`; consumers of Markdown/Mermaid output unaffected.
- **Severity:** medium — improves stakeholder accessibility; no behaviour change by default.
- **Rollback:** remove `toHtml()` and theme options; Markdown/Mermaid unchanged.

### 📊 12. Epistemic Verification Matrix

| Claim | Tier | Evidence | Verification |
| --- | --- | --- | --- |
| `Renderer` has no HTML, theme, or contrast-audit capability | T1 | `Renderer.php:1-60` | direct read |
| `toMarkdown()` / `toMermaid()` are the only output formats | T1 | `Renderer.php` | direct read |
| WCAG 2.2 AA contrast is an established accessibility standard | T2 | WCAG 2.2 success criterion 1.4.3 (contrast) | external spec |
| No `toHtml()` method exists today | T1 | grep for `toHtml` in `Renderer.php` → no match | executed grep |