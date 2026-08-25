# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-08-25T11:16:47+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 149 (showing 200 newest)
- **Severity:** breaking **19** · actionable **69** · watch **61**
- **Top relevance:** `Conformance / schema validation` (64) · `P2-1 CLI binary` (33) · `uncategorized` (15) · `Potential breaking change (2.0)` (11) · `P1-7 JSON Schema layer` (9)
- **Top sources:** `strefethen/arazzo-cli` (34) · `OAI/Arazzo-Specification` (12) · `jentic/arazzo-engine` (6) · `OAI/OpenAPI-Specification` (5) · `b-lab-io/pyarazzo` (4)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Generated JSON](docs/generated/ecosystem-feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Potential breaking change (2.0) (11)

- `2026-08-25` [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) — `OAI/Overlay-Specification` · `pr` · _breaking,spec_
- `2026-08-25` [chore(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/550) — `OAI/Arazzo-Specification` · `pr` · _breaking,spec_
- `2026-08-24` [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/27) — `OAI/build-infra` · `pr` · _breaking,spec_
- `2026-08-24` [Bump respec from 37.2.0 to 37.3.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/20) — `OAI/build-infra` · `pr` · _breaking,schema,spec_
- `2026-08-24` [chore(deps): bump respec from 37.3.0 to 37.3.1](https://github.com/OAI/Arazzo-Specification/pull/549) — `OAI/Arazzo-Specification` · `pr` · _breaking,spec_
- `2026-08-21` [https://1.gravatar.com/avatar/505a6c892236ba3e5df5dcf22e5123eab57fe2d5326a29d765a4b9a356308f09?s=256&d=initials](https://github.com/OAI/tools.openapis.org/issues/284) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [feat: Source Resolution and OpenAPI Operations Architecture](https://github.com/Mohammed-Alama/php-arazzo/pull/14) — `Mohammed-Alama/php-arazzo` · `pr` · _breaking,spec_
- `2026-08-19` [Merge pull request #546 from OAI/dependabot/npm_and_yarn/respec-37.3.0](https://github.com/OAI/Arazzo-Specification/commit/fc140d26c440291b0061c62a53e45a8fb07cc369) — `OAI/Arazzo-Specification` · `commit` · _breaking,spec_
- … and 3 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-05-17` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,spec_
- `2025-09-19` [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) — `OAI/OpenAPI-Specification` · `release` · _xml,breaking,schema,spec_

### P2-1 CLI binary (2)

- `2026-08-25` [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `tag` · _cli,breaking,spec_
- `2026-03-21` [v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### P2-2 MCP server exposure (2)

- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,breaking,schema,spec_
- `2025-11-27` [1.2 - start of discussion/ideas/breaking changes](https://github.com/OAI/Arazzo-Specification/issues/410) — `OAI/Arazzo-Specification` · `issue` · _mcp,actor,human,loop,breaking,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-07-27` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,schema,spec_

### P1-7 JSON Schema layer (1)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (39)

- `2026-08-25` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-25` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-25` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-25` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-25` [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) — `JaredCE/Arazzo-Generator` · `tag` · _spec_
- `2026-08-25` [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) — `JaredCE/Arazzo-Generator` · `tag` · _spec_
- `2026-08-25` [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) — `speclynx/apidom` · `tag` · _spec_
- `2026-08-25` [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `tag` · _spec_
- … and 31 more in this group (see All events table)

### P2-1 CLI binary (18)

- `2026-08-25` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- … and 10 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (5)

- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,schema,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-04-06` [v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `release` · _xml,cli,loop,spec_
- `2026-03-13` [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,spec_
- `2025-09-19` [OAS 3.1.2 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.2) — `OAI/OpenAPI-Specification` · `release` · _xml,schema,spec_

### uncategorized (3)

- `2026-08-25` [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-25` [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-07-27` [feat: June 2026 newsletter](https://github.com/OAI/Outreach/pull/75) — `OAI/Outreach` · `pr` · _no tags_

### P1-7 JSON Schema layer (2)

- `2026-08-24` [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2025-10-01` [Arazzo Runner v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) — `jentic/arazzo-engine` · `release` · _schema,spec_

### Issue #410 kind discriminator / human-in-loop (1)

- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_

### Issue #410 loops vs goto (1)

- `2026-08-24` [chore(deps-dev): bump eslint from 10.8.1 to 10.9.0](https://github.com/usearazzo/arazzo-toolkit/pull/91) — `usearazzo/arazzo-toolkit` · `pr` · _loop,spec_


## Watch — context (commits/issues/checksums)

### Conformance / schema validation (25)

- `2026-08-25` [openapi.tools checksum 8815db2e440e](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-08-24` [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) — `usearazzo/website` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) — `usearazzo/website.ecosystem.atom` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) — `usearazzo/website` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) — `usearazzo/website.ecosystem.atom` · `commit` · _spec_
- `2026-08-23` [Tool discovery (`full` workflow) has failed on every scheduled run since 2025-07-13 — dead source URL in metadata.json](https://github.com/OAI/tools.openapis.org/issues/285) — `OAI/tools.openapis.org` · `issue` · _spec_
- `2026-08-23` [Open Community (TDC) Meeting, Thursday 27 August 2026](https://github.com/OAI/OpenAPI-Specification/issues/5505) — `OAI/OpenAPI-Specification` · `issue` · _spec_
- `2026-08-22` [I Went Looking for Everything Arazzo](https://usearazzo.com/blog/arazzo-ecosystem/) — `usearazzo/website.feed` · `article` · _spec_
- … and 17 more in this group (see All events table)

### P2-1 CLI binary (13)

- `2026-08-16` [plan: decompose arazzo validate](https://github.com/strefethen/arazzo-cli/commit/04ee2879e9f30396b80d99aa24968ac5cd9a9fca) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [revert: reset ac-67bf5 for fail-closed redesign](https://github.com/strefethen/arazzo-cli/commit/e252c979af1eca064af757f0c71559bc172f116d) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [Harden simple criterion parser](https://github.com/strefethen/arazzo-cli/commit/6b2a8eb6565b743e61cdf36cb472f4e92df8b592) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [Constrain simple criterion grammar](https://github.com/strefethen/arazzo-cli/commit/bbcae7fb36b56b31623417fc16ed5113ef97f062) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [fix: support decimal retry delays and string comparisons](https://github.com/strefethen/arazzo-cli/commit/1bef98b9668ddb88c54f7a3f236e806484c9a159) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [fix: preserve retry limits at numeric boundaries](https://github.com/strefethen/arazzo-cli/commit/708266ab06876d9b57de75aa180db134fb637c1e) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [fix: honor retry limits and failure fallthrough](https://github.com/strefethen/arazzo-cli/commit/f5890be5e02a48f609feafae75d6d145481a3e49) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-16` [fix: enforce action fixed fields](https://github.com/strefethen/arazzo-cli/commit/14ccda521eaba0f84b0b6767053cc7f1d2b7eb8c) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- … and 5 more in this group (see All events table)

### uncategorized (12)

- `2026-08-25` [Rebuild apis.json, scores.json, and API browsing indexes (#22081)](https://github.com/jentic/jentic-public-apis/commit/4b6a7e3ed01524ca366de0e340e6464cc4c8dc20) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-24` [Update Landscape from LFX 2026-08-24 (#187)](https://github.com/OAI/landscape/commit/7bbb234a9420058e987566baac8129a727e904fb) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-24` [Add overlay to set apify.com info.version (fixes import: missing version) (#22078)](https://github.com/jentic/jentic-public-apis/commit/2ec421d6d468fd9507560f8592b9fe32aeed4de5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-23` [Update Landscape from LFX 2026-08-23 (#186)](https://github.com/OAI/landscape/commit/fa54f124ce01a0ecafd424904fafa593914b3e72) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-21` [Allow versioning at path:method level](https://github.com/OAI/sig-lifecycle/issues/13) — `OAI/sig-lifecycle` · `issue` · _no tags_
- `2026-08-20` [Merge pull request #24 from handrews/yarn-package-management](https://github.com/OAI/build-infra/commit/b9b8777366b3d636ce18d2fe43f4449f6f8f67ea) — `OAI/build-infra` · `commit` · _no tags_
- `2026-08-18` [Make Git dependency test deterministic in CI](https://github.com/OAI/build-infra/commit/7825bbc07eb56b7359f8a91cb2fa3cc46d09cf79) — `OAI/build-infra` · `commit` · _no tags_
- `2026-07-27` [feat: Website redesign notes](https://github.com/OAI/Outreach/issues/53) — `OAI/Outreach` · `issue` · _no tags_
- … and 4 more in this group (see All events table)

### P1-7 JSON Schema layer (6)

- `2026-08-24` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _schema,spec_
- `2026-08-23` [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _schema,spec_
- `2026-08-10` [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) — `spec.arazzo.schema.1.1` · `schema_checksum` · _schema,spec_
- `2026-08-10` [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) — `spec.arazzo.schema.1.0` · `schema_checksum` · _schema,spec_
- `2026-08-02` [Add Gesso (PHP OpenAPI 3.0/3.1/3.2 contract testing library)](https://github.com/OAI/tools.openapis.org/pull/273) — `OAI/tools.openapis.org` · `pr` · _schema,spec_

### Issue #410 kind discriminator / human-in-loop (2)

- `2026-08-24` [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-19` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor,spec_

### P2-2 MCP server exposure (2)

- `2026-08-23` [refactor: derive describe method/target from the canonical classifier](https://github.com/strefethen/arazzo-cli/commit/92d3058cc63c1dcedefbdc78dacceee82daecd16) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,actor,schema,spec_
- `2026-08-05` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,loop,spec_

### P1-6 payload XPath / P0-5 XPath criteria (1)

- `2026-08-23` [fix: decide xpath criteria by effective boolean value](https://github.com/strefethen/arazzo-cli/commit/301174a63d1148f0d57d60fc8faef857706c9eab) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | openapi.tools | tool_collection | [openapi.tools checksum 8815db2e440e](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-08-25 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) |  | actionable |  |
| 2026-08-25 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) |  | actionable |  |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-25 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-25 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/550) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-25 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22081)](https://github.com/jentic/jentic-public-apis/commit/4b6a7e3ed01524ca366de0e340e6464cc4c8dc20) |  | watch |  |
| 2026-08-24 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-24 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump eslint from 10.8.1 to 10.9.0](https://github.com/usearazzo/arazzo-toolkit/pull/91) | loop, spec | actionable | Issue #410 loops vs goto |
| 2026-08-24 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/27) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/build-infra | pr | [Bump respec from 37.2.0 to 37.3.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/20) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-24 (#187)](https://github.com/OAI/landscape/commit/7bbb234a9420058e987566baac8129a727e904fb) |  | watch |  |
| 2026-08-24 | usearazzo/website | commit | [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website | commit | [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) | spec | watch | Conformance / schema validation |
| 2026-08-24 | OAI/OpenAPI-Specification | pr | [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-24 | jentic/jentic-public-apis | commit | [Add overlay to set apify.com info.version (fixes import: missing version) (#22078)](https://github.com/jentic/jentic-public-apis/commit/2ec421d6d468fd9507560f8592b9fe32aeed4de5) |  | watch |  |
| 2026-08-24 | OAI/Overlay-Specification | pr | [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-24 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.0 to 37.3.1](https://github.com/OAI/Arazzo-Specification/pull/549) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-23 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-23 (#186)](https://github.com/OAI/landscape/commit/fa54f124ce01a0ecafd424904fafa593914b3e72) |  | watch |  |
| 2026-08-23 | OAI/tools.openapis.org | issue | [Tool discovery (`full` workflow) has failed on every scheduled run since 2025-07-13 — dead source URL in metadata.json](https://github.com/OAI/tools.openapis.org/issues/285) | spec | watch | Conformance / schema validation |
| 2026-08-23 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 27 August 2026](https://github.com/OAI/OpenAPI-Specification/issues/5505) | spec | watch | Conformance / schema validation |
| 2026-08-23 | OAI/OpenAPI-Specification | pr | [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-23 | strefethen/arazzo-cli | commit | [refactor: derive describe method/target from the canonical classifier](https://github.com/strefethen/arazzo-cli/commit/92d3058cc63c1dcedefbdc78dacceee82daecd16) | mcp, cli, actor, schema, spec | watch | P2-2 MCP server exposure |
| 2026-08-23 | strefethen/arazzo-cli | commit | [fix: decide xpath criteria by effective boolean value](https://github.com/strefethen/arazzo-cli/commit/301174a63d1148f0d57d60fc8faef857706c9eab) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-22 | Specmatic/specmatic | release | [2.53.1](https://github.com/specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-08-22 | OAI/sig-security | issue | [Support for message level security](https://github.com/OAI/sig-security/issues/22) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-22 | usearazzo/website.feed | article | [I Went Looking for Everything Arazzo](https://usearazzo.com/blog/arazzo-ecosystem/) | spec | watch | Conformance / schema validation |
| 2026-08-21 | OAI/sig-lifecycle | issue | [Allow versioning at path:method level](https://github.com/OAI/sig-lifecycle/issues/13) |  | watch |  |
| 2026-08-21 | OAI/sig-lifecycle | issue | [OpenAPI 3.x: Scope of API version in servers definition too course grained?](https://github.com/OAI/sig-lifecycle/issues/12) | spec | watch | Conformance / schema validation |
| 2026-08-21 | OAI/tools.openapis.org | issue | [https://1.gravatar.com/avatar/505a6c892236ba3e5df5dcf22e5123eab57fe2d5326a29d765a4b9a356308f09?s=256&d=initials](https://github.com/OAI/tools.openapis.org/issues/284) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-21 | Mohammed-Alama/php-arazzo | pr | [fix: remove outdated deprecation notice on ExpressionResolverInterface](https://github.com/Mohammed-Alama/php-arazzo/pull/15) | spec | actionable | Conformance / schema validation |
| 2026-08-21 | Mohammed-Alama/php-arazzo | pr | [feat: Source Resolution and OpenAPI Operations Architecture](https://github.com/Mohammed-Alama/php-arazzo/pull/14) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-21 | Redocly/redocly-cli | release | [@redocly/respect-core@2.47.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.47.0) | spec | actionable | Conformance / schema validation |
| 2026-08-21 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.47.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.47.0) | spec | actionable | Conformance / schema validation |
| 2026-08-20 | OAI/sig-lifecycle | pr | [Yarn package management](https://github.com/OAI/sig-lifecycle/pull/4) | spec | actionable | Conformance / schema validation |
| 2026-08-20 | OAI/build-infra | commit | [Merge pull request #24 from handrews/yarn-package-management](https://github.com/OAI/build-infra/commit/b9b8777366b3d636ce18d2fe43f4449f6f8f67ea) |  | watch |  |
| 2026-08-19 | OAI/sig-lifecycle | pr | [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-19 | speclynx/apidom | release | [v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-19 | OAI/Arazzo-Specification | commit | [Merge pull request #546 from OAI/dependabot/npm_and_yarn/respec-37.3.0](https://github.com/OAI/Arazzo-Specification/commit/fc140d26c440291b0061c62a53e45a8fb07cc369) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-19 | OAI/Arazzo-Specification | commit | [Merge pull request #545 from OAI/dependabot/npm_and_yarn/highlight.js-11.12.0](https://github.com/OAI/Arazzo-Specification/commit/0ca33e2649225d7035a1e0d66ef07a5eb0f517e1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-18 | OAI/build-infra | commit | [Make Git dependency test deterministic in CI](https://github.com/OAI/build-infra/commit/7825bbc07eb56b7359f8a91cb2fa3cc46d09cf79) |  | watch |  |
| 2026-08-18 | speakeasy-api/openapi | release | [v1.25.0](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-18 | OAI/spec.openapis.org | pr | [Update date-time formats to RFC 9557](https://github.com/OAI/spec.openapis.org/pull/119) | spec | watch | Conformance / schema validation |
| 2026-08-18 | usearazzo/website.feed | article | [API Workflows Are Still Improvised. Here Is What We Are Doing About It](https://usearazzo.com/blog/api-workflows-are-still-improvised/) | spec | watch | Conformance / schema validation |
| 2026-08-16 | strefethen/arazzo-cli | commit | [plan: decompose arazzo validate](https://github.com/strefethen/arazzo-cli/commit/04ee2879e9f30396b80d99aa24968ac5cd9a9fca) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [revert: reset ac-67bf5 for fail-closed redesign](https://github.com/strefethen/arazzo-cli/commit/e252c979af1eca064af757f0c71559bc172f116d) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [Harden simple criterion parser](https://github.com/strefethen/arazzo-cli/commit/6b2a8eb6565b743e61cdf36cb472f4e92df8b592) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [Constrain simple criterion grammar](https://github.com/strefethen/arazzo-cli/commit/bbcae7fb36b56b31623417fc16ed5113ef97f062) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [fix: support decimal retry delays and string comparisons](https://github.com/strefethen/arazzo-cli/commit/1bef98b9668ddb88c54f7a3f236e806484c9a159) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [fix: preserve retry limits at numeric boundaries](https://github.com/strefethen/arazzo-cli/commit/708266ab06876d9b57de75aa180db134fb637c1e) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [fix: honor retry limits and failure fallthrough](https://github.com/strefethen/arazzo-cli/commit/f5890be5e02a48f609feafae75d6d145481a3e49) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-16 | strefethen/arazzo-cli | commit | [fix: enforce action fixed fields](https://github.com/strefethen/arazzo-cli/commit/14ccda521eaba0f84b0b6767053cc7f1d2b7eb8c) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-14 | Specmatic/specmatic | release | [2.53.0](https://github.com/specmatic/specmatic/releases/tag/2.53.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-13 | strefethen/arazzo-cli | issue | [When two or more sourceDescriptions are provided with (local) OpenAPI specs, only the first spec's base URL is shown in dry-run for all calls by OperationId.](https://github.com/strefethen/arazzo-cli/issues/5) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-12 | OAI/spec.openapis.org | pr | [fix json punctuation](https://github.com/OAI/spec.openapis.org/pull/130) | spec | watch | Conformance / schema validation |
| 2026-08-11 | OAI/learn.openapis.org | pr | [docs: adds an upgrade guide for overlay 1.2](https://github.com/OAI/learn.openapis.org/pull/206) | spec | watch | Conformance / schema validation |
| 2026-08-10 | spec.arazzo.html | spec_html_checksum | [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) | spec | watch | Conformance / schema validation |
| 2026-08-10 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-10 | spec.arazzo.schema.1.0 | schema_checksum | [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-10 | OAI/spec.openapis.org | commit | [Merge pull request #113 from darkmatterforge/add-x-data-classification-extension](https://github.com/OAI/spec.openapis.org/commit/ff18fbf54d8cdb721f0bf26e317f5ad4090f3da8) | spec | watch | Conformance / schema validation |
| 2026-08-10 | OAI/spec.openapis.org | commit | [Merge pull request #38 from ioggstream/ioggstream-37](https://github.com/OAI/spec.openapis.org/commit/f2113ca5095295af925012f19fe3cfc8d28874ef) | spec | watch | Conformance / schema validation |
| 2026-08-09 | speclynx/apidom | release | [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-08-06 | speakeasy-api/openapi | release | [v1.24.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.24.1) | cli, schema, spec | actionable | P2-1 CLI binary |
| 2026-08-05 | strefethen/arazzo-cli | issue | [Missing debug adapter for VS Code extension on Windows](https://github.com/strefethen/arazzo-cli/issues/2) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-05 | OAI/tools.openapis.org | pr | [fix: upgrade axios to 1.13.5, 0.30.3 (CVE-2026-25639)](https://github.com/OAI/tools.openapis.org/pull/275) | spec | watch | Conformance / schema validation |
| 2026-08-05 | strefethen/arazzo-cli | issue | [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) | mcp, cli, loop, spec | watch | P2-2 MCP server exposure |
| 2026-08-05 | strefethen/arazzo-cli | release | [Arazzo Debugger 0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-03 | strefethen/arazzo-cli | issue | [`arazzo generate` fails on OpenAPI 3.2 spec](https://github.com/strefethen/arazzo-cli/issues/3) | cli, schema, spec | watch | P2-1 CLI binary |
| 2026-08-03 | strefethen/arazzo-cli | release | [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | xml, mcp, cli, loop, schema, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-03 | strefethen/arazzo-cli | release | [Arazzo Debugger 0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-03 | stoplightio/spectral | release | [v6.16.3](https://github.com/stoplightio/spectral/releases/tag/v6.16.3) | spec | actionable | Conformance / schema validation |
| 2026-08-02 | OAI/tools.openapis.org | pr | [Add Gesso (PHP OpenAPI 3.0/3.1/3.2 contract testing library)](https://github.com/OAI/tools.openapis.org/pull/273) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-07-27 | OAI/Arazzo-Specification | pr | [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) | soap, wsdl, breaking, schema, spec | breaking | P0-6 source routing (wsdl type) |
| 2026-07-27 | OAI/Outreach | issue | [feat: Implement Agenda GitHub Action for weekly meetings](https://github.com/OAI/Outreach/issues/76) | spec | watch | Conformance / schema validation |
| 2026-07-27 | OAI/Outreach | issue | [feat: Website redesign notes](https://github.com/OAI/Outreach/issues/53) |  | watch |  |
| 2026-07-27 | OAI/Outreach | pr | [feat: June 2026 newsletter](https://github.com/OAI/Outreach/pull/75) |  | actionable |  |
| 2026-07-24 | OAI/learn.openapis.org | pr | [Reorganization of best practices and addition of overlays for globals](https://github.com/OAI/learn.openapis.org/pull/201) | spec | watch | Conformance / schema validation |
| 2026-07-24 | OAI/community | pr | [SIG Liaison and Ambassador Guide](https://github.com/OAI/community/pull/21) | spec | watch | Conformance / schema validation |
| 2026-07-21 | jentic/jentic-arazzo-tools | release | [v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-07-20 | stoplightio/spectral | release | [v6.16.2](https://github.com/stoplightio/spectral/releases/tag/v6.16.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-07-09 | OAI/Arazzo-Specification | pr | [fix(spec): specify ECMA-262 dialect for regex Criterion](https://github.com/OAI/Arazzo-Specification/pull/516) | spec | watch | Conformance / schema validation |
| 2026-07-08 | swaggerexpert/arazzo-criterion | release | [v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-07-08 | swaggerexpert/arazzo-criterion | release | [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | xml, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-07-08 | swaggerexpert/arazzo-runtime-expression | release | [v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-07-07 | swaggerexpert/arazzo-runtime-expression | release | [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-07-06 | OAI/Arazzo-Specification | pr | [Clarify when Criterion context is required](https://github.com/OAI/Arazzo-Specification/pull/499) | spec | watch | Conformance / schema validation |
| 2026-06-12 | jentic/arazzo-engine | pr | [Update community contact email to hello@jentic.com](https://github.com/jentic/arazzo-engine/pull/146) | spec | actionable | Conformance / schema validation |
| 2026-05-19 | jentic/jentic-arazzo-tools | release | [v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-05-17 | OAI/Arazzo-Specification | release | [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | xml, xpath, spec | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-04-29 | jentic/arazzo-engine | pr | [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) | spec | watch | Conformance / schema validation |
| 2026-04-24 | b-lab-io/pyarazzo | release | [v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-04-06 | strefethen/arazzo-cli | release | [v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | xml, cli, loop, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-03-29 | strefethen/arazzo-cli | release | [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | mcp, cli, breaking, schema, spec | breaking | P2-2 MCP server exposure |
| 2026-03-26 | b-lab-io/pyarazzo | release | [v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-03-21 | strefethen/arazzo-cli | release | [v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-03-20 | OAI/Outreach | pr | [test: Social workflow](https://github.com/OAI/Outreach/pull/65) |  | watch |  |
| 2026-03-17 | strefethen/arazzo-cli | release | [v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli, spec | actionable | P2-1 CLI binary |
| 2026-03-17 | OAI/sig-moonwalk | issue | [Write ADR for identity vs location](https://github.com/OAI/sig-moonwalk/issues/92) | spec | watch | Conformance / schema validation |
| 2026-03-15 | strefethen/arazzo-cli | release | [v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-03-15 | strefethen/arazzo-cli | release | [v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-03-13 | strefethen/arazzo-cli | release | [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | xml, xpath, cli, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-02-03 | JaredCE/Arazzo-Generator | release | [0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-02-02 | JaredCE/Arazzo-Generator | release | [0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-01-16 | OAI/Overlay-Specification | release | [](https://github.com/OAI/Overlay-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2025-11-27 | OAI/Arazzo-Specification | issue | [1.2 - start of discussion/ideas/breaking changes](https://github.com/OAI/Arazzo-Specification/issues/410) | mcp, actor, human, loop, breaking, spec | breaking | P2-2 MCP server exposure |
| 2025-11-12 | OAI/sig-moonwalk | pr | [Create draft REST proposal](https://github.com/OAI/sig-moonwalk/pull/212) |  | watch |  |
| 2025-10-01 | jentic/arazzo-engine | release | [Arazzo Runner v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2025-09-19 | OAI/OpenAPI-Specification | release | [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) | xml, breaking, schema, spec | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2025-09-19 | OAI/OpenAPI-Specification | release | [OAS 3.1.2 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.2) | xml, schema, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2025-09-04 | jentic/arazzo-engine | release | [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2025-05-07 | OAI/sig-moonwalk | pr | [Added preliminary design for resource model](https://github.com/OAI/sig-moonwalk/pull/183) |  | watch |  |
| 2025-03-28 | workflows-guru/awesome-arazzo | pr | [Add Redocly CLI Arazzo linter](https://github.com/workflows-guru/awesome-arazzo/pull/2) | cli, spec | actionable | P2-1 CLI binary |
| 2025-03-27 | workflows-guru/awesome-arazzo | commit | [Merge pull request #2 from tsolakoua/add-redocly-cli-linter](https://github.com/workflows-guru/awesome-arazzo/commit/4539edc132aad782abecaf1ba8cc41a114b97bf0) | cli, spec | watch | P2-1 CLI binary |
| 2025-03-27 | workflows-guru/awesome-arazzo | commit | [add Readlocly CLI Arazzo linter](https://github.com/workflows-guru/awesome-arazzo/commit/880ff98e55e9e36be60c50b8db285a6c57c3b63c) | cli, spec | watch | P2-1 CLI binary |
| 2025-01-20 | OAI/Arazzo-Specification | release | [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | schema, spec | breaking | P1-7 JSON Schema layer |
| 2024-10-17 | OAI/Overlay-Specification | release | [v1.0.0](https://github.com/OAI/Overlay-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2024-07-23 | OAI/community | issue | [Distribution entry was not updated properly. Need manual operator handling.](https://github.com/OAI/community/issues/20) |  | watch |  |
| 2024-07-10 | OAI/community | pr | [Update Special Interest Groups details](https://github.com/OAI/community/pull/19) | spec | watch | Conformance / schema validation |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`
