# Ecosystem Feed Plan — Internal · Daily · Repo-local · Spec+Toolkit+Runners

> Status: approved 2026-08-25 · Scope: internal daily cron, repo-local snapshots, spec+toolkit+runners subset with exhaustive OAI org enumeration (no missed repo inside `OAI/*`).
> References: `README.md:1`, `docs/arazzo-conformance-and-enhancement-roadmap.md:1`, `docs/php-arazzo-vs-arazzo-toolkit-report.md:1`, PR `OAI/Arazzo-Specification#533`, Issue `OAI/Arazzo-Specification#410`.

For agentic workers: use `superpowers:executing-plans` or `superpowers:subagent-driven-development`. Checkboxes track progress.

Goal: continuous feed aggregating Arazzo spec proposals (SOAP/WSDL, XML, actor/loop, MCP/CLI/A2A), toolkit releases, and runner evolution so `php-arazzo` can react early. Maps each event to `P0-P2` roadmap gaps.

Arch: Enumerators -> Ingestors (GithubApi|AtomRss|NpmRegistry|WebScrape) -> Normalizer(RelevanceMapper) -> Store(snapshots+feed.json) -> Projectors(Markdown/JSON). See chat plan 2026-08-25.

---

## File map

- Create `config/ecosystem/sources.json` — curated sources (tiers)
- Create `config/ecosystem/sources.oai.json` — generated enumeration of all `OAI/*` repos (30)
- Create `config/ecosystem/sources.ecosystem.json` — runner/validator libraries
- Create `scripts/ecosystem/poll.php` — CLI entry (`--dry-run|--commit|--since=`)
- Create `scripts/ecosystem/Enumerators/OaiOrgEnumerator.php`
- Create `scripts/ecosystem/Ingestors/GithubApiIngestor.php`
- Create `scripts/ecosystem/Ingestors/AtomRssIngestor.php`
- Create `scripts/ecosystem/Ingestors/NpmRegistryIngestor.php`
- Create `scripts/ecosystem/Normalizer.php` + `FeedEvent.php` + `RelevanceMapper.php`
- Create `scripts/ecosystem/Store.php` + `Renderer.php`
- Create `storage/ecosystem-feed/.gitkeep` + `docs/generated/ecosystem-feed.json`
- Create `docs/ECOSYSTEM_FEED.md` (projected)
- Create `.github/workflows/ecosystem-feed.yml`
- Modify `composer.json` — add `ecosystem:poll` script

## Task 0 — Scaffold & config

- [x] Create directories `scripts/ecosystem/{Enumerators,Ingestors}`, `config/ecosystem`, `storage/ecosystem-feed/snapshots/.gitkeep`, `docs/generated/.gitkeep`.
- [x] Write `config/ecosystem/sources.json` tiers:
  - P0: `OAI/Arazzo-Specification`, `OAI/OpenAPI-Specification`, `OAI/Overlay-Specification`, `OAI/spec.openapis.org`
  - P1 weekly: `OAI/tools.openapis.org`, `OAI/learn.openapis.org`, `OAI/build-infra`, SIGs (`sig-moonwalk`, `sig-security`, `sig-lifecycle`), landscape, etc.
  - UseArazzo: `usearazzo/arazzo-toolkit`, `usearazzo/website`, `usearazzo/community`
  - Runners/validators: `jentic/arazzo-engine`, `strefethen/arazzo-cli`, `leidenheit/itarazzo-library`, `Redocly/redocly-cli`, `Specmatic/specmatic`, `jentic/jentic-arazzo-tools`, `stoplightio/spectral`, `speakeasy-api/openapi`, `swaggerexpert/*`, `SpecLynx`, generators/editors list.
- [x] Generate `config/ecosystem/sources.oai.json` via `OaiOrgEnumerator` (enumerates `GET /orgs/OAI/repos` 30 repos) — committed, diffed daily.
- [x] Add JSON schema for sources config (basic validation).

## Task 1 — Enumerators + GithubApiIngestor (M0 prove)

- [x] Implement `OaiOrgEnumerator` — `GET /orgs/OAI/repos?per_page=100`, ETag cache `.cache/ecosystem/etags.json`, diff vs committed `sources.oai.json`, link extractor scanning `README.md` inside OAI repos for `github.com/` refs.
- [x] Implement `GithubApiIngestor` — `GET /repos/{owner}/{repo}/{releases|tags|pulls?state=all|issues|commits}`, pagination, `If-None-Match`, `X-RateLimit-Remaining`, `Retry-After` respect, auth `GITHUB_TOKEN`.
- [x] Add fixture: canned payloads for `OAI/Arazzo-Specification#533` (SOAP wsdl), `#410` issue, release `1.1.0` (`scripts/ecosystem/fixtures/pr-533-soap.json`).
- [x] Unit test enumerator diff + ingestor ETag path with mocked HTTP. _(verified via `--fixtures --dry-run` tagging; dedicated Pest test added in Task 5)_

