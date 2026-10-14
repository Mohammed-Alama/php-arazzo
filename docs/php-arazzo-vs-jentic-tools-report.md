# PHP Arazzo vs Jentic Arazzo Ecosystem

## 1. Purpose

This report provides a comprehensive architectural and feature comparison between the **PHP Arazzo** implementation in this repository and the **Jentic Arazzo Ecosystem** ([`jentic/jentic-arazzo-tools`](https://github.com/jentic/jentic-arazzo-tools) and [`jentic/arazzo-engine`](https://github.com/jentic/arazzo-engine)).

The report answers:
- What the PHP package provides.
- What the Jentic TypeScript and Python tools provide.
- What is stronger or weaker in each project.
- Where `php-arazzo` leads on specification currency and enterprise orchestration.
- What gaps and opportunities exist for `php-arazzo` (particularly in AI agent tooling via MCP, heuristic generation, and interactive visualization).
- The 4-Epic ticket hierarchy established to implement these capabilities.

---

## 2. Projects Reviewed

### PHP Package (`php-arazzo`)

- **Repository**: `Mohammed-Alama/php-arazzo`
- **Architecture**: Decoupled monorepo of **15 packages**:
  - `alama/arazzo-contracts`
  - `alama/arazzo-document`
  - `alama/arazzo-expression`
  - `alama/arazzo-evaluation`
  - `alama/arazzo-sources`
  - `alama/arazzo-runtime`
  - `alama/arazzo-runner`
  - `alama/arazzo-engine`
  - `alama/arazzo-events`
  - `alama/arazzo-cli`
  - `alama/arazzo-protocol-http`
  - `alama/arazzo-protocol-soap`
  - `alama/arazzo-protocol-rpc`
  - `alama/arazzo-core` (umbrella)
  - `alama/laravel-arazzo`
- **Runtime**: PHP 8.4, Laravel 11/12
- **Spec Versions**: Arazzo **1.0.0**, **1.0.1**, **1.1.0**, and **1.2.0** proposal draft (SOAP `#533`, RPC/Protobuf `#556`)
- **License**: MIT
- **Test Suites**: Pest v5 across all 15 packages with unit, feature, integration, and conformance suites

### Jentic TypeScript Tools (`jentic-arazzo-tools`)

- **Repository**: [`jentic/jentic-arazzo-tools`](https://github.com/jentic/jentic-arazzo-tools)
- **Packages (5 packages)**:
  - `@jentic/arazzo-parser`
  - `@jentic/arazzo-resolver`
  - `@jentic/arazzo-validator`
  - `@jentic/arazzo-runner`
  - `@jentic/arazzo-ui` (React interactive diagram and documentation viewer)
- **Runtime**: TypeScript/JavaScript (Node.js & Browser)
- **Spec Versions**: Pinned strictly to Arazzo **1.0.0** and **1.0.1**
- **License**: Apache-2.0

### Jentic Python Engine (`arazzo-engine`)

- **Repository**: [`jentic/arazzo-engine`](https://github.com/jentic/arazzo-engine)
- **Components**:
  - **Runner**: Python CLI and execution library for Arazzo workflows
  - **Generator**: Algorithmic / heuristic OpenAPI-to-Arazzo workflow synthesizer (analyzing CRUD endpoints and resource ID passing)
- **Runtime**: Python ^3.10
- **License**: Apache-2.0

### Jentic Platform & Agent Infrastructure

- **Platform**: Jentic Standard Agent framework, Just-in-Time Tooling (JITT), and Model Context Protocol (MCP) servers connecting Arazzo workflows to Claude Desktop, Cursor, Windsurf, ChatGPT, and VS Code.

---

## 3. Capability Matrix

| Capability | PHP Arazzo (`php-arazzo`) | Jentic TypeScript (`jentic-arazzo-tools`) | Jentic Python (`arazzo-engine`) | Current Analysis |
|---|---|---|---|---|
| **Architecture** | 15 modular packages (layered ports/adapters) | 5 packages (ApiDOM-based) | Single Python package (Runner + Generator) | PHP has highest modularity & separation of concerns |
| **Arazzo 1.0 Support** | ✅ Full | ✅ Full | ✅ Full | All projects support 1.0 |
| **Arazzo 1.1 Support** | ✅ Full (Selectors, dynamic parameters, expressions) | ❌ Unsupported (pinned to 1.0.1) | ❌ Unsupported (pinned to 1.0.1) | **PHP significantly ahead** |
| **Arazzo 1.2 Multi-Protocol** | ✅ Proposed (SOAP/WSDL, RPC/Protobuf) | ❌ Unsupported | ❌ Unsupported | **PHP unique multi-protocol leader** |
| **Supported Protocols** | HTTP (OpenAPI 2/3.0/3.1), SOAP (WSDL), RPC (gRPC/Protobuf) | HTTP (OpenAPI 2/3.0/3.1) | HTTP (OpenAPI 3.0) | PHP supports multi-protocol workflows |
| **Interactive UI** | ⚠️ Static Mermaid flowchart generation in CLI | ✅ `@jentic/arazzo-ui` (React split/diagram viewer) | ❌ None | **Jentic advantage in visualization** |
| **Workflow Generation** | ⚠️ LLM-based prompt generator (`AiClientInterface`) | ❌ None | ✅ Heuristic algorithmic generator (CRUD matching) | **Jentic advantage in offline generation** |
| **AI Agent Tooling / MCP** | ⚠️ Roadmap item 10 | ⚠️ Platform feature (MCP server connecting to Desktop agents) | ⚠️ Python agent bindings | **Jentic advantage in MCP agent integration** |
| **Durable Execution** | ✅ Persistent state, event ledger, distributed locks, queue workers | ❌ In-memory only | ❌ In-memory only | **PHP enterprise advantage** |
| **Framework Integration**| ✅ Native Laravel bridge & auto-discovery | ❌ None | ❌ None | PHP native framework integration |
| **CLI Capabilities** | `validate`, `run`, `list`, `render`, `explain` | `validate`, `ui` | `run`, `generate` | Feature parity split across CLI tools |

---

## 4. What the PHP Package Has (PHP Strengths)

1. **Modern Specification Support (1.0, 1.1, 1.2)**:
   - Full support for Arazzo 1.1 features (polymorphic Selector Objects, workflow-level inputs/parameters, expression type version pinning).
   - Early multi-protocol support for Arazzo 1.2 proposals (SOAP/WSDL, gRPC/Protobuf).
   - Jentic remains anchored to 1.0.0 and 1.0.1.

2. **Durable Enterprise Workflow Engine**:
   - Resumable asynchronous workflows with correlation keys (`PendingCorrelation`) and webhooks.
   - Distributed locking interfaces (`LockManagerInterface`) and Laravel cache integration.
   - Append-only event ledger (`LedgerAppendingListener`) capturing full state transitions.

3. **Multi-Protocol Execution**:
   - Pluggable protocols: OpenAPI operations via PSR-18, SOAP requests via WSDL envelopes, and RPC calls via Protocol Buffers.

---

## 5. What the Jentic Ecosystem Has (Jentic Strengths)

1. **Model Context Protocol (MCP) Integration**:
   - Jentic allows AI agents in Cursor, Claude Desktop, and VS Code to discover and execute Arazzo workflows as standardized MCP tools. Workflows become callable actions in agent tool loops.

2. **Heuristic OpenAPI-to-Arazzo Generator**:
   - The Python `arazzo-engine` analyzes OpenAPI schemas to automatically discover CRUD lifecycles (e.g. `POST /users` returns `id` -> feeds `GET /users/{id}`). It synthesizes valid Arazzo workflows deterministically without requiring an LLM or incurring API token costs.

3. **Interactive Visual UI (`@jentic/arazzo-ui`)**:
   - A dedicated React component and browser CLI (`npx @jentic/arazzo-ui`) providing split documentation, step inspector, and live interactive node graph views.

---

## 6. What is Missing from PHP (Gaps & Opportunities)

To achieve parity with Jentic's strongest capabilities, `php-arazzo` needs:

1. **Model Context Protocol (MCP) Server**:
   - An `arazzo mcp` CLI command serving JSON-RPC over stdio, exposing workflows in an Arazzo document as callable MCP tools with parameter validation and step execution progress streaming.

2. **Deterministic Heuristic Workflow Generator**:
   - An offline heuristic synthesizer in `arazzo generate --heuristic` that discovers entity CRUD lifecycles from OpenAPI documents without requiring external AI API keys.

3. **Interactive HTML / Visual Workflow Viewer**:
   - An `arazzo render --format=html` command producing a standalone, self-contained HTML bundle with an interactive graph viewer and step documentation, plus `arazzo view <file>` for instant local browser preview.

4. **Multi-Language Conformance Testing**:
   - Shared test fixtures between `php-arazzo` and Jentic's Python/TS test suites to verify cross-engine execution verdict consistency.

---

## 7. Action Plan: 4-Epic Hierarchy & Ticket Roadmap

The implementation is structured into 4 Epics managing 9 vertical-slice tracer bullets:

### Epic 1: AI Agent Orchestration & Model Context Protocol (MCP)
- **Ticket 1.1**: `feat(cli): add arazzo mcp stdio command serving JSON-RPC Model Context Protocol`
- **Ticket 1.2**: `feat(runner,cli): expose Arazzo workflows as callable MCP tools with JSON Schema parameters`
- **Ticket 1.3**: `feat(cli): support workflow step execution progress notifications over MCP`

### Epic 2: Deterministic OpenAPI-to-Arazzo Heuristic Workflow Generator
- **Ticket 2.1**: `feat(document): analyze OpenAPI operation schemas to discover CRUD entity lifecycles and ID dependencies`
- **Ticket 2.2**: `feat(cli): add deterministic arazzo generate --heuristic command without LLM requirement`

### Epic 3: Interactive Visual Workflow Viewer & Standalone HTML Renderer
- **Ticket 3.1**: `feat(cli): add standalone HTML bundle renderer (arazzo render --format=html) with interactive split diagram and documentation views`
- **Ticket 3.2**: `feat(cli): add arazzo view <file> command launching local browser preview`

### Epic 4: Multi-Language Cross-Conformance Test Suite (PHP vs Jentic)
- **Ticket 4.1**: `test(conformance): vendor Jentic Petstore and e-commerce reference workflow fixtures`
- **Ticket 4.2**: `test(conformance): add cross-engine execution verdict assertions to conformance matrix`

---

## 8. Final Assessment

`php-arazzo` is technically superior in specification currency (Arazzo 1.1/1.2), multi-protocol execution (SOAP/RPC), and durable workflow orchestration. However, Jentic has pioneered user-facing and agent-facing developer experiences: Model Context Protocol (MCP) tool integration, heuristic workflow generation, and interactive UI visualization.

By executing the 4 Epics detailed above, `php-arazzo` will combine its robust distributed backend with Jentic's leading agentic and visualization capabilities.
