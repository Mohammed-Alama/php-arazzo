# OAI Conformance Corpus

Vendored snapshot of the **official Arazzo examples** published by the OpenAPI
Initiative, used by `tests/Conformance/OaiConformanceTest.php` and
`scripts/generate-conformance-matrix.php`.

Provenance: https://github.com/OAI/Arazzo-Specification/tree/main/examples
Snapshot date: **2026-08-24**

| File | Upstream path |
|---|---|
| `1.0.0/*.yaml` | `examples/1.0.0/` |
| `1.1.0/pet-asyncapi.yaml` | `examples/1.1.0/` (companion AsyncAPI document; no official 1.1 Arazzo example exists upstream yet) |
| `remotes/swagger-petstore-openapi.yaml` | swagger-api/swagger-petstore `master:src/main/resources/openapi.yaml`, vendored so `LoginAndRetrievePets.arazzo.yaml` can resolve its source locally |

Known upstream quirks (handled honestly by the matrix):

- `ExtendedParametersExample.arazzo.yaml` references `./animals.yaml`, which is
  **not shipped upstream** — source resolution is reported as a warning-level
  gap for that document.
- `bnpl-arazzo.yaml` points at a raw GitHub URL; we register the identical
  vendored `bnpl-openapi.yaml` under the same source name.

## Refreshing

```bash
bash scripts/fetch-conformance-corpus.sh
```

Re-downloads everything in this directory from upstream. Commit any changes
with a note in the changelog.
