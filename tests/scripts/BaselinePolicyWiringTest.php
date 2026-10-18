<?php

declare(strict_types=1);

/**
 * Fitness functions for the baseline policy guardrail (#202, epic #197).
 *
 * `scripts/check-baselines.php` only protects anything while a gate actually
 * runs it, and PHPStan only reports a stale suppression while
 * `reportUnmatchedIgnoredErrors` is on. Both are one-line edits away from
 * silently disappearing, so pin them here.
 */
$repo = dirname(__DIR__, 2);

it('runs the baseline checker from every gate that guards a merge', function () use ($repo): void {
    $entryPoints = [
        '.githooks/pre-commit' => 'composer run check-baselines',
        '.github/workflows/ci.yml' => 'composer run check-baselines',
        'Makefile' => 'composer run check-baselines',
        'scripts/quality-gates.php' => 'scripts/check-baselines.php',
    ];

    foreach ($entryPoints as $file => $needle) {
        $this->assertStringContainsString(
            $needle,
            (string) file_get_contents($repo.'/'.$file),
            "{$file} no longer runs the baseline policy checker",
        );
    }
});

it('exposes the checker to composer so every gate can reach it', function () use ($repo): void {
    $composer = json_decode((string) file_get_contents($repo.'/composer.json'), true);

    expect($composer['scripts']['check-baselines'] ?? null)
        ->toBe('@php scripts/check-baselines.php');
});

it('turns on reportUnmatchedIgnoredErrors for every phpstan config', function () use ($repo): void {
    $configs = glob($repo.'/packages/*/phpstan.neon.dist');

    expect($configs)->not->toBeEmpty();

    foreach ((array) $configs as $config) {
        $this->assertStringContainsString(
            'reportUnmatchedIgnoredErrors: true',
            (string) file_get_contents($config),
            basename(dirname($config)).' would silently keep a stale baseline entry',
        );
    }
});
