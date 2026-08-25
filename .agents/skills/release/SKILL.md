---
name: release
description: Prepare a release of alama/arazzo-core or alama/laravel-arazzo — gates, version bump, changelog promotion. Use when cutting/tagging a release or bumping a package version.
---

# Release

Wraps the discipline in `docs/release-checklist.md` into one deterministic step: the script runs the behavior gates, refuses a dirty tree, bumps the package `composer.json` version, and promotes `CHANGELOG.md`'s `[Unreleased]` section to a dated release heading. It never tags or pushes.

## Step 1 — Fill the changelog first

The script promotes what is already written. Ensure every user-visible change since the last release sits under `## [Unreleased]` with **BREAKING** prefixed where needed — an empty section means nothing to ship.

## Step 2 — Run

```bash
bash .agents/skills/release/scripts/release.sh <core|laravel> <x.y.z[-suffix]>
```

- Gates (pint → phpstan → pest) run by default; `--no-gates` is only for rehearsing on a WIP tree.
- `--dry-run` previews both edits without writing.

## Step 3 — Finish manually

The printed checklist covers what stays human: `bash scripts/smoke-install.sh`, review checklist sections 2–5 (compatibility, docs, license), commit, then tag with the suggested `{package}/{version}` form so subtree splits can key off it.

Completion criterion: version bumped + changelog promoted on a clean gated tree, and the smoke-install script has exited 0 before the tag is cut.
