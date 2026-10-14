# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-13T14:08:51+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 883 (showing 200 newest)
- **Severity:** breaking **34** · actionable **507** · watch **342**
- **Top relevance:** `Conformance / schema validation` (340) · `uncategorized` (154) · `Dependency maintenance` (111) · `P2-1 CLI binary` (92) · `P1-7 JSON Schema layer` (44)
- **Top sources:** `strefethen/arazzo-cli` (62) · `OAI/Arazzo-Specification` (52) · `usearazzo/arazzo-toolkit` (43) · `OAI/build-infra` (41) · `speclynx/apidom` (40)
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

- `2026-10-13` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-01-23` [v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_

### P1-7 JSON Schema layer (5)

- `2026-10-07` [feat(cli): add @usearazzo/cli package with validate command](https://github.com/usearazzo/arazzo-toolkit/pull/220) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,schema,runner,depbump_
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

### Conformance / schema validation (247)

- `2026-10-13` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-13` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-13` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-13` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-13` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-13` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-13` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-13` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 239 more in this group (see All events table)

### uncategorized (62)

- `2026-10-13` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-13` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-13` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-10` [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) — `OAI/learn.openapis.org` · `pr` · _no tags_
- `2026-10-09` [Clarify the meeting time in the template](https://github.com/OAI/OpenAPI-Specification/pull/5567) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Explain that schemas are for mandatory requirements](https://github.com/OAI/OpenAPI-Specification/pull/5183) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Add comment, re-trigger auto-merge](https://github.com/OAI/OpenAPI-Specification/pull/5500) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-07` [Update the build-infra dependency](https://github.com/OAI/OpenAPI-Specification/pull/5520) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- … and 54 more in this group (see All events table)

### P2-1 CLI binary (55)

- `2026-10-13` [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-13` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 47 more in this group (see All events table)

### Dependency maintenance (50)

- `2026-10-13` [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/pull/146) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-12` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-09` [chore(deps): bump handlebars and lerna](https://github.com/usearazzo/arazzo-toolkit/pull/226) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-09` [chore(deps-dev): bump chai from 6.2.2 to 6.3.0](https://github.com/usearazzo/arazzo-toolkit/pull/225) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-08` [chore(deps-dev): bump globals from 17.12.0 to 17.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/224) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-07` [chore(deps): bump yaml from 2.9.0 to 2.9.1](https://github.com/usearazzo/arazzo-toolkit/pull/222) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump nx from 23.2.0 to 23.2.1](https://github.com/usearazzo/arazzo-toolkit/pull/217) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-06` [chore(deps-dev): bump typescript-eslint from 8.70.1 to 8.71.0](https://github.com/usearazzo/arazzo-toolkit/pull/215) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 42 more in this group (see All events table)

### P1-7 JSON Schema layer (24)

- `2026-10-12` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-10` [@usearazzo/validator@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.7) — `npm.@usearazzo/validator` · `release` · _schema,spec_
- `2026-10-07` [v3.2: Fix JSON Schema keyword name. (port of 5195)](https://github.com/OAI/OpenAPI-Specification/pull/5197) — `OAI/OpenAPI-Specification` · `pr` · _schema_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.9](https://github.com/OAI/learn.openapis.org/pull/213) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-05` [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-04` [v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) — `usearazzo/arazzo-toolkit` · `release` · _schema,spec_
- `2026-10-01` [v6.17.0](https://github.com/stoplightio/spectral/releases/tag/v6.17.0) — `stoplightio/spectral` · `release` · _schema,depbump_
- `2026-09-27` [Bump @hyperjump/json-schema-coverage from 1.2.2 to 1.3.0 in the hyperjump group across 1 directory](https://github.com/OAI/build-infra/pull/60) — `OAI/build-infra` · `pr` · _schema,depbump_
- … and 16 more in this group (see All events table)

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

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-10-06` [chore(deps-dev): bump shell-quote from 1.10.0 to 1.12.0](https://github.com/usearazzo/arazzo-toolkit/pull/218) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-10-05` [chore(deps-dev): bump @microsoft/api-extractor from 7.59.2 to 7.59.3](https://github.com/usearazzo/arazzo-toolkit/pull/210) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-27` [Bump vite from 8.3.0 to 8.3.1 in the vitest group across 1 directory](https://github.com/OAI/build-infra/pull/63) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-27` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- … and 5 more in this group (see All events table)

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

### Roadmap A2A step type (6)

- `2026-10-12` [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) — `OAI/Arazzo-Specification` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump prettier from 3.8.3 to 3.9.9](https://github.com/OAI/learn.openapis.org/pull/214) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump yaml from 2.8.3 to 2.9.1](https://github.com/OAI/learn.openapis.org/pull/215) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/pull/145) — `OAI/spec.openapis.org` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

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

- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26928)](https://github.com/jentic/jentic-public-apis/commit/80e3b57d57f486d4ea2cbec7e0ad1eba96360504) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26920)](https://github.com/jentic/jentic-public-apis/commit/fae39229e800179eacf3ab62d5e9501f049da22a) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26915)](https://github.com/jentic/jentic-public-apis/commit/fb0b5ed777b495fff98c07c4f8486c6febeac56f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26913)](https://github.com/jentic/jentic-public-apis/commit/7874d52df87218d969c07021c861a0f753137f2f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26911)](https://github.com/jentic/jentic-public-apis/commit/0ad93f0d4084fa3c25f30073575da46f4ad92cde) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26908)](https://github.com/jentic/jentic-public-apis/commit/fbafeded185d010a964ccbd590d7863cd9f24521) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26905)](https://github.com/jentic/jentic-public-apis/commit/ea5224186986c0124cefdd2009db57cfe33bb5d9) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-13` [Rebuild apis.json, scores.json, and API browsing indexes (#26899)](https://github.com/jentic/jentic-public-apis/commit/e559091e72acdbc25c834a612e9ccacf586656cd) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 84 more in this group (see All events table)

### Conformance / schema validation (86)

- `2026-10-13` [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24398 (#24401)](https://github.com/jentic/jentic-public-apis/commit/d96bc48f2b71901a3f4305d6d961bea203529a99) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24360 (#24363)](https://github.com/jentic/jentic-public-apis/commit/43edb9f0ad0f65c855de1d36b39e8ab573490e28) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24338 (#24341)](https://github.com/jentic/jentic-public-apis/commit/b1447268de8fd372f593998e316485672e66145b) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24346 (#24349)](https://github.com/jentic/jentic-public-apis/commit/ff0dd946c91b8f227ff9d58a8e39fc12adda4be5) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24333 (#24337)](https://github.com/jentic/jentic-public-apis/commit/e9f62b47a52d1f59489847157c4aff728796020f) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #25605 (#25608)](https://github.com/jentic/jentic-public-apis/commit/f31d591c586f76ccfcb9643abfdff8c1801eed16) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-13` [feat: Import OpenAPI spec from Issue #24336 (#24339)](https://github.com/jentic/jentic-public-apis/commit/e3fce0761bdc2f0a9a6cd0815d7c603afb3cb080) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 78 more in this group (see All events table)

### Dependency maintenance (53)

- `2026-10-13` [Merge pull request #146 from OAI/dependabot/github_actions/ruby/setup-ruby-1.328.0](https://github.com/OAI/spec.openapis.org/commit/76377bc7d66a96273e451be8940fc05b774ffbb8) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-13` [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/commit/83906696e2b59490204ba7abe7edea5998444798) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-12` [Merge pull request #591 from OAI/dependabot/npm_and_yarn/respec-37.4.1](https://github.com/OAI/Arazzo-Specification/commit/0c8c92eeacbe0e0def36ba30ff2782fb125aefc4) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-10-12` [Merge pull request #588 from OAI/dependabot/npm_and_yarn/vitest-4ca34babcb](https://github.com/OAI/Arazzo-Specification/commit/e9ed312b074cbedd52284cd0cfd815376cebd15f) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-10-09` [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-06` [build(deps): bump jekyll-include-cache from 0.2.1 to 0.3.1](https://github.com/OAI/learn.openapis.org/pull/212) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-06` [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/commit/12cb27d294172e13f53d4fbd6a3a9687d2e4af0f) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-10-05` [Merge pull request #144 from OAI/dependabot/bundler/jekyll-include-cache-0.3.1](https://github.com/OAI/spec.openapis.org/commit/ea40577169de316c22b24219136ada123ff5af49) — `OAI/spec.openapis.org` · `commit` · _depbump_
- … and 45 more in this group (see All events table)

### P2-1 CLI binary (35)

- `2026-10-13` [fix(deps): patch quinn-proto and refresh debugger docs](https://github.com/strefethen/arazzo-cli/commit/47ddf1c203e7a94d688bbb2f68b986eebc3aa79a) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-13` [docs: clarify contribution policy](https://github.com/strefethen/arazzo-cli/commit/eb9c323e651c88dce6b67502b95310c0fbe30498) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [runner: record criterion diagnostics and fail unavailable criteria softly](https://github.com/usearazzo/arazzo-toolkit/issues/231) — `usearazzo/arazzo-toolkit` · `issue` · _cli,runner,spec_
- `2026-10-10` [fix(expr): refuse JSONPath queries nested deeper than 8 brackets](https://github.com/strefethen/arazzo-cli/commit/af6ee9df1b1d15e0b00e8d171d072dc37b70c045) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [chore: add the owner-voice skill and record the CLI release in project notes](https://github.com/usearazzo/website/commit/25d8affa10dc70f901d9cb4a68f90e34c4814e33) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(blog): add the @usearazzo/cli release post](https://github.com/usearazzo/website/commit/76afac7e32bcc304b20991f1dc506831250724a1) — `usearazzo/website` · `commit` · _cli,spec_
- `2026-10-10` [docs: say the CLI is on npm across the site](https://github.com/usearazzo/website/commit/c9deafc32dea5dabca0798dfcd2c6e777551f3a1) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(cli): rewrite the product page for the published CLI](https://github.com/usearazzo/website/commit/624b0621ab8254ab5785ae83f74f7d7f78a8099f) — `usearazzo/website` · `commit` · _cli_
- … and 27 more in this group (see All events table)

### P1-7 JSON Schema layer (15)

- `2026-10-12` [Merge pull request #594 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.18.0](https://github.com/OAI/Arazzo-Specification/commit/79ddb24a832ea35d2d11e5674c40fd4f63ab906e) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-12` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/commit/3fe1737618afed353f346e8086a32716b44194ed) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-11` [Validate Arazzo Documents From the Command Line](https://usearazzo.com/blog/validate-arazzo-documents-from-the-command-line/) — `usearazzo/website.feed` · `article` · _cli,schema,spec_
- `2026-10-10` [runner: validate workflow inputs against the inputs schema and apply defaults before a run](https://github.com/usearazzo/arazzo-toolkit/issues/97) — `usearazzo/arazzo-toolkit` · `issue` · _schema,runner,spec_
- `2026-10-09` [Bump the hyperjump group with 2 updates](https://github.com/OAI/build-infra/pull/69) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-08` [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) — `OAI/learn.openapis.org` · `issue` · _schema,spec_
- `2026-10-08` [build(deps): bump @hyperjump/json-schema from 1.17.9 to 1.18.0](https://github.com/OAI/learn.openapis.org/pull/217) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- … and 7 more in this group (see All events table)

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

### Arazzo runner / step execution (7)

- `2026-10-12` [runner: CommonJS entry fails to load — missing vendor/swagger-client.cjs](https://github.com/usearazzo/arazzo-toolkit/issues/234) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-10` [runner: response context and $outputs for steps that call a workflow](https://github.com/usearazzo/arazzo-toolkit/issues/228) — `usearazzo/arazzo-toolkit` · `issue` · _loop,runner,spec_
- `2026-10-10` [runner: embedded runtime expressions in requestBody payloads are sent verbatim (spec MUST, 1.0.1)](https://github.com/usearazzo/arazzo-toolkit/issues/129) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-02` [Clarify how to read outputs of a workflow run by a `retry` failure action](https://github.com/OAI/Arazzo-Specification/issues/590) — `OAI/Arazzo-Specification` · `issue` · _runner,spec_
- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P2-2 MCP server exposure (7)

- `2026-10-09` [test(mcp): bound the .env subprocess wait and pin its stderr](https://github.com/strefethen/arazzo-cli/commit/57ca935392c24f05007f5113fd3c2634f0ef1497) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-09` [fix(cli,mcp): stop reading .env at the first hard I/O error](https://github.com/strefethen/arazzo-cli/commit/532b3ccce6d73018bc85c2bc8a20f7070200a186) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- `2026-09-10` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-10-10` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,runner,spec_
- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### Roadmap A2A step type (4)

- `2026-10-09` [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,depbump_
- `2026-10-09` [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump jekyll-remote-theme from 0.4.3 to 0.6.2](https://github.com/OAI/learn.openapis.org/pull/210) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_

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
| 2026-10-13 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-13 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-13 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-13 | openapi.tools | tool_collection | [openapi.tools checksum 7f4f72373348](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-13 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.7](https://github.com/speclynx/apidom/releases/tag/v5.2.7) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.3.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.3.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.7](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | speclynx/apidom | release | [v5.2.7](https://github.com/speclynx/apidom/releases/tag/v5.2.7) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26928)](https://github.com/jentic/jentic-public-apis/commit/80e3b57d57f486d4ea2cbec7e0ad1eba96360504) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24398 (#24401)](https://github.com/jentic/jentic-public-apis/commit/d96bc48f2b71901a3f4305d6d961bea203529a99) | spec | watch | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.63.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.63.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/respect-core@2.63.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.63.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/recheck@2.63.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.63.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.63.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.63.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/client-generator@0.6.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.6.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/cli@2.63.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.63.0) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26920)](https://github.com/jentic/jentic-public-apis/commit/fae39229e800179eacf3ab62d5e9501f049da22a) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24360 (#24363)](https://github.com/jentic/jentic-public-apis/commit/43edb9f0ad0f65c855de1d36b39e8ab573490e28) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26915)](https://github.com/jentic/jentic-public-apis/commit/fb0b5ed777b495fff98c07c4f8486c6febeac56f) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24338 (#24341)](https://github.com/jentic/jentic-public-apis/commit/b1447268de8fd372f593998e316485672e66145b) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26913)](https://github.com/jentic/jentic-public-apis/commit/7874d52df87218d969c07021c861a0f753137f2f) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24346 (#24349)](https://github.com/jentic/jentic-public-apis/commit/ff0dd946c91b8f227ff9d58a8e39fc12adda4be5) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26911)](https://github.com/jentic/jentic-public-apis/commit/0ad93f0d4084fa3c25f30073575da46f4ad92cde) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24333 (#24337)](https://github.com/jentic/jentic-public-apis/commit/e9f62b47a52d1f59489847157c4aff728796020f) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26908)](https://github.com/jentic/jentic-public-apis/commit/fbafeded185d010a964ccbd590d7863cd9f24521) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #25605 (#25608)](https://github.com/jentic/jentic-public-apis/commit/f31d591c586f76ccfcb9643abfdff8c1801eed16) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26905)](https://github.com/jentic/jentic-public-apis/commit/ea5224186986c0124cefdd2009db57cfe33bb5d9) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24336 (#24339)](https://github.com/jentic/jentic-public-apis/commit/e3fce0761bdc2f0a9a6cd0815d7c603afb3cb080) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26899)](https://github.com/jentic/jentic-public-apis/commit/e559091e72acdbc25c834a612e9ccacf586656cd) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24315 (#24318)](https://github.com/jentic/jentic-public-apis/commit/52cb3f82d762b306a15fda7b6c5fc9a6a0f312bd) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26897)](https://github.com/jentic/jentic-public-apis/commit/fe1541d3d9e95ed4af928dda79f66d48df126e9e) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24309 (#24312)](https://github.com/jentic/jentic-public-apis/commit/6ac124803be00464c469112ba1badf9ee5aa868f) | spec | watch | Conformance / schema validation |
| 2026-10-13 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#26896)](https://github.com/jentic/jentic-public-apis/commit/568535a14df119816fe55342e6e9ae5ba5966e38) |  | watch |  |
| 2026-10-13 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24307 (#24310)](https://github.com/jentic/jentic-public-apis/commit/8179ed004c419cb23a694dc64fbf445eb37a89ad) | spec | watch | Conformance / schema validation |
| 2026-10-13 | spec.arazzo.html | spec_html_checksum | [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) | spec | watch | Conformance / schema validation |
| 2026-10-13 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | spec | watch | Conformance / schema validation |
| 2026-10-13 | spec.arazzo.schema.1.0 | schema_checksum | [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) | spec | watch | Conformance / schema validation |
| 2026-10-13 | OAI/spec.openapis.org | pr | [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/pull/146) | depbump | actionable | Dependency maintenance |
| 2026-10-13 | OAI/spec.openapis.org | commit | [Merge pull request #146 from OAI/dependabot/github_actions/ruby/setup-ruby-1.328.0](https://github.com/OAI/spec.openapis.org/commit/76377bc7d66a96273e451be8940fc05b774ffbb8) | depbump | watch | Dependency maintenance |
| 2026-10-13 | OAI/Arazzo-Specification | pr | [v1.1-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/596) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | issue | [Meeting: Oct 14th, 2026](https://github.com/OAI/Arazzo-Specification/issues/597) | spec | watch | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | pr | [v1.2-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/598) | spec | watch | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | pr | [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/595) | spec | watch | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | issue | [Meeting: Sept 16th, 2026](https://github.com/OAI/Arazzo-Specification/issues/579) | spec | watch | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | pr | [dev: sync with main](https://github.com/OAI/Arazzo-Specification/pull/585) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/Arazzo-Specification | pr | [chore(README): add UseArazzo parser, resolver, and validator to Tooling](https://github.com/OAI/Arazzo-Specification/pull/592) | spec | watch | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.62.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.62.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/respect-core@2.62.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.62.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/recheck@2.62.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.62.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.62.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.62.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/client-generator@0.5.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.5.3) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | Redocly/redocly-cli | release | [@redocly/cli@2.62.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.62.2) | spec | actionable | Conformance / schema validation |
| 2026-10-13 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-13 (#232)](https://github.com/OAI/landscape/commit/de98f048c30a96a60ff094d7d876be844a9e3413) |  | watch |  |
| 2026-10-13 | strefethen/arazzo-cli | commit | [fix(deps): patch quinn-proto and refresh debugger docs](https://github.com/strefethen/arazzo-cli/commit/47ddf1c203e7a94d688bbb2f68b986eebc3aa79a) | cli | watch | P2-1 CLI binary |
| 2026-10-13 | strefethen/arazzo-cli | commit | [docs: clarify contribution policy](https://github.com/strefethen/arazzo-cli/commit/eb9c323e651c88dce6b67502b95310c0fbe30498) | cli | watch | P2-1 CLI binary |
| 2026-10-13 | OAI/spec.openapis.org | commit | [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/commit/83906696e2b59490204ba7abe7edea5998444798) | depbump | watch | Dependency maintenance |
| 2026-10-12 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) | schema, depbump | actionable | P1-7 JSON Schema layer |
| 2026-10-12 | OAI/Arazzo-Specification | commit | [Merge pull request #594 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.18.0](https://github.com/OAI/Arazzo-Specification/commit/79ddb24a832ea35d2d11e5674c40fd4f63ab906e) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-12 | OAI/Arazzo-Specification | commit | [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/commit/3fe1737618afed353f346e8086a32716b44194ed) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-10-12 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) | a2a, depbump | actionable | Roadmap A2A step type |
| 2026-10-12 | OAI/Arazzo-Specification | commit | [Merge pull request #591 from OAI/dependabot/npm_and_yarn/respec-37.4.1](https://github.com/OAI/Arazzo-Specification/commit/0c8c92eeacbe0e0def36ba30ff2782fb125aefc4) | depbump | watch | Dependency maintenance |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
