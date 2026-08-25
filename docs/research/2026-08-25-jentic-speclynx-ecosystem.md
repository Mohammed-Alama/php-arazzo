# Ecosystem research: Jentic, SpecLynx & other Arazzo tooling

**Date:** 2026-08-25
**Purpose:** Close capability gaps in php-arazzo (`alama/arazzo-core` + `alama/laravel-arazzo`) by studying what competing/adjacent Arazzo tools actually implement.
**Method:** Primary sources — GitHub repos/READMEs, docs sites, npm/PyPI packages, and (where claims mattered) direct grep of cloned source (`jentic/arazzo-engine`, `jentic/jentic-arazzo-tools` @ shallow clones, 2026-08-25).

---

> **Related internal tooling:** the repo also runs an automated
> [ecosystem feed](../ECOSYSTEM_FEED.md) (`php scripts/ecosystem/poll.php`) polling 54 GitHub
> sources — including `jentic/arazzo-engine` and `strefethen/arazzo-cli` — with severity/relevance
> triage mapped to roadmap items. This document is the deep-dive companion to that continuous feed.

## 1. Jentic

Jentic ships **three separate Arazzo-relevant efforts**: a Python engine (runner + generator), a TypeScript toolkit (parse/validate/resolve/run/render), and a giant open corpus of APIs + generated workflows.

### 1.1 `jentic/arazzo-engine` (Python) — Runner + Generator

Repo: <https://github.com/jentic/arazzo-engine> · PyPI: [`arazzo-runner`](https://pypi.org/project/arazzo-runner/) · Docs: <https://docs.jentic.com/cli/arazzo-runner/>
Apache-2.0. Beta. Latest runner release v0.9.x. CLI + Python library.

#### Runner features (verified against README + source)

