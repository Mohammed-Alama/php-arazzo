# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-06T13:17:28+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 862 (showing 200 newest)
- **Severity:** breaking **37** · actionable **480** · watch **345**
- **Top relevance:** `Conformance / schema validation` (344) · `uncategorized` (152) · `Dependency maintenance` (109) · `P2-1 CLI binary` (76) · `P1-7 JSON Schema layer` (39)
- **Top sources:** `strefethen/arazzo-cli` (54) · `OAI/Arazzo-Specification` (51) · `OAI/OpenAPI-Specification` (42) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (10)

- `2026-10-06` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-10-04` [docs(validator): link the README to the product page](https://github.com/usearazzo/arazzo-toolkit/pull/207) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-10-04` [docs: mark roadmap Phases 1 and 3 as shipped](https://github.com/usearazzo/arazzo-toolkit/pull/205) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-10-04` [fix(validator): install the workspace parser, not alpha.2](https://github.com/usearazzo/arazzo-toolkit/pull/203) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- … and 2 more in this group (see All events table)

### P1-7 JSON Schema layer (10)

- `2026-10-04` [docs: mark roadmap Phase 4 as shipped](https://github.com/usearazzo/arazzo-toolkit/pull/208) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,schema,spec_
- `2026-10-04` [chore(validator): prepare the package for its first publish](https://github.com/usearazzo/arazzo-toolkit/pull/206) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,schema,spec_
- `2026-10-04` [docs(validator): drop the baseURI advice for external $refs](https://github.com/usearazzo/arazzo-toolkit/pull/204) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,schema,spec_
- `2026-10-04` [fix(validator): check Arazzo 1.1.0 documents against JSON Schema](https://github.com/usearazzo/arazzo-toolkit/pull/202) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,schema,spec_
- `2026-10-02` [refactor(validator): migrate to @speclynx/api-languageservice](https://github.com/usearazzo/arazzo-toolkit/pull/196) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,schema,depbump_
- `2026-09-20` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- … and 2 more in this group (see All events table)

### Dependency maintenance (5)

- `2026-09-11` [Bump build-infra to 1.0.2](https://github.com/OAI/OpenAPI-Specification/pull/5552) — `OAI/OpenAPI-Specification` · `pr` · _breaking,depbump_
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

### Issue #410 kind discriminator / human-in-loop (1)

- `2026-10-03` [feat(validator): align validator with parser and resolver](https://github.com/usearazzo/arazzo-toolkit/pull/201) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,spec_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-30` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (243)

- `2026-10-06` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-06` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-06` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-06` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-06` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-06` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-06` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-06` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 235 more in this group (see All events table)

### uncategorized (60)

- `2026-10-06` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-06` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-06` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-04` [Update weekly meeting agenda to include spec/schema publishing](https://github.com/OAI/OpenAPI-Specification/pull/5557) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-04` [Phase c evaluator plugins](https://github.com/Mohammed-Alama/php-arazzo/pull/73) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-02` [Install qualification runner dependencies](https://github.com/OAI/build-infra/pull/43) — `OAI/build-infra` · `pr` · _no tags_
- `2026-10-02` [Fix/vitest vite peer](https://github.com/OAI/build-infra/pull/41) — `OAI/build-infra` · `pr` · _no tags_
- `2026-10-02` [Use semver for release version parsing](https://github.com/OAI/build-infra/pull/39) — `OAI/build-infra` · `pr` · _no tags_
- … and 52 more in this group (see All events table)

### P2-1 CLI binary (48)

- `2026-10-06` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-06` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 40 more in this group (see All events table)

### Dependency maintenance (41)

- `2026-10-05` [chore(deps-dev): bump lint-staged from 17.5.1 to 17.6.0](https://github.com/usearazzo/arazzo-toolkit/pull/211) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-05` [chore(deps-dev): bump @types/node from 26.6.3 to 26.6.4](https://github.com/usearazzo/arazzo-toolkit/pull/209) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/pull/144) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-04` [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/64) — `OAI/build-infra` · `pr` · _depbump_
- `2026-10-02` [chore(deps-dev): bump @types/node from 26.6.2 to 26.6.3](https://github.com/usearazzo/arazzo-toolkit/pull/200) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-02` [chore(deps-dev): bump prettier from 3.9.8 to 3.9.9](https://github.com/usearazzo/arazzo-toolkit/pull/198) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-02` [build(deps-dev): bump jekyll-remote-theme from 0.5.2 to 0.6.0](https://github.com/OAI/spec.openapis.org/pull/142) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-02` [build(deps-dev): bump jekyll-include-cache from 0.2.2 to 0.3.0](https://github.com/OAI/spec.openapis.org/pull/143) — `OAI/spec.openapis.org` · `pr` · _depbump_
- … and 33 more in this group (see All events table)

### P1-7 JSON Schema layer (21)

- `2026-10-05` [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-04` [@usearazzo/validator@1.0.1-alpha.6](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.6) — `npm.@usearazzo/validator` · `release` · _schema,spec_
- `2026-10-04` [v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) — `usearazzo/arazzo-toolkit` · `release` · _schema,spec_
- `2026-10-01` [v6.17.0](https://github.com/stoplightio/spectral/releases/tag/v6.17.0) — `stoplightio/spectral` · `release` · _schema,depbump_
- `2026-09-27` [Bump @hyperjump/json-schema-coverage from 1.2.2 to 1.3.0 in the hyperjump group across 1 directory](https://github.com/OAI/build-infra/pull/60) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-09-25` [v1.25.3](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.3) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-09-21` [v1.25.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,depbump_
- … and 13 more in this group (see All events table)

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

- `2026-10-05` [chore(deps-dev): bump @microsoft/api-extractor from 7.59.2 to 7.59.3](https://github.com/usearazzo/arazzo-toolkit/pull/210) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-10-02` [chore(deps): bump vscode-languageserver-textdocument from 1.0.14 to 1.0.15](https://github.com/usearazzo/arazzo-toolkit/pull/190) — `usearazzo/arazzo-toolkit` · `pr` · _human,depbump_
- `2026-09-27` [Bump vite from 8.3.0 to 8.3.1 in the vitest group across 1 directory](https://github.com/OAI/build-infra/pull/63) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-27` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump respec from 37.3.6 to 37.4.0](https://github.com/OAI/Arazzo-Specification/pull/575) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- … and 6 more in this group (see All events table)

### P1-6 payload XPath / P0-5 XPath criteria (13)

- `2026-10-02` [chore(deps-dev): bump jsdom from 29.1.1 to 30.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/187) — `usearazzo/arazzo-toolkit` · `pr` · _xml,human,depbump_
- `2026-09-06` [feat: add phpmd, pdepend, phpdoc to quality pipeline](https://github.com/Mohammed-Alama/php-arazzo/pull/53) — `Mohammed-Alama/php-arazzo` · `pr` · _xml_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,security,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,depbump_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,loop,spec_
- … and 5 more in this group (see All events table)

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

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-09-07` [feat(runner): consume expression + document engines via public faces](https://github.com/Mohammed-Alama/php-arazzo/pull/69) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,actor,spec_
- `2026-09-07` [feat(document): validator consumes expression via its public face](https://github.com/Mohammed-Alama/php-arazzo/pull/68) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath_
- `2026-09-06` [richen public face covering cli/laravel needs — Closes #58](https://github.com/Mohammed-Alama/php-arazzo/pull/67) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,cli,actor_
- `2026-03-13` [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,loop,spec_

### Roadmap A2A step type (4)

- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/pull/145) — `OAI/spec.openapis.org` · `pr` · _a2a,depbump_
- `2026-10-02` [chore(deps-dev): bump ip-address from 10.7.0 to 10.7.3](https://github.com/usearazzo/arazzo-toolkit/pull/195) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

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

- `2026-10-06` [Rebuild apis.json, scores.json, and API browsing indexes (#24920)](https://github.com/jentic/jentic-public-apis/commit/b4c0af7a367f0552b782ff83500bfb000c2a4e6f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-06` [Update Landscape from LFX 2026-10-06 (#225)](https://github.com/OAI/landscape/commit/facdea5144386c2067798f3f378b11f162f3a646) — `OAI/landscape` · `commit` · _no tags_
- `2026-10-05` [Rebuild apis.json, scores.json, and API browsing indexes (#24915)](https://github.com/jentic/jentic-public-apis/commit/d6f6f530f41678c177d7eb1c7573aa6ee3d92bd5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-05` [Rebuild apis.json, scores.json, and API browsing indexes (#24911)](https://github.com/jentic/jentic-public-apis/commit/be765838530c49b617aabf7c016a7c7bc3bc3fc1) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-05` [Update Landscape from LFX 2026-10-05 (#223)](https://github.com/OAI/landscape/commit/a2c10166ad013b5ce11d578c2f25a21ca73bfe37) — `OAI/landscape` · `commit` · _no tags_
- `2026-10-04` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5565) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-10-04` [Merge pull request #65 from handrews/feature/downstream-md2html-fixtures](https://github.com/OAI/build-infra/commit/ef14c602a8fc72bd1f0e43ec91c7453e0936a963) — `OAI/build-infra` · `commit` · _no tags_
- `2026-10-04` [feat(validator): rewrite the product page for the published Validator](https://github.com/usearazzo/website/commit/3eb76b2186516ddc670f3ee0579cbd99aeca94d8) — `usearazzo/website` · `commit` · _no tags_
- … and 84 more in this group (see All events table)

### Conformance / schema validation (91)

- `2026-10-06` [openapi.tools checksum 565873db1ac1](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-06` [feat: Import OpenAPI spec from Issue #24918 (#24919)](https://github.com/jentic/jentic-public-apis/commit/83966539f10d2bc8aa3640a3984c2177775d333f) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-06` [validator: validate OpenAPI source descriptions, not just the Arazzo document](https://github.com/usearazzo/arazzo-toolkit/issues/212) — `usearazzo/arazzo-toolkit` · `issue` · _spec_
- `2026-10-06` [chore(README): add UseArazzo parser, resolver, and validator to Tooling](https://github.com/OAI/Arazzo-Specification/pull/592) — `OAI/Arazzo-Specification` · `pr` · _spec_
- `2026-10-06` [feat(blog): add the @usearazzo/validator release post](https://github.com/usearazzo/website/commit/35b38363e22c4aa0b52ba86a32f260e97a8c969c) — `usearazzo/website` · `commit` · _spec_
- `2026-10-06` [Automate downstream release PRs](https://github.com/OAI/build-infra/pull/66) — `OAI/build-infra` · `pr` · _spec_
- `2026-10-06` [@usearazzo/validator Is on npm. Here Is What It Took](https://usearazzo.com/blog/arazzo-validator-on-npm/) — `usearazzo/website.feed` · `article` · _spec_
- `2026-10-05` [feat: Import OpenAPI spec from Issue #24909 (#24912)](https://github.com/jentic/jentic-public-apis/commit/d364ebf0e118d9395c8c3c0ec0d7ae83e7a6ca18) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 83 more in this group (see All events table)

### Dependency maintenance (63)

- `2026-10-06` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-05` [Merge pull request #144 from OAI/dependabot/bundler/jekyll-include-cache-0.3.1](https://github.com/OAI/spec.openapis.org/commit/ea40577169de316c22b24219136ada123ff5af49) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [Merge pull request #145 from OAI/dependabot/bundler/jekyll-remote-theme-0.6.2](https://github.com/OAI/spec.openapis.org/commit/1112ad677f1712a5bafedbd71449795fa68997d0) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/commit/88146ad7e97d3019c71ae1bed1592d50b1765391) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-05` [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/commit/f0f029b9c633895c13c987d6e788adad7256afb1) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-04` [Merge pull request #64 from OAI/dependabot/npm_and_yarn/vitest-92ac8026ca](https://github.com/OAI/build-infra/commit/c771ab929b8445becf4fd9349ab365823a553206) — `OAI/build-infra` · `commit` · _depbump_
- `2026-10-02` [chore(deps): bump vscode-languageserver-types from 3.17.6-next.6 to 3.18.4](https://github.com/usearazzo/arazzo-toolkit/pull/191) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-02` [feat(ecosystem): add ten resources from a GitHub and web sweep](https://github.com/usearazzo/website/commit/21a5153706c52fbaebe09ebde4ce891823c5debf) — `usearazzo/website` · `commit` · _depbump_
- … and 55 more in this group (see All events table)

### P2-1 CLI binary (26)

- `2026-10-04` [docs: say the Validator is on npm across the site](https://github.com/usearazzo/website/commit/104858c30a39e32287ae1994fe50a2a40be6e7e8) — `usearazzo/website` · `commit` · _cli,a2a_
- `2026-09-18` [refactor(expr): extract simple-condition evaluation into its own module](https://github.com/strefethen/arazzo-cli/commit/38b60889ae05dd5d1b43e63afa04a445452a71bd) — `strefethen/arazzo-cli` · `commit` · _cli,actor,spec_
- `2026-09-17` [docs(plans): track the 2026-09-05 and 2026-09-12 code smell audits](https://github.com/strefethen/arazzo-cli/commit/5ce6548ddda9bee89ff2eb84f0852382efb9580a) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-16` [fix: update rustls for RUSTSEC-2026-0285](https://github.com/strefethen/arazzo-cli/commit/679af12f6ee088b5dace69b4693a5802f7ff5aeb) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-16` [chore: pin Rust 1.98.1 for builds and releases](https://github.com/strefethen/arazzo-cli/commit/43cb26abd42f6b8753006412076b83e2b0b177b5) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [chore(release): prepare 0.7.0 with the RFC 9535 JSONPath engine](https://github.com/strefethen/arazzo-cli/commit/9a1dd3fe5add36f541b17385a361afb21ff0e825) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(plans): complete the RFC 9535 JSONPath migration plan](https://github.com/strefethen/arazzo-cli/commit/26a3dc0137b8eabcef6ecd694a01be5785271af7) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(agents): drop the empty approved-extensions allowlist paragraph](https://github.com/strefethen/arazzo-cli/commit/48b0dff75b2c6e7bd8ba611d58a475ead732d740) — `strefethen/arazzo-cli` · `commit` · _cli_
- … and 18 more in this group (see All events table)

### API security (OAI sig-security) (16)

- `2026-10-02` [Auto-merge deletes the entire src tree](https://github.com/OAI/sig-security/issues/56) — `OAI/sig-security` · `issue` · _security_
- `2026-09-25` [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-24` [Support for GNAP](https://github.com/OAI/sig-security/issues/9) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-11` [v3.2: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5534) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-09-11` [v3.3: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5533) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- … and 8 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (12)

- `2026-10-05` [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-28` [Bump jmertic/lfx-landscape-tools from 20260916 to 20260923 in the all group (#217)](https://github.com/OAI/landscape/commit/9a4f7b0a15a7c053b56271599292151e873b3159) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-08` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
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

### P1-7 JSON Schema layer (8)

- `2026-10-04` [docs(validator): JSON Schema validation covers Arazzo 1.1.0](https://github.com/usearazzo/website/commit/3d96e308603217dc3a54fd0475fed97c3977c20b) — `usearazzo/website` · `commit` · _schema,spec_
- `2026-09-27` [Merge pull request #60 from OAI/dependabot/npm_and_yarn/hyperjump-9c181a841b](https://github.com/OAI/build-infra/commit/135d2c92704b6628ef6f622231723845f7b4ad46) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-27` [Bump @hyperjump/json-schema-coverage](https://github.com/OAI/build-infra/commit/a62c9551ac450bbe70e65ec2b4e34429368fa719) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,schema,spec_
- `2026-08-02` [Add Gesso (PHP OpenAPI 3.0/3.1/3.2 contract testing library)](https://github.com/OAI/tools.openapis.org/pull/273) — `OAI/tools.openapis.org` · `pr` · _schema,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,schema,depbump_
- `2026-03-16` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.5](https://github.com/OAI/learn.openapis.org/pull/193) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2024-05-24` [Support SHACL schema as alternative to json-schema](https://github.com/OAI/sig-moonwalk/issues/118) — `OAI/sig-moonwalk` · `issue` · _human,schema,moonwalk_

### P2-2 MCP server exposure (7)

- `2026-10-01` [chore: ignore local MCP configuration](https://github.com/usearazzo/website/commit/9e14c5dd36ec8b09ea9b8c5ce07de337dd4cb61f) — `usearazzo/website` · `commit` · _mcp_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- `2026-09-12` [fix(runtime): stop JSONPath delimiter runs from panicking criteria](https://github.com/strefethen/arazzo-cli/commit/48b426d56317ca9f2455a55e98e6e2f8dd21fedc) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-09-10` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_

### Roadmap A2A step type (6)

- `2026-10-06` [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) — `OAI/Arazzo-Specification` · `pr` · _a2a,depbump_
- `2026-10-05` [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-05` [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,depbump_
- `2026-10-01` [fix(docs): say what the caret marks in the runtime expressions tutorial](https://github.com/usearazzo/website/commit/a2abbb6d90c5e61f176d6c034033d9f5947a7f54) — `usearazzo/website` · `commit` · _a2a_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_
- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Arazzo runner / step execution (4)

- `2026-10-02` [Clarify how to read outputs of a workflow run by a `retry` failure action](https://github.com/OAI/Arazzo-Specification/issues/590) — `OAI/Arazzo-Specification` · `issue` · _runner,spec_
- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-13` [fix(runtime): decide jsonpath criteria by nodelist cardinality](https://github.com/strefethen/arazzo-cli/commit/7511e40d0113803274e95a3e3e0115cec3a916ee) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### P1-6 payload XPath / P0-5 XPath criteria (4)

- `2026-10-04` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,depbump_
- `2026-09-16` [v3.3: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5540) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-12` [v3.2: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5541) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Roadmap GraphQL step type (2)

- `2026-09-16` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### Issue #410 loops vs goto (1)

- `2026-10-02` [validator: track new validation and lint rules](https://github.com/usearazzo/arazzo-toolkit/issues/197) — `usearazzo/arazzo-toolkit` · `issue` · _loop,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-10-06 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-06 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-06 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-06 | openapi.tools | tool_collection | [openapi.tools checksum 565873db1ac1](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-06 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24920)](https://github.com/jentic/jentic-public-apis/commit/b4c0af7a367f0552b782ff83500bfb000c2a4e6f) |  | watch |  |
| 2026-10-06 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24918 (#24919)](https://github.com/jentic/jentic-public-apis/commit/83966539f10d2bc8aa3640a3984c2177775d333f) | spec | watch | Conformance / schema validation |
| 2026-10-06 | usearazzo/arazzo-toolkit | issue | [validator: validate OpenAPI source descriptions, not just the Arazzo document](https://github.com/usearazzo/arazzo-toolkit/issues/212) | spec | watch | Conformance / schema validation |
| 2026-10-06 | OAI/Arazzo-Specification | pr | [chore(README): add UseArazzo parser, resolver, and validator to Tooling](https://github.com/OAI/Arazzo-Specification/pull/592) | spec | watch | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.58.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.58.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/respect-core@2.58.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.58.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/recheck@2.58.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.58.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.58.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.58.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.23](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.23) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | Redocly/redocly-cli | release | [@redocly/cli@2.58.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.58.2) | spec | actionable | Conformance / schema validation |
| 2026-10-06 | usearazzo/website | commit | [feat(blog): add the @usearazzo/validator release post](https://github.com/usearazzo/website/commit/35b38363e22c4aa0b52ba86a32f260e97a8c969c) | spec | watch | Conformance / schema validation |
| 2026-10-06 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-06 (#225)](https://github.com/OAI/landscape/commit/facdea5144386c2067798f3f378b11f162f3a646) |  | watch |  |
| 2026-10-06 | OAI/build-infra | pr | [Automate downstream release PRs](https://github.com/OAI/build-infra/pull/66) | spec | watch | Conformance / schema validation |
| 2026-10-06 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.4.0 to 37.4.1](https://github.com/OAI/Arazzo-Specification/pull/591) | a2a, depbump | watch | Roadmap A2A step type |
| 2026-10-06 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) | depbump | watch | Dependency maintenance |
| 2026-10-06 | speakeasy-api/openapi | release | [v1.25.5](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.5) | cli, spec | actionable | P2-1 CLI binary |
| 2026-10-06 | usearazzo/website.feed | article | [@usearazzo/validator Is on npm. Here Is What It Took](https://usearazzo.com/blog/arazzo-validator-on-npm/) | spec | watch | Conformance / schema validation |
| 2026-10-05 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @microsoft/api-extractor from 7.59.2 to 7.59.3](https://github.com/usearazzo/arazzo-toolkit/pull/210) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-10-05 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump lint-staged from 17.5.1 to 17.6.0](https://github.com/usearazzo/arazzo-toolkit/pull/211) | depbump | actionable | Dependency maintenance |
| 2026-10-05 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @types/node from 26.6.3 to 26.6.4](https://github.com/usearazzo/arazzo-toolkit/pull/209) | depbump | actionable | Dependency maintenance |
| 2026-10-05 | speakeasy-api/openapi | release | [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) | cli, schema, depbump | actionable | P1-7 JSON Schema layer |
| 2026-10-05 | OAI/build-infra | pr | [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) | a2a, depbump | watch | Roadmap A2A step type |
| 2026-10-05 | OAI/build-infra | pr | [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) | actor, a2a, depbump | watch | Roadmap A2A step type |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.58.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.58.1) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/respect-core@2.58.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.58.1) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/recheck@2.58.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.58.1) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.58.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.58.1) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.22](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.22) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/cli@2.58.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.58.1) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24915)](https://github.com/jentic/jentic-public-apis/commit/d6f6f530f41678c177d7eb1c7573aa6ee3d92bd5) |  | watch |  |
| 2026-10-05 | Redocly/redocly-cli | release | [@redocly/recheck@2.58.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.58.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-10-05 | Specmatic/specmatic | release | [2.55.3](https://github.com/specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24909 (#24912)](https://github.com/jentic/jentic-public-apis/commit/d364ebf0e118d9395c8c3c0ec0d7ae83e7a6ca18) | spec | watch | Conformance / schema validation |
| 2026-10-05 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24904 (#24905)](https://github.com/jentic/jentic-public-apis/commit/5b51f86aecdaf28d78321b247e44ce764a48eb59) | spec | watch | Conformance / schema validation |
| 2026-10-05 | spec.arazzo.html | spec_html_checksum | [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) | spec | watch | Conformance / schema validation |
| 2026-10-05 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | spec | watch | Conformance / schema validation |
| 2026-10-05 | spec.arazzo.schema.1.0 | schema_checksum | [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) | spec | watch | Conformance / schema validation |
| 2026-10-05 | OAI/spec.openapis.org | pr | [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/pull/144) | depbump | actionable | Dependency maintenance |
| 2026-10-05 | OAI/spec.openapis.org | commit | [Merge pull request #144 from OAI/dependabot/bundler/jekyll-include-cache-0.3.1](https://github.com/OAI/spec.openapis.org/commit/ea40577169de316c22b24219136ada123ff5af49) | depbump | watch | Dependency maintenance |
| 2026-10-05 | OAI/spec.openapis.org | pr | [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/pull/145) | a2a, depbump | actionable | Roadmap A2A step type |
| 2026-10-05 | OAI/spec.openapis.org | commit | [Merge pull request #145 from OAI/dependabot/bundler/jekyll-remote-theme-0.6.2](https://github.com/OAI/spec.openapis.org/commit/1112ad677f1712a5bafedbd71449795fa68997d0) | depbump | watch | Dependency maintenance |
| 2026-10-05 | OAI/OpenAPI-Specification | pr | [Fix Mastodon AI policy link](https://github.com/OAI/OpenAPI-Specification/pull/5561) | spec | actionable | Conformance / schema validation |
| 2026-10-05 | OAI/spec.openapis.org | commit | [build(deps-dev): bump jekyll-remote-theme from 0.6.0 to 0.6.2](https://github.com/OAI/spec.openapis.org/commit/88146ad7e97d3019c71ae1bed1592d50b1765391) | depbump | watch | Dependency maintenance |
| 2026-10-05 | OAI/spec.openapis.org | commit | [build(deps-dev): bump jekyll-include-cache from 0.3.0 to 0.3.1](https://github.com/OAI/spec.openapis.org/commit/f0f029b9c633895c13c987d6e788adad7256afb1) | depbump | watch | Dependency maintenance |
| 2026-10-05 | OAI/landscape | commit | [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-10-05 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24911)](https://github.com/jentic/jentic-public-apis/commit/be765838530c49b617aabf7c016a7c7bc3bc3fc1) |  | watch |  |
| 2026-10-05 | OAI/landscape | commit | [Update Landscape from LFX 2026-10-05 (#223)](https://github.com/OAI/landscape/commit/a2c10166ad013b5ce11d578c2f25a21ca73bfe37) |  | watch |  |
| 2026-10-04 | usearazzo/arazzo-toolkit | pr | [docs: mark roadmap Phase 4 as shipped](https://github.com/usearazzo/arazzo-toolkit/pull/208) | breaking, schema, spec | breaking | P1-7 JSON Schema layer |
| 2026-10-04 | npm.@usearazzo/validator | release | [@usearazzo/validator@1.0.1-alpha.6](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.6) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-10-04 | npm.@usearazzo/resolver | release | [@usearazzo/resolver@1.0.1-alpha.6](https://www.npmjs.com/package/@usearazzo/resolver/v/1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-04 | npm.@usearazzo/parser | release | [@usearazzo/parser@1.0.1-alpha.6](https://www.npmjs.com/package/@usearazzo/parser/v/1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-04 | usearazzo/arazzo-toolkit | release | [v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | schema, spec | actionable | P1-7 JSON Schema layer |
| 2026-10-04 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5565) |  | watch |  |
| 2026-10-04 | OAI/OpenAPI-Specification | pr | [Update weekly meeting agenda to include spec/schema publishing](https://github.com/OAI/OpenAPI-Specification/pull/5557) |  | actionable |  |
| 2026-10-04 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/64) | depbump | actionable | Dependency maintenance |
| 2026-10-04 | OAI/build-infra | commit | [Merge pull request #64 from OAI/dependabot/npm_and_yarn/vitest-92ac8026ca](https://github.com/OAI/build-infra/commit/c771ab929b8445becf4fd9349ab365823a553206) | depbump | watch | Dependency maintenance |
| 2026-10-04 | OAI/build-infra | pr | [Share downstream md2html fixture tests](https://github.com/OAI/build-infra/pull/65) | spec | actionable | Conformance / schema validation |
| 2026-10-04 | OAI/build-infra | commit | [Merge pull request #65 from handrews/feature/downstream-md2html-fixtures](https://github.com/OAI/build-infra/commit/ef14c602a8fc72bd1f0e43ec91c7453e0936a963) |  | watch |  |
| 2026-10-04 | usearazzo/website | commit | [docs(validator): JSON Schema validation covers Arazzo 1.1.0](https://github.com/usearazzo/website/commit/3d96e308603217dc3a54fd0475fed97c3977c20b) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-10-04 | usearazzo/arazzo-toolkit | pr | [docs(validator): link the README to the product page](https://github.com/usearazzo/arazzo-toolkit/pull/207) | breaking, spec | breaking | Conformance / schema validation |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
