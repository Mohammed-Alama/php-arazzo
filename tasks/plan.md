# Plan: Software Factory Playbook (V1)

> Spec: `docs/superpowers/specs/2026-11-16-software-factory-playbook-design.md`
> Intent: `docs/intent/software-factory-playbook.md`

## Components & dependencies

1. **Quick-path table** (top of playbook) — depends on stages 1–8 being drafted (content known).
2. **Stage sections** (8 stages) — independent of each other; each follows the stage template.
3. **Intake spine section** — depends on stages (references file-issue/gh commands) but can be drafted in parallel.
4. **Supplement seam callouts** — inline within stages 3, 4, 5, 6 (quality bar, source-grounding, adversarial, debugging).
5. **Appendix** (see-also + failure modes per gate) — independent.
6. **Verification pass** — depends on all above.

## Implementation order

- Wave A (parallel): draft each of the 8 stage sections + intake spine section + appendix.
- Wave B: assemble into `docs/SOFTWARE-FACTORY-PLAYBOOK.md` with quick-path table + cross-references.
- Wave C: verification pass (links, commands, skill existence, drift check, human operability).

## Risks & mitigations

- **Command drift:** playbook commands must match actual repo commands. Mitigation: run each fenced command (or dry-run) during verification.
- **Duplication with SKILLS-REPORT.md:** playbook must cross-reference, not duplicate. Mitigation: verification checklist item.
- **Scope creep into AFK autonomy:** explicitly out of scope; keep to human-in-the-loop.

## Verification checkpoints

- CP1: All 8 stage sections drafted and follow template.
- CP2: Assembled doc has quick-path table + intake spine + appendix.
- CP3: All links resolve, all commands verified, no drift vs SKILLS-REPORT.md §3, human operability confirmed.
