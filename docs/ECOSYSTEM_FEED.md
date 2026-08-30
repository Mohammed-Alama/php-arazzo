# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-08-30T11:53:26+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 834 (showing 200 newest)
- **Severity:** breaking **161** · actionable **381** · watch **292**
- **Top relevance:** `Conformance / schema validation` (363) · `uncategorized` (123) · `Potential breaking change (2.0)` (112) · `P2-1 CLI binary` (86) · `P1-7 JSON Schema layer` (51)
- **Top sources:** `OAI/Arazzo-Specification` (49) · `strefethen/arazzo-cli` (48) · `OAI/build-infra` (42) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Generated JSON](docs/generated/ecosystem-feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Potential breaking change (2.0) (112)

- `2026-08-30` [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-08-30` [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) — `speclynx/apidom` · `tag` · _breaking,spec_
- `2026-08-30` [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-30` [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-30` [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-30` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-08-30` [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `tag` · _breaking,spec_
- `2026-08-30` [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) — `jentic/arazzo-engine` · `tag` · _breaking,spec_
- … and 104 more in this group (see All events table)

### P2-1 CLI binary (15)

- `2026-08-30` [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) — `strefethen/arazzo-cli` · `tag` · _cli,breaking,spec_
- `2026-08-28` [runner: workflow execution profile — analyze what a run needs, pre-configure the runner with the filled artifact](https://github.com/usearazzo/arazzo-toolkit/issues/82) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,breaking,schema,spec_
- `2026-08-27` [Bump markdown-it from 14.2.0 to 15.0.0](https://github.com/OAI/OpenAPI-Specification/pull/5461) — `OAI/OpenAPI-Specification` · `pr` · _cli,breaking,spec_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-25` [chore: release v0.4.0](https://github.com/strefethen/arazzo-cli/commit/6217148dba9f279529405ab27277dcf2de9a0cba) — `strefethen/arazzo-cli` · `commit` · _cli,breaking,spec_
- `2026-08-21` [chore(deps-dev): bump lint-staged from 16.4.0 to 17.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/72) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,spec_
- `2026-08-18` [chore(deps-dev): bump lerna from 9.0.7 to 10.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/45) — `usearazzo/arazzo-toolkit` · `pr` · _cli,a2a,breaking,spec_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,breaking,schema,spec_
- … and 7 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (11)

- `2026-08-28` [runner: document and enforce the criterion dialect support matrix (JSONPath dialects, XPath versions)](https://github.com/usearazzo/arazzo-toolkit/issues/95) — `usearazzo/arazzo-toolkit` · `issue` · _xml,xpath,breaking,spec_
- `2026-08-26` [feat: OpenAPI Normalization Gaps (Spec 4)](https://github.com/Mohammed-Alama/php-arazzo/issues/25) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,breaking,spec_
- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,schema,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,breaking,spec_
- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2025-09-19` [OAS 3.2.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.2.0) — `OAI/OpenAPI-Specification` · `release` · _xml,breaking,schema,spec_
- … and 3 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (9)

- `2026-08-28` [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/30) — `OAI/build-infra` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_
- `2026-08-28` [chore(deps): bump respec from 37.3.0 to 37.3.5](https://github.com/OAI/Arazzo-Specification/pull/552) — `OAI/Arazzo-Specification` · `pr` · _actor,breaking,spec_
- `2026-08-28` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,breaking,spec_
- `2026-08-04` [Bump content-type from 1.0.5 to 2.0.0](https://github.com/OAI/build-infra/pull/7) — `OAI/build-infra` · `pr` · _actor,breaking_
- `2026-07-28` [build(deps-dev): bump markdownlint-cli2 from 0.23.1 to 0.23.2](https://github.com/OAI/Overlay-Specification/pull/368) — `OAI/Overlay-Specification` · `pr` · _actor,a2a,breaking,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,breaking,schema,spec_
- … and 1 more in this group (see All events table)

### P2-2 MCP server exposure (5)

- `2026-08-10` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,breaking,schema,spec_
- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,breaking,spec_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,breaking,schema,spec_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,breaking,spec_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,breaking,schema,spec_

### P0-6 source routing (wsdl type) (4)

- `2026-08-28` [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) — `OAI/build-infra` · `pr` · _soap,breaking_
- `2026-08-28` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,schema,spec_
- `2026-08-19` [chore(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Arazzo-Specification/pull/545) — `OAI/Arazzo-Specification` · `pr` · _soap,breaking,spec_
- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,breaking,spec_

### Issue #410 loops vs goto (2)

- `2026-08-18` [chore(deps-dev): bump core-js from 3.49.0 to 3.50.0](https://github.com/usearazzo/arazzo-toolkit/pull/69) — `usearazzo/arazzo-toolkit` · `pr` · _loop,breaking,spec_
- `2026-03-30` [Feat: Marketing channel strategy for repositioning OAI](https://github.com/OAI/Outreach/issues/72) — `OAI/Outreach` · `issue` · _loop,breaking,spec_

### Roadmap A2A step type (2)

- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,breaking,spec_
- `2026-08-18` [build(deps-dev): update pestphp/pest requirement from ^4.0 to ^5.1](https://github.com/Mohammed-Alama/php-arazzo/pull/8) — `Mohammed-Alama/php-arazzo` · `pr` · _a2a,breaking,spec_

### P1-7 JSON Schema layer (1)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (231)

- `2026-08-30` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-30` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-30` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-08-30` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-30` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-30` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-30` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-08-30` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 223 more in this group (see All events table)

### uncategorized (48)

- `2026-08-30` [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) — `Redocly/redocly-cli` · `tag` · _no tags_
- `2026-08-30` [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) — `Redocly/redocly-cli` · `tag` · _no tags_
- … and 40 more in this group (see All events table)

### P2-1 CLI binary (44)

- `2026-08-30` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- `2026-08-30` [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) — `strefethen/arazzo-cli` · `tag` · _cli,spec_
- … and 36 more in this group (see All events table)

### P1-7 JSON Schema layer (24)

- `2026-08-27` [Remove various npm hacks, switch to yarn for package management](https://github.com/OAI/build-infra/pull/24) — `OAI/build-infra` · `pr` · _schema,spec_
- `2026-08-27` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [v3.3: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5518) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [@redocly/openapi-core@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-26` [@redocly/cli@2.48.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.48.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-24` [v3.3: Fix RFC reference with stray space](https://github.com/OAI/OpenAPI-Specification/pull/5516) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5510) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-21` [3.2: Fix formatting of 'Encoding Object' in oas.md](https://github.com/OAI/OpenAPI-Specification/pull/5515) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- … and 16 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (15)

- `2026-08-27` [Fix/sync lockfile packaged lock](https://github.com/OAI/build-infra/pull/19) — `OAI/build-infra` · `pr` · _human,spec_
- `2026-08-27` [chore: delete 2 dead exception classes (G14)](https://github.com/Mohammed-Alama/php-arazzo/pull/45) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-24` [chore(deps-dev): bump @microsoft/api-extractor from 7.58.12 to 7.59.0](https://github.com/usearazzo/arazzo-toolkit/pull/89) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-21` [feat(runner): support cross-document workflowId references](https://github.com/usearazzo/arazzo-toolkit/pull/73) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-18` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,schema,spec_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- `2026-08-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
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

### Conformance / schema validation (132)

- `2026-08-30` [openapi.tools checksum c4e8b5c7d435](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-08-30` [chore: Initialise Claude file](https://github.com/OAI/tools.openapis.org/issues/287) — `OAI/tools.openapis.org` · `issue` · _spec_
- `2026-08-29` [feat(blog): add Arazzo release-diff post](https://github.com/usearazzo/website/commit/86f23a3b23bd4f46b78535b23ef08a6b67c664be) — `usearazzo/website` · `commit` · _spec_
- `2026-08-29` [chore: drop stray pycache, ignore Python bytecode](https://github.com/usearazzo/website/commit/855c14199119fda02638eb94d2dc3b4c994fabb1) — `usearazzo/website` · `commit` · _spec_
- `2026-08-29` [chore(harness): add writing skills](https://github.com/usearazzo/website/commit/ba02e450084ae1aed62ae7eeb9cf3e8e2836ecae) — `usearazzo/website` · `commit` · _spec_
- `2026-08-29` [I Diffed Every Arazzo Release So You Don’t Have To](https://usearazzo.com/blog/arazzo-specification-evolution/) — `usearazzo/website.feed` · `article` · _spec_
- `2026-08-28` [parser: make sure parser doesn't crash on recrursion](https://github.com/usearazzo/arazzo-toolkit/issues/94) — `usearazzo/arazzo-toolkit` · `issue` · _spec_
- `2026-08-28` [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/553) — `OAI/Arazzo-Specification` · `pr` · _spec_
- … and 124 more in this group (see All events table)

### uncategorized (75)

- `2026-08-30` [Rebuild apis.json, scores.json, and API browsing indexes (#22094)](https://github.com/jentic/jentic-public-apis/commit/6a7ecb067ab8233dc5fb2a11674eefc7e620390f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-29` [Update Landscape from LFX 2026-08-29 (#191)](https://github.com/OAI/landscape/commit/a2efbf8c58027f7b9b7928043d1c73a0ee66c7c9) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-29` [Rebuild apis.json, scores.json, and API browsing indexes (#22093)](https://github.com/jentic/jentic-public-apis/commit/4ed2f909a1e3a5ee6911c1b4955f1f1fdd7f5c2d) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-08-28` [Merge pull request #27 from OAI/dependabot/npm_and_yarn/vitest-03a3299ce5](https://github.com/OAI/build-infra/commit/a61949fe70c0d78308b7d565751760c1c655cb41) — `OAI/build-infra` · `commit` · _no tags_
- `2026-08-28` [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/commit/d4664bffc1d21f6e636fc9983d99ceda3dc8a987) — `OAI/build-infra` · `commit` · _no tags_
- `2026-08-28` [Stage release changes during release branch adjustment](https://github.com/OAI/build-infra/pull/29) — `OAI/build-infra` · `pr` · _no tags_
- `2026-08-28` [Update Landscape from LFX 2026-08-28 (#190)](https://github.com/OAI/landscape/commit/2625639709e7bd87c106fe367254049ba7f6c947) — `OAI/landscape` · `commit` · _no tags_
- `2026-08-28` [Rebuild apis.json, scores.json, and API browsing indexes (#22092)](https://github.com/jentic/jentic-public-apis/commit/1bd49bdc0072e7ffb19f79f28e53380c7c593505) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 67 more in this group (see All events table)

### P2-1 CLI binary (27)

- `2026-08-28` [runner: record/replay — deterministic offline re-execution of workflow runs](https://github.com/usearazzo/arazzo-toolkit/issues/101) — `usearazzo/arazzo-toolkit` · `issue` · _cli,actor,schema,spec_
- `2026-08-28` [cli: new @usearazzo/cli package — proposed command surface](https://github.com/usearazzo/arazzo-toolkit/issues/84) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,schema,spec_
- `2026-08-27` [test: add tests for Console (55% → 100%) (G11)](https://github.com/Mohammed-Alama/php-arazzo/issues/38) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,spec_
- `2026-08-26` [When two or more sourceDescriptions are provided with (local) OpenAPI specs, only the first spec's base URL is shown in dry-run for all calls by OperationId.](https://github.com/strefethen/arazzo-cli/issues/5) — `strefethen/arazzo-cli` · `issue` · _cli,spec_
- `2026-08-26` [fix(conformance): scope the claim to type: openapi, not "non-arazzo"](https://github.com/strefethen/arazzo-cli/commit/8f2217c6fe38be5117543529f367b5bfc0a0d606) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [fix(conformance): re-own the operationPath claim and cover the file url read](https://github.com/strefethen/arazzo-cli/commit/47d0b0de1ac199e9f29c498c4774069c1debdd6f) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: install CLI after release push](https://github.com/strefethen/arazzo-cli/commit/73ed5b90d105d595ddad4cdd0e8e08a5df3a8a27) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-08-26` [chore: release v0.5.0](https://github.com/strefethen/arazzo-cli/commit/9a405456aa58b3c48736740400fd373b42227e4e) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- … and 19 more in this group (see All events table)

### P1-7 JSON Schema layer (26)

- `2026-08-28` [OAS v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/pull/5528) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-28` [runner: validate workflow inputs against the inputs schema and apply defaults before a run](https://github.com/usearazzo/arazzo-toolkit/issues/97) — `usearazzo/arazzo-toolkit` · `issue` · _schema,spec_
- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5526) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [V3.2.1 rel](https://github.com/OAI/OpenAPI-Specification/pull/5525) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Proposal: Add externalLinks, like externalDocs but allow more than one](https://github.com/OAI/OpenAPI-Specification/pull/5467) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-27` [Make patch optional in openapi field.](https://github.com/OAI/OpenAPI-Specification/pull/4929) — `OAI/OpenAPI-Specification` · `pr` · _schema,spec_
- `2026-08-26` [feat: Transport Failure Handling — typed exception hierarchy (Spec 3)](https://github.com/Mohammed-Alama/php-arazzo/issues/24) — `Mohammed-Alama/php-arazzo` · `issue` · _schema,spec_
- `2026-08-25` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _schema,spec_
- … and 18 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (16)

- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor,spec_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-08-28` [runner: opt-in retry delay policy (exponential backoff, jitter); rate limiting stays at the transport seam](https://github.com/usearazzo/arazzo-toolkit/issues/98) — `usearazzo/arazzo-toolkit` · `issue` · _actor,loop,spec_
- `2026-08-28` [runner: resumable workflow execution — WorkflowExecution state machine with advance()/snapshot()/restore()](https://github.com/usearazzo/arazzo-toolkit/issues/96) — `usearazzo/arazzo-toolkit` · `issue` · _actor,loop,spec_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website` · `commit` · _actor,loop,spec_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor,loop,spec_
- `2026-08-28` [refactor(core): resolve layering violations (#36)](https://github.com/Mohammed-Alama/php-arazzo/pull/50) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-26` [feat: Documentation, CI, Release Readiness (Spec 6)](https://github.com/Mohammed-Alama/php-arazzo/issues/27) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- … and 8 more in this group (see All events table)

### Issue #410 loops vs goto (4)

- `2026-08-04` [OpenAPI - publish v3.1-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/129) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-07-27` [Arazzo - publish v1.2-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/109) — `OAI/spec.openapis.org` · `pr` · _loop,schema,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,spec_
- `2026-04-02` [Feat: Launch monthly member drop-in clinics (EU and US timezones)](https://github.com/OAI/Outreach/issues/69) — `OAI/Outreach` · `issue` · _loop,spec_

### P2-2 MCP server exposure (4)

- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website` · `commit` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,loop,spec_
- `2026-08-26` [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_

### Roadmap A2A step type (3)

- `2026-08-20` [docs: update CLAUDE.md to reflect current reality](https://github.com/usearazzo/website/commit/ac65d199b313b25b1eea2a19af2881573634246e) — `usearazzo/website` · `commit` · _a2a,spec_
- `2026-08-18` [Document Yarn workflows](https://github.com/OAI/build-infra/commit/f1cb0e050a823e1a2a188fdbb0b4356cb694e7da) — `OAI/build-infra` · `commit` · _a2a_
- `2026-07-22` [build(deps): bump ruby/setup-ruby from 1.319.0 to 1.320.0](https://github.com/OAI/spec.openapis.org/commit/3ccc930eaa2a78c31ea19f09e0dbea2639b571ed) — `OAI/spec.openapis.org` · `commit` · _a2a,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-08-05` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-08-26` [feat: Testing and Adapter Parity (Spec 5)](https://github.com/Mohammed-Alama/php-arazzo/issues/26) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,spec_

### Roadmap GraphQL step type (1)

- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### Roadmap gRPC step type (1)

- `2026-08-28` [feat(spec): add gRPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,schema,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-08-30 | openapi.tools | tool_collection | [openapi.tools checksum c4e8b5c7d435](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-08-30 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | speclynx/apidom | tag | [tag v4.8.0](https://github.com/speclynx/apidom/releases/tag/v4.8.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.45.1](https://github.com/Specmatic/specmatic/releases/tag/2.45.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Specmatic/specmatic | tag | [tag 2.45.0](https://github.com/Specmatic/specmatic/releases/tag/2.45.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) |  | actionable |  |
| 2026-08-30 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) |  | actionable |  |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | OAI/tools.openapis.org | issue | [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-30 | OAI/tools.openapis.org | pr | [chore: add CLAUDE.md for Claude Code guidance](https://github.com/OAI/tools.openapis.org/pull/288) | spec | actionable | Conformance / schema validation |
| 2026-08-30 | OAI/tools.openapis.org | issue | [chore: Initialise Claude file](https://github.com/OAI/tools.openapis.org/issues/287) | spec | watch | Conformance / schema validation |
| 2026-08-30 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22094)](https://github.com/jentic/jentic-public-apis/commit/6a7ecb067ab8233dc5fb2a11674eefc7e620390f) |  | watch |  |
| 2026-08-29 | usearazzo/website | commit | [feat(blog): add Arazzo release-diff post](https://github.com/usearazzo/website/commit/86f23a3b23bd4f46b78535b23ef08a6b67c664be) | spec | watch | Conformance / schema validation |
| 2026-08-29 | usearazzo/website | commit | [chore: drop stray pycache, ignore Python bytecode](https://github.com/usearazzo/website/commit/855c14199119fda02638eb94d2dc3b4c994fabb1) | spec | watch | Conformance / schema validation |
| 2026-08-29 | usearazzo/website | commit | [chore(harness): add writing skills](https://github.com/usearazzo/website/commit/ba02e450084ae1aed62ae7eeb9cf3e8e2836ecae) | spec | watch | Conformance / schema validation |
| 2026-08-29 | speclynx/apidom | release | [v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-29 | speclynx/apidom | release | [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | a2a, breaking, spec | breaking | Roadmap A2A step type |
| 2026-08-29 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-29 (#191)](https://github.com/OAI/landscape/commit/a2efbf8c58027f7b9b7928043d1c73a0ee66c7c9) |  | watch |  |
| 2026-08-29 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22093)](https://github.com/jentic/jentic-public-apis/commit/4ed2f909a1e3a5ee6911c1b4955f1f1fdd7f5c2d) |  | watch |  |
| 2026-08-29 | usearazzo/website.feed | article | [I Diffed Every Arazzo Release So You Don’t Have To](https://usearazzo.com/blog/arazzo-specification-evolution/) | spec | watch | Conformance / schema validation |
| 2026-08-28 | OAI/OpenAPI-Specification | pr | [OAS v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/pull/5528) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-28 | OAI/build-infra | pr | [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/30) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-* dependencies to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/103) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | pr | [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/27) |  | actionable |  |
| 2026-08-28 | OAI/build-infra | commit | [Merge pull request #27 from OAI/dependabot/npm_and_yarn/vitest-03a3299ce5](https://github.com/OAI/build-infra/commit/a61949fe70c0d78308b7d565751760c1c655cb41) |  | watch |  |
| 2026-08-28 | OAI/build-infra | pr | [Bump content-type from 2.0.0 to 3.0.0](https://github.com/OAI/build-infra/pull/28) | breaking | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | commit | [Merge pull request #28 from OAI/dependabot/npm_and_yarn/content-type-3.0.0](https://github.com/OAI/build-infra/commit/5bc5a5fe7eab5ae3843f7cfe7844c8da0dd43839) | breaking | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | commit | [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/commit/d4664bffc1d21f6e636fc9983d99ceda3dc8a987) |  | watch |  |
| 2026-08-28 | OAI/build-infra | commit | [Bump content-type from 2.0.0 to 3.0.0](https://github.com/OAI/build-infra/commit/88576ba5979b470973b47a6ebdb7b51bb0a65645) | breaking | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | pr | [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) | soap, breaking | breaking | P0-6 source routing (wsdl type) |
| 2026-08-28 | OAI/build-infra | commit | [Merge pull request #23 from OAI/dependabot/npm_and_yarn/highlight.js-11.12.0](https://github.com/OAI/build-infra/commit/63d74de0b47670923f3ab34c6523e7fbbc5f488b) | breaking | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | pr | [Bump respec from 37.2.0 to 37.3.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/20) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | commit | [Merge pull request #20 from OAI/dependabot/npm_and_yarn/publishing-915b191cf8](https://github.com/OAI/build-infra/commit/4656e194e30bf1d4008051f8735f2be9677f5b15) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/build-infra | pr | [Stage release changes during release branch adjustment](https://github.com/OAI/build-infra/pull/29) |  | watch |  |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump github/codeql-action from 4.37.7 to 4.37.8](https://github.com/usearazzo/arazzo-toolkit/pull/88) | cli, actor, spec | actionable | P2-1 CLI binary |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @speclynx/apidom-json-pointer from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/90) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [parser: make sure parser doesn't crash on recrursion](https://github.com/usearazzo/arazzo-toolkit/issues/94) | spec | watch | Conformance / schema validation |
| 2026-08-28 | usearazzo/arazzo-toolkit | pr | [test(parser): add regression tests for source description cycle safety](https://github.com/usearazzo/arazzo-toolkit/pull/102) | spec | actionable | Conformance / schema validation |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: record/replay — deterministic offline re-execution of workflow runs](https://github.com/usearazzo/arazzo-toolkit/issues/101) | cli, actor, schema, spec | watch | P2-1 CLI binary |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: opt-in request/response validation against OpenAPI operation schemas](https://github.com/usearazzo/arazzo-toolkit/issues/100) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [parser: expose parsing interfaces for runtime expressions and criterion conditions](https://github.com/usearazzo/arazzo-toolkit/issues/99) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: workflow execution profile — analyze what a run needs, pre-configure the runner with the filled artifact](https://github.com/usearazzo/arazzo-toolkit/issues/82) | cli, human, breaking, schema, spec | breaking | P2-1 CLI binary |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: opt-in retry delay policy (exponential backoff, jitter); rate limiting stays at the transport seam](https://github.com/usearazzo/arazzo-toolkit/issues/98) | actor, loop, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: validate workflow inputs against the inputs schema and apply defaults before a run](https://github.com/usearazzo/arazzo-toolkit/issues/97) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: resumable workflow execution — WorkflowExecution state machine with advance()/snapshot()/restore()](https://github.com/usearazzo/arazzo-toolkit/issues/96) | actor, loop, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [cli: new @usearazzo/cli package — proposed command surface](https://github.com/usearazzo/arazzo-toolkit/issues/84) | cli, human, schema, spec | watch | P2-1 CLI binary |
| 2026-08-28 | usearazzo/arazzo-toolkit | issue | [runner: document and enforce the criterion dialect support matrix (JSONPath dialects, XPath versions)](https://github.com/usearazzo/arazzo-toolkit/issues/95) | xml, xpath, breaking, spec | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) | soap, wsdl, breaking, schema, spec | breaking | P0-6 source routing (wsdl type) |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [feat(spec): add gRPC support](https://github.com/OAI/Arazzo-Specification/pull/556) | grpc, schema, spec | watch | Roadmap gRPC step type |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [v1.2-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/555) | spec | actionable | Conformance / schema validation |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [v1.1-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/554) | spec | actionable | Conformance / schema validation |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/553) | spec | watch | Conformance / schema validation |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [dev: sync with main](https://github.com/OAI/Arazzo-Specification/pull/548) | spec | actionable | Conformance / schema validation |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.0 to 37.3.5](https://github.com/OAI/Arazzo-Specification/pull/552) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | OAI/Arazzo-Specification | commit | [Merge pull request #552 from OAI/dependabot/npm_and_yarn/respec-37.3.5](https://github.com/OAI/Arazzo-Specification/commit/7d8b90f1741e7174ba058c8f77df6eb6d0d758ab) | spec | watch | Conformance / schema validation |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/550) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/Arazzo-Specification | commit | [Merge pull request #550 from OAI/dependabot/npm_and_yarn/vitest-0d65bea298](https://github.com/OAI/Arazzo-Specification/commit/bb071aa61e2d5058e09ec6ae492153c9527f5ac3) | spec | watch | Conformance / schema validation |
| 2026-08-28 | usearazzo/website | commit | [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) | actor, loop, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) | actor, loop, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | usearazzo/website | commit | [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) | mcp, spec | watch | P2-2 MCP server exposure |
| 2026-08-28 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) | mcp, spec | watch | P2-2 MCP server exposure |
| 2026-08-28 | Mohammed-Alama/php-arazzo | pr | [refactor(core): resolve layering violations (#36)](https://github.com/Mohammed-Alama/php-arazzo/pull/50) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | OAI/landscape | commit | [Update Landscape from LFX 2026-08-28 (#190)](https://github.com/OAI/landscape/commit/2625639709e7bd87c106fe367254049ba7f6c947) |  | watch |  |
| 2026-08-28 | Mohammed-Alama/php-arazzo | pr | [test: add Laravel/Bindings tests (33% → 100%) (G10)](https://github.com/Mohammed-Alama/php-arazzo/pull/49) | spec | actionable | Conformance / schema validation |
| 2026-08-28 | Mohammed-Alama/php-arazzo | issue | [test: add tests for Laravel/Bindings (33% → 100%) (G10)](https://github.com/Mohammed-Alama/php-arazzo/issues/37) | spec | watch | Conformance / schema validation |
| 2026-08-28 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/Overlay-Specification | pr | [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) | actor, breaking, spec | breaking | Issue #410 kind discriminator / human-in-loop |
| 2026-08-28 | OAI/OpenAPI-Specification | issue | [v3.2.1 release](https://github.com/OAI/OpenAPI-Specification/issues/5460) | breaking, schema, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22092)](https://github.com/jentic/jentic-public-apis/commit/1bd49bdc0072e7ffb19f79f28e53380c7c593505) |  | watch |  |
| 2026-08-28 | OAI/tools.openapis.org | pr | [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) | graphql, spec | watch | Roadmap GraphQL step type |
| 2026-08-28 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.0 to 37.3.2](https://github.com/OAI/Arazzo-Specification/pull/551) | breaking, spec | breaking | Potential breaking change (2.0) |
| 2026-08-28 | OAI/Arazzo-Specification | commit | [chore(deps): bump respec from 37.3.0 to 37.3.5](https://github.com/OAI/Arazzo-Specification/commit/560dbfe98b2cb63608b29657d080e52c27794b36) | spec | watch | Conformance / schema validation |
| 2026-08-27 | OAI/build-infra | pr | [Avoid pinning transitive lockfile entries](https://github.com/OAI/build-infra/pull/15) |  | actionable |  |
| 2026-08-27 | OAI/build-infra | pr | [Fix/sync lockfile packaged lock](https://github.com/OAI/build-infra/pull/19) | human, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-08-27 | OAI/build-infra | pr | [Run linkspector without Chromium sandbox in Actions](https://github.com/OAI/build-infra/pull/16) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/build-infra | pr | [Test oai-spec-test package checkout layout](https://github.com/OAI/build-infra/pull/14) | spec | actionable | Conformance / schema validation |
| 2026-08-27 | OAI/build-infra | pr | [Remove various npm hacks, switch to yarn for package management](https://github.com/OAI/build-infra/pull/24) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-08-27 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 03 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5527) | spec | watch | Conformance / schema validation |
| 2026-08-27 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 14 May 2026](https://github.com/OAI/OpenAPI-Specification/issues/5318) | spec | watch | Conformance / schema validation |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`
