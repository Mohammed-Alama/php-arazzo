#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * CI regression gate for the Arazzo conformance score.
 *
 * Compares conformance/score.json (latest run) against conformance/baseline.json
 * (accepted result) and exits non-zero when a previously-right case turns wrong,
 * i.e. when the passed count for either tier drops below the baseline.
 *
 * Usage:
 *   php scripts/conformance-gate.php [--baseline conformance/baseline.json] [--score conformance/score.json]
 *
 * Exit codes:
 *   0  score is at or above the baseline on every tier
 *   1  regression detected (a previously-right case turned wrong)
 *   2  usage / missing file
 */

require __DIR__.'/../vendor/autoload.php';

$baselinePath = __DIR__.'/../conformance/baseline.json';
$scorePath = __DIR__.'/../conformance/score.json';

$argv = array_slice($_SERVER['argv'], 1);
for ($i = 0; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if ($arg === '--baseline' && isset($argv[$i + 1])) {
        $baselinePath = $argv[++$i];
    } elseif (str_starts_with($arg, '--baseline=')) {
        $baselinePath = substr($arg, strlen('--baseline='));
    } elseif ($arg === '--score' && isset($argv[$i + 1])) {
        $scorePath = $argv[++$i];
    } elseif (str_starts_with($arg, '--score=')) {
        $scorePath = substr($arg, strlen('--score='));
    } elseif ($arg === '-h' || $arg === '--help') {
        echo <<<'USAGE'
Usage: php scripts/conformance-gate.php [--baseline <file>] [--score <file>]

  --baseline  Path to conformance/baseline.json (default: conformance/baseline.json)
  --score     Path to conformance/score.json (default: conformance/score.json)

Exits non-zero when the scored passed count drops below the baseline on either
the validation or execution tier, i.e. when a previously-right case turns wrong.
USAGE;
        exit(0);
    }
}

if (!is_file($baselinePath)) {
    fwrite(STDERR, "conformance-gate: baseline not found: {$baselinePath}\n");
    exit(2);
}

if (!is_file($scorePath)) {
    fwrite(STDERR, "conformance-gate: score not found: {$scorePath}\n");
    fwrite(STDERR, "       Generate it with: php scripts/conformance-score.php\n");
    exit(2);
}

$baseline = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
$score = json_decode((string) file_get_contents($scorePath), true, 512, JSON_THROW_ON_ERROR);

$failures = [];

foreach (['validation', 'execution'] as $tier) {
    $b = ($baseline[$tier] ?? []);
    $s = ($score[$tier] ?? []);
    $basePassed = (int) ($b['passed'] ?? 0);
    $baseTotal = (int) ($b['total'] ?? 0);
    $scorePassed = (int) ($s['passed'] ?? 0);
    $scoreTotal = (int) ($s['total'] ?? 0);

    if ($baseTotal !== 0 && $scoreTotal !== 0 && $baseTotal !== $scoreTotal) {
        $failures[] = "{$tier}: case count changed from {$baseTotal} to {$scoreTotal}";
    }

    if ($scorePassed < $basePassed) {
        $failures[] = "{$tier}: passed dropped from {$basePassed}/{$baseTotal} to {$scorePassed}/{$scoreTotal}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "CONFORMANCE REGRESSION DETECTED:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  - {$failure}\n");
    }
    fwrite(STDERR, "\n");
    exit(1);
}

echo "conformance-gate: score is at or above the baseline on every tier.\n";
echo '  validation: '.($score['validation']['passed'] ?? 0).'/'.($score['validation']['total'] ?? 0)."\n";
echo '  execution: '.($score['execution']['passed'] ?? 0).'/'.($score['execution']['total'] ?? 0)."\n";

exit(0);
