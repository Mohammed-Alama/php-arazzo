# 19. Interactive Time-Travel Debugger

**Category:** UI — Advanced Debugging & Interaction
**Phase:** 6 — UI: advanced debugging
**Depends on:** [15 — The Graph Explorer](15-graph-explorer.md), [17 — The Payload Inspector](17-payload-inspector.md)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** When an API workflow fails at step 7, developers need to understand exactly
what the state of the data was before step 7 executed, and how it mutated. Staring at raw
JSON payloads is tedious.

**The Feature:** A "time-scrubber" interface. Below the DAG (Directed Acyclic Graph), provide
a timeline slider. As the user drags the slider back and forth through the execution steps,
the UI dynamically updates the "Current Context" panel. They can visually watch the variables
populate and mutate step-by-step, making it instantly obvious where the data pipeline broke
down.
