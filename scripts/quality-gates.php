<?php

/**
 * Runs this repo's architecture quality gates and records a machine-readable
 * snapshot to storage/quality-gates.json, which scripts/generate-docs.php
 * renders into docs/generated/quality-gates.md.
 *
 * Heavy gates are NOT part of pre-commit. Refresh deliberately:
 *
 *     make quality-gates                    # pint + phpstan + pest
 *     make quality-gates ARGS=--with-mutations   # + pest --mutate (slow)
 *
 * Only metrics are rendered into the doc (no timestamps/durations) so the
 * generated markdown stays deterministic for a given measurement.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$outFile = $root.'/storage/quality-gates.json';

$withMutations = in_array('--with-mutations', $argv, true);

/** @return array{status: string, metrics: array<string, int|float|string>, notes: string} */
function runGate(string $name, string $command, string $cwd, callable $parse): array
{
    echo "▶ {$name}\n";
    $start = microtime(true);
    $output = [];
    $code = 0;
    exec('cd '.escapeshellarg($cwd)." && {$command} 2>&1", $output, $code);
    $durationMs = (int) ((microtime(true) - $start) * 1000);
    $text = implode("\n", $output);

    /** @var array{status: string, metrics: array<string, int|float|string>, notes: string} $result */
    $result = $parse($code, $text);
    $result['metrics']['exit_code'] = $code;
    $result['metrics']['duration_ms'] = $durationMs;

    echo sprintf(
        "  %s (%dms)\n",
        strtoupper($result['status']),
        $durationMs,
    );

    return $result;
}

/** @return array{status: string, metrics: array<string, int|float|string>, notes: string} */
function parsePhpStan(int $code, string $text): array
{
    preg_match('/Found (\d+) errors?(?:, ignored (\d+))?/', $text, $m);
    $errors = isset($m[1]) ? (int) $m[1] : ($code === 0 ? 0 : -1);
    $ignored = isset($m[2]) ? (int) $m[2] : null;

    $metrics = ['errors' => $errors];
    if ($ignored !== null) {
        $metrics['baseline_ignored'] = $ignored;
    }

    return [
        'status' => $code === 0 ? 'pass' : 'fail',
        'metrics' => $metrics,
        'notes' => '',
    ];
}

/** @return array{status: string, metrics: array<string, int|float|string>, notes: string} */
function parsePest(int $code, string $text): array
{
    preg_match_all('/(\d+)\s+(passed|failed|skipped|errors?)/', $text, $m, PREG_SET_ORDER);
    $metrics = [];
    foreach ($m as $entry) {
        $key = rtrim($entry[2], 's');
        if (!isset($metrics[$key])) {
            $metrics[$key] = 0;
        }
        $metrics[$key] += (int) $entry[1];
    }
    preg_match_all('/(\d+)\s+assertions?/', $text, $a);
    if (isset($a[1][0])) {
        $metrics['assertions'] = (int) $a[1][count($a[1]) - 1];
    }

    return [
        'status' => $code === 0 ? 'pass' : 'fail',
        'metrics' => $metrics === [] ? ['summary' => 'unparsed'] : $metrics,
        'notes' => '',
    ];
}

/** @return array{status: string, metrics: array<string, int|float|string>, notes: string} */
function parsePint(int $code, string $text): array
{
    preg_match_all('/(\d+)\s+(?:file|style)/', $text, $m);
    $total = isset($m[1][0]) ? (int) $m[1][0] : 0;

    return [
        'status' => $code === 0 ? 'pass' : 'fail',
        'metrics' => ['files_reported' => $total],
        'notes' => $code === 0 ? '' : trim(implode(' | ', array_slice(explode("\n", trim($text)), -3))),
    ];
}

/** @return array{status: string, metrics: array<string, int|float|string>, notes: string} */
function parseMutations(int $code, string $text): array
{
    preg_match('/MSI:\s*([\d.]+)/', $text, $msi);
    preg_match_all('/(\d+)\s+(?:killed|escaped|timed out|not covered)/i', $text, $m, PREG_SET_ORDER);
    $metrics = [];
    foreach ($m as $entry) {
        $key = strtolower(trim($entry[2]));
        $metrics[str_replace(' ', '_', $key)] = (int) $entry[1];
    }

    return [
        'status' => $code === 0 ? 'pass' : 'fail',
        'metrics' => $metrics + ['msi_percent' => isset($msi[1]) ? (float) $msi[1] : -1.0],
        'notes' => '',
    ];
}

$coreDir = $root.'/packages/core';
$laravelDir = $root.'/packages/laravel';

$gateDefs = [
    ['pint', 'Code Style (Pint)', 'vendor/bin/pint --test', $root, 'parsePint'],
    ['phpstan-core', 'Static Analysis · core', 'vendor/bin/phpstan analyse --memory-limit=1G --no-progress', $coreDir, 'parsePhpStan'],
    ['phpstan-laravel', 'Static Analysis · laravel', 'vendor/bin/phpstan analyse --memory-limit=1G --no-progress', $laravelDir, 'parsePhpStan'],
    ['pest-core', 'Tests · core', 'vendor/bin/pest --no-coverage', $coreDir, 'parsePest'],
    ['pest-laravel', 'Tests · laravel', 'vendor/bin/pest --no-coverage', $laravelDir, 'parsePest'],
];

if ($withMutations) {
    $mutateCmd = 'php -d memory_limit=-1 vendor/bin/pest --mutate --everything --covered-only --no-coverage';
    $gateDefs[] = ['mutations-core', 'Mutation Testing · core', $mutateCmd, $coreDir, 'parseMutations'];
    $gateDefs[] = ['mutations-laravel', 'Mutation Testing · laravel', $mutateCmd, $laravelDir, 'parseMutations'];
}

$gates = [];
foreach ($gateDefs as [$id, $label, $command, $cwd, $parser]) {
    $result = runGate($label, $command, $cwd, $parser);
    $gates[] = [
        'id' => $id,
        'label' => $label,
        'command' => $command,
        'status' => $result['status'],
        'metrics' => $result['metrics'],
        'notes' => $result['notes'],
    ];
}

$snapshot = [
    '_comment' => 'Written by scripts/quality-gates.php. Rendered into docs/generated/quality-gates.md.',
    'gates' => $gates,
];

if (!is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0777, true);
}
file_put_contents(
    $outFile,
    json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
);

// trend history: one JSON line per run; docs/generated/gate-trend.md plots it
$historyEntry = [
    'date' => date('Y-m-d'),
    'measured_at' => date('c'),
    'gates' => [],
];
foreach ($gates as $gate) {
    $record = ['status' => $gate['status']];
    foreach (['errors', 'passed', 'failed', 'skipped', 'msi_percent', 'files_reported'] as $key) {
        if (isset($gate['metrics'][$key])) {
            $record[$key] = $gate['metrics'][$key];
        }
    }
    $historyEntry['gates'][$gate['id']] = $record;
}
$historyFile = $root.'/storage/quality-history.jsonl';
file_put_contents($historyFile, json_encode($historyEntry)."\n", FILE_APPEND | LOCK_EX);

$failing = array_filter($gates, fn (array $g): bool => $g['status'] === 'fail');
echo "\nSnapshot: {$outFile}\n";
echo "History:  {$historyFile}\n";
echo count($gates) - count($failing).'/'.count($gates)." gates passing\n";
exit($failing === [] ? 0 : 1);
