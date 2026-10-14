# 02: Expose Arazzo workflows as callable MCP tools with JSON Schema parameters

## Parent
Epic: AI Agent Orchestration & Model Context Protocol (MCP)

## What to build
Dynamically transform workflows defined in loaded Arazzo documents into callable MCP tools. Handle `tools/list` requests by converting workflow definitions and input parameters into valid JSON Schema objects, and handle `tools/call` by parsing parameters, executing the workflow via `RunnerFacade`, and returning structured outputs to the LLM agent.

## Acceptance criteria
- [ ] Implement `tools/list` handler returning each workflow as an MCP Tool with `name`, `description`, and `inputSchema`.
- [ ] Synthesize `inputSchema` from `workflow.inputs` and unbounded step parameters (types, required fields, descriptions).
- [ ] Implement `tools/call` handler resolving arguments, invoking `RunnerFacade::execute()`, and formatting the `ExecutionResult`.
- [ ] Return MCP `content` blocks with text and structured output payloads.
- [ ] Map execution errors (criteria failures, authoring faults) into graceful MCP tool error responses rather than server crashes.
- [ ] Test tool generation and execution invocation against sample Arazzo documents.

## Blocked by
- #91
