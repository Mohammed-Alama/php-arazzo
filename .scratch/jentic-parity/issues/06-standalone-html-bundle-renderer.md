# 06: Add standalone HTML bundle renderer (arazzo render --format=html) with interactive split diagram and documentation views

## Parent
Epic: Interactive Visual Workflow Viewer & Standalone HTML Renderer

## What to build
Provide a standalone, single-file HTML renderer that transforms any Arazzo document into a rich interactive web viewer. The generated HTML file requires no external internet connection or CDN, embedding an interactive node graph (zoom/pan, collapsible workflows), step detail drawer, and parameter inspection panel, matching `@jentic/arazzo-ui`.

## Acceptance criteria
- [ ] Add `toHtml()` method to `packages/cli/src/Renderer/Renderer.php` and support `--format=html` in `RenderCommand`.
- [ ] Generate self-contained HTML containing inline SVG graph, responsive CSS, and minimal vanilla JavaScript.
- [ ] Render interactive nodes for steps with status icons and operation IDs.
- [ ] Provide split documentation view: clicking a step displays parameters, request body, outputs, and failure actions.
- [ ] Support `--output=<path.html>` option.
- [ ] Add tests verifying valid HTML output with no unresolved external dependencies.

## Blocked by
None (can start immediately).
