# `WorkflowSnapshot` — Canonical Serialization

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Enables: pro-persistence replay, pro-observability time-travel (debug-19), audit report signing

## Problem

`WorkflowContext` today is only used in-process. Cross-worker state transfer, replay
after crash, time-travel debugging, and tamper-evident audit exports all need canonical
byte-identical serialization of run state. No such format exists — different callers would
invent incompatible encodings, breaking replay and signature verification.

## Feature

`Alama\Arazzo\Persistence\WorkflowSnapshot`:

```php
final readonly class WorkflowSnapshot
{
    public string $version;              // '1'
    public string $executionId;
    public string $workflowId;
    public string $definitionSha256;     // pinned document hash
    public string $status;               // running|succeeded|failed|suspended
    public array  $stepStates;           // stepId => StepState
    public array  $outputs;
    public array  $inputs;
    public \DateTimeImmutable $startedAt;
    public ?\DateTimeImmutable $endedAt;

    public static function fromContext(WorkflowContext $ctx): self;
    public function toContext(DefinitionRegistryInterface $reg): WorkflowContext;

    public function toBytes(): string;   // canonical JSON, sorted keys, no whitespace
    public static function fromBytes(string $bytes): self;

    public function sha256(): string;    // hash of toBytes(), for signing/dedup
}
```

Canonical form = JSON with:
- All keys sorted lexicographically at every level.
- No insignificant whitespace.
- Explicit numeric types (`json_encode` with `JSON_PRESERVE_ZERO_FRACTION`).
- UTF-8, no BOM.

## Acceptance

- Two snapshots of the same run state on different hosts produce byte-identical output.
- `fromContext()` → `toContext()` round-trip preserves execution semantics (replayed run
  reaches identical outputs).
- Snapshot sha256 changes iff observable state changes (ignores map insertion order).
- Format v1 documented under `docs/snapshot-format.md`. Future versions must migrate
  explicitly.

## Out of scope

- Compression / binary encoding — JSON is fine; add later if size hurts.
- Encryption — orthogonal concern; belongs to the storage layer that holds snapshots.
