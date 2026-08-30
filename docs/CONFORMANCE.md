# Conformance Matrix

> Generated on 2026-08-28 by `php scripts/generate-conformance-matrix.php`.
> Corpus: official [OAI Arazzo examples](https://github.com/OAI/Arazzo-Specification/tree/main/examples)
> (vendored snapshot under `packages/core/tests/Conformance/corpus/oai/`).
>
> **Tier 1** parses and structurally validates each document.
> **Execute** runs workflow(s) against a deterministic in-memory transport
> whose responses are fabricated from each operation's declared contract.

| Document | Arazzo | Parse + validate | Adapter | Execute | Notes |
|---|---|---|---|---|---|
| `bnpl-arazzo` | 1.0.0 | pass | sync | succeeded |  |
| `ExtendedParametersExample` | 1.0.0 | pass | — | skipped | companion `animals.yaml` is not shipped upstream |
| `FAPI-PAR` | 1.0.0 | pass | — | n/a (upstream defect) | arazzo references operationId &quot;PAR&quot; but companion OpenAPI declares &quot;Par&quot; (case mismatch) |
| `LoginAndRetrievePets` | 1.0.0 | pass | — | n/a (upstream defect) | operationPath `#/paths/~1pet~1findByStatus` omits the HTTP-method segment required to reach an Operation Object |
| `oauth` | 1.0.0 | pass | queued (sub-workflow) | succeeded |  |
| `pet-coupons` | 1.0.0 | pass | queued (sub-workflow) | succeeded |  |

## Capability gaps surfaced by this harness

Bugs found and fixed while building this matrix (each pinned by tests):

- validator accepted raw `{$sourceDescriptions.*}` strings instead of extracting the source name
- runtime resolver lacked the dotted `$sourceDescriptions.NAME.OPID` grammar
- multi-segment JSON Pointer paths (`~1a~1b`) rejected by operationPath resolution
- cebe references unresolvable (`readFromJson` provides no context)
- OpenAPI 3.1 normalizer was an unimplemented stub
- expression parser rejected the spec's `$steps.<id>.<output>` shortcut form
- JSONPath filter selectors `[?@...]` / `[?count(...)]` unsupported spelling
