# 01: Add arazzo mcp stdio command serving JSON-RPC Model Context Protocol

## Parent
Epic: AI Agent Orchestration & Model Context Protocol (MCP)

## What to build
Implement the foundational Model Context Protocol (MCP) server command `arazzo mcp <document>` that listens for standard JSON-RPC 2.0 messages over `php://stdin` and responds on `php://stdout`. It implements MCP protocol initialization (`initialize`), capability negotiation, and ping/shutdown lifecycles.

## Acceptance criteria
- [ ] Create `McpCommand` in `packages/cli/src/Console/Command/McpCommand.php` mapped to `arazzo mcp`.
- [ ] Implement `JsonRpcStdioTransport` reading newline-delimited JSON-RPC from standard input and writing framed responses to standard output.
- [ ] Handle MCP `initialize` request returning server name, version, and advertised capabilities (`tools`).
- [ ] Handle `notifications/initialized` and `ping` requests according to the MCP spec.
- [ ] Ensure non-protocol CLI logging is directed to `php://stderr` to preserve standard output cleanliness for JSON-RPC.
- [ ] Add unit and functional tests simulating JSON-RPC stdin/stdout message flows.

## Blocked by
None (can start immediately).
