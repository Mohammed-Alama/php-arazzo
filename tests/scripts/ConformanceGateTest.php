<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @param  array<string>  $args
 */
function runConformanceGate(array $args = []): Process
{
    $repo = dirname(__DIR__, 2);

    $process = new Process(
        array_merge([PHP_BINARY, $repo.'/scripts/conformance-gate.php'], $args),
        $repo,
    );
    $process->run();

    return $process;
}

/**
 * Write a baseline and a score file and return their paths.
 *
 * @param  array{validation?: array{total: int, passed: int}, execution?: array{total: int, passed: int}}  $baseline
 * @param  array{validation?: array{total: int, passed: int}, execution?: array{total: int, passed: int}}  $score
 * @return array{baseline: string, score: string}
 */
function conformanceGateFixtures(array $baseline = [], array $score = []): array
{
    $root = sys_get_temp_dir().'/arazzo-gate-'.bin2hex(random_bytes(4));
    @mkdir($root, 0777, true);

    $baselineDoc = array_merge([
        'suite' => 'api-evangelist/arazzo-conformance',
        'sha' => '6e93591',
        'validation' => ['total' => 127, 'passed' => 91],
        'execution' => ['total' => 14, 'passed' => 0],
    ], $baseline);
    $scoreDoc = array_merge([
        'suite' => 'api-evangelist/arazzo-conformance',
        'sha' => '6e93591',
        'validation' => ['total' => 127, 'passed' => 91],
        'execution' => ['total' => 14, 'passed' => 0],
    ], $score);

    $baselinePath = $root.'/baseline.json';
    $scorePath = $root.'/score.json';
    file_put_contents($baselinePath, json_encode($baselineDoc));
    file_put_contents($scorePath, json_encode($scoreDoc));

    return ['baseline' => $baselinePath, 'score' => $scorePath];
}

it('passes when the score is at or above the baseline on every tier', function () {
    $fixtures = conformanceGateFixtures(
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 0]],
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 0]],
    );

    $process = runConformanceGate([
        '--baseline='.$fixtures['baseline'],
        '--score='.$fixtures['score'],
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('at or above the baseline');
});

it('passes when the score improves on the baseline', function () {
    $fixtures = conformanceGateFixtures(
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 0]],
        ['validation' => ['total' => 127, 'passed' => 95], 'execution' => ['total' => 14, 'passed' => 2]],
    );

    $process = runConformanceGate([
        '--baseline='.$fixtures['baseline'],
        '--score='.$fixtures['score'],
    ]);

    expect($process->getExitCode())->toBe(0);
});

it('fails when a previously-right validation case turns wrong', function () {
    $fixtures = conformanceGateFixtures(
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 0]],
        ['validation' => ['total' => 127, 'passed' => 90], 'execution' => ['total' => 14, 'passed' => 0]],
    );

    $process = runConformanceGate([
        '--baseline='.$fixtures['baseline'],
        '--score='.$fixtures['score'],
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('validation');
});

it('fails when a previously-right execution case turns wrong', function () {
    // execution has no previously-passed case in the real baseline (0/14), so
    // use a baseline with execution passed > 0 to model a regression.
    $fixtures = conformanceGateFixtures(
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 3]],
        ['validation' => ['total' => 127, 'passed' => 91], 'execution' => ['total' => 14, 'passed' => 2]],
    );

    $process = runConformanceGate([
        '--baseline='.$fixtures['baseline'],
        '--score='.$fixtures['score'],
    ]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('execution');
});

it('exits 2 when the score file is missing', function () {
    $process = runConformanceGate([
        '--baseline='.sys_get_temp_dir().'/arazzo-baseline-missing.json',
        '--score='.sys_get_temp_dir().'/arazzo-score-missing.json',
    ]);

    expect($process->getExitCode())->toBe(2);
});
