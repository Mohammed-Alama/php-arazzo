# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-16T11:20:01+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 890 (showing 200 newest)
- **Severity:** breaking **37** · actionable **502** · watch **351**
- **Top relevance:** `Conformance / schema validation` (341) · `uncategorized` (163) · `Dependency maintenance` (109) · `P2-1 CLI binary` (89) · `P1-7 JSON Schema layer` (43)
- **Top sources:** `strefethen/arazzo-cli` (62) · `OAI/Arazzo-Specification` (51) · `OAI/OpenAPI-Specification` (42) · `OAI/build-infra` (41) · `speclynx/apidom` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Dependency maintenance (9)

- `2026-10-15` [chore(deps-dev): bump mocha-chai-jest-snapshot from 1.1.7 to 1.1.8](https://github.com/usearazzo/arazzo-toolkit/pull/243) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-10` [chore(deps): bump chalk from 5.6.2 to 6.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/223) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-10` [chore(deps): bump commander from 14.0.3 to 15.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/221) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-10-06` [build(deps): bump actions/checkout from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/208) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2026-10-06` [Bump just-the-docs from 0.11.1 to 0.12.0](https://github.com/OAI/learn.openapis.org/pull/180) — `OAI/learn.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- … and 1 more in this group (see All events table)

### Conformance / schema validation (8)

- `2026-10-16` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-10-16` [\[Feature\] Swagger 2.0 source support (normalizer throws; resolver rejects non-3.x)](https://github.com/Mohammed-Alama/php-arazzo/issues/167) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking,spec_
- `2026-10-15` [v6.0.0](https://github.com/speclynx/apidom/releases/tag/v6.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-01-23` [v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_

### P1-7 JSON Schema layer (4)

- `2025-01-20` [Arazzo 1.0.1 Released!](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) — `OAI/Arazzo-Specification` · `release` · _schema,runner,spec_
- `2021-02-16` [OAS 3.1.0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-10-09` [OAS 3.1.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,spec_
- `2020-06-18` [OAS 3.1.0-rc0 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.1.0-rc0) — `OAI/OpenAPI-Specification` · `release` · _breaking,schema,security,spec_

### P2-1 CLI binary (4)

- `2026-10-16` [v1.26.0](https://github.com/speakeasy-api/openapi/releases/tag/v1.26.0) — `speakeasy-api/openapi` · `release` · _cli,breaking,spec_
- `2026-10-15` [chore(deps): bump github/codeql-action from 4.38.2 to 4.38.3](https://github.com/usearazzo/arazzo-toolkit/pull/238) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,depbump_
- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

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

### P0-6 source routing (wsdl type) (2)

- `2026-10-16` [\[Story\] Topics, scoring & retargeted refs](https://github.com/Mohammed-Alama/php-arazzo/issues/180) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,mcp,cli,actor,human,loop,a2a,grpc,graphql,transformer,function,breaking,schema,runner,moonwalk,security,depbump_
- `2026-09-30` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_

### Arazzo runner / step execution (1)

- `2026-10-10` [feat(runner): treat a called workflow as its step's result](https://github.com/usearazzo/arazzo-toolkit/pull/230) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,runner,spec_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (244)

- `2026-10-16` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-16` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-16` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-16` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-16` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-16` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-16` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-16` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 236 more in this group (see All events table)

### uncategorized (63)

- `2026-10-16` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-16` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-16` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-15` [Share Hyperjump runtime across nested installs](https://github.com/OAI/build-infra/pull/71) — `OAI/build-infra` · `pr` · _no tags_
- `2026-10-15` [docs(issue-intake): document the four-level hierarchy and the renamed kinds](https://github.com/Mohammed-Alama/php-arazzo/pull/138) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-15` [fix(issue-intake): make package-index resilient and narrow its triggers](https://github.com/Mohammed-Alama/php-arazzo/pull/136) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-15` [feat(issue-intake): migrate to kind:feature/kind:bugfix/kind:release/kind:chore](https://github.com/Mohammed-Alama/php-arazzo/pull/135) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-14` [fix(issue-intake): delete the gate's marker comment once an issue conforms](https://github.com/Mohammed-Alama/php-arazzo/pull/119) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- … and 55 more in this group (see All events table)

### P2-1 CLI binary (54)

- `2026-10-16` [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-16` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 46 more in this group (see All events table)

### Dependency maintenance (47)

- `2026-10-15` [chore(deps-dev): bump typescript-eslint from 8.71.0 to 8.71.2](https://github.com/usearazzo/arazzo-toolkit/pull/244) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-15` [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/pull/147) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump eslint from 10.11.0 to 10.12.0](https://github.com/usearazzo/arazzo-toolkit/pull/240) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump @types/node from 26.6.4 to 26.6.5](https://github.com/usearazzo/arazzo-toolkit/pull/241) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump @babel/preset-typescript from 8.0.1 to 8.0.7](https://github.com/usearazzo/arazzo-toolkit/pull/239) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump prettier from 3.9.9 to 3.9.10](https://github.com/usearazzo/arazzo-toolkit/pull/237) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-13` [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/pull/146) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-12` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- … and 39 more in this group (see All events table)

### P1-7 JSON Schema layer (24)

- `2026-10-15` [v1.25.6](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.6) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-12` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/pull/594) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-10` [@usearazzo/validator@1.0.1-alpha.7](https://www.npmjs.com/package/@usearazzo/validator/v/1.0.1-alpha.7) — `npm.@usearazzo/validator` · `release` · _schema,spec_
- `2026-10-07` [v3.2: Fix JSON Schema keyword name. (port of 5195)](https://github.com/OAI/OpenAPI-Specification/pull/5197) — `OAI/OpenAPI-Specification` · `pr` · _schema_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.9](https://github.com/OAI/learn.openapis.org/pull/213) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-05` [v1.25.4](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.4) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-10-04` [v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) — `usearazzo/arazzo-toolkit` · `release` · _schema,spec_
- `2026-10-01` [v6.17.0](https://github.com/stoplightio/spectral/releases/tag/v6.17.0) — `stoplightio/spectral` · `release` · _schema,depbump_
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

### P1-6 payload XPath / P0-5 XPath criteria (14)

- `2026-10-14` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,depbump_
- `2026-10-14` [chore(deps-dev): bump jsdom from 30.1.1 to 30.1.2](https://github.com/usearazzo/arazzo-toolkit/pull/236) — `usearazzo/arazzo-toolkit` · `pr` · _xml,a2a,depbump_
- `2026-10-06` [build(deps): bump actions/setup-node from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/209) — `OAI/learn.openapis.org` · `pr` · _xml,depbump_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,security,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- `2026-06-01` [2.46.3](https://github.com/specmatic/specmatic/releases/tag/2.46.3) — `Specmatic/specmatic` · `release` · _xml,depbump_
- … and 6 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (11)

- `2026-10-16` [Factor branch management code into the build-infra package](https://github.com/OAI/build-infra/pull/72) — `OAI/build-infra` · `pr` · _actor,spec_
- `2026-10-15` [feat(runner): run petstore workflows e2e and publish the runner](https://github.com/usearazzo/arazzo-toolkit/pull/233) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-10-14` [feat: typed issue-intake enforcement + arazzo-detective skill](https://github.com/Mohammed-Alama/php-arazzo/pull/117) — `Mohammed-Alama/php-arazzo` · `pr` · _human,spec_
- `2026-09-27` [Bump vite from 8.3.0 to 8.3.1 in the vitest group across 1 directory](https://github.com/OAI/build-infra/pull/63) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- `2026-07-31` [2.51.1](https://github.com/specmatic/specmatic/releases/tag/2.51.1) — `Specmatic/specmatic` · `release` · _actor,spec_
- `2025-02-17` [v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _actor,spec_
- … and 3 more in this group (see All events table)

### Roadmap A2A step type (8)

- `2026-10-15` [chore(deps-dev): bump @babel/core from 8.0.6 to 8.0.7](https://github.com/usearazzo/arazzo-toolkit/pull/245) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-10-15` [v6.0.6](https://github.com/speclynx/apidom/releases/tag/v6.0.6) — `speclynx/apidom` · `release` · _a2a,spec_
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

### P0-6 source routing (wsdl type) (4)

- `2026-10-14` [Phase F — protocol packages (HTTP/SOAP/RPC) + runner restructure](https://github.com/Mohammed-Alama/php-arazzo/pull/76) — `Mohammed-Alama/php-arazzo` · `pr` · _soap,wsdl,xml,xpath,cli,actor,grpc,spec_
- `2026-10-04` [Phase E: Runner Package Split + OMS Engine (E0-E10 complete)](https://github.com/Mohammed-Alama/php-arazzo/pull/75) — `Mohammed-Alama/php-arazzo` · `pr` · _soap,xml,xpath,a2a,spec_
- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_

### Roadmap GraphQL step type (1)

- `2026-09-26` [Phase D: Document Normalizer - D0 Package Split + D0.5 Cebe Purge](https://github.com/Mohammed-Alama/php-arazzo/pull/74) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,graphql,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (100)

- `2026-10-16` [Rebuild apis.json, scores.json, and API browsing indexes (#27565)](https://github.com/jentic/jentic-public-apis/commit/47e385b62c58801d83bea5e2d5e8a871ca932f53) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-16` [\[Package\] core](https://github.com/Mohammed-Alama/php-arazzo/issues/122) — `Mohammed-Alama/php-arazzo` · `issue` · _no tags_
- `2026-10-16` [\[Package\] evaluation](https://github.com/Mohammed-Alama/php-arazzo/issues/125) — `Mohammed-Alama/php-arazzo` · `issue` · _no tags_
- `2026-10-16` [\[Package\] expression](https://github.com/Mohammed-Alama/php-arazzo/issues/127) — `Mohammed-Alama/php-arazzo` · `issue` · _no tags_
- `2026-10-16` [\[Package\] runner](https://github.com/Mohammed-Alama/php-arazzo/issues/132) — `Mohammed-Alama/php-arazzo` · `issue` · _no tags_
- `2026-10-16` [\[Package\] ecosystem](https://github.com/Mohammed-Alama/php-arazzo/issues/175) — `Mohammed-Alama/php-arazzo` · `issue` · _no tags_
- `2026-10-16` [Rebuild apis.json, scores.json, and API browsing indexes (#27564)](https://github.com/jentic/jentic-public-apis/commit/988c5c630985e5eee37520e1c212e1622ef8d29b) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-16` [Rebuild apis.json, scores.json, and API browsing indexes (#27563)](https://github.com/jentic/jentic-public-apis/commit/774b7ef0d15bf485859dca79211728c8aa343da4) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 92 more in this group (see All events table)

### Conformance / schema validation (89)

- `2026-10-16` [openapi.tools checksum 41b1717278c2](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-16` [feat: Replace OpenAPI spec for Issue #27327 (#27330)](https://github.com/jentic/jentic-public-apis/commit/dd75febabfc8184edc7483d427721dee3be8013a) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-16` [\[Package\] protocol-http](https://github.com/Mohammed-Alama/php-arazzo/issues/129) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-10-16` [\[Feature\] AsyncAPI `receive` steps: default success semantics + validator rule](https://github.com/Mohammed-Alama/php-arazzo/issues/168) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-10-16` [\[Bug\] `$outputs.<name>` reference evaluates to null](https://github.com/Mohammed-Alama/php-arazzo/issues/164) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-10-16` [\[Bug\] `jsonpath` criterion ignores its `context` field (plugin shadows the built-in evaluator)](https://github.com/Mohammed-Alama/php-arazzo/issues/165) — `Mohammed-Alama/php-arazzo` · `issue` · _spec_
- `2026-10-16` [feat: Import OpenAPI spec from Issue #26856 (#26858)](https://github.com/jentic/jentic-public-apis/commit/750e342806a2fccea074920afb8df43ac3a00ac8) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-16` [feat: Import OpenAPI spec from Issue #26854 (#26855)](https://github.com/jentic/jentic-public-apis/commit/d851dc9666b7de76b68916a463276570d3860ff5) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 81 more in this group (see All events table)

### Dependency maintenance (53)

- `2026-10-16` [chore(deps-dev): bump vitest from 5.0.2 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/Arazzo-Specification/pull/600) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-15` [chore(deps-dev): bump @types/dedent from 0.7.2 to 1.7.2](https://github.com/usearazzo/arazzo-toolkit/pull/246) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-15` [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-15` [chore(deps-dev): bump mocha-chai-jest-snapshot to 1.1.8 with Jest 30 snapshot headers](https://github.com/usearazzo/arazzo-toolkit/issues/242) — `usearazzo/arazzo-toolkit` · `issue` · _depbump_
- `2026-10-15` [chore(deps-dev): bump mocha-chai-jest-snapshot from 1.1.7 to 1.1.8](https://github.com/usearazzo/arazzo-toolkit/pull/235) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-15` [Merge pull request #147 from OAI/dependabot/github_actions/ruby/setup-ruby-1.329.0](https://github.com/OAI/spec.openapis.org/commit/a2287d17dbed41eb3422d5001f9811cabd204c06) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-15` [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/commit/023afb610737f0963c0931f74243681f1407de00) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-13` [build(deps): bump prettier from 3.9.9 to 3.9.10](https://github.com/OAI/learn.openapis.org/pull/219) — `OAI/learn.openapis.org` · `pr` · _depbump_
- … and 45 more in this group (see All events table)

### P2-1 CLI binary (31)

- `2026-10-13` [chore: stop tracking bug-hunt-results.md](https://github.com/strefethen/arazzo-cli/commit/038dc83bf5d8e1961c11e01b58ddaaf8da07d135) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-13` [fix(deps): patch quinn-proto and refresh debugger docs](https://github.com/strefethen/arazzo-cli/commit/47ddf1c203e7a94d688bbb2f68b986eebc3aa79a) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-13` [docs: clarify contribution policy](https://github.com/strefethen/arazzo-cli/commit/eb9c323e651c88dce6b67502b95310c0fbe30498) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [runner: record criterion diagnostics and fail unavailable criteria softly](https://github.com/usearazzo/arazzo-toolkit/issues/231) — `usearazzo/arazzo-toolkit` · `issue` · _cli,runner,spec_
- `2026-10-10` [fix(expr): refuse JSONPath queries nested deeper than 8 brackets](https://github.com/strefethen/arazzo-cli/commit/af6ee9df1b1d15e0b00e8d171d072dc37b70c045) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [chore: add the owner-voice skill and record the CLI release in project notes](https://github.com/usearazzo/website/commit/25d8affa10dc70f901d9cb4a68f90e34c4814e33) — `usearazzo/website` · `commit` · _cli_
- `2026-10-10` [feat(blog): add the @usearazzo/cli release post](https://github.com/usearazzo/website/commit/76afac7e32bcc304b20991f1dc506831250724a1) — `usearazzo/website` · `commit` · _cli,spec_
- `2026-10-10` [docs: say the CLI is on npm across the site](https://github.com/usearazzo/website/commit/c9deafc32dea5dabca0798dfcd2c6e777551f3a1) — `usearazzo/website` · `commit` · _cli_
- … and 23 more in this group (see All events table)

### P1-7 JSON Schema layer (15)

- `2026-10-16` [\[Package\] document](https://github.com/Mohammed-Alama/php-arazzo/issues/123) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,schema,spec_
- `2026-10-16` [\[Bug\] Sub-workflow steps are not handled on the synchronous path](https://github.com/Mohammed-Alama/php-arazzo/issues/170) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,schema,spec_
- `2026-10-15` [Bump the hyperjump group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/69) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-10-12` [Merge pull request #594 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.18.0](https://github.com/OAI/Arazzo-Specification/commit/79ddb24a832ea35d2d11e5674c40fd4f63ab906e) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-12` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/commit/3fe1737618afed353f346e8086a32716b44194ed) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-11` [Validate Arazzo Documents From the Command Line](https://usearazzo.com/blog/validate-arazzo-documents-from-the-command-line/) — `usearazzo/website.feed` · `article` · _cli,schema,spec_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-08` [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) — `OAI/learn.openapis.org` · `issue` · _schema,spec_
- … and 7 more in this group (see All events table)

### API security (OAI sig-security) (12)

- `2026-10-02` [Auto-merge deletes the entire src tree](https://github.com/OAI/sig-security/issues/56) — `OAI/sig-security` · `issue` · _security_
- `2026-09-25` [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-24` [Support for GNAP](https://github.com/OAI/sig-security/issues/9) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- … and 4 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (11)

- `2026-10-16` [Merge pull request #72 from handrews/feature/shared-branch-sync](https://github.com/OAI/build-infra/commit/d9a42b59eb6229167ab627540752a8a0785cbfae) — `OAI/build-infra` · `commit` · _actor_
- `2026-10-14` [Permit multiple externalDocs](https://github.com/OAI/OpenAPI-Specification/issues/1034) — `OAI/OpenAPI-Specification` · `issue` · _actor_
- `2026-10-05` [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-28` [Bump jmertic/lfx-landscape-tools from 20260916 to 20260923 in the all group (#217)](https://github.com/OAI/landscape/commit/9a4f7b0a15a7c053b56271599292151e873b3159) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor_
- `2026-08-21` [Endpoint-level and field-level role/permission support](https://github.com/OAI/sig-security/issues/35) — `OAI/sig-security` · `issue` · _actor,spec,security_
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

### Arazzo runner / step execution (7)

- `2026-10-15` [runner: opt-in e2e petstore suite](https://github.com/usearazzo/arazzo-toolkit/issues/79) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-12` [runner: CommonJS entry fails to load — missing vendor/swagger-client.cjs](https://github.com/usearazzo/arazzo-toolkit/issues/234) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-10` [runner: response context and $outputs for steps that call a workflow](https://github.com/usearazzo/arazzo-toolkit/issues/228) — `usearazzo/arazzo-toolkit` · `issue` · _loop,runner,spec_
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

- `2026-10-16` [\[Bug\] `xpath` criterion version: only `xpath-10` runs while the spec default is `xpath-31`](https://github.com/Mohammed-Alama/php-arazzo/issues/166) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,function,spec_
- `2026-10-14` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-10-10` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,runner,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### Roadmap A2A step type (4)

- `2026-10-15` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,runner,depbump_
- `2026-10-15` [Bump respec from 37.4.0 to 37.4.1 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump jekyll-remote-theme from 0.4.3 to 0.6.2](https://github.com/OAI/learn.openapis.org/pull/210) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_

### Roadmap GraphQL step type (4)

- `2026-10-16` [\[Story\] Version-signal correctness](https://github.com/Mohammed-Alama/php-arazzo/issues/179) — `Mohammed-Alama/php-arazzo` · `issue` · _graphql,spec_
- `2026-10-16` [\[Bug\] `file-issue --edit --parent` re-adds an existing sub-issue and aborts after writing the body](https://github.com/Mohammed-Alama/php-arazzo/issues/187) — `Mohammed-Alama/php-arazzo` · `issue` · _graphql,spec_
- `2026-10-14` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### P0-6 source routing (wsdl type) (2)

- `2026-10-16` [Document & runner validation hardening](https://github.com/Mohammed-Alama/php-arazzo/issues/118) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,loop,graphql,runner,spec_
- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-10-16` [\[Story\] Spec-text & npm diffing](https://github.com/Mohammed-Alama/php-arazzo/issues/183) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,mcp,cli,moonwalk,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Issue #410 loops vs goto (1)

- `2026-10-11` [validator: track new validation and lint rules](https://github.com/usearazzo/arazzo-toolkit/issues/197) — `usearazzo/arazzo-toolkit` · `issue` · _loop,spec_

### Roadmap gRPC step type (1)

- `2026-10-14` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-10-16 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-16 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-16 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-16 | openapi.tools | tool_collection | [openapi.tools checksum 41b1717278c2](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-16 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.6](https://github.com/speclynx/apidom/releases/tag/v6.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.5](https://github.com/speclynx/apidom/releases/tag/v6.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.4](https://github.com/speclynx/apidom/releases/tag/v6.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.3](https://github.com/speclynx/apidom/releases/tag/v6.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.2](https://github.com/speclynx/apidom/releases/tag/v6.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.1](https://github.com/speclynx/apidom/releases/tag/v6.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v6.0.0](https://github.com/speclynx/apidom/releases/tag/v6.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.7](https://github.com/speclynx/apidom/releases/tag/v5.2.7) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.3.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.3.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.7](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27565)](https://github.com/jentic/jentic-public-apis/commit/47e385b62c58801d83bea5e2d5e8a871ca932f53) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Replace OpenAPI spec for Issue #27327 (#27330)](https://github.com/jentic/jentic-public-apis/commit/dd75febabfc8184edc7483d427721dee3be8013a) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] document](https://github.com/Mohammed-Alama/php-arazzo/issues/123) | actor, schema, spec | watch | P1-7 JSON Schema layer |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] core](https://github.com/Mohammed-Alama/php-arazzo/issues/122) |  | watch |  |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] evaluation](https://github.com/Mohammed-Alama/php-arazzo/issues/125) |  | watch |  |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] expression](https://github.com/Mohammed-Alama/php-arazzo/issues/127) |  | watch |  |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] protocol-http](https://github.com/Mohammed-Alama/php-arazzo/issues/129) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] runner](https://github.com/Mohammed-Alama/php-arazzo/issues/132) |  | watch |  |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Package\] ecosystem](https://github.com/Mohammed-Alama/php-arazzo/issues/175) |  | watch |  |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.64.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.64.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/respect-core@2.64.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.64.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/recheck@2.64.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.64.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.64.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.64.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/client-generator@0.7.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.7.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Redocly/redocly-cli | release | [@redocly/cli@2.64.1](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.64.1) | spec | actionable | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Story\] Version-signal correctness](https://github.com/Mohammed-Alama/php-arazzo/issues/179) | graphql, spec | watch | Roadmap GraphQL step type |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Story\] Spec-text & npm diffing](https://github.com/Mohammed-Alama/php-arazzo/issues/183) | xml, mcp, cli, moonwalk, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Bug\] `xpath` criterion version: only `xpath-10` runs while the spec default is `xpath-31`](https://github.com/Mohammed-Alama/php-arazzo/issues/166) | xml, xpath, function, spec | watch | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Feature\] AsyncAPI `receive` steps: default success semantics + validator rule](https://github.com/Mohammed-Alama/php-arazzo/issues/168) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Story\] Topics, scoring & retargeted refs](https://github.com/Mohammed-Alama/php-arazzo/issues/180) | soap, wsdl, xml, xpath, mcp, cli, actor, human, loop, a2a, grpc, graphql, transformer, function, breaking, schema, runner, moonwalk, security, depbump | breaking | P0-6 source routing (wsdl type) |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Bug\] `$outputs.<name>` reference evaluates to null](https://github.com/Mohammed-Alama/php-arazzo/issues/164) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Bug\] `jsonpath` criterion ignores its `context` field (plugin shadows the built-in evaluator)](https://github.com/Mohammed-Alama/php-arazzo/issues/165) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Feature\] Swagger 2.0 source support (normalizer throws; resolver rejects non-3.x)](https://github.com/Mohammed-Alama/php-arazzo/issues/167) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [Document & runner validation hardening](https://github.com/Mohammed-Alama/php-arazzo/issues/118) | soap, wsdl, loop, graphql, runner, spec | watch | P0-6 source routing (wsdl type) |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Bug\] `file-issue --edit --parent` re-adds an existing sub-issue and aborts after writing the body](https://github.com/Mohammed-Alama/php-arazzo/issues/187) | graphql, spec | watch | Roadmap GraphQL step type |
| 2026-10-16 | Mohammed-Alama/php-arazzo | issue | [\[Bug\] Sub-workflow steps are not handled on the synchronous path](https://github.com/Mohammed-Alama/php-arazzo/issues/170) | actor, schema, spec | watch | P1-7 JSON Schema layer |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27564)](https://github.com/jentic/jentic-public-apis/commit/988c5c630985e5eee37520e1c212e1622ef8d29b) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26856 (#26858)](https://github.com/jentic/jentic-public-apis/commit/750e342806a2fccea074920afb8df43ac3a00ac8) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27563)](https://github.com/jentic/jentic-public-apis/commit/774b7ef0d15bf485859dca79211728c8aa343da4) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26854 (#26855)](https://github.com/jentic/jentic-public-apis/commit/d851dc9666b7de76b68916a463276570d3860ff5) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27562)](https://github.com/jentic/jentic-public-apis/commit/f7f80820941f0a2929b04d2454ff5385ce3ef0ba) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26844 (#26846)](https://github.com/jentic/jentic-public-apis/commit/082012cf61152f1ea349659f3b8895f997f55b53) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27561)](https://github.com/jentic/jentic-public-apis/commit/8e85f35f36c7e09e89d80e980690fadf7ce46e26) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26842 (#26843)](https://github.com/jentic/jentic-public-apis/commit/e9a9d4b8783166009d63fe4e5f8571e95fd5c88a) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27560)](https://github.com/jentic/jentic-public-apis/commit/bc5df7f3e3ad08ea8e893844677aa1ac9a6efb4e) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26836 (#26837)](https://github.com/jentic/jentic-public-apis/commit/203c7cba04018687639ebee8b30a29c0874115f1) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27559)](https://github.com/jentic/jentic-public-apis/commit/7bcda00bbd585d8a8febda44e4c79fcd59715374) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26834 (#26835)](https://github.com/jentic/jentic-public-apis/commit/3e07913dabb0789465ac6a00039da413bd5de57a) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27558)](https://github.com/jentic/jentic-public-apis/commit/bc4e7f2c7b520e5b4b0996316422ae9943a2c074) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26831 (#26833)](https://github.com/jentic/jentic-public-apis/commit/c1abfd40bc1b32cabf799a655e7f4c2a291b63a2) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27557)](https://github.com/jentic/jentic-public-apis/commit/e1b528f1cd3570cbdb8493f00b1c159c55aafe72) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26827 (#26830)](https://github.com/jentic/jentic-public-apis/commit/ea13265722c2b85c2df6e515fb50738bc8736a8a) | spec | watch | Conformance / schema validation |
| 2026-10-16 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27556)](https://github.com/jentic/jentic-public-apis/commit/284ee332ea83d3e23ff025c9de9e8dd4c20bca37) |  | watch |  |
| 2026-10-16 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26812 (#26814)](https://github.com/jentic/jentic-public-apis/commit/22fbff9a5f45e1baec42611bdc2f1396c41dfc3d) | spec | watch | Conformance / schema validation |
| 2026-10-16 | Mohammed-Alama/php-arazzo | pr | [feat(ecosystem): stateful signal redesign](https://github.com/Mohammed-Alama/php-arazzo/pull/185) |  | watch |  |
| 2026-10-16 | Mohammed-Alama/php-arazzo | pr | [feat(issue-intake): ground §7 in commit-pinned permalinks](https://github.com/Mohammed-Alama/php-arazzo/pull/186) | cli, human, spec | actionable | P2-1 CLI binary |
| 2026-10-16 | OAI/build-infra | pr | [Factor branch management code into the build-infra package](https://github.com/OAI/build-infra/pull/72) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-10-16 | OAI/build-infra | commit | [Merge pull request #72 from handrews/feature/shared-branch-sync](https://github.com/OAI/build-infra/commit/d9a42b59eb6229167ab627540752a8a0785cbfae) | actor | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-10-16 | speakeasy-api/openapi | release | [v1.26.0](https://github.com/speakeasy-api/openapi/releases/tag/v1.26.0) | cli, breaking, spec | breaking | P2-1 CLI binary |
| 2026-10-16 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 5.0.2 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/Arazzo-Specification/pull/600) | depbump | watch | Dependency maintenance |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @types/dedent from 0.7.2 to 1.7.2](https://github.com/usearazzo/arazzo-toolkit/pull/246) | depbump | watch | Dependency maintenance |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/core from 8.0.6 to 8.0.7](https://github.com/usearazzo/arazzo-toolkit/pull/245) | a2a, depbump | actionable | Roadmap A2A step type |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump typescript-eslint from 8.71.0 to 8.71.2](https://github.com/usearazzo/arazzo-toolkit/pull/244) | depbump | actionable | Dependency maintenance |
| 2026-10-15 | OAI/build-infra | pr | [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/67) | actor, a2a, runner, depbump | watch | Roadmap A2A step type |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
