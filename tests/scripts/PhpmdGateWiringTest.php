<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| The PHPMD gate
|--------------------------------------------------------------------------
| #78: the gate reported success while PDepend's parser gave up on PHP 8.4's
| parentheses-less instantiation, and the ignore-errors-on-exit flag turned "I
| could not read this file" into a green check. #244: two of the same shape
| survived the fix - a report target that wrote nothing behind a swallowed exit
| code, and a baseline target that could empty the committed file on a parse
| error. #242: the gate covered 7 of 15 packages, so 8 carried their violations
| where nobody read them.
|
| Two halves hold all of it in place: the wiring (one list, scanned the same way
| by CI, the Makefile and the scorecard) and a real run over the committed tree.
*/

/**
 * Every package that has a src/ directory, in the order CI scans them.
 *
 * All of them, with no boundary and no exclusion: a package split out of core
 * joins the gate when it is added here, and the test below fails until it is.
 * The three dirs that carry no PHP today (core is a placeholder) are still
 * listed, so the list stays "every package" rather than "every package we
 * remembered".
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
        'packages/evaluation/src',
        'packages/sources/src',
        'packages/runtime/src',
        'packages/engine/src',
        'packages/events/src',
        'packages/runner/src',
        'packages/cli/src',
        'packages/laravel/src',
        'packages/protocol-http/src',
        'packages/protocol-rpc/src',
        'packages/protocol-soap/src',
    ];
}

/**
 * The same list as the filesystem sees it: packages/<name>/src, sorted.
 *
 * @return list<string>
 */
function phpmdSourceDirectories(string $repo): array
{
    $paths = [];

    foreach (glob($repo.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $dir) {
        $paths[] = 'packages/'.basename(dirname($dir)).'/src';
    }

    sort($paths);

    return $paths;
}

/**
 * The value of a Makefile variable, split on whitespace.
 *
 * @return list<string>
 */
function phpmdMakeVariable(string $repo, string $name): array
{
    $makefile = (string) file_get_contents($repo.'/Makefile');

    if (preg_match('/^'.preg_quote($name, '/').' = (.+)$/m', $makefile, $matches) !== 1) {
        return [];
    }

    return array_values(array_filter(preg_split('/\s+/', trim($matches[1])) ?: []));
}

/**
 * The Run PHPMD step's executable lines, as argv and without a shell.
 *
 * Comment lines are dropped, so the flags the invocation does not pass cannot
 * be confused with the ones it merely mentions. An empty body means the step
 * or the job was renamed, and answers [] so that the assertions below fail
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

it('scans every package that has a src directory', function () {
    $repo = dirname(__DIR__, 2);
    $scanned = phpmdScannedPackages();
    sort($scanned);

    expect(phpmdSourceDirectories($repo))->not->toBe([])
        ->and($scanned)->toBe(phpmdSourceDirectories($repo));
});

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

    // One variable, four targets: the list exists once and every target reads it.
    expect(phpmdMakeVariable($repo, 'PHPMD_PATHS'))->toBe(phpmdScannedPackages());

    foreach (['phpmd', 'phpmd-report', 'phpmd-baseline'] as $target) {
        $recipe = phpmdMakeTarget($repo, $target);

        expect($recipe)->not->toBe('')
            ->and($recipe)->toContain('$(PHPMD_PATHS)');

        foreach (['--ignore-errors-on-exit', '--ignore-violations-on-exit'] as $flag) {
            expect($recipe)->not->toContain($flag);
        }
    }

    // pdepend takes the same set comma-joined, from the same variable.
    expect(phpmdMakeTarget($repo, 'pdepend'))->toContain('$(PHPMD_PATHS_COMMA)');
});

it('runs the same gate from the quality scorecard, not a stale second list', function () {
    $repo = dirname(__DIR__, 2);
    $script = (string) file_get_contents($repo.'/scripts/quality-gates.php');

    expect($script)->toContain("glob(__DIR__.'/../packages/*/src', GLOB_ONLYDIR)")
        ->and($script)->toContain("'analyze'")
        ->and($script)->not->toContain('--ignore-violations-on-exit')
        ->and($script)->not->toContain('--ignore-errors-on-exit');
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

it('carries no baseline entry for a violation that no longer exists', function () {
    $repo = dirname(__DIR__, 2);
    $committed = $repo.'/phpmd.baseline.xml';
    $copy = (string) tempnam(sys_get_temp_dir(), 'phpmd-baseline-');

    copy($committed, $copy);

    $process = new Process(
        [
            $repo.'/vendor/bin/phpmd',
            'analyze',
            ...phpmdScannedPackages(),
            '--ruleset',
            'phpmd.xml',
            '--update-baseline',
            '--baseline-file',
            $copy,
            '--threads',
            '1',
        ],
        $repo,
    );
    $process->run();

    $before = file($committed, FILE_IGNORE_NEW_LINES) ?: [];
    $after = file($copy, FILE_IGNORE_NEW_LINES) ?: [];

    if (is_file($copy)) {
        unlink($copy);
    }

    // PHPMD writes entries in a scan-dependent order, so this compares the set
    // rather than the file: pruning means an entry disappears, not that the
    // file moved.
    sort($before);
    sort($after);

    expect($process->getExitCode())->toBe(0)
        ->and(count($after))->toBeGreaterThan(1)
        ->and($after)->toBe($before);
});

it('passes over the committed tree, so the baseline covers every current violation', function () {
    $repo = dirname(__DIR__, 2);
    $process = runPhpmdGate($repo);
    $output = $process->getOutput().$process->getErrorOutput();

    expect($process->getExitCode())->toBe(0)
        ->and($output)->not->toContain('::error')
        ->and($output)->not->toContain('::warning');
});
