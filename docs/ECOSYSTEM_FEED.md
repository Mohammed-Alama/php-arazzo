# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-23T11:32:41+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 855 (showing 200 newest)
- **Severity:** breaking **40** · actionable **472** · watch **343**
- **Top relevance:** `Conformance / schema validation` (348) · `uncategorized` (147) · `Dependency maintenance` (101) · `P2-1 CLI binary` (79) · `P1-7 JSON Schema layer` (32)
- **Top sources:** `strefethen/arazzo-cli` (54) · `OAI/Arazzo-Specification` (51) · `OAI/build-infra` (42) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Dependency maintenance (10)

- `2026-09-19` [chore(deps): bump @speclynx/apidom-* packages to 5.2.6](https://github.com/usearazzo/arazzo-toolkit/pull/171) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-09-18` [fix(resolver): bump apidom to 5.2.5 to rebase bundled schema $ref](https://github.com/usearazzo/arazzo-toolkit/pull/167) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-09-11` [Bump build-infra to 1.0.2](https://github.com/OAI/OpenAPI-Specification/pull/5552) — `OAI/OpenAPI-Specification` · `pr` · _breaking,depbump_
- `2026-09-09` [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/40) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-09-07` [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/569) — `OAI/Arazzo-Specification` · `pr` · _breaking,depbump_
- `2026-08-03` [build(deps-dev): bump jekyll-include-cache from 0.2.1 to 0.2.2](https://github.com/OAI/spec.openapis.org/pull/128) — `OAI/spec.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- … and 2 more in this group (see All events table)

### Conformance / schema validation (8)

- `2026-09-23` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-11` [Tweak OpenAPI Spec rendering for new build](https://github.com/OAI/build-infra/pull/45) — `OAI/build-infra` · `pr` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-08-11` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-01-23` [v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_

### P1-7 JSON Schema layer (5)

- `2026-09-20` [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) — `OAI/Arazzo-Specification` · `issue` · _mcp,cli,human,loop,breaking,schema,runner,spec_
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

### P2-1 CLI binary (3)

- `2026-09-22` [chore(deps-dev): bump @commitlint/cli from 21.2.2 to 21.2.3](https://github.com/usearazzo/arazzo-toolkit/pull/179) — `usearazzo/arazzo-toolkit` · `pr` · _cli,actor,breaking,depbump_
- `2026-09-22` [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) — `Mohammed-Alama/php-arazzo` · `pr` · _cli,actor,breaking_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_

### P0-6 source routing (wsdl type) (2)

- `2026-09-16` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_
- `2026-09-06` [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,breaking,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### Potential breaking change (2.0) (2)

- `2026-09-10` [If there's a 2.0 spec, add it (doesn't match 3-digit semVer)](https://github.com/OAI/build-infra/commit/45b5076e6c94835f02c3adb4432cfc9cb87dc7b8) — `OAI/build-infra` · `commit` · _breaking_
- `2026-09-06` [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking_

### Arazzo runner / step execution (1)

- `2026-09-09` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/42) — `OAI/build-infra` · `pr` · _actor,breaking,runner,depbump_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_


## Actionable — new releases/tags to review

### Conformance / schema validation (244)

- `2026-09-23` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-23` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-23` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-23` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-23` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-23` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-23` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-23` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 236 more in this group (see All events table)

### uncategorized (56)

- `2026-09-22` [Reinstate original participate link ordering](https://github.com/OAI/Overlay-Specification/pull/403) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-22` [1.2-dev refresh from dev](https://github.com/OAI/Overlay-Specification/pull/400) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-22` [refresh dev from main](https://github.com/OAI/Overlay-Specification/pull/399) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-22` [ci: updates the infra package](https://github.com/OAI/Overlay-Specification/pull/398) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-11` [Use the version placeholder in the commit history link](https://github.com/OAI/OpenAPI-Specification/pull/5551) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-11` [docs: adds precision around which branch to start from for updates](https://github.com/OAI/build-infra/pull/44) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-10` [fix json punctuation](https://github.com/OAI/spec.openapis.org/pull/130) — `OAI/spec.openapis.org` · `pr` · _no tags_
- `2026-09-10` [Update examples to use 3.2.1](https://github.com/OAI/OpenAPI-Specification/pull/5548) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- … and 48 more in this group (see All events table)

### P2-1 CLI binary (50)

- `2026-09-23` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-23` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 42 more in this group (see All events table)

### Dependency maintenance (39)

- `2026-09-22` [chore(deps-dev): bump @types/node from 26.6.1 to 26.6.2](https://github.com/usearazzo/arazzo-toolkit/pull/178) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-21` [chore(deps-dev): bump @babel/preset-env from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/174) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-21` [chore(deps-dev): bump eslint from 10.10.0 to 10.11.0](https://github.com/usearazzo/arazzo-toolkit/pull/176) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-21` [chore(deps): bump @babel/runtime-corejs3 from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/172) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-21` [build(deps): bump ruby/setup-ruby from 1.323.0 to 1.324.0](https://github.com/OAI/spec.openapis.org/pull/137) — `OAI/spec.openapis.org` · `pr` · _depbump_
- `2026-09-19` [v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) — `usearazzo/arazzo-toolkit` · `release` · _depbump_
- `2026-09-18` [chore(deps-dev): bump @types/node from 26.5.1 to 26.6.1](https://github.com/usearazzo/arazzo-toolkit/pull/170) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-18` [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/pull/136) — `OAI/spec.openapis.org` · `pr` · _depbump_
- … and 31 more in this group (see All events table)

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

### P1-7 JSON Schema layer (16)

- `2026-09-21` [v1.25.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-09-17` [Bump @hyperjump/json-schema-coverage from 1.2.1 to 1.2.2 in the hyperjump group](https://github.com/OAI/build-infra/pull/47) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,depbump_
- `2026-08-06` [v1.24.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.24.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-08-04` [v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) — `speclynx/apidom` · `release` · _schema,spec_
- `2026-06-19` [v1.23.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-06-01` [v1.23.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-04-08` [v1.23.0](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.0) — `speakeasy-api/openapi` · `release` · _cli,schema,security,depbump_
- … and 8 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (14)

- `2026-09-17` [chore(deps-dev): bump @babel/core from 8.0.1 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/164) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-17` [chore(deps-dev): bump @babel/preset-env from 8.0.2 to 8.0.5](https://github.com/usearazzo/arazzo-toolkit/pull/165) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-17` [chore(deps-dev): bump webpack from 5.110.3 to 5.111.0](https://github.com/usearazzo/arazzo-toolkit/pull/166) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-16` [chore(deps): bump respec from 37.3.6 to 37.4.0](https://github.com/OAI/Arazzo-Specification/pull/575) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- … and 6 more in this group (see All events table)

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

### Roadmap A2A step type (4)

- `2026-09-22` [chore(deps-dev): bump prettier from 3.9.6 to 3.9.8](https://github.com/usearazzo/arazzo-toolkit/pull/177) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-09-16` [chore(deps-dev): bump mocha from 12.0.0 to 12.0.1](https://github.com/usearazzo/arazzo-toolkit/pull/163) — `usearazzo/arazzo-toolkit` · `pr` · _a2a,depbump_
- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### Issue #410 loops vs goto (3)

- `2026-09-17` [@redocly/respect-core@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-17` [@redocly/cli@2.53.3](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.53.3) — `Redocly/redocly-cli` · `release` · _loop,spec_
- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_

### P0-6 source routing (wsdl type) (3)

- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_


## Watch — context (commits/issues/checksums)

### Conformance / schema validation (96)

- `2026-09-23` [openapi.tools checksum 986a885fa349](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-22` [docs(notes): record the resolving guide, the tutorial test, and the guide rule](https://github.com/usearazzo/website/commit/1070f4e2e77d896c4481e927e7fae0184bf92121) — `usearazzo/website` · `commit` · _spec_
- `2026-09-22` [fix(a11y): mark the nav and footer logos decorative](https://github.com/usearazzo/website/commit/c3b95b56d39ff57e731e7b09d06859c2adf459d3) — `usearazzo/website` · `commit` · _spec_
- `2026-09-22` [Overlay - update ReSpec-rendered specification versions](https://github.com/OAI/spec.openapis.org/pull/138) — `OAI/spec.openapis.org` · `pr` · _spec_
- `2026-09-22` [fix: removes duplicate abstract now covered by config](https://github.com/OAI/Overlay-Specification/pull/404) — `OAI/Overlay-Specification` · `pr` · _spec_
- `2026-09-22` [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) — `OAI/OpenAPI-Specification` · `issue` · _spec_
- `2026-09-22` [VOTE: Approve release of OpenAPI Overlay Specification v1.2](https://github.com/OAI/Overlay-Specification/issues/392) — `OAI/Overlay-Specification` · `issue` · _spec_
- `2026-09-22` [Branching strategy in CONTRIBUTING.md is outdated (I think)](https://github.com/OAI/Overlay-Specification/issues/393) — `OAI/Overlay-Specification` · `issue` · _spec_
- … and 88 more in this group (see All events table)

### uncategorized (91)

- `2026-09-23` [Rebuild apis.json, scores.json, and API browsing indexes (#24669)](https://github.com/jentic/jentic-public-apis/commit/8f59606969aa9ca6dedfd4c473f8db70a789f6b3) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-22` [Update Landscape from LFX 2026-09-22 (#212)](https://github.com/OAI/landscape/commit/e4e37987fa4c9f9865e4990c00251ca9332753bf) — `OAI/landscape` · `commit` · _no tags_
- `2026-09-22` [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-22` [DO NOT MERGE - release notes for 1.2](https://github.com/OAI/Overlay-Specification/pull/378) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-22` [Rebuild apis.json, scores.json, and API browsing indexes (#24668)](https://github.com/jentic/jentic-public-apis/commit/594b8fe6cdf5aea8c0f074e564cdcef08dfb597c) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-22` [Rebuild apis.json, scores.json, and API browsing indexes (#24666)](https://github.com/jentic/jentic-public-apis/commit/b447ac00c292a3f5c119cfb979ffe99cdd455df0) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-22` [Update Landscape from LFX 2026-09-22 (#211)](https://github.com/OAI/landscape/commit/11c1b7bd16edd6be380062f84bd7f46810e4ada4) — `OAI/landscape` · `commit` · _no tags_
- `2026-09-21` [Rebuild apis.json, scores.json, and API browsing indexes (#24663)](https://github.com/jentic/jentic-public-apis/commit/fc54a0cf750a56baded9eba21f3bc0c9834cb523) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 83 more in this group (see All events table)

### Dependency maintenance (52)

- `2026-09-23` [chore(deps-dev): bump vitest from 5.0.0 to 5.0.1 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/583) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-09-22` [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/55) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-21` [chore(deps): bump github/codeql-action from 4.38.0 to 4.38.1](https://github.com/usearazzo/arazzo-toolkit/pull/175) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-21` [Bump content-type from 3.1.0 to 3.1.1](https://github.com/OAI/build-infra/pull/56) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-21` [Merge pull request #137 from OAI/dependabot/github_actions/ruby/setup-ruby-1.324.0](https://github.com/OAI/spec.openapis.org/commit/bbe5afa0f86d16e141a7e8e3c7b66579d951990f) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-09-21` [build(deps): bump ruby/setup-ruby from 1.323.0 to 1.324.0](https://github.com/OAI/spec.openapis.org/commit/b3990a5020f96bb554eb97bb77c0ab50d298ac2e) — `OAI/spec.openapis.org` · `commit` · _depbump_
- `2026-09-21` [Bump jmertic/lfx-landscape-tools from 20260826 to 20260916 in the all group (#210)](https://github.com/OAI/landscape/commit/813dc1754d368ed686801cb9cda666332b4bb18e) — `OAI/landscape` · `commit` · _depbump_
- `2026-09-18` [build(deps): bump ruby/setup-ruby from 1.322.0 to 1.323.0](https://github.com/OAI/spec.openapis.org/commit/d60a34e5f3564d40bd5e65f88c16789a91c9d413) — `OAI/spec.openapis.org` · `commit` · _depbump_
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

### Issue #410 kind discriminator / human-in-loop (14)

- `2026-09-22` [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-19` [chore(deps): bump @speclynx/apidom-ls from 2.12.0 to 2.13.0](https://github.com/usearazzo/arazzo-toolkit/pull/168) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-13` [Enhance lifecycle.md with abstract and version info](https://github.com/OAI/sig-lifecycle/pull/3) — `OAI/sig-lifecycle` · `pr` · _actor_
- `2026-09-08` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- … and 6 more in this group (see All events table)

### P1-7 JSON Schema layer (11)

- `2026-09-22` [docs(guides): add the Resolving Arazzo Documents guide](https://github.com/usearazzo/website/commit/e31c935722658f058637ba7410cf1f77d58136ee) — `usearazzo/website` · `commit` · _schema,spec_
- `2026-09-18` [resolver: bundleArazzo leaves $ref unresolvable when the embedded schema's $id differs from its retrieval URI](https://github.com/usearazzo/arazzo-toolkit/issues/158) — `usearazzo/arazzo-toolkit` · `issue` · _schema,depbump_
- `2026-09-17` [Merge pull request #47 from OAI/dependabot/npm_and_yarn/hyperjump-ad3e30104b](https://github.com/OAI/build-infra/commit/177ff67ec793e93ea0a9c84121c45384b960da30) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-11` [Bump @hyperjump/json-schema-coverage in the hyperjump group](https://github.com/OAI/build-infra/commit/eb5cc236ffda832ddc0e2c18a7c25139dc95df42) — `OAI/build-infra` · `commit` · _schema,depbump_
- `2026-09-06` [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,schema,spec_
- `2026-08-31` [Bump jmertic/lfx-landscape-tools from 20260625 to 20260826 in the all group (#193)](https://github.com/OAI/landscape/commit/8c128a7b3f32ff3b50815246017cb9d651ab88bf) — `OAI/landscape` · `commit` · _schema,depbump_
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

### Arazzo runner / step execution (3)

- `2026-09-16` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P0-6 source routing (wsdl type) (3)

- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the September sweep](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website` · `commit` · _soap,mcp,schema,spec_
- `2026-09-17` [feat(ecosystem): add tools, articles, videos, and an example from the…](https://github.com/usearazzo/website/commit/42c42e4b06aad28e685769498dd9694617f51450) — `usearazzo/website.ecosystem.atom` · `commit` · _soap,mcp,schema,spec_
- `2026-09-06` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### Roadmap A2A step type (3)

- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website` · `commit` · _a2a,spec_
- `2026-09-18` [feat(ecosystem): add JArazzo Java models library](https://github.com/usearazzo/website/commit/77492b26f44bd210b3e2b19f08bd7e26fc9a2a3d) — `usearazzo/website.ecosystem.atom` · `commit` · _a2a,spec_
- `2026-09-18` [Merge pull request #136 from OAI/dependabot/github_actions/ruby/setup-ruby-1.323.0](https://github.com/OAI/spec.openapis.org/commit/06adacc41e9ce9a37686b0ca2ab8551346cb8a55) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Roadmap GraphQL step type (2)

- `2026-09-16` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### Roadmap gRPC step type (1)

- `2026-09-03` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-23 | openapi.tools | tool_collection | [openapi.tools checksum 986a885fa349](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-23 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.6](https://github.com/speclynx/apidom/releases/tag/v5.2.6) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.5](https://github.com/speclynx/apidom/releases/tag/v5.2.5) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.4](https://github.com/speclynx/apidom/releases/tag/v5.2.4) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.3](https://github.com/speclynx/apidom/releases/tag/v5.2.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.7.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.7.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.6.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.4](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.4) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.3](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.3) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-23 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24669)](https://github.com/jentic/jentic-public-apis/commit/8f59606969aa9ca6dedfd4c473f8db70a789f6b3) |  | watch |  |
| 2026-09-23 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 5.0.0 to 5.0.1 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/583) | depbump | watch | Dependency maintenance |
| 2026-09-22 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-22 (#212)](https://github.com/OAI/landscape/commit/e4e37987fa4c9f9865e4990c00251ca9332753bf) |  | watch |  |
| 2026-09-22 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @types/node from 26.6.1 to 26.6.2](https://github.com/usearazzo/arazzo-toolkit/pull/178) | depbump | actionable | Dependency maintenance |
| 2026-09-22 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @commitlint/cli from 21.2.2 to 21.2.3](https://github.com/usearazzo/arazzo-toolkit/pull/179) | cli, actor, breaking, depbump | breaking | P2-1 CLI binary |
| 2026-09-22 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump prettier from 3.9.6 to 3.9.8](https://github.com/usearazzo/arazzo-toolkit/pull/177) | a2a, depbump | actionable | Roadmap A2A step type |
| 2026-09-22 | OAI/build-infra | pr | [Bump the vitest group with 2 updates](https://github.com/OAI/build-infra/pull/55) | depbump | watch | Dependency maintenance |
| 2026-09-22 | OAI/build-infra | pr | [Bump respec from 37.3.6 to 37.4.0 in the publishing group across 1 directory](https://github.com/OAI/build-infra/pull/50) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-22 | usearazzo/website | commit | [docs(notes): record the resolving guide, the tutorial test, and the guide rule](https://github.com/usearazzo/website/commit/1070f4e2e77d896c4481e927e7fae0184bf92121) | spec | watch | Conformance / schema validation |
| 2026-09-22 | usearazzo/website | commit | [fix(a11y): mark the nav and footer logos decorative](https://github.com/usearazzo/website/commit/c3b95b56d39ff57e731e7b09d06859c2adf459d3) | spec | watch | Conformance / schema validation |
| 2026-09-22 | usearazzo/website | commit | [docs(guides): add the Resolving Arazzo Documents guide](https://github.com/usearazzo/website/commit/e31c935722658f058637ba7410cf1f77d58136ee) | schema, spec | watch | P1-7 JSON Schema layer |
| 2026-09-22 | OAI/spec.openapis.org | pr | [Overlay - update ReSpec-rendered specification versions](https://github.com/OAI/spec.openapis.org/pull/138) | spec | watch | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [fix: removes duplicate abstract now covered by config](https://github.com/OAI/Overlay-Specification/pull/404) | spec | watch | Conformance / schema validation |
| 2026-09-22 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 24 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5554) | spec | watch | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [Reinstate original participate link ordering](https://github.com/OAI/Overlay-Specification/pull/403) |  | actionable |  |
| 2026-09-22 | OAI/OpenAPI-Specification | pr | [dev: sync with main](https://github.com/OAI/OpenAPI-Specification/pull/5550) |  | watch |  |
| 2026-09-22 | OAI/OpenAPI-Specification | pr | [Improve the instructions for specification releases](https://github.com/OAI/OpenAPI-Specification/pull/5553) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [Better spec config values](https://github.com/OAI/Overlay-Specification/pull/402) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [DO NOT MERGE - release notes for 1.2](https://github.com/OAI/Overlay-Specification/pull/378) |  | watch |  |
| 2026-09-22 | OAI/Overlay-Specification | release | [1.2.0](https://github.com/OAI/Overlay-Specification/releases/tag/1.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | OAI/learn.openapis.org | pr | [docs: adds an upgrade guide for overlay 1.2](https://github.com/OAI/learn.openapis.org/pull/206) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [Release v1.2.0](https://github.com/OAI/Overlay-Specification/pull/401) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | issue | [VOTE: Approve release of OpenAPI Overlay Specification v1.2](https://github.com/OAI/Overlay-Specification/issues/392) | spec | watch | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [1.2-dev refresh from dev](https://github.com/OAI/Overlay-Specification/pull/400) |  | actionable |  |
| 2026-09-22 | OAI/Overlay-Specification | pr | [refresh dev from main](https://github.com/OAI/Overlay-Specification/pull/399) |  | actionable |  |
| 2026-09-22 | OAI/Overlay-Specification | issue | [Branching strategy in CONTRIBUTING.md is outdated (I think)](https://github.com/OAI/Overlay-Specification/issues/393) | spec | watch | Conformance / schema validation |
| 2026-09-22 | OAI/Overlay-Specification | pr | [ci: updates the infra package](https://github.com/OAI/Overlay-Specification/pull/398) |  | actionable |  |
| 2026-09-22 | Redocly/redocly-cli | release | [@redocly/reunite-integration@2.54.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/reunite-integration%402.54.2) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | Redocly/redocly-cli | release | [@redocly/respect-core@2.54.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%402.54.2) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | Redocly/redocly-cli | release | [@redocly/openapi-core@2.54.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.54.2) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | Redocly/redocly-cli | release | [@redocly/client-generator@0.4.15](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/client-generator%400.4.15) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | Redocly/redocly-cli | release | [@redocly/cli@2.54.2](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.54.2) | spec | actionable | Conformance / schema validation |
| 2026-09-22 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24668)](https://github.com/jentic/jentic-public-apis/commit/594b8fe6cdf5aea8c0f074e564cdcef08dfb597c) |  | watch |  |
| 2026-09-22 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24664 (#24665)](https://github.com/jentic/jentic-public-apis/commit/7a2c33ba266bb7e8aaaee21d27d73dbcbb367457) | spec | watch | Conformance / schema validation |
| 2026-09-22 | Mohammed-Alama/php-arazzo | pr | [feat(contracts): Phase A — plugin SPIs, StepState, state repo, ResponseTransfer, Step decomposition](https://github.com/Mohammed-Alama/php-arazzo/pull/72) | cli, actor, breaking | breaking | P2-1 CLI binary |
| 2026-09-22 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24666)](https://github.com/jentic/jentic-public-apis/commit/b447ac00c292a3f5c119cfb979ffe99cdd455df0) |  | watch |  |
| 2026-09-22 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-22 (#211)](https://github.com/OAI/landscape/commit/11c1b7bd16edd6be380062f84bd7f46810e4ada4) |  | watch |  |
| 2026-09-21 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/preset-env from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/174) | depbump | actionable | Dependency maintenance |
| 2026-09-21 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump eslint from 10.10.0 to 10.11.0](https://github.com/usearazzo/arazzo-toolkit/pull/176) | depbump | actionable | Dependency maintenance |
| 2026-09-21 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump @babel/cli from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/173) | cli, depbump | actionable | P2-1 CLI binary |
| 2026-09-21 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump @babel/runtime-corejs3 from 8.0.5 to 8.0.6](https://github.com/usearazzo/arazzo-toolkit/pull/172) | depbump | actionable | Dependency maintenance |
| 2026-09-21 | usearazzo/arazzo-toolkit | pr | [chore(deps): bump github/codeql-action from 4.38.0 to 4.38.1](https://github.com/usearazzo/arazzo-toolkit/pull/175) | depbump | watch | Dependency maintenance |
| 2026-09-21 | OAI/build-infra | pr | [Bump content-type from 3.1.0 to 3.1.1](https://github.com/OAI/build-infra/pull/56) | depbump | watch | Dependency maintenance |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24663)](https://github.com/jentic/jentic-public-apis/commit/fc54a0cf750a56baded9eba21f3bc0c9834cb523) |  | watch |  |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24418 (#24421)](https://github.com/jentic/jentic-public-apis/commit/fe92ba3d07af3dbe5ac572223a905a0643d080eb) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24069 (#24072)](https://github.com/jentic/jentic-public-apis/commit/ca1f50020b48022f4ea4b8834945bc7d26ea0c2d) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24661)](https://github.com/jentic/jentic-public-apis/commit/7445c9786dba0082d6304095dd1d69785c2cc8b2) |  | watch |  |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22308 (#22310)](https://github.com/jentic/jentic-public-apis/commit/f52776c8378f2837c41f0ca09f53c21aec966ddf) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24003 (#24005)](https://github.com/jentic/jentic-public-apis/commit/b20ec7d1e2b1f363d13245750ab4b77f0062c53b) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24659)](https://github.com/jentic/jentic-public-apis/commit/3900562b99a01bbe25121b4ca735889800ecb4d2) |  | watch |  |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22156 (#22158)](https://github.com/jentic/jentic-public-apis/commit/1079a9db7abdb4c7191017bb185e96d5e735f4a7) | spec | watch | Conformance / schema validation |
| 2026-09-21 | usearazzo/website | commit | [feat(blog): add the @usearazzo/resolver release post](https://github.com/usearazzo/website/commit/f0bc4daf3251a326f4a24da8da5af741f7feeecb) | spec | watch | Conformance / schema validation |
| 2026-09-21 | OAI/learn.openapis.org | pr | [specification/security: fix of a typo on line 47](https://github.com/OAI/learn.openapis.org/pull/207) | security, spec | actionable | API security (OAI sig-security) |
| 2026-09-21 | usearazzo/website | commit | [docs(parser): document tolerant parsing, file extensions, criterion AST, and unparsed sources](https://github.com/usearazzo/website/commit/17308a5d85cf25ad167cc3ea7792f4d7a5f3239e) | spec | watch | Conformance / schema validation |
| 2026-09-21 | usearazzo/website | commit | [docs(guides): move the JavaScript parsing FAQ entry back to the guide](https://github.com/usearazzo/website/commit/826d856d5c4c1287c39675e3e6705cfb4cbbaa28) | spec | watch | Conformance / schema validation |
| 2026-09-21 | usearazzo/website | commit | [docs(blog): point the parser post at what the guide now covers](https://github.com/usearazzo/website/commit/42312c0b4e7466d33f3f6f7510ff902fac02591d) |  | watch |  |
| 2026-09-21 | usearazzo/website | commit | [docs(guides): make the parsing guide vendor-neutral](https://github.com/usearazzo/website/commit/cacf86bbb5ac864d359d6c6e8402d88fca723bde) | spec | watch | Conformance / schema validation |
| 2026-09-21 | usearazzo/website | commit | [feat(docs): add tree figures and numbered code blocks](https://github.com/usearazzo/website/commit/6a97725ea07977ac5753a1adb97536ef07898faf) |  | watch |  |
| 2026-09-21 | OAI/Arazzo-Specification | issue | [Define comparison semantics (numeric strings, cross-type ordering and equality) for simple Criterion Object conditions](https://github.com/OAI/Arazzo-Specification/issues/584) | spec | watch | Conformance / schema validation |
| 2026-09-21 | OAI/Arazzo-Specification | issue | [Provide a normative grammar and boundary rules for simple Criterion Object conditions](https://github.com/OAI/Arazzo-Specification/issues/518) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24658)](https://github.com/jentic/jentic-public-apis/commit/752f62496055f4b13e7e5f83656fbe4aa03cad1c) |  | watch |  |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24096 (#24099)](https://github.com/jentic/jentic-public-apis/commit/ac3ec4bebfcd2f10bddb9f998a291cf6d9735b6f) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24657)](https://github.com/jentic/jentic-public-apis/commit/a35a44cb2f70eeb5bf933563678dfa5469c0ef17) |  | watch |  |
| 2026-09-21 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #24159 (#24160)](https://github.com/jentic/jentic-public-apis/commit/ddf355b3ee2334987662193e34c91b8680af9a86) | spec | watch | Conformance / schema validation |
| 2026-09-21 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#24656)](https://github.com/jentic/jentic-public-apis/commit/98f30ca20b6c19ea5784ec4cfab68c7762cef276) |  | watch |  |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
