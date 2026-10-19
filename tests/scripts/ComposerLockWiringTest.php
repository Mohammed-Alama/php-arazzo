<?php

declare(strict_types=1);

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

    expect($composer['scripts']['check-lock'] ?? null)
        ->toBe('composer validate --no-check-publish --check-lock');
});

it('runs the lock check from the hook that guards a commit', function () use ($repo): void {
    $this->assertStringContainsString(
        'composer run check-lock',
        (string) file_get_contents($repo.'/.githooks/pre-commit'),
        '.githooks/pre-commit no longer runs the lock check',
    );
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

it('accepts the committed lock and rejects one the manifest no longer matches', function () use ($repo): void {
    $command = 'composer validate --no-check-publish --check-lock';

    $sandbox = sys_get_temp_dir().'/arazzo-lock-check-'.bin2hex(random_bytes(4));
    mkdir($sandbox);
    copy($repo.'/composer.json', $sandbox.'/composer.json');
    copy($repo.'/composer.lock', $sandbox.'/composer.lock');

    try {
        $inSync = lockCheckRun($sandbox, $command);
        $this->assertSame(
            0,
            $inSync['exit'],
            "the committed lock does not match composer.json — run `composer update`:\n".$inSync['output'],
        );

        // A requirement the lock cannot satisfy. This is the shape a forgotten
        // `composer update` leaves behind, and composer escalates it to a lock
        // file error even though nothing is unresolvable in principle.
        $manifest = json_decode((string) file_get_contents($sandbox.'/composer.json'), true);
        $manifest['require']['symfony/yaml'] = '*';
        file_put_contents(
            $sandbox.'/composer.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $stale = lockCheckRun($sandbox, $command);

        expect($stale['exit'])->not->toBe(0)
            ->and($stale['output'])->toContain('Lock file errors')
            ->and($stale['output'])->toContain('composer update');
    } finally {
        @unlink($sandbox.'/composer.json');
        @unlink($sandbox.'/composer.lock');
        @rmdir($sandbox);
    }
});

/**
 * Run the check in a directory of its own, so the sandbox's manifest is what
 * composer reads and the working tree is never touched.
 *
 * @return array{exit: int, output: string}
 */
function lockCheckRun(string $directory, string $command): array
{
    $output = [];
    $exit = 0;

    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($directory), $command), $output, $exit);

    return ['exit' => $exit, 'output' => implode("\n", $output)];
}
