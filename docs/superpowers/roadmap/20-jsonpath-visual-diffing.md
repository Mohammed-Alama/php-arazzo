# 20. JSONPath Visual Diffing

**Category:** UI — Advanced Debugging & Interaction
**Phase:** 6 — UI: advanced debugging
**Depends on:** [17 — The Payload Inspector](17-payload-inspector.md), [01 — Zero-Code Data Pipelining](01-zero-code-data-pipelining.md)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** A developer uses a JSONPath expression like
`$steps.fetch_users.outputs.body[*].email`, but it resolves to an empty array instead of a
list of emails. Debugging this usually requires trial and error.

**The Feature:** A built-in JSONPath testing playground within the payload inspector. The UI
shows the raw response payload in a code editor on the left. On the right, the developer can
type different JSONPath expressions and see the evaluated result update in real-time against
that specific payload, highlighting the exact matches within the raw JSON block.
