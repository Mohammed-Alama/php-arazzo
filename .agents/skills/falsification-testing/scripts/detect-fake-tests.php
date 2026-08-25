<?php

declare(strict_types=1);

// Fake Test Detector for php-arazzo
// Scans Pest/PHPUnit test files for the 7 red flags in the falsification skill.
// Usage:
//   php detect-fake-tests.php [path] [--json] [--strict]
//   php detect-fake-tests.php packages/core/tests/Runner/StepExecutorTest.php --json
//   php detect-fake-tests.php --all        (scans both packages)
// Exit: 0 = no violations, 1 = violations found, 2 = usage error

$root = dirname(__DIR__, 4);
$argvCopy = array_slice($argv, 1);

$json = in_array('--json', $argvCopy, true);
$strict = in_array('--strict', $argvCopy, true);
$all = in_array('--all', $argvCopy, true);

$paths = array_values(array_filter($argvCopy, static fn (string $a): bool => !str_starts_with($a, '--')));

if ($all || $paths === []) {
    if ($paths === [] && !$all) {
        // default: scan both packages
        $paths = ["{$root}/packages/core/tests", "{$root}/packages/laravel/tests"];
    } elseif ($all) {
        $paths = ["{$root}/packages/core/tests", "{$root}/packages/laravel/tests"];
    }
}

$files = [];
foreach ($paths as $p) {
    $abs = $p;
    if (!str_starts_with($p, '/')) {
        $abs = "{$root}/{$p}";
    }
    if (is_file($abs)) {
        $files[] = $abs;
    } elseif (is_dir($abs)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $files[] = $f->getPathname();
            }
        }
    } else {
        fwrite(STDERR, "error: path not found: {$p}\n");
        exit(2);
    }
}

if ($files === []) {
    fwrite(STDERR, "error: no php files found\n");
    exit(2);
}

$violations = [];
$totalTests = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    $rel = str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;

    // Count test definitions
    preg_match_all('/\b(it|test)\s*\(/', $content, $m);
    $testCount = count($m[0]);
    $totalTests += $testCount;

    // Helper to push violation
    $push = static function (string $code, string $msg, ?int $line = null) use (&$violations, $rel): void {
        $violations[] = ['file' => $rel, 'code' => $code, 'message' => $msg, 'line' => $line];
    };

    // 1) No meaningful assertion — expect(...)->not->toBeNull / toBeTruthy loose
    if (preg_match('/expect\s*\(.*\)\s*->\s*not\s*->\s*toBeNull\s*\(\)/', $content)) {
        $push('FAKE-1', 'uses not->toBeNull() instead of specific expectation (would pass on wrong value)');
    }
    if (preg_match('/->toBeTruthy\(\)|->toBeFalsy\(\)/', $content)) {
        $push('FAKE-1', 'uses toBeTruthy/toBeFalsy — prefer toBe(true/false) or specific value');
    }
    // No assertion at all in file with tests: count expect vs it
    preg_match_all('/\bexpect\s*\(/', $content, $em);
    if ($testCount > 0 && count($em[0]) === 0 && !str_contains($content, 'assert')) {
        $push('FAKE-1', "file has {$testCount} test(s) but zero expect()/assert calls");
    }

    // 2) Mirrors implementation — heuristic: recomputes same expression
    // flag if test computes expected via same helper as production
    if (preg_match('/\$expected\s*=\s*.*(?:evaluate|resolve|compile|normalize)\s*\(/i', $content)) {
        $push('FAKE-2', 'computes $expected by calling same evaluator/resolver as production — assert against independently-derived literal');
    }

    // 3) Only happy path — single test in file
    if ($testCount === 1) {
        $push('FAKE-3', 'only one test case in file — likely missing empty / boundary / invalid paths (Hume)');
    }
    // No edge keywords
    $edgeKeywords = ['empty', 'null', 'zero', 'invalid', 'throws', 'exception', 'fails', 'cycle'];
    $hasEdge = false;
    foreach ($edgeKeywords as $kw) {
        if (stripos($content, $kw) !== false) {
            $hasEdge = true;
            break;
        }
    }
    if ($testCount >= 1 && !$hasEdge) {
        $push('FAKE-3', 'no edge-case keyword (empty/null/invalid/exception/fails/cycle) found — likely happy-path only');
    }

    // 4) Mock call count without behavior
    $mockCount = substr_count($content, 'shouldReceive');
    $expectCount = substr_count($content, 'expect(');
    if ($mockCount > 0 && $expectCount === 0) {
        $push('FAKE-4', "uses Mockery shouldReceive ({$mockCount}x) but no expect() on observable behavior");
    }
    if ($mockCount > 0 && !preg_match('/getSteps|getState|status|->toBe\(/', $content)) {
        $push('FAKE-4', 'mocks interactions but never asserts on WorkflowContext/ExecutionState/Transition/status');
    }

    // 5) Vague assertion — toBeLessThan(500) etc
    if (preg_match('/toBeLessThan\s*\(\s*500/', $content) || preg_match('/toBeGreaterThan\s*\(/', $content)) {
        $push('FAKE-6', 'vague numeric bound (toBeLessThan/toBeGreaterThan) — assert exact status/shape/Transition');
    }
    if (preg_match('/status\(\)\s*\)\s*->\s*toBeLessThan/', $content)) {
        $push('FAKE-6', 'asserts status < 500 instead of exact expected status');
    }

    // 7) Core test imports Illuminate
    if (str_contains($rel, 'packages/core/tests') && preg_match('/use\s+Illuminate\\\\/', $content)) {
        $push('BOUNDARY', 'core test imports Illuminate — production code belongs in laravel, not core');
    }

    // Naming: it_calls_* vs it_marks_* — flag method-name mirroring
    if (preg_match('/it\s*\(\s*[\'"]calls\s+/', $content) || preg_match('/it\s*\(\s*[\'"]test\s+/', $content)) {
        $push('NAMING', 'test named after method under test (calls/test) — name after falsifiable claim instead');
    }

    // Pest strictness: executionOrder random means order-dependent tests are fragile
    if (preg_match('/->depends\(|->beforeEach.*global|static\s+\$counter/', $content)) {
        $push('STRICT', 'possible order-dependent test (depends/beforeEach global/static counter) — suite runs random order');
    }

    // Missing declare strict_types
    if (!str_contains($content, 'declare(strict_types=1)')) {
        $push('STYLE', 'missing declare(strict_types=1)');
    }
    // Single quote check (light)
    if ($strict && preg_match('/"/', $content) && preg_match('/it\s*\(\s*"/', $content)) {
        // only warn if pint would fix
        // $push('STYLE', 'uses double quotes for test descriptions — pint prefers single quotes');
    }
}

if ($json) {
    echo json_encode(['files' => count($files), 'tests' => $totalTests, 'violations' => $violations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "Scanned " . count($files) . " files, {$totalTests} test definitions\n";
    if ($violations === []) {
        echo "No fake-test violations found.\n";
    } else {
        echo count($violations) . " violation(s):\n";
        $byFile = [];
        foreach ($violations as $v) {
            $byFile[$v['file']][] = $v;
        }
        foreach ($byFile as $file => $list) {
            echo "\n  {$file}\n";
            foreach ($list as $v) {
                $line = $v['line'] !== null ? ":{$v['line']}" : '';
                echo "    [{$v['code']}]{$line} {$v['message']}\n";
            }
        }
        echo "\nTip: fix FAKE-1..6 before adding coverage — a green fake suite is decoration (see SKILL.md Fake Detector).\n";
    }
}

exit($violations === [] ? 0 : 1);
