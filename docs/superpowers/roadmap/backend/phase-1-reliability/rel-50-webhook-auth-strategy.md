# `WebhookAuthStrategyInterface`

Category: **rel** · Phase: **1-reliability** · Tier: **OSS** (interface + token/HMAC) + **Pro** (custom/pluggable verifiers)
Depends on: shipped `WebhookResumeController`/`ResumeCorrelationJob`, `exec-47-signals-and-queries`
(signal delivery is the second consumer of this, alongside AsyncAPI resume)

## Problem

`WebhookResumeController::resume()` currently has **no authentication at all** — it looks up
a `PendingCorrelation` by `correlationId` from the URL and dispatches a resume job for
whatever JSON body is posted. Anyone who obtains or guesses a correlation ID (they're not
documented as cryptographically unguessable, and are exposed via `CorrelationPending` events)
can resume — and inject arbitrary payload data into — a running workflow. This is a real gap,
not just a DX one, and it blocks two things:

1. Shipping AsyncAPI correlation-resume to production with any confidence.
2. `exec-47-signals-and-queries`, which explicitly deferred "public exposure of signal
   delivery" pending this stub — a generic signal-webhook needs the same protection.

`durable-workflow/workflow` ships pluggable webhook auth (token / HMAC / custom) precisely
because "here's a URL that mutates workflow state" is a common attack surface for any
durable-execution tool with external triggers.

## Feature

```php
interface WebhookAuthStrategyInterface
{
    /** @throws WebhookAuthException on failure */
    public function verify(Request $request): void;
}
```

Core ships:
- `NullWebhookAuth` — current behavior, explicit opt-in only (never the silent default once
  this ships — see Acceptance).
- `TokenWebhookAuth(string $expectedToken)` — bearer token / shared-secret header check,
  constant-time comparison.
- `HmacWebhookAuth(string $secret, string $headerName = 'X-Signature')` — HMAC-SHA256 over
  the raw request body, matching common webhook-signing conventions (Stripe/GitHub-style).

Pro (`arazzo-pro-persistence`) adds:
- Pluggable custom verifiers (e.g. JWT, mTLS client cert check) via a resolver bound per
  route/document.

Wiring:
- `WebhookResumeController::resume()` gains a constructor-injected `WebhookAuthStrategyInterface`,
  called before the correlation lookup — auth failure returns 401 before leaking whether the
  correlation ID even exists (avoid the oracle: don't 404 before auth, 401 first).
- Per-document `x-webhook-auth` extension lets an Arazzo document declare which strategy
  its AsyncAPI callback expects, resolved by the Laravel bridge's service provider.
- `exec-47`'s eventual signal-delivery webhook reuses the same
  `WebhookAuthStrategyInterface` binding rather than inventing a second config surface.

## Acceptance

- `WebhookResumeController` rejects unauthenticated requests by default once this ships —
  `NullWebhookAuth` must be explicitly configured, it is never the implicit fallback (closes
  the current silent-no-auth gap rather than preserving it as a "safe default").
- Auth failure returns 401 and does not reveal whether the correlation ID exists (no
  auth-vs-not-found timing or response-shape leak).
- `HmacWebhookAuth` verifies against the raw body bytes, not a re-serialized JSON
  representation (avoids signature mismatches from key-order/whitespace differences).
- Existing `WebhookResumeControllerTest` fixtures pass with `NullWebhookAuth` explicitly
  bound (backward-compat path for anyone already relying on today's behavior), and new tests
  cover token/HMAC success and failure paths.
- `x-webhook-auth` on a document with no matching bound strategy fails fast at validation
  time, not silently at request time.

## Out of scope

- mTLS / client-cert verification — pro custom-verifier territory, not core.
- Rate limiting / replay-attack windows for HMAC signatures — worth its own stub if this
  becomes a real exposure surface (nonce/timestamp tracking needs a store, not just a
  stateless check).
