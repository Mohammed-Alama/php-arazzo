# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-07T12:29:05+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 841 (showing 200 newest)
- **Severity:** breaking **37** · actionable **452** · watch **352**
- **Top relevance:** `Conformance / schema validation` (330) · `uncategorized` (166) · `Dependency maintenance` (91) · `P2-1 CLI binary` (75) · `P1-7 JSON Schema layer` (38)
- **Top sources:** `OAI/Arazzo-Specification` (51) · `strefethen/arazzo-cli` (50) · `OAI/build-infra` (43) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (9)

- `2026-09-07` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-03` [fix(parser): distinguish shared source descriptions from cycles](https://github.com/usearazzo/arazzo-toolkit/pull/142) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-02` [parser: expose parsing interfaces for runtime expressions and criterion conditions](https://github.com/usearazzo/arazzo-toolkit/issues/99) — `usearazzo/arazzo-toolkit` · `issue` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-08-11` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- … and 1 more in this group (see All events table)

### Dependency maintenance (9)

- `2026-09-07` [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/569) — `OAI/Arazzo-Specification` · `pr` · _breaking,depbump_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-* dependencies to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/103) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-08-28` [Bump content-type from 2.0.0 to 3.0.0](https://github.com/OAI/build-infra/pull/28) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-08-24` [Bump content-type from 2.0.0 to 2.1.0](https://github.com/OAI/build-infra/pull/22) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-08-03` [build(deps-dev): bump jekyll-include-cache from 0.2.1 to 0.2.2](https://github.com/OAI/spec.openapis.org/pull/128) — `OAI/spec.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- … and 1 more in this group (see All events table)

### P1-7 JSON Schema layer (4)

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

- `2026-08-27` [Bump markdown-it from 14.2.0 to 15.0.0](https://github.com/OAI/OpenAPI-Specification/pull/5461) — `OAI/OpenAPI-Specification` · `pr` · _cli,breaking,depbump_
- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-03` [build(deps): bump markdown-it from 14.3.0 to 15.0.0](https://github.com/OAI/Overlay-Specification/pull/375) — `OAI/Overlay-Specification` · `pr` · _cli,breaking,depbump_

### P0-6 source routing (wsdl type) (2)

- `2026-09-06` [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,breaking,spec_
- `2026-09-01` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### Potential breaking change (2.0) (1)

- `2026-09-06` [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking_


## Actionable — new releases/tags to review

### Conformance / schema validation (231)

- `2026-09-07` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-07` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-07` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-07` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-07` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-07` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-07` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-07` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 223 more in this group (see All events table)

### P2-1 CLI binary (48)

- `2026-09-07` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-07` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 40 more in this group (see All events table)

### uncategorized (48)

- `2026-09-05` [Catch ReSpec errors, update ReSpec to a verison with a fallback API URL](https://github.com/OAI/build-infra/pull/38) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-03` [2026 07 25 core 38 event dispatcher wiring](https://github.com/Mohammed-Alama/php-arazzo/pull/5) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-09-03` [Align Architecture Namespaces to Official JS Toolkit](https://github.com/Mohammed-Alama/php-arazzo/pull/10) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-09-03` [fix: remove outdated deprecation notice on ExpressionResolverInterface](https://github.com/Mohammed-Alama/php-arazzo/pull/15) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-09-02` [Reverting recent dependabot changes](https://github.com/OAI/build-infra/pull/32) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-01` [Stage release changes during release branch adjustment](https://github.com/OAI/build-infra/pull/29) — `OAI/build-infra` · `pr` · _no tags_
- `2026-08-30` [chore: add CLAUDE.md for Claude Code guidance](https://github.com/OAI/tools.openapis.org/pull/288) — `OAI/tools.openapis.org` · `pr` · _no tags_
- `2026-08-27` [Avoid pinning transitive lockfile entries](https://github.com/OAI/build-infra/pull/15) — `OAI/build-infra` · `pr` · _no tags_
- … and 40 more in this group (see All events table)

### Dependency maintenance (38)

- `2026-09-04` [chore(deps-dev): bump webpack from 5.110.2 to 5.110.3](https://github.com/usearazzo/arazzo-toolkit/pull/145) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-03` [chore(deps): bump github/codeql-action from 4.37.8 to 4.37.9](https://github.com/usearazzo/arazzo-toolkit/pull/110) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-02` [chore(deps-dev): bump webpack from 5.110.1 to 5.110.2](https://github.com/usearazzo/arazzo-toolkit/pull/133) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-02` [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/34) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-01` [chore(deps-dev): bump webpack from 5.109.2 to 5.110.1](https://github.com/usearazzo/arazzo-toolkit/pull/132) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-01` [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/31) — `OAI/build-infra` · `pr` · _depbump_
- `2026-08-31` [chore(deps): bump markdown-it from 15.0.0 to 15.0.1](https://github.com/OAI/Arazzo-Specification/pull/560) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- `2026-08-31` [chore(deps): bump @speclynx/apidom-* dependencies to 5.2.1](https://github.com/usearazzo/arazzo-toolkit/pull/107) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- … and 30 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (18)

- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [feat(parser): export ParseError so callers can catch it by type](https://github.com/usearazzo/arazzo-toolkit/pull/141) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor(runner): migrate to @usearazzo/parser's parseRuntimeExpression](https://github.com/usearazzo/arazzo-toolkit/pull/134) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-09-02` [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/35) — `OAI/build-infra` · `pr` · _actor,depbump_
- `2026-09-02` [feat(parser): expose parseRuntimeExpression and parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/pull/130) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- … and 10 more in this group (see All events table)

### P1-7 JSON Schema layer (18)

- `2026-09-02` [@redocly/openapi-core@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%402.50.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-09-02` [@redocly/cli@2.50.0](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%402.50.0) — `Redocly/redocly-cli` · `release` · _schema,spec_
- `2026-08-26` [v1.25.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.25.1) — `speakeasy-api/openapi` · `release` · _cli,a2a,schema,depbump_
- `2026-08-06` [v1.24.1](https://github.com/speakeasy-api/openapi/releases/tag/v1.24.1) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- `2026-08-04` [v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) — `speclynx/apidom` · `release` · _schema,spec_
- `2026-07-20` [feat: adds examples extension](https://github.com/OAI/spec.openapis.org/pull/124) — `OAI/spec.openapis.org` · `pr` · _schema_
- `2026-07-17` [docs: adds a json schema namespace](https://github.com/OAI/spec.openapis.org/pull/110) — `OAI/spec.openapis.org` · `pr` · _schema_
- `2026-06-19` [v1.23.2](https://github.com/speakeasy-api/openapi/releases/tag/v1.23.2) — `speakeasy-api/openapi` · `release` · _cli,schema,depbump_
- … and 10 more in this group (see All events table)

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

### P0-6 source routing (wsdl type) (5)

- `2026-08-28` [Bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/build-infra/pull/23) — `OAI/build-infra` · `pr` · _soap,depbump_
- `2026-08-17` [build(deps): bump highlight.js from 11.11.1 to 11.12.0](https://github.com/OAI/Overlay-Specification/pull/380) — `OAI/Overlay-Specification` · `pr` · _soap,depbump_
- `2026-07-06` [2.50.0](https://github.com/specmatic/specmatic/releases/tag/2.50.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,spec_
- `2026-06-29` [2.49.0](https://github.com/specmatic/specmatic/releases/tag/2.49.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,xml,depbump_
- `2026-05-11` [2.46.0](https://github.com/specmatic/specmatic/releases/tag/2.46.0) — `Specmatic/specmatic` · `release` · _soap,wsdl,actor,spec_

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

### API security (OAI sig-security) (3)

- `2024-09-21` [v6.13.1](https://github.com/stoplightio/spectral/releases/tag/v6.13.1) — `stoplightio/spectral` · `release` · _security,depbump_
- `2018-10-08` [OAS 3.0.2 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.2) — `OAI/OpenAPI-Specification` · `release` · _security,spec_
- `2017-04-28` [OAS 3.0.0-rc1 Released!](https://github.com/OAI/OpenAPI-Specification/releases/tag/3.0.0-rc1) — `OAI/OpenAPI-Specification` · `release` · _security,spec_

### Roadmap A2A step type (3)

- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-07-28` [build(deps-dev): bump markdownlint-cli2 from 0.23.1 to 0.23.2](https://github.com/OAI/Overlay-Specification/pull/368) — `OAI/Overlay-Specification` · `pr` · _actor,a2a,depbump_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_

### Issue #410 loops vs goto (1)

- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (118)

- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22605)](https://github.com/jentic/jentic-public-apis/commit/6d4899ada930f9be0a73d619ba9a5bc3e9635a8f) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22604)](https://github.com/jentic/jentic-public-apis/commit/a7a04fb377896e53ba388fb530351d03b825beab) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22601)](https://github.com/jentic/jentic-public-apis/commit/b3e4b44cda3e290095620e524a9b02eacc6e86e7) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22594)](https://github.com/jentic/jentic-public-apis/commit/987ba88386bdab3963c5741f65601864a89e65a9) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Dedup aiception.com: remove duplicate specs, keep aiception-api (#22590)](https://github.com/jentic/jentic-public-apis/commit/30ea657f9099f88092a5282530fa632ae991ae88) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22591)](https://github.com/jentic/jentic-public-apis/commit/e4b5580e6a06e98c04486248bf7859ba4c178f00) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [Rebuild apis.json, scores.json, and API browsing indexes (#22580)](https://github.com/jentic/jentic-public-apis/commit/865f11e49d871b127c1c091f0247ee798ec6ad24) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-07` [feat: add FAQ to parsing guide](https://github.com/usearazzo/website/commit/e13283b0702ef63140a009eed461d1f8ef261443) — `usearazzo/website` · `commit` · _no tags_
- … and 110 more in this group (see All events table)

### Conformance / schema validation (90)

- `2026-09-07` [openapi.tools checksum d6d39798f7de](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-07` [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/572) — `OAI/Arazzo-Specification` · `pr` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22526 (#22527)](https://github.com/jentic/jentic-public-apis/commit/f2ddb3054c889ca16784b09bb92549f909fbce5a) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22524 (#22525)](https://github.com/jentic/jentic-public-apis/commit/796a9b9ece12941bafac422c9744ff2ac2ea7f6c) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22540 (#22541)](https://github.com/jentic/jentic-public-apis/commit/bd92fef19a7e8c6a3ac55171bb37313c85352550) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22599 (#22602)](https://github.com/jentic/jentic-public-apis/commit/da6f0efe742fd1b58cef6f4d11c72d67c9ce2e3c) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22571 (#22572)](https://github.com/jentic/jentic-public-apis/commit/24aaf80ca35a2e288fbbbcf97f2e16bf9061fe50) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-07` [feat: Import OpenAPI spec from Issue #22593 (#22595)](https://github.com/jentic/jentic-public-apis/commit/19cdaff6f20c155936f642eba2d7fa55d8564868) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 82 more in this group (see All events table)

### Dependency maintenance (44)

- `2026-09-07` [Merge pull request #570 from OAI/dependabot/npm_and_yarn/respec-37.3.6](https://github.com/OAI/Arazzo-Specification/commit/7e21fa94abe77f7befc8f11f0bda85af8acb9d63) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-07` [Merge pull request #569 from OAI/dependabot/npm_and_yarn/vitest-4b52c90628](https://github.com/OAI/Arazzo-Specification/commit/02b4241c2fb6302de72e05bfd088ccbd15f92c84) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-07` [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) — `OAI/Overlay-Specification` · `pr` · _depbump_
- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/commit/3e24e8d2c8fbc4cbfa68a02adf81c95b8c37c683) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-07` [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/commit/0af6e3da8e27e64099edacae71eced941d36b8c8) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-03` [chore(deps): bump vscode-languageserver-types from 3.17.6-next.6 to 3.18.3](https://github.com/usearazzo/arazzo-toolkit/pull/109) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-02` [Merge pull request #35 from OAI/dependabot/npm_and_yarn/publishing-e8f8b6188d](https://github.com/OAI/build-infra/commit/d4828b62866309262ff16d9925b9223956e09989) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-02` [Merge pull request #34 from OAI/dependabot/npm_and_yarn/markdown-a6d6531f3e](https://github.com/OAI/build-infra/commit/2a400798cfb7e6158fdb84c6d296c8d3e3830c9d) — `OAI/build-infra` · `commit` · _depbump_
- … and 36 more in this group (see All events table)

### P2-1 CLI binary (24)

- `2026-09-07` [Migrate cli + laravel to consume runner/document/expression via public faces](https://github.com/Mohammed-Alama/php-arazzo/issues/61) — `Mohammed-Alama/php-arazzo` · `issue` · _cli_
- `2026-09-06` [docs(changelog): open 0.6.0 with a summary a reader can act on](https://github.com/strefethen/arazzo-cli/commit/1dc4731122c00c83eaed66a16485b4faaa848814) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-06` [fix(release): publish the CHANGELOG section as the release body](https://github.com/strefethen/arazzo-cli/commit/c61311778c30727da4bb2497819eb16ee2ba819b) — `strefethen/arazzo-cli` · `commit` · _cli,actor_
- `2026-09-06` [docs(changelog): date 0.6.0 to the day it ships](https://github.com/strefethen/arazzo-cli/commit/40d24241d9bcd5d0efe9a5f1a76c2622c22a0807) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-06` [@internal sweep: hide all non-face interfaces + implementations from the public API](https://github.com/Mohammed-Alama/php-arazzo/issues/63) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,actor_
- `2026-09-06` [docs(changelog): cover the four fixes that landed after the release commit](https://github.com/strefethen/arazzo-cli/commit/33ab040fd03610e7393b864389798724ff140f8c) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-04` [chore(deps-dev): bump mocha from 11.8.0 to 12.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/144) — `usearazzo/arazzo-toolkit` · `pr` · _cli,depbump_
- `2026-09-04` [test(runtime): use valid OpenAPI event-drain fixture](https://github.com/strefethen/arazzo-cli/commit/f82b2ff0a4b5d5afcde55393d7583c2b503e675a) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- … and 16 more in this group (see All events table)

### API security (OAI sig-security) (16)

- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [Auth URL Variables](https://github.com/OAI/sig-security/issues/33) — `OAI/sig-security` · `issue` · _security_
- `2026-08-21` [Use wildcard or regex in cookie name in Cookie Authentication](https://github.com/OAI/sig-security/issues/32) — `OAI/sig-security` · `issue` · _security_
- `2026-08-21` [Security Schemes for different roles and environments](https://github.com/OAI/sig-security/issues/31) — `OAI/sig-security` · `issue` · _security_
- … and 8 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (16)

- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-09-03` [parser: export ParseError so callers can catch it by type](https://github.com/usearazzo/arazzo-toolkit/issues/140) — `usearazzo/arazzo-toolkit` · `issue` · _actor,spec_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-ns-openapi-3-0 from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/93) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-traverse from 5.1.0 to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/92) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- … and 8 more in this group (see All events table)

### P1-7 JSON Schema layer (16)

- `2026-09-06` [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,schema,spec_
- `2026-09-04` [docs(plans): record blocked JSON Schema input design](https://github.com/strefethen/arazzo-cli/commit/7e5d96d981c3998ffa93879bba025491bf3a4feb) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,human,schema,spec_
- `2026-09-03` [docs(conformance): say what ac-93d90 actually settled and what is still open](https://github.com/strefethen/arazzo-cli/commit/1f75561c49843aafc91836c88bec3c822f9f8010) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,schema,spec_
- `2026-08-31` [Bump jmertic/lfx-landscape-tools from 20260625 to 20260826 in the all group (#193)](https://github.com/OAI/landscape/commit/8c128a7b3f32ff3b50815246017cb9d651ab88bf) — `OAI/landscape` · `commit` · _schema,depbump_
- `2026-08-20` [Bump the hyperjump group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/18) — `OAI/build-infra` · `pr` · _schema,depbump_
- `2026-08-11` [Revisit: should Overlays declare their target document format? (follow-up to #268)](https://github.com/OAI/Overlay-Specification/issues/367) — `OAI/Overlay-Specification` · `issue` · _schema,spec_
- `2026-08-10` [Merge pull request #540 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.17.8](https://github.com/OAI/Arazzo-Specification/commit/6f391e33b892c82f7cbb7b98dd01dd5fcaa3481b) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,schema,spec_
- … and 8 more in this group (see All events table)

### OAI Moonwalk (next-gen spec) (8)

- `2026-03-17` [Write ADR for identity vs location](https://github.com/OAI/sig-moonwalk/issues/92) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2025-11-12` [Create draft REST proposal](https://github.com/OAI/sig-moonwalk/pull/212) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-05-07` [Added preliminary design for resource model](https://github.com/OAI/sig-moonwalk/pull/183) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-02-23` [Added example of query parameter versioning](https://github.com/OAI/sig-moonwalk/pull/174) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-07-23` [Can the Data Types section be replaced by a reference to the format registry?](https://github.com/OAI/sig-moonwalk/issues/131) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Open API Path Templating vs WHATWG URL Pattern](https://github.com/OAI/sig-moonwalk/issues/125) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Allow recursive paths](https://github.com/OAI/sig-moonwalk/issues/117) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Structural improvements: inheritance on paths and its sublevels](https://github.com/OAI/sig-moonwalk/issues/115) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_

### P2-2 MCP server exposure (6)

- `2026-09-04` [docs(plans): capture runtime HTTP decomposition draft](https://github.com/strefethen/arazzo-cli/commit/ab1c627148dfbc6fe1ae76c1f79f5af03cf01529) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,actor,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website` · `commit` · _mcp_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_
- `2026-08-26` [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_

### Arazzo runner / step execution (5)

- `2026-09-06` [feat: ExecutionLoop Autonomy (P0-2) — human confirmation + circuit breaker](https://github.com/Mohammed-Alama/php-arazzo/issues/19) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,human,runner,spec_
- `2026-09-04` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-09-03` [runner: migrate to @usearazzo/parser's parseRuntimeExpression / parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/issues/131) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2026-08-31` [XPath version identifier feels a bit confusing](https://github.com/OAI/Arazzo-Specification/issues/219) — `OAI/Arazzo-Specification` · `issue` · _xml,xpath,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### Roadmap GraphQL step type (2)

- `2026-09-03` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-06` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### P1-6 payload XPath / P0-5 XPath criteria (1)

- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### Roadmap A2A step type (1)

- `2026-07-22` [build(deps): bump ruby/setup-ruby from 1.319.0 to 1.320.0](https://github.com/OAI/spec.openapis.org/commit/3ccc930eaa2a78c31ea19f09e0dbea2639b571ed) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Roadmap gRPC step type (1)

- `2026-09-03` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-07 | openapi.tools | tool_collection | [openapi.tools checksum d6d39798f7de](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-07 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Specmatic/specmatic | tag | [tag 2.45.1](https://github.com/Specmatic/specmatic/releases/tag/2.45.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [v1.0-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/572) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22605)](https://github.com/jentic/jentic-public-apis/commit/6d4899ada930f9be0a73d619ba9a5bc3e9635a8f) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22526 (#22527)](https://github.com/jentic/jentic-public-apis/commit/f2ddb3054c889ca16784b09bb92549f909fbce5a) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22524 (#22525)](https://github.com/jentic/jentic-public-apis/commit/796a9b9ece12941bafac422c9744ff2ac2ea7f6c) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22540 (#22541)](https://github.com/jentic/jentic-public-apis/commit/bd92fef19a7e8c6a3ac55171bb37313c85352550) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22599 (#22602)](https://github.com/jentic/jentic-public-apis/commit/da6f0efe742fd1b58cef6f4d11c72d67c9ce2e3c) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22604)](https://github.com/jentic/jentic-public-apis/commit/a7a04fb377896e53ba388fb530351d03b825beab) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22571 (#22572)](https://github.com/jentic/jentic-public-apis/commit/24aaf80ca35a2e288fbbbcf97f2e16bf9061fe50) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22593 (#22595)](https://github.com/jentic/jentic-public-apis/commit/19cdaff6f20c155936f642eba2d7fa55d8564868) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22587 (#22592)](https://github.com/jentic/jentic-public-apis/commit/5641e4b51f995189177d619f07c4fd99cb2fb16c) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22556 (#22557)](https://github.com/jentic/jentic-public-apis/commit/812794f9249ff11760b70c64c4517979c6048896) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22601)](https://github.com/jentic/jentic-public-apis/commit/b3e4b44cda3e290095620e524a9b02eacc6e86e7) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22534 (#22535)](https://github.com/jentic/jentic-public-apis/commit/de1debc8f7823fd31ccb2d75274ebb3f41ce6fa5) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22552 (#22553)](https://github.com/jentic/jentic-public-apis/commit/daaef709efe8cd8f015dc51821e1b3a8f1fbec03) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [v1.2-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/574) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [v1.1-dev: sync with dev](https://github.com/OAI/Arazzo-Specification/pull/573) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [dev: sync with main](https://github.com/OAI/Arazzo-Specification/pull/571) | spec | actionable | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22594)](https://github.com/jentic/jentic-public-apis/commit/987ba88386bdab3963c5741f65601864a89e65a9) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Dedup aiception.com: remove duplicate specs, keep aiception-api (#22590)](https://github.com/jentic/jentic-public-apis/commit/30ea657f9099f88092a5282530fa632ae991ae88) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22591)](https://github.com/jentic/jentic-public-apis/commit/e4b5580e6a06e98c04486248bf7859ba4c178f00) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22585 (#22589)](https://github.com/jentic/jentic-public-apis/commit/372ac899a6fe5ca5dcc1947efd84cc207d28c02a) | spec | watch | Conformance / schema validation |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22579 (#22584)](https://github.com/jentic/jentic-public-apis/commit/32a3c4c7937856c2362890cb98e478fdceb43612) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-07 | OAI/Arazzo-Specification | commit | [Merge pull request #570 from OAI/dependabot/npm_and_yarn/respec-37.3.6](https://github.com/OAI/Arazzo-Specification/commit/7e21fa94abe77f7befc8f11f0bda85af8acb9d63) | depbump | watch | Dependency maintenance |
| 2026-09-07 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22580)](https://github.com/jentic/jentic-public-apis/commit/865f11e49d871b127c1c091f0247ee798ec6ad24) |  | watch |  |
| 2026-09-07 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22550 (#22551)](https://github.com/jentic/jentic-public-apis/commit/d46c802fcd5ef46a33eb85b9272bde96e7916310) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | pr | [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/569) | breaking, depbump | breaking | Dependency maintenance |
| 2026-09-07 | OAI/Arazzo-Specification | commit | [Merge pull request #569 from OAI/dependabot/npm_and_yarn/vitest-4b52c90628](https://github.com/OAI/Arazzo-Specification/commit/02b4241c2fb6302de72e05bfd088ccbd15f92c84) | depbump | watch | Dependency maintenance |
| 2026-09-07 | usearazzo/website | commit | [feat: add FAQ to parsing guide](https://github.com/usearazzo/website/commit/e13283b0702ef63140a009eed461d1f8ef261443) |  | watch |  |
| 2026-09-07 | OAI/Overlay-Specification | pr | [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-07 | OAI/Overlay-Specification | pr | [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-07 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | depbump | watch | Dependency maintenance |
| 2026-09-07 | Mohammed-Alama/php-arazzo | issue | [Migrate cli + laravel to consume runner/document/expression via public faces](https://github.com/Mohammed-Alama/php-arazzo/issues/61) | cli | watch | P2-1 CLI binary |
| 2026-09-07 | Mohammed-Alama/php-arazzo | pr | [feat: expose runner public faces; migrate cli + laravel onto them](https://github.com/Mohammed-Alama/php-arazzo/pull/70) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-07 | Mohammed-Alama/php-arazzo | pr | [feat(runner): consume expression + document engines via public faces](https://github.com/Mohammed-Alama/php-arazzo/pull/69) | xml, xpath, actor, spec | actionable | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-09-07 | Mohammed-Alama/php-arazzo | issue | [Migrate runner internals to consume expression+document via public faces](https://github.com/Mohammed-Alama/php-arazzo/issues/60) | spec | watch | Conformance / schema validation |
| 2026-09-07 | OAI/Arazzo-Specification | commit | [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/commit/3e24e8d2c8fbc4cbfa68a02adf81c95b8c37c683) | depbump | watch | Dependency maintenance |
| 2026-09-07 | OAI/Arazzo-Specification | commit | [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/commit/0af6e3da8e27e64099edacae71eced941d36b8c8) | depbump | watch | Dependency maintenance |
| 2026-09-07 | Mohammed-Alama/php-arazzo | issue | [Migrate document validator to consume expression via its public face](https://github.com/Mohammed-Alama/php-arazzo/issues/59) |  | watch |  |
| 2026-09-07 | Mohammed-Alama/php-arazzo | pr | [feat(document): validator consumes expression via its public face](https://github.com/Mohammed-Alama/php-arazzo/pull/68) | xml, xpath | actionable | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-09-06 | OAI/OpenAPI-Specification | pr | [v3.3: Add a global parameters field](https://github.com/OAI/OpenAPI-Specification/pull/5446) |  | watch |  |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Richen runner public face + rewire RunnerFacade off concrete cross-package imports](https://github.com/Mohammed-Alama/php-arazzo/issues/58) | spec | watch | Conformance / schema validation |
| 2026-09-06 | Mohammed-Alama/php-arazzo | pr | [richen public face covering cli/laravel needs — Closes #58](https://github.com/Mohammed-Alama/php-arazzo/pull/67) | xml, xpath, cli, actor | actionable | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) | actor, spec | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-06 | Mohammed-Alama/php-arazzo | pr | [richen public face covering runner/cli/laravel needs — Closes #57](https://github.com/Mohammed-Alama/php-arazzo/pull/66) | spec | actionable | Conformance / schema validation |
| 2026-09-06 | strefethen/arazzo-cli | commit | [docs(changelog): open 0.6.0 with a summary a reader can act on](https://github.com/strefethen/arazzo-cli/commit/1dc4731122c00c83eaed66a16485b4faaa848814) | cli | watch | P2-1 CLI binary |
| 2026-09-06 | strefethen/arazzo-cli | commit | [fix(release): publish the CHANGELOG section as the release body](https://github.com/strefethen/arazzo-cli/commit/c61311778c30727da4bb2497819eb16ee2ba819b) | cli, actor | watch | P2-1 CLI binary |
| 2026-09-06 | strefethen/arazzo-cli | release | [v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli, spec | actionable | P2-1 CLI binary |
| 2026-09-06 | strefethen/arazzo-cli | commit | [docs(changelog): date 0.6.0 to the day it ships](https://github.com/strefethen/arazzo-cli/commit/40d24241d9bcd5d0efe9a5f1a76c2622c22a0807) | cli | watch | P2-1 CLI binary |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) | xml, xpath, actor | watch | P0-5 XPath criteria + P1-6 targetSelectorType |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) | actor | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-06 | Mohammed-Alama/php-arazzo | pr | [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) | actor | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) | breaking | breaking | Potential breaking change (2.0) |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [@internal sweep: hide all non-face interfaces + implementations from the public API](https://github.com/Mohammed-Alama/php-arazzo/issues/63) | cli, actor | watch | P2-1 CLI binary |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) | cli, schema, spec | watch | P1-7 JSON Schema layer |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) | soap, xml, xpath, spec | watch | P0-6 source routing (wsdl type) |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) | soap, wsdl, xml, xpath, breaking, spec | breaking | P0-6 source routing (wsdl type) |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [feat: Priority-ordered Arazzo file parsing (P2-7)](https://github.com/Mohammed-Alama/php-arazzo/issues/18) | spec | watch | Conformance / schema validation |
| 2026-09-06 | Mohammed-Alama/php-arazzo | issue | [feat: ExecutionLoop Autonomy (P0-2) — human confirmation + circuit breaker](https://github.com/Mohammed-Alama/php-arazzo/issues/19) | actor, human, runner, spec | watch | Arazzo runner / step execution |
| 2026-09-06 | Mohammed-Alama/php-arazzo | pr | [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) | loop, spec | actionable | Issue #410 loops vs goto |
| 2026-09-06 | Mohammed-Alama/php-arazzo | pr | [feat: add phpmd, pdepend, phpdoc to quality pipeline](https://github.com/Mohammed-Alama/php-arazzo/pull/53) | xml | actionable | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-06 | strefethen/arazzo-cli | commit | [docs(changelog): cover the four fixes that landed after the release commit](https://github.com/strefethen/arazzo-cli/commit/33ab040fd03610e7393b864389798724ff140f8c) | cli | watch | P2-1 CLI binary |
| 2026-09-05 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-05 (#197)](https://github.com/OAI/landscape/commit/6d7e8816601bb5682af06723a489302495d22a4c) |  | watch |  |
| 2026-09-05 | OAI/build-infra | pr | [Use semver for release version parsing](https://github.com/OAI/build-infra/pull/39) |  | watch |  |
| 2026-09-05 | OAI/build-infra | pr | [Catch ReSpec errors, update ReSpec to a verison with a fallback API URL](https://github.com/OAI/build-infra/pull/38) |  | actionable |  |
| 2026-09-05 | OAI/build-infra | commit | [Merge pull request #38 from handrews/fix/specref-fallback](https://github.com/OAI/build-infra/commit/7216d85798b496ae379416cae6598325479a4edd) |  | watch |  |
| 2026-09-05 | usearazzo/website | commit | [feat: add ROAS to Ecosystem Tools (Run & test)](https://github.com/usearazzo/website/commit/b0298f16a368be7caca8341a5c98e6581edba52d) |  | watch |  |
| 2026-09-05 | usearazzo/website.ecosystem.atom | commit | [feat: add ROAS to Ecosystem Tools (Run & test)](https://github.com/usearazzo/website/commit/b0298f16a368be7caca8341a5c98e6581edba52d) |  | watch |  |
| 2026-09-05 | OAI/Overlay-Specification | pr | [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) |  | watch |  |
| 2026-09-04 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-04 (#196)](https://github.com/OAI/landscape/commit/00a4724c72e467ca567c5da44525f931cbcb4e32) |  | watch |  |
| 2026-09-04 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump mocha from 11.8.0 to 12.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/144) | cli, depbump | watch | P2-1 CLI binary |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
