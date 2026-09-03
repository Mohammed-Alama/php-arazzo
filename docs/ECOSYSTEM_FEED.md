# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-03T11:12:22+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 840 (showing 200 newest)
- **Severity:** breaking **159** · actionable **380** · watch **301**
- **Top relevance:** `Conformance / schema validation` (378) · `uncategorized` (115) · `Potential breaking change (2.0)` (114) · `P2-1 CLI binary` (82) · `P1-7 JSON Schema layer` (52)
- **Top sources:** `OAI/Arazzo-Specification` (53) · `strefethen/arazzo-cli` (48) · `OAI/build-infra` (43) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Potential breaking change (2.0) (114)

- `2026-09-03` [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-09-03` [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-09-03` [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `tag` · _breaking,spec_
- … and 106 more in this group (see All events table)

### P2-1 CLI binary (13)

- `2026-09-03` [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `tag` · _cli,breaking,spec_
- `2026-08-31` [chore(deps-dev): bump lint-staged from 17.3.0 to 17.4.1](https://github.com/usearazzo/arazzo-toolkit/pull/108) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,spec_
- `2026-08-27` [Bump markdown-it from 14.2.0 to 15.0.0](https://github.com/OAI/OpenAPI-Specification/pull/5461) — `OAI/OpenAPI-Specification` · `pr` · _cli,breaking,spec_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-25` [chore: release v0.4.0](https://github.com/strefethen/arazzo-cli/commit/6217148dba9f279529405ab27277dcf2de9a0cba) — `strefethen/arazzo-cli` · `commit` · _cli,breaking,spec_
- `2026-08-21` [chore(deps-dev): bump lint-staged from 16.4.0 to 17.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/72) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,spec_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,breaking,schema,spec_
- `2026-08-03` [build(deps): bump markdown-it from 14.3.0 to 15.0.0](https://github.com/OAI/Overlay-Specification/pull/375) — `OAI/Overlay-Specification` · `pr` · _cli,breaking,spec_
- … and 5 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (11)

- `2026-09-01` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,breaking,spec_
- `2026-08-26` [feat: OpenAPI Normalization Gaps (Spec 4)](https://github.com/Mohammed-Alama/php-arazzo/issues/25) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,breaking,spec_
- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,schema,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,breaking,spec_
- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2025-09-19` [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) — `OAI/OpenAPI-Specification` · `release` · _xml,breaking,schema,spec_
- … and 3 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (9)

- `2026-09-02` [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/35) — `OAI/build-infra` · `pr` · _actor,breaking,spec_
- `2026-09-01` [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/30) — `OAI/build-infra` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump respec from 37.3.0 to 37.3.5](https://github.com/OAI/Arazzo-Specification/pull/552) — `OAI/Arazzo-Specification` · `pr` · _actor,breaking,spec_
- `2026-08-28` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,breaking,spec_
- `2026-07-28` [build(deps-dev): bump markdownlint-cli2 from 0.23.1 to 0.23.2](https://github.com/OAI/Overlay-Specification/pull/368) — `OAI/Overlay-Specification` · `pr` · _actor,a2a,breaking,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,breaking,schema,spec_
- … and 1 more in this group (see All events table)

### P0-6 source routing (wsdl type) (4)

- `2026-09-01` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,schema,spec_
- `2026-08-28` [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) — `OAI/build-infra` · `pr` · _soap,breaking_
- `2026-08-19` [chore(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Arazzo-Specification/pull/545) — `OAI/Arazzo-Specification` · `pr` · _soap,breaking,spec_
- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,breaking,spec_

### P2-2 MCP server exposure (4)

- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,breaking,spec_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,breaking,schema,spec_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,breaking,spec_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,breaking,schema,spec_

### Roadmap A2A step type (2)

- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,breaking,spec_
- `2026-08-18` [build(deps-dev): update pestphp/pest requirement from ^4.0 to ^5.1](https://github.com/Mohammed-Alama/php-arazzo/pull/8) — `Mohammed-Alama/php-arazzo` · `pr` · _a2a,breaking,spec_

### Issue #410 loops vs goto (1)

- `2026-03-30` [Feat: Marketing channel strategy for repositioning OAI](https://github.com/OAI/Outreach/issues/72) — `OAI/Outreach` · `issue` · _loop,breaking,spec_

### P1-7 JSON Schema layer (1)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (225)

- `2026-09-03` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-03` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-03` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-03` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-03` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-03` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-03` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-03` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 217 more in this group (see All events table)

### uncategorized (50)

- `2026-09-03` [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-09-03` [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) — `Redocly/redocly-cli` · `tag` · _no tags_
- … and 42 more in this group (see All events table)

### P2-1 CLI binary (45)

- `2026-09-03` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-09-03` [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- … and 37 more in this group (see All events table)

### P1-7 JSON Schema layer (27)

- `2026-09-02` [@redocly/openapi-core@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.50.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-09-02` [@redocly/cli@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.50.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-09-01` [v3.3: fix openapi version in these examples](https://github.com/OAI/OpenAPI-Specification/pull/5531) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-31` [v3.2: fix regex for openapi version](https://github.com/OAI/OpenAPI-Specification/pull/5529) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-31` [v3.3: fix regex for openapi version](https://github.com/OAI/OpenAPI-Specification/pull/5530) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Remove various npm hacks, switch to yarn for package management](https://github.com/OAI/build-infra/pull/24) — `OAI/build-infra` · `pr` · _schema,spec_
- `2026-08-27` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [v3.3: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5518) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- … and 19 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (15)

- `2026-09-03` [refactor(runner): migrate to @usearazzo/parser's parseRuntimeExpression](https://github.com/usearazzo/arazzo-toolkit/pull/134) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-09-02` [feat(parser): expose parseRuntimeExpression and parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/pull/130) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-27` [Fix/sync lockfile packaged lock](https://github.com/OAI/build-infra/pull/19) — `OAI/build-infra` · `pr` · _human,spec_
- `2026-08-27` [chore: delete 2 dead exception classes (G14)](https://github.com/Mohammed-Alama/php-arazzo/pull/45) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-24` [chore(deps-dev): bump @microsoft/api-extractor from 7.58.12 to 7.59.0](https://github.com/usearazzo/arazzo-toolkit/pull/89) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-18` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,schema,spec_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- `2026-07-31` [2.51.1](https://github.com/specmatic/specmatic/releases/tag/2.51.1) — `Specmatic/specmatic` · `release` · _actor,schema,spec_
- … and 7 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (11)

- `2026-08-26` [feat: Docker-based isolated dev environments (apptree)](https://github.com/Mohammed-Alama/php-arazzo/pull/28) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,cli,spec_
- `2026-08-26` [refactor: flatten Runner module into 23 top-level sibling modules](https://github.com/Mohammed-Alama/php-arazzo/pull/21) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,actor,schema,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,schema,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,schema,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,spec_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,spec_
- `2026-04-06` [v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `release` · _xml,cli,loop,spec_
- … and 3 more in this group (see All events table)

### P0-6 source routing (wsdl type) (3)

- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

### Issue #410 loops vs goto (2)

- `2026-08-24` [chore(deps-dev): bump eslint from 10.8.1 to 10.9.0](https://github.com/usearazzo/arazzo-toolkit/pull/91) — `usearazzo/arazzo-toolkit` · `pr` · _loop,spec_
- `2026-08-10` [feat: adds reusable actions](https://github.com/OAI/Overlay-Specification/pull/296) — `OAI/Overlay-Specification` · `pr` · _loop,spec_

### P2-2 MCP server exposure (1)

- `2026-07-17` [2.50.1](https://github.com/specmatic/specmatic/releases/tag/2.50.1) — `Specmatic/specmatic` · `release` · _mcp,spec_

### Roadmap A2A step type (1)

- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_


## Watch — context (commits/issues/checksums)

### Conformance / schema validation (153)

- `2026-09-03` [openapi.tools checksum c4e8b5c7d435](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-03` [parser: MemoryResolver serves the parent document for any memory:// URI, masking unresolvable relative source descriptions](https://github.com/usearazzo/arazzo-toolkit/issues/135) — `usearazzo/arazzo-toolkit` · `issue` · _spec_
- `2026-09-03` [runner: migrate to @usearazzo/parser's parseRuntimeExpression / parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/issues/131) — `usearazzo/arazzo-toolkit` · `issue` · _spec_
- `2026-09-03` [Render URL/vendor examples as real markdown bullets (#22040)](https://github.com/jentic/jentic-public-apis/commit/ad0c9dfc973b2e47036806077331b2def16cb794) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-03` [feat: Import OpenAPI spec from Issue #22154 (#22155)](https://github.com/jentic/jentic-public-apis/commit/0175c4ee13adb6b18a15006c78aa211ab570b757) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-03` [feat: Import OpenAPI spec from Issue #22152 (#22153)](https://github.com/jentic/jentic-public-apis/commit/e5fddc7f487c03ffca8f40ef6e3eb675f145123c) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-03` [feat: Import OpenAPI spec from Issue #22141 (#22142)](https://github.com/jentic/jentic-public-apis/commit/51ad04cbc03ef1540cf8f259337428c339e9fe86) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-03` [feat: Import OpenAPI spec from Issue #22139 (#22140)](https://github.com/jentic/jentic-public-apis/commit/f64e674faab6be2ade5ff50eca3d6a0dbf4a172d) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 145 more in this group (see All events table)

### uncategorized (65)

- `2026-09-03` [Rebuild apis.json, scores.json, and API browsing indexes (#22180)](https://github.com/jentic/jentic-public-apis/commit/79d02d0c2b5f04ed7b23cd34bfcb7d8b3d08b2a3) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-03` [Rebuild apis.json, scores.json, and API browsing indexes (#22178)](https://github.com/jentic/jentic-public-apis/commit/f7b3b11080d33fbbac73b6f81c101d1965659a15) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-03` [Rebuild apis.json, scores.json, and API browsing indexes (#22177)](https://github.com/jentic/jentic-public-apis/commit/fe147e7fecd07899c6a538c437b5df45c634e6ce) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-03` [Rebuild apis.json, scores.json, and API browsing indexes (#22175)](https://github.com/jentic/jentic-public-apis/commit/f40022ab682abb32ae2f48e4e84e908cf440824a) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-02` [Tag or otherwise manage releases](https://github.com/OAI/build-infra/issues/33) — `OAI/build-infra` · `issue` · _no tags_
- `2026-09-02` [Merge pull request #34 from OAI/dependabot/npm_and_yarn/markdown-a6d6531f3e](https://github.com/OAI/build-infra/commit/2a400798cfb7e6158fdb84c6d296c8d3e3830c9d) — `OAI/build-infra` · `commit` · _no tags_
- `2026-09-02` [Rebuild apis.json, scores.json, and API browsing indexes (#22157)](https://github.com/jentic/jentic-public-apis/commit/8de283270b042e9d157ac8f978c1d390dde84320) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-02` [Rebuild apis.json, scores.json, and API browsing indexes (#22127)](https://github.com/jentic/jentic-public-apis/commit/e1fb5a1d802a394c031a4ffe7edc80de51c2f3fc) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 57 more in this group (see All events table)

### P1-7 JSON Schema layer (24)

- `2026-08-31` [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-31` [1.1 schema: parameter-object.value lost the `object` type present in 1.0](https://github.com/OAI/Arazzo-Specification/issues/559) — `OAI/Arazzo-Specification` · `issue` · _schema,spec_
- `2026-08-30` [1.1 schema: expression-type-object requires `version`, contradicting its own description](https://github.com/OAI/Arazzo-Specification/issues/558) — `OAI/Arazzo-Specification` · `issue` · _schema,spec_
- `2026-08-28` [OAS v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/pull/5528) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5526) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5525) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Make patch optional in openapi field.](https://github.com/OAI/OpenAPI-Specification/pull/4929) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-25` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _schema,spec_
- … and 16 more in this group (see All events table)

### P2-1 CLI binary (24)

- `2026-08-27` [test: add tests for Console (55% → 100%) (G11)](https://github.com/Mohammed-Alama/php-arazzo/issues/38) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,spec_
- `2026-08-26` [When two or more sourceDescriptions are provided with (local) OpenAPI specs, only the first spec's base URL is shown in dry-run for all calls by OperationId.](https://github.com/strefethen/arazzo-cli/issues/5) — `strefethen/arazzo-cli` · `issue` · _cli,spec_
- `2026-08-26` [fix(conformance): scope the claim to type: openapi, not "non-arazzo"](https://github.com/strefethen/arazzo-cli/commit/8f2217c6fe38be5117543529f367b5bfc0a0d606) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [fix(conformance): re-own the operationPath claim and cover the file url read](https://github.com/strefethen/arazzo-cli/commit/47d0b0de1ac199e9f29c498c4774069c1debdd6f) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: install CLI after release push](https://github.com/strefethen/arazzo-cli/commit/73ed5b90d105d595ddad4cdd0e8e08a5df3a8a27) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: release v0.5.0](https://github.com/strefethen/arazzo-cli/commit/9a405456aa58b3c48736740400fd373b42227e4e) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [docs(readme): link the examples catalog instead of restating it](https://github.com/strefethen/arazzo-cli/commit/090672a03a487f033bd175d33b6cd6cf81409262) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [docs(examples): catalog every example spec and fix stale run commands](https://github.com/strefethen/arazzo-cli/commit/bc12c67c58e50133d9a6893a6d9251cd618a737a) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- … and 16 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-09-03` [refactor(core): resolve layering violations (#36)](https://github.com/Mohammed-Alama/php-arazzo/pull/50) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website` · `commit` · _actor,loop,spec_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor,loop,spec_
- `2026-08-26` [feat: Documentation, CI, Release Readiness (Spec 6)](https://github.com/Mohammed-Alama/php-arazzo/issues/27) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-08-21` [Endpoint-level and field-level role/permission support](https://github.com/OAI/sig-security/issues/35) — `OAI/sig-security` · `issue` · _actor,spec_
- `2026-08-21` [Support describing security keys in OAS](https://github.com/OAI/sig-security/issues/20) — `OAI/sig-security` · `issue` · _human,spec_
- … and 5 more in this group (see All events table)

### Issue #410 loops vs goto (6)

- `2026-08-31` [OpenAPI - publish v3.2-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/64) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-08-31` [OpenAPI - publish v3.3-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/60) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-08-04` [OpenAPI - publish v3.1-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/129) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-07-27` [Arazzo - publish v1.2-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/109) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,spec_
- `2026-04-02` [Feat: Launch monthly member drop-in clinics (EU and US timezones)](https://github.com/OAI/Outreach/issues/69) — `OAI/Outreach` · `issue` · _loop,spec_

### P1-6 payload XPath / P0-5 XPath criteria (6)

- `2026-09-01` [runner: payload replacement targets beyond JSON Pointer — JSONPath, XPath, targetSelectorType, media-type default (Arazzo 1.1.0)](https://github.com/usearazzo/arazzo-toolkit/issues/124) — `usearazzo/arazzo-toolkit` · `issue` · _xml,xpath,spec_
- `2026-09-01` [runner: criterion evaluation errors must fail the criterion, not abort the step (Arazzo 1.1.0 MUST)](https://github.com/usearazzo/arazzo-toolkit/issues/120) — `usearazzo/arazzo-toolkit` · `issue` · _xml,xpath,spec_
- `2026-09-01` [runner: Selector Object support in outputs, parameter values, and payload replacement values (Arazzo 1.1.0)](https://github.com/usearazzo/arazzo-toolkit/issues/123) — `usearazzo/arazzo-toolkit` · `issue` · _xml,xpath,spec_
- `2026-09-01` [runner: Selector Object support in value positions (Arazzo 1.1.0)](https://github.com/usearazzo/arazzo-toolkit/issues/113) — `usearazzo/arazzo-toolkit` · `issue` · _xml,xpath,spec_
- `2026-08-31` [XPath version identifier feels a bit confusing](https://github.com/OAI/Arazzo-Specification/issues/219) — `OAI/Arazzo-Specification` · `issue` · _xml,xpath,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,spec_

### P2-2 MCP server exposure (4)

- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website` · `commit` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,loop,spec_
- `2026-08-26` [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_

### Roadmap A2A step type (2)

- `2026-08-20` [docs: update CLAUDE.md to reflect current reality](https://github.com/usearazzo/website/commit/ac65d199b313b25b1eea2a19af2881573634246e) — `usearazzo/website` · `commit` · _a2a,spec_
- `2026-07-22` [build(deps): bump ruby/setup-ruby from 1.319.0 to 1.320.0](https://github.com/OAI/spec.openapis.org/commit/3ccc930eaa2a78c31ea19f09e0dbea2639b571ed) — `OAI/spec.openapis.org` · `commit` · _a2a,spec_

### Roadmap GraphQL step type (2)

- `2026-09-02` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,schema,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-08-26` [feat: Testing and Adapter Parity (Spec 5)](https://github.com/Mohammed-Alama/php-arazzo/issues/26) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,spec_

### Roadmap gRPC step type (1)

- `2026-09-02` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,schema,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-03 | openapi.tools | tool_collection | [openapi.tools checksum c4e8b5c7d435](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-03 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | speclynx/apidom | tag | [tag v4.8.0](https://github.com/speclynx/apidom/releases/tag/v4.8.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.45.1](https://github.com/Specmatic/specmatic/releases/tag/2.45.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Specmatic/specmatic | tag | [tag 2.45.0](https://github.com/Specmatic/specmatic/releases/tag/2.45.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) |  | actionable |  |
| 2026-09-03 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) |  | actionable |  |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | usearazzo/arazzo-toolkit | issue | [parser: MemoryResolver serves the parent document for any memory:// URI, masking unresolvable relative source descriptions](https://github.com/usearazzo/arazzo-toolkit/issues/135) | spec | watch | Conformance / schema validation |
| 2026-09-03 | usearazzo/arazzo-toolkit | pr | [refactor(runner): migrate to @usearazzo/parser's parseRuntimeExpression](https://github.com/usearazzo/arazzo-toolkit/pull/134) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-03 | usearazzo/arazzo-toolkit | issue | [runner: migrate to @usearazzo/parser's parseRuntimeExpression / parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/issues/131) | spec | watch | Conformance / schema validation |
| 2026-09-03 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump github/codeql-action from 4.37.8 to 4.37.9](https://github.com/usearazzo/arazzo-toolkit/pull/110) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump vscode-languageserver-types from 3.17.6-next.6 to 3.18.3](https://github.com/usearazzo/arazzo-toolkit/pull/109) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | jentic/jentic-public-apis | commit | [Render URL/vendor examples as real markdown bullets (#22040)](https://github.com/jentic/jentic-public-apis/commit/ad0c9dfc973b2e47036806077331b2def16cb794) | spec | watch | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22180)](https://github.com/jentic/jentic-public-apis/commit/79d02d0c2b5f04ed7b23cd34bfcb7d8b3d08b2a3) |  | watch |  |
| 2026-09-03 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22154 (#22155)](https://github.com/jentic/jentic-public-apis/commit/0175c4ee13adb6b18a15006c78aa211ab570b757) | spec | watch | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22152 (#22153)](https://github.com/jentic/jentic-public-apis/commit/e5fddc7f487c03ffca8f40ef6e3eb675f145123c) | spec | watch | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22178)](https://github.com/jentic/jentic-public-apis/commit/f7b3b11080d33fbbac73b6f81c101d1965659a15) |  | watch |  |
| 2026-09-03 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22141 (#22142)](https://github.com/jentic/jentic-public-apis/commit/51ad04cbc03ef1540cf8f259337428c339e9fe86) | spec | watch | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22139 (#22140)](https://github.com/jentic/jentic-public-apis/commit/f64e674faab6be2ade5ff50eca3d6a0dbf4a172d) | spec | watch | Conformance / schema validation |
| 2026-09-03 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22177)](https://github.com/jentic/jentic-public-apis/commit/fe147e7fecd07899c6a538c437b5df45c634e6ce) |  | watch |  |
| 2026-09-03 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22134 (#22136)](https://github.com/jentic/jentic-public-apis/commit/d66243ca358db011763d3354c0a2ecb187bdacf8) | spec | watch | Conformance / schema validation |
| 2026-09-03 | Mohammed-Alama/php-arazzo | pr | [refactor(core): resolve layering violations (#36)](https://github.com/Mohammed-Alama/php-arazzo/pull/50) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-03 | Redocly/redocly-cli | release | [@redocly/respect-core@2.51.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.51.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.5](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.5) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | Redocly/redocly-cli | release | [@redocly/cli@2.51.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-03 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-03 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22175)](https://github.com/jentic/jentic-public-apis/commit/f40022ab682abb32ae2f48e4e84e908cf440824a) |  | watch |  |
| 2026-09-02 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump webpack from 5.110.1 to 5.110.2](https://github.com/usearazzo/arazzo-toolkit/pull/133) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-02 | OAI/tools.openapis.org | issue | [dz](https://github.com/OAI/tools.openapis.org/issues/291) | spec | watch | Conformance / schema validation |
| 2026-09-02 | OAI/build-infra | issue | [Tag or otherwise manage releases](https://github.com/OAI/build-infra/issues/33) |  | watch |  |
| 2026-09-02 | OAI/build-infra | pr | [Reverting recent dependabot changes](https://github.com/OAI/build-infra/pull/32) |  | actionable |  |
| 2026-09-02 | OAI/build-infra | pr | [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/35) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-09-02 | OAI/build-infra | commit | [Merge pull request #35 from OAI/dependabot/npm_and_yarn/publishing-e8f8b6188d](https://github.com/OAI/build-infra/commit/d4828b62866309262ff16d9925b9223956e09989) | spec | watch | Conformance / schema validation |
| 2026-09-02 | OAI/build-infra | pr | [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/34) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-02 | OAI/build-infra | commit | [Merge pull request #34 from OAI/dependabot/npm_and_yarn/markdown-a6d6531f3e](https://github.com/OAI/build-infra/commit/2a400798cfb7e6158fdb84c6d296c8d3e3830c9d) |  | watch |  |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) | graphql, schema, spec | watch | Roadmap GraphQL step type |
| 2026-09-02 | OAI/Arazzo-Specification | issue | [Meeting: Sept 2nd, 2026](https://github.com/OAI/Arazzo-Specification/issues/561) | spec | watch | Conformance / schema validation |
| 2026-09-02 | usearazzo/arazzo-toolkit | pr | [feat(parser): expose parseRuntimeExpression and parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/pull/130) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-02 | usearazzo/arazzo-toolkit | issue | [parser: expose parsing interfaces for runtime expressions and criterion conditions](https://github.com/usearazzo/arazzo-toolkit/issues/99) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-02 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22157)](https://github.com/jentic/jentic-public-apis/commit/8de283270b042e9d157ac8f978c1d390dde84320) |  | watch |  |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22132 (#22133)](https://github.com/jentic/jentic-public-apis/commit/a2d3eccbada1047351652528282e68b40b6e1df9) | spec | watch | Conformance / schema validation |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22130 (#22131)](https://github.com/jentic/jentic-public-apis/commit/dc789e0560b0a41eb0f051ce955a06a7efbf94c3) | spec | watch | Conformance / schema validation |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22126 (#22129)](https://github.com/jentic/jentic-public-apis/commit/774aaaebd32b3c86f3e7ddf79a55c73bf8a5bcec) | spec | watch | Conformance / schema validation |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22125 (#22128)](https://github.com/jentic/jentic-public-apis/commit/debbb672ed7f5780b4c01ad4f88286236318d4e8) | spec | watch | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/respect-core@2.51.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.51.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.4](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.4) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/cli@2.51.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.51.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-02 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22127)](https://github.com/jentic/jentic-public-apis/commit/e1fb5a1d802a394c031a4ffe7edc80de51c2f3fc) |  | watch |  |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22120 (#22121)](https://github.com/jentic/jentic-public-apis/commit/033ef77d7a9e0367dc1ddf42f85d55196fd31a1d) | spec | watch | Conformance / schema validation |
| 2026-09-02 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22116 (#22117)](https://github.com/jentic/jentic-public-apis/commit/00b9c4d5ca54ae37528fa683db1b48f001949aa7) | spec | watch | Conformance / schema validation |
| 2026-09-02 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22119)](https://github.com/jentic/jentic-public-apis/commit/68a72b2687fa92a42452a3240439f46174cfc1e7) |  | watch |  |
| 2026-09-02 | jentic/jentic-public-apis | commit | [Add PostHog Data Warehouse (external_data_sources) endpoints via overlay (#22061)](https://github.com/jentic/jentic-public-apis/commit/4e06158f6e75ba44a5b692c0d231f2eb8ac4fc55) | spec | watch | Conformance / schema validation |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) | grpc, schema, spec | watch | Roadmap gRPC step type |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [v1.2-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/566) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [v1.1-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/565) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/respect-core@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.50.0) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.3) | spec | actionable | Conformance / schema validation |
| 2026-09-02 | Redocly/redocly-cli | release | [@redocly/cli@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.50.0) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/564) | spec | watch | Conformance / schema validation |
| 2026-09-02 | OAI/Arazzo-Specification | pr | [dev: sync with main](https://github.com/OAI/Arazzo-Specification/pull/562) | spec | actionable | Conformance / schema validation |
| 2026-09-01 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-01 (#195)](https://github.com/OAI/landscape/commit/4fc2221c5c4d4f30734dc760f05bda457cbebc3b) |  | watch |  |
| 2026-09-01 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump webpack from 5.109.2 to 5.110.1](https://github.com/usearazzo/arazzo-toolkit/pull/132) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-01 | OAI/build-infra | commit | [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/commit/a190c09df5c7276537d20ba9c3b5015b0abd9b90) | spec | watch | Conformance / schema validation |
| 2026-09-01 | OAI/build-infra | commit | [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/commit/cf86c533fdfba9f373ab83a81e3cc86bf04ebe00) |  | watch |  |
| 2026-09-01 | OAI/OpenAPI-Specification | pr | [v3.3: fix openapi version in these examples](https://github.com/OAI/OpenAPI-Specification/pull/5531) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-09-01 | OAI/tools.openapis.org | pr | [Add OpenDoc UI](https://github.com/OAI/tools.openapis.org/pull/290) | spec | watch | Conformance / schema validation |
| 2026-09-01 | OAI/Arazzo-Specification | issue | [Specify supported AsyncAPI version(s) for `asyncapi` source descriptions (tighten to v3)](https://github.com/OAI/Arazzo-Specification/issues/563) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-01 | OAI/build-infra | commit | [Merge pull request #32 from handrews/revert-recent](https://github.com/OAI/build-infra/commit/74307c709ad2324e3bd336641b02eb58636793e6) |  | watch |  |
| 2026-09-01 | OAI/build-infra | issue | [Consider replacing our link checker](https://github.com/OAI/build-infra/issues/26) | spec | watch | Conformance / schema validation |
| 2026-09-01 | usearazzo/arazzo-toolkit | issue | [Analyze arazzo implementations](https://github.com/usearazzo/arazzo-toolkit/issues/22) | spec | watch | Conformance / schema validation |
| 2026-09-01 | OAI/build-infra | commit | [Reverting recent dependabot changes](https://github.com/OAI/build-infra/commit/2683974edf75d625faf79fefe8bd038907078491) |  | watch |  |
| 2026-09-01 | OAI/build-infra | pr | [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/31) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-09-01 | usearazzo/arazzo-toolkit | issue | [runner: payload replacement targets beyond JSON Pointer — JSONPath, XPath, targetSelectorType, media-type default (Arazzo 1.1.0)](https://github.com/usearazzo/arazzo-toolkit/issues/124) | xml, xpath, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-01 | OAI/build-infra | pr | [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/30) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-09-01 | usearazzo/arazzo-toolkit | issue | [runner: AsyncAPI v3 support — channelPath steps, $message expressions, correlationId, timeout (Arazzo 1.1.0, phase-2 decision)](https://github.com/usearazzo/arazzo-toolkit/issues/122) | spec | watch | Conformance / schema validation |
| 2026-09-01 | usearazzo/arazzo-toolkit | issue | [runner: $self support — runtime expression root, document base URI, identity-based source resolution (Arazzo 1.1.0)](https://github.com/usearazzo/arazzo-toolkit/issues/121) | spec | watch | Conformance / schema validation |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
