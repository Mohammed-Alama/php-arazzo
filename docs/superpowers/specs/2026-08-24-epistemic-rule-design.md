# Epistemic Protocol Rule Design

## Overview
We are creating a custom Antigravity Rule to enforce the "Epistemic Protocol" (defined in `docs/epistemic-protocol.md`) for all AI interactions within the `php-arazzo` workspace. This rule will act as the cognitive constitution for the AI, ensuring its outputs are highly justified and not merely plausible.

## Architecture

### File Location and Metadata
- **Path**: `.agents/rules/epistemic.md`
- **Type**: Global Workspace Rule
- **Trigger**: `always_on: true` - This ensures the epistemic standards are applied to every prompt and action the AI takes, without needing explicit invocation.

### Rule Prompt Content (The "Embedded Core" Approach)
Instead of forcing the AI to read the full 273-line document on every turn, the rule will contain a dense, distilled version of the core protocol.

The rule will instruct the AI to follow these principles:

1. **Epistemic Tiers & Language Markers**: The AI must classify its knowledge and use specific markers:
   - *Tier 1 (Axiomatic)*: State directly.
   - *Tier 2 (Established)*: State confidently (based on direct codebase evidence).
   - *Tier 3 (Inferential)*: Must use "Based on [evidence]..." or "The pattern suggests...".
   - *Tier 4 (Speculative)*: Must use "X may be..." or "Confirm this with...".
   - *Tier 5 (Unknown)*: Must declare "I don't have reliable information on X."

2. **The `<epistemic_audit>` Block**: For tasks involving code analysis, debugging, or architectural decisions, the AI must output an XML block before its final response. This block forces the AI to show its work:
   - **Ontological Mapping**: What entities actually exist in the code being analyzed?
   - **Source Reliability**: Where did this knowledge come from? (Direct read > Docs > Inference > Memory).
   - **Falsifiability**: What evidence would prove this assumption wrong?

3. **Behavioral Constraints**:
   - Never reason from memory when direct codebase evidence is available.
   - Never silently promote a lower-reliability source to higher status (e.g., presenting an inference as a Tier 1 fact).

## Self-Review Checklist
- [x] No placeholders (TBD, TODO).
- [x] Internal consistency: The XML block requirement aligns with the hybrid enforcement approach agreed upon.
- [x] Scope: Highly focused on a single rule file creation. No decomposition needed.
- [x] Ambiguity: The trigger (`always_on: true`) and location (`.agents/rules/epistemic.md`) are explicitly defined.

## Next Steps
Upon user approval of this spec, transition to the `writing-plans` skill to generate the implementation plan for creating the rule file.