| Axis | What it does |
|---|---|
| Execution modes | `execute-workflow`; **`execute-operation`** (run a single OpenAPI operation by `operationId` or `"GET /path"` with no workflow at all); step-by-step engine loop (`start_workflow` / `execute_next_step`) |
| Expression support | Runtime expressions with dot-notation, array indexing, object/array expressions ([components.md](https://github.com/jentic/arazzo-engine/blob/main/runner/components.md)) |
| successCriteria types | `simple`, `jsonpath`, `regex` implemented; **`xpath` is stubbed — "XPath evaluation not implemented"** (`runner/arazzo_runner/executor/success_criteria.py:172`) |
| Source handling | Local file path (+ configurable base path); remote URLs; no POST-based fetching anywhere in the codebase |
| Callbacks | **Does NOT implement Arazzo spec `callbacks`.** Its "callbacks" are lifecycle event listeners (`step_start`, `step_complete`, `workflow_start`, `workflow_complete`) via `register_callback` (`runner.py:109-202`) |
| x- extensions | No generic x- extension handling (only incidental strings like `x-api-key` in fixtures) |
| Outputs / flow control | Full action set: `continue`, `goto` (step), `retry` (with `retryAfter` + `retryLimit`, and retry targeting a `stepId`/`workflowId`), `end` ([action_handler.py](https://github.com/jentic/arazzo-engine/blob/main/runner/arazzo_runner/executor/action_handler.py)) |
| Retry/backoff | `retryAfter` delay + `retryLimit`; **no exponential backoff** found |
| Auth | API key (header/query/cookie), OAuth2 *client-credentials + password* flows, HTTP Basic/Bearer. Not supported: OAuth auth-code/implicit, OIDC, custom schemes. Credentials resolved from env vars; `show-env-mappings` CLI prints required env var names |
| Server URLs | Dynamic templated server variable resolution with precedence runtime → env → default, and a documented env naming convention (`PREFIX_RUNNER_SERVER_VAR`) ([README §Server URL Configuration](https://github.com/jentic/arazzo-engine/blob/main/runner/README.md)) |
| Blob storage | Optional `LocalFileBlobStore` / `InMemoryBlobStore` offloads large binary responses out of memory/LLM context; threshold via `ARAZZO_BLOB_THRESHOLD`; disabled by default |
| Inputs schema validation | **No** JSON Schema validation of workflow `inputs` against the workflow's `inputs` schema (no jsonschema usage in the execution path) |
| Testing/mock support | Ships an **OpenAPI-driven HTTP mocker** for tests (`tests/mocks/openapi_mocker.py`), custom per-endpoint mock handlers, and request-count assertions — a mock transport you can run workflows against instead of live APIs |
| Spec versions | Arazzo **1.0.0 / 1.0.1 only** (no 1.1) |

CLI surface: `execute-workflow`, `execute-operation`, `list-workflows`, `describe-workflow`, **`generate-example`** (emits a ready-to-run example CLI invocation incl. placeholder inputs), `show-env-mappings`.

#### Generator features

Repo: [generator/README.md](https://github.com/jentic/arazzo-engine/blob/main/generator/README.md) · PyPI: `arazzo-generator`

- **LLM-powered workflow generation from OpenAPI specs** (OpenAI / Anthropic / Gemini via LiteLLM): identifies meaningful user-journey sequences, writes compliant Arazzo docs.
- Batch mode over many specs; REST API server mode (`POST /generate`, Docker image on GHCR); built-in validator command.
- Powers the automated generation pipeline of [jentic-public-apis](https://github.com/jentic/jentic-public-apis).

### 1.2 `jentic/jentic-arazzo-tools` (TypeScript monorepo)

Repo: <https://github.com/jentic/jentic-arazzo-tools> · Apache-2.0
Supported: **Arazzo 1.0.0 / 1.0.1**; OpenAPI 2.0 / 3.0.x / 3.1.x as sources.

| Package | Features |
|---|---|
| `@jentic/arazzo-parser` | Parse Arazzo from JS object / JSON-YAML string / file / URL into **SpecLynx ApiDOM** data model |
| `@jentic/arazzo-resolver` | Dereference Arazzo & OpenAPI docs inline; optional `sourceDescriptions` dereferencing strategy |
| `@jentic/arazzo-validator` | JSON Schema (AJV) validation + semantic validation via SpecLynx ApiDOM Language Service + semantic linting. CLI output formats: **`stylish`, `codeframe`, `json`, `github-actions`**; programmatic diagnostics with severities ([validator package](https://github.com/jentic/jentic-arazzo-tools/tree/main/packages/jentic-arazzo-validator)) |
| `@jentic/arazzo-runner` | **Unpublished beta** ([package README](https://github.com/jentic/jentic-arazzo-tools/tree/main/packages/jentic-arazzo-runner)). Layered design: `DocumentRegistry` (load+cache) → `WorkflowExecutor` → `StepExecutor` → `OpenAPIOperationExecutor` → injectable `OpenAPIClientSwagger` client factory (swagger-client). Notable: `maxSteps` runaway budget (**default 1000, counts every attempt including retries**) with `step-budget` error; **injectable `sleep`** so tests don't wait; workflow-level default `successActions`/`failureActions` with wholesale override semantics; per-step `attempts` in result trace; crisp authoring-error vs failed-run distinction. **Explicitly NOT supported yet:** sub-workflows, `dependsOn`, cross-document workflows, retry references, workflow-level parameters, callbacks |
| `@jentic/arazzo-ui` | React component + hosted viewer (<https://arazzo-ui.jentic.com>): diagram / documentation / split views of any Arazzo doc by URL |

### 1.3 `jentic/jentic-public-apis`

Repo: <https://github.com/jentic/jentic-public-apis> · CC0-1.0

- Massive standardized directory (~10k public APIs claimed) of repaired OpenAPI specs **plus thousands of AI-generated Arazzo workflows**, indexed by vendor, with feedback files tracking spec repairs.
- Ships an "API scorecard" AI-readiness scoring CLI (`npx @jentic/api-scorecard-cli`). RFCs planned for agent-oriented extensions (auth, rate limits, pricing, safety).
- Relevance to php-arazzo: this is the largest available **real-world conformance corpus** beyond the official OAI examples we already run — thousands of machine-generated-but-varied documents to fuzz our parser/validator against.

---

## 2. SpecLynx

Site: <https://speclynx.com> · GitHub org: <https://github.com/speclynx>

**What it is:** the commercial steward of **ApiDOM** (the semantic parser/data-model that underpins Redocly/vacuum-era tooling; `speclynx/apidom` is the maintained continuation) plus a product line around it. It is a **parser/validator/editor-intelligence company — not a runner**. TypeScript throughout.

| Product | What it is | Arazzo relevance |
|---|---|---|
| [ApiDOM](https://speclynx.com/apidom/) | Semantic parse of OpenAPI 2/3.0/3.1, AsyncAPI 2.x, **Arazzo 1.0.0/1.0.1** ([ns package](https://github.com/speclynx/apidom/tree/main/packages/apidom-ns-arazzo-1)), Overlay 1.0/1.1, JSON Schema drafts 4–2020-12 into one traversable model with namespaces | The de-facto TS data model; Jentic's parser is built on it |
| [Language Service](https://speclynx.com/language-service/) | LSP library: validation, autocompletion, hover docs, go-to-definition — works for **Arazzo** too | Editor intelligence php-arazzo has no analogue for |
| [CLI `@speclynx/cli`](https://speclynx.com/cli/) | `validate` (semantic + `$ref` resolution + best-practice lint layers; **optional `--json-schema-validation` AJV pass**; `--fail-severity error\|warning\|info\|hint`; `--max-problems`; `stylish`/`json` output; `-o report file`; CI-friendly exit codes distinguishing "invalid doc" vs "tool failure"), `overlay apply` (chainable, `--strict` fail-on-zero-matches), `overlay diff` (`--fail-on-empty` for CI). Roadmap: dereference, bundle, convert | Closest competitor to our validator CLI. Notable ideas: severity gating flag, explicit exit-code contract, Overlay apply/diff |

No execution, no SARIF, no HAR, no mocking. Spec versions: Arazzo 1.0.x only (no 1.1).

---

## 3. Other notable tools (one-liners)

- **respect-cli (Redocly)** — <https://redocly.com/docs/respect/commands/respect>: Arazzo-as-contract-test runner; validates live responses against the linked OpenAPI (per-check severity gates `STATUS_CODE_CHECK` / `SCHEMA_CHECK` / `CRITERIA_CHECK` / `CONTENT_TYPE_CHECK` = `error|warn|off`); **HAR export** (`--har-output`), JSON results (`--json-output`), secrets masking (`format: password` scrubbing, `--no-secrets-masking`), per-sourceDescription `--server name=url` overrides, nested `--input key=value|JSON`, `--workflow`/`--skip` selection, `--max-steps`, `--max-fetch-timeout`/`--execution-timeout`, `generate-arazzo` starter-test scaffolding from OpenAPI. **Arazzo 1.0.1 only.**
- **arazzo-cli (strefethen, Rust)** — <https://github.com/strefethen/arazzo-cli>: standalone executor whose differentiators are an **MCP server** (`arazzo serve`: `list_workflows`, `describe_workflow`, `run_workflow` with **`dry_run`/parallel/timeout**, `validate_spec`, `describe_openapi`, `generate_workflow` from natural language, `generate_example` from JSON Schema), a **DAP debug adapter** (VS Code breakpoints/step-through of workflows), trace recording, token-bucket rate limiting, `.env` loading with `$env.VAR_NAME`, and `arazzo schema` (JSON Schema for every command's `--json` output).
- **Itarazzo (leidenheit, Java)** — <https://github.com/leidenheit/itarazzo-library> + [Dockerized client](https://github.com/leidenheit/itarazzo-client): runs Arazzo suites as **JUnit 5 dynamic tests** inside Maven builds; container-based execution driven purely by env vars; RestAssured HTTP stack; XPath validation for XML APIs; custom `x-itarazzo-designated-server` extension for server selection.
- **Specmatic** — <https://specmatic.io>: authoring/generation/**mocking** (contract-test mock server) and testing of Arazzo workflows.
- **swaggerexpert/arazzo-runtime-expression** — dedicated runtime-expression parser/validator/extractor (useful reference for expression grammar edge cases).
- **Converters:** [arazzo2openapi](https://frankkilcommins.github.io/arazzo2openapi) (Arazzo→OpenAPI w/ type inference), [pyarazzo](https://github.com/b-lab-io/pyarazzo) (Arazzo→Markdown/PlantUML).

---

## 4. Cross-cutting matrix (who has what)

Legend: ✅ yes · ⚠️ partial/stubbed · ❌ no · 🚧 beta/unpublished

| Capability | php-arazzo (today) | jentic engine (Py) | jentic tools (TS) | respect-cli | arazzo-cli (Rust) | Itarazzo | SpecLynx |
|---|---|---|---|---|---|---|---|
| Parse+validate 1.0 & **1.1** | ✅ (49 rules + official JSON Schema layer) | 1.0 only | 1.0 only | 1.0.1 only | 1.0 | 1.0.x | 1.0.x |
| Execution engine | ✅ sync + Laravel queue durable | ✅ | 🚧 unpublished | ✅ | ✅ | ✅ (JUnit) | ❌ |
| Sub-workflows / cross-doc | ✅ | ⚠️ | ❌ explicit | ⚠️ | ⚠️ | ? | n/a |
| AsyncAPI send/receive + correlations | ✅ | ❌ | ❌ | ❌ | ❌ | ? | parse only |
| Response validated vs OpenAPI during run | ❌ | ❌ | ❌ | ✅ per-check severity gates | ❌ | ⚠️ RestAssured matchers | n/a |
| Workflow `inputs` schema pre-validation | ❌ (opportunity) | ❌ | ❌ | ❌ | ❌ | ❌ | n/a |
| Retry + Retry-After + backoff | ✅ | ⚠️ fixed delay | ✅ retryAfter fixed | n/a (criteria) | ✅ | ? | n/a |
| Step budget / runaway guard | ✅ maxSteps=1000 shared across retries+nested (StepBudgetExceededException) | ❌ | ✅ maxSteps=1000 | ✅ --max-steps | ✅ | ? | n/a |
| Dry-run / mock transport | ❌ | ✅ OpenAPI mocker (test fw) | ❌ | ❌ | ✅ dry_run | ❌ | n/a |
| Auth security-scheme application + env mappings | ❌ | ✅ | ⚠️ via swagger-client | ⚠️ manual headers | ✅ .env/$env | ⚠️ | n/a |
| Server overrides / templated server vars | ❌ | ✅ | ⚠️ contextUrl | ✅ --server overrides | ⚠️ | ✅ x-extension | n/a |
| HAR export | ❌ | ❌ | ❌ | ✅ | ⚠️ trace recording | ❌ | n/a |
| JSON run reports / CI formatters | ❌ | ⚠️ logs | ✅ validator: json/github-actions | ✅ --json-output | ✅ +schema cmd | ⚠️ JUnit XML | ✅ validate: json + --fail-severity |
| SARIF | ❌ | ❌ | ❌ | ❌ ([open request on redocly lint](https://github.com/Redocly/redocly-cli/issues/1615)) | ❌ | ❌ | ❌ |
| Postman export | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Coverage reporting (% ops/codes exercised) | ❌ | ❌ | ❌ | ⚠️ gap-finding marketing | ❌ | ❌ | n/a |
| Watch mode | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Arazzo spec `callbacks` execution | ❌ | ❌ (only lifecycle listeners) | ❌ | ❌ | ❌ | ❌ | n/a |
| x- sub-spec embedding | ❌ | ❌ | ❌ | ❌ | ❌ | ⚠️ one bespoke x-ext | n/a |
| POST-based source resolution | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Generation (OpenAPI→Arazzo) | ❌ | ✅ LLM + batch + API | ❌ | ✅ deterministic starter | ✅ NL + CRUD + examples | ❌ | ❌ |
| MCP / agent integration | ❌ | ⚠️ positions runner for agents | ❌ | ❌ | ✅ MCP server | ❌ | ❌ |
| Conformance harness vs official examples | ✅ published matrix | ⚠️ fixtures | ⚠️ | ❌ | ⚠️ tests | ⚠️ | ⚠️ |
| Interactive UI/diagram | ⚠️ mermaid render | ❌ | ✅ arazzo-ui React | ❌ | ❌ | ❌ | ✅ editor |

---

## 5. Gap analysis for php-arazzo

Already covered (do **not** re-recommend): full 1.0/1.1 parser + 49-rule validator + official JSON-schema structural layer; sync + Laravel-queue durable execution; retry w/ Retry-After + backoff; implicit dependencies; ms timeouts; deepObject serialization; payload replacements w/ targetSelectorType; preflight validation; PSR-14 events + ledger; sub-workflows; asyncapi send/receive w/ correlations; golden-fixture conformance harness on official OAI examples w/ published matrix; property tests; `bin/arazzo` (validate/list-workflows/explain/run/render md+mermaid); no license gate.

### Prioritized recommendations

#### P1 — Contract mode: validate responses against the linked OpenAPI operation during runs
- **Who has it:** respect-cli (its whole value prop: `SCHEMA_CHECK`, `STATUS_CODE_CHECK`, `CONTENT_TYPE_CHECK`, each gateable to `error|warn|off`).
- **Evidence:** <https://redocly.com/docs/respect/commands/respect>, <https://redocly.com/docs/respect>
- **Why:** converts our runner from "orchestrator" into a **contract tester**, the single biggest adoption driver in this space (Redocly, Speakeasy, Specmatic all position Arazzo this way). We already resolve operations from source descriptions; comparing actual status/body/content-type against the operation's responses is incremental. Per-check severity config mirrors respect's UX.
- **Effort:** M (needs an OpenAPI response-schema matcher; PHP has options like `opis/json-schema` we already use structurally).

#### P2 — Machine-readable outputs: JSON run reports, `--fail-severity`, GitHub Actions formatter, and SARIF (first-mover)
- **Who has it:** JSON reports — respect-cli (`--json-output`), jentic validator (`json`, `github-actions` formats), SpecLynx CLI (`--format json`, `--fail-severity`, documented exit-code contract). SARIF — **nobody** in the Arazzo ecosystem (open feature request even for Redocly lint: [redocly-cli#1615](https://github.com/Redocly/redocly-cli/issues/1615)).
- **Evidence:** <https://redocly.com/docs/respect/commands/respect> · <https://github.com/jentic/jentic-arazzo-tools#validator> · <https://speclynx.com/cli/#validate-command>
- **Why:** CI gating is where validators get adopted. SARIF upload to GitHub code scanning would make php-arazzo the only Arazzo linter with first-class GH integration — cheap differentiation.
- **Effort:** S (JSON + github-actions formatter + `--fail-severity`), M (SARIF).

#### P3 — HAR export of executed runs
- **Who has it:** respect-cli (`--har-output`).
- **Evidence:** <https://redocly.com/docs/respect/commands/respect>
- **Why:** HAR is the lingua franca of HTTP debugging (replayable in browser DevTools, Charles, k6, etc.). Our ledger already records requests/responses — emitting HAR 1.2 is mostly serialization. Pairs naturally with the ledger.
- **Effort:** S.

#### P4 — Workflow `inputs` JSON-Schema pre-flight validation (lead the market)
- **Who has it:** **nobody** (verified: jentic Python runner does not validate inputs; TS runner doesn't; respect/arazzo-cli don't).
- **Evidence:** absence verified in source (`arazzo_runner/runner.py` — no jsonschema usage in execution path; jentic TS runner README lists unsupported features, inputs validation absent).
- **Why:** Arazzo 1.x defines `inputs` as JSON Schema 2020-12. Validating supplied inputs before spending network calls is exactly what our existing preflight/preflight-events architecture wants; also unlocks better DX errors ("input `recipient_id` must be integer"). We'd be first.
- **Effort:** S/M (we already ship a JSON-schema structural layer).

#### P5 — Auth/security-scheme resolution + `show-env-mappings`-style command
- **Who has it:** jentic Python runner (API key/OAuth2 CC+password/Basic/Bearer applied from OpenAPI security schemes; env-var credential mapping surfaced via CLI).
- **Evidence:** <https://github.com/jentic/arazzo-engine/blob/main/runner/README.md#authentication>
- **Why:** every real workflow needs credentials; today users must hand-roll headers in php-arazzo. Env-var mapping discovery (`which env vars do I need for this workflow?`) is great DX and easy to expose via `bin/arazzo explain`.
- **Effort:** M (scheme application) / L if OAuth2 client-credentials flow included.

#### P6 — Mock transport for testing user workflows (OpenAPI-driven fake responses)
- **Who has it:** jentic Python runner test framework (`openapi_mocker.py`, per-endpoint custom handlers, call-count assertions); Specmatic (full product).
- **Evidence:** <https://github.com/jentic/arazzo-engine/blob/main/runner/README.md#running-tests> · <https://specmatic.io/>
- **Why:** lets php-arazzo *users* test their workflows in CI without hitting live APIs — ship a PSR-18 mock client that synthesizes responses from the bound OpenAPI spec (examples → schemas), plus assertion helpers (call counts). Natural fit with PSR-18 abstraction we already ride.
- **Effort:** M/L (example/schema-based response synthesis is the bulk).

#### P7 — Dry-run plan mode
- **Who has it:** arazzo-cli (`run_workflow` MCP option `dry_run`; CLI dry-run).
- **Evidence:** <https://github.com/strefethen/arazzo-cli>, <https://openapi.tools/tools/arazzo-cli>
- **Why:** show resolved operations, methods, URLs, parameter bindings and which steps would execute — without sending anything. Cheap given our resolver/expression evaluator; excellent for debugging and docs.
- **Effort:** S/M.

#### P8 — Safety & polish bundle: step budget, rate limiting, secrets masking
- **Who has it:** step budget — jentic TS runner (`maxSteps=1000`, counts retries, `step-budget` error), respect-cli (`--max-steps`); rate limiting — arazzo-cli (token bucket); secrets masking — respect-cli (`format: password` scrubbing, `--no-secrets-masking`).
- **Evidence:** <https://github.com/jentic/jentic-arazzo-tools/tree/main/packages/jentic-arazzo-runner> · <https://redocly.com/docs/respect/commands/respect> · <https://github.com/strefethen/arazzo-cli>
- **Why:** our goto/retry loops are currently bounded only by timeouts; a max-attempts counter is trivial insurance. Secrets masking protects the ledger/logs we persist — important since durable queued runs store payloads.
- **Effort:** S each.

### Second tier (worth queuing)

9. **Server overrides & templated server variables** — respect `--server name=url`; jentic env-based server variables (S). Needed for staging-vs-prod runs.
10. **`execute-operation` single-op mode** — jentic (S): invoke one OpenAPI operation by id or `METHOD /path`; useful primitive and easy win for the CLI.
11. **MCP server exposing workflows as tools** — arazzo-cli (M): pair with official `laravel/mcp`; tools = list/describe/run workflow. Strong story for the Laravel audience; agents are Jentic's stated target consumer too.
12. **PHPUnit integration à la Itarazzo** — run a workflow as PHPUnit dynamic tests w/ per-step assertions + JUnit XML output (M): meets PHP devs where they already are.
13. **Blob storage for large binary responses** — jentic (M): store oversized bodies via Laravel filesystem (Flysystem) and keep references in state/ledger instead of payloads.
14. **Coverage reporting** — nobody formalizes it; respect markets "gap finding" (M): report % of operations/status-codes exercised by a suite vs the source descriptions; natural extension of P1 + generate-style scaffolding.
15. **Generation: deterministic OpenAPI→Arazzo starter workflows + example values** — respect `generate-arazzo`, arazzo-cli `generate_workflow`/`generate_example`, jentic LLM generator (M/L): skip the LLM; scaffold one-step-per-operation suites and schema-example inputs like respect does.
16. **Overlay apply** — SpecLynx CLI (M): apply Overlay 1.x to source descriptions before binding (e.g., point servers at sandbox); nice-to-have.
17. **Corpus hardening against jentic-public-apis** — pull a sample of their thousands of generated Arazzo docs into property/fuzz testing (S): real-world messiness beyond OAI examples.

### Whitespace notes (asked-about axes where *nobody* has the feature)

- **Watch mode:** no tool among those surveyed ships watch/re-run-on-change. Deprioritize unless requested by users; trivial later via `watchman`-style polling in the CLI.
- **Arazzo→Postman export:** no tool does it (converters stop at OpenAPI/Markdown/PlantUML). Possible future differentiator; Postman collections are well-documented JSON (effort M) but demand is unproven.
- **Spec `callbacks` execution:** unimplemented across the entire ecosystem (jentic's "callbacks" are lifecycle listeners, not the spec feature). Implementing step `callbacks` (listen for inbound requests mid-workflow, evaluate, respond) would be a genuine first — but it's L effort and rare in real docs; watch OAI discussions first.
- **x- sub-specification embedding & POST-based source resolution:** unsupported everywhere; Jentic signals future x- extensions via RFC in jentic-public-apis. Keep parser lenient (preserve unknown x- fields losslessly — worth verifying we do) and revisit when the RFC lands.

### Competitive advantages to defend (context)

- We're alone in supporting **Arazzo 1.1** parsing/validation/execution; every surveyed tool stops at 1.0.x/1.0.1.
- Published **conformance matrix** vs official examples is unmatched transparency (respect/jentic claim correctness without publishing matrices).
- Durable queued execution + ledger + PSR-14 events has no analogue in any surveyed tool (closest: arazzo-cli trace recording).

---

## Appendix: source links

- jentic/arazzo-engine: <https://github.com/jentic/arazzo-engine> · runner README: <https://github.com/jentic/arazzo-engine/blob/main/runner/README.md> · components: <https://github.com/jentic/arazzo-engine/blob/main/runner/components.md> · generator: <https://github.com/jentic/arazzo-engine/blob/main/generator/README.md> · PyPI: <https://pypi.org/project/arazzo-runner/> · CLI docs: <https://docs.jentic.com/cli/arazzo-runner/>
- jentic-arazzo-tools: <https://github.com/jentic/jentic-arazzo-tools> · runner pkg: <https://github.com/jentic/jentic-arazzo-tools/tree/main/packages/jentic-arazzo-runner> · validator pkg: <https://github.com/jentic/jentic-arazzo-tools/tree/main/packages/jentic-arazzo-validator>
- jentic-public-apis: <https://github.com/jentic/jentic-public-apis> · arazzo-ui: <https://arazzo-ui.jentic.com>
- SpecLynx: <https://speclynx.com/> · CLI: <https://speclynx.com/cli/> · Language Service: <https://speclynx.com/language-service/> · ApiDOM: <https://speclynx.com/apidom/> · arazzo namespace: <https://github.com/speclynx/apidom/tree/main/packages/apidom-ns-arazzo-1>
- respect-cli: <https://redocly.com/docs/respect> · command ref: <https://redocly.com/docs/respect/commands/respect> · repo: <https://github.com/Redocly/redocly-cli> · SARIF request: <https://github.com/Redocly/redocly-cli/issues/1615>
- arazzo-cli: <https://github.com/strefethen/arazzo-cli> · site: <https://strefethen.github.io/arazzo-cli/> · listing: <https://openapi.tools/tools/arazzo-cli>
- Itarazzo: <https://github.com/leidenheit/itarazzo-library> · client: <https://github.com/leidenheit/itarazzo-client>
- Specmatic: <https://specmatic.io/>
- OAI tooling index: <https://github.com/OAI/Arazzo-Specification#tooling>
