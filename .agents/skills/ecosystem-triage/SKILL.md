---
name: ecosystem-triage
description: Analyze the stateful ecosystem feed events to produce prioritized tasks grouped by roadmap ref, correlated to local PRs/issues and recorded in decisions.json. Use when planning work from Arazzo spec/tooling changes, or when turning feed events into ready-for-agent tickets.
---

# Ecosystem Triage

Turns the **stateful feed events** (`storage/ecosystem-feed/state/events/*.jsonl`) plus the
**watchlist status table** (`state/watchlist-status.json`) and **`config/ecosystem/decisions.json`**
into a prioritized, de-duplicated task list **grouped by roadmap ref**.

It never reads the retired `storage/ecosystem-feed/feed.json` and never hard-codes titles or
blockers: a task's title comes from `config/ecosystem/topics.json` (`roadmaps`/`topics`) or the live
`watchlist-status.json` title; one roadmap ref is one task, with the source events as evidence.

Feed source: `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` via
`scripts/ecosystem/poll.php` (daily `17 6 * * *`); the transition events are written by the stateful
`Differ` (`scripts/ecosystem/Differ.php`) and scored by `TopicMatcher` + `Scorer` into
Act/Watch/Noise with `topics` and `roadmapRefs`.

## When to use

- After the daily feed workflow updates `docs/ECOSYSTEM_FEED.md` / `storage/ecosystem-feed/state/`
- When the digest shows Act/Breaking items and you need to decide what to build next
- Before opening a planning PR — to avoid duplicating work an explicit `ourIssue` already covers

## Run

```bash
php .agents/skills/ecosystem-triage/scripts/analyze.php [--since=YYYY-MM-DD] [--json] [--limit=20] [--verbose]
php .agents/skills/ecosystem-triage/scripts/analyze.php --mark-reviewed <id>
```

- `--since` filters `occurredAt >= date` (last 7 days: `--since=$(date -v-7d +%Y-%m-%d)` or `date -d '7 days ago' +%Y-%m-%d`)
- `--json` emits `{"tasks":[...],"prs":[...],"events":N,"groups":N,"decisions":N}`
- `--limit` caps tasks (default 20; one task per roadmap ref)
- `--verbose` prints the `gh` calls used for correlation
- `--events=PATH` / `--state=DIR` / `--watchlist-status=PATH` / `--decisions=PATH` override the defaults (used by tests)
- `--mark-reviewed <id>` records a decision (see below)

Events come from `storage/ecosystem-feed/state/events/*.jsonl`; local PRs are fetched with
`gh api repos/<owner>/<repo>/pulls?state=open` when `gh` is available (no `gh` → tasks still emit,
only correlation falls back to explicit links).

Output lands in `.scratch/ecosystem-triage/<YYYY-MM-DD>.md` and prints to stdout — **read it before
publishing tickets**.

## What the script does

1. **Gather events** — read the monthly `state/events/*.jsonl` files (transition events, not raw
   observations). Each event carries `tier` (`act|watch|noise`), `topics`, `roadmapRefs`, `score`
   and `breaking`.
2. **Drop settled items** — any event whose `entityId`/`id` is `reviewed`/`dismissed`/`linked` in
   `decisions.json` is removed, so handled items do not resurface.
3. **Group by roadmap ref** — one task per `roadmapRefs` entry (`plan:` / `spec:` / `upstream:`).
   Events with no ref land in an `unmapped:<topic|source>` bucket so they never collapse into a
   single mega-task.
4. **Title from authored config** — `watchlist-status.json` live title, else the `topics.json`
   `roadmaps[ref].title`, else the group's topic title, else a humanized ref. No hard-coded titles.
5. **Correlate** — explicit watchlist `ourIssue` links and `decisions.json` `linked` entries win;
   otherwise keyword overlap between the group's titles/topics and open local PRs, **excluding**
   the words `arazzo`, `spec`, `openapi` (they match nearly everything).
6. **Order** — breaking groups first, then highest `score`, then ref.

## Record outcomes — `--mark-reviewed`

```bash
php .agents/skills/ecosystem-triage/scripts/analyze.php --mark-reviewed gh:OAI/Arazzo-Specification#533
```

`<id>` is an event `id` (`evt_…`) or an entity id (`gh:owner/repo#N`). It appends a `reviewed`
decision to `config/ecosystem/decisions.json`; the item is then excluded from later runs. The
command is idempotent (marking the same item twice records one row). Use `--by=<actor>` to override
the recorder (defaults to `$GITHUB_ACTOR` or `human`). The same file records `dismissed` and
`linked: #123` outcomes; commit it with your PR so the decision is durable.

## Output shape

Markdown (default) — one section per roadmap-ref task:

```markdown
## 01 — Phase F — protocol packages

**Roadmap ref:** `plan:phase-f-protocol-packages`
**Severity:** actionable
**Related:** #151 (watchlist ourIssue)

**Source events (3):**
- `2026-10-14` feat(spec): add SOAP support — `OAI/Arazzo-Specification` · `pr.state_changed` https://github.com/OAI/Arazzo-Specification/pull/533
- …
```

JSON (`--json`) — the same under `tasks[]` for `to-tickets` publishing.

## Next step — publish

1. Review the `.scratch/ecosystem-triage/<date>.md` draft. Adjust granularity by editing
   `topics.json` / `watchlist.json` (add a roadmap ref or a title), **not** the script.
2. Confirm `Related` links — if an `ourIssue` already covers the task, add to that issue instead of
   opening a duplicate.
3. Publish via `/to-tickets` (local files `.scratch/ecosystem-triage/issues/NN-*.md`) or
   `gh issue create --label ecosystem,ready-for-agent`.
4. Record the outcome with `--mark-reviewed <id>` so the item stops resurfacing.

## Offline / CI note

Without `gh` or `GITHUB_TOKEN`, PR correlation is skipped but tasks still emit. When an item has an
explicit watchlist `ourIssue` or a `decisions.json` link, that correlation still shows. The daily
feed workflow sets `GH_TOKEN`; the weekly delivery workflow
(`.github/workflows/ecosystem-deliver.yml`) opens the `ecosystem-digest: YYYY-Www` issue and — only
when opted in (`config/ecosystem/delivery.json` `autoIssue` or a manual dispatch) — the per-Act
auto-issues this analysis mirrors.
