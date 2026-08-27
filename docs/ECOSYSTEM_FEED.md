# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-08-27T17:38:12+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 836 (showing 200 newest)
- **Severity:** breaking **155** · actionable **385** · watch **296**
- **Top relevance:** `Conformance / schema validation` (374) · `uncategorized` (122) · `Potential breaking change (2.0)` (106) · `P2-1 CLI binary` (88) · `P1-7 JSON Schema layer` (52)
- **Top sources:** `OAI/Arazzo-Specification` (51) · `strefethen/arazzo-cli` (48) · `OAI/build-infra` (42) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Generated JSON](docs/generated/ecosystem-feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Potential breaking change (2.0) (106)

- `2026-08-27` [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-08-27` [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-27` [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-27` [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-27` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-27` [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `tag` · _breaking,spec_
- `2026-08-27` [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) — `jentic/arazzo-engine` · `tag` · _breaking,spec_
- `2026-08-27` [v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/issues/5460) — `OAI/OpenAPI-Specification` · `issue` · _breaking,schema,spec_
- … and 98 more in this group (see All events table)

### P2-1 CLI binary (18)

- `2026-08-27` [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `tag` · _cli,breaking,spec_
- `2026-08-27` [Bump markdown-it from 14.2.0 to 15.0.0](https://github.com/OAI/OpenAPI-Specification/pull/5461) — `OAI/OpenAPI-Specification` · `pr` · _cli,breaking,spec_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-25` [chore: release v0.4.0](https://github.com/strefethen/arazzo-cli/commit/6217148dba9f279529405ab27277dcf2de9a0cba) — `strefethen/arazzo-cli` · `commit` · _cli,breaking,spec_
- `2026-08-24` [chore(deps): bump github/codeql-action from 4.37.7 to 4.37.8](https://github.com/usearazzo/arazzo-toolkit/pull/88) — `usearazzo/arazzo-toolkit` · `pr` · _cli,actor,breaking,spec_
- `2026-08-22` [runner: execution observability — structured event stream with logging, OpenTelemetry, and stream adapters](https://github.com/usearazzo/arazzo-toolkit/issues/85) — `usearazzo/arazzo-toolkit` · `issue` · _cli,breaking,spec_
- `2026-08-22` [runner: workflow execution profile — analyze what a run needs, pre-configure the runner with the filled artifact](https://github.com/usearazzo/arazzo-toolkit/issues/82) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,breaking,schema,spec_
- `2026-08-21` [chore(deps-dev): bump lint-staged from 16.4.0 to 17.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/72) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,spec_
- … and 10 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (11)

- `2026-08-26` [feat: OpenAPI Normalization Gaps (Spec 4)](https://github.com/Mohammed-Alama/php-arazzo/issues/25) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,breaking,spec_
- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,breaking,spec_
- `2026-07-14` [build(deps): bump actions/setup-node from 6 to 7](https://github.com/OAI/Overlay-Specification/pull/361) — `OAI/Overlay-Specification` · `pr` · _xml,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,schema,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,breaking,spec_
- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2025-09-19` [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) — `OAI/OpenAPI-Specification` · `release` · _xml,breaking,schema,spec_
- … and 3 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (6)

- `2026-08-25` [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-25` [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-04` [Bump content-type from 1.0.5 to 2.0.0](https://github.com/OAI/build-infra/pull/7) — `OAI/build-infra` · `pr` · _actor,breaking_
- `2026-07-28` [build(deps-dev): bump markdownlint-cli2 from 0.23.1 to 0.23.2](https://github.com/OAI/Overlay-Specification/pull/368) — `OAI/Overlay-Specification` · `pr` · _actor,a2a,breaking,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,breaking,schema,spec_
- `2024-04-05` [v6.11.1](https://github.com/stoplightio/spectral/releases/tag/v6.11.1) — `stoplightio/spectral` · `release` · _actor,breaking,schema,spec_

### P0-6 source routing (wsdl type) (5)

- `2026-08-27` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,schema,spec_
- `2026-08-26` [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,breaking,spec_
- `2026-08-20` [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) — `OAI/build-infra` · `pr` · _soap,breaking_
- `2026-08-19` [chore(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Arazzo-Specification/pull/545) — `OAI/Arazzo-Specification` · `pr` · _soap,breaking,spec_
- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,breaking,spec_

### P2-2 MCP server exposure (5)

- `2026-08-10` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,breaking,schema,spec_
- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,breaking,spec_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,breaking,schema,spec_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,breaking,spec_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,breaking,schema,spec_

### Issue #410 loops vs goto (2)

- `2026-08-18` [chore(deps-dev): bump core-js from 3.49.0 to 3.50.0](https://github.com/usearazzo/arazzo-toolkit/pull/69) — `usearazzo/arazzo-toolkit` · `pr` · _loop,breaking,spec_
- `2026-03-30` [Feat: Marketing channel strategy for repositioning OAI](https://github.com/OAI/Outreach/issues/72) — `OAI/Outreach` · `issue` · _loop,breaking,spec_

### P1-7 JSON Schema layer (1)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,spec_

### Roadmap A2A step type (1)

- `2026-08-18` [build(deps-dev): update pestphp/pest requirement from ^4.0 to ^5.1](https://github.com/Mohammed-Alama/php-arazzo/pull/8) — `Mohammed-Alama/php-arazzo` · `pr` · _a2a,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (233)

- `2026-08-27` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-27` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-27` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-27` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-27` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-27` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-27` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-27` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 225 more in this group (see All events table)

### uncategorized (48)

- `2026-08-27` [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-27` [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) — `Redocly/redocly-cli` · `tag` · _no tags_
- … and 40 more in this group (see All events table)

### P2-1 CLI binary (43)

- `2026-08-27` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-27` [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- … and 35 more in this group (see All events table)

### P1-7 JSON Schema layer (26)

- `2026-08-27` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [v3.3: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5518) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [@redocly/openapi-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-26` [@redocly/cli@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-24` [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5510) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [3.2: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5515) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-20` [feat: Decouple HTTP dispatching using OpenAPI Executor](https://github.com/Mohammed-Alama/php-arazzo/pull/11) — `Mohammed-Alama/php-arazzo` · `pr` · _schema,spec_
- … and 18 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (15)

- `2026-08-24` [chore(deps-dev): bump @microsoft/api-extractor from 7.58.12 to 7.59.0](https://github.com/usearazzo/arazzo-toolkit/pull/89) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-21` [feat(runner): support cross-document workflowId references](https://github.com/usearazzo/arazzo-toolkit/pull/73) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-18` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,schema,spec_
- `2026-08-17` [feat(runner): execute a retry action's stepId/workflowId reference before each retry attempt](https://github.com/usearazzo/arazzo-toolkit/pull/63) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-13` [Fix/sync lockfile packaged lock](https://github.com/OAI/build-infra/pull/19) — `OAI/build-infra` · `pr` · _human,spec_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- `2026-08-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-07-31` [2.51.1](https://github.com/specmatic/specmatic/releases/tag/2.51.1) — `Specmatic/specmatic` · `release` · _actor,schema,spec_
- … and 7 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (12)

- `2026-08-26` [feat: Docker-based isolated dev environments (apptree)](https://github.com/Mohammed-Alama/php-arazzo/pull/28) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,cli,spec_
- `2026-08-26` [refactor: flatten Runner module into 23 top-level sibling modules](https://github.com/Mohammed-Alama/php-arazzo/pull/21) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,actor,schema,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,schema,spec_
- `2026-07-25` [2026 07 25 core 34 arazzo 1.1.0 spec](https://github.com/Mohammed-Alama/php-arazzo/pull/3) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,schema,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,spec_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,spec_
- … and 4 more in this group (see All events table)

### Issue #410 loops vs goto (3)

- `2026-08-24` [chore(deps-dev): bump eslint from 10.8.1 to 10.9.0](https://github.com/usearazzo/arazzo-toolkit/pull/91) — `usearazzo/arazzo-toolkit` · `pr` · _loop,spec_
- `2026-08-18` [feat(runner): support step-level goto to a workflowId as a one-way transfer](https://github.com/usearazzo/arazzo-toolkit/pull/66) — `usearazzo/arazzo-toolkit` · `pr` · _loop,spec_
- `2026-08-10` [feat: adds reusable actions](https://github.com/OAI/Overlay-Specification/pull/296) — `OAI/Overlay-Specification` · `pr` · _loop,spec_

### P0-6 source routing (wsdl type) (3)

- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

### P2-2 MCP server exposure (1)

- `2026-07-17` [2.50.1](https://github.com/specmatic/specmatic/releases/tag/2.50.1) — `Specmatic/specmatic` · `release` · _mcp,spec_

### Roadmap A2A step type (1)

- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_


## Watch — context (commits/issues/checksums)

### Conformance / schema validation (141)

- `2026-08-27` [openapi.tools checksum 8815db2e440e](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-08-26` [chore: add storage/quality-history.jsonl for failure budget trend (G9)](https://github.com/Mohammed-Alama/php-arazzo/issues/31) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [ci: wire conformance matrix into pre-commit hook (G3)](https://github.com/Mohammed-Alama/php-arazzo/issues/30) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [ci: populate quality-gates.json and establish MSI baseline (G1-G2)](https://github.com/Mohammed-Alama/php-arazzo/issues/29) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [chore: delete 7 dead exception classes (G14)](https://github.com/Mohammed-Alama/php-arazzo/issues/41) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [test: add tests for Validator (95% → 100%) (G13)](https://github.com/Mohammed-Alama/php-arazzo/issues/40) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [test: add tests for Telemetry (50% → 100%) (G12)](https://github.com/Mohammed-Alama/php-arazzo/issues/39) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-08-26` [test: add tests for Laravel/Bindings (33% → 100%) (G10)](https://github.com/Mohammed-Alama/php-arazzo/issues/37) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- … and 133 more in this group (see All events table)

### uncategorized (74)

- `2026-08-27` [Update Landscape from LFX 2026-08-27 (#189)](https://github.com/OAI/landscape/commit/ba7876647a80d39c2487d8c25cdf5861bcd1dfdf) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-27` [Rebuild apis.json, scores.json, and API browsing indexes (#22091)](https://github.com/jentic/jentic-public-apis/commit/2460dfc56369dfcaf8820fcbb5858436217a95d7) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-26` [Rebuild apis.json, scores.json, and API browsing indexes (#22090)](https://github.com/jentic/jentic-public-apis/commit/eefc4b13250bb1a1289c689fd9da6327cf8e500c) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-26` [Rebuild apis.json, scores.json, and API browsing indexes (#22089)](https://github.com/jentic/jentic-public-apis/commit/2144ea808bcfecebccde643674831ea79e876a8b) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-25` [Update Landscape from LFX 2026-08-25 (#188)](https://github.com/OAI/landscape/commit/8e1856983c3e1b0aa459fd1f26f56091d58a4f2d) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-25` [Rebuild apis.json, scores.json, and API browsing indexes (#22084)](https://github.com/jentic/jentic-public-apis/commit/cd9dc22a0209acbec4bfac0b5b9bdf7ef43e0b45) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-25` [Rebuild apis.json, scores.json, and API browsing indexes (#22081)](https://github.com/jentic/jentic-public-apis/commit/4b6a7e3ed01524ca366de0e340e6464cc4c8dc20) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-24` [Update Landscape from LFX 2026-08-24 (#187)](https://github.com/OAI/landscape/commit/7bbb234a9420058e987566baac8129a727e904fb) — `OAI/landscape` · `commit` · _no tags_
- … and 66 more in this group (see All events table)

### P2-1 CLI binary (27)

- `2026-08-26` [When two or more sourceDescriptions are provided with (local) OpenAPI specs, only the first spec's base URL is shown in dry-run for all calls by OperationId.](https://github.com/strefethen/arazzo-cli/issues/5) — `strefethen/arazzo-cli` · `issue` · _cli,spec_
- `2026-08-26` [fix(conformance): scope the claim to type: openapi, not "non-arazzo"](https://github.com/strefethen/arazzo-cli/commit/8f2217c6fe38be5117543529f367b5bfc0a0d606) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [fix(conformance): re-own the operationPath claim and cover the file url read](https://github.com/strefethen/arazzo-cli/commit/47d0b0de1ac199e9f29c498c4774069c1debdd6f) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: install CLI after release push](https://github.com/strefethen/arazzo-cli/commit/73ed5b90d105d595ddad4cdd0e8e08a5df3a8a27) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: release v0.5.0](https://github.com/strefethen/arazzo-cli/commit/9a405456aa58b3c48736740400fd373b42227e4e) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [docs(readme): link the examples catalog instead of restating it](https://github.com/strefethen/arazzo-cli/commit/090672a03a487f033bd175d33b6cd6cf81409262) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [docs(examples): catalog every example spec and fix stale run commands](https://github.com/strefethen/arazzo-cli/commit/bc12c67c58e50133d9a6893a6d9251cd618a737a) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [fix(runtime): resolve each parameter once, at the site that owns it](https://github.com/strefethen/arazzo-cli/commit/c7a0392952ed5722706b1787ca1f7c950ffd93ed) — `strefethen/arazzo-cli` · `commit` · _cli,loop,spec_
- … and 19 more in this group (see All events table)

### P1-7 JSON Schema layer (25)

- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5526) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5525) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Make patch optional in openapi field.](https://github.com/OAI/OpenAPI-Specification/pull/4929) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [feat: Transport Failure Handling — typed exception hierarchy (Spec 3)](https://github.com/Mohammed-Alama/php-arazzo/issues/24) — `Mohammed-Alama/php-arazzo` · `issue` · _schema,spec_
- `2026-08-25` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _schema,spec_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _schema,spec_
- `2026-08-21` [Support for Sensitive/PII/Personal Data](https://github.com/OAI/sig-security/issues/27) — `OAI/sig-security` · `issue` · _schema,spec_
- … and 17 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-08-26` [feat: Documentation, CI, Release Readiness (Spec 6)](https://github.com/Mohammed-Alama/php-arazzo/issues/27) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-08-26` [refactor: reduce coupling between Validator→Spec and Validator→Expression (G16)](https://github.com/Mohammed-Alama/php-arazzo/issues/43) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-08-26` [refactor: investigate and reduce churn hotspots (G15)](https://github.com/Mohammed-Alama/php-arazzo/issues/42) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-08-24` [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-21` [runner: support cross-document workflowId references](https://github.com/usearazzo/arazzo-toolkit/issues/64) — `usearazzo/arazzo-toolkit` · `issue` · _actor,spec_
- `2026-08-21` [Endpoint-level and field-level role/permission support](https://github.com/OAI/sig-security/issues/35) — `OAI/sig-security` · `issue` · _actor,spec_
- `2026-08-21` [Support describing security keys in OAS](https://github.com/OAI/sig-security/issues/20) — `OAI/sig-security` · `issue` · _human,spec_
- `2026-08-19` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor,spec_
- … and 5 more in this group (see All events table)

### Issue #410 loops vs goto (6)

- `2026-08-26` [feat: Canonical Execution Core — unify sync/queue engines (Spec 1)](https://github.com/Mohammed-Alama/php-arazzo/issues/22) — `Mohammed-Alama/php-arazzo` · `issue` · _loop,spec_
- `2026-08-21` [runner: give StepAttemptOutcome a deliberate home](https://github.com/usearazzo/arazzo-toolkit/issues/75) — `usearazzo/arazzo-toolkit` · `issue` · _loop,spec_
- `2026-08-04` [OpenAPI - publish v3.1-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/129) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-07-27` [Arazzo - publish v1.2-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/109) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,spec_
- `2026-04-02` [Feat: Launch monthly member drop-in clinics (EU and US timezones)](https://github.com/OAI/Outreach/issues/69) — `OAI/Outreach` · `issue` · _loop,spec_

### Roadmap A2A step type (4)

- `2026-08-20` [docs: update CLAUDE.md to reflect current reality](https://github.com/usearazzo/website/commit/ac65d199b313b25b1eea2a19af2881573634246e) — `usearazzo/website` · `commit` · _a2a,spec_
- `2026-08-18` [Document Yarn workflows](https://github.com/OAI/build-infra/commit/f1cb0e050a823e1a2a188fdbb0b4356cb694e7da) — `OAI/build-infra` · `commit` · _a2a_
- `2026-08-06` [Package lockfile snapshot for consumer sync](https://github.com/OAI/build-infra/commit/e467f7a2ade183176cc1d1f46b75857323c87245) — `OAI/build-infra` · `commit` · _a2a_
- `2026-07-22` [build(deps): bump ruby/setup-ruby from 1.319.0 to 1.320.0](https://github.com/OAI/spec.openapis.org/commit/3ccc930eaa2a78c31ea19f09e0dbea2639b571ed) — `OAI/spec.openapis.org` · `commit` · _a2a,spec_

### P0-6 source routing (wsdl type) (2)

- `2026-08-26` [feat: Testing and Adapter Parity (Spec 5)](https://github.com/Mohammed-Alama/php-arazzo/issues/26) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,spec_
- `2026-08-26` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-08-05` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,spec_

### P2-2 MCP server exposure (2)

- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,loop,spec_
- `2026-08-26` [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-08-27 | openapi.tools | tool_collection | [openapi.tools checksum 8815db2e440e](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-08-27 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.8.0](https://github.com/speclynx/apidom/releases/tag/v4.8.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.7.1](https://github.com/speclynx/apidom/releases/tag/v4.7.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | speclynx/apidom | tag | [tag v4.7.0](https://github.com/speclynx/apidom/releases/tag/v4.7.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.45.1](https://github.com/Specmatic/specmatic/releases/tag/2.45.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Specmatic/specmatic | tag | [tag 2.45.0](https://github.com/Specmatic/specmatic/releases/tag/2.45.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) |  | actionable |  |
| 2026-08-27 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) |  | actionable |  |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | issue | [v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/issues/5460) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5526) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5525) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [v3.3-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5524) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [v3.2-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5523) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [v3.1-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5522) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5521) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Make patch optional in openapi field.](https://github.com/OAI/OpenAPI-Specification/pull/4929) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Bump respec from 37.1.0 to 37.2.0](https://github.com/OAI/OpenAPI-Specification/pull/5423) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Bump markdown-it from 14.2.0 to 15.0.0](https://github.com/OAI/OpenAPI-Specification/pull/5461) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-27 | OAI/OpenAPI-Specification | pr | [Bump @umbrelladocs/linkspector from 0.5.5 to 0.5.6](https://github.com/OAI/OpenAPI-Specification/pull/5452) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | OAI/Arazzo-Specification | pr | [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) | soap, wsdl, breaking, schema, spec | breaking | P0-6 source routing (wsdl type) |
| 2026-08-27 | Mohammed-Alama/php-arazzo | pr | [chore: implement Phase 0 quality gates infrastructure](https://github.com/Mohammed-Alama/php-arazzo/pull/44) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Redocly/redocly-cli | release | [@redocly/respect-core@2.49.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.49.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.1) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | Redocly/redocly-cli | release | [@redocly/cli@2.49.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-27 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-27 (#189)](https://github.com/OAI/landscape/commit/ba7876647a80d39c2487d8c25cdf5861bcd1dfdf) |  | watch |  |
| 2026-08-27 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22091)](https://github.com/jentic/jentic-public-apis/commit/2460dfc56369dfcaf8820fcbb5858436217a95d7) |  | watch |  |
| 2026-08-27 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/550) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | strefethen/arazzo-cli | issue | [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) | mcp, cli, loop, spec | watch | P2-2 MCP server exposure |
| 2026-08-26 | OAI/OpenAPI-Specification | pr | [v3.3: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5518) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-26 | strefethen/arazzo-cli | issue | [When two or more sourceDescriptions are provided with (local) OpenAPI specs, only the first spec's base URL is shown in dry-run for all calls by OperationId.](https://github.com/strefethen/arazzo-cli/issues/5) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/27) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | OAI/build-infra | pr | [Bump respec from 37.2.0 to 37.3.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/20) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(conformance): scope the claim to type: openapi, not "non-arazzo"](https://github.com/strefethen/arazzo-cli/commit/8f2217c6fe38be5117543529f367b5bfc0a0d606) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(conformance): re-own the operationPath claim and cover the file url read](https://github.com/strefethen/arazzo-cli/commit/47d0b0de1ac199e9f29c498c4774069c1debdd6f) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) | mcp, cli, spec | watch | P2-2 MCP server exposure |
| 2026-08-26 | strefethen/arazzo-cli | commit | [chore: install CLI after release push](https://github.com/strefethen/arazzo-cli/commit/73ed5b90d105d595ddad4cdd0e8e08a5df3a8a27) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | release | [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [chore: release v0.5.0](https://github.com/strefethen/arazzo-cli/commit/9a405456aa58b3c48736740400fd373b42227e4e) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [chore: add storage/quality-history.jsonl for failure budget trend (G9)](https://github.com/Mohammed-Alama/php-arazzo/issues/31) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [ci: wire conformance matrix into pre-commit hook (G3)](https://github.com/Mohammed-Alama/php-arazzo/issues/30) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [ci: populate quality-gates.json and establish MSI baseline (G1-G2)](https://github.com/Mohammed-Alama/php-arazzo/issues/29) | spec | watch | Conformance / schema validation |
| 2026-08-26 | strefethen/arazzo-cli | commit | [docs(readme): link the examples catalog instead of restating it](https://github.com/strefethen/arazzo-cli/commit/090672a03a487f033bd175d33b6cd6cf81409262) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [docs(examples): catalog every example spec and fix stale run commands](https://github.com/strefethen/arazzo-cli/commit/bc12c67c58e50133d9a6893a6d9251cd618a737a) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(runtime): resolve each parameter once, at the site that owns it](https://github.com/strefethen/arazzo-cli/commit/c7a0392952ed5722706b1787ca1f7c950ffd93ed) | cli, loop, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [Fix a tooling gap with a comment](https://github.com/strefethen/arazzo-cli/commit/748925a93971151290a4b17de01172cc47e835e6) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(runtime): say whose limit each operationId refusal is](https://github.com/strefethen/arazzo-cli/commit/b8cdb8bd721540c0caa88f40630e9b9e1a897df9) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | Mohammed-Alama/php-arazzo | pr | [feat: Docker-based isolated dev environments (apptree)](https://github.com/Mohammed-Alama/php-arazzo/pull/28) | xml, cli, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: Documentation, CI, Release Readiness (Spec 6)](https://github.com/Mohammed-Alama/php-arazzo/issues/27) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: Testing and Adapter Parity (Spec 5)](https://github.com/Mohammed-Alama/php-arazzo/issues/26) | soap, wsdl, xml, spec | watch | P0-6 source routing (wsdl type) |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: OpenAPI Normalization Gaps (Spec 4)](https://github.com/Mohammed-Alama/php-arazzo/issues/25) | xml, breaking, spec | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: Transport Failure Handling — typed exception hierarchy (Spec 3)](https://github.com/Mohammed-Alama/php-arazzo/issues/24) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: Named Source Resolution + OpenAPI (Spec 2)](https://github.com/Mohammed-Alama/php-arazzo/issues/23) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: Canonical Execution Core — unify sync/queue engines (Spec 1)](https://github.com/Mohammed-Alama/php-arazzo/issues/22) | loop, spec | watch | Issue #410 loops vs goto |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) | soap, wsdl, xml, xpath, breaking, spec | breaking | P0-6 source routing (wsdl type) |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) | soap, xml, xpath, spec | watch | P0-6 source routing (wsdl type) |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [refactor: reduce coupling between Validator→Spec and Validator→Expression (G16)](https://github.com/Mohammed-Alama/php-arazzo/issues/43) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [refactor: investigate and reduce churn hotspots (G15)](https://github.com/Mohammed-Alama/php-arazzo/issues/42) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [chore: delete 7 dead exception classes (G14)](https://github.com/Mohammed-Alama/php-arazzo/issues/41) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [test: add tests for Validator (95% → 100%) (G13)](https://github.com/Mohammed-Alama/php-arazzo/issues/40) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [test: add tests for Telemetry (50% → 100%) (G12)](https://github.com/Mohammed-Alama/php-arazzo/issues/39) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [test: add tests for Console (55% → 100%) (G11)](https://github.com/Mohammed-Alama/php-arazzo/issues/38) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | Mohammed-Alama/php-arazzo | issue | [test: add tests for Laravel/Bindings (33% → 100%) (G10)](https://github.com/Mohammed-Alama/php-arazzo/issues/37) | spec | watch | Conformance / schema validation |
| 2026-08-26 | Mohammed-Alama/php-arazzo | pr | [refactor: flatten Runner module into 23 top-level sibling modules](https://github.com/Mohammed-Alama/php-arazzo/pull/21) | xml, xpath, actor, schema, spec | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22090)](https://github.com/jentic/jentic-public-apis/commit/eefc4b13250bb1a1289c689fd9da6327cf8e500c) |  | watch |  |
| 2026-08-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22085 (#22086)](https://github.com/jentic/jentic-public-apis/commit/94982c77d9eae46f7ec25a61b09f5366e56dffe9) | spec | watch | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22089)](https://github.com/jentic/jentic-public-apis/commit/2144ea808bcfecebccde643674831ea79e876a8b) |  | watch |  |
| 2026-08-26 | jentic/jentic-public-apis | commit | [feat: Replace OpenAPI spec for Issue #22030 (#22033)](https://github.com/jentic/jentic-public-apis/commit/272aa1d182d60a13ad7507f262ed43f4a6fffaea) | spec | watch | Conformance / schema validation |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(runtime): stop claiming the spec forbids an asyncapi operationId](https://github.com/strefethen/arazzo-cli/commit/94965cb451316d7c9755e4490c2991b4563cd0eb) | cli, a2a, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(spec): parse a qualified operationId by the vendored source-reference ABNF](https://github.com/strefethen/arazzo-cli/commit/4a2acecaa2a8b071ab2167f2c47c1b0c04563ff4) | cli, a2a, grpc, spec | watch | P2-1 CLI binary |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/respect-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.48.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.48.0) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/cli@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.48.0) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-26 | strefethen/arazzo-cli | commit | [fix(runtime): resolve operationId through its declared OpenAPI source](https://github.com/strefethen/arazzo-cli/commit/4cb5b63244dddbf894e63f1111aa282f867a8167) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [docs(plans): record the ac-c5105 runtime/validate split in the conformance plan](https://github.com/strefethen/arazzo-cli/commit/75ba0640f4c756a3c4dd6486a0161f802fa083de) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-26 | strefethen/arazzo-cli | commit | [refactor(validate): externalize the inline unit-test module into fragments](https://github.com/strefethen/arazzo-cli/commit/3afc4be67538ab092f8f3fdb632a7695c69a8ef3) | cli, actor, spec | watch | P2-1 CLI binary |
| 2026-08-26 | speakeasy-api/openapi | release | [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) | cli, a2a, schema, spec | actionable | P2-1 CLI binary |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`
