<?php

declare(strict_types=1);

// Property audit — Goodman/Quine grue: lines hit ≠ properties hold.
// Lists invariants from tests/Property/InvariantsTest.php and checks coverage-insights for grue gaps.
// Usage: php property-audit.php [--list] [--json] [--package core] [--check]
// Exit: 0 if no uncovered properties (or --list mode), 1 if gaps, 2 usage

$root = dirname(__DIR__, 4);
$args = array_slice($argv, 1);
$has = static fn (string $f): bool => in_array($f, $args, true);
$get = static function (string $f) use ($args): ?string {
    $i = array_search($f, $args, true);
    if ($i === false || !isset($args[$i+1])) return null;
    $v = $args[$i+1];
    return str_starts_with($v, '--') ? null : $v;
};
if ($has('--help') || $has('-h')) {
    fwrite(STDERR, "usage: php property-audit.php [--list] [--check] [--json] [--package core|laravel|all]\n");
    exit(0);
}
$json = $has('--json');
$package = $get('--package') ?? 'core';

// Known properties from InvariantsTest.php + domain invariants (grue = same lines, different property)
$properties = [
    ['id' => 'json_pointer_roundtrip', 'file' => 'tests/Property/InvariantsTest.php', 'line' => 'it round-trips random JSON pointers', 'covers' => 'Runner/Evaluation/JsonPointer.php', 'type' => 'property', 'status' => 'known'],
    ['id' => 'runtime_expr_equivalence', 'file' => 'tests/Property/InvariantsTest.php', 'line' => 'accepts equivalent runtime-expression spellings', 'covers' => 'Expression/Lexer.php', 'type' => 'property', 'status' => 'known'],
    ['id' => 'dag_acyclic_orders', 'file' => 'tests/Property/InvariantsTest.php', 'line' => 'returns acyclic-consistent topological orders', 'covers' => 'Runner/Evaluation/DependencyGraph.php', 'type' => 'property', 'status' => 'known'],
    // Grue candidates — lines covered but property not pinned
    ['id' => 'execution_state_immutability', 'file' => 'Runner/Context/ExecutionState.php', 'line' => 'with* immutability (withStepResult returns new)', 'covers' => 'Runner/Context/ExecutionState.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'workflow_context_immutability', 'file' => 'Runner/Context/WorkflowContext.php', 'line' => 'withStepResult immutability', 'covers' => 'Runner/Context/WorkflowContext.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'dependency_graph_diamond', 'file' => 'Runner/Evaluation/DependencyGraph.php', 'line' => 'diamond graph', 'covers' => 'Runner/Evaluation/DependencyGraph.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'step_budget_enforcement', 'file' => 'Runner/Execution/WorkflowEngine.php', 'line' => 'maxSteps / stepsSpent at budget', 'covers' => 'Runner/Execution/WorkflowEngine.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'idempotency_header_injection', 'file' => 'Runner/Execution/IdempotencyKeyInjector.php', 'line' => 'Idempotency-Key header determinism', 'covers' => 'Runner/Execution/IdempotencyKeyInjector.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'openapi_normalizer_30_31', 'file' => 'Runner/Normalizer/OpenApi30Normalizer.php', 'line' => '3.0 vs 3.1 normalization equivalence', 'covers' => 'Runner/Normalizer/OpenApi30Normalizer.php', 'type' => 'grue', 'status' => 'candidate'],
    ['id' => 'expression_vs_literal', 'file' => 'Spec/Expression.php', 'line' => 'Expression vs literal string {$...} heuristic', 'covers' => 'Expression/Parser.php', 'type' => 'grue', 'status' => 'candidate'],
];

$check = $has('--check') || !$has('--list');
// If --list, just list; if --check, check coverage for each property's covers file
$results = [];
foreach ($properties as $p) {
    $coversPath = "{$root}/packages/{$package}/coverage-report/{$p['covers']}.html";
    $cov = null;
    $uncovered = null;
    if (file_exists($coversPath)) {
        // Quick parse: extract percent from file html (same as query-coverage)
        $html = file_get_contents($coversPath);
        if (preg_match('/aria-valuenow="([\d.]+)"/', (string)$html, $m)) $cov = (float)$m[1];
        // Count danger lines as proxy for property gap
        if (preg_match_all('/class="danger d-flex"/', (string)$html, $mm)) $uncovered = count($mm[0]);
    }
    // Check if property test exists: grep for id in InvariantsTest
    $testExists = false;
    $inv = "{$root}/packages/core/tests/Property/InvariantsTest.php";
    if (file_exists($inv)) {
        $c = file_get_contents($inv);
        if (str_contains((string)$c, $p['line']) || str_contains((string)$c, $p['id'])) $testExists = true;
    }
    // For grue candidates, testExists is false unless candidate has dedicated property
    $status = $testExists ? 'pinned' : ($p['type'] === 'grue' ? 'grue_gap' : 'missing');
    $results[] = array_merge($p, ['coverage' => $cov, 'uncovered_lines' => $uncovered, 'test_exists' => $testExists, 'status' => $status]);
}

$uncoveredProps = array_values(array_filter($results, fn ($r) => $r['status'] === 'grue_gap'));
$pass = count($uncoveredProps) === 0;

$out = [
    'package' => $package,
    'total_properties' => count($results),
    'pinned' => count(array_filter($results, fn ($r) => $r['status'] === 'pinned')),
    'grue_gaps' => count($uncoveredProps),
    'properties' => $results,
    'uncovered_properties' => $uncoveredProps,
    'pass' => $pass,
    'advice' => $pass ? 'all grue properties pinned' : 'add property test for: ' . implode(', ', array_column($uncoveredProps, 'id')) . ' — see tests/Property/InvariantsTest.php',
];

if ($json) {
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($pass ? 0 : 1);
}

// Human
echo "Property audit (Goodman grue) — {$package}\n";
echo "  Total: " . count($results) . " | pinned: " . count(array_filter($results, fn($r)=>$r['status']==='pinned')) . " | grue gaps: " . count($uncoveredProps) . " → " . ($pass ? "PASS" : "FAIL") . "\n";
foreach ($results as $r) {
    $icon = $r['status'] === 'pinned' ? '✓' : ($r['status'] === 'grue_gap' ? '◌' : '✗');
    $cov = $r['coverage'] !== null ? sprintf("%.1f%%", $r['coverage']) : "n/a";
    $unc = $r['uncovered_lines'] !== null ? " uncovered {$r['uncovered_lines']}" : "";
    echo "  {$icon} {$r['id']} — {$r['covers']} {$cov}{$unc} — {$r['status']}\n";
}
if ($uncoveredProps !== []) {
    echo "  tip: `tests/Property/InvariantsTest.php` is seeded (mt_srand) for reproducibility — add `it('{$uncoveredProps[0]['id']}')` there\n";
}
exit($pass ? 0 : 1);
