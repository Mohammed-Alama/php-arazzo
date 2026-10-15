# Ecosystem Feed — Signal Redesign (Spec + Implementation Plan)

> **Status:** Proposed · **Date:** 2026-10-14 · **Revision 1 (2026-10-14):** D1 decided — state is committed to `main` (daily bot commits accepted)
> **Supersedes (in part):** `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md` — the pipeline shape
> (Ingestors → Normalizer → Store → Renderer) stays; **state, event model, relevance and outputs change**.
> **Evidence base (direct reads):** `scripts/ecosystem/*` (all PHP), `config/ecosystem/sources.json` (first ~100 KB;
> tail truncated), `.github/workflows/ecosystem-feed.yml`, `.github/ISSUE_TEMPLATE/ecosystem-update.yml`, `.gitignore`,
> `docs/ECOSYSTEM_FEED.md` (generated 2026-10-13T14:08Z), `.agents/skills/ecosystem-triage/*`,
> `.scratch/ecosystem-triage/2026-08-26.md`, `docs/arazzo-conformance-and-enhancement-roadmap.md`,
> `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`, `packages/core/tests/Ecosystem/FeedTest.php`.
> **Not read / not verifiable from here:** remote CI run logs, the live GitHub API, `composer.json` scripts,
> `sources.oai.json`, the truncated tail of `sources.json`.
>
> Confidence markers follow `.agents/rules/epistemic.md`:
> **[T2]** established by direct code/doc read · **[T3]** inferred from evidence (a verification command is given) · **[T4]** speculative.

---

## 1. Summary

The feed currently answers *"what are the latest ~20 items in each of 54 repos, as of today?"*
It needs to answer *"what **changed** upstream since I last looked, and does it touch something on our roadmap?"*

Three root causes, in order of impact:

1. **No memory between runs.** The feed is (very likely) regenerated from scratch on every CI run, so it cannot distinguish new from old (F1).
   Every historical tag looks brand-new and is stamped with the poll date (F2).
2. **Observations, not changes.** An item is recorded once, at first sight, and never updated (F4). The signals you actually want —
   *PR #533 went from open → ready → merged*, *a PR was retargeted to `main`*, *schema.yaml changed on `v1.2-dev`* — are never emitted.
3. **Relevance is substring matching on whole bodies and URLs** (F5, F6), mapped to roadmap IDs that have drifted from the real roadmap (F7).
   Dependabot PRs, `refactor:` commits and bot commits therefore land in "breaking" and in roadmap buckets they don't belong to.

The fix is a **stateful, transition-based, topic-scored feed** with a **watchlist of tracked upstream items** and a **bounded digest**
as the primary output. Phase 0 (hotfix) removes most of the noise without any new architecture.

---

## 2. Goals / Non-goals

**Goals** — the feed must reliably answer, with evidence and links:

