#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Drift guard for the conformance capability manifest.
 *
 * Rebuilds the manifest model from the vendored corpus (the same model
 * `php scripts/generate-conformance-matrix.php` writes) and compares it to
 * the committed `docs/conformance/manifest.json`.
 *
 * Exit 0 when the committed manifest is in sync with the corpus; exit 1 and
 * print a unified diff when it has drifted, so CI can fail the build.
 *
 * Usage: php scripts/check-conformance-manifest.php
 */

require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../packages/core/tests/Conformance/ConformanceManifestBuilder.php';
require_once __DIR__.'/../packages/core/tests/Conformance/OaiCorpusRunner.php';

use Alama\Arazzo\Tests\Conformance\ConformanceManifestBuilder;
use Alama\Arazzo\Tests\Conformance\OaiCorpusRunner;

$root = __DIR__.'/..';
$manifestPath = $root.'/docs/conformance/manifest.json';

$documents = OaiCorpusRunner::documents();
$model = ConformanceManifestBuilder::build($documents);
$rebuilt = ConformanceManifestBuilder::serialize($model);

if (!is_file($manifestPath)) {
    echo "DRIFT: docs/conformance/manifest.json does not exist.\n";
    echo "       Run: php scripts/generate-conformance-matrix.php\n";
    exit(1);
}

$committed = (string) file_get_contents($manifestPath);

if ($committed === $rebuilt) {
    echo "docs/conformance/manifest.json is in sync with the corpus.\n";
    exit(0);
}

echo "DRIFT: docs/conformance/manifest.json is out of sync with the corpus.\n";
echo "       Regenerate with: php scripts/generate-conformance-matrix.php\n";
echo "\n";

$committedTmp = tempnam(sys_get_temp_dir(), 'conformance-manifest-committed-');
$rebuiltTmp = tempnam(sys_get_temp_dir(), 'conformance-manifest-rebuilt-');
file_put_contents($committedTmp, $committed);
file_put_contents($rebuiltTmp, $rebuilt);

$diff = shell_exec(sprintf(
    'diff -u --label committed --label rebuilt %s %s 2>&1',
    escapeshellarg($committedTmp),
    escapeshellarg($rebuiltTmp),
));

if ($diff !== false && $diff !== '') {
    echo $diff;
} else {
    echo "(diff unavailable; committed and rebuilt manifests differ)\n";
}

unlink($committedTmp);
unlink($rebuiltTmp);

exit(1);
