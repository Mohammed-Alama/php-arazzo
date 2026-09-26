# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-26T11:22:39+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 853 (showing 200 newest)
- **Severity:** breaking **39** · actionable **472** · watch **342**
- **Top relevance:** `Conformance / schema validation` (350) · `uncategorized` (148) · `Dependency maintenance` (104) · `P2-1 CLI binary` (77) · `P1-7 JSON Schema layer` (32)
- **Top sources:** `strefethen/arazzo-cli` (54) · `OAI/Arazzo-Specification` (51) · `OAI/build-infra` (41) · `OAI/OpenAPI-Specification` (41) · `speclynx/apidom` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (9)

- `2026-09-26` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-09-24` [fix(docs): add documentation section to parser and resolver READMEs](https://github.com/usearazzo/arazzo-toolkit/pull/184) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-11` [Tweak OpenAPI Spec rendering for new build](https://github.com/OAI/build-infra/pull/45) — `OAI/build-infra` · `pr` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- … and 1 more in this group (see All events table)

### Dependency maintenance (9)

- `2026-09-25` [chore(deps-dev): bump typescript-eslint from 8.70.0 to 8.70.1](https://github.com/usearazzo/arazzo-toolkit/pull/189) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-09-19` [chore(deps): bump @speclynx/apidom-* packages to 5.2.6](https://github.com/usearazzo/arazzo-toolkit/pull/171) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-09-11` [Bump build-infra to 1.0.2](https://github.com/OAI/OpenAPI-Specification/pull/5552) — `OAI/OpenAPI-Specification` · `pr` · _breaking,depbump_
- `2026-09-09` [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/40) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-08-03` [build(deps-dev): bump jekyll-include-cache from 0.2.1 to 0.2.2](https://github.com/OAI/spec.openapis.org/pull/128) — `OAI/spec.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- … and 1 more in this group (see All events table)

### P1-7 JSON Schema layer (5)

- `2026-09-20` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-10-09` [OAS 3.1.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-06-18` [OAS 3.1.0-rc0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,security,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-08-25` [v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `release` · _xml,xpath,cli,breaking,spec_
- `2026-05-18` [Arazzo 1.1.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,actor,runner,spec_
- `2024-09-25` [Arazzo 1.0.0 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) — `OAI/Arazzo-Specification` · `release` · _xml,xpath,schema,runner,spec_

### P2-1 CLI binary (3)

- `2026-09-22` [chore(deps-dev): bump @commitlint/cli from 21.2.2 to 21.2.3](https://github.com/usearazzo/arazzo-toolkit/pull/179) — `usearazzo/arazzo-toolkit` · `pr` · _cli,actor,breaking,depbump_
- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### API security (OAI sig-security) (2)

- `2026-08-21` [\[Announcement\] OAuth2.1 and OAuth3 drafts](https://github.com/OAI/sig-security/issues/30) — `OAI/sig-security` · `issue` · _breaking,security,spec_
- `2020-02-21` [OAS 3.0.3 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.3) — `OAI/OpenAPI-Specification` · `release` · _breaking,security,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### Potential breaking change (2.0) (2)

- `2026-09-10` [If there's a 2.0 spec, add it (doesn't match 3-digit semVer)](https://github.com/OAI/build-infra/commit/45b5076e6c94835f02c3adb4432cfc9cb87dc7b8) — `OAI/build-infra` · `commit` · _breaking_
- `2026-09-06` [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking_

### Arazzo runner / step execution (1)

- `2026-09-09` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/42) — `OAI/build-infra` · `pr` · _actor,breaking,runner,depbump_

### Issue #410 kind discriminator / human-in-loop (1)

- `2026-09-23` [chore(deps-dev): bump @commitlint/config-conventional from 21.2.2 to 21.2.3](https://github.com/usearazzo/arazzo-toolkit/pull/182) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,depbump_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-16` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (241)

- `2026-09-26` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-26` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-26` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-26` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-26` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-26` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-26` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-26` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 233 more in this group (see All events table)

### uncategorized (58)

- `2026-09-26` [Phase c evaluator plugins](https://github.com/Mohammed-Alama/php-arazzo/pull/73) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-09-24` [Don't delete files we need on sync.](https://github.com/OAI/OpenAPI-Specification/pull/5555) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-24` [v3.3-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5560) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-24` [v3.2-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5559) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-24` [v3.1-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5558) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-24` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-22` [Reinstate original participate link ordering](https://github.com/OAI/Overlay-Specification/pull/403) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-22` [1.2-dev refresh from dev](https://github.com/OAI/Overlay-Specification/pull/400) — `OAI/Overlay-Specification` · `pr` · _no tags_
- … and 50 more in this group (see All events table)

### P2-1 CLI binary (48)

- `2026-09-26` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-26` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 40 more in this group (see All events table)

### Dependency maintenance (43)

- `2026-09-25` [chore(deps): bump yargs from 18.1.0 to 18.2.0](https://github.com/OAI/Arazzo-Specification/pull/587) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-09-25` [chore(deps-dev): bump markdownlint-cli2 from 0.23.2 to 0.23.3](https://github.com/OAI/Arazzo-Specification/pull/586) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-09-24` [chore(deps-dev): bump swagger-client from 3.38.1 to 3.38.2](https://github.com/usearazzo/arazzo-toolkit/pull/186) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-24` [chore(deps-dev): bump @babel/core from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/185) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-24` [build(deps): bump ruby/setup-ruby from 1.324.0 to 1.325.0](https://github.com/OAI/spec.openapis.org/pull/139) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-09-24` [chore(deps): bump github/codeql-action from 4.38.0 to 4.38.1](https://github.com/usearazzo/arazzo-toolkit/pull/175) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-23` [chore(deps-dev): bump vitest from 5.0.0 to 5.0.1 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/583) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-09-23` [chore(deps): bump type-fest from 5.9.0 to 5.10.0](https://github.com/usearazzo/arazzo-toolkit/pull/180) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 35 more in this group (see All events table)

### P1-7 JSON Schema layer (17)

- `2026-09-25` [v1.25.3](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.3) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-09-21` [v1.25.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-09-17` [Bump @hyperjump/json-schema-coverage from 1.2.1 to 1.2.2 in the hyperjump group](https://github.com/OAI/build-infra/pull/47) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,depbump_
- `2026-08-06` [v1.24.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.24.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-08-04` [v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) — `speclynx/apidom` · `release` · _schema,spec_
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

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-09-25` [chore(deps-dev): bump @microsoft/api-extractor from 7.59.1 to 7.59.2](https://github.com/usearazzo/arazzo-toolkit/pull/188) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-23` [chore(deps-dev): bump webpack from 5.111.0 to 5.111.1](https://github.com/usearazzo/arazzo-toolkit/pull/183) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump respec from 37.3.6 to 37.4.0](https://github.com/OAI/Arazzo-Specification/pull/575) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- … and 5 more in this group (see All events table)

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

### Issue #410 loops vs goto (3)

- `2026-09-17` [@redocly/respect-core@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-17` [@redocly/cli@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_

### P0-6 source routing (wsdl type) (3)

- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

### Roadmap A2A step type (3)

- `2026-09-22` [chore(deps-dev): bump prettier from 3.9.6 to 3.9.8](https://github.com/usearazzo/arazzo-toolkit/pull/177) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_


## Watch — context (commits/issues/checksums)

### Conformance / schema validation (100)

- `2026-09-26` [openapi.tools checksum 986a885fa349](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23156 (#23157)](https://github.com/jentic/jentic-public-apis/commit/f145d06f3afb628e8d4d8efced938de7d34a8366) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23168 (#23169)](https://github.com/jentic/jentic-public-apis/commit/c12cc96e7efa762391c179c87bd7dcba011267ee) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23174 (#23175)](https://github.com/jentic/jentic-public-apis/commit/56873776998dbe5794467f903be3c2ae9276129d) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23186 (#23187)](https://github.com/jentic/jentic-public-apis/commit/1098a42d2728634592b6812c83d5ddb978fc874f) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23195 (#23196)](https://github.com/jentic/jentic-public-apis/commit/4e3d60c1ab17c7284c8250ddc9a7e61ff4bfe2d1) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23201 (#23202)](https://github.com/jentic/jentic-public-apis/commit/846acdf192f61bdaa362a7b0bede75661bea1140) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-26` [feat: Import OpenAPI spec from Issue #23205 (#23206)](https://github.com/jentic/jentic-public-apis/commit/640aca3b6f6d13d2f59e5ff6aebfe5d1af29500a) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 92 more in this group (see All events table)

### uncategorized (90)

- `2026-09-26` [feat(ecosystem): add The Architectural Limits of Agentic iPaaS to articles](https://github.com/usearazzo/website/commit/db193cb7b0397ec14b20ef0ebfb34d56a30bc70d) — `usearazzo/website` · `commit` · _no tags_
- `2026-09-26` [feat(ecosystem): add The Architectural Limits of Agentic iPaaS to art…](https://github.com/usearazzo/website/commit/db193cb7b0397ec14b20ef0ebfb34d56a30bc70d) — `usearazzo/website.ecosystem.atom` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24780)](https://github.com/jentic/jentic-public-apis/commit/d16c4b9342fe73ad4f6fb34d6395e78dd51f853f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24779)](https://github.com/jentic/jentic-public-apis/commit/d1737bd1adaff2ee823c23a20b7fdc299d39af40) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24777)](https://github.com/jentic/jentic-public-apis/commit/8327aa47b6307159c76f971ca73b1183d81e4879) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24776)](https://github.com/jentic/jentic-public-apis/commit/c7824496ca58ddd58f246e84bcff12ac688d1234) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24775)](https://github.com/jentic/jentic-public-apis/commit/ad398a6de44c353f5a848b916bb23c12e2390b6e) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-26` [Rebuild apis.json, scores.json, and API browsing indexes (#24774)](https://github.com/jentic/jentic-public-apis/commit/fafd3b7275d0b153bdca041b43e3228764e6cfef) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 82 more in this group (see All events table)

### Dependency maintenance (52)

- `2026-09-25` [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/55) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-25` [Bump markdownlint-cli2 from 0.23.2 to 0.23.3 in the markdown group](https://github.com/OAI/build-infra/pull/57) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-25` [Merge pull request #587 from OAI/dependabot/npm_and_yarn/yargs-18.2.0](https://github.com/OAI/Arazzo-Specification/commit/12aa8bcb07a2df0719afd1f1f3d18334012a5172) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-25` [Merge pull request #586 from OAI/dependabot/npm_and_yarn/markdownlint-cli2-0.23.3](https://github.com/OAI/Arazzo-Specification/commit/1378e970e78d4920bfef021f8a0a4ecbd9b650f9) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-24` [Bump yargs from 18.1.0 to 18.2.0](https://github.com/OAI/build-infra/pull/61) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-24` [Merge pull request #139 from OAI/dependabot/github_actions/ruby/setup-ruby-1.325.0](https://github.com/OAI/spec.openapis.org/commit/6906195c4ea4aef257facb47a98dd0ce0b342df9) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-09-24` [build(deps): bump ruby/setup-ruby from 1.324.0 to 1.325.0](https://github.com/OAI/spec.openapis.org/commit/51b42f129e6f67e76f1787c09e5c1641cc065b94) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-09-24` [chore(deps): bump yargs from 18.1.0 to 18.2.0](https://github.com/OAI/Arazzo-Specification/commit/97abeedf0d72df1df917349e8478f235507345ef) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- … and 44 more in this group (see All events table)

### P2-1 CLI binary (26)

- `2026-09-18` [refactor(expr): extract simple-condition evaluation into its own module](https://github.com/strefethen/arazzo-cli/commit/38b60889ae05dd5d1b43e63afa04a445452a71bd) — `strefethen/arazzo-cli` · `commit` · _cli,actor,spec_
- `2026-09-18` [docs(audience): define the target audience in AUDIENCE-NOTES.md](https://github.com/usearazzo/website/commit/a1e7e8dba77dacf0693910b49c11474f8b91a6c9) — `usearazzo/website` · `commit` · _cli_
- `2026-09-17` [docs(plans): track the 2026-09-05 and 2026-09-12 code smell audits](https://github.com/strefethen/arazzo-cli/commit/5ce6548ddda9bee89ff2eb84f0852382efb9580a) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-16` [fix: update rustls for RUSTSEC-2026-0285](https://github.com/strefethen/arazzo-cli/commit/679af12f6ee088b5dace69b4693a5802f7ff5aeb) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-16` [chore: pin Rust 1.98.1 for builds and releases](https://github.com/strefethen/arazzo-cli/commit/43cb26abd42f6b8753006412076b83e2b0b177b5) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [chore(release): prepare 0.7.0 with the RFC 9535 JSONPath engine](https://github.com/strefethen/arazzo-cli/commit/9a1dd3fe5add36f541b17385a361afb21ff0e825) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(plans): complete the RFC 9535 JSONPath migration plan](https://github.com/strefethen/arazzo-cli/commit/26a3dc0137b8eabcef6ecd694a01be5785271af7) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-14` [docs(agents): drop the empty approved-extensions allowlist paragraph](https://github.com/strefethen/arazzo-cli/commit/48b0dff75b2c6e7bd8ba611d58a475ead732d740) — `strefethen/arazzo-cli` · `commit` · _cli_
- … and 18 more in this group (see All events table)

### API security (OAI sig-security) (17)

- `2026-09-25` [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-24` [Support for GNAP](https://github.com/OAI/sig-security/issues/9) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-11` [v3.2: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5534) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-09-11` [v3.3: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5533) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- … and 9 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (13)

- `2026-09-25` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-19` [chore(deps): bump @speclynx/apidom-ls from 2.12.0 to 2.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/168) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-08` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- … and 5 more in this group (see All events table)

### P1-7 JSON Schema layer (10)

- `2026-09-25` [Bump @hyperjump/json-schema-coverage from 1.2.2 to 1.3.0 in the hyperjump group](https://github.com/OAI/build-infra/pull/60) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-09-22` [docs(guides): add the Resolving Arazzo Documents guide](https://github.com/usearazzo/website/commit/e31c935722658f058637ba7410cf1f77d58136ee) — `usearazzo/website` · `commit` · _schema,spec_
- `2026-09-17` [Merge pull request #47 from OAI/dependabot/npm_and_yarn/hyperjump-ad3e30104b](https://github.com/OAI/build-infra/commit/177ff67ec793e93ea0a9c84121c45384b960da30) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-11` [Bump @hyperjump/json-schema-coverage in the hyperjump group](https://github.com/OAI/build-infra/commit/eb5cc236ffda832ddc0e2c18a7c25139dc95df42) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-06` [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,schema,spec_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,schema,spec_
- `2026-08-02` [Add Gesso (PHP OpenAPI 3.0/3.1/3.2 contract testing library)](https://github.com/OAI/tools.openapis.org/pull/273) — `OAI/tools.openapis.org` · `pr` · _schema,spec_
- `2026-03-16` [Bump @hyperjump/json-schema from 1.17.3 to 1.17.4](https://github.com/OAI/learn.openapis.org/pull/177) — `OAI/learn.openapis.org` · `pr` · _actor,schema,depbump_
- … and 2 more in this group (see All events table)

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

### P1-6 payload XPath / P0-5 XPath criteria (5)

- `2026-09-25` [chore(deps-dev): bump jsdom from 29.1.1 to 30.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/187) — `usearazzo/arazzo-toolkit` · `pr` · _xml,human,depbump_
- `2026-09-25` [chore(deps-dev): bump jsdom from 29.1.1 to 30.1.0](https://github.com/usearazzo/arazzo-toolkit/pull/181) — `usearazzo/arazzo-toolkit` · `pr` · _xml,human,a2a,depbump_
- `2026-09-16` [v3.3: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5540) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-12` [v3.2: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5541) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### P0-5 XPath criteria + P1-6 targetSelectorType (4)

- `2026-09-16` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-09-13` [fix(runtime): decide jsonpath criteria by nodelist cardinality](https://github.com/strefethen/arazzo-cli/commit/7511e40d0113803274e95a3e3e0115cec3a916ee) — `strefethen/arazzo-cli` · `commit` · _xml,xpath,cli,spec_
- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### Arazzo runner / step execution (3)

- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### Roadmap A2A step type (3)

- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website` · `commit` · _a2a,spec_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_
- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### P0-6 source routing (wsdl type) (2)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_
- `2026-09-06` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### Roadmap GraphQL step type (2)

- `2026-09-16` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-26 | openapi.tools | tool_collection | [openapi.tools checksum 986a885fa349](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-26 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-26 | usearazzo/website | commit | [feat(ecosystem): add The Architectural Limits of Agentic iPaaS to articles](https://github.com/usearazzo/website/commit/db193cb7b0397ec14b20ef0ebfb34d56a30bc70d) |  | watch |  |
| 2026-09-26 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add The Architectural Limits of Agentic iPaaS to art…](https://github.com/usearazzo/website/commit/db193cb7b0397ec14b20ef0ebfb34d56a30bc70d) |  | watch |  |
| 2026-09-26 | Mohammed-Alama/php-arazzo | pr | [Phase c evaluator plugins](https://github.com/Mohammed-Alama/php-arazzo/pull/73) |  | actionable |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23156 (#23157)](https://github.com/jentic/jentic-public-apis/commit/f145d06f3afb628e8d4d8efced938de7d34a8366) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24780)](https://github.com/jentic/jentic-public-apis/commit/d16c4b9342fe73ad4f6fb34d6395e78dd51f853f) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23168 (#23169)](https://github.com/jentic/jentic-public-apis/commit/c12cc96e7efa762391c179c87bd7dcba011267ee) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24779)](https://github.com/jentic/jentic-public-apis/commit/d1737bd1adaff2ee823c23a20b7fdc299d39af40) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23174 (#23175)](https://github.com/jentic/jentic-public-apis/commit/56873776998dbe5794467f903be3c2ae9276129d) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23186 (#23187)](https://github.com/jentic/jentic-public-apis/commit/1098a42d2728634592b6812c83d5ddb978fc874f) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24777)](https://github.com/jentic/jentic-public-apis/commit/8327aa47b6307159c76f971ca73b1183d81e4879) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23195 (#23196)](https://github.com/jentic/jentic-public-apis/commit/4e3d60c1ab17c7284c8250ddc9a7e61ff4bfe2d1) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24776)](https://github.com/jentic/jentic-public-apis/commit/c7824496ca58ddd58f246e84bcff12ac688d1234) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23201 (#23202)](https://github.com/jentic/jentic-public-apis/commit/846acdf192f61bdaa362a7b0bede75661bea1140) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24775)](https://github.com/jentic/jentic-public-apis/commit/ad398a6de44c353f5a848b916bb23c12e2390b6e) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23205 (#23206)](https://github.com/jentic/jentic-public-apis/commit/640aca3b6f6d13d2f59e5ff6aebfe5d1af29500a) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24774)](https://github.com/jentic/jentic-public-apis/commit/fafd3b7275d0b153bdca041b43e3228764e6cfef) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23209 (#23210)](https://github.com/jentic/jentic-public-apis/commit/e7600086d0e2fa4ca957711976f7edf332e55353) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23211 (#23212)](https://github.com/jentic/jentic-public-apis/commit/617b05d9b933303708bf8289fed3f44d41219dbd) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24772)](https://github.com/jentic/jentic-public-apis/commit/fca8e4003f127ac504794fcfb90f8089198add44) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23219 (#23220)](https://github.com/jentic/jentic-public-apis/commit/85ad8e78fcdd8a18b90cfd7bb9fbd23f60abf6d0) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24771)](https://github.com/jentic/jentic-public-apis/commit/ff43852f9a826a520fc50c77fd4e4954c3cc3cfb) |  | watch |  |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23223 (#23224)](https://github.com/jentic/jentic-public-apis/commit/c009f0751262e5859297337197482bf3c10a91d3) | spec | watch | Conformance / schema validation |
| 2026-09-26 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #23229 (#23230)](https://github.com/jentic/jentic-public-apis/commit/d49c74160a830bf7043ff459c6c439ffe45baa3f) | spec | watch | Conformance / schema validation |
| 2026-09-26 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-26 (#214)](https://github.com/OAI/landscape/commit/a52e15d1a3ee9af049ad339bcc3dfabe0ff50599) |  | watch |  |
| 2026-09-25 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump jsdom from 29.1.1 to 30.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/187) | xml, human, depbump | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-25 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump typescript-eslint from 8.70.0 to 8.70.1](https://github.com/usearazzo/arazzo-toolkit/pull/189) | breaking, depbump | breaking | Dependency maintenance |
| 2026-09-25 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @microsoft/api-extractor from 7.59.1 to 7.59.2](https://github.com/usearazzo/arazzo-toolkit/pull/188) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-25 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump jsdom from 29.1.1 to 30.1.0](https://github.com/usearazzo/arazzo-toolkit/pull/181) | xml, human, a2a, depbump | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-25 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/55) | depbump | watch | Dependency maintenance |
| 2026-09-25 | OAI/build-infra | pr | [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-25 | OAI/build-infra | pr | [Bump @hyperjump/json-schema-coverage from 1.2.2 to 1.3.0 in the hyperjump group](https://github.com/OAI/build-infra/pull/60) | schema, depbump | watch | P1-7 JSON Schema layer |
| 2026-09-25 | OAI/build-infra | pr | [Bump markdownlint-cli2 from 0.23.2 to 0.23.3 in the markdown group](https://github.com/OAI/build-infra/pull/57) | depbump | watch | Dependency maintenance |
| 2026-09-25 | speakeasy-api/openapi | release | [v1.25.3](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.3) | cli, schema, depbump | actionable | P1-7 JSON Schema layer |
| 2026-09-25 | usearazzo/website | commit | [feat(ecosystem): add API Flows Viewer to tools](https://github.com/usearazzo/website/commit/08f3f536042b04b5dc43dbf8267a34720a774e8b) |  | watch |  |
| 2026-09-25 | usearazzo/website.ecosystem.atom | commit | [feat(ecosystem): add API Flows Viewer to tools](https://github.com/usearazzo/website/commit/08f3f536042b04b5dc43dbf8267a34720a774e8b) |  | watch |  |
| 2026-09-25 | OAI/Arazzo-Specification | pr | [dev: sync with main](https://github.com/OAI/Arazzo-Specification/pull/585) | spec | watch | Conformance / schema validation |
| 2026-09-25 | OAI/Arazzo-Specification | pr | [chore(deps): bump yargs from 18.1.0 to 18.2.0](https://github.com/OAI/Arazzo-Specification/pull/587) | depbump | actionable | Dependency maintenance |
| 2026-09-25 | OAI/Arazzo-Specification | commit | [Merge pull request #587 from OAI/dependabot/npm_and_yarn/yargs-18.2.0](https://github.com/OAI/Arazzo-Specification/commit/12aa8bcb07a2df0719afd1f1f3d18334012a5172) | depbump | watch | Dependency maintenance |
| 2026-09-25 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump markdownlint-cli2 from 0.23.2 to 0.23.3](https://github.com/OAI/Arazzo-Specification/pull/586) | depbump | actionable | Dependency maintenance |
| 2026-09-25 | OAI/Arazzo-Specification | commit | [Merge pull request #586 from OAI/dependabot/npm_and_yarn/markdownlint-cli2-0.23.3](https://github.com/OAI/Arazzo-Specification/commit/1378e970e78d4920bfef021f8a0a4ecbd9b650f9) | depbump | watch | Dependency maintenance |
| 2026-09-25 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.54.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.54.3) | spec | actionable | Conformance / schema validation |
| 2026-09-25 | Redocly/redocly-cli | release | [@redocly/respect-core@2.54.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.54.3) | spec | actionable | Conformance / schema validation |
| 2026-09-25 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.54.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.54.3) | spec | actionable | Conformance / schema validation |
| 2026-09-25 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.16](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.16) | spec | actionable | Conformance / schema validation |
| 2026-09-25 | Redocly/redocly-cli | release | [@redocly/cli@2.54.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.54.3) | spec | actionable | Conformance / schema validation |
| 2026-09-25 | OAI/sig-security | issue | [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) | spec, security | watch | API security (OAI sig-security) |
| 2026-09-25 | Specmatic/specmatic | release | [2.55.0](https://github.com/specmatic/specmatic/releases/tag/2.55.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-24 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump swagger-client from 3.38.1 to 3.38.2](https://github.com/usearazzo/arazzo-toolkit/pull/186) | depbump | actionable | Dependency maintenance |
| 2026-09-24 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/core from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/185) | depbump | actionable | Dependency maintenance |
| 2026-09-24 | OAI/build-infra | pr | [Bump yargs from 18.1.0 to 18.2.0](https://github.com/OAI/build-infra/pull/61) | depbump | watch | Dependency maintenance |
| 2026-09-24 | OAI/spec.openapis.org | pr | [Overlay - update ReSpec-rendered specification versions](https://github.com/OAI/spec.openapis.org/pull/138) | spec | watch | Conformance / schema validation |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [Don't delete files we need on sync.](https://github.com/OAI/OpenAPI-Specification/pull/5555) |  | actionable |  |
| 2026-09-24 | OAI/build-infra | pr | [Support abstracts sourced from Markdown](https://github.com/OAI/build-infra/pull/59) | spec | watch | Conformance / schema validation |
| 2026-09-24 | OAI/tools.openapis.org | issue | [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-24 | OAI/sig-security | issue | [Support for GNAP](https://github.com/OAI/sig-security/issues/9) | spec, security | watch | API security (OAI sig-security) |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [v3.3-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5560) |  | actionable |  |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [v3.2-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5559) |  | actionable |  |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [v3.1-dev: sync with dev](https://github.com/OAI/OpenAPI-Specification/pull/5558) |  | actionable |  |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) |  | actionable |  |
| 2026-09-24 | OAI/OpenAPI-Specification | pr | [Update weekly meeting agenda to include spec/schema publishing](https://github.com/OAI/OpenAPI-Specification/pull/5557) |  | watch |  |
| 2026-09-24 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) | spec | watch | Conformance / schema validation |
| 2026-09-24 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 01 October 2026](https://github.com/OAI/OpenAPI-Specification/issues/5556) | spec | watch | Conformance / schema validation |
| 2026-09-24 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 10 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5532) | spec | watch | Conformance / schema validation |
| 2026-09-24 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | spec | watch | Conformance / schema validation |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
