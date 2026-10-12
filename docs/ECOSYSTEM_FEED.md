# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-12T15:19:13+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 882 (showing 200 newest)
- **Severity:** breaking **35** · actionable **505** · watch **342**
- **Top relevance:** `Conformance / schema validation` (336) · `uncategorized` (154) · `Dependency maintenance` (112) · `P2-1 CLI binary` (91) · `P1-7 JSON Schema layer` (43)
- **Top sources:** `strefethen/arazzo-cli` (62) · `OAI/Arazzo-Specification` (51) · `usearazzo/arazzo-toolkit` (43) · `OAI/build-infra` (41) · `speclynx/apidom` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Dependency maintenance (8)

- `2026-10-10` [chore(deps): bump chalk from 5.6.2 to 6.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/223) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-10` [chore(deps): bump commander from 14.0.3 to 15.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/221) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-06` [build(deps): bump actions/checkout from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/208) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2026-10-06` [Bump just-the-docs from 0.11.1 to 0.12.0](https://github.com/OAI/learn.openapis.org/pull/180) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 6](https://github.com/jentic/arazzo-engine/pull/136) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_

### Conformance / schema validation (7)

- `2026-10-12` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-01-23` [v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_

### P1-7 JSON Schema layer (6)

- `2026-10-07` [feat(cli): add @usearazzo/cli package with validate command](https://github.com/usearazzo/arazzo-toolkit/pull/220) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,schema,runner,depbump_
- `2026-09-20` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-10-09` [OAS 3.1.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-06-18` [OAS 3.1.0-rc0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,security,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,runner,spec_
- `2024-09-25` [Arazzo 1.0.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,schema,runner,spec_

### P1-6 payload XPath / P0-5 XPath criteria (3)

- `2026-10-10` [fix(runner): evaluate runtime expressions in request payloads](https://github.com/usearazzo/arazzo-toolkit/pull/227) — `usearazzo/arazzo-toolkit` · `pr` · _xml,actor,breaking,runner,depbump_
- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### API security (OAI sig-security) (2)

- `2026-08-21` [\[Announcement\] OAuth2.1 and OAuth3 drafts](https://github.com/OAI/sig-security/issues/30) — `OAI/sig-security` · `issue` · _breaking,security,spec_
- `2020-02-21` [OAS 3.0.3 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.3) — `OAI/OpenAPI-Specification` · `release` · _breaking,security,spec_

### Arazzo runner / step execution (2)

- `2026-10-10` [feat(runner): treat a called workflow as its step's result](https://github.com/usearazzo/arazzo-toolkit/pull/230) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,runner,spec_
- `2026-10-10` [runner: pass a called workflow's outputs through as the calling step's outputs](https://github.com/usearazzo/arazzo-toolkit/issues/229) — `usearazzo/arazzo-toolkit` · `issue` · _breaking,runner,spec_

### P2-1 CLI binary (2)

- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-30` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (246)

- `2026-10-12` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-12` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-12` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-12` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-12` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-12` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-12` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-12` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 238 more in this group (see All events table)

### uncategorized (62)

- `2026-10-12` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-12` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-12` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-10` [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) — `OAI/learn.openapis.org` · `pr` · _no tags_
- `2026-10-09` [Clarify the meeting time in the template](https://github.com/OAI/OpenAPI-Specification/pull/5567) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Explain that schemas are for mandatory requirements](https://github.com/OAI/OpenAPI-Specification/pull/5183) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Add comment, re-trigger auto-merge](https://github.com/OAI/OpenAPI-Specification/pull/5500) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- … and 54 more in this group (see All events table)

### P2-1 CLI binary (55)

- `2026-10-12` [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-12` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 47 more in this group (see All events table)

### Dependency maintenance (50)

- `2026-10-09` [chore(deps): bump handlebars and lerna](https://github.com/usearazzo/arazzo-toolkit/pull/226) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-09` [chore(deps-dev): bump chai from 6.2.2 to 6.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/225) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-08` [chore(deps-dev): bump globals from 17.12.0 to 17.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/224) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-07` [chore(deps): bump yaml from 2.9.0 to 2.9.1](https://github.com/usearazzo/arazzo-toolkit/pull/222) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump nx from 23.2.0 to 23.2.1](https://github.com/usearazzo/arazzo-toolkit/pull/217) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump typescript-eslint from 8.70.1 to 8.71.0](https://github.com/usearazzo/arazzo-toolkit/pull/215) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump mocha from 12.0.2 to 12.0.3](https://github.com/usearazzo/arazzo-toolkit/pull/213) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps): bump source-map-js from 1.2.1 to 1.2.2](https://github.com/usearazzo/arazzo-toolkit/pull/216) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 42 more in this group (see All events table)

### P1-7 JSON Schema layer (23)

- `2026-10-10` [@usearazzo/validator@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.7) — `npm.@usearazzo/validator` · `release` · _schema,spec_
- `2026-10-07` [v3.2: Fix JSON Schema keyword name. (port of 5195)](https://github.com/OAI/OpenAPI-Specification/pull/5197) — `OAI/OpenAPI-Specification` · `pr` · _schema_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.9](https://github.com/OAI/learn.openapis.org/pull/213) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-05` [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-04` [v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) — `usearazzo/arazzo-toolkit` · `release` · _schema,spec_
- `2026-10-01` [v6.17.0](https://github.com/stoplightio/spectral/releases/tag/v6.17.0) — `stoplightio/spectral` · `release` · _schema,depbump_
- `2026-09-27` [Bump @hyperjump/json-schema-coverage from 1.2.2 to 1.3.0 in the hyperjump group across 1 directory](https://github.com/OAI/build-infra/pull/60) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-09-25` [v1.25.3](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.3) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- … and 15 more in this group (see All events table)

### OAI Moonwalk (next-gen spec) (16)

- `2025-03-31` [Clean up principles](https://github.com/OAI/sig-moonwalk/pull/182) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-07-08` [Mimic current OpenAPI spec layout](https://github.com/OAI/sig-moonwalk/pull/140) — `OAI/sig-moonwalk` · `pr` · _moonwalk,spec_
- `2024-06-16` [Try quoting to fix mysterious yaml error](https://github.com/OAI/sig-moonwalk/pull/138) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-06-16` [Use local npm install in build](https://github.com/OAI/sig-moonwalk/pull/137) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-06-16` [Set up node for respec and invoke it accordingly](https://github.com/OAI/sig-moonwalk/pull/135) — `OAI/sig-moonwalk` · `pr` · _moonwalk,spec_
- `2024-06-16` [Removed content from spec as per meeting decision](https://github.com/OAI/sig-moonwalk/pull/136) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-06-12` [(possibly controversial) document processes and organize repo for ADR-centric work](https://github.com/OAI/sig-moonwalk/pull/89) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-06-12` [Add ADR for using IRIs](https://github.com/OAI/sig-moonwalk/pull/86) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- … and 8 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (14)

- `2026-10-06` [chore(deps-dev): bump shell-quote from 1.10.0 to 1.12.0](https://github.com/usearazzo/arazzo-toolkit/pull/218) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-10-05` [chore(deps-dev): bump @microsoft/api-extractor from 7.59.2 to 7.59.3](https://github.com/usearazzo/arazzo-toolkit/pull/210) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-27` [Bump vite from 8.3.0 to 8.3.1 in the vitest group across 1 directory](https://github.com/OAI/build-infra/pull/63) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-27` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump respec from 37.3.6 to 37.4.0](https://github.com/OAI/Arazzo-Specification/pull/575) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- … and 6 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (13)

- `2026-10-06` [build(deps): bump actions/setup-node from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/209) — `OAI/learn.openapis.org` · `pr` · _xml,depbump_
- `2026-09-06` [feat: add phpmd, pdepend, phpdoc to quality pipeline](https://github.com/Mohammed-Alama/php-arazzo/pull/53) — `Mohammed-Alama/php-arazzo` · `pr` · _xml_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,security,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,depbump_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,loop,spec_
- … and 5 more in this group (see All events table)

### P0-5 XPath criteria + P1-6 targetSelectorType (5)

- `2026-10-08` [v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,mcp,cli,spec_
- `2026-09-07` [feat(runner): consume expression + document engines via public faces](https://github.com/Mohammed-Alama/php-arazzo/pull/69) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,actor,spec_
- `2026-09-07` [feat(document): validator consumes expression via its public face](https://github.com/Mohammed-Alama/php-arazzo/pull/68) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath_
- `2026-09-06` [richen public face covering cli/laravel needs — Closes #58](https://github.com/Mohammed-Alama/php-arazzo/pull/67) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,cli,actor_
- `2026-03-13` [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,loop,spec_

### P2-2 MCP server exposure (5)

- `2026-09-03` [2.54.0](https://github.com/specmatic/specmatic/releases/tag/2.54.0) — `Specmatic/specmatic` · `release` · _mcp,cli,security,depbump_
- `2026-07-17` [2.50.1](https://github.com/specmatic/specmatic/releases/tag/2.50.1) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,spec_

### Roadmap A2A step type (5)

- `2026-10-06` [build(deps): bump prettier from 3.8.3 to 3.9.9](https://github.com/OAI/learn.openapis.org/pull/214) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump yaml from 2.8.3 to 2.9.1](https://github.com/OAI/learn.openapis.org/pull/215) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/pull/145) — `OAI/spec.openapis.org` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### API security (OAI sig-security) (4)

- `2026-09-21` [specification/security: fix of a typo on line 47](https://github.com/OAI/learn.openapis.org/pull/207) — `OAI/learn.openapis.org` · `pr` · _security,spec_
- `2024-09-21` [v6.13.1](https://github.com/stoplightio/spectral/releases/tag/v6.13.1) — `stoplightio/spectral` · `release` · _security,depbump_
- `2018-10-08` [OAS 3.0.2 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.2) — `OAI/OpenAPI-Specification` · `release` · _security,spec_
- `2017-04-28` [OAS 3.0.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _security,spec_

### P0-6 source routing (wsdl type) (3)

- `2026-10-04` [Phase E: Runner Package Split + OMS Engine (E0-E10 complete)](https://github.com/Mohammed-Alama/php-arazzo/pull/75) — `Mohammed-Alama/php-arazzo` · `pr` · _soap,xml,xpath,a2a,spec_
- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_

### Issue #410 loops vs goto (1)

- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_

### Roadmap GraphQL step type (1)

- `2026-09-26` [Phase D: Document Normalizer - D0 Package Split + D0.5 Cebe Purge](https://github.com/Mohammed-Alama/php-arazzo/pull/74) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,graphql,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (92)

- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26749)](https://github.com/jentic/jentic-public-apis/commit/cb7b40ed2976b82f3fc372d31a319f000b7103b3) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26737)](https://github.com/jentic/jentic-public-apis/commit/8e26b81c7b585289a5df7eb828ae663f8b736d56) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26735)](https://github.com/jentic/jentic-public-apis/commit/fde2237640c96c81e4d70234402d35b5e8e0443d) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26726)](https://github.com/jentic/jentic-public-apis/commit/95a41fc19bfc141e073c6b50b7550c123de2fbde) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26721)](https://github.com/jentic/jentic-public-apis/commit/e3261408d3b07b90ff1b52d056270474bce1af66) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26719)](https://github.com/jentic/jentic-public-apis/commit/3264711fde7b789c0d5818b5fb98872155a03d81) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26661)](https://github.com/jentic/jentic-public-apis/commit/87c01bc6ac53dd300154cb9e412c33e68b8c9c7e) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-12` [Rebuild apis.json, scores.json, and API browsing indexes (#26660)](https://github.com/jentic/jentic-public-apis/commit/93fdf32ad1302321672fa1179d8ea80f5ed7c12e) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 84 more in this group (see All events table)

### Conformance / schema validation (83)

- `2026-10-12` [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-12` [feat: Replace OpenAPI spec for Issue #26723 (#26728)](https://github.com/jentic/jentic-public-apis/commit/3d548bba8b2ad7de0d3b53a0e2bb90d374ee912d) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22643 (#22646)](https://github.com/jentic/jentic-public-apis/commit/ccb31bee367b567240177cb300d960af29564c52) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22483 (#22484)](https://github.com/jentic/jentic-public-apis/commit/9c6c3cdaff83c3ce29c853a3f81eae99c37ac786) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22479 (#22480)](https://github.com/jentic/jentic-public-apis/commit/f278b5f47e34c812d7be86d6b37c9e06d43c3c2d) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22462 (#22463)](https://github.com/jentic/jentic-public-apis/commit/7b28c31354d6903e3883ba8715e60b8f98bd7f86) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22432 (#22435)](https://github.com/jentic/jentic-public-apis/commit/8affd4d1f98f6c3cd412294a32df27bdc8aec2f9) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-12` [feat: Import OpenAPI spec from Issue #22416 (#22419)](https://github.com/jentic/jentic-public-apis/commit/28152752a8220037979a1e7057d773373dc2394c) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 75 more in this group (see All events table)

### Dependency maintenance (54)

- `2026-10-12` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-09` [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-06` [build(deps): bump jekyll-include-cache from 0.2.1 to 0.3.1](https://github.com/OAI/learn.openapis.org/pull/212) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-05` [Merge pull request #144 from OAI/dependabot/bundler/jekyll-include-cache-0.3.1](https://github.com/OAI/spec.openapis.org/commit/ea40577169de316c22b24219136ada123ff5af49) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [Merge pull request #145 from OAI/dependabot/bundler/jekyll-remote-theme-0.6.2](https://github.com/OAI/spec.openapis.org/commit/1112ad677f1712a5bafedbd71449795fa68997d0) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/commit/88146ad7e97d3019c71ae1bed1592d50b1765391) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/commit/f0f029b9c633895c13c987d6e788adad7256afb1) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-04` [Merge pull request #64 from OAI/dependabot/npm_and_yarn/vitest-92ac8026ca](https://github.com/OAI/build-infra/commit/c771ab929b8445becf4fd9349ab365823a553206) — `OAI/build-infra` · `commit` · _depbump_
- … and 46 more in this group (see All events table)

### P2-1 CLI binary (34)

- `2026-10-10` [runner: record criterion diagnostics and fail unavailable criteria softly](https://github.com/usearazzo/arazzo-toolkit/issues/231) — `usearazzo/arazzo-toolkit` · `issue` · _cli,runner,spec_
- `2026-10-10` [fix(expr): refuse JSONPath queries nested deeper than 8 brackets](https://github.com/strefethen/arazzo-cli/commit/af6ee9df1b1d15e0b00e8d171d072dc37b70c045) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [chore: add the owner-voice skill and record the CLI release in project notes](https://github.com/usearazzo/website/commit/25d8affa10dc70f901d9cb4a68f90e34c4814e33) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(blog): add the @usearazzo/cli release post](https://github.com/usearazzo/website/commit/76afac7e32bcc304b20991f1dc506831250724a1) — `usearazzo/website` · `commit` · _cli,spec_
- `2026-10-10` [docs: say the CLI is on npm across the site](https://github.com/usearazzo/website/commit/c9deafc32dea5dabca0798dfcd2c6e777551f3a1) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(cli): rewrite the product page for the published CLI](https://github.com/usearazzo/website/commit/624b0621ab8254ab5785ae83f74f7d7f78a8099f) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(docs): add @usearazzo/cli reference](https://github.com/usearazzo/website/commit/1313e297606e1b45e9767f281283d24742587d95) — `usearazzo/website` · `commit` · _cli,spec_
- `2026-10-10` [cli: new @usearazzo/cli package — proposed command surface](https://github.com/usearazzo/arazzo-toolkit/issues/84) — `usearazzo/arazzo-toolkit` · `issue` · _cli,human,runner,spec_
- … and 26 more in this group (see All events table)

### API security (OAI sig-security) (14)

- `2026-10-02` [Auto-merge deletes the entire src tree](https://github.com/OAI/sig-security/issues/56) — `OAI/sig-security` · `issue` · _security_
- `2026-09-25` [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-24` [Support for GNAP](https://github.com/OAI/sig-security/issues/9) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- … and 6 more in this group (see All events table)

### P1-7 JSON Schema layer (14)

- `2026-10-11` [Validate Arazzo Documents From the Command Line](https://usearazzo.com/blog/validate-arazzo-documents-from-the-command-line/) — `usearazzo/website.feed` · `article` · _cli,schema,spec_
- `2026-10-10` [runner: validate workflow inputs against the inputs schema and apply defaults before a run](https://github.com/usearazzo/arazzo-toolkit/issues/97) — `usearazzo/arazzo-toolkit` · `issue` · _schema,runner,spec_
- `2026-10-09` [Bump the hyperjump group with 2 updates](https://github.com/OAI/build-infra/pull/69) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-08` [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) — `OAI/learn.openapis.org` · `issue` · _schema,spec_
- `2026-10-08` [build(deps): bump @hyperjump/json-schema from 1.17.9 to 1.18.0](https://github.com/OAI/learn.openapis.org/pull/217) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.5](https://github.com/OAI/learn.openapis.org/pull/193) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- … and 6 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (12)

- `2026-10-12` [feat(runner): run petstore workflows e2e and publish the runner](https://github.com/usearazzo/arazzo-toolkit/pull/233) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-10-05` [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-28` [Bump jmertic/lfx-landscape-tools from 20260916 to 20260923 in the all group (#217)](https://github.com/OAI/landscape/commit/9a4f7b0a15a7c053b56271599292151e873b3159) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor_
- … and 4 more in this group (see All events table)

### OAI Moonwalk (next-gen spec) (8)

- `2026-03-17` [Write ADR for identity vs location](https://github.com/OAI/sig-moonwalk/issues/92) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2025-11-12` [Create draft REST proposal](https://github.com/OAI/sig-moonwalk/pull/212) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-05-07` [Added preliminary design for resource model](https://github.com/OAI/sig-moonwalk/pull/183) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-02-23` [Added example of query parameter versioning](https://github.com/OAI/sig-moonwalk/pull/174) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-07-23` [Can the Data Types section be replaced by a reference to the format registry?](https://github.com/OAI/sig-moonwalk/issues/131) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Open API Path Templating vs WHATWG URL Pattern](https://github.com/OAI/sig-moonwalk/issues/125) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Allow recursive paths](https://github.com/OAI/sig-moonwalk/issues/117) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Structural improvements: inheritance on paths and its sublevels](https://github.com/OAI/sig-moonwalk/issues/115) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_

### P2-2 MCP server exposure (8)

- `2026-10-12` [docs(readme): add Popular + niche API tables, category browse, and visuals (SEO/AEO) (#25033)](https://github.com/jentic/jentic-public-apis/commit/cf0757ab921d5f1bff0c729a1538354ad15c926c) — `jentic/jentic-public-apis` · `commit` · _mcp,cli,spec_
- `2026-10-09` [test(mcp): bound the .env subprocess wait and pin its stderr](https://github.com/strefethen/arazzo-cli/commit/57ca935392c24f05007f5113fd3c2634f0ef1497) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-09` [fix(cli,mcp): stop reading .env at the first hard I/O error](https://github.com/strefethen/arazzo-cli/commit/532b3ccce6d73018bc85c2bc8a20f7070200a186) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- `2026-09-10` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_

### Arazzo runner / step execution (7)

- `2026-10-12` [runner: CommonJS entry fails to load — missing vendor/swagger-client.cjs](https://github.com/usearazzo/arazzo-toolkit/issues/234) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-10` [runner: response context and $outputs for steps that call a workflow](https://github.com/usearazzo/arazzo-toolkit/issues/228) — `usearazzo/arazzo-toolkit` · `issue` · _loop,runner,spec_
- `2026-10-10` [runner: embedded runtime expressions in requestBody payloads are sent verbatim (spec MUST, 1.0.1)](https://github.com/usearazzo/arazzo-toolkit/issues/129) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-02` [Clarify how to read outputs of a workflow run by a `retry` failure action](https://github.com/OAI/Arazzo-Specification/issues/590) — `OAI/Arazzo-Specification` · `issue` · _runner,spec_
- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### Roadmap A2A step type (6)

- `2026-10-09` [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,depbump_
- `2026-10-09` [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump jekyll-remote-theme from 0.4.3 to 0.6.2](https://github.com/OAI/learn.openapis.org/pull/210) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-06` [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) — `OAI/Arazzo-Specification` · `pr` · _a2a,depbump_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_
- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### P0-5 XPath criteria + P1-6 targetSelectorType (5)

- `2026-10-10` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,runner,spec_
- `2026-10-08` [fix(validate): enforce canonical expression field modes](https://github.com/strefethen/arazzo-cli/commit/e95ef11993fe3f1462097f1692aec54e6eeaba4e) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli_
- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-10-04` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,depbump_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Issue #410 loops vs goto (1)

- `2026-10-11` [validator: track new validation and lint rules](https://github.com/usearazzo/arazzo-toolkit/issues/197) — `usearazzo/arazzo-toolkit` · `issue` · _loop,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_

### Roadmap GraphQL step type (1)

- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-10-12 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-12 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-12 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-12 | openapi.tools | tool_collection | [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-12 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.3.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.3.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.7](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26749)](https://github.com/jentic/jentic-public-apis/commit/cb7b40ed2976b82f3fc372d31a319f000b7103b3) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Replace OpenAPI spec for Issue #26723 (#26728)](https://github.com/jentic/jentic-public-apis/commit/3d548bba8b2ad7de0d3b53a0e2bb90d374ee912d) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26737)](https://github.com/jentic/jentic-public-apis/commit/8e26b81c7b585289a5df7eb828ae663f8b736d56) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22643 (#22646)](https://github.com/jentic/jentic-public-apis/commit/ccb31bee367b567240177cb300d960af29564c52) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26735)](https://github.com/jentic/jentic-public-apis/commit/fde2237640c96c81e4d70234402d35b5e8e0443d) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22483 (#22484)](https://github.com/jentic/jentic-public-apis/commit/9c6c3cdaff83c3ce29c853a3f81eae99c37ac786) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26726)](https://github.com/jentic/jentic-public-apis/commit/95a41fc19bfc141e073c6b50b7550c123de2fbde) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22479 (#22480)](https://github.com/jentic/jentic-public-apis/commit/f278b5f47e34c812d7be86d6b37c9e06d43c3c2d) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26721)](https://github.com/jentic/jentic-public-apis/commit/e3261408d3b07b90ff1b52d056270474bce1af66) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22462 (#22463)](https://github.com/jentic/jentic-public-apis/commit/7b28c31354d6903e3883ba8715e60b8f98bd7f86) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26719)](https://github.com/jentic/jentic-public-apis/commit/3264711fde7b789c0d5818b5fb98872155a03d81) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22432 (#22435)](https://github.com/jentic/jentic-public-apis/commit/8affd4d1f98f6c3cd412294a32df27bdc8aec2f9) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26661)](https://github.com/jentic/jentic-public-apis/commit/87c01bc6ac53dd300154cb9e412c33e68b8c9c7e) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22416 (#22419)](https://github.com/jentic/jentic-public-apis/commit/28152752a8220037979a1e7057d773373dc2394c) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [docs(readme): add Popular + niche API tables, category browse, and visuals (SEO/AEO) (#25033)](https://github.com/jentic/jentic-public-apis/commit/cf0757ab921d5f1bff0c729a1538354ad15c926c) | mcp, cli, spec | watch | P2-2 MCP server exposure |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26660)](https://github.com/jentic/jentic-public-apis/commit/93fdf32ad1302321672fa1179d8ea80f5ed7c12e) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22369 (#22371)](https://github.com/jentic/jentic-public-apis/commit/37f6e0d232123ae0da45ce1bd88a90b1033047b0) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26659)](https://github.com/jentic/jentic-public-apis/commit/b523fbdce2b71ca71a5d6154ac0169ece29e321f) |  | watch |  |
| 2026-10-12 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22363 (#22364)](https://github.com/jentic/jentic-public-apis/commit/49c20e15351b70dc02aed0de4cda7e80b45fb96c) | spec | watch | Conformance / schema validation |
| 2026-10-12 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26658)](https://github.com/jentic/jentic-public-apis/commit/1efc92044de6b9369d3b9101985b4b2be4b222bd) |  | watch |  |
| 2026-10-12 | usearazzo/arazzo-toolkit | pr | [feat(runner): run petstore workflows e2e and publish the runner](https://github.com/usearazzo/arazzo-toolkit/pull/233) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-10-12 | usearazzo/arazzo-toolkit | issue | [runner: CommonJS entry fails to load — missing vendor/swagger-client.cjs](https://github.com/usearazzo/arazzo-toolkit/issues/234) | runner, spec | watch | Arazzo runner / step execution |
| 2026-10-12 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) | depbump | watch | Dependency maintenance |
| 2026-10-12 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-12 (#231)](https://github.com/OAI/landscape/commit/bf01e146e0f5dcdeea2058febbb4b22d7ecb97a0) |  | watch |  |
| 2026-10-11 | OAI/build-infra | issue | [RSS feed not updated with published releases.](https://github.com/OAI/build-infra/issues/70) |  | watch |  |
| 2026-10-11 | OAI/Overlay-Specification | issue | [Traits fixtures use a target that is not RFC 9535 (`@.x-oai-traits`)](https://github.com/OAI/Overlay-Specification/issues/415) | spec | watch | Conformance / schema validation |
| 2026-10-11 | usearazzo/arazzo-toolkit | issue | [validator: track new validation and lint rules](https://github.com/usearazzo/arazzo-toolkit/issues/197) | loop, spec | watch | Issue #410 loops vs goto |
| 2026-10-11 | usearazzo/website | commit | [feat(blog): add the validate-from-the-command-line post](https://github.com/usearazzo/website/commit/8ef160a4edbd679655790b9425406a20f0f55ae1) |  | watch |  |
| 2026-10-11 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-11 (#230)](https://github.com/OAI/landscape/commit/ba86c13f1e2409a5d42d0f878e2a333d019277c4) |  | watch |  |
| 2026-10-11 | usearazzo/website.feed | article | [Validate Arazzo Documents From the Command Line](https://usearazzo.com/blog/validate-arazzo-documents-from-the-command-line/) | cli, schema, spec | watch | P1-7 JSON Schema layer |
| 2026-10-10 | OAI/learn.openapis.org | pr | [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) |  | actionable |  |
| 2026-10-10 | usearazzo/arazzo-toolkit | pr | [docs(spec): scaffold phase 5 — runner-petstore-workflow](https://github.com/usearazzo/arazzo-toolkit/pull/232) | cli, spec | actionable | P2-1 CLI binary |
| 2026-10-10 | usearazzo/arazzo-toolkit | pr | [feat(runner): treat a called workflow as its step's result](https://github.com/usearazzo/arazzo-toolkit/pull/230) | actor, breaking, runner, spec | breaking | Arazzo runner / step execution |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [runner: response context and $outputs for steps that call a workflow](https://github.com/usearazzo/arazzo-toolkit/issues/228) | loop, runner, spec | watch | Arazzo runner / step execution |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [runner: record criterion diagnostics and fail unavailable criteria softly](https://github.com/usearazzo/arazzo-toolkit/issues/231) | cli, runner, spec | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [runner: pass a called workflow's outputs through as the calling step's outputs](https://github.com/usearazzo/arazzo-toolkit/issues/229) | breaking, runner, spec | breaking | Arazzo runner / step execution |
| 2026-10-10 | OAI/Arazzo-Specification | pr | [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) | xml, xpath, schema, runner, spec | watch | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-10-10 | usearazzo/arazzo-toolkit | pr | [fix(runner): evaluate runtime expressions in request payloads](https://github.com/usearazzo/arazzo-toolkit/pull/227) | xml, actor, breaking, runner, depbump | breaking | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [runner: embedded runtime expressions in requestBody payloads are sent verbatim (spec MUST, 1.0.1)](https://github.com/usearazzo/arazzo-toolkit/issues/129) | runner, spec | watch | Arazzo runner / step execution |
| 2026-10-10 | swaggerexpert/arazzo-runtime-expression | release | [v3.3.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.3.0) | spec | actionable | Conformance / schema validation |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [runner: validate workflow inputs against the inputs schema and apply defaults before a run](https://github.com/usearazzo/arazzo-toolkit/issues/97) | schema, runner, spec | watch | P1-7 JSON Schema layer |
| 2026-10-10 | strefethen/arazzo-cli | commit | [fix(expr): refuse JSONPath queries nested deeper than 8 brackets](https://github.com/strefethen/arazzo-cli/commit/af6ee9df1b1d15e0b00e8d171d072dc37b70c045) | cli | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/website | commit | [chore: add the owner-voice skill and record the CLI release in project notes](https://github.com/usearazzo/website/commit/25d8affa10dc70f901d9cb4a68f90e34c4814e33) | cli | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/website | commit | [feat(blog): add the @usearazzo/cli release post](https://github.com/usearazzo/website/commit/76afac7e32bcc304b20991f1dc506831250724a1) | cli, spec | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/website | commit | [docs: say the CLI is on npm across the site](https://github.com/usearazzo/website/commit/c9deafc32dea5dabca0798dfcd2c6e777551f3a1) | cli | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/website | commit | [feat(cli): rewrite the product page for the published CLI](https://github.com/usearazzo/website/commit/624b0621ab8254ab5785ae83f74f7d7f78a8099f) | cli | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/website | commit | [feat(docs): add @usearazzo/cli reference](https://github.com/usearazzo/website/commit/1313e297606e1b45e9767f281283d24742587d95) | cli, spec | watch | P2-1 CLI binary |
| 2026-10-10 | usearazzo/arazzo-toolkit | issue | [cli: new @usearazzo/cli package — proposed command surface](https://github.com/usearazzo/arazzo-toolkit/issues/84) | cli, human, runner, spec | watch | P2-1 CLI binary |
| 2026-10-10 | npm.@usearazzo/validator | release | [@usearazzo/validator@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.7) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-10-10 | npm.@usearazzo/resolver | release | [@usearazzo/resolver@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/resolver/v/1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-10 | npm.@usearazzo/cli | release | [@usearazzo/cli@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/cli/v/1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-10 | npm.@usearazzo/parser | release | [@usearazzo/parser@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/parser/v/1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-10 | usearazzo/arazzo-toolkit | release | [v1.0.1-alpha.7](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.7) | cli, spec | actionable | P2-1 CLI binary |
| 2026-10-10 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump chalk from 5.6.2 to 6.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/223) | breaking, depbump | breaking | Dependency maintenance |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
