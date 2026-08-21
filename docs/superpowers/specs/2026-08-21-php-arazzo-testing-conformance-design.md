# Testing and Cross-Language Conformance

## Goal

Prove official Arazzo behavior and parity between synchronous, queued, and Laravel execution without making the toolkit an implementation dependency.

## Test layers

Unit tests cover parsing, DTOs, expressions, pointers, selectors, criteria, source resolution, normalization, serialization, actions, retries, graphs, results, and errors. Core integration tests use mocked HTTP, clocks, sleep, fetchers, evaluators, persistence, and events. Queue tests prove transition persistence, no duplicate completed steps, locking, delayed retries, resumption, correlation suspension, and equivalent results. Laravel tests cover bindings, queues, locks, events, registries, webhooks, configuration, and supported framework versions.

## Golden fixtures

Fixtures contain the Arazzo document, OpenAPI sources, inputs, mock responses, expected requests, outputs, traces, statuses, and expected errors. They are independent of PHP test syntax and may be compared with toolkit behavior where the official specification permits.

## Additional verification

Property tests cover pointer round trips, expression tokenization, graph acyclicity, retry limits, and state serialization. Mutation testing targets action selection, retry exhaustion, dependency ordering, outputs, and error classification. CI runs formatting, static analysis, unit/integration tests, Laravel matrices, fixtures, and scheduled mutation tests.

## Acceptance

Every high-priority review finding has a regression test. Sync and queue paths have equivalent observable results. Shared fixtures do not import toolkit code. Invalid documents make no network calls. Transport, criteria, and authoring failures are tested separately. Supported Arazzo/OpenAPI versions have coverage.
