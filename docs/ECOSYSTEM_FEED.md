# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-09T13:14:08+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 862 (showing 200 newest)
- **Severity:** breaking **38** · actionable **492** · watch **332**
- **Top relevance:** `Conformance / schema validation` (341) · `uncategorized` (149) · `Dependency maintenance` (111) · `P2-1 CLI binary` (74) · `P1-7 JSON Schema layer` (44)
- **Top sources:** `strefethen/arazzo-cli` (58) · `OAI/Arazzo-Specification` (51) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40) · `Specmatic/specmatic` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (10)

- `2026-10-09` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-10-04` [docs(validator): link the README to the product page](https://github.com/usearazzo/arazzo-toolkit/pull/207) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-10-04` [docs: mark roadmap Phases 1 and 3 as shipped](https://github.com/usearazzo/arazzo-toolkit/pull/205) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-10-04` [fix(validator): install the workspace parser, not alpha.2](https://github.com/usearazzo/arazzo-toolkit/pull/203) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- … and 2 more in this group (see All events table)

### P1-7 JSON Schema layer (9)

- `2026-10-07` [feat(cli): add @usearazzo/cli package with validate command](https://github.com/usearazzo/arazzo-toolkit/pull/220) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,schema,runner,depbump_
- `2026-10-04` [docs: mark roadmap Phase 4 as shipped](https://github.com/usearazzo/arazzo-toolkit/pull/208) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,schema,spec_
- `2026-10-04` [chore(validator): prepare the package for its first publish](https://github.com/usearazzo/arazzo-toolkit/pull/206) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,schema,spec_
- `2026-10-04` [docs(validator): drop the baseURI advice for external $refs](https://github.com/usearazzo/arazzo-toolkit/pull/204) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,schema,spec_
- `2026-09-20` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-10-09` [OAS 3.1.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- … and 1 more in this group (see All events table)

### Dependency maintenance (8)

- `2026-10-08` [chore(deps): bump chalk from 5.6.2 to 6.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/223) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-07` [chore(deps): bump commander from 14.0.3 to 15.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/221) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-06` [build(deps): bump actions/checkout from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/208) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2026-10-06` [Bump just-the-docs from 0.11.1 to 0.12.0](https://github.com/OAI/learn.openapis.org/pull/180) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 6](https://github.com/jentic/arazzo-engine/pull/136) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,runner,spec_
- `2024-09-25` [Arazzo 1.0.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,schema,runner,spec_

### API security (OAI sig-security) (2)

- `2026-08-21` [\[Announcement\] OAuth2.1 and OAuth3 drafts](https://github.com/OAI/sig-security/issues/30) — `OAI/sig-security` · `issue` · _breaking,security,spec_
- `2020-02-21` [OAS 3.0.3 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.3) — `OAI/OpenAPI-Specification` · `release` · _breaking,security,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### P2-1 CLI binary (2)

- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-30` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (242)

- `2026-10-09` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-09` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-09` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-09` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-09` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-09` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-09` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-09` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 234 more in this group (see All events table)

### uncategorized (61)

- `2026-10-09` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-09` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-09` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-09` [Clarify the meeting time in the template](https://github.com/OAI/OpenAPI-Specification/pull/5567) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Explain that schemas are for mandatory requirements](https://github.com/OAI/OpenAPI-Specification/pull/5183) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Add comment, re-trigger auto-merge](https://github.com/OAI/OpenAPI-Specification/pull/5500) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Migrate to yarn to avoid npm workarounds](https://github.com/OAI/OpenAPI-Specification/pull/5503) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- … and 53 more in this group (see All events table)

### P2-1 CLI binary (49)

- `2026-10-09` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-09` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 41 more in this group (see All events table)

### Dependency maintenance (48)

- `2026-10-08` [chore(deps-dev): bump globals from 17.12.0 to 17.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/224) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-07` [chore(deps): bump yaml from 2.9.0 to 2.9.1](https://github.com/usearazzo/arazzo-toolkit/pull/222) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump nx from 23.2.0 to 23.2.1](https://github.com/usearazzo/arazzo-toolkit/pull/217) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump typescript-eslint from 8.70.1 to 8.71.0](https://github.com/usearazzo/arazzo-toolkit/pull/215) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump mocha from 12.0.2 to 12.0.3](https://github.com/usearazzo/arazzo-toolkit/pull/213) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps): bump source-map-js from 1.2.1 to 1.2.2](https://github.com/usearazzo/arazzo-toolkit/pull/216) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump @babel/plugin-transform-runtime from 8.0.1 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/214) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [build(deps): bump dependabot/fetch-metadata from 3.0.0 to 3.1.0](https://github.com/OAI/learn.openapis.org/pull/211) — `OAI/learn.openapis.org` · `pr` · _depbump_
- … and 40 more in this group (see All events table)

### P1-7 JSON Schema layer (23)

- `2026-10-07` [v3.2: Fix JSON Schema keyword name. (port of 5195)](https://github.com/OAI/OpenAPI-Specification/pull/5197) — `OAI/OpenAPI-Specification` · `pr` · _schema_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.9](https://github.com/OAI/learn.openapis.org/pull/213) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-05` [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-04` [@usearazzo/validator@1.0.1-alpha.6](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.6) — `npm.@usearazzo/validator` · `release` · _schema,spec_
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

### Conformance / schema validation (89)

- `2026-10-09` [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-08` [Open Community (TDC) Meeting, Thursday 08 October 2026](https://github.com/OAI/OpenAPI-Specification/issues/5562) — `OAI/OpenAPI-Specification` · `issue` · _spec_
- `2026-10-08` [Open Community (TDC) Meeting, Thursday 15 October 2026](https://github.com/OAI/OpenAPI-Specification/issues/5566) — `OAI/OpenAPI-Specification` · `issue` · _spec_
- `2026-10-08` [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) — `OAI/OpenAPI-Specification` · `issue` · _spec_
- `2026-10-08` [feat: Import OpenAPI spec from Issue #25029 (#25036)](https://github.com/jentic/jentic-public-apis/commit/5db6f7433d4e4d5aa143baaee347a7eec8356be5) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-08` [feat: Import OpenAPI spec from Issue #25027 (#25034)](https://github.com/jentic/jentic-public-apis/commit/a8abe8a39d8998b083500e5fa332ea6941af388b) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-08` [feat: Import OpenAPI spec from Issue #25024 (#25031)](https://github.com/jentic/jentic-public-apis/commit/a5da044219bcf187bc8a952104d89529361bef14) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-08` [feat: Import OpenAPI spec from Issue #25022 (#25028)](https://github.com/jentic/jentic-public-apis/commit/6908dd137a1eae8e7e31dbbfe617e31f045ba27f) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 81 more in this group (see All events table)

### uncategorized (88)

- `2026-10-09` [docs: add llm.txt with repository context](https://github.com/OAI/learn.openapis.org/pull/198) — `OAI/learn.openapis.org` · `pr` · _no tags_
- `2026-10-09` [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) — `OAI/learn.openapis.org` · `pr` · _no tags_
- `2026-10-09` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5565) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-09` [Update Landscape from LFX 2026-10-09 (#228)](https://github.com/OAI/landscape/commit/5088ee6de7f6afbcb44486b056f92de95c424f07) — `OAI/landscape` · `commit` · _no tags_
- `2026-10-09` [Rebuild apis.json, scores.json, and API browsing indexes (#25281)](https://github.com/jentic/jentic-public-apis/commit/6af8aeee9f38dbf15959a946f82cb24ef9144b4d) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-08` [v3.3: First pass at explaining SAFs](https://github.com/OAI/OpenAPI-Specification/pull/5447) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-08` [Rebuild apis.json, scores.json, and API browsing indexes (#25052)](https://github.com/jentic/jentic-public-apis/commit/2f2455f25b5770ed21646d0956deee6f6dc0cb07) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-08` [Rebuild apis.json, scores.json, and API browsing indexes (#25030)](https://github.com/jentic/jentic-public-apis/commit/af9fe4e88b9e53285e8359863c32a9720abf5ce6) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 80 more in this group (see All events table)

### Dependency maintenance (55)

- `2026-10-09` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-08` [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-06` [build(deps): bump jekyll-include-cache from 0.2.1 to 0.3.1](https://github.com/OAI/learn.openapis.org/pull/212) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-05` [Merge pull request #144 from OAI/dependabot/bundler/jekyll-include-cache-0.3.1](https://github.com/OAI/spec.openapis.org/commit/ea40577169de316c22b24219136ada123ff5af49) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [Merge pull request #145 from OAI/dependabot/bundler/jekyll-remote-theme-0.6.2](https://github.com/OAI/spec.openapis.org/commit/1112ad677f1712a5bafedbd71449795fa68997d0) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/commit/88146ad7e97d3019c71ae1bed1592d50b1765391) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/commit/f0f029b9c633895c13c987d6e788adad7256afb1) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-04` [Merge pull request #64 from OAI/dependabot/npm_and_yarn/vitest-92ac8026ca](https://github.com/OAI/build-infra/commit/c771ab929b8445becf4fd9349ab365823a553206) — `OAI/build-infra` · `commit` · _depbump_
- … and 47 more in this group (see All events table)

### P2-1 CLI binary (23)

- `2026-10-09` [chore: prepare v0.8.1 release](https://github.com/strefethen/arazzo-cli/commit/bd0647bd3a51542b105cba4047a9770ab9fd7595) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-09` [fix(expr): make null inequality complement equality](https://github.com/strefethen/arazzo-cli/commit/f0d9230d14ca96b07714e5baa161ad252fb24a28) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-10-09` [docs: serve CLI demo preview from the repository](https://github.com/strefethen/arazzo-cli/commit/bc7d9a7e607021ec7c1295ba3a478f7a1747370b) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-08` [chore: prepare v0.8.0 release](https://github.com/strefethen/arazzo-cli/commit/dca772010f091c5399b024982dfd3846d8413e72) — `strefethen/arazzo-cli` · `commit` · _cli,depbump_
- `2026-10-08` [docs: record bounded simple-condition evidence](https://github.com/strefethen/arazzo-cli/commit/b94f027fdca13f8cc22fec2973e7433e33d900cf) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-08` [fix(validate): retain component parameter diagnostic ownership](https://github.com/strefethen/arazzo-cli/commit/f3ddb326e2913669057fe07ca8d4b47d50550367) — `strefethen/arazzo-cli` · `commit` · _cli,runner_
- `2026-10-08` [fix(expr): cut over parsed expression and condition evaluation](https://github.com/strefethen/arazzo-cli/commit/fba4adc0fe53310cb888bc0cdf03df6283926a7b) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-08` [fix(expr): preserve literals and interpolation diagnostics](https://github.com/strefethen/arazzo-cli/commit/f39b2ab685eb08b6e7b3c250f9cd35d74062fe3d) — `strefethen/arazzo-cli` · `commit` · _cli_
- … and 15 more in this group (see All events table)

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

### Issue #410 kind discriminator / human-in-loop (12)

- `2026-10-08` [ci(rebuild-indexes): fix auto-merge race and never leave rebuild PRs open (#24896)](https://github.com/jentic/jentic-public-apis/commit/82c76c2267e4552fd0a4ceb659d76135c244bf74) — `jentic/jentic-public-apis` · `commit` · _actor,loop_
- `2026-10-05` [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-28` [Bump jmertic/lfx-landscape-tools from 20260916 to 20260923 in the all group (#217)](https://github.com/OAI/landscape/commit/9a4f7b0a15a7c053b56271599292151e873b3159) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor_
- … and 4 more in this group (see All events table)

### P1-7 JSON Schema layer (12)

- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-08` [Bump the hyperjump group with 2 updates](https://github.com/OAI/build-infra/pull/69) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-10-08` [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) — `OAI/learn.openapis.org` · `issue` · _schema,spec_
- `2026-10-08` [build(deps): bump @hyperjump/json-schema from 1.17.9 to 1.18.0](https://github.com/OAI/learn.openapis.org/pull/217) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.5](https://github.com/OAI/learn.openapis.org/pull/193) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-04` [docs(validator): JSON Schema validation covers Arazzo 1.1.0](https://github.com/usearazzo/website/commit/3d96e308603217dc3a54fd0475fed97c3977c20b) — `usearazzo/website` · `commit` · _schema,spec_
- `2026-09-27` [Merge pull request #60 from OAI/dependabot/npm_and_yarn/hyperjump-9c181a841b](https://github.com/OAI/build-infra/commit/135d2c92704b6628ef6f622231723845f7b4ad46) — `OAI/build-infra` · `commit` · _schema,depbump_
- … and 4 more in this group (see All events table)

### P2-2 MCP server exposure (11)

- `2026-10-09` [test(mcp): bound the .env subprocess wait and pin its stderr](https://github.com/strefethen/arazzo-cli/commit/57ca935392c24f05007f5113fd3c2634f0ef1497) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-09` [fix(cli,mcp): stop reading .env at the first hard I/O error](https://github.com/strefethen/arazzo-cli/commit/532b3ccce6d73018bc85c2bc8a20f7070200a186) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-08` [feat(cli,mcp): report what .env loading set, kept, and ignored](https://github.com/strefethen/arazzo-cli/commit/ac539720a80132c7d6f0184ad0d5b01675455912) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-10-08` [fix(cli,mcp): let the environment win over .env values](https://github.com/strefethen/arazzo-cli/commit/ba7497bb224307d5ca24e61dfec3d6e050c8e7fe) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-08` [fix(cli,mcp): skip .env lines that would panic instead of aborting](https://github.com/strefethen/arazzo-cli/commit/a6e1271a66b2e1a3ed707e727b5c280faa556fe6) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-01` [chore: ignore local MCP configuration](https://github.com/usearazzo/website/commit/9e14c5dd36ec8b09ea9b8c5ce07de337dd4cb61f) — `usearazzo/website` · `commit` · _mcp_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- … and 3 more in this group (see All events table)

### OAI Moonwalk (next-gen spec) (8)

- `2026-03-17` [Write ADR for identity vs location](https://github.com/OAI/sig-moonwalk/issues/92) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2025-11-12` [Create draft REST proposal](https://github.com/OAI/sig-moonwalk/pull/212) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-05-07` [Added preliminary design for resource model](https://github.com/OAI/sig-moonwalk/pull/183) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-02-23` [Added example of query parameter versioning](https://github.com/OAI/sig-moonwalk/pull/174) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-07-23` [Can the Data Types section be replaced by a reference to the format registry?](https://github.com/OAI/sig-moonwalk/issues/131) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Open API Path Templating vs WHATWG URL Pattern](https://github.com/OAI/sig-moonwalk/issues/125) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Allow recursive paths](https://github.com/OAI/sig-moonwalk/issues/117) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Structural improvements: inheritance on paths and its sublevels](https://github.com/OAI/sig-moonwalk/issues/115) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_

### Roadmap A2A step type (7)

- `2026-10-08` [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,depbump_
- `2026-10-08` [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump jekyll-remote-theme from 0.4.3 to 0.6.2](https://github.com/OAI/learn.openapis.org/pull/210) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-06` [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) — `OAI/Arazzo-Specification` · `pr` · _a2a,depbump_
- `2026-10-01` [fix(docs): say what the caret marks in the runtime expressions tutorial](https://github.com/usearazzo/website/commit/a2abbb6d90c5e61f176d6c034033d9f5947a7f54) — `usearazzo/website` · `commit` · _a2a_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_
- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Arazzo runner / step execution (4)

- `2026-10-02` [Clarify how to read outputs of a workflow run by a `retry` failure action](https://github.com/OAI/Arazzo-Specification/issues/590) — `OAI/Arazzo-Specification` · `issue` · _runner,spec_
- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-10-08` [fix(validate): enforce canonical expression field modes](https://github.com/strefethen/arazzo-cli/commit/e95ef11993fe3f1462097f1692aec54e6eeaba4e) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli_
- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-10-04` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,depbump_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Roadmap GraphQL step type (2)

- `2026-09-16` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-10-09 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-09 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-09 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-09 | openapi.tools | tool_collection | [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-09 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | OAI/learn.openapis.org | pr | [docs: add llm.txt with repository context](https://github.com/OAI/learn.openapis.org/pull/198) |  | watch |  |
| 2026-10-09 | OAI/learn.openapis.org | pr | [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) |  | watch |  |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/recheck@2.62.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.62.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.62.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.62.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/respect-core@2.62.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.62.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.62.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.62.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/client-generator@0.5.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.5.2) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/cli@2.62.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.62.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.62.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.62.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/respect-core@2.62.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.62.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/recheck@2.62.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.62.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.62.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.62.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/client-generator@0.5.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.5.1) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/cli@2.62.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.62.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5565) |  | watch |  |
| 2026-10-09 | OAI/OpenAPI-Specification | pr | [Clarify the meeting time in the template](https://github.com/OAI/OpenAPI-Specification/pull/5567) |  | actionable |  |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.61.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.61.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.61.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.61.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/client-generator@0.5.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.5.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/respect-core@2.61.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.61.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/recheck@2.61.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.61.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | Redocly/redocly-cli | release | [@redocly/cli@2.61.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.61.0) | spec | actionable | Conformance / schema validation |
| 2026-10-09 | strefethen/arazzo-cli | release | [v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli, spec | actionable | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | commit | [chore: prepare v0.8.1 release](https://github.com/strefethen/arazzo-cli/commit/bd0647bd3a51542b105cba4047a9770ab9fd7595) | cli | watch | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | commit | [fix(expr): make null inequality complement equality](https://github.com/strefethen/arazzo-cli/commit/f0d9230d14ca96b07714e5baa161ad252fb24a28) | cli, spec | watch | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | commit | [docs: serve CLI demo preview from the repository](https://github.com/strefethen/arazzo-cli/commit/bc7d9a7e607021ec7c1295ba3a478f7a1747370b) | cli | watch | P2-1 CLI binary |
| 2026-10-09 | strefethen/arazzo-cli | commit | [test(mcp): bound the .env subprocess wait and pin its stderr](https://github.com/strefethen/arazzo-cli/commit/57ca935392c24f05007f5113fd3c2634f0ef1497) | mcp, cli | watch | P2-2 MCP server exposure |
| 2026-10-09 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-09 (#228)](https://github.com/OAI/landscape/commit/5088ee6de7f6afbcb44486b056f92de95c424f07) |  | watch |  |
| 2026-10-09 | strefethen/arazzo-cli | commit | [fix(cli,mcp): stop reading .env at the first hard I/O error](https://github.com/strefethen/arazzo-cli/commit/532b3ccce6d73018bc85c2bc8a20f7070200a186) | mcp, cli | watch | P2-2 MCP server exposure |
| 2026-10-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#25281)](https://github.com/jentic/jentic-public-apis/commit/6af8aeee9f38dbf15959a946f82cb24ef9144b4d) |  | watch |  |
| 2026-10-09 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-09 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) | depbump | watch | Dependency maintenance |
| 2026-10-09 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-08 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump chalk from 5.6.2 to 6.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/223) | breaking, depbump | breaking | Dependency maintenance |
| 2026-10-08 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump globals from 17.12.0 to 17.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/224) | depbump | actionable | Dependency maintenance |
| 2026-10-08 | OAI/build-infra | pr | [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) | actor, a2a, depbump | watch | Roadmap A2A step type |
| 2026-10-08 | OAI/build-infra | pr | [Bump the hyperjump group with 2 updates](https://github.com/OAI/build-infra/pull/69) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-08 | OAI/build-infra | pr | [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) | a2a, depbump | watch | Roadmap A2A step type |
| 2026-10-08 | strefethen/arazzo-cli | release | [v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | xml, xpath, mcp, cli, spec | actionable | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-10-08 | strefethen/arazzo-cli | commit | [chore: prepare v0.8.0 release](https://github.com/strefethen/arazzo-cli/commit/dca772010f091c5399b024982dfd3846d8413e72) | cli, depbump | watch | P2-1 CLI binary |
| 2026-10-08 | OAI/learn.openapis.org | issue | [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-10-08 | OAI/learn.openapis.org | pr | [build(deps): bump @hyperjump/json-schema from 1.17.9 to 1.18.0](https://github.com/OAI/learn.openapis.org/pull/217) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-08 | OAI/learn.openapis.org | pr | [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) | depbump | watch | Dependency maintenance |
| 2026-10-08 | strefethen/arazzo-cli | commit | [docs: record bounded simple-condition evidence](https://github.com/strefethen/arazzo-cli/commit/b94f027fdca13f8cc22fec2973e7433e33d900cf) | cli | watch | P2-1 CLI binary |
| 2026-10-08 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 08 October 2026](https://github.com/OAI/OpenAPI-Specification/issues/5562) | spec | watch | Conformance / schema validation |
| 2026-10-08 | strefethen/arazzo-cli | commit | [fix(validate): retain component parameter diagnostic ownership](https://github.com/strefethen/arazzo-cli/commit/f3ddb326e2913669057fe07ca8d4b47d50550367) | cli, runner | watch | P2-1 CLI binary |
| 2026-10-08 | OAI/OpenAPI-Specification | pr | [v3.3: First pass at explaining SAFs](https://github.com/OAI/OpenAPI-Specification/pull/5447) |  | watch |  |
| 2026-10-08 | strefethen/arazzo-cli | commit | [fix(validate): enforce canonical expression field modes](https://github.com/strefethen/arazzo-cli/commit/e95ef11993fe3f1462097f1692aec54e6eeaba4e) | xml, xpath, cli | watch | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-10-08 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 15 October 2026](https://github.com/OAI/OpenAPI-Specification/issues/5566) | spec | watch | Conformance / schema validation |
| 2026-10-08 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#25052)](https://github.com/jentic/jentic-public-apis/commit/2f2455f25b5770ed21646d0956deee6f6dc0cb07) |  | watch |  |
| 2026-10-08 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25029 (#25036)](https://github.com/jentic/jentic-public-apis/commit/5db6f7433d4e4d5aa143baaee347a7eec8356be5) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25027 (#25034)](https://github.com/jentic/jentic-public-apis/commit/a8abe8a39d8998b083500e5fa332ea6941af388b) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25024 (#25031)](https://github.com/jentic/jentic-public-apis/commit/a5da044219bcf187bc8a952104d89529361bef14) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#25030)](https://github.com/jentic/jentic-public-apis/commit/af9fe4e88b9e53285e8359863c32a9720abf5ce6) |  | watch |  |
| 2026-10-08 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25022 (#25028)](https://github.com/jentic/jentic-public-apis/commit/6908dd137a1eae8e7e31dbbfe617e31f045ba27f) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25020 (#25026)](https://github.com/jentic/jentic-public-apis/commit/d40642f6bf159e41fa06be34219ca8c013d7bc7d) | spec | watch | Conformance / schema validation |
| 2026-10-08 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#25025)](https://github.com/jentic/jentic-public-apis/commit/0ddf1f9d3209e16a1ac5499916c94eedb9820856) |  | watch |  |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
