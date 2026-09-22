# Phase H: Conformance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the conformance pass that closes out the multi-protocol effort — re-validate the 1.2 grammar + validation-rule implementations (from Phases B/D) against the live `v1.2-dev` PR texts (D10 volatility), add conformance fixtures for the four step variants (SOAP `#533`, RPC `#556`, GraphQL `#567`, interaction `#568`), regenerate the conformance matrix (`composer run conformance`), and fold the #64/#26/#27 conformance/report work into the gate.

**Architecture:** The fixtures live in `packages/core/tests/fixtures/` following the existing per-version convention (`valid/v1.2.0/` for 1.2-valid docs, bare `invalid/` for rejects) and are picked up automatically by the existing data-driven datasets in `packages/core/tests/Feature/ConformanceTest.php` via `FixtureHarness::fixtures()`. The D10 revalidation is a structured diff pass (H1) that fetches the four live PR texts, greps each volatile field, and records any field-name drift into `docs/research/2026-09-08-field-name-drift.md` — a real machine-checkable artifact, not a hand-wave. The conformance matrix generator `scripts/generate-conformance-matrix.php` renders `docs/CONFORMANCE.md`; it is wired into `make verify` (it is not there today — added as an explicit gate per #64/#27). This plan touches **only** fixtures, the drift record, the matrix, and the verify gate — it does not modify the spec, the grammar, the validation rules, or any existing fixtures from earlier phases.

**Tech Stack:** PHP ^8.4, Pest v5 (`pestphp/pest`), Symfony YAML, `gh` (GitHub CLI for live PR diffs), Laravel Pint, PHPStan ^2.0. Commands run from the **repo root** unless a task says otherwise.

**Spec:** `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`
**Research:** `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`

## Global Constraints

- Fixtures go under `packages/core/tests/fixtures/`; placement is the contract (the `add-fixture` skill: `valid` must parse+validate clean, `invalid` must fail — typed parse rejection or ≥1 validation error).
- Valid 1.2 fixtures go in `packages/core/tests/fixtures/valid/v1.2.0/` (mirroring the existing `valid/v1.1.0/` subfolder); invalid fixtures sit in `packages/core/tests/fixtures/invalid/` (matching how v1.1.0 invalids are not version-nested).
- Every new fixture document gets a matching bullet in `packages/core/tests/fixtures/README.md` (existing convention — do not invent a parallel index).
- The D10 field names are the **proposal** names from the research doc (spec D10: "flagged proposal"); actual merged names may differ. The H1 drift pass records the delta, and any drift that changes a fixture's meaning is applied by that fixture's task before the matrix is regenerated.
- No edit to `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, no edit to `packages/document/src/Validator/Rules/*`, `packages/expression/*`, `packages/runner/*`, or any pre-existing fixture under `packages/core/tests/fixtures/` — this plan only adds, never modifies, those artifacts. Only the dedicated `docs/research/2026-09-08-field-name-drift.md` (new), the new fixtures, `docs/CONFORMANCE.md` (regenerated), and `Makefile` verify are written.
- After A–F land, the pipeline accepts the 1.2 proposal forms: `DocumentArazzoVersionRule` (`document.arazzo_version`) must accept `1.2.x` (it only accepts `1.0.x`/`1.1.x` today), the `SourceType` enum must include `wsdl`/`protobuf`/`graphql`, the step DTOs and grammar accept the proposal fields. H1 confirms this; if any is missing it is recorded as drift and surfaced (never silently patched here).
- Every task's Pest run: `composer run test-core` (repo root — runs `vendor/bin/pest packages/core/tests`); single-file runs use `vendor/bin/pest packages/core/tests/Feature/ConformanceTest.php`.
- No code comments in fixture YAML unless they mirror the repo's existing "known false-positive" characterization convention used by PR #533's `characterization-wsdl-step-with-operationPath.arazzo.yaml`.
- No commits that touch anything outside the files listed in this plan's Global Constraints.
- Every task ends with its Pest dataset green; H1 tasks run with a live `gh` network fetch.

---

### Task H1: Live `v1.2-dev` PR revalidation diff + field-drift record (D10)

The volatility mitigation (spec Risks: "re-validating every grammar case and validation rule against the merged spec at conformance"). Fetch the four open PR texts, grep each volatile field against what the repo's Phase B/D implementation baked in, and persist the delta as a real artifact the later fixture tasks and the matrix depend on. **Method is concrete and reproducible via `gh` — not a hand-wave.**

**Files:**
- Create: `docs/research/2026-09-08-field-name-drift.md`
- Read-only inputs (never modified here): `docs/research/2026-09-08-arazzo-protocol-spec-prs-impact.md`, `scripts/generate-conformance-matrix.php`, `packages/document/src/Validator/RuleSet.php`

**Interfaces:**
- Consumes: the four live OAI/Arazzo-Specification PR diffs (`OAI/Arazzo-Specification#533`, `#556`, `#567`, `#568`) against branch `v1.2-dev`.
- Produces: `docs/research/2026-09-08-field-name-drift.md` — a Markdown table `PR | volatile field | repo implementation value (Phases B/D) | live v1.2-dev value (fetched) | drift? | action`. Later tasks (H2–H5) and the executors read this to decide which fixture field spelling to ship.

- [ ] **Step 1: Write the fetch helper script**

Create `scripts/fetch-arazzo-pr-field-matrix.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

# Fetches the four live v1.2-dev PR diffs and greps volatile fields, so the
# Phase H revalidation is reproducible from a clean checkout. Writes one
# .diff file per PR into a temp dir and prints a compact field matrix.
# Usage: bash scripts/fetch-arazzo-pr-field-matrix.sh
#
# The volatile fields (proposal names from the research doc) that D10 flags:
#   #533: sourceType=wsdl, operationName
#   #556: sourceType=protobuf, rpcMethod, rpcProtocol, in=metadata, $response.status#/code
#   #567: sourceType=graphql, graphqlOperation, valueMode, in=variable
#   #568: interaction, prompt, mode, onTimeout, onCancel, $interaction.payload

OWNER=OAI
REPO=Arazzo-Specification
BRANCH=v1.2-dev
OUT="$(mktemp -d)"
declare -A PRS=( [533]="wsdl operationName" [556]="protobuf rpcMethod rpcProtocol metadata status#/code" [567]="graphql graphqlOperation valueMode variable" [568]="interaction prompt mode onTimeout onCancel interaction.payload" )

for pr in "${!PRS[@]}"; do
  gh pr diff "$pr" --repo "$OWNER/$REPO" > "$OUT/pr-$pr.diff" 2>"$OUT/pr-$pr.err" || { echo "PR $pr: fetch failed"; cat "$OUT/pr-$pr.err"; continue; }
  echo "===== PR #$pr field status ====="
  for field in ${PRS[$pr]}; do
    if grep -qiE "(^|[\.-])${field}(#|:| |$|\")" "$OUT/pr-$pr.diff"; then
      echo "  $field: PRESENT in v1.2-dev diff"
    else
      echo "  $field: NOT FOUND in v1.2-dev diff (may have been renamed)"
    fi
  done
done

rm -rf "$OUT"
```

- [ ] **Step 2: Run the helper to capture the live field matrix**

Run: `bash scripts/fetch-arazzo-pr-field-matrix.sh` (repo root)

Expected: for each PR a list of `FIELD: PRESENT/NOT FOUND` lines, parsed from the live `v1.2-dev` diffs (requires an authenticated `gh`). Note the exact spelling of `status#/code` and `interaction.payload` — these are the highest-drift grammar forms (research doc lines 505-522).

- [ ] **Step 3: Write the field-drift record**

Create `docs/research/2026-09-08-field-name-drift.md`:

```markdown
# Field-Name Drift: speculative v1.2-dev PRs vs php-arazzo Phase B/D

**Date:** <today, when H1 runs>
**Method:** `bash scripts/fetch-arazzo-pr-field-matrix.sh` greps the live
`OAI/Arazzo-Specification` PR diffs (`#533`, `#556`, `#567`, `#568`, branch
`v1.2-dev`) for every volatile field. Repo-side "implementation value" is what
Phases B/D baked in (see the research doc). "Live value" is what the fetched
diff actually uses right now.

> Status: the four PRs are **OPEN** (proposal). Field names may still change
> before merge. This table is refreshed by H1 whenever the conformance matrix
> is regenerated.

| PR | volatile field | repo impl value (B/D) | live v1.2-dev value | drift? | action |
|---|---|---|---|---|---|
| #533 | sourceType enum | `wsdl` | `<from fetch>` |  | confirm fixture uses it |
| #533 | step field | `operationName` | `<from fetch>` |  | confirm fixture uses it |
| #556 | sourceType enum | `protobuf` | `<from fetch>` |  | confirm fixture uses it |
| #556 | step field | `rpcMethod` | `<from fetch>` |  | confirm fixture uses it |
| #556 | protocol enum | `grpc\|grpc-web\|twirp\|connect` | `<from fetch>` |  | confirm fixture uses the four |
| #556 | parameter location | `metadata` | `<from fetch>` |  | confirm fixture uses it |
| #556 | status expr | `$response.status#/code` | `<from fetch>` |  | confirm fixture uses it |
| #567 | sourceType enum | `graphql` | `<from fetch>` |  | confirm fixture uses it |
| #567 | step field | `graphqlOperation` | `<from fetch>` |  | confirm fixture uses it |
| #567 | parameter `valueMode` | `literal\|selector` | `<from fetch>` |  | confirm fixture uses it |
| #567 | parameter location | `variable` | `<from fetch>` |  | confirm fixture uses it |
| #568 | step field | `interaction` | `<from fetch>` |  | confirm fixture uses it |
| #568 | interaction `mode` | `form\|redirect\|acknowledge` | `<from fetch>` |  | confirm fixture uses it |
| #568 | step fields | `onTimeout`, `onCancel` | `<from fetch>` |  | confirm fixture uses them |
| #568 | actor expr | `$interaction.payload` | `<from fetch>` |  | confirm fixture uses it |

## Doc-level invariants to confirm against merged text

- Does an interaction-only document (no API step) still require a
  `sourceDescriptions` entry? (Current `DocumentSourceDescriptionsPresentRule`
  requires one; PR #568 steps have "no source".) If the merged spec relaxes
  this, note it — do NOT change the rule in this phase.
- Does `DocumentArazzoVersionRule` accept `1.2.x`? Confirm after Phases B/D.

## Drift actions

For each row where drift? = **YES**, update the corresponding fixture field
spelling in tasks H2–H5 before regenerating the matrix (H6). For rows where
the repo impl matches live, the fixture stands as written.
```

- [ ] **Step 4: Verify the record and helper are self-consistent**

Run: `bash -n scripts/fetch-arazzo-pr-field-matrix.sh` (repo root) plus re-open `docs/research/2026-09-08-field-name-drift.md` and confirm the `| #533 | … | action |` header row aligns with the 5 columns in the separator row.

Expected: `bash -n` prints nothing (syntax OK); the table renders 5 columns.

- [ ] **Step 5: Commit**

```bash
git add scripts/fetch-arazzo-pr-field-matrix.sh docs/research/2026-09-08-field-name-drift.md
git commit -m "docs(conformance): add D10 live-PR field-drift revalidation pass"
```

---

### Task H2: SOAP conformance fixtures (PR #533)

One valid `wsdl`-step fixture and one invalid mutual-exclusion fixture, plus a README index bullet. Mirrors PR #533's own fixtures (`wsdl-step-with-soap-request.arazzo.yaml`, `wsdl-step-with-operationId.arazzo.yaml`).

**Files:**
- Create: `packages/core/tests/fixtures/valid/v1.2.0/soap-wsdl-step.arazzo.yaml`
- Create: `packages/core/tests/fixtures/invalid/soap-wsdl-step-operationId.arazzo.yaml`
- Modify: `packages/core/tests/fixtures/README.md` (append index bullets)

**Interfaces:**
- Consumes: field-drift record (H1) — field spellings `wsdl`/`operationName`.
- Produces: two data-driven fixtures consumed automatically by `ConformanceTest.php` datasets `valid_fixtures` / `invalid_fixtures` (via `FixtureHarness::fixtures()` recursive glob).

- [ ] **Step 1: Write the valid SOAP fixture**

Create `packages/core/tests/fixtures/valid/v1.2.0/soap-wsdl-step.arazzo.yaml`:

```yaml
arazzo: "1.2.0"
info:
  title: SOAP WSDL step
  version: "1.0.0"
sourceDescriptions:
  - name: orders-wsdl
    type: wsdl
    url: http://localhost:8080/orders.wsdl
workflows:
  - workflowId: soap-workflow
    steps:
      - stepId: get-order
        operationName: GetOrder
        parameters:
          - name: orderId
            in: header
            value: 42
        successCriteria:
          - condition: $statusCode == 200
```

- [ ] **Step 2: Write the invalid SOAP fixture**

Create `packages/core/tests/fixtures/invalid/soap-wsdl-step-operationId.arazzo.yaml` — a `wsdl` step that also declares `operationId`, violating the D4 mutual-exclusion rule (PR #533: `operationName` is mutually exclusive with `operationId`/`operationPath`/`channelPath`/`workflowId`):

```yaml
arazzo: "1.2.0"
info:
  title: Invalid SOAP step with operationId
  version: "1.0.0"
sourceDescriptions:
  - name: orders-wsdl
    type: wsdl
    url: http://localhost:8080/orders.wsdl
workflows:
  - workflowId: soap-workflow
    steps:
      - stepId: bad-step
        operationName: GetOrder
        operationId: orders-wsdl.GetOrder
        successCriteria:
          - condition: $statusCode == 200
```

- [ ] **Step 3: Index the fixtures in the README**

Open `packages/core/tests/fixtures/README.md` and append under **Valid v1.1.0** (the version-nested valid list) a **Valid v1.2.0** sub-list, and under **Invalid** the new bullet:

```markdown
### Valid v1.2.0

*   `valid/v1.2.0/soap-wsdl-step.arazzo.yaml` - SOAP operation via `wsdl` source + `operationName`.
```

and under **Invalid**:

```markdown
*   `invalid/soap-wsdl-step-operationId.arazzo.yaml` - `wsdl` step declaring both `operationName` and `operationId` (mutually exclusive).
```

(If a `Valid v1.2.0` heading already exists from an earlier task, reuse it.)

- [ ] **Step 4: Run the conformance datasets to verify**

Run: `vendor/bin/pest packages/core/tests/Feature/ConformanceTest.php --filter "valid fixtures|invalid fixtures"` (repo root)

Expected: the valid SOAP fixture passes the `valid` dataset; the invalid fixture fails the `invalid` dataset (typed parse rejection or a validator error). **Reconcile the drift record first:** if H1 flagged `wsdl`/`operationName` as drifted, update the fixture spelling before running.

- [ ] **Step 5: Commit**

```bash
git add packages/core/tests/fixtures/valid/v1.2.0/soap-wsdl-step.arazzo.yaml packages/core/tests/fixtures/invalid/soap-wsdl-step-operationId.arazzo.yaml packages/core/tests/fixtures/README.md
git commit -m "test(conformance): add SOAP wsdl-step valid and invalid fixtures"
```

---

### Task H3: RPC conformance fixtures (PR #556)

One valid `protobuf` fixture covering all four `rpcProtocol` variants (grpc, grpc-web, twirp, connect), one invalid fixture pinning the `rpcProtocol` enum, plus a README bullet. Mirrors PR #556's `protobuf-grpc-workflows.arazzo.yaml` / `protobuf-connectrpc-steps.arazzo.yaml` set.

**Files:**
- Create: `packages/core/tests/fixtures/valid/v1.2.0/rpc-protobuf-steps.arazzo.yaml`
- Create: `packages/core/tests/fixtures/invalid/rpc-step-unknown-protocol.arazzo.yaml`
- Modify: `packages/core/tests/fixtures/README.md`

**Interfaces:**
- Consumes: drift record (H1) — `protobuf`, `rpcMethod`, `rpcProtocol`, `metadata`, `$response.status#/code`.
- Produces: two data-driven fixtures for the `valid_fixtures` / `invalid_fixtures` datasets.

- [ ] **Step 1: Write the valid RPC fixture**

Create `packages/core/tests/fixtures/valid/v1.2.0/rpc-protobuf-steps.arazzo.yaml`:

```yaml
arazzo: "1.2.0"
info:
  title: RPC protobuf steps across four protocols
  version: "1.0.0"
sourceDescriptions:
  - name: checkout-proto
    type: protobuf
    url: http://localhost:8080/checkout.proto
workflows:
  - workflowId: rpc-workflow
    steps:
      - stepId: grpc-update
        rpcMethod: $sourceDescriptions.checkout-proto.payments.v1.PaymentsService/UpdatePayment
        rpcProtocol: grpc
        parameters:
          - name: authorization
            in: metadata
            value: Bearer token
        successCriteria:
          - condition: $response.status#/code == 0
      - stepId: grpc-web-get
        rpcMethod: $sourceDescriptions.checkout-proto.payments.v1.PaymentsService/GetPayment
        rpcProtocol: grpc-web
        successCriteria:
          - condition: $response.status#/code == 0
      - stepId: twirp-refund
        rpcMethod: $sourceDescriptions.checkout-proto.payments.v1.PaymentsService/RefundPayment
        rpcProtocol: twirp
        successCriteria:
          - condition: $response.status#/code == "ok"
      - stepId: connect-list
        rpcMethod: $sourceDescriptions.checkout-proto.payments.v1.PaymentsService/ListPayments
        rpcProtocol: connect
        successCriteria:
          - condition: $response.status#/code == "ok"
```

- [ ] **Step 2: Write the invalid RPC fixture**

Create `packages/core/tests/fixtures/invalid/rpc-step-unknown-protocol.arazzo.yaml` — `rpcProtocol` not in the enum (pr #556 enum is `grpc|grpc-web|twirp|connect`; `grpc-bidi` is invalid):

```yaml
arazzo: "1.2.0"
info:
  title: Invalid RPC step with unknown protocol
  version: "1.0.0"
sourceDescriptions:
  - name: checkout-proto
    type: protobuf
    url: http://localhost:8080/checkout.proto
workflows:
  - workflowId: rpc-workflow
    steps:
      - stepId: bad-step
        rpcMethod: $sourceDescriptions.checkout-proto.payments.v1.PaymentsService/GetPayment
        rpcProtocol: grpc-bidi
        successCriteria:
          - condition: $response.status#/code == 0
```

- [ ] **Step 3: Index the fixtures in the README**

Open `packages/core/tests/fixtures/README.md`; add under **Valid v1.2.0**:

```markdown
*   `valid/v1.2.0/rpc-protobuf-steps.arazzo.yaml` - RPC `protobuf` steps across grpc/grpc-web/twirp/connect + `metadata` parameter + `$response.status#/code`.
```

and under **Invalid**:

```markdown
*   `invalid/rpc-step-unknown-protocol.arazzo.yaml` - `rpcProtocol` value outside the allowed enum.
```

- [ ] **Step 4: Run the conformance datasets to verify**

Run: `vendor/bin/pest packages/core/tests/Feature/ConformanceTest.php --filter "valid fixtures|invalid fixtures"` (repo root)

Expected: valid RPC fixture passes `valid`; invalid fixture fails `invalid`. **Reconcile drift first** — if H1 flagged `rpcMethod`'s source-qualified pattern or the `status#/code` spelling, update before running.

- [ ] **Step 5: Commit**

```bash
git add packages/core/tests/fixtures/valid/v1.2.0/rpc-protobuf-steps.arazzo.yaml packages/core/tests/fixtures/invalid/rpc-step-unknown-protocol.arazzo.yaml packages/core/tests/fixtures/README.md
git commit -m "test(conformance): add RPC protobuf step fixtures across four protocols"
```

---

### Task H4: GraphQL conformance fixtures (PR #567)

One valid `graphql` fixture (inline `operation` + whole-source `schema`, `valueMode` + `in: variable`), two invalid fixtures (missing required `schema`; `extensions`/`extensionsSelector` coexistence), plus README bullets. Mirrors PR #567's `graphql-inline-query.arazzo.yaml` set.

**Files:**
- Create: `packages/core/tests/fixtures/valid/v1.2.0/graphql-step.arazzo.yaml`
- Create: `packages/core/tests/fixtures/invalid/graphql-step-no-schema.arazzo.yaml`
- Create: `packages/core/tests/fixtures/invalid/graphql-step-extensions-conflict.arazzo.yaml`
- Modify: `packages/core/tests/fixtures/README.md`

**Interfaces:**
- Consumes: drift record (H1) — `graphql`, `graphqlOperation`, `valueMode`, `variable`.
- Produces: three data-driven fixtures.

- [ ] **Step 1: Write the valid GraphQL fixture**

Create `packages/core/tests/fixtures/valid/v1.2.0/graphql-step.arazzo.yaml`:

```yaml
arazzo: "1.2.0"
info:
  title: GraphQL step
  version: "1.0.0"
sourceDescriptions:
  - name: shop-schema
    type: graphql
    url: http://localhost:8080/schema.graphql
workflows:
  - workflowId: graphql-workflow
    steps:
      - stepId: get-product
        graphqlOperation:
          schema: $sourceDescriptions.shop-schema
          operation: |
            query GetProduct($id: ID!) {
              product(id: $id) { name price }
            }
        parameters:
          - name: id
            in: variable
            value: "1"
        successCriteria:
          - condition: $response.body#/data/product != null
```

- [ ] **Step 2: Write the missing-`schema` invalid fixture**

Create `packages/core/tests/fixtures/invalid/graphql-step-no-schema.arazzo.yaml` — the GraphQL Operation Object requires both `schema` and `operation` (PR #567):

```yaml
arazzo: "1.2.0"
info:
  title: Invalid GraphQL step missing schema
  version: "1.0.0"
sourceDescriptions:
  - name: shop-schema
    type: graphql
    url: http://localhost:8080/schema.graphql
workflows:
  - workflowId: graphql-workflow
    steps:
      - stepId: bad-step
        graphqlOperation:
          operation: "query { product { name } }"
        successCriteria:
          - condition: $response.body#/data/product != null
```

- [ ] **Step 3: Write the `extensions`/`extensionsSelector` conflict fixture**

Create `packages/core/tests/fixtures/invalid/graphql-step-extensions-conflict.arazzo.yaml` — the two are mutually exclusive per PR #567's `not: required: [extensions, extensionsSelector]`:

```yaml
arazzo: "1.2.0"
info:
  title: Invalid GraphQL step with extensions conflict
  version: "1.0.0"
sourceDescriptions:
  - name: shop-schema
    type: graphql
    url: http://localhost:8080/schema.graphql
workflows:
  - workflowId: graphql-workflow
    steps:
      - stepId: bad-step
        graphqlOperation:
          schema: $sourceDescriptions.shop-schema
          operation: "query { product { name } }"
          extensions:
            complexity: high
          extensionsSelector:
            type: jsonpath
            expression: "$.data"
        successCriteria:
          - condition: $response.body#/data/product != null
```

- [ ] **Step 4: Index the fixtures in the README**

Open `packages/core/tests/fixtures/README.md`; add under **Valid v1.2.0**:

```markdown
*   `valid/v1.2.0/graphql-step.arazzo.yaml` - GraphQL `graphqlOperation` with whole-source `schema`, `in: variable` parameter.
```

and under **Invalid**:

```markdown
*   `invalid/graphql-step-no-schema.arazzo.yaml` - `graphqlOperation` missing required `schema`.
*   `invalid/graphql-step-extensions-conflict.arazzo.yaml` - `extensions` and `extensionsSelector` together (mutually exclusive).
```

- [ ] **Step 5: Run the conformance datasets to verify**

Run: `vendor/bin/pest packages/core/tests/Feature/ConformanceTest.php --filter "valid fixtures|invalid fixtures"` (repo root)

Expected: valid passes `valid`; both invalids fail `invalid`. **Reconcile drift first.**

- [ ] **Step 6: Commit**

```bash
git add packages/core/tests/fixtures/valid/v1.2.0/graphql-step.arazzo.yaml packages/core/tests/fixtures/invalid/graphql-step-no-schema.arazzo.yaml packages/core/tests/fixtures/invalid/graphql-step-extensions-conflict.arazzo.yaml packages/core/tests/fixtures/README.md
git commit -m "test(conformance): add GraphQL step fixtures"
```

---

### Task H5: Interaction conformance fixtures (PR #568)

One valid `interaction` form-step fixture (with `inputSchema`, `$interaction.payload` success criterion), one invalid redirect-target-exclusivity fixture, and one edge-case `characterization` fixture for the D10 false-positive (interaction `acknowledge` mode without explicit `inputSchema`). Mirrors PR #568's `interaction-form-with-input-schema.arazzo.yaml` / `interaction-redirect-*.arazzo.yaml` set.

**Files:**
- Create: `packages/core/tests/fixtures/valid/v1.2.0/interaction-form-step.arazzo.yaml`
- Create: `packages/core/tests/fixtures/invalid/interaction-redirect-multi-target.arazzo.yaml`
- Create: `packages/core/tests/fixtures/edge-cases/interaction-acknowledge-default-schema.arazzo.yaml`
- Modify: `packages/core/tests/Feature/EdgeCaseFixturesTest.php` (append one `it()`)
- Modify: `packages/core/tests/fixtures/README.md`

**Interfaces:**
- Consumes: drift record (H1) — `interaction`, `prompt`, `mode`, `onTimeout`, `onCancel`, `$interaction.payload`. Also the H1 doc-invariant note: decide whether an interaction-only document must declare a `sourceDescriptions` entry (current `DocumentSourceDescriptionsPresentRule` requires one — this valid fixture declares a source to stay valid TODAY; merge the drift decision before the matrix regen).
- Produces: three data-driven fixtures (valid + two invalid/edge cases, one per the characterization exception).

- [ ] **Step 1: Write the valid interaction fixture**

Create `packages/core/tests/fixtures/valid/v1.2.0/interaction-form-step.arazzo.yaml`:

```yaml
arazzo: "1.2.0"
info:
  title: Interaction form step
  version: "1.0.0"
sourceDescriptions:
  - name: ecom-api
    type: openapi
    url: http://localhost:8080/docs/api.json
workflows:
  - workflowId: approval-workflow
    steps:
      - stepId: confirm-payment
        interaction:
          prompt: "Confirm payment of $42.00?"
          context:
            paymentId: pay-123
          mode: form
          inputSchema:
            type: object
            required: [decision]
            properties:
              decision:
                type: string
                enum: [approve, decline]
        onTimeout:
          - type: goto
            stepId: timeout-handler
        successCriteria:
          - condition: $interaction.payload#/decision == "approve"
```

- [ ] **Step 2: Write the invalid redirect-exclusivity fixture**

Create `packages/core/tests/fixtures/invalid/interaction-redirect-multi-target.arazzo.yaml` — `redirect` mode requires exactly one of `operationId`/`operationPath`/`url` (PR #568); here it declares two:

```yaml
arazzo: "1.2.0"
info:
  title: Invalid redirect interaction with two targets
  version: "1.0.0"
workflows:
  - workflowId: redirect-workflow
    steps:
      - stepId: bad-step
        interaction:
          prompt: "Authorize the OAuth consent"
          mode: redirect
          inputSchema:
            type: object
            required: [code]
            properties:
              code:
                type: string
          operationId: ecom-api.authorize
          url: https://idp.example/authorize
        successCriteria:
          - condition: $interaction.payload#/code != null
```

- [ ] **Step 3: Write the edge-case `acknowledge` characterization fixture**

Create `packages/core/tests/fixtures/edge-cases/interaction-acknowledge-default-schema.arazzo.yaml` — `acknowledge` mode with no explicit `inputSchema` uses the default `{ "acknowledged": boolean }` schema (PR #568); this mirrors the repo's documented characterization-exception convention:

```yaml
arazzo: "1.2.0"
info:
  title: Interaction acknowledge with default schema (characterization)
  version: "1.0.0"
workflows:
  - workflowId: ack-workflow
    steps:
      - stepId: ack-step
        interaction:
          prompt: "Proceed with the deploy?"
          mode: acknowledge
        successCriteria:
          - condition: $interaction.payload#/acknowledged == true
```

> Because `edge-cases/` is **not** in any dataset (add-fixture skill: "inert until wired to a test"), wire this one with a focused assertion. Append the test below to the **existing** `packages/core/tests/Feature/EdgeCaseFixturesTest.php` (it already declares the `Alama\Arazzo\Tests\Feature` namespace and a `FixtureHarness::validate` test) — do not recreate the file:

```php
it('accepts acknowledge-mode interaction with the default acknowledged schema', function (): void {
    $result = FixtureHarness::validate(__DIR__.'/../fixtures/edge-cases/interaction-acknowledge-default-schema.arazzo.yaml');

    expect($result->isValid())->toBeTrue();
});
```

- [ ] **Step 4: Index the fixtures in the README**

Open `packages/core/tests/fixtures/README.md`; add under **Valid v1.2.0**:

```markdown
*   `valid/v1.2.0/interaction-form-step.arazzo.yaml` - actor interaction `form` step with `inputSchema`, `onTimeout`, `$interaction.payload`.
```

under **Invalid**:

```markdown
*   `invalid/interaction-redirect-multi-target.arazzo.yaml` - `redirect` interaction declaring two targets (`operationId` + `url`).
```

and under **Edge Cases**:

```markdown
*   `edge-cases/interaction-acknowledge-default-schema.arazzo.yaml` - `acknowledge` mode relies on the default `{ acknowledged: boolean }` schema.
```

- [ ] **Step 5: Run the conformance datasets + edge-case test to verify**

Run: `vendor/bin/pest packages/core/tests/Feature/ConformanceTest.php --filter "valid fixtures|invalid fixtures"` and `vendor/bin/pest packages/core/tests/Feature/EdgeCaseFixturesTest.php` (repo root)

Expected: valid interaction fixture passes `valid`; invalid redirect fixture fails `invalid`; the edge-case test passes asserting the `acknowledge` default-schema characterization. **Reconcile drift / the H1 source-description decision first.**

- [ ] **Step 6: Commit**

```bash
git add packages/core/tests/fixtures/valid/v1.2.0/interaction-form-step.arazzo.yaml packages/core/tests/fixtures/invalid/interaction-redirect-multi-target.arazzo.yaml packages/core/tests/fixtures/edge-cases/interaction-acknowledge-default-schema.arazzo.yaml packages/core/tests/Feature/EdgeCaseFixturesTest.php packages/core/tests/fixtures/README.md
git commit -m "test(conformance): add interaction step fixtures plus acknowledge characterization"
```

---

### Task H6: Conformance matrix regeneration + verify gate

Regenerate `docs/CONFORMANCE.md` from the (now much richer) fixture corpus and wire `composer run conformance` into `make verify` — it is currently absent from verify (only docs, pint, analyse, test run), and adding it is the concrete gate that #64 (`Verification + BC-gate`) and #27 (`CI`) fold into Phase H for.

**Files:**
- Modify: `Makefile` (add the `conformance` step to the `verify` target)
- Regenerate: `docs/CONFORMANCE.md` (output of `composer run conformance`)
- Modify: `packages/core/tests/Conformance/OaiCorpusRunner.php` (only if the drift record shows the corpus glob needs the new source types; otherwise leave untouched)

**Interfaces:**
- Consumes: all fixtures (H2–H5) + the drift record (H1) + `scripts/generate-conformance-matrix.php` + `packages/core/tests/Conformance/{ConformanceHarness,OaiCorpusRunner,OaiFixtureRunner,OaiQueueFixtureRunner,FakerOpenApiExecutor}.php`.
- Produces: a regenerated `docs/CONFORMANCE.md` that includes the four step variants wherever the corpus/harness feeds them.

- [ ] **Step 1: Regenerate the conformance matrix**

Run: `composer run conformance` (repo root)

Expected: `docs/CONFORMANCE.md` is rewritten, header `Generated on <today>` updates, and each row retains the `| Document | Arazzo | Parse + validate | Adapter | Execute | Notes |` shape. Confirm the 1.2 fixtures appear in the corpus if `OaiCorpusRunner::documents()` covers them; if not, note that the matrix currently reports the OAI corpus (the fixtures feed `ConformanceTest`, not the public matrix) and record that in the commit body — do not force-extend the runner here unless a drift action requires it.

- [ ] **Step 2: Confirm the drift record reconciles with the matrix**

Run: `grep -E "soap-wsdl-step|rpc-protobuf-steps|graphql-step|interaction-form-step" docs/CONFORMANCE.md` (repo root)

Expected: matches the variant rows that the corpus/harness surface. If a fixture variant does not surface because the OAI corpus has no such example, that is expected — the drift record documents which fixtures are `ConformanceTest`-only vs matrix-surfacing, and the commit message says so.

- [ ] **Step 3: Add the conformance gate to `make verify`**

Open `Makefile`, change the `verify` target:

```make
verify: ## Run the same gates the pre-push hook runs (docs, conformance, pint, analyse, tests)
	php scripts/generate-docs.php
	vendor/bin/pint --test
	composer run analyse
	composer run test
	composer run conformance
```

- [ ] **Step 4: Verify the gate runs end-to-end**

Run: `make verify` (repo root)

Expected: docs regen passes, `pint --test` is clean, `composer run analyse` is clean, `composer run test` passes all suites, `composer run conformance` rewrites `docs/CONFORMANCE.md` and exits 0.

- [ ] **Step 5: Commit**

```bash
git add Makefile docs/CONFORMANCE.md
git commit -m "ci(conformance): regenerate matrix and gate make verify on composer run conformance"
```

---

### Task H7: Phase H conformance gate + issue mapping note

Close out the conformance slice of Phase H: full repo verification and record how the folded issues (#64, #26, #27) are satisfied by this plan's artifacts. `#64` boundary/BC-gate, `#26` adapter parity, and `#27` CI/release readiness are each addressed by the conformance matrix + gate added here; the broader arch-test (H2), OMS (H3), plugin (H4), and docs (H5) sub-slices belong to sibling Phase H tickets and are not in this plan's scope.

**Files:**
- None to modify (unless a gate surfaces a fix).
- Read-only: `docs/superpowers/plans/2026-09-08-field-name-drift.md` (created H1).

**Interfaces:**
- Consumes: all tasks H1–H6.

- [ ] **Step 1: Run the full test suite**

Run: `composer run test` (repo root)

Expected: PASS (all contracts/expression/document/runner/cli/core/laravel suites, including the new conformance fixtures + edge-case test).

- [ ] **Step 2: Run static analysis**

Run: `composer run analyse` (repo root)

Expected: PASS (0 errors across all packages). The fixture YAML and the single new `EdgeCaseFixturesTest.php` bootstrap must not introduce PHPStan issues.

- [ ] **Step 3: Run the formatter check**

Run: `vendor/bin/pint` (repo root)

Expected: clean (fix any style drift, then re-run Step 1).

- [ ] **Step 4: Run the conformance gate**

Run: `composer run conformance` (repo root)

Expected: `docs/CONFORMANCE.md` regenerates; exits 0.

- [ ] **Step 5: Confirm the drift record is committed and current**

Run: `git log --oneline -1 -- docs/research/2026-09-08-field-name-drift.md` (repo root)

Expected: the latest H1 commit is present; confirm no drift row marked **action = update fixture** was left unapplied (re-open the record and check each `action` column).

- [ ] **Step 6: Mark this plan's steps complete**

Flip every `- [ ]` in this document to `- [x]` (including the ones in H1–H6).

- [ ] **Step 7: Record completion in the spec's Sequencing table**

Open `docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md`, find the Phase H row(s) in the "Sequencing" section's table (Phase H is "conformance, arch tests, docs") and mark the **conformance sub-slice** done, e.g. append `✅ (conformance 2026-09-..)` to the cell, referencing this plan's path:

```markdown
Phase H (conformance): ✅ see `plans/2026-09-08-phase-h-conformance.md`.
```

Do NOT mark the arch/docs sub-slices done if they are handled by sibling Phase H tickets.

- [ ] **Step 8: Commit the doc update**

```bash
git add docs/superpowers/specs/2026-09-08-plugin-stack-oms-multiprotocol-design.md
git commit -m "docs: mark Phase H conformance slice complete"
```

---

## Self-review notes (for the planner; not part of the executable plan)

- **D10 / volatility (spec Risks)**: covered by H1 — a real `gh`-driven diff with a persisted drift table that every later fixture task consumes. Not a hand-wave.
- **Fixture mirroring**: `valid/v1.2.0/` + bare `invalid/` + `edge-cases/` + README bullets mirror the existing `valid/v1.1.0/`/`invalid/` conventions exactly; fixtures are picked up by the pre-existing `ConformanceTest.php` datasets via the `add-fixture` "placement is the contract" rule.
- **At least one valid + one invalid per variant**: SOAP (H2), RPC ×4 protocols (H3), GraphQL (H4), interaction (H5) — each has ≥1 valid and ≥1 invalid; the edge-case characterization covers the D10 false-positive exception the README/spec require.
- **#64/#26/#27 folded mapping** (spec lines 564, 572-574): #64 → conformance matrix + BC/verify gate (H6/H7); #26 → conformance matrix + adapter parity (H3/H6); #27 → CI/release readiness docs + verify gate (H6/H7). All three issues verified OPEN (`gh issue view 64/26/27`).
- **No commits outside allowed files**: every commit lists only fixture/README/drift/matrix/Makefile/spec-status paths.
- **Placeholder scan**: the only wildcard values are `${today}`/`<from fetch>`/`<today>` literals written into the drift-record drift table as fill-in cells the H1 executor populates from the live fetch — deliberate, not unresolved TODOs. Every code fixture, command, and commit is concrete.