| # | Question | Today |
|---|---|---|
| Q1 | What changed upstream since the last digest that matters to php-arazzo? | ❌ cannot tell new from old |
| Q2 | Which tracked upstream proposals (SOAP #533, protobuf #556, GraphQL #567, actor-in-the-loop #568, #410…) moved, and where are they now? | ❌ done by hand in `docs/research/2026-09-08-…` |
| Q3 | Did the spec text / JSON Schema change on `main` / `dev` / `v1.2-dev` — and which files? | ❌ configured (`watch_files`) but not implemented (F8) |
| Q4 | Which peer implementations shipped or fixed something we lack (parity / free conformance test ideas)? | ⚠️ buried in noise |
| Q5 | For each roadmap item: what upstream signals exist, and what did we decide? | ❌ no decision persistence (F10) |

**Non-goals:** no LLM in the polling path (deterministic, cheap, testable); no scraping of sites that have an API/feed;
no change to the public `alama/*` packages; no replacement of GitHub as the system of record for our own work.

---

## 3. Findings (evidence)

| ID | Finding | Tier | Evidence |
|---|---|---|---|
| **F1** | **CI is stateless; the committed feed is just *one run's fetch window*.** `.gitignore` ends with `/storage/ecosystem-feed`, which (last-match-wins) re-ignores the directory the earlier `!storage/ecosystem-feed/**` lines un-ignored. The workflow's `git add storage/ecosystem-feed/feed.json …` then fails for those paths, and `|| true` hides it. `docs/ECOSYSTEM_FEED.md` is still staged, so it is committed. `actions/cache` only restores `.cache/ecosystem` (ETags), not the feed. Corroboration: after your `git pull` this morning `docs/ECOSYSTEM_FEED.md` exists locally but `storage/ecosystem-feed/feed.json` does not. | **T3** | `.gitignore` (last 2 ecosystem rules); `ecosystem-feed.yml` "Commit feed if changed" step; `Store::loadExisting()`; local dir listing. **Verify:** `git check-ignore -v storage/ecosystem-feed/feed.json` and `git ls-files storage/ecosystem-feed` (expect: ignored / empty). |
| **F2** | **Tag events are fabricated "now" events.** `GithubApiIngestor::map('tags')` sets `publishedAt => gmdate('c')` ("tags lack timestamp; use now but dedup via id") — the dedup assumed persisted state (F1). With 20 tags/repo/run, *every* poll re-announces old tags as new. By my count of the rendered table, **~144 of the "newest 200" rows are `tag` events all dated 2026-10-13**. The tags API is also not ordered by date: Redocly shows `v1.0.0-beta.116…v1.0.0` while its releases are at `2.63.0`; Specmatic shows `v0.0.1` among `2.55.x`. | T2 (code, count) · T3 (ordering) | `GithubApiIngestor.php` `'tags' =>` arm; `ECOSYSTEM_FEED.md` "All events" table. |
| **F3** | **Release + tag double-count.** Same version appears as `release:<tag>` and `tag:<tag>` (different ids). e.g. `OAI/Arazzo-Specification` 1.1.0: release dated 2026-05-18 *and* tag dated 2026-10-13. Monorepos multiply it: Redocly emits 6 `@redocly/*` releases per version; `speclynx/apidom` ~1 release/tag per day. | T2 | Feed rows; `sources.json` endpoints `["releases","tags"]` on the same repos. |
| **F4** | **State changes are never emitted.** `Store::commit` inserts only `if (!isset($byId[$ev->id]))`, and `id = sha256(source|externalId)` has no state in it. A PR that is later merged/closed/retargeted, or an issue closed/relabeled, never produces an event; `Normalizer`'s "merged ⇒ actionable" rule can only fire if the PR was *already merged* the first time it was seen. | T2 | `Store.php`; `FeedEvent::makeId`; `Normalizer` severity block. |
| **F5** | **Keyword tagging is substring matching over title + *body* + labels + **URL**.** Consequences visible in the data: `actor` matches inside `refactor` and `extractor` → `refactor: extract framework-agnostic engine into arazzo-core` and `bump @microsoft/api-extractor` are tagged `actor` ("Issue #410 human-in-loop"); the repo name `arazzo-cli` is in every URL, so (nearly) all `strefethen/arazzo-cli` events get `cli` ("P2-1 CLI binary": 92); Dependabot bodies embed upstream release notes, so `actions/checkout 6→7` is **breaking** and a `highlight.js` bump was tagged `soap,breaking` (2026-08-26 triage). `a2a` is hex-valid and **may** be matching commit SHAs in bodies (the `a2a` tag on `respec`/`prettier`/`jekyll` bumps is otherwise unexplained). | T2 (mechanism) · T3 (`a2a`/SHA) | `Normalizer.php` `$haystack`, `str_contains`; feed rows; `.scratch/ecosystem-triage/2026-08-26.md` task 02. |
| **F6** | **Relevance is a catch-all.** Every event from the allow-listed repos gets `spec`, which maps to "Conformance / schema validation" → **340 of 883** events; 154 are `uncategorized`; **507 are `actionable`** (any release/tag). Severity has no notion of *whether the change is about Arazzo*. | T2 | `Normalizer` source allow-list; `RelevanceMapper` priority; summary block of the dashboard. |
| **F7** | **Roadmap IDs drifted.** `RelevanceMapper` says `P0-6 = wsdl source routing`; the roadmap's `P0-6` is *Swagger 2.0 / OpenAPI 3.1 routing*. `P1-7 = JSON Schema layer` in the mapper; in the roadmap it is `P1-8` (P1-7 = validator closure). SOAP/WSDL, protobuf RPC and GraphQL are not in the August roadmap at all — they live in the September design (`2026-09-08-plugin-stack-oms-multiprotocol-design.md`, phase F). Matching IDs: P0-5, P1-6 (partial), P2-1, P2-2. | T2 | `RelevanceMapper::MAP` vs roadmap §4. |
| **F8** | **Highest-value signals are configured but not implemented.** `sources.json` gives `OAI/Arazzo-Specification` `branches: [main, v1.2-dev]` and `watch_files`, but `GithubApiIngestor::poll()` takes neither. The only spec-text signal is a whole-page checksum (`spec.arazzo.html`, schema checksums) that fires on any byte change and carries no diff. `watch_files` also lists `versions/*.md` while the Sept research doc cites `src/arazzo.md` and `src/schemas/validation/schema.yaml` — the paths need confirming. NPM ingestor reads only `dist-tags.latest`. | T2 (code) · T3 (paths) | `poll.php` call to `GithubApiIngestor::poll`; `WebScrapeIngestor.php`; `NpmRegistryIngestor.php`. |
| **F9** | **Volume sources swamp the signal.** `jentic/jentic-public-apis` commits ("Rebuild apis.json…", "feat: Import OpenAPI spec from Issue #…") fill most of the Watch/uncategorized rows; `OAI/build-infra`, `learn.openapis.org`, `spec.openapis.org` are mostly Dependabot; `OAI/Arazzo-Specification` contributes `vN-dev: sync with dev` PRs and `Meeting:` issues. **Our own repo** (`Mohammed-Alama/php-arazzo` PRs #6, #9, #51–#75) is in the feed (PR #72 is even listed under "breaking"). | T2 | Dashboard rows. |
| **F10** | **Triage can't persist decisions and is partly hard-coded.** `analyze.php` groups by relevance (so F6's 340-event bucket becomes one giant task), hard-codes titles/acceptance for wsdl/xpath/mcp/cli, hard-codes `Blocked by: 01 — Support wsdl source type`, correlates to local PRs by keyword overlap that includes the word `arazzo` (matches everything), and never records *reviewed/dismissed/linked*. The `ecosystem-update` issue template says automation creates issues — nothing does. | T2 | `analyze.php`; `SKILL.md`; issue template text. |
| **F11** | **Store writes a snapshot file for every event on every run** (inside the loop, not only for new ones), keyed by `publishedAt` date (= poll date for tags); cap 2000 by `publishedAt` with no `firstSeenAt` means evicted events can later re-enter as "new". | T2 | `Store::commit`. |
| **F12** | **`poll: weekly` and `tier` are decorative** — `poll.php` polls every source every run and never reads `poll`/`tier`. | T2 | `poll.php` source loop. |

**What works and must be kept:** the ingestor/normalizer/store/renderer separation; `gh` with curl fallback; fixture-driven tests
(`FeedTest.php`); OAI org enumeration with committed diff; the `ecosystem-triage` skill's intent (anchoring tasks to local work).

---

## 4. Design principles

1. **Stateful.** Persist what was seen; emit only what is new or *changed*.
2. **Events are transitions.** `PR #533: open → ready`, not "PR #533 exists".
3. **Relevance is authored data, not code.** Topics → roadmap refs live in a reviewed config file, keyed to *current* plan/spec docs.
4. **Gate before display.** Score and suppress; show counts of suppressed noise instead of the noise.
5. **Deterministic poll, optional LLM later.** The poller stays pure PHP; summarization/triage assistance sits downstream.
6. **Evidence-first output.** Every surfaced item carries link + what changed (from/to, files, versions), never just a title.

---

## 5. Target design

### 5.1 Pipeline

```
sources.json ─┐
watchlist.json├─▶ Ingestors ─▶ Observations ─▶ Differ (vs State) ─▶ Events ─▶ Scorer (topics.json) ─▶ Outputs
topics.json  ─┘     (REST/GraphQL/atom/npm)      (entity fingerprints)  (transitions)   (Act/Watch/Noise)   digest · issues · board
                                     ▲                      │
                                     └──── State (watermarks, entities, decisions) ◀────┘
```

### 5.2 State (fixes F1, F11, F12)

Files (JSON, deterministic key order, small diffs):

| File | Content |
|---|---|
| `state/watermarks.json` | per source+endpoint: `lastPolledAt`, `lastSeenUpdatedAt`/`lastSha`/`lastTag`; per spec branch: `headSha` |
| `state/entities.jsonl` | one line per tracked entity: `entityId`, `kind`, `fingerprint` (hash of the fields we care about), `fields` (state, isDraft, base, labels, version…), `firstSeenAt`, `lastChangedAt` |
| `state/events.jsonl` | append-only emitted events (rolled up monthly) |
| `decisions.json` (config, committed on main) | per `entityId` or `roadmapRef`: `reviewed`/`dismissed`/`linked: #NNN`, `note`, `by`, `at` |

**Where state lives — Decision D1 (decided 2026-10-14): on `main`, committed by the daily bot.**

State and digest are plain files under `storage/ecosystem-feed/` and `docs/ECOSYSTEM_FEED.md`, committed by `github-actions[bot]` to `main` as `chore(ecosystem): update feed YYYY-MM-DD` (the commit style the workflow already uses). Rejected alternatives: a separate `ecosystem-data` branch (extra checkout, state hidden from normal review) and `actions/cache` (evictable ⇒ silent re-baseline).

Because the bot commits daily, the design must keep those commits **small, quiet and conflict-free**:

| Guardrail | Why / how |
|---|---|
| **Fix the ignore rule** | Delete the trailing `/storage/ecosystem-feed` line in `.gitignore` (F1). Keep ignoring raw snapshots with `/storage/ecosystem-feed/snapshots/` — they are not state (§5.4). |
| **Commit only on real change** | The existing `git diff --cached --quiet` check stays. State must be **deterministic**: sorted keys/rows, no per-run timestamps. |
| **No always-changing fields** | Don't store `lastPolledAt` per run for daily sources; store a watermark only when it moved (`lastSeenUpdatedAt`, `headSha`). For `weekly` sources store the *date* of the last poll. The digest's "Generated" timestamp is written only when the digest body changed — today it changes on every run, which forces a commit every day even when nothing happened. |
| **Bounded growth** | `events` roll monthly (`state/events/2026-10.jsonl`); entities for closed/merged items older than N days compact to a tombstone `{entityId, fingerprint, closedAt}`; no raw payloads in git. |
| **CI does not run on bot commits** | `ci.yml` on `push` to `main` is path-filtered to `**.php` and composer/phpstan/pint/phpmd/phpdoc config files, so `.json`/`.jsonl`/`.md` state commits don't trigger it **[T2: read `ci.yml`]**. Changes to `scripts/ecosystem/*.php` still do, as they should. No `[skip ci]` needed. |
| **Push races** | The workflow already has `concurrency: ecosystem-feed`; add `git pull --rebase origin main` before `git push` and retry once. Locally: `git pull --rebase` before pushing — your `.githooks/pre-push` gates pushes to `main`, so a non-fast-forward after a bot commit is the usual cause of a rejected push. Avoid running `poll.php --commit` locally. |
| **Branch protection** | If `main` requires PRs or status checks, the default `GITHUB_TOKEN` push is rejected. **Verify** under repo Settings → Branches. If protected: allow `github-actions[bot]` to bypass, or push with a GitHub App / fine-grained token. **[T3: protection status not visible from here]** |
| **Readable history** | Keep the `chore(ecosystem):` prefix; browse code history with `git log --invert-grep --grep='^chore(ecosystem)'`. |

`decisions.json`, `watchlist.json`, `topics.json` and `sources.json` are **human-authored config** edited via normal commits; the bot never writes them. Exception: `sources.oai.json`, generated by the enumerator as today. (`poll.php` currently also auto-writes into `sources.json` on `--commit`, but the workflow never `git add`s that file, so those edits are lost — remove that write.)

### 5.3 Event model v2 (fixes F2, F3, F4)

```json
{
  "id": "evt_<sha256(entityId|kind|toValue)[:16]>",
  "entityId": "gh:OAI/Arazzo-Specification#533",
  "kind": "pr.state_changed",
  "source": "OAI/Arazzo-Specification",
  "occurredAt": "2026-10-20T09:12:00Z",
  "observedAt": "2026-10-21T06:17:10Z",
  "subject": { "type": "pr", "title": "feat(spec): add SOAP support", "url": "https://github.com/OAI/Arazzo-Specification/pull/533" },
  "change": { "field": "state", "from": "open", "to": "merged", "base": "v1.2-dev" },
  "topics": ["wsdl"],
  "roadmapRefs": ["spec:plugin-stack-oms-multiprotocol", "plan:phase-f-protocol-packages"],
  "tier": "act",
  "score": 87,
  "why": ["watchlist", "spec-repo", "merged-to-dev-branch"]
}
```

`kind` vocabulary: `entity.created`, `pr.state_changed` (open/draft/ready/merged/closed), `pr.retargeted`, `pr.labels_changed`,
`issue.state_changed`, `release.published`, `release.prerelease`, `package.version` (npm), `spec.text_changed`,
`schema.changed`, `meeting.agenda`. **`occurredAt` is always a provider timestamp; no event is created without one** (tags without a date are resolved or dropped).

### 5.4 Ingestion changes

| Area | Change |
|---|---|
| **Baseline** | `poll.php --baseline`: record entities + watermarks, emit **zero** events. Automatic when state is empty. Prevents any "everything is new" flood. |
| **Releases** | Authoritative version signal. Skip `draft`; mark `prerelease`; collapse monorepo releases by `(repo, version)` into one event with `packages[]` (Redocly ×6 → 1). Stop paging at the stored `lastSeenTag`. |
| **Tags** | Only for repos with **no** GitHub Releases. Resolve a real date via GraphQL `refs(refPrefix:"refs/tags/", orderBy:{field:TAG_COMMIT_DATE, direction:DESC}, first:N){ target{ ... on Commit{committedDate} ... on Tag{tagger{date}} } }`. Never emit tag events for a version that already has a release. |
| **PRs / issues** | REST list sorted by `updated` desc with **early stop at `lastSeenUpdatedAt`**; issues also use `since=`. Diff against `entities.jsonl` fingerprint `{state, draft, merged, base, labels}`. Watchlist items additionally fetch detail (`files`, review decision, `mergeable`, comments count). |
| **Spec text** (`OAI/Arazzo-Specification`, branches `main`, `dev`, `v1.2-dev`, `v1.1-dev`) | Store `headSha` per branch; next run `GET compare/{lastSha}...{branch}`; emit `spec.text_changed` listing changed files filtered by **confirmed** paths (`src/arazzo.md`, `src/schemas/validation/schema.yaml`, `versions/*.md`, `examples/**`), commit titles, and +/− line counts. Replaces checksum-only detection (keep schema-iteration checksums as a cheap secondary signal). |
| **npm** | Read `time` map; emit `package.version` for versions newer than the watermark (not only `dist-tags.latest`). |
| **Cadence** | Honor `poll: daily|weekly` using `lastPolledAt`. |
| **Snapshots** | Write raw payload snapshot **only for new events**; short retention (14 d) or drop — state + events are the record. |

### 5.5 Watchlist — tracked upstream items (answers Q2, Q5)

`config/ecosystem/watchlist.json` (authored; seeded from the 2026-09-08 research):

```json
{
  "items": [
    { "ref": "OAI/Arazzo-Specification#533", "topic": "wsdl",     "roadmap": ["spec:plugin-stack-oms-multiprotocol", "plan:phase-f-protocol-packages"], "ourIssue": null, "stance": "plan-to-support" },
    { "ref": "OAI/Arazzo-Specification#556", "topic": "protobuf", "roadmap": ["plan:phase-f-protocol-packages"], "ourIssue": null, "stance": "plan-to-support" },
    { "ref": "OAI/Arazzo-Specification#567", "topic": "graphql",  "roadmap": ["plan:phase-f-protocol-packages"], "ourIssue": null, "stance": "plan-to-support" },
    { "ref": "OAI/Arazzo-Specification#568", "topic": "actor-in-loop", "roadmap": [], "ourIssue": null, "stance": "evaluate" },
    { "ref": "OAI/Arazzo-Specification#410", "topic": "1.2-discussion", "roadmap": [], "stance": "watch" },
    { "ref": "OAI/Arazzo-Specification#590", "topic": "retry-outputs", "roadmap": ["roadmap:P1-1"], "stance": "watch" },
    { "ref": "OAI/Arazzo-Specification#516", "topic": "criterion-regex", "roadmap": ["roadmap:P0-4"], "stance": "watch" }
  ]
}
```

*Seed note:* #533/#556/#567 are from your research doc; #568 and #410/#590/#516 were seen in the 2026-10-13 feed; the research doc names four PRs but its
fourth was not in the portion read — **confirm the seed list and `stance` values** before enabling.

Per run, each item yields: current state, draft/ready, base branch, review/merge status, changed-file list, label set, last activity, and a
**transition event** when any tracked field changes. The digest renders them as a status table (§5.7). Adding an item is a one-line PR; removing it records a decision.

### 5.6 Topics, scoring and tiers (fixes F5–F7, F9)

`config/ecosystem/topics.json` replaces the hard-coded `keywordMap`/`RelevanceMapper`:

```json
{
  "topics": {
    "wsdl":     { "match": ["\\bwsdl\\b", "\\bsoap\\b"], "scope": ["OAI/Arazzo-Specification", "OAI/sig-*"], "roadmap": ["plan:phase-f-protocol-packages"], "weight": 5 },
    "xpath":    { "match": ["\\bxpath\\b"], "roadmap": ["roadmap:P0-5", "roadmap:P1-6"], "weight": 4 },
    "json-schema": { "match": ["json[- ]?schema"], "roadmap": ["roadmap:P1-8"], "weight": 3 },
    "cli":      { "match": ["\\barazzo[- ]cli\\b", "\\bcommand line\\b"], "roadmap": ["roadmap:P2-1"], "weight": 2 },
    "mcp":      { "match": ["\\bmcp\\b", "model context protocol"], "roadmap": ["roadmap:P2-2"], "weight": 3 },
    "actor-in-loop": { "match": ["human[- ]in[- ]the[- ]loop", "actor[- ]in[- ]the[- ]loop"], "weight": 3 }
  }
}
```

**Matching rules:** word-boundary regex on **title + labels (+ first 500 chars of body for non-bot authors)**; never the URL; never the body of Dependabot/bot items;
`A2A` etc. require uppercase or word-boundary. Roadmap refs use stable keys (`roadmap:P0-5`, `plan:<file-stem>`, `spec:<file-stem>`, `upstream:<ref>`) so they survive renumbering;
a CI check fails if a ref does not resolve to a heading/file.

**Scoring:** `score = sourceWeight × kindWeight × topicWeight × transitionWeight (+ watchlist bonus)`.

| Tier | Rule of thumb | Surface |
|---|---|---|
| **Act** | watchlist transition; `spec.text_changed`/`schema.changed` on a spec branch; spec-repo release; **semver-major or minor** release of a *tracked* Arazzo library (oracles: `swaggerexpert/*`; runners: arazzo-cli, jentic, Redocly Respect, usearazzo toolkit); PR merged into a spec dev branch touching schema/spec text | digest top, weekly issue, optional auto-issue |
| **Watch** | topic-matched issues/PRs on spec & peer repos; peer bug reports that read like conformance test cases (e.g. toolkit#129 *"embedded runtime expressions in requestBody payloads are sent verbatim"*); `Meeting:` agenda issues (what's about to be decided) | digest, collapsed |
| **Noise** | bot/Dependabot, `vN-dev: sync with dev`, `jentic-public-apis` bulk commits, docs/typo/CI-only, prerelease patch tags, our own repo | **counted, not listed** |

**`breaking` is retired as a keyword tag.** It becomes a *derived* flag on Act items only: spec-repo release/merge touching schema, or semver-major of a tracked library, or an explicit upstream deprecation.

### 5.7 Outputs

1. **`docs/ECOSYSTEM_FEED.md` → bounded digest** (same path; README link keeps working): *Since last digest*, **Act** (≤ 15), **Watchlist status table**
   (ref · title · state · base branch · last change · our roadmap ref · our issue · stance), **Peer releases** (collapsed per library), **Meeting agenda**, and a one-line
   *"suppressed: N bot PRs, M bulk commits, K prereleases"*. Never a 200-row table.
2. **Weekly roll-up issue** `ecosystem-digest: YYYY-Www` (label `ecosystem-digest`) — the thing you read; links to Act items.
3. **Auto-issue per Act event (opt-in flag, default off)** using the existing `ecosystem-update.yml` form; dedup by `entityId`; labels `ecosystem`, `needs-triage`.
4. **GitHub Project hook** (ties to org task management): `gh project item-add` for Act issues into the org Project, with a custom field **Upstream ref**
   and Status `Needs triage`. Optional; no effect on the pipeline if the project is absent.
5. **JSON** `events.jsonl` + `watchlist-status.json` for the triage skill and any future UI.

### 5.8 Triage skill (`ecosystem-triage`) rewrite

- Input: new events + watchlist status + `decisions.json` (not the raw feed).
- Group by **roadmap ref**, not by relevance string; one task per ref with source events as evidence.
- Remove hard-coded wsdl/xpath/cli titles and acceptance; titles come from `topics.json`/`watchlist.json` (`stance`, `roadmap`).
- `Related PRs/issues` from explicit `ourIssue` links + `decisions.json`, falling back to keyword overlap **excluding** the words `arazzo`, `spec`, `openapi`.
- Record outcomes (`reviewed` / `dismissed` / `linked: #123`) so items don't resurface; add `--mark-reviewed <id>`.

---

## 6. Implementation plan

> Effort figures are my **estimates** (T4), assuming one engineer familiar with the code. Each phase ships independently and leaves the feed usable.

### Phase 0 — Hotfix: stop the bleeding (≈ ½–1 day)

- [ ] **P0.1 Persistence (F1, D1):** delete the trailing `/storage/ecosystem-feed` line in `.gitignore` and ignore `/storage/ecosystem-feed/snapshots/` instead; in `ecosystem-feed.yml` delete `|| true` from `git add` (so a failed add fails the job), drop `storage/ecosystem-feed/snapshots` from the add list, add a post-step assertion that `git ls-files storage/ecosystem-feed/feed.json` is non-empty, and add `git pull --rebase origin main` + one retry before `git push`; stop `poll.php` auto-writing `sources.json`.
- [ ] **P0.2 Tags (F2/F3):** stop emitting tag events that have no real date; drop `tags` from repos that also use `releases`; keep tags only where a repo has no Releases (pyarazzo, arazzo2openapi, Arazzo-Generator — **verify**).
- [ ] **P0.3 Baseline guard:** `--baseline` flag; auto-baseline when `feed.json` is absent/empty.
- [ ] **P0.4 Normalizer (F5):** word-boundary regex; remove `$url` from the haystack; skip body matching for `depbump`/bot items; `breaking` only for spec-repo events or explicit labels; fix `actor`/`a2a`/`cli`.
- [ ] **P0.5 Noise sources (F9):** `jentic/jentic-public-apis` → `endpoints: []` (or weekly count); exclude our own repo (`Mohammed-Alama/php-arazzo`) from the digest — **find where it enters the feed** (not in the part of `sources.json` read; may be in the tail or an enumerator).
- [ ] **P0.6 RelevanceMapper IDs (F7):** correct to the real roadmap (P0-6 ↛ wsdl; JSON-Schema layer → P1-8); add a `wsdl/protobuf/graphql → plan:phase-f-protocol-packages` interim mapping.
- [ ] **P0.7 Store (F11):** write snapshots only for new events.
- **Done when:** the regression fixtures in Appendix B classify as listed; a second back-to-back run reports `written 0 new`; the dashboard's "newest 200" contains no synthetic-date tag rows.

### Phase 1 — State & transitions (≈ 2 days)

- [ ] `State` class (watermarks, entities, events, decisions loader) + schema validation; deterministic JSON writer.
- [ ] `Differ`: observation → fingerprint compare → transition events (§5.3). Replace `Store::commit` insert-only logic.
- [ ] Release collapsing (monorepo) and release-over-tag precedence.
- [ ] GraphQL tag-date resolver for no-Releases repos; early-stop paging with watermarks; honor `poll: weekly`.
- [ ] Workflow: commit state to `main` per the D1 guardrails (§5.2) — rebase-before-push, no per-run timestamps, monthly event files, compaction of closed entities; cache only ETags.
- **Done when:** A1–A4 (§7) pass; CI log shows `loaded N entities` on the 2nd scheduled run.

### Phase 2 — Watchlist + spec-text diffing (≈ 2 days)

- [ ] `watchlist.json` + `WatchlistIngestor` (detail fetch incl. changed files, draft, base, review state).
- [ ] `SpecBranchIngestor` (`compare` API on stored `headSha`; confirm real paths first — F8).
- [ ] `npm` full-version ingestion via `time` map.
- [ ] Seed watchlist from the Sept research; add `meeting.agenda` kind for `Meeting:` issues.
- **Done when:** a recorded fixture of PR #533 `open → ready → merged` yields exactly the expected transition events; a `v1.2-dev` commit touching `schema.yaml` yields one `schema.changed` with file + line counts.

### Phase 3 — Topics, scoring, digest (≈ 2 days)

- [ ] `topics.json` + `TopicMatcher` (word-boundary, scope rules); `Scorer` (Act/Watch/Noise); ref-resolution CI check.
- [ ] New `Renderer` → bounded digest (§5.7.1); keep the file path.
- [ ] Update `FeedTest.php` (it currently asserts `watch` for #533 and `breaking` for #410 — both encode the old semantics).
- **Done when:** digest for a replay of the last 30 days has ≤ 15 Act items and every suppressed category is counted.

### Phase 4 — Delivery & triage (≈ 1–2 days)

- [ ] Weekly digest issue job (`schedule` weekly + `workflow_dispatch`).
- [ ] Opt-in auto-issue (dedup by `entityId`), reuse `ecosystem-update.yml`.
- [ ] Optional `gh project item-add` + **Upstream ref** field.
- [ ] Rewrite `ecosystem-triage` per §5.8; add `--mark-reviewed`.
- **Done when:** a simulated Act event creates exactly one issue (re-run creates none) and appears in the weekly issue.

### Phase 5 — Optional / later

- [ ] Peer **capability matrix** (who supports xpath / wsdl / mcp / loops / asyncapi) derived from peer release notes by topic.
- [ ] Optional LLM summarizer *downstream* of Act items (never in the poll path) writing a 3-line "impact on php-arazzo".
- [ ] Migrate remaining REST list calls to a single GraphQL query per repo if API budget becomes a concern.

---

## 7. Acceptance criteria & tests

Tests live with the existing suite (`packages/core/tests/Ecosystem/`) using recorded payload fixtures in `scripts/ecosystem/fixtures/`.

| ID | Criterion | How |
|---|---|---|
| **A1** | **Idempotent:** running the poll twice on the same fixtures yields 0 new events on run 2. | Pest: run `Differ` twice against a temp state dir |
| **A2** | **Baseline silent:** first run on empty state emits 0 events and writes watermarks. | Pest |
| **A3** | **Transitions:** PR fixture `open → draft→ready → merged` emits one event per change with correct `change.from/to`; retarget emits `pr.retargeted`. | Pest + staged fixtures |
| **A4** | **Release/tag collapse:** one event per `(repo, version)`; Redocly ×6 packages → 1; a tag with a release → 0 tag events; tag-only repo gets a real `occurredAt`. | Pest |
| **A5** | **Regression classification** (Appendix B) matches expected tier/topics. | Table-driven Pest |
| **A6** | **Persistence in CI:** 2nd scheduled run logs `loaded N entities` and `new` ≪ first-run count. | Workflow log assertion |
| **A7** | **Digest bounds:** ≤ 15 Act items; suppressed counts present; no synthetic dates. | Snapshot test of rendered digest |
| **A8** | **Roadmap refs resolve:** every `roadmapRefs` entry maps to an existing heading/file. | CI script |
| **A9** | **API budget:** a steady-state run stays within `X-RateLimit` headroom (log remaining at end). | Workflow log |
| **A10** | **Quiet commits:** two consecutive runs with no upstream change produce **no commit** (no timestamp-only diffs). | Local double-run + `git status --porcelain` assertion; workflow log |

**Review loop:** after 4 weeks of real digests, review Act precision/recall by hand (what did you act on; what did you miss) and tune weights — T4: expect 1–2 tuning rounds.

---

## 8. Rollout & migration

1. Ship **Phase 0** on a branch; run `poll.php --dry-run --baseline` locally and compare against today's dashboard.
2. Ship **Phase 1** (state on `main`, per D1); let CI run two scheduled cycles and check A6 and A10.
3. Phases 2–3 behind `--digest-v2`; compare old vs new for one week; then flip the default and delete the old table renderer.
4. There is **no persisted history to migrate** (F1); the first stateful run is a baseline.
5. Keep `docs/ECOSYSTEM_FEED.md` as the path; update its header and `README.md:232` blurb.

---

## 9. Decisions needed

| # | Decision | Recommendation |
|---|---|---|
| ~~D1~~ | State location | **Decided:** committed to `main` by the daily bot, with the §5.2 guardrails |
| **D2** | Auto-create issues for Act events? | Start **off**; weekly digest issue only; enable after 2–3 weeks if precision is good |
| **D3** | Keep our own repo in the feed? | No — exclude from digest; use it only to link `ourIssue` |
| **D4** | Spec branches to diff | `main`, `dev`, `v1.2-dev` (+ `v1.1-dev` while 1.1.x patches ship) — **confirm real default/dev branch names** |
| **D5** | Which peers are "tracked libraries" for major/minor Act? | `swaggerexpert/*` (oracles), `strefethen/arazzo-cli`, `jentic/arazzo-engine`, `usearazzo/arazzo-toolkit`, `Redocly/redocly-cli` |
| **D6** | Do you want the GitHub Project hook? | Yes if the org Project exists; otherwise skip |

---

## Appendix A — Verification commands for T3 claims

```bash
# F1: is the feed state ignored / untracked?
git check-ignore -v storage/ecosystem-feed/feed.json
git ls-files storage/ecosystem-feed | head
git log --oneline -5 -- docs/ECOSYSTEM_FEED.md          # confirm CI commits only the .md

# F2: tag order vs releases (Redocly as the example)
gh api 'repos/Redocly/redocly-cli/tags?per_page=5' --jq '.[].name'
gh api 'repos/Redocly/redocly-cli/releases?per_page=3' --jq '.[].tag_name'

# F5: does the haystack really include the URL / bodies? (dry run, single source)
php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5 --verbose

# F8: real spec paths on the dev branches
gh api repos/OAI/Arazzo-Specification/contents/src --jq '.[].name'
gh api repos/OAI/Arazzo-Specification/branches --jq '.[].name'
```

## Appendix B — Regression fixtures (taken from the 2026-10-13 feed)

| Fixture (source — title) | Today | Expected |
|---|---|---|
| `Mohammed-Alama/php-arazzo` — *refactor: extract framework-agnostic engine into arazzo-core (Plan A)* | tags `actor,spec` | excluded (own repo); even if included: **no** `actor` |
| `usearazzo/arazzo-toolkit` — *chore(deps-dev): bump @microsoft/api-extractor 7.59.2→7.59.3* | `actor` → "#410 human-in-loop" | **Noise** |
| `OAI/learn.openapis.org` — *build(deps): bump actions/checkout from 6 to 7* | **breaking** | **Noise** |
| `OAI/build-infra` — *Bump respec 37.3.6→37.4.0* | `actor` | **Noise** |
| `OAI/Arazzo-Specification` — *chore(deps): bump respec 37.4.0→37.4.1* | `a2a` / "Roadmap A2A step type" | **Noise** |
| `Redocly/redocly-cli` — tag `v1.0.0-beta.116` | actionable (dated poll day) | **no event** |
| `Redocly/redocly-cli` — `@redocly/*@2.63.0` ×6 | 6 actionable | **1** peer-release event |
| `OAI/Arazzo-Specification` — tag `1.1.0` (dated 2026-10-13) | actionable | **no event** (release 2026-05-18 is the one event) |
| `jentic/jentic-public-apis` — *Rebuild apis.json, scores.json…* | watch / uncategorized | **Noise** (counted) |
| `OAI/Arazzo-Specification#533` — *feat(spec): add SOAP support* | `soap,wsdl,breaking`, "P0-6" | watchlist; topic `wsdl`; ref `plan:phase-f-protocol-packages` |
| `OAI/Arazzo-Specification#516` — *specify ECMA-262 dialect for regex Criterion* | `xml,xpath,schema,runner,spec` (mis-tagged) | **Watch/Act**; topic `criterion-regex`; ref `roadmap:P0-4` |
| `OAI/Arazzo-Specification#590` — *Clarify how to read outputs of a workflow run by a retry failure action* | watch | **Watch**; ref `roadmap:P1-1` |
| `usearazzo/arazzo-toolkit#129` — *embedded runtime expressions in requestBody payloads are sent verbatim* | watch | **Watch** (conformance-test idea) |
| `OAI/Arazzo-Specification#597` — *Meeting: Oct 14th, 2026* | watch | `meeting.agenda`, one line in digest |
| `swaggerexpert/arazzo-runtime-expression` — release `v3.0.0` | `breaking,spec` | **Act** (tracked oracle, semver-major) |

## Appendix C — Epistemic audit

```xml
<epistemic_audit>
  <ontological_mapping>
    Entities: Source (sources.json) → Ingestor (Github/Atom/Npm/WebScrape) → raw item → Normalizer (tags, severity) → RelevanceMapper →
    FeedEvent (id = sha256(source|externalId)) → Store (feed.json, snapshots, cap 2000) → Renderer (ECOSYSTEM_FEED.md) → analyze.php (tasks).
    Constraints observed: id has no state component; tag publishedAt is synthetic; poll.php ignores poll/tier/branches/watch_files;
    CI persists only .cache/ecosystem; .gitignore last-match re-ignores storage/ecosystem-feed.
  </ontological_mapping>
  <source_reliability>
    Direct reads (highest): all scripts/ecosystem/*.php, ecosystem-feed.yml, .gitignore, FeedTest.php, analyze.php, SKILL.md,
    ECOSYSTEM_FEED.md, roadmap and research docs. Counts (e.g. ~144 tag rows of 200) are my manual count of the rendered table.
    Inference (T3): CI statelessness (F1), tag-API ordering (F2), `a2a`↔SHA (F5), real spec file paths (F8).
    Not read: remote CI logs, live API, composer.json scripts, tail of sources.json, sources.oai.json.
  </source_reliability>
  <falsifiability>
    F1 is wrong if `git ls-files storage/ecosystem-feed` lists feed.json (then investigate why it is absent locally) or CI logs show
    "Store: written N new" with N small on consecutive days. F2's ordering claim is wrong if `gh api repos/Redocly/redocly-cli/tags`
    returns 2.x tags first. F5's `a2a` claim is wrong if the matching bodies contain a literal "a2a" word.
    Effort estimates are T4 and should be re-scoped after Phase 0.
  </falsifiability>
</epistemic_audit>
```
