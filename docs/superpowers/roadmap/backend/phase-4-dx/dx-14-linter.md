# 14. Pre-Flight Linter

**Category:** Backend — Testing & Developer Experience
**Phase:** 4 — Testing & developer tooling
**Depends on:** [01 — Zero-Code Data Pipelining](01-zero-code-data-pipelining.md)
**Status:** Not started — needs brainstorming

## Description

A static analysis CLI tool (`php artisan arazzo:analyze`) that traces JSONPaths against the
referenced OpenAPI schemas to catch structural typos and mapping errors prior to deployment.
