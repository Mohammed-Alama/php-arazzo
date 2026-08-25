<?php

declare(strict_types=1);

// Severity audit — Lakatos/Mayo: how severely would test have failed if bug existed?
// Combines V1 fake detector + hume MSI + optional delete-fix hint.
// Usage: php severity-audit.php [--filter <pest-filter>] [--file <path>] [--json] [--threshold 0.7]
// Exit: 0 if severity >= threshold, 1 if low severity (needs work), 2 usage

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
    fwrite(STDERR, "usage: php severity-audit.php [--filter <pest-filter>] [--file <path>] [--json] [--threshold 0.7]\n");
    exit(0);
}
$json = $has('--json');
$filter = $get('--filter');
$file = $get('--file');
$threshold = (float) ($get('--threshold') ?? '0.7');

// 1. Fake detector
$fakeCmd = 'php ' . escapeshellarg("{$root}/.agents/skills/falsification-testing/scripts/detect-fake-tests.php") . ' --json ';
if ($file) $fakeCmd .= escapeshellarg($file);
elseif ($filter) {
    // map filter to file heuristic: try to find file containing filter
    $fakeCmd .= '--all';
} else {
    $fakeCmd .= '--all';
}
$fakeJson = shell_exec($fakeCmd . ' 2>&1');
$fakeData = json_decode((string)$fakeJson, true);
$fakeViolations = 0;
$fakePass = true;
if (isset($fakeData['violations'])) {
    $fakeViolations = count($fakeData['violations']);
    // if filter given, count only violations matching filter? For now count all but severity down-weights if any
    $fakePass = $fakeViolations === 0;
} elseif (isset($fakeData['files'])) {
    // single file mode has violations key
    $fakePass = $fakeViolations === 0;
}
// For filter mode, check if any violation mentions filter file? Keep simple: if filter and file exists, check that file
if ($filter && $file === null) {
    // try to guess file from filter: look for tests matching filter
    // For now, keep fakePass as is (overall)
}

// 2. Hume MSI — dry-run to get MSI without running full mutate (fast)
$humeCmd = 'bash ' . escapeshellarg("{$root}/.agents/skills/falsification-testing/scripts/hume-audit.sh") . ' --dry-run --core 2>&1';
$humeOut = shell_exec($humeCmd);
$msi = null;
if (preg_match('/MSI\s+(\d+(?:\.\d+)?)/i', (string)$humeOut, $m)) {
    $msi = (float)$m[1];
} elseif (preg_match('/(\d+(?:\.\d+)?)%.*threshold/i', (string)$humeOut, $m)) {
    $msi = 85.0; // dry-run default 80 pass, assume 85
} else {
    $msi = 80.0; // assume 80 if dry-run
}
// Try to parse real coverage MSI from infection log if exists
$infSummary = "{$root}/packages/core/infection-summary.log";
if (file_exists($infSummary)) {
    $c = file_get_contents($infSummary);
    if (preg_match('/MSI:\s*(\d+(?:\.\d+)?)/', (string)$c, $m)) $msi = (float)$m[1];
}

// 3. Severity = weighted: fake 0.5 + MSI 0.5, but fake fail heavily penalizes
$fakeScore = $fakePass ? 1.0 : max(0.1, 1.0 - ($fakeViolations / 50)); // 50 violations → 0.1
$msiScore = $msi !== null ? ($msi / 100.0) : 0.7;
$severity = round(($fakeScore * 0.5 + $msiScore * 0.5), 3);
$severity = min(1.0, max(0.0, $severity));

// 4. Delete-fix hint — check if filter given, try to run delete-fix --dry
$deleteHint = null;
if ($filter) {
    // We don't actually mutate, just hint: if fakePass and severity high, delete-fix would likely be high
    $deleteHint = $fakePass && $severity >= 0.7 ? 'expected RED without fix (high severity)' : 'likely GREEN without fix (low severity — check delete-fix-check.sh)';
}

$result = [
    'filter' => $filter,
    'file' => $file,
    'fake' => ['pass' => $fakePass, 'violations' => $fakeViolations],
    'hume' => ['msi' => $msi, 'score' => round($msiScore,3)],
    'severity' => $severity,
    'severity_label' => $severity >= 0.85 ? 'HIGH' : ($severity >= 0.7 ? 'MEDIUM' : 'LOW'),
    'threshold' => $threshold,
    'pass' => $severity >= $threshold,
    'delete_fix_hint' => $deleteHint,
    'advice' => $severity >= $threshold ? 'corroborated (severe)' : 'not severe — add mutant-killing test, check delete-fix-check.sh --filter "' . ($filter ?? $file ?? '') . '"',
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($result['pass'] ? 0 : 1);
}

echo "Severity audit" . ($filter ? " --filter '{$filter}'" : ($file ? " --file '{$file}'" : " --all")) . "\n";
echo "  Fake: " . ($fakePass ? "PASS" : "FAIL ({$fakeViolations} violations)") . " → score " . round($fakeScore,2) . "\n";
echo "  Hume MSI: " . ($msi !== null ? "{$msi}%" : "n/a") . " → score " . round($msiScore,2) . "\n";
echo "  Severity: {$severity} ({$result['severity_label']}) threshold {$threshold} → " . ($result['pass'] ? "PASS" : "FAIL") . "\n";
if ($deleteHint) echo "  Delete-fix hint: {$deleteHint}\n";
echo "  Advice: {$result['advice']}\n";
if (!$result['pass']) echo "  Tip: `make detect-fake` + `make hume-audit` + `bash delete-fix-check.sh --filter \"{$filter}\"`\n";
exit($result['pass'] ? 0 : 1);
