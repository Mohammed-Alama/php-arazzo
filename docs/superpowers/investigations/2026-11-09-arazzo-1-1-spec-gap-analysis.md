# Spec v1.1 Review — Gap Analysis: php-arazzo vs Official Arazzo 1.1.0

**Source:** https://spec.openapis.org/arazzo/latest.html (official v1.1.0, published 2026-05-17)
**Spec repo issues:** https://github.com/OAI/Arazzo-Specification/issues (71 issues)
**Implementation tree:** `main` @ `ea8c55ad` (PR #309, issue #168 merged)

This report reviews our receive-step default-success implementation (#168) and the broader AsyncAPI surface against the official Arazzo 1.1.0 spec, and cross-references open issues on the spec repository itself. Its purpose is to identify what we got right, what we're missing, and what may be wrong.

---

## 1. What the spec v1.1 actually says (relevant provisions)

### §5.8.3.1 Source Description Object — `type`
> Possible values are `"openapi"` or `"asyncapi"` or `"arazzo"`.

AsyncAPI is a first-class source type in 1.1.0. (1.0.1 had only `openapi`/`arazzo`, which is why our rule no-ops on 1.0 — correct.)

### §5.8.5.1 Step Object — AsyncAPI-specific fields
| Field | Spec text |
|-------|-----------|
| `channelPath` | Reference to a Source Description + JSON Pointer to an event channel. Mutually exclusive of `operationId`/`workflowId`. |
| `action` | `"send"` or `"receive"`. Only applicable for `asyncapi` steps. |
| `correlationId` | "Only applicable to `asyncapi` steps with action `receive` and has to be in-sync with correlationId defined in the AsyncAPI document." Typed as `string` in the prose. |
| `successCriteria` | "If `successCriteria` is provided, it `MUST` contain at least one Criterion Object." |
| `timeout` | Max ms to wait before aborting/failing the step. |

### §5.8.5.3 Defining Success for Asynchronous Steps (the core of #168)
- Send: "the step completes immediately after the message is sent. Arazzo does not model broker acknowledgment or delivery confirmation."
- Receive: "step completion depends on message receipt."
- "authors `SHOULD` define `successCriteria` for AsyncAPI receive steps to explicitly evaluate the received message payload (for example, via `$message.payload`)"
- "Authors `MAY` omit `successCriteria` only when both: (1) the channel defines a single message type that unambiguously represents successful completion; (2) the message payload does not contain fields indicating error states."
- "When `successCriteria` is omitted, receiving any message matching the `correlationId` (if specified) within the `timeout` period is considered a successful step completion."
- "If `correlationId` is specified, only messages matching the correlation identifier are considered. If no matching message is received within the `timeout` period, the step fails and triggers any defined `onFailure` actions."

### §5.9 Runtime Expressions — ABNF grammar
```
expression = ... / "$message." source / ...
source = header-reference / query-reference / path-reference / body-reference / payload-reference
payload-reference = "payload" ["#" json-pointer]
```
Examples table:
- `$message.header.Server` — "Single header values only are available."
- `$message.payload#/status` — JSON Pointer form (`#/...`), **not** dot notation.

### §5.8.11 Criterion Object — conditions
- Flavors: simple, regex, jsonpath, xpath.
- Multiple criteria = logical AND (all must pass).
- Evaluation errors → fail (§5.8.11.4.5).
- String comparisons case-insensitive; numeric strings SHOULD coerce; null only equals itself (§5.8.11.4.1).

---

## 2. Implementation vs spec — gap analysis

### A. Receive default-success semantics (§5.8.5.3) — MATCH ✅

| Spec requirement | Our implementation | File | Status |
|------------------|-------------------|------|--------|
| Omitted `successCriteria` → success iff a matching message arrives within `timeout` | `messageReceived === true` gate | `CriteriaEvaluator.php:85-86` | Match |
| No matching message → step fails | Absent/false/non-array `messageReceived` → `false` | `CriteriaEvaluator.php:243-247` | Match |
| Timeout → fail + `onFailure` applies, criteria never evaluated | 504 synthesized, `criteriaMet=false` directly, no marker set | `CorrelationResumer.php:89-110` | Match |
| `correlationId` filters which messages count | Resumer consumes/marks only messages resolved by correlationId | `CorrelationResumer.php:49,130` | Match |
| `successCriteria` provided → must have ≥1 criterion (else invalid) | `ReceiveStepSuccessCriteriaRule` reports empty criteria | `ReceiveStepSuccessCriteriaRule.php:31` | Match |
| Rule no-ops on 1.0 (AsyncAPI fields are 1.1-only) | `specVersion === V1_0 → return` | `ReceiveStepSuccessCriteriaRule.php:25-27` | Match |
| Strict→error / non-strict→warning | `strict` flag honored | `ReceiveStepSuccessCriteriaRule.php:39-43` | Match |

**Verdict:** #168's core receive behavior aligns with §5.8.5.3. The marker approach is deliberate and correct (the timeout path also writes a 504 response record, so we must not infer receipt from the response alone).

### B. `$message` runtime expression (§5.9) — PARTIAL ⚠️ (issue #310)

The expression engine **does** parse and resolve `$message`:
- Parser: `$message.header.Name` and `$message.payload#/ptr` (`Parser.php:209-229`, tests `ParserTest:180-181`)
- Resolver: `StepReferenceResolver::message()` reads `$stepData['response']` → headers/body (`StepReferenceResolver.php:148-161`)
- Resumer: stores transport `$response` as the step's `response` record, so `$message.payload` reads the received message body and `$message.header` reads its headers (`CorrelationResumer.php:117-128`)
- Tests: `ExpressionEvaluatorTest:347-358`, `CorrelationResumerTest:322`

**Gaps:**
1. **No validation restricting `$message` to asyncapi `receive` steps.** `ExpressionContextMisuseRule` only guards `$response`/`$request`/`$statusCode`/`$url`/`$method` (§5.9 runtime context), not `$message`. Per spec, `$message` is "Available In: Steps with kind: asyncapi and action: receive." Using `$message` on an HTTP step is silently allowed today. → **missing rule.**
2. **Output extraction with `$message`** — the spec example shows `outputs: orderId: $message.payload.orderId`. Outputs flow through `OutputExtractor::extractOutputs()` against `$contextWithResponse`, so `$message.payload#/ptr` would resolve via the resolver. This appears wired, but there's no dedicated test asserting `$message` works in step `outputs` on a resumed receive step. → **needs a test.**
3. **Dot notation is invalid** — see #622 below. Our issue #310 and the #150 validation comment use `$message.payload.orderId` / `$message.payload.status` (dot notation), which the ABNF rejects. Must be `$message.payload#/orderId` and `$message.payload#/status`.

### C. `correlationId` type / AsyncAPI relationship (§5.8.5.1) — SPEC AMBIGUITY ⚠️ (issue #610)

- Spec prose: `correlationId` is a `string`, "in-sync with correlationId defined in the AsyncAPI document."
- Spec schema (1.1): accepts strings, numbers, booleans, objects, arrays.
- AsyncAPI 3.1: `correlationId` is a **Correlation ID Object** with a required `location` runtime expression — not a bare value.
- Our impl: parses `correlationId` as an `Expression` (`Parser.php:244`, `StepTarget::$correlationId` is `?Expression`) and the `PendingCorrelationRegistry` matches on the resolved correlation id string.

The spec repo issue **#610** is open and asks for clarification of this mapping. Our choice (expression → string match) is one reasonable interpretation, but it is not normatively settled by the spec. → **document as an assumption; revisit when #610 resolves.**

### D. `channelPath` send-step payload — NOT ADDRESSED ⚠️ (issue #617)

- #168 scope was receive only. `requestBody` prose names `operationId`/`operationPath` but not `channelPath`.
- Spec repo issue **#617** (open): "Does `requestBody.payload` also apply to a `channelPath` send? … A wording clarification and a small `channelPath` send example would cover what we need."
- If we ever implement send steps, this is the open question. → **out of scope for #168; track.**

### E. Simple-condition comparison semantics (§5.8.11.4.1) — SPEC UNRESOLVED ⚠️ (issues #584, #518)

Our `ConditionEvaluator` makes specific choices that the spec leaves undefined:

| Behavior | Our implementation | Spec status |
|----------|-------------------|-------------|
| Numeric-string coercion | regex `^-?\d+(?:\.\d+)?$` (matches `42`, `-3.5`, not `1e3`, `0x10`, `+5`, `.5`, `Infinity`) | **#584 Gap 2**: "numeric string" undefined; proposal = JSON number grammar |
| Cross-type equality `true == '1'` | `true` (via `strcasecmp(stringify(...))`) | **#584 Gap 4**: proposal = cross-type `false` except numeric-string rule |
| Ordering `<`/`>` | `false` if either operand isn't a number | **#584 Gap 3**: proposal = number-number and string-string only |
| Case-insensitive | `strcasecmp` (locale/ASCII-dependent) | **#584 Gap 5**: ASCII vs Unicode folding undefined |
| Grammar / operand boundary | Hand-rolled lexer/parser | **#518**: no normative grammar; ambiguous boundary (e.g. `$request.query.a=b == 1`) |

Since #584 and #518 are **open** spec issues, the spec does not yet mandate a single behavior. Our implementation is internally consistent and documented, but it may need revision when the spec resolves these. → **monitor; file a tracking note.**

### F. AsyncAPI version scope — ASSUMPTION ⚠️ (issue #563)

- Spec never states which AsyncAPI version `type: asyncapi` refers to.
- Spec repo issue **#563** (open): proposes tightening to AsyncAPI v3.x; notes `send`/`receive` is v3 terminology and the v2↔v3 `publish`/`subscribe` mapping is inverted and ambiguous.
- Our impl assumes AsyncAPI v3 (consistent with `send`/`receive`). → **document the assumption.**

---

## 3. Spec-repository open issues that directly affect us

| Spec issue | Title | Relevance to php-arazzo | State |
|------------|-------|------------------------|-------|
| #622 | `1.1: Two runtime expressions examples for $message.payload.orderId are incorrect according to defined ABNF grammar` | **Blocks #310 examples.** Spec's own `$message.payload.orderId` (dot notation) is invalid; correct is `$message.payload#/orderId`. Our #310 and #150 comment used invalid dot notation. | OPEN (bug/documentation) |
| #610 | `1.1: clarify correlationId type and its relationship to AsyncAPI` | Our `correlationId` as `Expression`→string match is one interpretation; not normatively settled. | OPEN |
| #617 | `Clarify how channelPath send steps supply their payload` | Send-step payload story unresolved; affects any future send implementation. | OPEN |
| #584 | `Define comparison semantics for simple Criterion Object conditions` | Our coercion/comparison choices may conflict with a future normative table. | OPEN |
| #518 | `Provide a normative grammar for simple Criterion Object conditions` | Our condition parser has no spec grammar to conform to; boundary rules undefined. | OPEN |
| #563 | `Specify supported AsyncAPI version(s) for asyncapi source descriptions` | We assume v3; spec silent. | OPEN |
| #589 | `Clarify what $outputs refers to and where it can be used` | Affects output expression semantics generally. | OPEN |
| #590 | `Clarify how to read outputs of a workflow run by a retry failure action` | Affects retry/failure-action output semantics (we have `onFailure`/`retry`). | OPEN |

---

## 4. Corrections needed

### 4.1 Issue #310 (`$message` runtime expression) — fix examples
The acceptance criteria and examples currently use dot notation (`$message.payload.status`, `$message.payload.orderId`), which is **invalid per the ABNF** and contradicts spec issue #622. They must use JSON Pointer:
- `$message.payload#/status`
- `$message.payload#/orderId`
- `$message.header#/X-Trace` → actually `$message.header.X-Trace` (header uses dot name per grammar: `header-reference = "header." token`)

Also, the feature scope should note the validation gap: no rule restricts `$message` to asyncapi `receive` steps.

### 4.2 Issue #150 spec-validation comment
The comment I posted used `$message.payload.status == "ok"` and `$message.payload.orderId` — both invalid dot notation per #622. Should be corrected to `$message.payload#/status` and `$message.payload#/orderId`.

---

## 5. Recommended next steps

### Immediate (no new spec dependency)
1. **Fix #310 examples** to use JSON Pointer (`#/`) not dot notation, per spec #622.
2. **Add a validator rule** restricting `$message` references to asyncapi `receive` steps (spec: "Available In: Steps with kind: asyncapi and action: receive"). Currently `ExpressionContextMisuseRule` does not cover `MessageRef`.
3. **Add a Pest test** asserting `$message.payload#/ptr` works in step `outputs` on a resumed receive step.
4. **Correct the #150 comment** examples (dot notation → JSON Pointer).

### Track / monitor (depend on spec resolution)
5. **#610 (correlationId):** document our expression→string-match assumption; revisit when spec clarifies AsyncAPI Correlation ID Object mapping.
6. **#584/#518 (condition semantics):** record our coercion choices as a known deviation risk; update when the spec publishes a normative comparison table/grammar.
7. **#563 (AsyncAPI version):** document v3 assumption.
8. **#617 (channelPath send payload):** out of scope for #168; revisit if send steps are implemented.

### Potential new ticket
9. A spec-conformance ticket: "Validator rule restricting `$message` to asyncapi receive steps" (derives from §5.9 and ExpressionContextMisuseRule gap).

---

## 6. Bottom line

- **#168 receive default + validator rule: correct per §5.8.5.3.** No implementation change needed there.
- **#310 (`$message`): parser/resolver/resumer already wired; missing receive-step validation rule; examples must be fixed to JSON Pointer (#622).**
- **Everything else (#610, #617, #584, #518, #563):** open spec issues on OAI/Arazzo-Specification. Our implementation makes reasonable interpretations but should be documented as assumptions and revisited as the spec issues resolve.
