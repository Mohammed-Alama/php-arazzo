# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-18T11:11:54+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 848 (showing 200 newest)
- **Severity:** breaking **40** · actionable **469** · watch **339**
- **Top relevance:** `Conformance / schema validation` (334) · `uncategorized` (155) · `Dependency maintenance` (100) · `P2-1 CLI binary` (77) · `P1-7 JSON Schema layer` (33)
- **Top sources:** `strefethen/arazzo-cli` (54) · `OAI/Arazzo-Specification` (49) · `OAI/build-infra` (43) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (11)

- `2026-09-18` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-16` [chore(harness): pin low effort level on automated code-review calls](https://github.com/usearazzo/arazzo-toolkit/pull/160) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-11` [Tweak OpenAPI Spec rendering for new build](https://github.com/OAI/build-infra/pull/45) — `OAI/build-infra` · `pr` · _breaking,spec_
- `2026-09-10` [feat(homepage): problem-first hero, plus Concept Catalog and Audience Notes](https://github.com/usearazzo/website/commit/6431c19c5195d3814bcea3475fa65f8f45330117) — `usearazzo/website` · `commit` · _breaking,spec_
- `2026-09-08` [fix(parser): resolve relative file paths against working directory](https://github.com/usearazzo/arazzo-toolkit/pull/148) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-08-11` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- … and 3 more in this group (see All events table)

### Dependency maintenance (8)

- `2026-09-11` [Bump build-infra to 1.0.2](https://github.com/OAI/OpenAPI-Specification/pull/5552) — `OAI/OpenAPI-Specification` · `pr` · _breaking,depbump_
- `2026-09-09` [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/40) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-09-07` [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/569) — `OAI/Arazzo-Specification` · `pr` · _breaking,depbump_
- `2026-08-03` [build(deps-dev): bump jekyll-include-cache from 0.2.1 to 0.2.2](https://github.com/OAI/spec.openapis.org/pull/128) — `OAI/spec.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 6](https://github.com/jentic/arazzo-engine/pull/136) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_

### P1-7 JSON Schema layer (5)

- `2026-09-08` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-10-09` [OAS 3.1.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-06-18` [OAS 3.1.0-rc0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,security,spec_

### API security (OAI sig-security) (3)

- `2026-08-21` [\[Announcement\] OAuth2.1 and OAuth3 drafts](https://github.com/OAI/sig-security/issues/30) — `OAI/sig-security` · `issue` · _breaking,security,spec_
- `2026-08-21` [OAuth refreshUrl property](https://github.com/OAI/sig-security/issues/21) — `OAI/sig-security` · `issue` · _breaking,security_
- `2020-02-21` [OAS 3.0.3 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.3) — `OAI/OpenAPI-Specification` · `release` · _breaking,security,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,runner,spec_
- `2024-09-25` [Arazzo 1.0.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,schema,runner,spec_

### P0-6 source routing (wsdl type) (2)

- `2026-09-16` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_
- `2026-09-06` [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,breaking,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### P2-1 CLI binary (2)

- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-03` [build(deps): bump markdown-it from 14.3.0 to 15.0.0](https://github.com/OAI/Overlay-Specification/pull/375) — `OAI/Overlay-Specification` · `pr` · _cli,breaking,depbump_

### Potential breaking change (2.0) (2)

- `2026-09-10` [If there's a 2.0 spec, add it (doesn't match 3-digit semVer)](https://github.com/OAI/build-infra/commit/45b5076e6c94835f02c3adb4432cfc9cb87dc7b8) — `OAI/build-infra` · `commit` · _breaking_
- `2026-09-06` [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking_

### Arazzo runner / step execution (1)

- `2026-09-09` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/42) — `OAI/build-infra` · `pr` · _actor,breaking,runner,depbump_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (237)

- `2026-09-18` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-18` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-18` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-18` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-18` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-18` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-18` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-18` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 229 more in this group (see All events table)

### uncategorized (54)

- `2026-09-11` [Use the version placeholder in the commit history link](https://github.com/OAI/OpenAPI-Specification/pull/5551) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-11` [docs: adds precision around which branch to start from for updates](https://github.com/OAI/build-infra/pull/44) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-10` [fix json punctuation](https://github.com/OAI/spec.openapis.org/pull/130) — `OAI/spec.openapis.org` · `pr` · _no tags_
- `2026-09-10` [Update examples to use 3.2.1](https://github.com/OAI/OpenAPI-Specification/pull/5548) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-10` [refresh 1.2-dev from dev](https://github.com/OAI/Overlay-Specification/pull/396) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-10` [dev refresh from main](https://github.com/OAI/Overlay-Specification/pull/395) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-10` [v3.3-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5547) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-10` [v3.2-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5546) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- … and 46 more in this group (see All events table)

### P2-1 CLI binary (50)

- `2026-09-18` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-18` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 42 more in this group (see All events table)

### Dependency maintenance (41)

- `2026-09-18` [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/pull/136) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-09-17` [Bump content-type from 3.0.0 to 3.1.0](https://github.com/OAI/build-infra/pull/52) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-17` [Bump yaml from 2.9.0 to 2.9.1](https://github.com/OAI/build-infra/pull/51) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-17` [Bump markdown-it from 15.0.1 to 15.0.2 in the markdown group](https://github.com/OAI/build-infra/pull/49) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-17` [Bump vite from 8.2.2 to 8.3.0 in the vitest group](https://github.com/OAI/build-infra/pull/48) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-17` [build(deps): bump bigdecimal from 4.1.2 to 4.1.3](https://github.com/OAI/spec.openapis.org/pull/134) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-09-17` [build(deps): bump ruby/setup-ruby from 1.321.0 to 1.322.0](https://github.com/OAI/spec.openapis.org/pull/135) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-09-16` [chore(deps-dev): bump lint-staged from 17.5.0 to 17.5.1](https://github.com/usearazzo/arazzo-toolkit/pull/161) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 33 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (18)

- `2026-09-17` [chore(deps-dev): bump @babel/core from 8.0.1 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/164) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-17` [chore(deps-dev): bump @babel/preset-env from 8.0.2 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/165) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-17` [chore(deps-dev): bump webpack from 5.110.3 to 5.111.0](https://github.com/usearazzo/arazzo-toolkit/pull/166) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps-dev): bump @microsoft/api-extractor from 7.59.0 to 7.59.1](https://github.com/usearazzo/arazzo-toolkit/pull/153) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump @babel/runtime-corejs3 from 8.0.0 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/156) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump respec from 37.3.6 to 37.4.0](https://github.com/OAI/Arazzo-Specification/pull/575) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-08` [chore(deps-dev): bump lint-staged from 17.4.1 to 17.5.0](https://github.com/usearazzo/arazzo-toolkit/pull/149) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- … and 10 more in this group (see All events table)

### P1-7 JSON Schema layer (17)

- `2026-09-17` [Bump @hyperjump/json-schema-coverage from 1.2.1 to 1.2.2 in the hyperjump group](https://github.com/OAI/build-infra/pull/47) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-09-16` [feat(resolver): add resolve and bundle and publish the package](https://github.com/usearazzo/arazzo-toolkit/pull/157) — `usearazzo/arazzo-toolkit` · `pr` · _actor,schema,runner,spec_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,depbump_
- `2026-08-06` [v1.24.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.24.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-08-04` [v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) — `speclynx/apidom` · `release` · _schema,spec_
- `2026-07-20` [feat: adds examples extension](https://github.com/OAI/spec.openapis.org/pull/124) — `OAI/spec.openapis.org` · `pr` · _schema_
- `2026-06-19` [v1.23.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-06-01` [v1.23.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- … and 9 more in this group (see All events table)

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

### P1-6 payload XPath / P0-5 XPath criteria (12)

- `2026-09-06` [feat: add phpmd, pdepend, phpdoc to quality pipeline](https://github.com/Mohammed-Alama/php-arazzo/pull/53) — `Mohammed-Alama/php-arazzo` · `pr` · _xml_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,security,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,depbump_
- `2026-04-22` [Fix/errors with expression evaluation binary content and branching](https://github.com/jentic/arazzo-engine/pull/142) — `jentic/arazzo-engine` · `pr` · _xml,loop,spec_
- `2026-04-06` [v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `release` · _xml,cli,loop,runner,spec_
- … and 4 more in this group (see All events table)

### P2-2 MCP server exposure (5)

- `2026-09-03` [2.54.0](https://github.com/specmatic/specmatic/releases/tag/2.54.0) — `Specmatic/specmatic` · `release` · _mcp,cli,security,depbump_
- `2026-07-17` [2.50.1](https://github.com/specmatic/specmatic/releases/tag/2.50.1) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-06-18` [2.48.0](https://github.com/specmatic/specmatic/releases/tag/2.48.0) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-06-11` [2.46.5](https://github.com/specmatic/specmatic/releases/tag/2.46.5) — `Specmatic/specmatic` · `release` · _mcp,depbump_
- `2026-03-29` [v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `release` · _mcp,cli,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-09-07` [feat(runner): consume expression + document engines via public faces](https://github.com/Mohammed-Alama/php-arazzo/pull/69) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,actor,spec_
- `2026-09-07` [feat(document): validator consumes expression via its public face](https://github.com/Mohammed-Alama/php-arazzo/pull/68) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath_
- `2026-09-06` [richen public face covering cli/laravel needs — Closes #58](https://github.com/Mohammed-Alama/php-arazzo/pull/67) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,xpath,cli,actor_
- `2026-03-13` [v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,loop,spec_

### P0-6 source routing (wsdl type) (4)

- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,depbump_
- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

### API security (OAI sig-security) (3)

- `2024-09-21` [v6.13.1](https://github.com/stoplightio/spectral/releases/tag/v6.13.1) — `stoplightio/spectral` · `release` · _security,depbump_
- `2018-10-08` [OAS 3.0.2 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.2) — `OAI/OpenAPI-Specification` · `release` · _security,spec_
- `2017-04-28` [OAS 3.0.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _security,spec_

### Issue #410 loops vs goto (3)

- `2026-09-17` [@redocly/respect-core@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-17` [@redocly/cli@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_

### Roadmap A2A step type (3)

- `2026-09-16` [chore(deps-dev): bump mocha from 12.0.0 to 12.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/163) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (101)

- `2026-09-17` [Update Landscape from LFX 2026-09-17 (#206)](https://github.com/OAI/landscape/commit/b357160c953f85ad3eb08a2605cb2b031664aa79) — `OAI/landscape` · `commit` · _no tags_
- `2026-09-17` [Rebuild apis.json, scores.json, and API browsing indexes (#24158)](https://github.com/jentic/jentic-public-apis/commit/7a9a4f669861778137a849965b2c181d10479499) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-17` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-17` [Rebuild apis.json, scores.json, and API browsing indexes (#24150)](https://github.com/jentic/jentic-public-apis/commit/70439f726894baa3e953b9ef1a9df810d59f63a5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-17` [Rebuild apis.json, scores.json, and API browsing indexes (#24115)](https://github.com/jentic/jentic-public-apis/commit/3ca7259f27cf7f2e88a075f97105ddddb9e62bb5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-17` [Remove duplicate spec dirs for 4 mis-detected multi-API vendors (#24053)](https://github.com/jentic/jentic-public-apis/commit/eccd24fd61ae1bace4d3104044b51c35aaf08538) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-17` [Rebuild apis.json, scores.json, and API browsing indexes (#24073)](https://github.com/jentic/jentic-public-apis/commit/64db7c0bb8a18e0451c26b2797277e268fc39004) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-17` [Rebuild apis.json, scores.json, and API browsing indexes (#24051)](https://github.com/jentic/jentic-public-apis/commit/f49ce87d097bd90aa9686d98affab90af5ab647d) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 93 more in this group (see All events table)

### Conformance / schema validation (86)

- `2026-09-18` [openapi.tools checksum 951888928491](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-18` [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) — `spec.arazzo.html` · `spec_html_checksum` · _spec_
- `2026-09-18` [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) — `spec.arazzo.schema.1.1` · `schema_checksum` · _spec_
- `2026-09-18` [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) — `spec.arazzo.schema.1.0` · `schema_checksum` · _spec_
- `2026-09-17` [Merge pull request #64 from OAI/openapi-v3.2-dev-schema-iterations](https://github.com/OAI/spec.openapis.org/commit/c34d5267ae9faba7fa0838370d7c15280fd4f407) — `OAI/spec.openapis.org` · `commit` · _spec_
- `2026-09-17` [Do we need to test the `dev` branch in OpenAPI-Specification?](https://github.com/OAI/build-infra/issues/54) — `OAI/build-infra` · `issue` · _spec_
- `2026-09-17` [Merge pull request #53 from lornajane/chore/add-overlays-to-qualification](https://github.com/OAI/build-infra/commit/c3a3fadfcf51c21ec49bbecc4fcade702ffbcb03) — `OAI/build-infra` · `commit` · _spec_
- `2026-09-17` [harness: pin explicit /code-review effort level in automated skill invocations](https://github.com/usearazzo/arazzo-toolkit/issues/159) — `usearazzo/arazzo-toolkit` · `issue` · _spec_
- … and 78 more in this group (see All events table)

### Dependency maintenance (51)

- `2026-09-18` [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/commit/d60a34e5f3564d40bd5e65f88c16789a91c9d413) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-09-17` [Merge pull request #52 from OAI/dependabot/npm_and_yarn/content-type-3.1.0](https://github.com/OAI/build-infra/commit/f73f4de9c8a47a0dc24e4528f9e4e13481c3e230) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Merge pull request #51 from OAI/dependabot/npm_and_yarn/yaml-2.9.1](https://github.com/OAI/build-infra/commit/f9e7c4faa19a42ebb767a65941114b2f65ff6ebd) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Bump content-type from 3.0.0 to 3.1.0](https://github.com/OAI/build-infra/commit/06a825c4dcb5fcb69c708e8aa02dc3f7ac214c02) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Bump yaml from 2.9.0 to 2.9.1](https://github.com/OAI/build-infra/commit/728c4dbf0dd074bee0c4c5403b35b4cc9d457687) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Merge pull request #49 from OAI/dependabot/npm_and_yarn/markdown-f1d87fe65f](https://github.com/OAI/build-infra/commit/53fb6619987c10ce8bd0d5ef84c9fbf5201d6427) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Merge pull request #48 from OAI/dependabot/npm_and_yarn/vitest-977da20ab4](https://github.com/OAI/build-infra/commit/bd950fae6c1435e0e47aa27ed47544c3ae3e71e4) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-17` [Merge pull request #134 from OAI/dependabot/bundler/bigdecimal-4.1.3](https://github.com/OAI/spec.openapis.org/commit/1b13fb24ba5a28f68558109287e61bbd6a3443fd) — `OAI/spec.openapis.org` · `commit` · _depbump_
- … and 43 more in this group (see All events table)

### P2-1 CLI binary (25)

- `2026-09-16` [fix: update rustls for RUSTSEC-2026-0285](https://github.com/strefethen/arazzo-cli/commit/679af12f6ee088b5dace69b4693a5802f7ff5aeb) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-16` [chore: pin Rust 1.98.1 for builds and releases](https://github.com/strefethen/arazzo-cli/commit/43cb26abd42f6b8753006412076b83e2b0b177b5) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [chore(release): prepare 0.7.0 with the RFC 9535 JSONPath engine](https://github.com/strefethen/arazzo-cli/commit/9a1dd3fe5add36f541b17385a361afb21ff0e825) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(plans): complete the RFC 9535 JSONPath migration plan](https://github.com/strefethen/arazzo-cli/commit/26a3dc0137b8eabcef6ecd694a01be5785271af7) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(agents): drop the empty approved-extensions allowlist paragraph](https://github.com/strefethen/arazzo-cli/commit/48b0dff75b2c6e7bd8ba611d58a475ead732d740) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(jsonpath): prove and document the RFC 9535 cutover](https://github.com/strefethen/arazzo-cli/commit/f467c32c94f63561be34cf10b72d8ffffb078486) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-09-14` [test(conformance): prove typed JSONPath rejects GJSON syntax](https://github.com/strefethen/arazzo-cli/commit/9ba8901b5a6150c7e736e5f3e802fce54660187c) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-09-13` [feat(jsonpath): cut selectors and replacements over to RFC 9535](https://github.com/strefethen/arazzo-cli/commit/a0f7170651a75f31391d824cb18a5ae08607db23) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- … and 17 more in this group (see All events table)

### API security (OAI sig-security) (18)

- `2026-09-11` [v3.2: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5534) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-09-11` [v3.3: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5533) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [Auth URL Variables](https://github.com/OAI/sig-security/issues/33) — `OAI/sig-security` · `issue` · _security_
- … and 10 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-09-17` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-08` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor_
- … and 5 more in this group (see All events table)

### P1-7 JSON Schema layer (11)

- `2026-09-17` [Merge pull request #47 from OAI/dependabot/npm_and_yarn/hyperjump-ad3e30104b](https://github.com/OAI/build-infra/commit/177ff67ec793e93ea0a9c84121c45384b960da30) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-16` [resolver: bundleArazzo leaves $ref unresolvable when the embedded schema's $id differs from its retrieval URI](https://github.com/usearazzo/arazzo-toolkit/issues/158) — `usearazzo/arazzo-toolkit` · `issue` · _schema,depbump_
- `2026-09-11` [Bump @hyperjump/json-schema-coverage in the hyperjump group](https://github.com/OAI/build-infra/commit/eb5cc236ffda832ddc0e2c18a7c25139dc95df42) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-06` [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,schema,spec_
- `2026-08-31` [Bump jmertic/lfx-landscape-tools from 20260625 to 20260826 in the all group (#193)](https://github.com/OAI/landscape/commit/8c128a7b3f32ff3b50815246017cb9d651ab88bf) — `OAI/landscape` · `commit` · _schema,depbump_
- `2026-08-11` [Revisit: should Overlays declare their target document format? (follow-up to #268)](https://github.com/OAI/Overlay-Specification/issues/367) — `OAI/Overlay-Specification` · `issue` · _schema,spec_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,schema,spec_
- `2026-08-02` [Add Gesso (PHP OpenAPI 3.0/3.1/3.2 contract testing library)](https://github.com/OAI/tools.openapis.org/pull/273) — `OAI/tools.openapis.org` · `pr` · _schema,spec_
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

### P2-2 MCP server exposure (7)

- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website` · `commit` · _mcp,spec_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- `2026-09-12` [fix(runtime): stop JSONPath delimiter runs from panicking criteria](https://github.com/strefethen/arazzo-cli/commit/48b426d56317ca9f2455a55e98e6e2f8dd21fedc) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-09-10` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_

### Arazzo runner / step execution (4)

- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-09-11` [runner: Arazzo 1.1.0 support — tracking issue](https://github.com/usearazzo/arazzo-toolkit/issues/119) — `usearazzo/arazzo-toolkit` · `issue` · _loop,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-13` [fix(runtime): decide jsonpath criteria by nodelist cardinality](https://github.com/strefethen/arazzo-cli/commit/7511e40d0113803274e95a3e3e0115cec3a916ee) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### P1-6 payload XPath / P0-5 XPath criteria (4)

- `2026-09-16` [v3.3: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5540) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-12` [v3.2: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5541) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-07` [ci: add GitHub Actions workflow for documentation validation](https://github.com/OAI/OpenAPI-Specification/pull/5392) — `OAI/OpenAPI-Specification` · `pr` · _xml,schema,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### P0-6 source routing (wsdl type) (3)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the September sweep](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website` · `commit` · _soap,mcp,schema,spec_
- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_
- `2026-09-06` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### Roadmap GraphQL step type (2)

- `2026-09-16` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### Roadmap A2A step type (1)

- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Roadmap gRPC step type (1)

- `2026-09-03` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-18 | openapi.tools | tool_collection | [openapi.tools checksum 951888928491](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-18 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-18 | spec.arazzo.html | spec_html_checksum | [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) | spec | watch | Conformance / schema validation |
| 2026-09-18 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | spec | watch | Conformance / schema validation |
| 2026-09-18 | spec.arazzo.schema.1.0 | schema_checksum | [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) | spec | watch | Conformance / schema validation |
| 2026-09-18 | OAI/spec.openapis.org | pr | [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/pull/136) | depbump | actionable | Dependency maintenance |
| 2026-09-18 | OAI/spec.openapis.org | commit | [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) | a2a, depbump | watch | Roadmap A2A step type |
| 2026-09-18 | OAI/spec.openapis.org | commit | [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/commit/d60a34e5f3564d40bd5e65f88c16789a91c9d413) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-17 (#206)](https://github.com/OAI/landscape/commit/b357160c953f85ad3eb08a2605cb2b031664aa79) |  | watch |  |
| 2026-09-17 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/core from 8.0.1 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/164) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-17 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/preset-env from 8.0.2 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/165) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-17 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump webpack from 5.110.3 to 5.111.0](https://github.com/usearazzo/arazzo-toolkit/pull/166) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-17 | OAI/build-infra | pr | [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-17 | speclynx/apidom | release | [v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-09-17 | OAI/spec.openapis.org | pr | [OpenAPI - publish v3.2-dev schema iterations](https://github.com/OAI/spec.openapis.org/pull/64) | spec | actionable | Conformance / schema validation |
| 2026-09-17 | OAI/spec.openapis.org | commit | [Merge pull request #64 from OAI/openapi-v3.2-dev-schema-iterations](https://github.com/OAI/spec.openapis.org/commit/c34d5267ae9faba7fa0838370d7c15280fd4f407) | spec | watch | Conformance / schema validation |
| 2026-09-17 | OAI/build-infra | pr | [Bump content-type from 3.0.0 to 3.1.0](https://github.com/OAI/build-infra/pull/52) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #52 from OAI/dependabot/npm_and_yarn/content-type-3.1.0](https://github.com/OAI/build-infra/commit/f73f4de9c8a47a0dc24e4528f9e4e13481c3e230) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | pr | [Bump yaml from 2.9.0 to 2.9.1](https://github.com/OAI/build-infra/pull/51) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #51 from OAI/dependabot/npm_and_yarn/yaml-2.9.1](https://github.com/OAI/build-infra/commit/f9e7c4faa19a42ebb767a65941114b2f65ff6ebd) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | issue | [Do we need to test the `dev` branch in OpenAPI-Specification?](https://github.com/OAI/build-infra/issues/54) | spec | watch | Conformance / schema validation |
| 2026-09-17 | OAI/build-infra | commit | [Bump content-type from 3.0.0 to 3.1.0](https://github.com/OAI/build-infra/commit/06a825c4dcb5fcb69c708e8aa02dc3f7ac214c02) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | commit | [Bump yaml from 2.9.0 to 2.9.1](https://github.com/OAI/build-infra/commit/728c4dbf0dd074bee0c4c5403b35b4cc9d457687) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | pr | [Bump markdown-it from 15.0.1 to 15.0.2 in the markdown group](https://github.com/OAI/build-infra/pull/49) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #49 from OAI/dependabot/npm_and_yarn/markdown-f1d87fe65f](https://github.com/OAI/build-infra/commit/53fb6619987c10ce8bd0d5ef84c9fbf5201d6427) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | pr | [Bump vite from 8.2.2 to 8.3.0 in the vitest group](https://github.com/OAI/build-infra/pull/48) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #48 from OAI/dependabot/npm_and_yarn/vitest-977da20ab4](https://github.com/OAI/build-infra/commit/bd950fae6c1435e0e47aa27ed47544c3ae3e71e4) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/build-infra | pr | [Bump @hyperjump/json-schema-coverage from 1.2.1 to 1.2.2 in the hyperjump group](https://github.com/OAI/build-infra/pull/47) | schema, depbump | actionable | P1-7 JSON Schema layer |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #47 from OAI/dependabot/npm_and_yarn/hyperjump-ad3e30104b](https://github.com/OAI/build-infra/commit/177ff67ec793e93ea0a9c84121c45384b960da30) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-09-17 | OAI/build-infra | pr | [Add the main and in-progress Overlay specifications to the list](https://github.com/OAI/build-infra/pull/53) | spec | actionable | Conformance / schema validation |
| 2026-09-17 | OAI/build-infra | commit | [Merge pull request #53 from lornajane/chore/add-overlays-to-qualification](https://github.com/OAI/build-infra/commit/c3a3fadfcf51c21ec49bbecc4fcade702ffbcb03) | spec | watch | Conformance / schema validation |
| 2026-09-17 | usearazzo/arazzo-toolkit | issue | [harness: pin explicit /code-review effort level in automated skill invocations](https://github.com/usearazzo/arazzo-toolkit/issues/159) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24158)](https://github.com/jentic/jentic-public-apis/commit/7a9a4f669861778137a849965b2c181d10479499) |  | watch |  |
| 2026-09-17 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) |  | watch |  |
| 2026-09-17 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) | spec | watch | Conformance / schema validation |
| 2026-09-17 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 03 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5527) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23709 (#23712)](https://github.com/jentic/jentic-public-apis/commit/aa761a2229a8199acb7d099ba46d886d5dd1e208) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24142 (#24145)](https://github.com/jentic/jentic-public-apis/commit/c04d3c501a36f6d30308090ef0a9b20498c54c3a) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24150)](https://github.com/jentic/jentic-public-apis/commit/70439f726894baa3e953b9ef1a9df810d59f63a5) |  | watch |  |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23755 (#23757)](https://github.com/jentic/jentic-public-apis/commit/7f55f3c14940e28c4d2b52603dbd916faad01468) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24138 (#24140)](https://github.com/jentic/jentic-public-apis/commit/6a95eddff5ade57ba14fd462a6f9f277e27e67db) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Remove marketplace.your.rentals API (#24141)](https://github.com/jentic/jentic-public-apis/commit/1a761a4507eca38e777c14d951769250cdccad5f) | spec | watch | Conformance / schema validation |
| 2026-09-17 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 17 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5542) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24132 (#24135)](https://github.com/jentic/jentic-public-apis/commit/f579561722849dd2530b08ea02b823723ca2ba5e) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24128 (#24131)](https://github.com/jentic/jentic-public-apis/commit/bd8d7966e8e6378ef4dbda764f81f814f18f5b36) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24115)](https://github.com/jentic/jentic-public-apis/commit/3ca7259f27cf7f2e88a075f97105ddddb9e62bb5) |  | watch |  |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Remove duplicate spec dirs for 4 mis-detected multi-API vendors (#24053)](https://github.com/jentic/jentic-public-apis/commit/eccd24fd61ae1bace4d3104044b51c35aaf08538) |  | watch |  |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Remove duplicate payrun.io spec directories (#24047)](https://github.com/jentic/jentic-public-apis/commit/1a5b74da540effe759f8c15cf243ecf309f07dc3) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24073)](https://github.com/jentic/jentic-public-apis/commit/64db7c0bb8a18e0451c26b2797277e268fc39004) |  | watch |  |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23943 (#23946)](https://github.com/jentic/jentic-public-apis/commit/78f1c449b64bbf0f4fb151167d2e43ca23817593) | spec | watch | Conformance / schema validation |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24051)](https://github.com/jentic/jentic-public-apis/commit/f49ce87d097bd90aa9686d98affab90af5ab647d) |  | watch |  |
| 2026-09-17 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24044 (#24046)](https://github.com/jentic/jentic-public-apis/commit/dd5e6f8e808a11ae05687484218b0a93627ce0c4) | spec | watch | Conformance / schema validation |
| 2026-09-17 | OAI/spec.openapis.org | pr | [build(deps): bump bigdecimal from 4.1.2 to 4.1.3](https://github.com/OAI/spec.openapis.org/pull/134) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/spec.openapis.org | commit | [Merge pull request #134 from OAI/dependabot/bundler/bigdecimal-4.1.3](https://github.com/OAI/spec.openapis.org/commit/1b13fb24ba5a28f68558109287e61bbd6a3443fd) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/spec.openapis.org | pr | [build(deps): bump ruby/setup-ruby from 1.321.0 to 1.322.0](https://github.com/OAI/spec.openapis.org/pull/135) | depbump | actionable | Dependency maintenance |
| 2026-09-17 | OAI/spec.openapis.org | commit | [Merge pull request #135 from OAI/dependabot/github_actions/ruby/setup-ruby-1.322.0](https://github.com/OAI/spec.openapis.org/commit/ff7d4c5675a474f397c27863ddf46b903bc2566a) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/spec.openapis.org | commit | [build(deps): bump ruby/setup-ruby from 1.321.0 to 1.322.0](https://github.com/OAI/spec.openapis.org/commit/3bb7743ee5ec49330497851ff451ec1ef3c87a16) | depbump | watch | Dependency maintenance |
| 2026-09-17 | OAI/spec.openapis.org | commit | [build(deps): bump bigdecimal from 4.1.2 to 4.1.3](https://github.com/OAI/spec.openapis.org/commit/1e88aaae437013780f4417e0f0ce23f04040f5f4) | depbump | watch | Dependency maintenance |
| 2026-09-17 | Redocly/redocly-cli | release | [@redocly/respect-core@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.53.3) | loop, spec | actionable | Issue #410 loops vs goto |
| 2026-09-17 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.53.3) | spec | actionable | Conformance / schema validation |
| 2026-09-17 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.12](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.12) | spec | actionable | Conformance / schema validation |
| 2026-09-17 | Redocly/redocly-cli | release | [@redocly/cli@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.53.3) | loop, spec | actionable | Issue #410 loops vs goto |
| 2026-09-17 | usearazzo/website | commit | [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) | mcp, spec | watch | P2-2 MCP server exposure |
| 2026-09-17 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) | mcp, spec | watch | P2-2 MCP server exposure |
| 2026-09-17 | usearazzo/website | commit | [feat(ecosystem): add tools, articles, videos, and an example from the September sweep](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) | soap, mcp, schema, spec | watch | P0-6 source routing (wsdl type) |
| 2026-09-17 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) | soap, mcp, schema, spec | watch | P0-6 source routing (wsdl type) |
| 2026-09-17 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24050)](https://github.com/jentic/jentic-public-apis/commit/637446476a4fae9563923adf136226c4d0020f86) |  | watch |  |
| 2026-09-16 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-16 (#205)](https://github.com/OAI/landscape/commit/3e9dd43b97dd138ace31c1f6f3e888d38372a511) |  | watch |  |
| 2026-09-16 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/cli from 8.0.4 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/162) | cli, actor, depbump | actionable | P2-1 CLI binary |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
