# 21. Webhook Payload Interception UI

**Category:** UI — Advanced Debugging & Interaction
**Phase:** 6 — UI: advanced debugging
**Depends on:** [15 — The Graph Explorer](15-graph-explorer.md), [03 — Native Asynchronous Control Flow](03-native-async-control-flow.md) (webhook suspension)
**Status:** Not started — needs brainstorming

## Description

**The Problem:** Workflows paused for webhook callbacks can get stuck if the third-party
service sends a malformed payload that fails schema validation, or if the external service
fails to send the webhook entirely.

**The Feature:** A manual "Inject Event" interface. When viewing a paused workflow, an
operator can click the waiting node and open a modal. This modal allows them to manually paste
a JSON payload (or select from a list of predefined mock payloads) and forcefully submit it
into the workflow engine, overriding the wait state and forcing the workflow to continue.
