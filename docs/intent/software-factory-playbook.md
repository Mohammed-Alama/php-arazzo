# Intent: Software Factory Playbook

**Status:** Confirmed (interview-me, 2026-11-16)
**Scope:** Documented playbook (Markdown), not a new skill.

## Statement of Intent

- **Outcome:** A documented playbook (Markdown) mapping each stage of the idea→ship loop to the right skill(s), with explicit handoff points into `file-issue`/`gh` and the intake gate.
- **User:** Solo maintainer, human-in-the-loop. AFK autonomy is a future aspiration, not this scope.
- **Why now:** The repo just gained a large set of agent-skills with no composition map against the existing Matt Pocock flows and the project's gh/file-issue intake spine.
- **Success:** A single doc a maintainer can open to know, for any stage, which skill to invoke, what artifact it produces, and how that artifact becomes a conforming issue/PR via `file-issue`/`gh`.
- **Constraint:** Matt Pocock skills stay primary where already wired (lock-tracked, cross-referenced); new agent-skills act as supplements at specific seams (quality bar, source-grounding, adversarial review, debugging depth). No new orchestrating skill.
- **Out of scope:** Autonomous AFK runner, new skill creation, editing locked upstream skills, migrating primary flows onto the new agent-skills.

## Notes

- Companion to `docs/SKILLS-REPORT.md` (which maps skills to development activities generally).
- This playbook specifically wires the stage→skill map to the `file-issue`/`gh` intake spine and the triage state machine.
- Next suggested step: draft the playbook spec with `spec-driven-development`, or move to `planning-and-task-breakdown`.
