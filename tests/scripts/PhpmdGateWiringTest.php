<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| The PHPMD gate
|--------------------------------------------------------------------------
| #78: the gate reported success while PDepend's parser gave up on PHP 8.4's
| parentheses-less instantiation, and --ignore-errors-on-exit turned "I could
| not read this file" into a green check. #244: two of the same shape survived
| the fix — a report target that wrote nothing behind `|| true`, and a baseline
| target that could empty the committed file on a parse error.
|
| Two halves hold all of it in place: the wiring, and a real run over the
| committed tree.
*/

/**
 * The packages the gate is meant to scan.
 *
 * A deliberate boundary rather than an accident: the 8 packages outside it
 * carry 102 violations between them, and widening the list without triaging
 * those would only grow the baseline sight-unseen.
 *
 * @return list<string>
 */
function phpmdScannedPackages(): array
{
    return [
        'packages/core/src',
        'packages/contracts/src',
        'packages/document/src',
        'packages/expression/src',
        'packages/runner/src',
        'packages/cli/src',
        'packages/laravel/src',
    ];
}

/**
 * The `Run PHPMD` step's executable lines, as argv and without a shell.
 *
 * Comment lines are dropped, so the flags the invocation does not pass cannot
 * be confused with the ones it merely mentions. An empty body means the step
 * or the job was renamed, and answers `[]` so that the assertions below fail
 * loudly instead of passing vacuously.
 *
 * @return list<string>
 */
function phpmdCiInvocation(string $repo): array
{
    $step = null;

    foreach (Yaml::parseFile($repo.'/.github/workflows/ci.yml')['jobs']['phpmd']['steps'] ?? [] as $candidate) {
        if (($candidate['name'] ?? null) === 'Run PHPMD') {
            $step = $candidate;
            break;
        }
    }

    $code = [];

    foreach (explode("\n", (string) ($step['run'] ?? '')) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $code[] = rtrim($line, "\\ \t");
    }

    $joined = trim(implode(' ', $code));

    if ($joined === '') {
        return [];
    }

    $tokens = preg_split('/\s+/', $joined) ?: [];

    if (!str_starts_with((string) $tokens[0], '/')) {
        $tokens[0] = $repo.'/'.$tokens[0];
    }

    return array_values($tokens);
}

/**
 * Every recipe line of a Makefile target, or an empty string when it is gone.
 */
function phpmdMakeTarget(string $repo, string $target): string
{
    $makefile = (string) file_get_contents($repo.'/Makefile');

    preg_match('/^'.preg_quote($target, '/').':.*\n((?:\t.*\n?)*)/m', $makefile, $matches);

    return $matches[1] ?? '';
}

/**
 * Run the gate over the committed tree the way the job means to run it.
 */
function runPhpmdGate(string $repo): Process
{
    $process = new Process(
        [
            $repo.'/vendor/bin/phpmd',
            'analyze',
            ...phpmdScannedPackages(),
            '--format',
            'github',
            '--ruleset',
            'phpmd.xml',
            '--baseline-file',
            'phpmd.baseline.xml',
        ],
        $repo,
    );
    $process->run();

    return $process;
}

it('leaves off the flags that turn a parse failure into a green check', function () {
    $repo = dirname(__DIR__, 2);
    $invocation = phpmdCiInvocation($repo);

    expect($invocation)->not->toBe([])
        ->and($invocation)->not->toContain('--ignore-errors-on-exit')
        ->and($invocation)->not->toContain('--ignore-violations-on-exit');
});

it('calls the analyze subcommand with separate paths, not the deprecated comma form', function () {
    $repo = dirname(__DIR__, 2);
    $invocation = phpmdCiInvocation($repo);
    $paths = array_values(array_filter($invocation, static fn (string $token): bool => str_starts_with($token, 'packages/')));

    expect($invocation[1] ?? null)->toBe('analyze')
        ->and(array_filter($invocation, static fn (string $token): bool => str_contains($token, ',')))->toBe([])
        ->and($paths)->toBe(phpmdScannedPackages());
});

it('scans from the Makefile the same packages it scans from CI', function () {
    $repo = dirname(__DIR__, 2);
    $target = phpmdMakeTarget($repo, 'phpmd');

    expect($target)->not->toBe('');

    foreach (['--ignore-errors-on-exit', '--ignore-violations-on-exit'] as $flag) {
        expect($target)->not->toContain($flag);
    }

    foreach (phpmdScannedPackages() as $path) {
        expect($target)->toContain($path);
    }
});

it('writes the report it promises, rather than failing behind a swallowed exit code', function () {
    $repo = dirname(__DIR__, 2);
    $report = $repo.'/storage/phpmd-report.xml';

    if (is_file($report)) {
        unlink($report);
    }

    $process = new Process(['make', 'phpmd-report'], $repo);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($report)->toBeFile()
        ->and((int) filesize($report))->toBeGreaterThan(0);

    if (is_file($report)) {
        unlink($report);
    }
});

it('never lets a failed regeneration write the committed baseline', function () {
    $repo = dirname(__DIR__, 2);
    $target = phpmdMakeTarget($repo, 'phpmd-baseline');

    expect($target)->not->toBe('')
        // Generating straight into the tracked file truncates it when PDepend
        // aborts, so the result has to arrive by rename, on success only.
        ->and($target)->not->toContain('--baseline-file phpmd.baseline.xml')
        ->and($target)->toContain('mv')
        ->and($target)->toContain('phpmd.baseline.xml');
});

it('passes over the committed tree, so the baseline covers every current violation', function () {
    $repo = dirname(__DIR__, 2);
    $process = runPhpmdGate($repo);
    $output = $process->getOutput().$process->getErrorOutput();

    expect($process->getExitCode())->toBe(0)
        ->and($output)->not->toContain('::error')
        ->and($output)->not->toContain('::warning');
});
