# GitHub Issue Migration Plan

## Current State (from `gh issue list`)

### Existing epics (5):
| # | Title | Packages | Current Labels | Parent |
|---|-------|----------|----------------|--------|
| 87 | AI Agent Orchestration & MCP | cli, runner | epic | none |
| 88 | Interactive Visual Workflow Viewer | cli, document | epic | none |
| 89 | Deterministic OpenAPI-to-Arazzo Heuristic | cli, document | epic | none |
| 90 | Multi-Language Cross-Conformance Test Suite | core | epic | none |
| 118 | Document & runner validation hardening | document, runner | level:epic, enhancement | none |

### Existing story (1):
| # | Title | Packages | Labels | Parent |
|---|-------|----------|--------|--------|
| 79 | fix(validation,runner): reject ambiguous goto... | document, runner | level:story, bug, ready-for-agent, priority:high | 118 |

### Legacy parents blocking children (5 parents, 11 children):
- #16 → #113, #114
- #25 → #115, #116
- #27 → #100, #101, #102
- #42 → #109, #110
- #43 → #111, #112

## Migration Plan

### Phase 1: Create 15 package-epics (one per package)
```bash
php .agents/skills/file-issue/scripts/file-issue.php \
  --level=level:package --package=<name> --title="[Package] <name>" \
  --sections='{}'
```

### Phase 2: Re-parent existing epics under package-epics

| Epic | Primary Package | New Parent |
|------|-----------------|------------|
| #87 | cli | cli package-epic |
| #88 | document | document package-epic |
| #89 | document | document package-epic |
| #90 | core | core package-epic |
| #118 | document | document package-epic |

### Phase 3: Update labels on existing epics
- Remove `epic` label
- Add `level:epic` + `package:<primary>` labels

### Phase 4: Re-parent existing story
- #79 already under #118 ✓ (just needs label fix: `level:story` already correct)

### Phase 5: Create missing levels for legacy hierarchy
The legacy parents (#16, #25, #27, #42, #43) are grandfathered (no level label). They remain as-is per spec §A4.

### Phase 6: Verify with CI gates
Run `file-issue`, `gh` shim, and `package-index` workflow on real issues.

---

## Package-Epic Mapping

| Package | Primary Epics | Package-Epic # (after creation) |
|---------|---------------|---------------------------------|
| cli | #87, #88, #89 | TBD |
| document | #88, #89, #118 | TBD |
| runner | #87, #118 | TBD |
| core | #90 | TBD |
| contracts | — | TBD |
| engine | — | TBD |
| evaluation | — | TBD |
| events | — | TBD |
| expression | — | TBD |
| laravel | — | TBD |
| protocol-http | — | TBD |
| protocol-rpc | — | TBD |
| protocol-soap | — | TBD |
| runtime | — | TBD |
| sources | — | TBD |

---

## Commands to Execute (after plan approval)

```bash
# Phase 1: Create package-epics
for pkg in cli contracts core document engine evaluation events expression laravel protocol-http protocol-rpc protocol-soap runner runtime sources; do
  php .agents/skills/file-issue/scripts/file-issue.php \
    --level=level:package --package=$pkg --title="[Package] $pkg" \
    --sections='{}'
done

# Phase 2: Update epic labels + set parent
# (use gh issue edit with --add-label/--remove-label --parent)

# Phase 3: Verify
# - file-issue --level=level:epic --package=cli --parent=<cli-package-epic> --title="Test" --sections=...
# - Check package-index workflow runs
```