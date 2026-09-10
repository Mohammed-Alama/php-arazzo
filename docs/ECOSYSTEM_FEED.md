# Ecosystem Feed — Human Dashboard

> **Generated:** 2026-09-10T11:17:24+00:00 by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`
> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`
> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)

## Summary

- **Total events:** 845 (showing 200 newest)
- **Severity:** breaking **38** · actionable **458** · watch **349**
- **Top relevance:** `Conformance / schema validation` (332) · `uncategorized` (170) · `Dependency maintenance` (88) · `P2-1 CLI binary` (73) · `P1-7 JSON Schema layer` (38)
- **Top sources:** `OAI/Arazzo-Specification` (51) · `strefethen/arazzo-cli` (50) · `OAI/build-infra` (43) · `speclynx/apidom` (40) · `jentic/jentic-arazzo-tools` (40)
- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)

## Legend

- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context
- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)
- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels

## Breaking — needs attention

### Conformance / schema validation (9)

- `2026-09-10` [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `tag` · _breaking,spec_
- `2026-09-08` [fix(parser): resolve relative file paths against working directory](https://github.com/usearazzo/arazzo-toolkit/pull/148) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-09-03` [fix(parser): distinguish shared source descriptions from cycles](https://github.com/usearazzo/arazzo-toolkit/pull/142) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,spec_
- `2026-08-21` [Enhanced Operation Deprecation and versioning](https://github.com/OAI/sig-lifecycle/issues/10) — `OAI/sig-lifecycle` · `issue` · _breaking,spec_
- `2026-08-11` [Add OpenAPI Breaking Change Checker](https://github.com/OAI/tools.openapis.org/issues/282) — `OAI/tools.openapis.org` · `issue` · _breaking,spec_
- `2026-07-07` [v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) — `speclynx/apidom` · `release` · _breaking,spec_
- `2026-07-07` [v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _breaking,spec_
- `2026-06-23` [v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) — `speclynx/apidom` · `release` · _breaking,spec_
- … and 1 more in this group (see All events table)

### Dependency maintenance (9)

- `2026-09-09` [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/40) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-09-07` [chore(deps-dev): bump vitest from 4.1.11 to 5.0.0 in the vitest group](https://github.com/OAI/Arazzo-Specification/pull/569) — `OAI/Arazzo-Specification` · `pr` · _breaking,depbump_
- `2026-08-28` [chore(deps): bump @speclynx/apidom-* dependencies to 5.1.1](https://github.com/usearazzo/arazzo-toolkit/pull/103) — `usearazzo/arazzo-toolkit` · `pr` · _breaking,depbump_
- `2026-08-28` [Bump content-type from 2.0.0 to 3.0.0](https://github.com/OAI/build-infra/pull/28) — `OAI/build-infra` · `pr` · _breaking,depbump_
- `2026-08-03` [build(deps-dev): bump jekyll-include-cache from 0.2.1 to 0.2.2](https://github.com/OAI/spec.openapis.org/pull/128) — `OAI/spec.openapis.org` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 6](https://github.com/jentic/arazzo-engine/pull/130) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/download-artifact from 5 to 7](https://github.com/jentic/arazzo-engine/pull/137) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- `2025-12-12` [chore(deps): bump actions/upload-artifact from 4 to 5](https://github.com/jentic/arazzo-engine/pull/131) — `jentic/arazzo-engine` · `pr` · _breaking,depbump_
- … and 1 more in this group (see All events table)

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

- `2026-09-06` [feat: WSDL source routing (P0-6) — parser/validator only](https://github.com/Mohammed-Alama/php-arazzo/issues/17) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,wsdl,xml,xpath,breaking,spec_
- `2026-09-01` [feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533) — `OAI/Arazzo-Specification` · `pr` · _soap,wsdl,breaking,spec_

### P1-6 payload XPath / P0-5 XPath criteria (2)

- `2026-01-23` [v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) — `swaggerexpert/arazzo-runtime-expression` · `release` · _xml,breaking,spec_
- `2024-05-24` [Implementors Feedback on current Alternative Schemas Draft Proposal](https://github.com/OAI/sig-moonwalk/issues/121) — `OAI/sig-moonwalk` · `issue` · _xml,grpc,graphql,breaking,schema,moonwalk,depbump_

### P2-1 CLI binary (2)

- `2026-08-26` [v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `release` · _cli,breaking,spec_
- `2026-08-03` [build(deps): bump markdown-it from 14.3.0 to 15.0.0](https://github.com/OAI/Overlay-Specification/pull/375) — `OAI/Overlay-Specification` · `pr` · _cli,breaking,depbump_

### Arazzo runner / step execution (1)

- `2026-09-09` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/42) — `OAI/build-infra` · `pr` · _actor,breaking,runner,depbump_

### OAI Moonwalk (next-gen spec) (1)

- `2026-04-08` [Feat: Proposed content strategy to support repositioning OAI](https://github.com/OAI/Outreach/issues/71) — `OAI/Outreach` · `issue` · _breaking,moonwalk,spec_

### Potential breaking change (2.0) (1)

- `2026-09-06` [Verification + BC-gate polish for hardened boundaries](https://github.com/Mohammed-Alama/php-arazzo/issues/64) — `Mohammed-Alama/php-arazzo` · `issue` · _breaking_


## Actionable — new releases/tags to review

### Conformance / schema validation (237)

- `2026-09-10` [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-10` [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-10` [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) — `frankkilcommins/arazzo2openapi` · `tag` · _spec_
- `2026-09-10` [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-10` [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-10` [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-10` [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) — `b-lab-io/pyarazzo` · `tag` · _spec_
- `2026-09-10` [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) — `b-lab-io/pyarazzo` · `tag` · _spec_
- … and 229 more in this group (see All events table)

### uncategorized (49)

- `2026-09-09` [Fix/vitest vite peer](https://github.com/OAI/build-infra/pull/41) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-08` [Use semver for release version parsing](https://github.com/OAI/build-infra/pull/39) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-08` [1.2-dev refresh from dev](https://github.com/OAI/Overlay-Specification/pull/391) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-08` [dev refresh from main](https://github.com/OAI/Overlay-Specification/pull/390) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-08` [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-08` [chore(docs): mark implementation classes and non-public-face interfaces as @internal (refs #63)](https://github.com/Mohammed-Alama/php-arazzo/pull/71) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- `2026-09-05` [Catch ReSpec errors, update ReSpec to a verison with a fallback API URL](https://github.com/OAI/build-infra/pull/38) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-03` [2026 07 25 core 38 event dispatcher wiring](https://github.com/Mohammed-Alama/php-arazzo/pull/5) — `Mohammed-Alama/php-arazzo` · `pr` · _no tags_
- … and 41 more in this group (see All events table)

### P2-1 CLI binary (48)

- `2026-09-10` [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) — `strefethen/arazzo-cli` · `tag` · _cli_
- `2026-09-10` [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) — `strefethen/arazzo-cli` · `tag` · _cli_
- … and 40 more in this group (see All events table)

### Dependency maintenance (37)

- `2026-09-07` [chore(deps-dev): bump eslint from 10.9.1 to 10.10.0](https://github.com/usearazzo/arazzo-toolkit/pull/146) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-04` [chore(deps-dev): bump webpack from 5.110.2 to 5.110.3](https://github.com/usearazzo/arazzo-toolkit/pull/145) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-03` [chore(deps): bump github/codeql-action from 4.37.8 to 4.37.9](https://github.com/usearazzo/arazzo-toolkit/pull/110) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-02` [chore(deps-dev): bump webpack from 5.110.1 to 5.110.2](https://github.com/usearazzo/arazzo-toolkit/pull/133) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-02` [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/34) — `OAI/build-infra` · `pr` · _depbump_
- `2026-09-01` [chore(deps-dev): bump webpack from 5.109.2 to 5.110.1](https://github.com/usearazzo/arazzo-toolkit/pull/132) — `usearazzo/arazzo-toolkit` · `pr` · _depbump_
- `2026-09-01` [Bump markdown-it from 15.0.0 to 15.0.1 in the markdown group](https://github.com/OAI/build-infra/pull/31) — `OAI/build-infra` · `pr` · _depbump_
- `2026-08-31` [chore(deps): bump markdown-it from 15.0.0 to 15.0.1](https://github.com/OAI/Arazzo-Specification/pull/560) — `OAI/Arazzo-Specification` · `pr` · _depbump_
- … and 29 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (19)

- `2026-09-08` [chore(deps-dev): bump lint-staged from 17.4.1 to 17.5.0](https://github.com/usearazzo/arazzo-toolkit/pull/149) — `usearazzo/arazzo-toolkit` · `pr` · _actor,depbump_
- `2026-09-07` [chore(deps): bump respec from 37.3.5 to 37.3.6](https://github.com/OAI/Arazzo-Specification/pull/570) — `OAI/Arazzo-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [feat(tooling): contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/pull/65) — `Mohammed-Alama/php-arazzo` · `pr` · _actor_
- `2026-09-03` [feat(parser): export ParseError so callers can catch it by type](https://github.com/usearazzo/arazzo-toolkit/pull/141) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-09-03` [refactor: extract framework-agnostic engine into arazzo-core (Plan A)](https://github.com/Mohammed-Alama/php-arazzo/pull/6) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor: decompose ExpressionResolver into deep modules](https://github.com/Mohammed-Alama/php-arazzo/pull/9) — `Mohammed-Alama/php-arazzo` · `pr` · _actor,spec_
- `2026-09-03` [refactor(runner): migrate to @usearazzo/parser's parseRuntimeExpression](https://github.com/usearazzo/arazzo-toolkit/pull/134) — `usearazzo/arazzo-toolkit` · `pr` · _actor,spec_
- `2026-09-02` [Bump respec from 37.3.0 to 37.3.5 in the publishing group](https://github.com/OAI/build-infra/pull/35) — `OAI/build-infra` · `pr` · _actor,depbump_
- … and 11 more in this group (see All events table)

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

### Arazzo runner / step execution (2)

- `2025-09-04` [Arazzo Runner v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) — `jentic/arazzo-engine` · `release` · _runner,spec_
- `2025-09-02` [Arazzo Runner v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) — `jentic/arazzo-engine` · `release` · _runner,spec_

### Roadmap A2A step type (2)

- `2026-08-29` [v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) — `speclynx/apidom` · `release` · _a2a,spec_
- `2026-03-11` [v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) — `jentic/jentic-arazzo-tools` · `release` · _a2a,spec_

### Issue #410 loops vs goto (1)

- `2026-09-06` [feat(tooling): add custom PHPStan rules for code-hygiene discipline](https://github.com/Mohammed-Alama/php-arazzo/pull/54) — `Mohammed-Alama/php-arazzo` · `pr` · _loop,spec_


## Watch — context (commits/issues/checksums)

### uncategorized (121)

- `2026-09-10` [v3.3: define an explicit default for the top-level "security" object](https://github.com/OAI/OpenAPI-Specification/pull/5536) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-10` [Rebuild apis.json, scores.json, and API browsing indexes (#22786)](https://github.com/jentic/jentic-public-apis/commit/e5b354b4e7b6e37b79e78d54e4fe1054fc9432f4) — `jentic/jentic-public-apis` · `commit` · _no tags_
- `2026-09-09` [Update Landscape from LFX 2026-09-09 (#200)](https://github.com/OAI/landscape/commit/a1f8de2112d45b8c127f078cf5cf1c6cd63badc2) — `OAI/landscape` · `commit` · _no tags_
- `2026-09-09` [Install qualification runner dependencies](https://github.com/OAI/build-infra/pull/43) — `OAI/build-infra` · `pr` · _no tags_
- `2026-09-09` [DO NOT MERGE - release notes for 1.2](https://github.com/OAI/Overlay-Specification/pull/378) — `OAI/Overlay-Specification` · `pr` · _no tags_
- `2026-09-09` [Merge pull request #41 from handrews/fix/vitest-vite-peer](https://github.com/OAI/build-infra/commit/302422355ad9f38658a66637ac8a4b84791a6a2c) — `OAI/build-infra` · `commit` · _no tags_
- `2026-09-09` [v3.2: warn that multiple document logic may change](https://github.com/OAI/OpenAPI-Specification/pull/5535) — `OAI/OpenAPI-Specification` · `pr` · _no tags_
- `2026-09-09` [Rebuild apis.json, scores.json, and API browsing indexes (#22774)](https://github.com/jentic/jentic-public-apis/commit/84c552c8f1b6b8093ef0c6021bacb4e115e064e6) — `jentic/jentic-public-apis` · `commit` · _no tags_
- … and 113 more in this group (see All events table)

### Conformance / schema validation (86)

- `2026-09-10` [openapi.tools checksum d6d39798f7de](https://openapi.tools/collections/arazzo) — `openapi.tools` · `tool_collection` · _spec_
- `2026-09-10` [^R»05<^O*ÿ^M½^O}^\] £^_Mº^\O](https://github.com/OAI/tools.openapis.org/issues/292) — `OAI/tools.openapis.org` · `issue` · _spec_
- `2026-09-09` [VOTE: Approve release of OpenAPI Overlay Specification v1.2](https://github.com/OAI/Overlay-Specification/issues/392) — `OAI/Overlay-Specification` · `issue` · _spec_
- `2026-09-09` [docs: adds an upgrade guide for overlay 1.2](https://github.com/OAI/learn.openapis.org/pull/206) — `OAI/learn.openapis.org` · `pr` · _spec_
- `2026-09-09` [Branching strategy in CONTRIBUTING.md is outdated (I think)](https://github.com/OAI/Overlay-Specification/issues/393) — `OAI/Overlay-Specification` · `issue` · _spec_
- `2026-09-09` [Remove duplicate idtbeyond.com spec folders (#22775)](https://github.com/jentic/jentic-public-apis/commit/4b4c7df2fb851d3cbf9693bc8cd10301965e3d4c) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-09` [feat: Import OpenAPI spec from Issue #22690 (#22691)](https://github.com/jentic/jentic-public-apis/commit/8204a517923e926f736e479f0a05567855f3b221) — `jentic/jentic-public-apis` · `commit` · _spec_
- `2026-09-09` [feat: Import OpenAPI spec from Issue #22355 (#22357)](https://github.com/jentic/jentic-public-apis/commit/c1a95784b575e9ab1407b859c8ca6879fdc00f68) — `jentic/jentic-public-apis` · `commit` · _spec_
- … and 78 more in this group (see All events table)

### Dependency maintenance (42)

- `2026-09-09` [Merge pull request #42 from OAI/dependabot/npm_and_yarn/vitest-8ab2bcc523](https://github.com/OAI/build-infra/commit/34fdfa9f0019f440f5ee184e04259081cb16ce07) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-09` [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/commit/a0537fe5db1b78650f1b007bd0547b082e168b73) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-08` [Bump version number to prep for release.](https://github.com/OAI/build-infra/commit/4b19eb634f8daf598feeeff9e5cd6d59dee95012) — `OAI/build-infra` · `commit` · _depbump_
- `2026-09-08` [build(deps): bump markdown-it from 15.0.0 to 15.0.1](https://github.com/OAI/Overlay-Specification/pull/386) — `OAI/Overlay-Specification` · `pr` · _depbump_
- `2026-09-08` [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) — `OAI/Overlay-Specification` · `pr` · _depbump_
- `2026-09-08` [docs: bump parser reference to 1.0.1-alpha.1](https://github.com/usearazzo/website/commit/9aea56618dcfcc92d5102a29dd39aea820839423) — `usearazzo/website` · `commit` · _depbump_
- `2026-09-07` [Merge pull request #570 from OAI/dependabot/npm_and_yarn/respec-37.3.6](https://github.com/OAI/Arazzo-Specification/commit/7e21fa94abe77f7befc8f11f0bda85af8acb9d63) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- `2026-09-07` [Merge pull request #569 from OAI/dependabot/npm_and_yarn/vitest-4b52c90628](https://github.com/OAI/Arazzo-Specification/commit/02b4241c2fb6302de72e05bfd088ccbd15f92c84) — `OAI/Arazzo-Specification` · `commit` · _depbump_
- … and 34 more in this group (see All events table)

### P2-1 CLI binary (23)

- `2026-09-08` [@internal sweep: hide all non-face interfaces + implementations from the public API](https://github.com/Mohammed-Alama/php-arazzo/issues/63) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,actor_
- `2026-09-07` [Migrate cli + laravel to consume runner/document/expression via public faces](https://github.com/Mohammed-Alama/php-arazzo/issues/61) — `Mohammed-Alama/php-arazzo` · `issue` · _cli_
- `2026-09-06` [docs(changelog): open 0.6.0 with a summary a reader can act on](https://github.com/strefethen/arazzo-cli/commit/1dc4731122c00c83eaed66a16485b4faaa848814) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-06` [fix(release): publish the CHANGELOG section as the release body](https://github.com/strefethen/arazzo-cli/commit/c61311778c30727da4bb2497819eb16ee2ba819b) — `strefethen/arazzo-cli` · `commit` · _cli,actor_
- `2026-09-06` [docs(changelog): date 0.6.0 to the day it ships](https://github.com/strefethen/arazzo-cli/commit/40d24241d9bcd5d0efe9a5f1a76c2622c22a0807) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-06` [docs(changelog): cover the four fixes that landed after the release commit](https://github.com/strefethen/arazzo-cli/commit/33ab040fd03610e7393b864389798724ff140f8c) — `strefethen/arazzo-cli` · `commit` · _cli_
- `2026-09-04` [test(runtime): use valid OpenAPI event-drain fixture](https://github.com/strefethen/arazzo-cli/commit/f82b2ff0a4b5d5afcde55393d7583c2b503e675a) — `strefethen/arazzo-cli` · `commit` · _cli,spec_
- `2026-09-04` [fix: drain parallel step events during execution](https://github.com/strefethen/arazzo-cli/commit/768750d1de99dac62ae965858232ff6a0abe1a54) — `strefethen/arazzo-cli` · `commit` · _cli_
- … and 15 more in this group (see All events table)

### API security (OAI sig-security) (18)

- `2026-09-08` [v3.3: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5533) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-09-08` [v3.2: fix schema test that lacks security scheme definitions](https://github.com/OAI/OpenAPI-Specification/pull/5534) — `OAI/OpenAPI-Specification` · `pr` · _security_
- `2026-08-22` [Support for message level security](https://github.com/OAI/sig-security/issues/22) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Support for JOSE (JSON Signature and Encryption) Standards](https://github.com/OAI/sig-security/issues/37) — `OAI/sig-security` · `issue` · _security,spec_
- `2026-08-21` [Add info to security considerations about outdated security practices, and link in new versions](https://github.com/OAI/sig-security/issues/36) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [security scheme apiKey in body form data parameter](https://github.com/OAI/sig-lifecycle/issues/9) — `OAI/sig-lifecycle` · `issue` · _security_
- `2026-08-21` [Add support OpenID Connect Hybrid Flow](https://github.com/OAI/sig-security/issues/34) — `OAI/sig-security` · `issue` · _spec,security_
- `2026-08-21` [Auth URL Variables](https://github.com/OAI/sig-security/issues/33) — `OAI/sig-security` · `issue` · _security_
- … and 10 more in this group (see All events table)

### Issue #410 kind discriminator / human-in-loop (15)

- `2026-09-08` [Merge pull request #39 from handrews/refactor/semver-parsing](https://github.com/OAI/build-infra/commit/1c143f71ba3f3d8e884436ccb3bb6396e4105d64) — `OAI/build-infra` · `commit` · _actor_
- `2026-09-08` [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-07` [build(deps): bump respec from 37.3.2 to 37.3.5](https://github.com/OAI/Overlay-Specification/pull/385) — `OAI/Overlay-Specification` · `pr` · _actor,depbump_
- `2026-09-06` [Richen document public face: DocumentInterface covers runner/cli/laravel needs](https://github.com/Mohammed-Alama/php-arazzo/issues/57) — `Mohammed-Alama/php-arazzo` · `issue` · _actor,spec_
- `2026-09-06` [Prefactor: contract-surface tooling — capability doc + @internal-aware public API](https://github.com/Mohammed-Alama/php-arazzo/issues/55) — `Mohammed-Alama/php-arazzo` · `issue` · _actor_
- `2026-09-03` [parser: export ParseError so callers can catch it by type](https://github.com/usearazzo/arazzo-toolkit/issues/140) — `usearazzo/arazzo-toolkit` · `issue` · _actor,spec_
- `2026-08-30` [refactor: Migrate Gulp build to GitHub Actions](https://github.com/OAI/tools.openapis.org/issues/289) — `OAI/tools.openapis.org` · `issue` · _actor_
- `2026-08-28` [feat(ecosystem): add Actor-in-the-Loop article](https://github.com/usearazzo/website/commit/930f2cfce8c1e3d5cd83c8f98341e44853db48c7) — `usearazzo/website` · `commit` · _actor_
- … and 7 more in this group (see All events table)

### P1-7 JSON Schema layer (15)

- `2026-09-06` [Seal real framework leaks behind facades (cebe, JSON-schema, cli Guzzle)](https://github.com/Mohammed-Alama/php-arazzo/issues/62) — `Mohammed-Alama/php-arazzo` · `issue` · _cli,schema,spec_
- `2026-09-04` [docs(plans): record blocked JSON Schema input design](https://github.com/strefethen/arazzo-cli/commit/7e5d96d981c3998ffa93879bba025491bf3a4feb) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,human,schema,spec_
- `2026-09-03` [docs(conformance): say what ac-93d90 actually settled and what is still open](https://github.com/strefethen/arazzo-cli/commit/1f75561c49843aafc91836c88bec3c822f9f8010) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,schema,spec_
- `2026-08-31` [Bump jmertic/lfx-landscape-tools from 20260625 to 20260826 in the all group (#193)](https://github.com/OAI/landscape/commit/8c128a7b3f32ff3b50815246017cb9d651ab88bf) — `OAI/landscape` · `commit` · _schema,depbump_
- `2026-08-11` [Revisit: should Overlays declare their target document format? (follow-up to #268)](https://github.com/OAI/Overlay-Specification/issues/367) — `OAI/Overlay-Specification` · `issue` · _schema,spec_
- `2026-08-10` [Merge pull request #540 from OAI/dependabot/npm_and_yarn/hyperjump/json-schema-1.17.8](https://github.com/OAI/Arazzo-Specification/commit/6f391e33b892c82f7cbb7b98dd01dd5fcaa3481b) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- `2026-08-10` [Add Diff Anything](https://github.com/OAI/tools.openapis.org/issues/281) — `OAI/tools.openapis.org` · `issue` · _cli,schema,spec_
- `2026-08-10` [chore(deps-dev): bump @hyperjump/json-schema from 1.17.7 to 1.17.8](https://github.com/OAI/Arazzo-Specification/commit/f2bd6542e4814df4050053f36380493f0853281b) — `OAI/Arazzo-Specification` · `commit` · _schema,depbump_
- … and 7 more in this group (see All events table)

### OAI Moonwalk (next-gen spec) (8)

- `2026-03-17` [Write ADR for identity vs location](https://github.com/OAI/sig-moonwalk/issues/92) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2025-11-12` [Create draft REST proposal](https://github.com/OAI/sig-moonwalk/pull/212) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-05-07` [Added preliminary design for resource model](https://github.com/OAI/sig-moonwalk/pull/183) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2025-02-23` [Added example of query parameter versioning](https://github.com/OAI/sig-moonwalk/pull/174) — `OAI/sig-moonwalk` · `pr` · _moonwalk_
- `2024-07-23` [Can the Data Types section be replaced by a reference to the format registry?](https://github.com/OAI/sig-moonwalk/issues/131) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Open API Path Templating vs WHATWG URL Pattern](https://github.com/OAI/sig-moonwalk/issues/125) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Allow recursive paths](https://github.com/OAI/sig-moonwalk/issues/117) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_
- `2024-05-24` [Structural improvements: inheritance on paths and its sublevels](https://github.com/OAI/sig-moonwalk/issues/115) — `OAI/sig-moonwalk` · `issue` · _moonwalk,spec_

### P2-2 MCP server exposure (5)

- `2026-09-04` [docs(plans): capture runtime HTTP decomposition draft](https://github.com/strefethen/arazzo-cli/commit/ab1c627148dfbc6fe1ae76c1f79f5af03cf01529) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,actor,spec_
- `2026-08-28` [feat(ecosystem): add HAPI MCP](https://github.com/usearazzo/website/commit/5e0ff2239f14afcf186d805c7ade84037772e4d8) — `usearazzo/website.ecosystem.atom` · `commit` · _mcp_
- `2026-08-26` [Fetch remote sourceDescriptions OpenAPI documents (opt-in)](https://github.com/strefethen/arazzo-cli/issues/4) — `strefethen/arazzo-cli` · `issue` · _mcp,cli,runner,spec_
- `2026-08-26` [feat(runtime): resolve source references against the $self base URI](https://github.com/strefethen/arazzo-cli/commit/f0adfeb5abc5e5ed4f200f6c3316cdc3b34aa020) — `strefethen/arazzo-cli` · `commit` · _mcp,cli,spec_
- `2026-07-23` [Add Routebase (OpenAPI-native API lifecycle platform)](https://github.com/OAI/tools.openapis.org/issues/270) — `OAI/tools.openapis.org` · `issue` · _mcp,spec_

### Arazzo runner / step execution (4)

- `2026-09-09` [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) — `OAI/Arazzo-Specification` · `pr` · _actor,human,runner,spec_
- `2026-09-03` [runner: migrate to @usearazzo/parser's parseRuntimeExpression / parseCriterionCondition](https://github.com/usearazzo/arazzo-toolkit/issues/131) — `usearazzo/arazzo-toolkit` · `issue` · _runner,spec_
- `2026-04-29` [fix: forward step response to action criteria evaluation context](https://github.com/jentic/arazzo-engine/pull/144) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_
- `2026-04-29` [fix: enforce retryLimit and correct step pointer on retry](https://github.com/jentic/arazzo-engine/pull/145) — `jentic/arazzo-engine` · `pr` · _loop,runner,spec_

### P1-6 payload XPath / P0-5 XPath criteria (4)

- `2026-09-09` [v3.3: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5540) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-09` [v3.2: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5541) — `OAI/OpenAPI-Specification` · `pr` · _xml,spec_
- `2026-09-07` [ci: add GitHub Actions workflow for documentation validation](https://github.com/OAI/OpenAPI-Specification/pull/5392) — `OAI/OpenAPI-Specification` · `pr` · _xml,schema,spec_
- `2026-02-04` [chore(deps): bump actions/cache from 4 to 5](https://github.com/jentic/arazzo-engine/pull/135) — `jentic/arazzo-engine` · `pr` · _xml,depbump_

### P0-5 XPath criteria + P1-6 targetSelectorType (3)

- `2026-09-06` [Richen expression public face: ExpressionEngineInterface covers document's needs](https://github.com/Mohammed-Alama/php-arazzo/issues/56) — `Mohammed-Alama/php-arazzo` · `issue` · _xml,xpath,actor_
- `2026-08-31` [XPath version identifier feels a bit confusing](https://github.com/OAI/Arazzo-Specification/issues/219) — `OAI/Arazzo-Specification` · `issue` · _xml,xpath,spec_
- `2024-05-24` [Ability to import datatype declarations from XSD files](https://github.com/OAI/sig-moonwalk/issues/123) — `OAI/sig-moonwalk` · `issue` · _xml,xpath,schema,moonwalk,spec_

### Roadmap GraphQL step type (2)

- `2026-09-03` [feat(spec): add GraphQL operation support](https://github.com/OAI/Arazzo-Specification/pull/567) — `OAI/Arazzo-Specification` · `pr` · _graphql,spec_
- `2026-08-28` [fix: restore full tool discovery](https://github.com/OAI/tools.openapis.org/pull/286) — `OAI/tools.openapis.org` · `pr` · _graphql,spec_

### P0-6 source routing (wsdl type) (1)

- `2026-09-06` [feat: XML payload support + XPath targetSelectorType (P1-6)](https://github.com/Mohammed-Alama/php-arazzo/issues/16) — `Mohammed-Alama/php-arazzo` · `issue` · _soap,xml,xpath,spec_

### Roadmap A2A step type (1)

- `2026-07-22` [build(deps): bump ruby/setup-ruby from 1.319.0 to 1.320.0](https://github.com/OAI/spec.openapis.org/commit/3ccc930eaa2a78c31ea19f09e0dbea2639b571ed) — `OAI/spec.openapis.org` · `commit` · _a2a,depbump_

### Roadmap gRPC step type (1)

- `2026-09-03` [feat(spec): add Protocol Buffer RPC support](https://github.com/OAI/Arazzo-Specification/pull/556) — `OAI/Arazzo-Specification` · `pr` · _grpc,spec_


## All events — newest 200

| Date | Source | Type | Title | Tags | Severity | Relevance |
|---|---|---|---|---|---|---|
| 2026-09-10 | openapi.tools | tool_collection | [openapi.tools checksum d6d39798f7de](https://openapi.tools/collections/arazzo) | spec | watch | Conformance / schema validation |
| 2026-09-10 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.2](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.1](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | frankkilcommins/arazzo2openapi | tag | [tag v1.0.0](https://github.com/frankkilcommins/arazzo2openapi/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.7](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.7) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.6](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.6) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.5](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.5) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.4](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.3](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.2](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | b-lab-io/pyarazzo | tag | [tag v0.0.1](https://github.com/b-lab-io/pyarazzo/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | JaredCE/Arazzo-Generator | tag | [tag 0.0.4](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.4) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | JaredCE/Arazzo-Generator | tag | [tag 0.0.3](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | JaredCE/Arazzo-Generator | tag | [tag 0.0.2](https://github.com/JaredCE/Arazzo-Generator/releases/tag/0.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.2.2](https://github.com/speclynx/apidom/releases/tag/v5.2.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.2.1](https://github.com/speclynx/apidom/releases/tag/v5.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.2.0](https://github.com/speclynx/apidom/releases/tag/v5.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.1.1](https://github.com/speclynx/apidom/releases/tag/v5.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.1.0](https://github.com/speclynx/apidom/releases/tag/v5.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.0.2](https://github.com/speclynx/apidom/releases/tag/v5.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.0.1](https://github.com/speclynx/apidom/releases/tag/v5.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v5.0.0](https://github.com/speclynx/apidom/releases/tag/v5.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.16.0](https://github.com/speclynx/apidom/releases/tag/v4.16.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.15.0](https://github.com/speclynx/apidom/releases/tag/v4.15.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.14.0](https://github.com/speclynx/apidom/releases/tag/v4.14.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.13.0](https://github.com/speclynx/apidom/releases/tag/v4.13.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.12.1](https://github.com/speclynx/apidom/releases/tag/v4.12.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.12.0](https://github.com/speclynx/apidom/releases/tag/v4.12.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.11.1](https://github.com/speclynx/apidom/releases/tag/v4.11.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.11.0](https://github.com/speclynx/apidom/releases/tag/v4.11.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.10.1](https://github.com/speclynx/apidom/releases/tag/v4.10.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.10.0](https://github.com/speclynx/apidom/releases/tag/v4.10.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.9.1](https://github.com/speclynx/apidom/releases/tag/v4.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | speclynx/apidom | tag | [tag v4.9.0](https://github.com/speclynx/apidom/releases/tag/v4.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-criterion | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-criterion/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.2.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.1.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v3.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v3.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.3](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.3) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.2](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v2.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v2.0.0) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.1](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | swaggerexpert/arazzo-runtime-expression | tag | [tag v1.0.0](https://github.com/swaggerexpert/arazzo-runtime-expression/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.32](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.32) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.31](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.31) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.30](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.30) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.29](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.29) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.28](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.28) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.27](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.27) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.26](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.26) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.25](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.25) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.24](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.24) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.23](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.23) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.22](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.22) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.21](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.21) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.20](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.20) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.19](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.19) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.18](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.18) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.17](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.17) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.16](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.16) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.15](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.15) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.14](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.14) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/jentic-arazzo-tools | tag | [tag v1.0.0-alpha.13](https://github.com/jentic/jentic-arazzo-tools/releases/tag/v1.0.0-alpha.13) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag v0.0.1](https://github.com/Specmatic/specmatic/releases/tag/v0.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.54.1](https://github.com/Specmatic/specmatic/releases/tag/2.54.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.54.0](https://github.com/Specmatic/specmatic/releases/tag/2.54.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.53.1](https://github.com/Specmatic/specmatic/releases/tag/2.53.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.53.0](https://github.com/Specmatic/specmatic/releases/tag/2.53.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.52.0](https://github.com/Specmatic/specmatic/releases/tag/2.52.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.51.1](https://github.com/Specmatic/specmatic/releases/tag/2.51.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.51.0](https://github.com/Specmatic/specmatic/releases/tag/2.51.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.50.1](https://github.com/Specmatic/specmatic/releases/tag/2.50.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.50.0](https://github.com/Specmatic/specmatic/releases/tag/2.50.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.49.1](https://github.com/Specmatic/specmatic/releases/tag/2.49.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.49.0](https://github.com/Specmatic/specmatic/releases/tag/2.49.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.48.0](https://github.com/Specmatic/specmatic/releases/tag/2.48.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.47.0](https://github.com/Specmatic/specmatic/releases/tag/2.47.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.5](https://github.com/Specmatic/specmatic/releases/tag/2.46.5) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.4](https://github.com/Specmatic/specmatic/releases/tag/2.46.4) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.3](https://github.com/Specmatic/specmatic/releases/tag/2.46.3) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.2](https://github.com/Specmatic/specmatic/releases/tag/2.46.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.1](https://github.com/Specmatic/specmatic/releases/tag/2.46.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Specmatic/specmatic | tag | [tag 2.46.0](https://github.com/Specmatic/specmatic/releases/tag/2.46.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.3](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.3) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.2](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-rc.1](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-rc.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.131](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.131) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.130](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.130) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.129](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.129) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.128](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.128) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.127](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.127) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.126](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.126) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.125](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.125) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.124](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.124) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.123](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.123) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.122](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.122) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.121](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.121) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.120](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.120) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.119](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.119) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.118](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.118) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.117](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.117) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | Redocly/redocly-cli | tag | [tag v1.0.0-beta.116](https://github.com/Redocly/redocly-cli/releases/tag/v1.0.0-beta.116) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.6](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.6) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag vscode-v0.0.5](https://github.com/strefethen/arazzo-cli/releases/tag/vscode-v0.0.5) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.6.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.6.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.5.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.5.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.4.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.4.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.3.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.3.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.2.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.2.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.2.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.2.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.1.3](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.3) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.1.2](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.2) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.1.1](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.1) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | strefethen/arazzo-cli | tag | [tag v0.1.0](https://github.com/strefethen/arazzo-cli/releases/tag/v0.1.0) | cli | actionable | P2-1 CLI binary |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.5](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.5) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_runner/v0.9.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_runner/v0.9.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.2.0](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.2.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.2](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | jentic/arazzo-engine | tag | [tag arazzo_generator/v0.1.1](https://github.com/jentic/arazzo-engine/releases/tag/arazzo_generator/v0.1.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | usearazzo/arazzo-toolkit | tag | [tag v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | OAI/Arazzo-Specification | tag | [tag 1.1.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.1.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | OAI/Arazzo-Specification | tag | [tag 1.0.1](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.1) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | OAI/Arazzo-Specification | tag | [tag 1.0.0](https://github.com/OAI/Arazzo-Specification/releases/tag/1.0.0) | spec | actionable | Conformance / schema validation |
| 2026-09-10 | OAI/tools.openapis.org | issue | [^R»05<^O*ÿ^M½^O}^\] £^_Mº^\O](https://github.com/OAI/tools.openapis.org/issues/292) | spec | watch | Conformance / schema validation |
| 2026-09-10 | OAI/OpenAPI-Specification | pr | [v3.3: define an explicit default for the top-level "security" object](https://github.com/OAI/OpenAPI-Specification/pull/5536) |  | watch |  |
| 2026-09-10 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22786)](https://github.com/jentic/jentic-public-apis/commit/e5b354b4e7b6e37b79e78d54e4fe1054fc9432f4) |  | watch |  |
| 2026-09-09 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-09 (#200)](https://github.com/OAI/landscape/commit/a1f8de2112d45b8c127f078cf5cf1c6cd63badc2) |  | watch |  |
| 2026-09-09 | OAI/build-infra | pr | [Install qualification runner dependencies](https://github.com/OAI/build-infra/pull/43) |  | watch |  |
| 2026-09-09 | OAI/build-infra | pr | [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/pull/42) | actor, breaking, runner, depbump | breaking | Arazzo runner / step execution |
| 2026-09-09 | OAI/build-infra | commit | [Merge pull request #42 from OAI/dependabot/npm_and_yarn/vitest-8ab2bcc523](https://github.com/OAI/build-infra/commit/34fdfa9f0019f440f5ee184e04259081cb16ce07) | depbump | watch | Dependency maintenance |
| 2026-09-09 | OAI/OpenAPI-Specification | pr | [v3.3: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5540) | xml, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-09 | OAI/Overlay-Specification | issue | [VOTE: Approve release of OpenAPI Overlay Specification v1.2](https://github.com/OAI/Overlay-Specification/issues/392) | spec | watch | Conformance / schema validation |
| 2026-09-09 | OAI/learn.openapis.org | pr | [docs: adds an upgrade guide for overlay 1.2](https://github.com/OAI/learn.openapis.org/pull/206) | spec | watch | Conformance / schema validation |
| 2026-09-09 | OAI/Overlay-Specification | pr | [DO NOT MERGE - release notes for 1.2](https://github.com/OAI/Overlay-Specification/pull/378) |  | watch |  |
| 2026-09-09 | OAI/OpenAPI-Specification | pr | [v3.2: define defaults for "nodeType" in the XML Object](https://github.com/OAI/OpenAPI-Specification/pull/5541) | xml, spec | watch | P1-6 payload XPath / P0-5 XPath criteria |
| 2026-09-09 | OAI/Overlay-Specification | issue | [Branching strategy in CONTRIBUTING.md is outdated (I think)](https://github.com/OAI/Overlay-Specification/issues/393) | spec | watch | Conformance / schema validation |
| 2026-09-09 | OAI/build-infra | commit | [Bump the vitest group across 1 directory with 3 updates](https://github.com/OAI/build-infra/commit/a0537fe5db1b78650f1b007bd0547b082e168b73) | depbump | watch | Dependency maintenance |
| 2026-09-09 | OAI/build-infra | pr | [Bump the vitest group across 1 directory with 2 updates](https://github.com/OAI/build-infra/pull/40) | breaking, depbump | breaking | Dependency maintenance |
| 2026-09-09 | OAI/build-infra | pr | [Fix/vitest vite peer](https://github.com/OAI/build-infra/pull/41) |  | actionable |  |
| 2026-09-09 | OAI/build-infra | commit | [Merge pull request #41 from handrews/fix/vitest-vite-peer](https://github.com/OAI/build-infra/commit/302422355ad9f38658a66637ac8a4b84791a6a2c) |  | watch |  |
| 2026-09-09 | Redocly/redocly-cli | release | [@redocly/respect-core@1.34.20](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/respect-core%401.34.20) | spec | actionable | Conformance / schema validation |
| 2026-09-09 | Redocly/redocly-cli | release | [@redocly/openapi-core@1.34.20](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/openapi-core%401.34.20) | spec | actionable | Conformance / schema validation |
| 2026-09-09 | Redocly/redocly-cli | release | [@redocly/cli@1.34.20](https://github.com/Redocly/redocly-cli/releases/tag/%40redocly/cli%401.34.20) | spec | actionable | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Remove duplicate idtbeyond.com spec folders (#22775)](https://github.com/jentic/jentic-public-apis/commit/4b4c7df2fb851d3cbf9693bc8cd10301965e3d4c) | spec | watch | Conformance / schema validation |
| 2026-09-09 | OAI/OpenAPI-Specification | pr | [v3.2: warn that multiple document logic may change](https://github.com/OAI/OpenAPI-Specification/pull/5535) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22774)](https://github.com/jentic/jentic-public-apis/commit/84c552c8f1b6b8093ef0c6021bacb4e115e064e6) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22690 (#22691)](https://github.com/jentic/jentic-public-apis/commit/8204a517923e926f736e479f0a05567855f3b221) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22773)](https://github.com/jentic/jentic-public-apis/commit/38b5840b1589a0c2c4b04ec949762b39b34942e9) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22355 (#22357)](https://github.com/jentic/jentic-public-apis/commit/c1a95784b575e9ab1407b859c8ca6879fdc00f68) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22772)](https://github.com/jentic/jentic-public-apis/commit/6a4381e56d2a68d39df207190e25b13966185690) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22711 (#22713)](https://github.com/jentic/jentic-public-apis/commit/9c511d1e5b4c4b856ab5007b84547c04ba510c0e) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22771)](https://github.com/jentic/jentic-public-apis/commit/67abf2d1d411330585ddd02d11b5df835da26b36) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22367 (#22368)](https://github.com/jentic/jentic-public-apis/commit/5cbc0bb2dfd4ed4260ed5cd4f0cd34f2843c705f) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22770)](https://github.com/jentic/jentic-public-apis/commit/ef218007454a1beb28e1cb9361fa36641da78314) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22714 (#22715)](https://github.com/jentic/jentic-public-apis/commit/291842b25be9e466728a61c0154abcade7e5776f) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22145 (#22146)](https://github.com/jentic/jentic-public-apis/commit/0cda9f5b1dc53e91b536769110e416b3f45d2dbd) | spec | watch | Conformance / schema validation |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22769)](https://github.com/jentic/jentic-public-apis/commit/11933a423cd4ba54d0324de4a486ef9b8a0785ff) |  | watch |  |
| 2026-09-09 | jentic/jentic-public-apis | commit | [feat: Import OpenAPI spec from Issue #22730 (#22731)](https://github.com/jentic/jentic-public-apis/commit/693566bb45f49239bb2de626a0e1619bfc2d7641) | spec | watch | Conformance / schema validation |
| 2026-09-09 | usearazzo/website | commit | [feat(docs): run the dependencies tutorial in the browser](https://github.com/usearazzo/website/commit/9503b33b6c9febf006b7cd104da20d3aa3bc1cc1) |  | watch |  |
| 2026-09-09 | OAI/Arazzo-Specification | pr | [feat(spec): add actor-in-the-loop support](https://github.com/OAI/Arazzo-Specification/pull/568) | actor, human, runner, spec | watch | Arazzo runner / step execution |
| 2026-09-09 | jentic/jentic-public-apis | commit | [Rebuild apis.json, scores.json, and API browsing indexes (#22750)](https://github.com/jentic/jentic-public-apis/commit/ac4462f18d23ca04ebe5dd8066e965301ab1fcb2) |  | watch |  |
| 2026-09-08 | OAI/landscape | commit | [Update Landscape from LFX 2026-09-08 (#199)](https://github.com/OAI/landscape/commit/052440babe27990b9a1556c6a574bfb809632102) |  | watch |  |
| 2026-09-08 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump lint-staged from 17.4.1 to 17.5.0](https://github.com/usearazzo/arazzo-toolkit/pull/149) | actor, depbump | actionable | Issue #410 kind discriminator / human-in-loop |
| 2026-09-08 | OAI/OpenAPI-Specification | issue | [Open Community (TDC) Meeting, Thursday 10 September 2026](https://github.com/OAI/OpenAPI-Specification/issues/5532) | spec | watch | Conformance / schema validation |
| 2026-09-08 | OAI/build-infra | commit | [Bump version number to prep for release.](https://github.com/OAI/build-infra/commit/4b19eb634f8daf598feeeff9e5cd6d59dee95012) | depbump | watch | Dependency maintenance |
| 2026-09-08 | OAI/build-infra | commit | [Declare Vitest's Vite peer dependency](https://github.com/OAI/build-infra/commit/c28cd39c580bbc4121498929ad47bcba20e98475) |  | watch |  |
| 2026-09-08 | OAI/build-infra | issue | [Evaluate using semver package instead of bespoke regex to parse semver versions](https://github.com/OAI/build-infra/issues/37) |  | watch |  |
| 2026-09-08 | OAI/build-infra | pr | [Use semver for release version parsing](https://github.com/OAI/build-infra/pull/39) |  | actionable |  |
| 2026-09-08 | OAI/build-infra | commit | [Merge pull request #39 from handrews/refactor/semver-parsing](https://github.com/OAI/build-infra/commit/1c143f71ba3f3d8e884436ccb3bb6396e4105d64) | actor | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-08 | OAI/OpenAPI-Specification | pr | [v3.3: Add a global parameters field](https://github.com/OAI/OpenAPI-Specification/pull/5446) |  | watch |  |
| 2026-09-08 | OAI/Overlay-Specification | pr | [1.2-dev refresh from dev](https://github.com/OAI/Overlay-Specification/pull/391) |  | actionable |  |
| 2026-09-08 | OAI/Overlay-Specification | pr | [dev refresh from main](https://github.com/OAI/Overlay-Specification/pull/390) |  | actionable |  |
| 2026-09-08 | OAI/Overlay-Specification | pr | [build(deps): bump respec from 37.3.2 to 37.3.6](https://github.com/OAI/Overlay-Specification/pull/389) | actor, depbump | watch | Issue #410 kind discriminator / human-in-loop |
| 2026-09-08 | OAI/Overlay-Specification | pr | [build(deps): bump markdown-it from 15.0.0 to 15.0.1](https://github.com/OAI/Overlay-Specification/pull/386) | depbump | watch | Dependency maintenance |
| 2026-09-08 | OAI/Overlay-Specification | pr | [build(deps-dev): bump vitest from 4.1.10 to 4.1.11 in the vitest group](https://github.com/OAI/Overlay-Specification/pull/384) | depbump | watch | Dependency maintenance |
| 2026-09-08 | OAI/Overlay-Specification | issue | [Prepare Overlay repository for new Build process](https://github.com/OAI/Overlay-Specification/issues/369) |  | watch |  |
| 2026-09-08 | OAI/Overlay-Specification | pr | [Prepare for new Build Infra](https://github.com/OAI/Overlay-Specification/pull/379) |  | actionable |  |
| 2026-09-08 | Mohammed-Alama/php-arazzo | issue | [@internal sweep: hide all non-face interfaces + implementations from the public API](https://github.com/Mohammed-Alama/php-arazzo/issues/63) | cli, actor | watch | P2-1 CLI binary |
| 2026-09-08 | Mohammed-Alama/php-arazzo | pr | [chore(docs): mark implementation classes and non-public-face interfaces as @internal (refs #63)](https://github.com/Mohammed-Alama/php-arazzo/pull/71) |  | actionable |  |
| 2026-09-08 | OAI/Arazzo-Specification | issue | [1.2 proposal: Function Object and functionId step target (MCP tools, CLI commands, and other calls with no source description)](https://github.com/OAI/Arazzo-Specification/issues/523) | mcp, cli, human, loop, breaking, schema, runner, spec | breaking | P1-7 JSON Schema layer |
| 2026-09-08 | npm.@usearazzo/parser | release | [@usearazzo/parser@1.0.1-alpha.2](https://www.npmjs.com/package/@usearazzo/parser/v/1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-08 | usearazzo/arazzo-toolkit | release | [v1.0.1-alpha.2](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.2) | spec | actionable | Conformance / schema validation |
| 2026-09-08 | usearazzo/arazzo-toolkit | pr | [fix(parser): resolve relative file paths against working directory](https://github.com/usearazzo/arazzo-toolkit/pull/148) | breaking, spec | breaking | Conformance / schema validation |
| 2026-09-08 | usearazzo/arazzo-toolkit | issue | [parser: relative file path as parseArazzo source breaks relative source description URLs](https://github.com/usearazzo/arazzo-toolkit/issues/147) | spec | watch | Conformance / schema validation |
| 2026-09-08 | usearazzo/website | commit | [docs: drop concrete package versions from the site](https://github.com/usearazzo/website/commit/9fcd8c90551af3526c5f35381c9ff97a3db20f79) |  | watch |  |
| 2026-09-08 | usearazzo/website | commit | [feat(blog): announce @usearazzo/parser on npm](https://github.com/usearazzo/website/commit/83d4a2c8adf0fbb5b94d017ff92f2c5f8aefb69e) | spec | watch | Conformance / schema validation |
| 2026-09-08 | usearazzo/website | commit | [feat(docs): add Tutorials section and the first parser tutorial](https://github.com/usearazzo/website/commit/853052231722a39c05718eb6e868d2d0263d1e49) | spec | watch | Conformance / schema validation |
| 2026-09-08 | usearazzo/website | commit | [docs: bump parser reference to 1.0.1-alpha.1](https://github.com/usearazzo/website/commit/9aea56618dcfcc92d5102a29dd39aea820839423) | depbump | watch | Dependency maintenance |
| 2026-09-08 | usearazzo/arazzo-toolkit | issue | [parser: make README light and deffer info to usearazzo.com/docs](https://github.com/usearazzo/arazzo-toolkit/issues/143) | spec | watch | Conformance / schema validation |
| 2026-09-08 | usearazzo/arazzo-toolkit | release | [v1.0.1-alpha.1](https://github.com/usearazzo/arazzo-toolkit/releases/tag/v1.0.1-alpha.1) | spec | actionable | Conformance / schema validation |
| 2026-09-08 | usearazzo/website | commit | [docs: link the parsing guide to the parser API reference](https://github.com/usearazzo/website/commit/083229aaf69d4285b3c9b136536d64dc68623937) |  | watch |  |
| 2026-09-08 | usearazzo/arazzo-toolkit | pr | [chore(deps-dev): bump mocha from 11.8.0 to 12.0.0](https://github.com/usearazzo/arazzo-toolkit/pull/144) | cli, depbump | actionable | P2-1 CLI binary |

## How to use

- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)
- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`
- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`
- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`
- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json`
