# 03: Support workflow step execution progress notifications over MCP

## Parent
Epic: AI Agent Orchestration & Model Context Protocol (MCP)

## What to build
Provide real-time feedback to AI agents and user interfaces while an Arazzo workflow is running. Subscribe to `packages/events` runner events and translate step transitions into standard MCP `notifications/progress` messages so clients can observe progress across multi-step API sequences.

## Acceptance criteria
- [ ] Connect `McpProgressEventListener` to the event dispatcher for active MCP tool calls.
- [ ] Map `StepStartedEvent` into progress notifications with current step name, step index, and total step count.
- [ ] Map `StepExecutedEvent` into progress completion milestones.
- [ ] Emit log messages via standard MCP `notifications/message` level logging.
- [ ] Ensure event listeners detach cleanly upon workflow completion.
- [ ] Add tests verifying progress notification payloads sent to stdout.

## Blocked by
- #92
