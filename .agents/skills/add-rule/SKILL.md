---
name: add-rule
description: Scaffold a new Arazzo validator rule (Rule class + Pest test + RuleSet registration) in packages/core. Use when adding a validation rule, error code, or spec-conformance check.
---

# Add Validator Rule

Every rule is three coordinated edits (class, test, registration). The script does all three deterministically; your job is designing the check.

## Step 1 — Generate

```bash
php .agents/skills/add-rule/scripts/new-rule.php <Name> <dotted.code>
```

- Name: PascalCase or kebab-case; the `Rule` suffix is added automatically.
- Code: dotted lower_snake segments matching existing codes (`source.unique_name`, `step.outputs_unique`, `workflow.inputs_valid_schema`) — pick a segment prefix that matches what the rule inspects (`source.*`, `step.*`, `workflow.*`, `action.*`, `expression.*`).
- Add `--dry-run` to preview placement without writing.

The script fails loudly instead of overwriting anything.

## Step 2 — Implement

Open the generated `check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors)` and implement it:

- Report via `$errors->error($this->code(), "<message>", "/json/pointer")` — pointers use the raw document shape.
- Read spec data through the typed DTOs (`$doc->workflows`, `$doc->sourceDescriptions`, …), never the raw array.
- Extend the generated test with one failing case per error path; keep the generated no-op assertion as the empty-document baseline.

## Step 3 — Verify

```bash
vendor/bin/pint packages/core/src/Validator/Rules/<Class>.php packages/core/tests/Validator/Rules/<Class>Test.php
cd packages/core && vendor/bin/pest tests/Validator/Rules/<Class>Test.php
cd ../.. && composer run analyse
```

Completion criterion: the new test passes with both no-op and failure cases, `composer run analyse` is clean, and `new <Class>()` sits in `RuleSet::default()` so the rule runs on every conformance fixture.
