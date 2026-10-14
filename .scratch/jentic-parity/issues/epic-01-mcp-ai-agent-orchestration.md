# Epic: AI Agent Orchestration & Model Context Protocol (MCP)

**What to build:**
Enable seamless AI agent execution of Arazzo workflows by implementing an official Model Context Protocol (MCP) server within the PHP package. This allows desktop AI assistants (Claude Desktop, Cursor, Windsurf, VS Code) and autonomous agents to connect to `php-arazzo` via stdio JSON-RPC, discover available workflows as structured MCP tools, execute them with validated parameters, and receive real-time execution progress updates.

**Issue**: #87

**Child Tickets:**
- #91: Add arazzo mcp stdio command serving JSON-RPC Model Context Protocol
- #92: Expose Arazzo workflows as callable MCP tools with JSON Schema parameters
- #93: Support workflow step execution progress notifications over MCP

**Status:** ready-for-agent
