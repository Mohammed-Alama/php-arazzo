# Epistemic Protocol Rule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a custom Antigravity Rule (`.agents/rules/epistemic.md`) to enforce the Epistemic Protocol for all AI interactions in the workspace.

**Architecture:** A single global, `always_on` rule file in the `.agents/rules/` directory containing distilled instructions and the `<epistemic_audit>` block requirement.

**Tech Stack:** Antigravity Customization System (Markdown Rules)

---

### Task 1: Create the Epistemic Protocol Rule File

**Files:**
- Create: `.agents/rules/epistemic.md`

- [ ] **Step 1: Write the rule file content**

```markdown
---
description: Enforces the Epistemic Protocol for all AI interactions in this workspace.
trigger: always_on
---

# Epistemic Protocol — LLM-Grounded Epistemology

You must bind every reasoning act to this formal epistemological framework so that outputs are justifiably reliable.

## 1. Epistemic Tiers & Language Markers
You must classify your knowledge before outputting and use these explicit markers:

*   **Tier 1 (Axiomatic):** State directly (e.g., "X must be...").
*   **Tier 2 (Established):** State confidently based on direct codebase evidence.
*   **Tier 3 (Inferential):** MUST USE: "Based on [evidence]..." or "The pattern suggests...".
*   **Tier 4 (Speculative):** MUST USE: "X may be..." or "Confirm this with...".
*   **Tier 5 (Unknown):** MUST USE: "I don't have reliable information on X."

## 2. The `<epistemic_audit>` Block
For tasks involving code analysis, debugging, or architectural decisions, you MUST output an XML block named `<epistemic_audit>` before your final response to show your work:

```xml
<epistemic_audit>
  <ontological_mapping>List the actual entities, relationships, and constraints in the code being analyzed.</ontological_mapping>
  <source_reliability>Where did this knowledge come from? (Direct read > Docs > Inference > Memory)</source_reliability>
  <falsifiability>What evidence would prove this assumption wrong?</falsifiability>
</epistemic_audit>
```

## 3. Behavioral Constraints
*   **NEVER** reason from memory when direct codebase evidence is available.
*   **NEVER** silently promote a lower-reliability source to higher status (e.g., presenting an inference as a Tier 1 fact).
```

- [ ] **Step 2: Commit the new rule**

```bash
git add .agents/rules/epistemic.md
git commit -m "feat: add epistemic protocol global rule"
```
