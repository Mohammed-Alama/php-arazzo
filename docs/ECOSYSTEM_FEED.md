# Ecosystem Feed — Human Dashboard

<<<<<<< HEAD
> **Generated:** 2026-08-26T07:08:49+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
=======
> **Generated:** 2026-08-26T08:18:55+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 824 (showing 200 newest)
- **Severity:** breaking **149** · actionable **386** · watch **289**
<<<<<<< HEAD
- **Top relevance:** `Conformance / schema validation` (375) · `uncategorized` (122) · `Potential breaking change (2.0)` (103) · `P2-1 CLI binary` (81) · `P1-7 JSON Schema layer` (49)
=======
- **Top relevance:** `Conformance / schema validation` (373) · `uncategorized` (122) · `Potential breaking change (2.0)` (103) · `P2-1 CLI binary` (81) · `P1-7 JSON Schema layer` (51)
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)
- **Top sources:** `strefethen/arazzo-cli` (53) · `OAI/Arazzo-Specification` (53) · `OAI/build-infra` (42) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Generated JSON](docs/generated/ecosystem-feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Potential breaking change (2.0) (103)

- `2026-08-26` [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-08-26` [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-26` [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-26` [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-26` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-26` [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `tag` · _breaking,spec_
- `2026-08-26` [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) — `jentic/arazzo-engine` · `tag` · _breaking,spec_
- `2026-08-25` [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) — `OAI/Overlay-Specification` · `pr` · _breaking,spec_
- … and 95 more in this group (see All events table)

### P2-1 CLI binary (16)

- `2026-08-25` [chore: release v0.4.0](https://github.com/strefethen/arazzo-cli/commit/6217148dba9f279529405ab27277dcf2de9a0cba) — `strefethen/arazzo-cli` · `commit` · _cli,breaking,spec_
- `2026-08-25` [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `tag` · _cli,breaking,spec_
- `2026-08-24` [chore(deps): bump github/codeql-action from 4.37.7 to 4.37.8](https://github.com/usearazzo/arazzo-toolkit/pull/88) — `usearazzo/arazzo-toolkit` · `pr` · _cli,actor,breaking,spec_
- `2026-08-22` [runner: execution observability — structured event stream with logging, OpenTelemetry, and stream adapters](https://github.com/usearazzo/arazzo-toolkit/issues/85) — `usearazzo/arazzo-toolkit` · `issue` · _cli,breaking,spec_
- `2026-08-22` [runner: workflow execution profile — analyze what a run needs, pre-configure the runner with the filled artifact](https://github.com/usearazzo/arazzo-toolkit/issues/82) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,breaking,schema,spec_
- `2026-08-21` [chore(deps-dev): bump lint-staged from 16.4.0 to 17.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/72) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,spec_
- `2026-08-18` [chore(deps-dev): bump lerna from 9.0.7 to 10.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/45) — `usearazzo/arazzo-toolkit` · `pr` · _cli,a2a,breaking,spec_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,breaking,schema,spec_
- … and 8 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (10)

- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,breaking,spec_
- `2026-07-14` [build(deps): bump actions/setup-node from 6 to 7](https://github.com/OAI/Overlay-Specification/pull/361) — `OAI/Overlay-Specification` · `pr` · _xml,breaking,spec_
- `2026-05-17` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,breaking,spec_
- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2025-09-19` [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) — `OAI/OpenAPI-Specification` · `release` · _xml,breaking,schema,spec_
- `2024-09-25` [Arazzo 1.0.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,schema,spec_
- … and 2 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (6)

- `2026-08-25` [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-25` [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-04` [Bump content-type from 1.0.5 to 2.0.0](https://github.com/OAI/build-infra/pull/7) — `OAI/build-infra` · `pr` · _actor,breaking_
- `2026-07-28` [build(deps-dev): bump markdownlint-cli2 from 0.23.1 to 0.23.2](https://github.com/OAI/Overlay-Specification/pull/368) — `OAI/Overlay-Specification` · `pr` · _actor,a2a,breaking,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,breaking,schema,spec_
- `2024-04-05` [v6.11.1](https://github.com/stoplightio/spectral/releases/tag/v6.11.1) — `stoplightio/spectral` · `release` · _actor,breaking,schema,spec_

### P2-2 MCP server exposure (6)

- `2026-08-10` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,breaking,schema,spec_
- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,breaking,spec_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,breaking,schema,spec_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,breaking,spec_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,breaking,schema,spec_
- `2025-11-27` [1.2 - start of discussion/ideas/breaking changes](https://github.com/OAI/Arazzo-Specification/issues/410) — `OAI/Arazzo-Specification` · `issue` · _mcp,actor,human,loop,breaking,spec_

### P0-6 source routing (wsdl type) (4)

- `2026-08-20` [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) — `OAI/build-infra` · `pr` · _soap,breaking_
- `2026-08-19` [chore(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Arazzo-Specification/pull/545) — `OAI/Arazzo-Specification` · `pr` · _soap,breaking,spec_
- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,breaking,spec_
- `2026-07-27` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,schema,spec_

### Issue #410 loops vs goto (2)

- `2026-08-18` [chore(deps-dev): bump core-js from 3.49.0 to 3.50.0](https://github.com/usearazzo/arazzo-toolkit/pull/69) — `usearazzo/arazzo-toolkit` · `pr` · _loop,breaking,spec_
- `2026-03-30` [Feat: Marketing channel strategy for repositioning OAI](https://github.com/OAI/Outreach/issues/72) — `OAI/Outreach` · `issue` · _loop,breaking,spec_

### P1-7 JSON Schema layer (1)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,spec_

### Roadmap A2A step type (1)

- `2026-08-18` [build(deps-dev): update pestphp/pest requirement from ^4.0 to ^5.1](https://github.com/Mohammed-Alama/php-arazzo/pull/8) — `Mohammed-Alama/php-arazzo` · `pr` · _a2a,breaking,spec_


## Actionable — new releases/tags to review

<<<<<<< HEAD
### Conformance / schema validation (237)
=======
### Conformance / schema validation (235)
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)

- `2026-08-26` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-26` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-26` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-26` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-26` [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-26` [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-26` [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) — `JaredCE/Arazzo-Generator` · `tag` · _spec_
- `2026-08-26` [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) — `speclynx/apidom` · `tag` · _spec_
<<<<<<< HEAD
- … and 229 more in this group (see All events table)
=======
- … and 227 more in this group (see All events table)
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)

### uncategorized (48)

- `2026-08-26` [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-26` [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) — `Redocly/redocly-cli` · `tag` · _no tags_
- … and 40 more in this group (see All events table)

### P2-1 CLI binary (42)

- `2026-08-26` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,spec_
- `2026-08-25` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-25` [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- … and 34 more in this group (see All events table)

<<<<<<< HEAD
### P1-7 JSON Schema layer (26)

=======
### P1-7 JSON Schema layer (28)

- `2026-08-26` [@redocly/openapi-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-26` [@redocly/cli@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)
- `2026-08-24` [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5510) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [3.2: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5515) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-20` [feat: Decouple HTTP dispatching using OpenAPI Executor](https://github.com/Mohammed-Alama/php-arazzo/pull/11) — `Mohammed-Alama/php-arazzo` · `pr` · _schema,spec_
- `2026-08-20` [Use build-infra schema test helpers on dev branches](https://github.com/OAI/OpenAPI-Specification/pull/5499) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-20` [Migrate to yarn to avoid npm workarounds](https://github.com/OAI/OpenAPI-Specification/pull/5503) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
<<<<<<< HEAD
- `2026-08-20` [Remove various npm hacks, switch to yarn for package management](https://github.com/OAI/build-infra/pull/24) — `OAI/build-infra` · `pr` · _schema,spec_
- `2026-08-18` [Remove stray duplicate schema test on the 3.1 branch.](https://github.com/OAI/OpenAPI-Specification/pull/5496) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- … and 18 more in this group (see All events table)
=======
- … and 20 more in this group (see All events table)
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)

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

### P1-6 payload XPath / P0-5 XPath criteria (10)

- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,schema,spec_
- `2026-07-25` [2026 07 25 core 34 arazzo 1.1.0 spec](https://github.com/Mohammed-Alama/php-arazzo/pull/3) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,schema,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,spec_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,spec_
- `2026-04-06` [v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `release` · _xml,cli,loop,spec_
- `2026-03-13` [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,spec_
- … and 2 more in this group (see All events table)

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

### Conformance / schema validation (138)

- `2026-08-25` [feat: Import OpenAPI spec from Issue #22079 (#22082)](https://github.com/jentic/jentic-public-apis/commit/a5fc3044cf7475c7e9e6913049f6f950beaa8975) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-08-25` [openapi.tools checksum 8815db2e440e](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-08-24` [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) — `usearazzo/website` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) — `usearazzo/website.ecosystem.atom` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) — `usearazzo/website` · `commit` · _spec_
- `2026-08-24` [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) — `usearazzo/website.ecosystem.atom` · `commit` · _spec_
- `2026-08-24` [feat: Import OpenAPI spec from Issue #22056 (#22076)](https://github.com/jentic/jentic-public-apis/commit/f7d85ecbd2c7c55bc2bdffd5bfdc30d9a82e2252) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-08-23` [Tool discovery (`full` workflow) has failed on every scheduled run since 2025-07-13 — dead source URL in metadata.json](https://github.com/OAI/tools.openapis.org/issues/285) — `OAI/tools.openapis.org` · `issue` · _spec_
- … and 130 more in this group (see All events table)

### uncategorized (74)

- `2026-08-25` [Update Landscape from LFX 2026-08-25 (#188)](https://github.com/OAI/landscape/commit/8e1856983c3e1b0aa459fd1f26f56091d58a4f2d) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-25` [Rebuild apis.json, scores.json, and API browsing indexes (#22084)](https://github.com/jentic/jentic-public-apis/commit/cd9dc22a0209acbec4bfac0b5b9bdf7ef43e0b45) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-25` [Rebuild apis.json, scores.json, and API browsing indexes (#22081)](https://github.com/jentic/jentic-public-apis/commit/4b6a7e3ed01524ca366de0e340e6464cc4c8dc20) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-24` [Update Landscape from LFX 2026-08-24 (#187)](https://github.com/OAI/landscape/commit/7bbb234a9420058e987566baac8129a727e904fb) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-24` [Add overlay to set apify.com info.version (fixes import: missing version) (#22078)](https://github.com/jentic/jentic-public-apis/commit/2ec421d6d468fd9507560f8592b9fe32aeed4de5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-24` [Rebuild apis.json, scores.json, and API browsing indexes (#22077)](https://github.com/jentic/jentic-public-apis/commit/09696d600e182ed04532e5b7de5bded677de0868) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-24` [Rebuild apis.json, scores.json, and API browsing indexes (#22075)](https://github.com/jentic/jentic-public-apis/commit/0d34455cc4252bd49f136724a39d523a5ea5658c) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-23` [Update Landscape from LFX 2026-08-23 (#186)](https://github.com/OAI/landscape/commit/fa54f124ce01a0ecafd424904fafa593914b3e72) — `OAI/landscape` · `commit` · _no tags_
- … and 66 more in this group (see All events table)

### P2-1 CLI binary (23)

- `2026-08-25` [docs(site): update version claims and test count for v0.4.0](https://github.com/strefethen/arazzo-cli/commit/9cfdbe25fd3d961ea6f48c9c4bf6c0ce91ad50c4) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-25` [fix(expr): cache matches patterns and fail non-compiling ones loudly](https://github.com/strefethen/arazzo-cli/commit/291f2ac24f2061808072a6822c6460dd36a0f155) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-25` [docs: drop the removed $env namespace from the README and site](https://github.com/strefethen/arazzo-cli/commit/8363228b722eeba394b226249da6d1e5c630a22d) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-25` [docs(plans): sync audit state after the Aug 22-24 audit work](https://github.com/strefethen/arazzo-cli/commit/3eee30f1573ac93f8a9a63991c02927da5717292) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-25` [chore: ignore target directories at any depth](https://github.com/strefethen/arazzo-cli/commit/f691d30595923714c830945402dfcb3241cf04a6) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-25` [docs: rewrite AGENTS.md around the compliance gate](https://github.com/strefethen/arazzo-cli/commit/4c20d510be7f36a18018110303aef657b3c79ff8) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-24` [fix: remove the non-standard $env expression namespace (ac-9c811)](https://github.com/strefethen/arazzo-cli/commit/1f15ff3716820b3107b01259f9b6c0fe17327798) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-22` [cli: live execution progress rendering built on the runner event stream](https://github.com/usearazzo/arazzo-toolkit/issues/86) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,spec_
- … and 15 more in this group (see All events table)

### P1-7 JSON Schema layer (22)

- `2026-08-24` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _schema,spec_
- `2026-08-23` [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _schema,spec_
- `2026-08-21` [Support for Sensitive/PII/Personal Data](https://github.com/OAI/sig-security/issues/27) — `OAI/sig-security` · `issue` · _schema,spec_
- `2026-08-21` [\[Feature Request\] - Allow payload definition for JWT schema](https://github.com/OAI/sig-security/issues/23) — `OAI/sig-security` · `issue` · _schema_
- `2026-08-21` [v3.3: First pass at explaining SAFs](https://github.com/OAI/OpenAPI-Specification/pull/5447) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-20` [Dev sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5509) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-20` [Bump the hyperjump group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/18) — `OAI/build-infra` · `pr` · _schema_
- … and 14 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (10)

- `2026-08-24` [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-21` [runner: support cross-document workflowId references](https://github.com/usearazzo/arazzo-toolkit/issues/64) — `usearazzo/arazzo-toolkit` · `issue` · _actor,spec_
- `2026-08-21` [Endpoint-level and field-level role/permission support](https://github.com/OAI/sig-security/issues/35) — `OAI/sig-security` · `issue` · _actor,spec_
- `2026-08-21` [Support describing security keys in OAS](https://github.com/OAI/sig-security/issues/20) — `OAI/sig-security` · `issue` · _human,spec_
- `2026-08-19` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor,spec_
- `2026-08-19` [Request for API Version Chaining Feature](https://github.com/OAI/sig-lifecycle/issues/8) — `OAI/sig-lifecycle` · `issue` · _human,spec_
- `2026-08-18` [chore(deps): bump @speclynx/apidom-ns-openapi-3-1 from 5.0.2 to 5.1.0](https://github.com/usearazzo/arazzo-toolkit/pull/46) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-03-31` [Resource: Event promotion and lead capture pack](https://github.com/OAI/Outreach/issues/73) — `OAI/Outreach` · `issue` · _actor,spec_
- … and 2 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (8)

- `2026-08-25` [Merge branch 'claude/magical-blackburn-037091': warn at validate time on rejected XPath versions](https://github.com/strefethen/arazzo-cli/commit/cc29d4247057c0cad06390a6e44567d606915f57) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-08-24` [fix: address review findings on the xpath version advisory](https://github.com/strefethen/arazzo-cli/commit/abf325f900512f3d2885d4946abc88a11f0f266e) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-08-24` [feat: warn at validate time on XPath versions the runtime rejects](https://github.com/strefethen/arazzo-cli/commit/2517b49bfe91a78cf90745249ab67720d7c36768) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,schema,spec_
- `2026-08-24` [Merge branch 'claude/quirky-bell-8a21c5': fail xpath criteria on null context](https://github.com/strefethen/arazzo-cli/commit/b806e659ecfe40af58ea2bb2142087b0a1b65674) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-08-24` [fix: address ac-46638 review findings on the XPath 1.0 boundary](https://github.com/strefethen/arazzo-cli/commit/596ef9e7b3e1dfc52b76a69afd0cc509eb48a7e4) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-08-23` [fix: fail xpath criteria on null context per spec 5.8.11.4.4](https://github.com/strefethen/arazzo-cli/commit/3afe6731b6f93d4095bf419347d6af6ff826b375) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-08-23` [fix: decide xpath criteria by effective boolean value](https://github.com/strefethen/arazzo-cli/commit/301174a63d1148f0d57d60fc8faef857706c9eab) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,spec_

### Issue #410 loops vs goto (5)

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

### P2-2 MCP server exposure (3)

- `2026-08-24` [test: pin steps --json for the unsupported operationPath form](https://github.com/strefethen/arazzo-cli/commit/99affb00c4a6d6353bf33b43021a5af3681f744e) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-08-23` [refactor: derive describe method/target from the canonical classifier](https://github.com/strefethen/arazzo-cli/commit/92d3058cc63c1dcedefbdc78dacceee82daecd16) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,actor,schema,spec_
- `2026-08-05` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,loop,spec_

### P0-6 source routing (wsdl type) (2)

- `2026-08-24` [feat: make xpath.rs the one XPath 1.0 boundary (ac-46638)](https://github.com/strefethen/arazzo-cli/commit/f90dda2ef5b888409f1f86ec90d08fb6cb905845) — `strefethen/arazzo-cli` · `commit` · _soap,xml,xpath,cli,spec_
- `2026-08-24` [fix: evaluate XPath against the document the server sent](https://github.com/strefethen/arazzo-cli/commit/9296f304484956f23483b4f217b6e0fefff98d29) — `strefethen/arazzo-cli` · `commit` · _soap,xml,xpath,cli,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-08-26 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.8.0](https://github.com/speclynx/apidom/releases/tag/v4.8.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.7.1](https://github.com/speclynx/apidom/releases/tag/v4.7.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | speclynx/apidom | tag | [tag v4.7.0](https://github.com/speclynx/apidom/releases/tag/v4.7.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.45.1](https://github.com/Specmatic/specmatic/releases/tag/2.45.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Specmatic/specmatic | tag | [tag 2.45.0](https://github.com/Specmatic/specmatic/releases/tag/2.45.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) |  | actionable |  |
| 2026-08-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) |  | actionable |  |
| 2026-08-26 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
<<<<<<< HEAD
=======
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/respect-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.48.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.48.0) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.0) | spec | actionable | Conformance / schema validation |
| 2026-08-26 | Redocly/redocly-cli | release | [@redocly/cli@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.48.0) | schema, spec | actionable | P1-7 JSON Schema layer |
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)
| 2026-08-26 | speakeasy-api/openapi | release | [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) | cli, a2a, schema, spec | actionable | P2-1 CLI binary |
| 2026-08-25 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-25 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-25 | strefethen/arazzo-cli | commit | [docs(site): update version claims and test count for v0.4.0](https://github.com/strefethen/arazzo-cli/commit/9cfdbe25fd3d961ea6f48c9c4bf6c0ce91ad50c4) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | release | [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | xml, xpath, cli, breaking, spec | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-25 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-25 (#188)](https://github.com/OAI/landscape/commit/8e1856983c3e1b0aa459fd1f26f56091d58a4f2d) |  | watch |  |
| 2026-08-25 | strefethen/arazzo-cli | commit | [chore: release v0.4.0](https://github.com/strefethen/arazzo-cli/commit/6217148dba9f279529405ab27277dcf2de9a0cba) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [fix(expr): cache matches patterns and fail non-compiling ones loudly](https://github.com/strefethen/arazzo-cli/commit/291f2ac24f2061808072a6822c6460dd36a0f155) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [docs: drop the removed $env namespace from the README and site](https://github.com/strefethen/arazzo-cli/commit/8363228b722eeba394b226249da6d1e5c630a22d) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [docs(plans): sync audit state after the Aug 22-24 audit work](https://github.com/strefethen/arazzo-cli/commit/3eee30f1573ac93f8a9a63991c02927da5717292) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [chore: ignore target directories at any depth](https://github.com/strefethen/arazzo-cli/commit/f691d30595923714c830945402dfcb3241cf04a6) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [docs: rewrite AGENTS.md around the compliance gate](https://github.com/strefethen/arazzo-cli/commit/4c20d510be7f36a18018110303aef657b3c79ff8) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-25 | strefethen/arazzo-cli | commit | [Merge branch 'claude/magical-blackburn-037091': warn at validate time on rejected XPath versions](https://github.com/strefethen/arazzo-cli/commit/cc29d4247057c0cad06390a6e44567d606915f57) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-25 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22084)](https://github.com/jentic/jentic-public-apis/commit/cd9dc22a0209acbec4bfac0b5b9bdf7ef43e0b45) |  | watch |  |
| 2026-08-25 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22079 (#22082)](https://github.com/jentic/jentic-public-apis/commit/a5fc3044cf7475c7e9e6913049f6f950beaa8975) | spec | watch | Conformance / schema validation |
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
| 2026-08-24 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @microsoft/api-extractor from 7.58.12 to 7.59.0](https://github.com/usearazzo/arazzo-toolkit/pull/89) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-08-24 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump lerna from 10.0.0 to 10.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/87) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump github/codeql-action from 4.37.7 to 4.37.8](https://github.com/usearazzo/arazzo-toolkit/pull/88) | cli, actor, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-24 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/27) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/build-infra | pr | [Bump respec from 37.2.0 to 37.3.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/20) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/build-infra | pr | [Bump content-type from 2.0.0 to 2.1.0](https://github.com/OAI/build-infra/pull/22) | breaking | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/build-infra | pr | [Bump content-type from 2.0.0 to 3.0.0](https://github.com/OAI/build-infra/pull/28) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-24 (#187)](https://github.com/OAI/landscape/commit/7bbb234a9420058e987566baac8129a727e904fb) |  | watch |  |
| 2026-08-24 | strefethen/arazzo-cli | commit | [fix: address review findings on the xpath version advisory](https://github.com/strefethen/arazzo-cli/commit/abf325f900512f3d2885d4946abc88a11f0f266e) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-24 | strefethen/arazzo-cli | commit | [feat: warn at validate time on XPath versions the runtime rejects](https://github.com/strefethen/arazzo-cli/commit/2517b49bfe91a78cf90745249ab67720d7c36768) | xml, xpath, cli, schema, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-24 | strefethen/arazzo-cli | commit | [Merge branch 'claude/quirky-bell-8a21c5': fail xpath criteria on null context](https://github.com/strefethen/arazzo-cli/commit/b806e659ecfe40af58ea2bb2142087b0a1b65674) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-24 | usearazzo/website | commit | [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): emphasized add button](https://github.com/usearazzo/website/commit/924fd967ce4c5edf09b9884396308cc7f71e6ae6) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website | commit | [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) | spec | watch | Conformance / schema validation |
| 2026-08-24 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): extend the list with bpedro links](https://github.com/usearazzo/website/commit/7185516cbc4bd9705692ceebe6eb611db6960d2f) | spec | watch | Conformance / schema validation |
| 2026-08-24 | OAI/OpenAPI-Specification | pr | [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-24 | strefethen/arazzo-cli | commit | [fix: address ac-46638 review findings on the XPath 1.0 boundary](https://github.com/strefethen/arazzo-cli/commit/596ef9e7b3e1dfc52b76a69afd0cc509eb48a7e4) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-24 | jentic/jentic-public-apis | commit | [Add overlay to set apify.com info.version (fixes import: missing version) (#22078)](https://github.com/jentic/jentic-public-apis/commit/2ec421d6d468fd9507560f8592b9fe32aeed4de5) |  | watch |  |
| 2026-08-24 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22077)](https://github.com/jentic/jentic-public-apis/commit/09696d600e182ed04532e5b7de5bded677de0868) |  | watch |  |
| 2026-08-24 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22056 (#22076)](https://github.com/jentic/jentic-public-apis/commit/f7d85ecbd2c7c55bc2bdffd5bfdc30d9a82e2252) | spec | watch | Conformance / schema validation |
| 2026-08-24 | OAI/Overlay-Specification | pr | [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-24 | strefethen/arazzo-cli | commit | [feat: make xpath.rs the one XPath 1.0 boundary (ac-46638)](https://github.com/strefethen/arazzo-cli/commit/f90dda2ef5b888409f1f86ec90d08fb6cb905845) | soap, xml, xpath, cli, spec | watch | P0-6 source routing (wsdl type) |
| 2026-08-24 | strefethen/arazzo-cli | commit | [fix: remove the non-standard $env expression namespace (ac-9c811)](https://github.com/strefethen/arazzo-cli/commit/1f15ff3716820b3107b01259f9b6c0fe17327798) | cli, spec | watch | P2-1 CLI binary |
| 2026-08-24 | strefethen/arazzo-cli | commit | [test: pin steps --json for the unsupported operationPath form](https://github.com/strefethen/arazzo-cli/commit/99affb00c4a6d6353bf33b43021a5af3681f744e) | mcp, cli, spec | watch | P2-2 MCP server exposure |
| 2026-08-24 | strefethen/arazzo-cli | commit | [fix: evaluate XPath against the document the server sent](https://github.com/strefethen/arazzo-cli/commit/9296f304484956f23483b4f217b6e0fefff98d29) | soap, xml, xpath, cli, spec | watch | P0-6 source routing (wsdl type) |
| 2026-08-24 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.0 to 37.3.1](https://github.com/OAI/Arazzo-Specification/pull/549) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.0 to 37.3.2](https://github.com/OAI/Arazzo-Specification/pull/551) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-24 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22075)](https://github.com/jentic/jentic-public-apis/commit/0d34455cc4252bd49f136724a39d523a5ea5658c) |  | watch |  |
| 2026-08-23 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-23 (#186)](https://github.com/OAI/landscape/commit/fa54f124ce01a0ecafd424904fafa593914b3e72) |  | watch |  |
| 2026-08-23 | OAI/tools.openapis.org | issue | [Tool discovery (`full` workflow) has failed on every scheduled run since 2025-07-13 — dead source URL in metadata.json](https://github.com/OAI/tools.openapis.org/issues/285) | spec | watch | Conformance / schema validation |
| 2026-08-23 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 27 August 2026](https://github.com/OAI/OpenAPI-Specification/issues/5505) | spec | watch | Conformance / schema validation |
| 2026-08-23 | OAI/OpenAPI-Specification | pr | [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-23 | strefethen/arazzo-cli | commit | [refactor: derive describe method/target from the canonical classifier](https://github.com/strefethen/arazzo-cli/commit/92d3058cc63c1dcedefbdc78dacceee82daecd16) | mcp, cli, actor, schema, spec | watch | P2-2 MCP server exposure |
| 2026-08-23 | strefethen/arazzo-cli | commit | [fix: fail xpath criteria on null context per spec 5.8.11.4.4](https://github.com/strefethen/arazzo-cli/commit/3afe6731b6f93d4095bf419347d6af6ff826b375) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-23 | strefethen/arazzo-cli | commit | [fix: decide xpath criteria by effective boolean value](https://github.com/strefethen/arazzo-cli/commit/301174a63d1148f0d57d60fc8faef857706c9eab) | xml, xpath, cli, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-23 | usearazzo/website | commit | [feat(ecosystem): extend the list of resources](https://github.com/usearazzo/website/commit/9626bdb657fdeaf5417f1bd8663dd94273714cf2) | spec | watch | Conformance / schema validation |
| 2026-08-23 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): extend the list of resources](https://github.com/usearazzo/website/commit/9626bdb657fdeaf5417f1bd8663dd94273714cf2) | spec | watch | Conformance / schema validation |
| 2026-08-23 | OAI/OpenAPI-Specification | issue | [vX.Y: ...](https://github.com/OAI/OpenAPI-Specification/issues/5517) | spec | watch | Conformance / schema validation |
| 2026-08-23 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22074)](https://github.com/jentic/jentic-public-apis/commit/a69be8e11f14db098ee725bc6d2fee8916ac4c48) |  | watch |  |
| 2026-08-22 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-22 (#185)](https://github.com/OAI/landscape/commit/2ffd2427e96879f58071ffc2bec2c50330c71c22) |  | watch |  |
| 2026-08-22 | usearazzo/website | commit | [feat: add ecosystem article](https://github.com/usearazzo/website/commit/d888bda7e05f251979d1ba7b6a7af8b10aee32d4) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website | commit | [feat(ecosystem): add more aricles / libraries](https://github.com/usearazzo/website/commit/d832721ff7ac8aa0306e37a3733a6f6ac45352b8) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add more aricles / libraries](https://github.com/usearazzo/website/commit/d832721ff7ac8aa0306e37a3733a6f6ac45352b8) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website | commit | [fix(ecosystem): process final review notes](https://github.com/usearazzo/website/commit/a5439bd6d0197204b485bcd1690ea756b126c906) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website.ecosystem.atom | commit | [fix(ecosystem): process final review notes](https://github.com/usearazzo/website/commit/a5439bd6d0197204b485bcd1690ea756b126c906) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website | commit | [feat: add ecosystem page](https://github.com/usearazzo/website/commit/4d61cd3ecf5dcb3d9c0a1439d6f165bba3ef4bcc) | spec | watch | Conformance / schema validation |
| 2026-08-22 | usearazzo/website.ecosystem.atom | commit | [feat: add ecosystem page](https://github.com/usearazzo/website/commit/4d61cd3ecf5dcb3d9c0a1439d6f165bba3ef4bcc) | spec | watch | Conformance / schema validation |
| 2026-08-22 | Specmatic/specmatic | release | [2.53.1](https://github.com/specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-08-22 | usearazzo/arazzo-toolkit | issue | [cli: live execution progress rendering built on the runner event stream](https://github.com/usearazzo/arazzo-toolkit/issues/86) | cli, human, spec | watch | P2-1 CLI binary |
| 2026-08-22 | usearazzo/arazzo-toolkit | issue | [runner: execution observability — structured event stream with logging, OpenTelemetry, and stream adapters](https://github.com/usearazzo/arazzo-toolkit/issues/85) | cli, breaking, spec | breaking | P2-1 CLI binary |
<<<<<<< HEAD
| 2026-08-22 | usearazzo/arazzo-toolkit | issue | [cli: new @usearazzo/cli package — proposed command surface](https://github.com/usearazzo/arazzo-toolkit/issues/84) | cli, human, schema, spec | watch | P2-1 CLI binary |
| 2026-08-22 | usearazzo/arazzo-toolkit | issue | [runner: workflow execution profile — analyze what a run needs, pre-configure the runner with the filled artifact](https://github.com/usearazzo/arazzo-toolkit/issues/82) | cli, human, breaking, schema, spec | breaking | P2-1 CLI binary |
| 2026-08-22 | usearazzo/arazzo-toolkit | issue | [runner: first-class workflow enumeration (listWorkflows) with a future CLI list-workflows command](https://github.com/usearazzo/arazzo-toolkit/issues/83) | cli, human, schema, spec | watch | P2-1 CLI binary |
| 2026-08-22 | OAI/sig-security | issue | [Support for message level security](https://github.com/OAI/sig-security/issues/22) | schema, spec | watch | P1-7 JSON Schema layer |
=======
>>>>>>> 3686d91 (chore(ecosystem-feed): ignore storage snapshots, keep only relevant feed implementation)

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`
