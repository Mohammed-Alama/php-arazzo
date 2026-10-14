# 07: Add arazzo view <file> command launching local browser preview

## Parent
Epic: Interactive Visual Workflow Viewer & Standalone HTML Renderer

## What to build
Provide a fast local preview command `arazzo view <file>` that starts an ephemeral local web server and opens the default web browser to display the interactive Arazzo workflow visualizer, matching the developer ergonomics of `npx @jentic/arazzo-ui`.

## Acceptance criteria
- [ ] Add `ViewCommand` in `packages/cli/src/Console/Command/ViewCommand.php` mapped to `arazzo view <file>`.
- [ ] Start an ephemeral PHP web server on a free local port (e.g. `localhost:8088`).
- [ ] Render the interactive HTML bundle on request.
- [ ] Automatically launch the default system browser via `open` (macOS), `xdg-open` (Linux), or `start` (Windows).
- [ ] Handle clean shutdown on `SIGINT` (Ctrl+C).
- [ ] Add functional tests for command options (`--port`, `--no-browser`).

## Blocked by
- #96
