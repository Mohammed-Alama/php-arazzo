========================================
 Falsification Comprehensive Report (V1+coverage+V2)
 Package: core  2026-08-25T09:44:00Z
 Skill: .agents/skills/falsification-testing
========================================

1) Fake Test Detector (Popper)
----------------------------------------
Scanned 206 files, 674 test definitions
98 violation(s):

  packages/core/tests/Dto/ContainerDtoTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Dto/Enum/FormatTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Dto/Action/SubWorkflowActionTest.php
    [FAKE-3] no edge-case keyword (empty/null/invalid/exception/fails/cycle) found — likely happy-path only

  packages/core/tests/Dto/ExpressionTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Ecosystem/FeedTest.php
    [FAKE-3] no edge-case keyword (empty/null/invalid/exception/fails/cycle) found — likely happy-path only

  packages/core/tests/Validator/ValidatorTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Validator/RuleSetTest.php
    [FAKE-3] no edge-case keyword (empty/null/invalid/exception/fails/cycle) found — likely happy-path only

  packages/core/tests/Validator/Rules/ComponentsUniqueNamesRuleTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Validator/Rules/StepOutputsUniqueRuleTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Validator/Rules/ExpressionContextMisuseRuleTest.php
    [FAKE-3] no edge-case keyword (empty/null/invalid/exception/fails/cycle) found — likely happy-path only

  packages/core/tests/Validator/Rules/SourceUrlSyntaxRuleTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Validator/Rules/StepNestedWorkflowExistsRuleTest.php
    [FAKE-3] only one test case in file — likely missing empty / boundary / invalid paths (Hume)

  packages/core/tests/Validator/Rules/DocumentArazzoVersionRuleTest.php

2) Hume Boundaries (0/1/max/equal) — WorkflowEngine
----------------------------------------
Hume boundary audit for: WorkflowEngine
 tests: /Users/mohammedalama/Code/Me/php-arazzo/packages/core/tests

Checklist (9 classes):
  [~hit] empty workflow (0 steps)
  [~hit] single step
  [~hit] maxSteps at budget / stepsSpent==maxSteps
  [~hit] maxWorkflowDepth at ceiling
  [~hit] dependsOn diamond vs linear vs cycle
  [~hit] onSuccess goto missing target
  [~hit] onFailure end vs goto vs retry
  [MISS] suspend/receive without correlation
  [~hit] retryLimit vs retry_ceiling (10)

1 boundary class(es) with no keyword hit in tests — likely uncovered (verify manually).
Tip: for each MISS, add at least one test at the exact boundary (Pass 2). Use --json for machine output.

Domain tip (php-arazzo): always include OAI corpus sanity — FixtureRunner vs QueueFixtureRunner parity and EdgeCaseFixtures (complex-cyclic-dependency, invalid fixtures).

3) Coverage Overview (Pest HTML)
----------------------------------------
=== core ===
Total: 87.55% lines (2896/3308) | functions 77.57% | classes 72.53%
Directories:
  🟢 88.24% (225/255) Expression
  🟢 100.00% (33/33) Generator
  🟡 60.00% (3/5) License
  🟢 97.48% (426/437) Parser
  🟢 88.51% (77/87) Resolver
  🟢 80.78% (1421/1759) Runner
  🟢 100.00% (46/46) Spec
  🟢 96.77% (60/62) Support
  🟢 96.96% (605/624) Validator
Hotspots (lowest coverage):
  0% Alama\Arazzo\Expression\Ast\InputPart
  0% Alama\Arazzo\Expression\Ast\OutputRef
  0% Alama\Arazzo\License\LicenseException
  0% Alama\Arazzo\Runner\Context\VariableContext
  0% Alama\Arazzo\Runner\Evaluation\ArazzoExpressionEvaluator
  0% Alama\Arazzo\Runner\Jobs\ResumeCorrelationJob
  0% Alama\Arazzo\Validator\Exceptions\ValidationException

