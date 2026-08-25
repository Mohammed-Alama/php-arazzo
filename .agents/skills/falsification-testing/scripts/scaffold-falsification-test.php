<?php

declare(strict_types=1);

// Scaffold a falsification-driven Pest test for php-arazzo.
// Usage:
//   php scaffold-falsification-test.php <core|laravel> <TestName> "<falsifiable claim>" [--path <file>] [--dry-run]
// Examples:
//   php scaffold-falsification-test.php core StepExecutorRetry "returns Retry transition when successCriteria false"
//   php scaffold-falsification-test.php laravel ConcurrentLock "only one worker acquires Redis lock for same executionId"
//   php scaffold-falsification-test.php core ParserCycle --dry-run
//
// Writes a Pest file with Popper/Hume/Socrates/Descartes placeholders and
// package-correct imports (Mockery vs Testbench, strict_types, pint-friendly).

$fail = static function (string $msg): never {
    fwrite(STDERR, "error: {$msg}\n");
    exit(2);
};

$root = dirname(__DIR__, 4);
$argvSlice = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $argvSlice, true);
$customPath = null;
foreach ($argvSlice as $i => $a) {
    if ($a === '--path' && isset($argvSlice[$i + 1])) {
        $customPath = $argvSlice[$i + 1];
        break;
    }
}
$args = array_values(array_filter($argvSlice, static fn (string $a): bool => $a !== '--dry-run' && $a !== '--path' && !str_starts_with($a, '--')));
if (isset($customPath)) {
    // remove path value from args if present
    $args = array_values(array_filter($args, static fn (string $a): bool => $a !== $customPath));
}

if (count($args) < 2) {
    fwrite(STDERR, "usage: php scaffold-falsification-test.php <core|laravel> <TestName> \"<falsifiable claim>\" [--path <file>] [--dry-run]\n");
    exit(2);
}

$pkg = strtolower($args[0]);
if (!in_array($pkg, ['core', 'laravel'], true)) {
    $fail("first arg must be core or laravel");
}
$rawName = $args[1];
$claim = $args[2] ?? "system will <behavior> when <condition>; false if <observable>";

$words = preg_split('/[^A-Za-z0-9]+/', $rawName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$base = implode('', array_map(static fn (string $w): string => ucfirst(strtolower($w)), $words));
if ($base === '') {
    $fail("TestName '{$rawName}' normalises to empty");
}
if (!str_ends_with($base, 'Test')) {
    $base .= 'Test';
}
if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $base)) {
    $fail("TestName '{$base}' is not a valid class/file stem");
}

$claimSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $claim) ?? '');
$claimSlug = trim(substr($claimSlug, 0, 60), '-');

$relPath = $customPath;
if ($relPath === null) {
    $hint = match ($pkg) {
        'core' => 'Runner',
        'laravel' => 'Feature',
    };
    // Heuristic subdir: Runner for core engine, Feature for laravel
    $relPath = "packages/{$pkg}/tests/{$hint}/{$base}.php";
}
if (!str_starts_with($relPath, '/')) {
    $absPath = "{$root}/{$relPath}";
} else {
    $absPath = $relPath;
    $relPath = ltrim(str_replace($root . '/', '', $absPath), '/');
}

if (file_exists($absPath) && !$dryRun) {
    $fail("{$relPath} already exists");
}

$ns = $pkg === 'core' ? 'Alama\\Arazzo\\Tests' : 'Alama\\Arazzo\\Laravel\\Tests';

if ($pkg === 'core') {
    $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;

// Popper claim: {{CLAIM}}
// Falsifiable: the system will {{CLAIM}}. False if <observable> occurs.

it('{{CLAIM_SLUG}}', function (): void {
    // Arrange — independently-derived expected value, not a mirror of the implementation
    // TODO: picks minimal ArazzoDocument / Workflow / Step that isolates the claim
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(false);

    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    // TODO: fake PSR-18 response for this claim

    $executor = new StepExecutor($openApiExecutor, $resolver, Mockery::mock(OpenApiOperationResolver::class));
    $step = new Step('s1', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, null);
    $context = new WorkflowContext('wf-test', [], [], [], 'exec-1');
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);

    // Act
    [$nextContext, $success] = $executor->execute($step, $context, $document);

    // Assert — specific, falsifiable; would fail if fix were reverted
    // TODO: replace with exact expectation
    expect($success)->toBeFalse();
});

// Hume boundaries to add (at least one per class):
// - zero/empty/null: empty steps / null field
// - one: single step / single dep
// - max: maxSteps / maxWorkflowDepth / retryLimit vs retry_ceiling
// - exactly-equal: stepsSpent == maxSteps, retryCount == retryLimit
// - discontinuity: OpenAPI 3.0 vs 3.1, YAML {$...} vs literal

// Socrates adversarial (pick 3):
// - out-of-order / repeat step / double-submit
// - concurrent workers / race on same executionId
// - semantically hostile valid-shaped data (someone else's ID, negative quantity)
// - mid-operation dependency failure (PSR-18 timeout, SourceRegistry 404)

// Descartes assumptions to violate (one test per violated assumption):
// - inputs type/range/presence, dependencies shape/latency, clock/timezone, auth
PHP;
} else {
    $template = <<<'PHP'
<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

// Popper claim: {{CLAIM}}
// Falsifiable: the system will {{CLAIM}}. False if <observable> occurs.

it('{{CLAIM_SLUG}}', function (): void {
    // Arrange — Testbench + fakes (Queue/Cache/Event) where applicable
    Queue::fake();

    // Act
    // TODO: invoke the service/job/controller under test

    // Assert — specific, falsifiable; would fail if fix were reverted
    // TODO: assert on persisted side-effects (DatabaseExecutionRegistry / EventLedger / RedisHotStateStore), not just mock counts
    expect(true)->toBeTrue(); // replace
});

// Hume boundaries: empty / one / max / exactly-equal / discontinuity (see SKILL.md Pass 2)
// Socrates: double-submit, race, hostile IDs, mid-failure
// Descartes: violate each assumption (inputs, deps, env, auth)
// Remember: tests in Persistence/ get RefreshDatabase automatically (see packages/laravel/tests/Pest.php)
PHP;
}

$render = str_replace(['{{CLAIM}}', '{{CLAIM_SLUG}}'], [$claim, $claimSlug ?: strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $base) ?? 'falsifiable-claim')], $template);

echo "package: {$pkg}\n";
echo "file:    {$relPath}\n";
echo "claim:   {$claim}\n";
if ($dryRun) {
    echo "dry-run — no files written\n";
    echo "--- preview ---\n";
    echo $render . "\n";
    exit(0);
}

$dir = dirname($absPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents($absPath, $render);
echo "written\n";
echo "next: vendor/bin/pint {$relPath} && cd packages/{$pkg} && vendor/bin/pest --filter=" . escapeshellarg($claimSlug ?: $base) . "\n";
