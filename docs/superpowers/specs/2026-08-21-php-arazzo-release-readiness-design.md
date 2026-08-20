# Documentation, Packaging, CI, and Release Readiness

## Goal

Make the PHP packages understandable, installable, verifiable, and ready for continued alpha-to-beta development.

## Documentation

Document correct namespaces, canonical synchronous/queued/Laravel examples, supported Arazzo/OpenAPI versions and protocols, selectors, result schema, errors, retries, control flow, outputs, sub-workflows, transport failures, cancellation, suspension, persistence, and breaking-change policy. Examples must be executable in CI or tested as fixtures.

## Packaging

`alama/arazzo-core` exposes intentional framework-independent APIs, accurate runtime dependencies, PSR requirements, and changelog entries. `alama/laravel-arazzo` remains an adapter around core interfaces, documents configuration/queues/locks/persistence/events/webhooks, verifies Laravel compatibility, and keeps Laravel dependencies out of core.

## CI and release

Standardize path filters, lockfiles, PHP/Laravel matrices, static analysis, formatting, tests, documentation checks, and local Makefile targets. Workflow job names must match Makefile targets. Before beta, define coordinated versioning, verify clean Composer installation, prevent development dependencies from leaking, verify licensing/attribution, and publish compatibility tables.

## Repository hygiene

Remove stale namespace examples, nonexistent links, and environment-specific artifacts. Keep design documents and implementation plans linked.

## Acceptance

- A new user can install either package and run a documented example.
- CI passes formatting, static analysis, tests, documentation, and clean-install checks.
- Core remains framework-independent and Laravel compatibility is verified.
- Release artifacts contain correct metadata, license, attribution, and documentation.
