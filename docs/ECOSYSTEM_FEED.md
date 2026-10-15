# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-10-15T11:25:42+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 892 (showing 200 newest)
- **Severity:** breaking **37** · actionable **504** · watch **351**
- **Top relevance:** `Conformance / schema validation` (335) · `uncategorized` (153) · `Dependency maintenance` (113) · `P2-1 CLI binary` (91) · `P1-7 JSON Schema layer` (43)
- **Top sources:** `strefethen/arazzo-cli` (62) · `OAI/Arazzo-Specification` (51) · `usearazzo/arazzo-toolkit` (42) · `OAI/build-infra` (41) · `speclynx/apidom` (40)
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

### Conformance / schema validation (7)

- `2026-10-15` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-10-15` [v6.0.0](https://github.com/speclynx/apidom/releases/tag/v6.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-09-24` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
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

### P2-1 CLI binary (3)

- `2026-10-15` [chore(deps): bump github/codeql-action from 4.38.2 to 4.38.3](https://github.com/usearazzo/arazzo-toolkit/pull/238) — `usearazzo/arazzo-toolkit` · `pr` · _cli,breaking,depbump_
- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### API security (OAI sig-security) (2)

- `2026-08-21` [\[Announcement\] OAuth2.1 and OAuth3 drafts](https://github.com/OAI/sig-security/issues/30) — `OAI/sig-security` · `issue` · _breaking,security,spec_
- `2020-02-21` [OAS 3.0.3 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.3) — `OAI/OpenAPI-Specification` · `release` · _breaking,security,spec_

### Arazzo runner / step execution (2)

- `2026-10-10` [feat(runner): treat a called workflow as its step's result](https://github.com/usearazzo/arazzo-toolkit/pull/230) — `usearazzo/arazzo-toolkit` · `pr` · _actor,breaking,runner,spec_
- `2026-10-10` [runner: pass a called workflow's outputs through as the calling step's outputs](https://github.com/usearazzo/arazzo-toolkit/issues/229) — `usearazzo/arazzo-toolkit` · `issue` · _breaking,runner,spec_

### Issue #410 kind discriminator / human-in-loop (1)

- `2026-10-15` [chore: security audit + perf benchmarks + API stability review (split from #27)](https://github.com/Mohammed-Alama/php-arazzo/issues/102) — `Mohammed-Alama/php-arazzo` · `issue` · _human,breaking,spec_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-30` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (243)

- `2026-10-15` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-15` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-15` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-10-15` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-15` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-15` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-15` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-10-15` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 235 more in this group (see All events table)

### uncategorized (63)

- `2026-10-15` [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-15` [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-15` [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) — `usearazzo/Arazzo-Specification` · `tag` · _no tags_
- `2026-10-15` [docs(issue-intake): document the four-level hierarchy and the renamed kinds](https://github.com/Mohammed-Alama/php-arazzo/pull/138) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-15` [fix(issue-intake): make package-index resilient and narrow its triggers](https://github.com/Mohammed-Alama/php-arazzo/pull/136) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-15` [feat(issue-intake): migrate to kind:feature/kind:bugfix/kind:release/kind:chore](https://github.com/Mohammed-Alama/php-arazzo/pull/135) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-14` [fix(issue-intake): delete the gate's marker comment once an issue conforms](https://github.com/Mohammed-Alama/php-arazzo/pull/119) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-10-10` [docs: update copyright year to 2026](https://github.com/OAI/learn.openapis.org/pull/218) — `OAI/learn.openapis.org` · `pr` · _no tags_
- … and 55 more in this group (see All events table)

### P2-1 CLI binary (54)

- `2026-10-15` [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-10-15` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 46 more in this group (see All events table)

### Dependency maintenance (49)

- `2026-10-15` [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/pull/147) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump eslint from 10.11.0 to 10.12.0](https://github.com/usearazzo/arazzo-toolkit/pull/240) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump @types/node from 26.6.4 to 26.6.5](https://github.com/usearazzo/arazzo-toolkit/pull/241) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump @babel/preset-typescript from 8.0.1 to 8.0.7](https://github.com/usearazzo/arazzo-toolkit/pull/239) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-14` [chore(deps-dev): bump prettier from 3.9.9 to 3.9.10](https://github.com/usearazzo/arazzo-toolkit/pull/237) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-13` [build(deps): bump ruby/setup-ruby from 1.327.0 to 1.328.0](https://github.com/OAI/spec.openapis.org/pull/146) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-10-12` [chore(deps-dev): bump vitest from 5.0.1 to 5.0.2 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/588) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-09` [chore(deps): bump handlebars and lerna](https://github.com/usearazzo/arazzo-toolkit/pull/226) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 41 more in this group (see All events table)

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

### P1-6 payload XPath / P0-5 XPath criteria (15)

- `2026-10-14` [chore(deps): bump actions/cache from 4 to 6](https://github.com/Mohammed-Alama/php-arazzo/pull/51) — `Mohammed-Alama/php-arazzo` · `pr` · _xml,depbump_
- `2026-10-14` [chore(deps-dev): bump jsdom from 30.1.1 to 30.1.2](https://github.com/usearazzo/arazzo-toolkit/pull/236) — `usearazzo/arazzo-toolkit` · `pr` · _xml,a2a,depbump_
- `2026-10-06` [build(deps): bump actions/setup-node from 6 to 7](https://github.com/OAI/learn.openapis.org/pull/209) — `OAI/learn.openapis.org` · `pr` · _xml,depbump_
- `2026-09-06` [feat: add phpmd, pdepend, phpdoc to quality pipeline](https://github.com/Mohammed-Alama/php-arazzo/pull/53) — `Mohammed-Alama/php-arazzo` · `pr` · _xml_
- `2026-08-10` [2.52.0](https://github.com/specmatic/specmatic/releases/tag/2.52.0) — `Specmatic/specmatic` · `release` · _xml,mcp,actor,security,spec_
- `2026-08-03` [v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `release` · _xml,mcp,cli,loop,spec_
- `2026-07-25` [2.51.0](https://github.com/specmatic/specmatic/releases/tag/2.51.0) — `Specmatic/specmatic` · `release` · _xml,actor,spec_
- `2026-07-08` [v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) — `swaggerexpert/arazzo-criterion` · `release` · _xml,spec_
- … and 7 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (11)

- `2026-10-15` [feat(runner): run petstore workflows e2e and publish the runner](https://github.com/usearazzo/arazzo-toolkit/pull/233) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-10-14` [feat: typed issue-intake enforcement + arazzo-detective skill](https://github.com/Mohammed-Alama/php-arazzo/pull/117) — `Mohammed-Alama/php-arazzo` · `pr` · _human,spec_
- `2026-09-27` [Bump vite from 8.3.0 to 8.3.1 in the vitest group across 1 directory](https://github.com/OAI/build-infra/pull/63) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-27` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-08-09` [v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) — `speclynx/apidom` · `release` · _actor,spec_
- `2026-07-31` [2.51.1](https://github.com/specmatic/specmatic/releases/tag/2.51.1) — `Specmatic/specmatic` · `release` · _actor,spec_
- `2025-02-17` [v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _actor,spec_
- … and 3 more in this group (see All events table)

### Roadmap A2A step type (7)

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

### Issue #410 loops vs goto (1)

- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_

### Roadmap GraphQL step type (1)

- `2026-09-26` [Phase D: Document Normalizer - D0 Package Split + D0.5 Cebe Purge](https://github.com/Mohammed-Alama/php-arazzo/pull/74) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,graphql,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (90)

- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27438)](https://github.com/jentic/jentic-public-apis/commit/e4f184ad45f1944d0e50e3e63e64ab1637b085ea) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27436)](https://github.com/jentic/jentic-public-apis/commit/f4ae620048153700fac56ab72293420d027a94a9) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27435)](https://github.com/jentic/jentic-public-apis/commit/ed19b8c95f053ea1fa77d4396fc2c35a81e95de8) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27434)](https://github.com/jentic/jentic-public-apis/commit/2e827ac332b95803e894d8287358c2ff108a2f7b) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27433)](https://github.com/jentic/jentic-public-apis/commit/d1b6f903c16d501014f616b3b4956711fb5a16f5) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27432)](https://github.com/jentic/jentic-public-apis/commit/e3e490598f7e77fcb2bdd32283e9a9b63aa96b42) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27430)](https://github.com/jentic/jentic-public-apis/commit/a8bc3c1806746cff9a9d8ed09b7c33c4a8dd9f2d) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27429)](https://github.com/jentic/jentic-public-apis/commit/054d04c6cc136ada02e0af595462c69a6256b83a) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 82 more in this group (see All events table)

### Conformance / schema validation (85)

- `2026-10-15` [openapi.tools checksum 41b1717278c2](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26366 (#26369)](https://github.com/jentic/jentic-public-apis/commit/014ab8abeb694f609c137a7063c5aa4c44ee3eed) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26361 (#26364)](https://github.com/jentic/jentic-public-apis/commit/f675f42702328382ea84c2f061f9f19ef3af5ac2) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26356 (#26360)](https://github.com/jentic/jentic-public-apis/commit/58793842cb7eb8a5e0e6bd735802512fd3c776f3) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26352 (#26355)](https://github.com/jentic/jentic-public-apis/commit/08aa53e553d63eaa8e45ed2067be98cc1181bd3d) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26350 (#26353)](https://github.com/jentic/jentic-public-apis/commit/3150d936c07db6843dc46db5fb6d6f5b86900eea) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26347 (#26349)](https://github.com/jentic/jentic-public-apis/commit/8e4b7e8eac5268eb8f9d696fe0eb7692e90d6275) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-10-15` [feat: Import OpenAPI spec from Issue #26340 (#26343)](https://github.com/jentic/jentic-public-apis/commit/b108a409ad4c7a523442453fbb3d3d0a343535e7) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 77 more in this group (see All events table)

### Dependency maintenance (55)

- `2026-10-15` [chore(deps-dev): bump mocha-chai-jest-snapshot to 1.1.8 with Jest 30 snapshot headers](https://github.com/usearazzo/arazzo-toolkit/issues/242) — `usearazzo/arazzo-toolkit` · `issue` · _depbump_
- `2026-10-15` [chore(deps-dev): bump mocha-chai-jest-snapshot from 1.1.7 to 1.1.8](https://github.com/usearazzo/arazzo-toolkit/pull/235) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-10-15` [Merge pull request #147 from OAI/dependabot/github_actions/ruby/setup-ruby-1.329.0](https://github.com/OAI/spec.openapis.org/commit/a2287d17dbed41eb3422d5001f9811cabd204c06) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-15` [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/commit/023afb610737f0963c0931f74243681f1407de00) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-10-15` [chore(deps-dev): bump vitest from 5.0.2 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/Arazzo-Specification/pull/600) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-10-14` [build(deps): bump vitest from 4.0.17 to 5.0.3 in the vitest group across 1 directory](https://github.com/OAI/learn.openapis.org/pull/190) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-13` [build(deps): bump prettier from 3.9.9 to 3.9.10](https://github.com/OAI/learn.openapis.org/pull/219) — `OAI/learn.openapis.org` · `pr` · _depbump_
- `2026-10-13` [Merge pull request #146 from OAI/dependabot/github_actions/ruby/setup-ruby-1.328.0](https://github.com/OAI/spec.openapis.org/commit/76377bc7d66a96273e451be8940fc05b774ffbb8) — `OAI/spec.openapis.org` · `commit` · _depbump_
- … and 47 more in this group (see All events table)

### P2-1 CLI binary (34)

- `2026-10-15` [feat(document): implement Arazzo document bundling API and CLI command](https://github.com/Mohammed-Alama/php-arazzo/issues/84) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,spec_
- `2026-10-15` [\[Epic\] Interactive Visual Workflow Viewer & Standalone HTML Renderer](https://github.com/Mohammed-Alama/php-arazzo/issues/89) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,spec_
- `2026-10-15` [\[Epic\] Deterministic OpenAPI-to-Arazzo Heuristic Workflow Generator](https://github.com/Mohammed-Alama/php-arazzo/issues/88) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,spec_
- `2026-10-13` [chore: stop tracking bug-hunt-results.md](https://github.com/strefethen/arazzo-cli/commit/038dc83bf5d8e1961c11e01b58ddaaf8da07d135) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-13` [fix(deps): patch quinn-proto and refresh debugger docs](https://github.com/strefethen/arazzo-cli/commit/47ddf1c203e7a94d688bbb2f68b986eebc3aa79a) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-13` [docs: clarify contribution policy](https://github.com/strefethen/arazzo-cli/commit/eb9c323e651c88dce6b67502b95310c0fbe30498) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-10-10` [runner: record criterion diagnostics and fail unavailable criteria softly](https://github.com/usearazzo/arazzo-toolkit/issues/231) — `usearazzo/arazzo-toolkit` · `issue` · _cli,runner,spec_
- `2026-10-10` [fix(expr): refuse JSONPath queries nested deeper than 8 brackets](https://github.com/strefethen/arazzo-cli/commit/af6ee9df1b1d15e0b00e8d171d072dc37b70c045) — `strefethen/arazzo-cli` · `commit` · _cli_
- … and 26 more in this group (see All events table)

### P1-7 JSON Schema layer (14)

- `2026-10-14` [Bump the hyperjump group with 2 updates](https://github.com/OAI/build-infra/pull/69) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-10-12` [Merge pull request #594 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.18.0](https://github.com/OAI/Arazzo-Specification/commit/79ddb24a832ea35d2d11e5674c40fd4f63ab906e) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-12` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.18.0](https://github.com/OAI/Arazzo-Specification/commit/3fe1737618afed353f346e8086a32716b44194ed) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-10-11` [Validate Arazzo Documents From the Command Line](https://usearazzo.com/blog/validate-arazzo-documents-from-the-command-line/) — `usearazzo/website.feed` · `article` · _cli,schema,spec_
- `2026-10-09` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.8 to 1.17.9](https://github.com/OAI/Arazzo-Specification/pull/593) — `OAI/Arazzo-Specification` · `pr` · _schema,depbump_
- `2026-10-08` [JSON Schema for OpenAPI YAML document (in VS Code)](https://github.com/OAI/learn.openapis.org/issues/216) — `OAI/learn.openapis.org` · `issue` · _schema,spec_
- `2026-10-08` [build(deps): bump @hyperjump/json-schema from 1.17.9 to 1.18.0](https://github.com/OAI/learn.openapis.org/pull/217) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- `2026-10-06` [build(deps): bump @hyperjump/json-schema from 1.17.3 to 1.17.5](https://github.com/OAI/learn.openapis.org/pull/193) — `OAI/learn.openapis.org` · `pr` · _schema,depbump_
- … and 6 more in this group (see All events table)

### API security (OAI sig-security) (13)

- `2026-10-02` [Auto-merge deletes the entire src tree](https://github.com/OAI/sig-security/issues/56) — `OAI/sig-security` · `issue` · _security_
- `2026-09-25` [feat: Add proposal for Security Specification](https://github.com/OAI/sig-security/issues/53) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-09-24` [Support for GNAP](https://github.com/OAI/sig-security/issues/9) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- … and 5 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (12)

- `2026-10-15` [feat(sources): OpenAPI security requirement injection (split from #23)](https://github.com/Mohammed-Alama/php-arazzo/issues/104) — `Mohammed-Alama/php-arazzo` · `issue` · _human,security,spec_
- `2026-10-15` [test: extract conformance fixtures to reusable package (split from #27)](https://github.com/Mohammed-Alama/php-arazzo/issues/101) — `Mohammed-Alama/php-arazzo` · `issue` · _human,loop,spec_
- `2026-10-14` [Permit multiple externalDocs](https://github.com/OAI/OpenAPI-Specification/issues/1034) — `OAI/OpenAPI-Specification` · `issue` · _actor_
- `2026-10-05` [Bump jmertic/lfx-landscape-tools from 20260923 to 20260928 in the all group (#224)](https://github.com/OAI/landscape/commit/b0333d40ca0d3a14be3c29b5e51105a3541a7ada) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-28` [Bump jmertic/lfx-landscape-tools from 20260916 to 20260923 in the all group (#217)](https://github.com/OAI/landscape/commit/9a4f7b0a15a7c053b56271599292151e873b3159) — `OAI/landscape` · `commit` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website.ecosystem.atom` · `commit` · _actor_
- … and 4 more in this group (see All events table)

### Arazzo runner / step execution (10)

- `2026-10-15` [runner: opt-in e2e petstore suite](https://github.com/usearazzo/arazzo-toolkit/issues/79) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-15` [\[Epic\] Runner execution semantics & cross-document references](https://github.com/Mohammed-Alama/php-arazzo/issues/137) — `Mohammed-Alama/php-arazzo` · `issue` · _loop,runner,spec_
- `2026-10-15` [feat(runner): preflight validate workflow outputs shape before prerequisite/step execution](https://github.com/Mohammed-Alama/php-arazzo/issues/80) — `Mohammed-Alama/php-arazzo` · `issue` · _runner,spec_
- `2026-10-12` [runner: CommonJS entry fails to load — missing vendor/swagger-client.cjs](https://github.com/usearazzo/arazzo-toolkit/issues/234) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-10` [runner: response context and $outputs for steps that call a workflow](https://github.com/usearazzo/arazzo-toolkit/issues/228) — `usearazzo/arazzo-toolkit` · `issue` · _loop,runner,spec_
- `2026-10-10` [runner: embedded runtime expressions in requestBody payloads are sent verbatim (spec MUST, 1.0.1)](https://github.com/usearazzo/arazzo-toolkit/issues/129) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-10-02` [Clarify how to read outputs of a workflow run by a `retry` failure action](https://github.com/OAI/Arazzo-Specification/issues/590) — `OAI/Arazzo-Specification` · `issue` · _runner,spec_
- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
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

- `2026-10-09` [test(mcp): bound the .env subprocess wait and pin its stderr](https://github.com/strefethen/arazzo-cli/commit/57ca935392c24f05007f5113fd3c2634f0ef1497) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-10-09` [fix(cli,mcp): stop reading .env at the first hard I/O error](https://github.com/strefethen/arazzo-cli/commit/532b3ccce6d73018bc85c2bc8a20f7070200a186) — `strefethen/arazzo-cli` · `commit` · _mcp,cli_
- `2026-09-17` [feat(ecosystem): add 26 repositories from the GitHub "arazzo" search](https://github.com/usearazzo/website/commit/0060622a797c8922444b6d7e27416dce06fff576) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp,spec_
- `2026-09-13` [Align lifecycle.md with SAF framing, using discussion #5 example data](https://github.com/OAI/sig-lifecycle/pull/15) — `OAI/sig-lifecycle` · `pr` · _mcp,spec_
- `2026-09-10` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_

### P0-6 source routing (wsdl type) (6)

- `2026-10-15` [feat: Canonical Execution Core — unify sync/queue engines (Spec 1)](https://github.com/Mohammed-Alama/php-arazzo/issues/22) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,cli,spec_
- `2026-10-15` [feat: 2020-12 JSON Schema validation (P0-1) — investigate + upgrade](https://github.com/Mohammed-Alama/php-arazzo/issues/20) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,human,schema,spec_
- `2026-10-15` [feat: ExecutionLoop Autonomy (P0-2) — human confirmation + circuit breaker](https://github.com/Mohammed-Alama/php-arazzo/issues/19) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,human,runner,spec_
- `2026-10-15` [feat: Priority-ordered Arazzo file parsing (P2-7)](https://github.com/Mohammed-Alama/php-arazzo/issues/18) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,spec_
- `2026-10-15` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,actor,spec_
- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_

### Roadmap A2A step type (5)

- `2026-10-15` [Rebuild apis.json, scores.json, and API browsing indexes (#27437)](https://github.com/jentic/jentic-public-apis/commit/5f37537fc154253a59d83290c87f77f0aa2aac97) — `jentic/jentic-public-apis` · `commit` · _a2a_
- `2026-10-14` [Bump the vitest group with 3 updates](https://github.com/OAI/build-infra/pull/67) — `OAI/build-infra` · `pr` · _actor,a2a,depbump_
- `2026-10-14` [Bump respec from 37.4.0 to 37.4.1 in the publishing group](https://github.com/OAI/build-infra/pull/68) — `OAI/build-infra` · `pr` · _a2a,depbump_
- `2026-10-06` [build(deps): bump jekyll-remote-theme from 0.4.3 to 0.6.2](https://github.com/OAI/learn.openapis.org/pull/210) — `OAI/learn.openapis.org` · `pr` · _a2a,depbump_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_

### Issue #410 loops vs goto (4)

- `2026-10-15` [feat(runner,sources): support cross-document workflow references ($sourceDescriptions.{name}.{workflowId})](https://github.com/Mohammed-Alama/php-arazzo/issues/83) — `Mohammed-Alama/php-arazzo` · `issue` · _loop,spec_
- `2026-10-15` [feat(runner): implement retry action repair reference execution with budget accounting](https://github.com/Mohammed-Alama/php-arazzo/issues/82) — `Mohammed-Alama/php-arazzo` · `issue` · _loop,spec_
- `2026-10-15` [feat(runner): support step-level goto to workflowId as one-way transfer](https://github.com/Mohammed-Alama/php-arazzo/issues/81) — `Mohammed-Alama/php-arazzo` · `issue` · _loop,spec_
- `2026-10-11` [validator: track new validation and lint rules](https://github.com/usearazzo/arazzo-toolkit/issues/197) — `usearazzo/arazzo-toolkit` · `issue` · _loop,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-10-14` [Clarify when Criterion `context` is required](https://github.com/OAI/Arazzo-Specification/pull/499) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,spec_
- `2026-10-10` [fix(spec): specify ECMA-262 dialect for regex Criterion condition type](https://github.com/OAI/Arazzo-Specification/pull/516) — `OAI/Arazzo-Specification` · `pr` · _xml,xpath,schema,runner,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-10-15` [fix(ci): PHPMD silently skips PHP 8.4 files — upgrade to phpmd ^3.0 + clean up CI annotations](https://github.com/Mohammed-Alama/php-arazzo/issues/78) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Roadmap GraphQL step type (2)

- `2026-10-14` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### Roadmap gRPC step type (1)

- `2026-10-14` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-10-15 | usearazzo/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.1.0) |  | actionable |  |
| 2026-10-15 | usearazzo/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.1) |  | actionable |  |
| 2026-10-15 | usearazzo/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/usearazzo/Arazzo-Specification/releases/tag/1.0.0) |  | actionable |  |
| 2026-10-15 | openapi.tools | tool_collection | [openapi.tools checksum 41b1717278c2](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-10-15 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.6](https://github.com/speclynx/apidom/releases/tag/v6.0.6) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.5](https://github.com/speclynx/apidom/releases/tag/v6.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.4](https://github.com/speclynx/apidom/releases/tag/v6.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.3](https://github.com/speclynx/apidom/releases/tag/v6.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.2](https://github.com/speclynx/apidom/releases/tag/v6.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.1](https://github.com/speclynx/apidom/releases/tag/v6.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v6.0.0](https://github.com/speclynx/apidom/releases/tag/v6.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.7](https://github.com/speclynx/apidom/releases/tag/v5.2.7) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.3.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.3.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.55.3](https://github.com/Specmatic/specmatic/releases/tag/2.55.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.55.2](https://github.com/Specmatic/specmatic/releases/tag/2.55.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.55.1](https://github.com/Specmatic/specmatic/releases/tag/2.55.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.55.0](https://github.com/Specmatic/specmatic/releases/tag/2.55.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.7](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.7) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.8.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.8.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.8.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.8.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.7](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.7) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.6](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.6) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.5](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump mocha-chai-jest-snapshot from 1.1.7 to 1.1.8](https://github.com/usearazzo/arazzo-toolkit/pull/243) | breaking, depbump | breaking | Dependency maintenance |
| 2026-10-15 | usearazzo/arazzo-toolkit | issue | [chore(deps-dev): bump mocha-chai-jest-snapshot to 1.1.8 with Jest 30 snapshot headers](https://github.com/usearazzo/arazzo-toolkit/issues/242) | depbump | watch | Dependency maintenance |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump mocha-chai-jest-snapshot from 1.1.7 to 1.1.8](https://github.com/usearazzo/arazzo-toolkit/pull/235) | depbump | watch | Dependency maintenance |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27438)](https://github.com/jentic/jentic-public-apis/commit/e4f184ad45f1944d0e50e3e63e64ab1637b085ea) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26366 (#26369)](https://github.com/jentic/jentic-public-apis/commit/014ab8abeb694f609c137a7063c5aa4c44ee3eed) | spec | watch | Conformance / schema validation |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump github/codeql-action from 4.38.2 to 4.38.3](https://github.com/usearazzo/arazzo-toolkit/pull/238) | cli, breaking, depbump | breaking | P2-1 CLI binary |
| 2026-10-15 | usearazzo/arazzo-toolkit | pr | [feat(runner): run petstore workflows e2e and publish the runner](https://github.com/usearazzo/arazzo-toolkit/pull/233) | actor, spec | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-10-15 | usearazzo/arazzo-toolkit | issue | [runner: opt-in e2e petstore suite](https://github.com/usearazzo/arazzo-toolkit/issues/79) | runner, spec | watch | Arazzo runner / step execution |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27437)](https://github.com/jentic/jentic-public-apis/commit/5f37537fc154253a59d83290c87f77f0aa2aac97) | a2a | watch | Roadmap A2A step type |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26361 (#26364)](https://github.com/jentic/jentic-public-apis/commit/f675f42702328382ea84c2f061f9f19ef3af5ac2) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27436)](https://github.com/jentic/jentic-public-apis/commit/f4ae620048153700fac56ab72293420d027a94a9) |  | watch |  |
| 2026-10-15 | speclynx/apidom | release | [v6.0.6](https://github.com/speclynx/apidom/releases/tag/v6.0.6) | a2a, spec | actionable | Roadmap A2A step type |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26356 (#26360)](https://github.com/jentic/jentic-public-apis/commit/58793842cb7eb8a5e0e6bd735802512fd3c776f3) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27435)](https://github.com/jentic/jentic-public-apis/commit/ed19b8c95f053ea1fa77d4396fc2c35a81e95de8) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26352 (#26355)](https://github.com/jentic/jentic-public-apis/commit/08aa53e553d63eaa8e45ed2067be98cc1181bd3d) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27434)](https://github.com/jentic/jentic-public-apis/commit/2e827ac332b95803e894d8287358c2ff108a2f7b) |  | watch |  |
| 2026-10-15 | speclynx/apidom | release | [v6.0.5](https://github.com/speclynx/apidom/releases/tag/v6.0.5) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26350 (#26353)](https://github.com/jentic/jentic-public-apis/commit/3150d936c07db6843dc46db5fb6d6f5b86900eea) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27433)](https://github.com/jentic/jentic-public-apis/commit/d1b6f903c16d501014f616b3b4956711fb5a16f5) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26347 (#26349)](https://github.com/jentic/jentic-public-apis/commit/8e4b7e8eac5268eb8f9d696fe0eb7692e90d6275) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27432)](https://github.com/jentic/jentic-public-apis/commit/e3e490598f7e77fcb2bdd32283e9a9b63aa96b42) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26340 (#26343)](https://github.com/jentic/jentic-public-apis/commit/b108a409ad4c7a523442453fbb3d3d0a343535e7) | spec | watch | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | release | [v6.0.4](https://github.com/speclynx/apidom/releases/tag/v6.0.4) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27430)](https://github.com/jentic/jentic-public-apis/commit/a8bc3c1806746cff9a9d8ed09b7c33c4a8dd9f2d) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26338 (#26341)](https://github.com/jentic/jentic-public-apis/commit/dd68c80e19c1d323daed6144e5391eeb6c976286) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27429)](https://github.com/jentic/jentic-public-apis/commit/054d04c6cc136ada02e0af595462c69a6256b83a) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26336 (#26339)](https://github.com/jentic/jentic-public-apis/commit/8f3fa409c1fd52e02616e1a316b9d6041394c508) | spec | watch | Conformance / schema validation |
| 2026-10-15 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#27428)](https://github.com/jentic/jentic-public-apis/commit/8ea701314530b1282a64de1579453835049a257f) |  | watch |  |
| 2026-10-15 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #26332 (#26334)](https://github.com/jentic/jentic-public-apis/commit/407bd62fa337785fe23bdf6bd88c4d5cc0971a7f) | spec | watch | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | release | [v6.0.3](https://github.com/speclynx/apidom/releases/tag/v6.0.3) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | release | [v6.0.2](https://github.com/speclynx/apidom/releases/tag/v6.0.2) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | spec.arazzo.html | spec_html_checksum | [spec.arazzo.html checksum 8e2ea7d20acc](https://spec.openapis.org/arazzo/latest.html) | spec | watch | Conformance / schema validation |
| 2026-10-15 | spec.arazzo.schema.1.1 | schema_checksum | [spec.arazzo.schema.1.1 checksum 37be908409bd](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15) | spec | watch | Conformance / schema validation |
| 2026-10-15 | spec.arazzo.schema.1.0 | schema_checksum | [spec.arazzo.schema.1.0 checksum b8715bd824ff](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15) | spec | watch | Conformance / schema validation |
| 2026-10-15 | OAI/spec.openapis.org | pr | [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/pull/147) | depbump | actionable | Dependency maintenance |
| 2026-10-15 | OAI/spec.openapis.org | commit | [Merge pull request #147 from OAI/dependabot/github_actions/ruby/setup-ruby-1.329.0](https://github.com/OAI/spec.openapis.org/commit/a2287d17dbed41eb3422d5001f9811cabd204c06) | depbump | watch | Dependency maintenance |
| 2026-10-15 | speclynx/apidom | release | [v6.0.1](https://github.com/speclynx/apidom/releases/tag/v6.0.1) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | speclynx/apidom | release | [v6.0.0](https://github.com/speclynx/apidom/releases/tag/v6.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-10-15 | OAI/spec.openapis.org | commit | [build(deps): bump ruby/setup-ruby from 1.328.0 to 1.329.0](https://github.com/OAI/spec.openapis.org/commit/023afb610737f0963c0931f74243681f1407de00) | depbump | watch | Dependency maintenance |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.64.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.64.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/respect-core@2.64.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.64.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/recheck@2.64.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/recheck%402.64.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.64.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.64.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/client-generator@0.7.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.7.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Redocly/redocly-cli | release | [@redocly/cli@2.64.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.64.0) | spec | actionable | Conformance / schema validation |
| 2026-10-15 | Mohammed-Alama/php-arazzo | pr | [docs(issue-intake): document the four-level hierarchy and the renamed kinds](https://github.com/Mohammed-Alama/php-arazzo/pull/138) |  | actionable |  |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [\[Package\] runner](https://github.com/Mohammed-Alama/php-arazzo/issues/132) |  | watch |  |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [\[Epic\] Runner execution semantics & cross-document references](https://github.com/Mohammed-Alama/php-arazzo/issues/137) | loop, runner, spec | watch | Arazzo runner / step execution |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat(document): implement Arazzo document bundling API and CLI command](https://github.com/Mohammed-Alama/php-arazzo/issues/84) | cli, spec | watch | P2-1 CLI binary |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat(runner,sources): support cross-document workflow references ($sourceDescriptions.{name}.{workflowId})](https://github.com/Mohammed-Alama/php-arazzo/issues/83) | loop, spec | watch | Issue #410 loops vs goto |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat(runner): implement retry action repair reference execution with budget accounting](https://github.com/Mohammed-Alama/php-arazzo/issues/82) | loop, spec | watch | Issue #410 loops vs goto |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat(runner): support step-level goto to workflowId as one-way transfer](https://github.com/Mohammed-Alama/php-arazzo/issues/81) | loop, spec | watch | Issue #410 loops vs goto |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat(runner): preflight validate workflow outputs shape before prerequisite/step execution](https://github.com/Mohammed-Alama/php-arazzo/issues/80) | runner, spec | watch | Arazzo runner / step execution |
| 2026-10-15 | Mohammed-Alama/php-arazzo | issue | [feat: Canonical Execution Core — unify sync/queue engines (Spec 1)](https://github.com/Mohammed-Alama/php-arazzo/issues/22) | soap, cli, spec | watch | P0-6 source routing (wsdl type) |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
