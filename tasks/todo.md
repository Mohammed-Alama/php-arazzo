# Task List: Software Factory Playbook (V1)

- [x] Task 1: Draft Stage 1 — Idea → Spec
  - Acceptance: section follows stage template; names grill-with-docs/to-spec/spec-driven-development; has procedure + artifact + handoff + verification
  - Verify: renders as valid Markdown; links resolve
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md (working draft)

- [x] Task 2: Draft Stage 2 — Spec → Tickets
  - Acceptance: names to-tickets/planning-and-task-breakdown; includes file-issue/gh handoff for publishing tickets; supplement: incremental-implementation pointer
  - Verify: file-issue example validates via validate-issue
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 3: Draft Stage 3 — Ticket → Code
  - Acceptance: names implement/tdd; supplement seams: source-driven-development, incremental-implementation, constraint-driven-development (quality bar)
  - Verify: commands accurate
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 4: Draft Stage 4 — Code → Review
  - Acceptance: names code-review/code-review-and-quality; supplement: doubt-driven-development
  - Verify: links resolve
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 5: Draft Stage 5 — Review → PR
  - Acceptance: names pr-prep/git-workflow-and-versioning; handoff to gh PR
  - Verify: commands accurate
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 6: Draft Stage 6 — PR → Release
  - Acceptance: names release/shipping-and-launch; gates before release
  - Verify: commands accurate
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 7: Draft Stage 7 — Bug path
  - Acceptance: names diagnosing-bugs/debugging-and-error-recovery; supplement: debugging depth seam
  - Verify: commands accurate
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 8: Draft Stage 8 — Quality bar (cross-cutting)
  - Acceptance: names constraint-driven-development/falsification-testing; how quality bar gates stages 3–6
  - Verify: links resolve
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 9: Draft Intake Spine section
  - Acceptance: arazzo-detective → file-issue → gh shim → CI gate → triage flow; exact validator JSON shape; code-permalink usage
  - Verify: file-issue example validates via validate-issue
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 10: Draft Appendix (see-also + failure modes per gate)
  - Acceptance: lists git-workflow-and-versioning, idea-refine, context-engineering with one-line pointers; failure-modes table per gate
  - Verify: links resolve
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 11: Assemble playbook + quick-path table + cross-references
  - Acceptance: single file docs/SOFTWARE-FACTORY-PLAYBOOK.md; quick-path table at top; cross-refs to SKILLS-REPORT.md
  - Verify: renders; links resolve
  - Files: docs/SOFTWARE-FACTORY-PLAYBOOK.md

- [x] Task 12: Verification pass
  - Acceptance: all links resolve; all fenced commands verified; no drift vs SKILLS-REPORT.md §3; human operability confirmed
  - Verify: make verify green; manual review
  - Files: (none new)
