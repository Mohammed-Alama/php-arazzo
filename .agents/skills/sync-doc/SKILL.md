---
name: sync-doc
description: Regenerate docs/generated architecture diagrams and detect drift. Use when architecture/namespaces changed, docs are stale, or CI/docs consistency is questioned.
---

# Sync Generated Docs

`scripts/generate-docs.php` derives mermaid diagrams and reference tables (`docs/generated/*.md`) from the live source tree — byte-deterministic, same tree in, same bytes out. The script regenerates and treats any diff as drift to fix now, not later.

## Run

```bash
bash .agents/skills/sync-doc/scripts/sync-docs.sh
```

- Exit 1 lists exactly which generated files went stale — stage them with the change that made them stale (`git add docs/generated`), keeping doc commits atomic with code commits.
- Exit 0 means already in sync; nothing written.

Run it after moving namespaces, adding contracts/events/exceptions/validator rules, or touching migrations — those are the inputs the generator scans.

Completion criterion: exit 0, or the drift staged together with its cause.
