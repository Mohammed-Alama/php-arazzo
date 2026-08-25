---
name: ecosystem-triage
description: Analyze the ecosystem feed to produce prioritized tasks linked to local PRs. Use when planning work from Arazzo spec/tooling changes, turning feed events into ready-for-agent tickets.
---

# Ecosystem Triage

Turns `storage/ecosystem-feed/feed.json` into a prioritized, de-duplicated task list that is **anchored to local PRs/issues** — so you plan what the feed actually requires, not what it merely mentions.

Feed source: `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` (30 OAI repos) via `gh api` (`scripts/ecosystem/poll.php` daily `17 6 * * *`). Analysis correlates via `scripts/ecosystem/RelevanceMapper.php` (`soap→P0-6`, `xml→P1-6`, `mcp→P2-2`, etc.).

## When to use

- After `composer ecosystem:poll` or the daily workflow updates `docs/ECOSYSTEM_FEED.md`
- When `Relevance` column shows `breaking`/`actionable` and you need to decide what to build next
- Before opening a planning PR — to avoid duplicating an open `gh pr` that already covers the feed event

## Run

```bash
php .agents/skills/ecosystem-triage/scripts/analyze.php [--since=YYYY-MM-DD] [--json] [--limit=20] [--verbose]
```

- `--since` filters `publishedAt >= date` (e.g. last 7 days: `--since=$(date -v-7d +%Y-%m-%d)` or `date -d '7 days ago' +%Y-%m-%d`)
- `--json` emits machine-readable `{"tasks":[...],"prs":[...]}` for `to-tickets` or CI
- `--limit` caps tasks (default 20, feed is deduped by `id = sha256(source|externalId)`)
- `--verbose` prints correlation hits per local PR
- Reads `storage/ecosystem-feed/feed.json` + snapshots for `raw.body`; runs `gh pr list --state open --json number,title,body,labels,url` and `gh issue list --state open` when `gh` is available (no `gh` → still produces tasks, just without correlation)

Output lands in `.scratch/ecosystem-triage/<YYYY-MM-DD>.md` and prints to stdout — **read it before publishing tickets**.

## What the script does

1. **Gather feed** — newest first, `publishedAt` desc, capped 2000 (`scripts/ecosystem/Store.php`). Each `FeedEvent` carries `tags[]`, `severity` (`watch|actionable|breaking`), `relevance` (`RelevanceMapper`).
2. **Gather local** — `gh pr list --state open --limit 100` + `gh issue list --state open` (if `gh` auth present). Falls back to empty when offline.
3. **Correlate** — keyword overlap between feed `tags+title+body` and local `title+body+labels` (case-insensitive, normalized). Score ≥ 2 keywords or explicit `Relevance` substring → `related_pr: #123` link. Feed events with same `relevance` are grouped (e.g. all `P0-6 source routing (wsdl)` coalesce to one task).
4. **Draft tasks** — vertical slices, not one-per-event:

   - **Title:** behaviour, not feed headline (e.g. `Support wsdl sourceDescription type + operationId reuse` not `PR #533`)
   - **Blocked by:** computed from `RelevanceMapper` tiers — `P0` before `P1` before `P2`; `breaking` before `actionable`; `wsdl` blocks `soap Fault detection`
   - **Related PRs:** `gh pr #123` with URL, or `None — new work`
   - **Source events:** bullet list of feed URLs that motivate the task (so you can verify)
   - **Acceptance:** concrete, testable — `gh api repos/OAI/Arazzo-Specification/pulls/533` renders `wsdl` type, `vendor/bin/pest` green, `docs/ECOSYSTEM_FEED.md` relevance cleared

## Output shape

Markdown (default) — one section per task:

```markdown
## 01 — Support wsdl source type (P0-6) — breaking

**Severity:** breaking | **Relevance:** P0-6 source routing (wsdl type)
**Blocked by:** None — can start immediately
**Related PRs:** None — new work (closest: #42 chore: bump speclynx — no overlap)
**Source events:**
- feat(spec): add SOAP support — OAI/Arazzo-Specification#533 https://github.com/OAI/Arazzo-Specification/pull/533 — tags: soap,wsdl,breaking
- Arazzo 1.1.0 — tag 1.1.0 — tags: xml,xpath

**What to build:** step `sourceDescription.type=wsdl` resolves WSDL 1.1/2.0, `operationId` maps to `wsdl:operation/@name`, `MUST NOT operationPath` enforced.

**Acceptance:**
- [ ] gh api shows wsdl type accepted, openapi-or-wsdl-step-object validated
- [ ] packages/core/tests/Parser/WsdlSourceTest.php green
- [ ] Related feed events marked reviewed in .scratch file

**Out of scope:** SOAP Fault body handling (next task)
```

JSON (`--json`) — same structure under `tasks[]` for `to-tickets` publishing.

## Next step — publish

1. Review the `.scratch/ecosystem-triage/<date>.md` draft — adjust granularity (too coarse = split by `soap` vs `wsdl`; too fine = merge `xml+xpath`).
2. Confirm `Blocked by` edges — `P0` tasks should not block on `P2` (`mcp` never blocks `soap`).
3. Confirm `Related PRs` — if a feed event already has an open PR, retitle that PR's remaining work instead of opening a duplicate.
4. Publish via `/to-tickets` (local files `.scratch/ecosystem-triage/issues/NN-*.md`) or `gh issue create` with label `ecosystem` + `ready-for-agent`.

Completion criterion: `.scratch` file reviewed, blocking edges make sense against `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`, and no open PR duplicates the proposed work (checked via `gh pr list` correlation in the output).

## Offline / CI note

Without `gh` or `GITHUB_TOKEN`, correlation is skipped but tasks still emit — `Related PRs: (gh not available — run with auth to correlate)`. The daily workflow sets `GH_TOKEN` so correlation works there.

