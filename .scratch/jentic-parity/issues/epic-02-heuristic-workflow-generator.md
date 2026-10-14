# Epic: Deterministic OpenAPI-to-Arazzo Heuristic Workflow Generator

**What to build:**
Provide an offline, deterministic workflow synthesizer that analyzes OpenAPI specifications to discover CRUD entity lifecycles, path parameter linkages, and response identifier flows. This generates valid, ready-to-run Arazzo 1.0/1.1 workflows automatically without requiring external LLM API keys or incurring token costs, achieving parity with Jentic's Python `arazzo-engine` generator.

**Issue**: #88

**Child Tickets:**
- #94: Analyze OpenAPI operation schemas to discover CRUD entity lifecycles and ID dependencies
- #95: Add deterministic arazzo generate --heuristic command without LLM requirement

**Status:** ready-for-agent