4) Coverage Hotspots (lowest 5)
----------------------------------------
=== core ===
Total: 87.55% lines (2896/3308) | functions 77.57% | classes 72.53%
Hotspots (lowest coverage):
  0% Alama\Arazzo\Expression\Ast\InputPart
  0% Alama\Arazzo\Expression\Ast\OutputRef
  0% Alama\Arazzo\License\LicenseException
  0% Alama\Arazzo\Runner\Context\VariableContext
  0% Alama\Arazzo\Runner\Evaluation\ArazzoExpressionEvaluator


5) Hume Mutation (dry-run MSI)
----------------------------------------
=== [core] pest --mutate --covered-only  ===
(dry-run) would run: cd /Users/mohammedalama/Code/Me/php-arazzo/packages/core && vendor/bin/pest --mutate --covered-only 
=== [laravel] pest --mutate --covered-only  ===
(dry-run) would run: cd /Users/mohammedalama/Code/Me/php-arazzo/packages/laravel && vendor/bin/pest --mutate --covered-only 

Hume audit: all packages meet MSI >= 80%

6) Severity Audit (Lakatos/Mayo)
----------------------------------------
Severity audit --all
  Fake: FAIL (98 violations) → score 0.1
  Hume MSI: 80% → score 0.8
  Severity: 0.45 (LOW) threshold 0.7 → FAIL
  Advice: not severe — add mutant-killing test, check delete-fix-check.sh --filter ""
  Tip: `make detect-fake` + `make hume-audit` + `bash delete-fix-check.sh --filter ""`

7) Property Audit (Goodman grue)
----------------------------------------
Property audit (Goodman grue) — core
  Total: 10 | pinned: 2 | grue gaps: 7 → FAIL
  ✗ json_pointer_roundtrip — Runner/Evaluation/JsonPointer.php 90.0% uncovered 1 — missing
  ✓ runtime_expr_equivalence — Expression/Lexer.php 98.2% uncovered 1 — pinned
  ✓ dag_acyclic_orders — Runner/Evaluation/DependencyGraph.php 95.5% uncovered 2 — pinned
  ◌ execution_state_immutability — Runner/Context/ExecutionState.php 81.8% uncovered 8 — grue_gap
  ◌ workflow_context_immutability — Runner/Context/WorkflowContext.php 100.0% — grue_gap
  ◌ dependency_graph_diamond — Runner/Evaluation/DependencyGraph.php 95.5% uncovered 2 — grue_gap
  ◌ step_budget_enforcement — Runner/Execution/WorkflowEngine.php 32.9% uncovered 51 — grue_gap
  ◌ idempotency_header_injection — Runner/Execution/IdempotencyKeyInjector.php 97.7% uncovered 1 — grue_gap
  ◌ openapi_normalizer_30_31 — Runner/Normalizer/OpenApi30Normalizer.php 95.2% uncovered 4 — grue_gap
  ◌ expression_vs_literal — Expression/Parser.php 79.1% uncovered 27 — grue_gap
  tip: `tests/Property/InvariantsTest.php` is seeded (mt_srand) for reproducibility — add `it('execution_state_immutability')` there

8) Socratic Fuzz (Hegelian agon, 10 iterations)
----------------------------------------
Socratic fuzz — 10 iterations from LoginAndRetrievePets.arazzo.yaml
  Killed: 10 / 10 (1) | Survived: 0 → PASS
  duplicate_stepId: 1
  cycle_dependsOn: 1
  missing_source: 1
  empty_workflow: 1
  negative_maxSteps: 1
  null_stepId: 1
  goto_missing_target: 1
  retry_exhaustion: 1
  unicode_stepId: 1
  10MB_yaml: 1
  

9) Demon Sim (Cartesian, 3 seeds)
----------------------------------------
Demon sim — core seeds=3
  Flaky: 0/3 | Order dependence: no | Time sensitive files: packages/core/src/License/NullLicenseVerifier.php, packages/core/src/License/LicenseVerifierInterface.php → PASS
  seed 1000: exit 0 passed 659 failed 49
  seed 1137: exit 0 passed 659 failed 49
  seed 1274: exit 0 passed 658 failed 49

========================================
 Human report complete. For agent JSON:
   bash /Users/mohammedalama/Code/Me/php-arazzo/.agents/skills/falsification-testing/scripts/generate-report.sh --json | jq
   make report-json
