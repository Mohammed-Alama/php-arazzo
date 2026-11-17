#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Scores php-arazzo against the api-evangelist/arazzo-conformance suite.
 *
 * Clones the suite at a pinned SHA (api-evangelist/arazzo-conformance,
 * SHA 2a90e6b), runs every validation and execution case against
 * `vendor/bin/arazzi` (mock petstore on 127.0.0.1:4010), and writes
 * conformance/score.json with per-case verdicts, versions and suite SHA.
 *
 * Requires: Python 3 + pyyaml (the suite's run.py / mock-petstore.py use
 * python3; pyyaml is needed to parse the suite's manifest.yml files).
 *
 * Usage:
 *   php scripts/conformance-score.php \
 *       --output conformance/score.json \
 *       --binary vendor/bin/arazzi \
 *       --sha 2a90e6b
 *
 * Options:
 *   --suite-dir  Use an already-cloned suite directory instead of cloning
 *   --output     Path to write score.json (required)
 *   --binary     Path to the arazzo binary (required)
 *   --sha        Pinned suite SHA (required, recorded in score.json)
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/ConformanceScorer.php';

use Alama\Arazzo\Scripts\ConformanceScorer;
use Symfony\Component\Process\Process;

$suiteDir = null;
$output = null;
$binary = null;
$sha = null;

$argv = array_slice($_SERVER['argv'], 1);
for ($i = 0; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if ($arg === '--suite-dir' && isset($argv[$i + 1])) {
        $suiteDir = $argv[++$i];
    } elseif (str_starts_with($arg, '--suite-dir=')) {
        $suiteDir = substr($arg, strlen('--suite-dir='));
    } elseif ($arg === '--output' && isset($argv[$i + 1])) {
        $output = $argv[++$i];
    } elseif (str_starts_with($arg, '--output=')) {
        $output = substr($arg, strlen('--output='));
    } elseif ($arg === '--binary' && isset($argv[$i + 1])) {
        $binary = $argv[++$i];
    } elseif (str_starts_with($arg, '--binary=')) {
        $binary = substr($arg, strlen('--binary='));
    } elseif ($arg === '--sha' && isset($argv[$i + 1])) {
        $sha = $argv[++$i];
    } elseif (str_starts_with($arg, '--sha=')) {
        $sha = substr($arg, strlen('--sha='));
    } elseif ($arg === '-h' || $arg === '--help') {
        echo <<<'USAGE'
Usage: php scripts/conformance-score.php --output <path> --binary <path> --sha <sha> [--suite-dir <dir>]

  --suite-dir  Already-cloned suite directory (defaults to cloning at pinned SHA)
  --output     Path to write score.json (required)
  --binary     Path to the arazzo binary (required)
  --sha        Pinned suite SHA (required, recorded in score.json)
USAGE;
        exit(0);
    }
}

if ($output === null || $binary === null || $sha === null) {
    fwrite(STDERR, "conformance-score: --output, --binary and --sha are required\n");
    exit(2);
}

$root = __DIR__.'/..';

// Determine the suite directory: use the given one, or clone the suite and
// check out the pinned SHA (a SHA is not a branch, so --branch cannot be used
// with a shallow clone; a full clone is required).
if ($suiteDir === null) {
    $suiteDir = sys_get_temp_dir().'/arazzo-conformance-suite-'.bin2hex(random_bytes(4));
    $clone = new Process(['git', 'clone', 'https://github.com/api-evangelist/arazzo-conformance.git', $suiteDir]);
    $clone->setTimeout(300);
    $clone->run();
    if ($clone->getExitCode() !== 0) {
        fwrite(STDERR, "conformance-score: failed to clone api-evangelist/arazzo-conformance\n");
        fwrite(STDERR, $clone->getErrorOutput());
        exit(1);
    }
    $checkout = new Process(['git', '-C', $suiteDir, 'checkout', $sha]);
    $checkout->setTimeout(120);
    $checkout->run();
    if ($checkout->getExitCode() !== 0) {
        fwrite(STDERR, "conformance-score: failed to check out suite SHA {$sha}\n");
        fwrite(STDERR, $checkout->getErrorOutput());
        exit(1);
    }
}

if (!is_dir($suiteDir)) {
    fwrite(STDERR, "conformance-score: suite directory not found: {$suiteDir}\n");
    exit(1);
}

$scorer = new ConformanceScorer($suiteDir, $binary, $output, $sha);
$scorer->score();

echo "Wrote {$output}\n";
