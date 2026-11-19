<?php

declare(strict_types=1);

// Scaffolds a conformance fixture and records it in tests/fixtures/README.md.
//
// Usage:
//   php new-fixture.php <valid|invalid|edge-cases> <slug-name> [--json] [--desc "..."] [--reason "..."]
//
// Examples:
//   php new-fixture.php valid correlation-id-propagation --desc "Correlation id flows across steps"
//   php new-fixture.php invalid duplicate-step-outputs --reason "Two steps emit the same output name"
//
// The invalid template ships intentionally broken ($ref to a missing component) so the
// invalid_fixtures dataset stays red until you swap in your own breakage.

$fail = static function (string $msg): never {
    fwrite(STDERR, "error: {$msg}\n");
    exit(2);
};

$root = dirname(__DIR__, 4);
$args = [];
$desc = null;
$reason = null;
$json = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
        $json = true;
    } elseif (str_starts_with($arg, '--desc=')) {
        $desc = substr($arg, 7);
    } elseif (str_starts_with($arg, '--reason=')) {
        $reason = substr($arg, 9);
    } else {
        $args[] = $arg;
    }
}

if (count($args) !== 2) {
    fwrite(STDERR, "usage: php new-fixture.php <valid|invalid|edge-cases> <slug-name> [--json] [--desc \"...\"] [--reason \"...\"]\n");
    exit(2);
}

[$kind, $rawName] = $args;

if (!in_array($kind, ['valid', 'invalid', 'edge-cases'], true)) {
    $fail("kind must be one of: valid, invalid, edge-cases");
}

$slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($rawName))) ?? '');
$slug = trim($slug, '-');
if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    $fail("name '{$rawName}' must normalise to a lowercase hyphenated slug");
}

$title = ucwords(str_replace('-', ' ', $slug));
$ext = $json ? 'arazzo.json' : 'arazzo.yaml';
$rel = "{$kind}/{$slug}.{$ext}";
$path = "{$root}/packages/core/tests/fixtures/{$rel}";
$readmePath = "{$root}/packages/core/tests/fixtures/README.md";

if (file_exists($path)) {
    $fail(basename($path) . ' already exists');
}
if ($kind === 'invalid' && ($reason === null || trim($reason) === '')) {
    $fail('invalid fixtures need a documented failure reason: --reason "..."');
}

if ($json) {
    $doc = [
        'arazzo' => '1.0.0',
        'info' => ['title' => $title, 'version' => '1.0.0'],
        'sourceDescriptions' => [
            ['name' => 'api', 'url' => 'http://localhost:8002/docs/api.json', 'type' => 'openapi'],
        ],
        'workflows' => [
            [
                'workflowId' => "{$slug}-workflow",
                'steps' => [
                    ['stepId' => 'step-one', 'operationId' => 'api.getProducts'],
                ],
            ],
        ],
    ];
    if ($kind === 'invalid') {
        // Mirror invalid/unresolvable-ref.arazzo.yaml: a $ref that cannot resolve.
        $doc['workflows'][0]['parameters'] = [['$ref' => '#/components/parameters/DoesNotExist']];
    }
    $body = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    $header = '';
    if ($kind === 'invalid') {
        $header = "# EXPECTED FAILURE: {$reason}\n";
    }

    $wf = "workflows:\n";
    $wf .= "  - workflowId: {$slug}-workflow\n";
    if ($kind === 'invalid') {
        // Mirror invalid/unresolvable-ref.arazzo.yaml: a $ref that cannot resolve.
        $wf .= "    parameters:\n";
        $wf .= "      - \$ref: '#/components/parameters/DoesNotExist'\n";
    }
    $wf .= "    steps:\n";
    $wf .= "      - stepId: step-one\n";
    $wf .= "        operationId: api.getProducts\n";

    $body = $header . <<<YAML
arazzo: "1.0.0"
info:
  title: {$title}
  version: "1.0.0"
sourceDescriptions:
  - name: api
    url: http://localhost:8002/docs/api.json
    type: openapi

YAML . "\n" . $wf;
}

$readme = file_get_contents($readmePath);
if ($readme === false) {
    $fail("cannot read README.md");
}

$section = match ($kind) {
    'valid' => '### Valid',
    'invalid' => '### Invalid',
    'edge-cases' => '### Edge Cases',
};
$desc ??= $title;
$bullet = "*   `{$rel}` - {$desc}.";
$lines = explode("\n", $readme);
$headerIdx = array_search($section, $lines, true);
if ($headerIdx === false) {
    $fail("README section '{$section}' not found");
}
$insertAt = $headerIdx + 1;
if (($lines[$insertAt] ?? '') === '') {
    $insertAt++;
}
array_splice($lines, $insertAt, 0, [$bullet]);
$newReadme = implode("\n", $lines);

echo "fixture:  packages/core/tests/fixtures/{$rel}\n";
echo "readme:   bullet added under '{$section}'\n";
if ($kind === 'edge-cases') {
    echo "note:     edge-cases are not in any dataset — wire a dedicated test (see EdgeCaseFixturesTest)\n";
}

file_put_contents($path, $body);
file_put_contents($readmePath, $newReadme);

echo "\nnext:\n";
echo "  cd packages/core && vendor/bin/pest tests/Feature/ConformanceTest.php\n";
