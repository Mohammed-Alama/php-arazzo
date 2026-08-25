<?php

declare(strict_types=1);

// Hume Boundary Audit — generates a checklist of equivalence classes / boundaries
// for a given Arazzo/PHP feature and cross-checks existing tests.
// Usage:
//   php audit-boundaries.php <src-or-feature> [--tests <tests-dir>] [--json]
// Examples:
//   php audit-boundaries.php packages/core/src/Runner/Execution/StepExecutor.php
//   php audit-boundaries.php Parser --tests packages/core/tests/Parser
//   php audit-boundaries.php WorkflowEngine --json

$root = dirname(__DIR__, 4);
$arg = $argv[1] ?? null;
if ($arg === null || $arg === '--help' || $arg === '-h') {
    fwrite(STDERR, "usage: php audit-boundaries.php <src-file|keyword> [--tests <dir>] [--json]\n");
    exit(2);
}

$json = in_array('--json', $argv, true);
$testsDir = null;
foreach ($argv as $i => $a) {
    if ($a === '--tests' && isset($argv[$i + 1])) {
        $testsDir = $argv[$i + 1];
    }
}

$srcPath = $arg;
if (!str_starts_with($arg, '/') && file_exists("{$root}/{$arg}")) {
    $srcPath = "{$root}/{$arg}";
}

$isFile = is_file($srcPath);
$keyword = $isFile ? basename($srcPath, '.php') : $arg;
$content = $isFile ? (file_get_contents($srcPath) ?: '') : '';

// Domain-specific boundary knowledge base for php-arazzo
$kb = [
    'Parser' => ['empty document', 'single workflow', 'single step', 'missing required field (info/version)', 'invalid YAML/JSON syntax', 'expression vs literal {$...}', 'reusable $ref', 'unknown field', 'null vs missing'],
    'Validator' => ['empty workflows/steps', 'dangling dependsOn', 'cycle dependsOn', 'duplicate ids', 'missing sourceDescription', 'unsupported source type', 'invalid expression syntax', 'unresolved component/input/step/source ref'],
    'SourceResolver' => ['file:// vs http:// vs https://', '404 / timeout / malformed OpenAPI', 'empty sourceDescriptions', 'duplicate source name', 'large OpenAPI (max size)', 'cached vs fresh fetcher'],
    'Expression' => ['null / missing path', 'empty string', 'unicode', 'nested JSONPath', 'out-of-bounds index', 'type mismatch (string vs int)', 'timezone/clock in selector'],
    'StepExecutor' => ['strictValidation global vs per-step (x-strict-validation)', 'idempotency injector enabled/disabled (x-idempotency-key)', 'empty requestBody/parameters', 'retry exhaustion vs success', 'schema validation throws', 'PSR-18 timeout mid-execution'],
    'WorkflowEngine' => ['empty workflow (0 steps)', 'single step', 'maxSteps at budget / stepsSpent==maxSteps', 'maxWorkflowDepth at ceiling', 'dependsOn diamond vs linear vs cycle', 'onSuccess goto missing target', 'onFailure end vs goto vs retry', 'suspend/receive without correlation', 'retryLimit vs retry_ceiling (10)'],
    'WorkflowExecutor' => ['sync vs queued parity (FixtureRunner vs QueueFixtureRunner)', 'sharedBudget / stepsSpent', 'context withStepResult immutability'],
    'ExecutionState' => ['start with empty inputs', 'fromArray/toArray round-trip', 'stepsSpent == maxSteps boundary', 'workflowCallStack depth == maxWorkflowDepth'],
    'DependencyGraph' => ['zero dep', 'one dep', 'diamond graph', 'cycle detection', 'implicit expression dep'],
    'default' => ['zero / empty / null', 'one (smallest non-empty)', 'max / ceiling', 'exactly-equal boundary', 'discontinuity (midnight/timezone/currency/3.0 vs 3.1)', 'invalid / malformed input'],
];

// Pick best matching key
$boundaries = $kb['default'];
foreach ($kb as $k => $v) {
    if ($k === 'default') {
        continue;
    }
    if (stripos($keyword, $k) !== false || stripos($arg, $k) !== false) {
        $boundaries = $v;
        break;
    }
    if ($isFile && stripos($content, $k) !== false) {
        $boundaries = $v;
        break;
    }
}

// Enrich with literals found in source: maxSteps, maxWorkflowDepth, retry, etc.
$literals = [];
if ($isFile && $content !== '') {
    preg_match_all('/\b(maxSteps|maxWorkflowDepth|retryLimit|retry_ceiling|state_ttl|stepsSpent)\b/', $content, $m);
    foreach (array_unique($m[0]) as $lit) {
        $boundaries[] = "boundary for {$lit}: 0, 1, max-1, max, max+1";
    }
    // Numeric limits
    preg_match_all('/\b([0-9]{2,})\b/', $content, $nums);
    $uniqNums = array_unique($nums[1]);
    sort($uniqNums);
    if (count($uniqNums) > 0 && count($uniqNums) < 10) {
        $boundaries[] = 'numeric literals in file: ' . implode(', ', $uniqNums) . ' — test ±1 around each';
    }
}

// Try to grep tests for coverage
$hits = [];
$misses = [];
if ($testsDir !== null) {
    if (!str_starts_with($testsDir, '/')) {
        $testsDir = "{$root}/{$testsDir}";
    }
}
if ($testsDir === null) {
    // infer
    if (str_contains($srcPath, 'packages/core')) {
        $testsDir = "{$root}/packages/core/tests";
    } elseif (str_contains($srcPath, 'packages/laravel')) {
        $testsDir = "{$root}/packages/laravel/tests";
    } else {
        $testsDir = "{$root}/packages/core/tests";
    }
}
$testContent = '';
if (is_dir($testsDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $testContent .= file_get_contents($f->getPathname()) . "\n";
        }
    }
}
foreach ($boundaries as $b) {
    // naive hit: keyword fragment appears in tests
    $fragment = strtolower(preg_split('/\s+/', $b)[0] ?? $b);
    $fragment = trim($fragment, ':,');
    if ($fragment !== '' && stripos($testContent, $fragment) !== false) {
        $hits[] = $b;
    } else {
        $misses[] = $b;
    }
}

$result = [
    'target' => $arg,
    'resolved' => $isFile ? $srcPath : $keyword,
    'testsDir' => $testsDir,
    'boundaries' => $boundaries,
    'hits' => $hits,
    'misses' => $misses,
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "Hume boundary audit for: {$arg}\n";
if ($isFile) {
    echo " file: {$srcPath}\n";
}
echo " tests: {$testsDir}\n";
echo "\nChecklist (" . count($boundaries) . " classes):\n";
foreach ($boundaries as $b) {
    $mark = in_array($b, $hits, true) ? '[~hit]' : '[MISS]';
    echo "  {$mark} {$b}\n";
}
if ($misses !== []) {
    echo "\n" . count($misses) . " boundary class(es) with no keyword hit in tests — likely uncovered (verify manually).\n";
    echo "Tip: for each MISS, add at least one test at the exact boundary (Pass 2). Use --json for machine output.\n";
} else {
    echo "\nAll boundary keywords seen in tests (heuristic — still verify the assertion is specific, not vague).\n";
}
echo "\nDomain tip (php-arazzo): always include OAI corpus sanity — FixtureRunner vs QueueFixtureRunner parity and EdgeCaseFixtures (complex-cyclic-dependency, invalid fixtures).\n";
