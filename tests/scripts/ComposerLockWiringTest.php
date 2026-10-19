<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Fitness functions for the committed composer.lock (#236, epic #27).
 *
 * #234 switched five CI jobs to `composer install` from the tracked root lock,
 * which turns a lock that no longer matches a manifest into a CI failure. The
 * pre-commit hook has to see it first — otherwise a contributor edits a
 * manifest, watches five green checks run against the already-installed
 * `vendor/`, pushes, and reads about it in a runner log.
 *
 * The detection itself is composer's own validator. What is pinned here is
 * that it is reachable as a command, that the hook invokes it, that the hook
 * fails the commit rather than warning, and that the committed lock really is
 * in sync — the last one because the hook can be bypassed with --no-verify,
 * while the test suite cannot.
 */
$repo = dirname(__DIR__, 2);

it('exposes the lock check to composer so the hook and a human can both run it', function () use ($repo): void {
    $composer = json_decode((string) file_get_contents($repo.'/composer.json'), true);

    // Both shapes of drift: the root manifest's content-hash, and a package
    // manifest the lock's snapshot no longer describes (#238).
    expect($composer['scripts']['check-lock'] ?? null)
        ->toBe([
            'composer validate --no-check-publish --check-lock',
            '@check-lock-manifests',
        ])
        ->and($composer['scripts']['check-lock-manifests'] ?? null)
        ->toBe('@php scripts/check-lock-manifests.php');
});

it('runs the lock check from the hook that guards a commit', function () use ($repo): void {
    $this->assertStringContainsString(
        'composer run check-lock',
        (string) file_get_contents($repo.'/.githooks/pre-commit'),
        '.githooks/pre-commit no longer runs the lock check',
    );
});

it('checks the package manifests on the CI job that rewrites nothing', function () use ($repo): void {
    // The hook sees a working tree, which is the right thing to guard a commit
    // with. A merge is guarded by CI, and the test matrix rewrites a package
    // manifest to change Laravel version, so the check has to run on a job that
    // installs from the lock and edits nothing (#238).
    $workflow = (string) file_get_contents($repo.'/.github/workflows/ci.yml');

    $this->assertStringContainsString(
        'composer run check-lock-manifests',
        $workflow,
        'ci.yml no longer checks the lock against the package manifests',
    );

    expect(strpos($workflow, 'composer run check-lock-manifests'))
        ->toBeLessThan((int) strpos($workflow, "\n  test:"));
});

it('fails the commit on a stale lock, naming the fix', function () use ($repo): void {
    $hook = (string) file_get_contents($repo.'/.githooks/pre-commit');

    $start = strpos($hook, 'if ! composer run check-lock; then');
    expect($start)->toBeInt();

    $rest = substr($hook, (int) $start);
    $end = strpos($rest, "\nfi");
    expect($end)->toBeInt();

    $block = substr($rest, 0, (int) $end);

    // A gate, not a warning: it exits the hook, the way the Pint and PHPStan
    // checks do, rather than carrying on to the conformance step. And it names
    // the fix, because "the lock is stale" is not actionable on its own.
    expect($block)->toContain('exit 1')
        ->toContain('composer update');
});

it('keeps the generating branch name out of the lock', function () use ($repo): void {
    $lock = json_decode((string) file_get_contents($repo.'/composer.lock'), true);
    $branchDerived = [];

    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        $name = (string) $package['name'];
        $version = (string) $package['version'];

        if (!str_starts_with($name, 'alama/')) {
            continue;
        }

        // `dev-main` is a fixed literal, pinned in the root manifest's path
        // repositories. Anything else beginning with `dev-` is the branch the
        // lock happened to be generated on: no other branch can satisfy it, so
        // `composer update --lock` refuses there.
        if ($version !== 'dev-main' && str_starts_with($version, 'dev-')) {
            $branchDerived[] = $name.' => '.$version;
        }
    }

    expect($branchDerived)->toBe([]);
});

it('pins a version for every path repository whose package does not declare one', function () use ($repo): void {
    $composer = json_decode((string) file_get_contents($repo.'/composer.json'), true);
    $unpinned = [];
    $pinnedTwice = [];

    foreach ($composer['repositories'] as $repository) {
        if (($repository['type'] ?? null) !== 'path') {
            continue;
        }

        $manifest = json_decode(
            (string) file_get_contents($repo.'/'.$repository['url'].'/composer.json'),
            true,
        );
        $name = (string) $manifest['name'];
        $pin = $repository['options']['versions'][$name] ?? null;
        $declaresVersion = isset($manifest['version']);

        // Both at once is worse than either: composer prefers the pin, so a
        // released package would still resolve here as `dev-main`. The release
        // flow is what adds a version, so it has to drop the pin with it.
        if ($declaresVersion && $pin !== null) {
            $pinnedTwice[] = $name;

            continue;
        }

        // A declared version stays the same on every branch; without one the
        // version comes from git, and the lock records whichever branch was
        // checked out when it was last written.
        if (!$declaresVersion && $pin === null) {
            $unpinned[] = $name;
        }
    }

    expect(['unpinned' => $unpinned, 'pinned twice' => $pinnedTwice])
        ->toBe(['unpinned' => [], 'pinned twice' => []]);
});

it('accepts the committed lock and rejects one the manifest no longer matches', function () use ($repo): void {
    $command = ['composer', 'validate', '--no-check-publish', '--check-lock'];

    $sandbox = sys_get_temp_dir().'/arazzo-lock-check-'.bin2hex(random_bytes(4));

    try {
        mkdir($sandbox);
        copy($repo.'/composer.json', $sandbox.'/composer.json');
        copy($repo.'/composer.lock', $sandbox.'/composer.lock');

        $inSync = lockCheckRun($sandbox, $command);
        $this->assertSame(
            0,
            $inSync->getExitCode(),
            "the committed lock does not match composer.json — run `composer update`:\n".lockCheckOutput($inSync),
        );

        // Change the root manifest without touching the lock. The constraint
        // itself is beside the point — `symfony/yaml` is already in require-dev
        // and in the lock, so nothing here is unresolvable. What fails is
        // composer's content-hash check, which it reports as a lock file error:
        // the same shape a forgotten `composer update` leaves behind.
        $manifest = json_decode((string) file_get_contents($sandbox.'/composer.json'), true);
        $manifest['require']['symfony/yaml'] = '*';
        file_put_contents(
            $sandbox.'/composer.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $stale = lockCheckRun($sandbox, $command);

        expect($stale->getExitCode())->not->toBe(0)
            ->and(lockCheckOutput($stale))->toContain('Lock file errors')
            ->and(lockCheckOutput($stale))->toContain('composer update');
    } finally {
        foreach (['composer.json', 'composer.lock'] as $file) {
            if (is_file($sandbox.'/'.$file)) {
                unlink($sandbox.'/'.$file);
            }
        }

        if (is_dir($sandbox)) {
            rmdir($sandbox);
        }
    }
});

/**
 * Run the check in a directory of its own, so the sandbox's manifest is what
 * composer reads and the working tree is never touched.
 *
 * @param  list<string>  $command
 */
function lockCheckRun(string $directory, array $command): Process
{
    $process = new Process($command, $directory);
    $process->run();

    return $process;
}

/**
 * Composer writes its diagnosis to whichever of the two streams it prefers.
 */
function lockCheckOutput(Process $process): string
{
    return $process->getOutput().$process->getErrorOutput();
}