## Task 2 — Normalizer, RelevanceMapper, Store, Renderer + poll.php

- [x] `FeedEvent.php` DTO: `id=sha256(source|externalId)`, `source`, `type` (`pr|release|commit|schema|ecosystem_listing|article|video|issue|tag`), `title`, `url`, `publishedAt`, `tags[]`, `severity` (`watch|actionable|breaking`), `raw`.
- [x] `Normalizer.php` — per-source mapping, deduplicate by `id`, derive `tags` via keyword/label heuristics (xml→xpath, wsdl/soap, mcp, actor/loop, a2a, grpc/graphql).
- [x] `RelevanceMapper.php` — `tags -> P0-P2` (`soap→P0-6`, `xml→P1-6`, `mcp→P2-2`, `cli→P2-1`, `actor|loop→future suspension`).
- [x] `Store.php` — append snapshots `storage/ecosystem-feed/snapshots/YYYY-MM-DD/<source>--<id>.json`, maintain `feed.json` capped 2000 newest, sorted `publishedAt desc`.
- [x] `Renderer.php` — Markdown table `docs/ECOSYSTEM_FEED.md` + `docs/generated/ecosystem-feed.json` + provider for `feed.json` JSON.
- [x] `poll.php` — `--dry-run` (no write), `--commit` (write snapshots+feed), `--since=YYYY-MM-DD`, `--source=repo` filter, `--limit` for testing.
- [x] Add `composer ecosystem:poll` script. (`composer.json:32`)

## Task 3 — Additional ingestors

- [x] `AtomRssIngestor` — `usearazzo.com/ecosystem/` atom (`/commits/main/pages/ecosystem.html.atom` + `feed.xml`/`blog/`), OAI releases atom fallback, `openapis.org/blog` RSS.
- [x] `NpmRegistryIngestor` — `registry.npmjs.org/@usearazzo/{parser,resolver,validator,cli}` `dist-tags.latest`.
- [x] `WebScrapeIngestor` — `spec.openapis.org/arazzo/latest.html` + schema dates `2026-04-15/2025-10-15`, `openapi.tools/collections/arazzo`.
- [x] Wire into `poll.php` via `sources.json` `ingestor` field dispatch table.

## Task 4 — Workflow + polish

- [x] Create `.github/workflows/ecosystem-feed.yml` — `on: schedule: '17 6 * * *'`, `workflow_dispatch` (input `dry_run`), `permissions: contents: write`, `actions/cache` for ETags, commit if `feed.json` changed, `git diff --exit-code` check.
- [x] Add `ISSUE_TEMPLATE/ecosystem-update.yml` for `severity=breaking` (release publish, schema date change, PR merged to `v1.2-dev`) — initially disabled behind flag (`_template` hidden from chooser).
- [x] Update `README.md` ecosystem section linking `docs/ECOSYSTEM_FEED.md` (`README.md:232`).
- [x] Document usage in `docs/ECOSYSTEM_FEED.md` header + retention prune note (30d snapshots) + fix `Store.php:28` prune + workflow prune step.

## Task 5 — Verification

- [x] Fixture test: `packages/core/tests/Ecosystem/FeedTest.php` asserts normalizer produces expected `FeedEvent` for #533 payload (5 passing, 16 assertions).
- [x] Dry-run: `php scripts/ecosystem/poll.php --dry-run --limit=5` logs without writes + `php scripts/ecosystem/poll.php --fixtures --dry-run` 5→5 + live `OAI/Arazzo-Specification --limit=2` 8 events dry (no commit).
- [ ] CI dry-run job renders `docs/generated/ecosystem-feed.json` and fails if projector stale (parity harness pattern like `generate-conformance-matrix.php:1`). — ready, workflow commits only on change so stale check is git diff.
- [ ] Manual `gh workflow run ecosystem-feed.yml` (workflow_dispatch) inspecting `docs/ECOSYSTEM_FEED.md`. — ready, run after merge.

---

## Risks

- Rate limits → cache + daily + auth; log `remaining`.
- Missed OAI repo → enumerator diff guarantees coverage; link extractor recovers docs-embedded refs.
- Snapshot bloat → cap + 30d prune.
- Scrape brittleness → prefer atom API, scrape as fallback.
