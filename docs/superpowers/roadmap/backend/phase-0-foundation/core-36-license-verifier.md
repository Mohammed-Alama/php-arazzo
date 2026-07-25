# `LicenseVerifierInterface` + `NullLicenseVerifier`

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Depends on: core-extraction
Enables: every `arazzo-pro-*` package

## Problem

The commercial-tier plan gates each pro feature behind a runtime license check
(ed25519-signed JSON, per feature name, boot-time verify-or-throw). That gate must be
callable from core (`Engine`, `StepExecutor`, `WorkflowExecutor`) without core taking a
dependency on any pro code or on any signing library. Currently no interface exists.

## Feature

Ship in `alama/arazzo-core`:

```php
interface LicenseVerifierInterface
{
    public function verifyOrThrow(string $feature): void;   // throws LicenseException
    public function isValid(string $feature): bool;
    public function expiresAt(string $feature): ?\DateTimeImmutable;
}

final class NullLicenseVerifier implements LicenseVerifierInterface
{
    public function verifyOrThrow(string $feature): void {}
    public function isValid(string $feature): bool { return true; }
    public function expiresAt(string $feature): ?\DateTimeImmutable { return null; }
}

final class LicenseException extends \RuntimeException {}
```

Core binds `NullLicenseVerifier` by default. Pro package `arazzo-pro-*` binds a bridge-specific
`Ed25519LicenseVerifier` via that bridge's service provider / bundle extension / Drupal
service subscriber.

Feature names live under `Alama\Arazzo\License\Features`:
```php
const string PERSISTENCE = 'persistence';
const string SAGA = 'saga';
const string MULTITENANCY = 'multitenancy';
// ...
```

Call sites: at boot (framework bridge) + at first-use of any pro-gated capability
(e.g. `SagaCoordinator::compensate()` → `$verifier->verifyOrThrow(Features::SAGA)`).

## Acceptance

- Core has zero cryptography dependencies.
- OSS-only install passes all existing tests with `NullLicenseVerifier` bound.
- Mutation test (byte-flip signature in pro-side `Ed25519LicenseVerifier` — separate repo)
  causes `verifyOrThrow` to throw. Fails closed.

## Out of scope

- The verifier implementation itself (lives in pro repo).
- Composer post-install signing hook (lives in pro package build).
