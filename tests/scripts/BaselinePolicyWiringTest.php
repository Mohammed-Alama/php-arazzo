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

it('checks the policy on both sides of every baseline regeneration (#203)', function () use ($repo): void {
    $makefile = (string) file_get_contents($repo.'/Makefile');

    preg_match('/(^analyse-baseline:.*?)(?=\n[a-zA-Z0-9_.-]+:)/ms', $makefile, $matches);
    $recipe = $matches[1] ?? '';

    expect($recipe)->not->toBe('');

    // Comments name the flag too (make writes them as `@#`), and they are not what runs —
    // compare shell lines only.
    $shell = implode("\n", array_filter(
        explode("\n", $recipe),
        static fn (string $line): bool => preg_match('/^\s*@?#/', $line) !== 1,
    ));

    $checker = 'scripts/check-baselines.php';
    $generate = strpos($shell, '--generate-baseline');

    expect($generate)->toBeInt()
        ->and(strpos($shell, $checker))->toBeInt()
        ->and(strrpos($shell, $checker))->toBeInt();

    // `--generate-baseline` freezes whatever it finds, so the checker has to run before
    // it (the tree must be clean to start with) and after it (nothing was re-admitted),
    // or a correctness error can be written into a baseline unnoticed.
    expect((int) strpos($shell, $checker))->toBeLessThan((int) $generate)
        ->and((int) strrpos($shell, $checker))->toBeGreaterThan((int) $generate);

    // Detecting a violation is not enough — the regenerated baseline has to go back and
    // the target has to fail, otherwise the bad baseline is what gets committed.
    expect($shell)->toContain('phpstan-baseline.neon')
        ->and($shell)->toContain('exit 1');

    // The packages to regenerate come from the baselines themselves, so a package added
    // to the split is picked up instead of sitting outside the guard behind a stale list.
    expect($shell)->toContain('packages/*/phpstan-baseline.neon');
});

it('keeps every committed baseline entry identified (#231)', function () use ($repo): void {
    // Provenance is the identifier. Every rule here stamps `arazzo.custom.*`, so
    // a fresh run never writes an identifier-free entry; one that appears
    // committed is either stale or hand-written. This fails if such an entry
    // lands, independently of the checker's own fixture tests.
    $baselines = glob($repo.'/packages/*/phpstan-baseline.neon') ?: [];

    expect($baselines)->not->toBeEmpty();

    foreach ($baselines as $baseline) {
        $source = (string) file_get_contents($baseline);
        $messages = substr_count($source, 'message:');
        $identifiers = substr_count($source, 'identifier:');

        expect($identifiers)->toBe(
            $messages,
            basename(dirname($baseline)).' has an identifier-free baseline entry, so its provenance cannot be checked',
        );
    }
});

it('stamps an arazzo.custom.* identifier on every custom rule error (#231)', function () use ($repo): void {
    // The gate bans identifier-free entries because the rules no longer produce
    // them. A new rule that forgets an identifier would make its findings
    // unsuppressible (fail-closed) and break that premise; pin it here.
    $rules = glob($repo.'/packages/core/phpstan/rules/*Rule.php') ?: [];

    expect($rules)->not->toBeEmpty();

    foreach ($rules as $rule) {
        $source = (string) file_get_contents($rule);
        $builders = substr_count($source, 'RuleErrorBuilder::message(');
        $identifiers = substr_count($source, "->identifier('arazzo.custom.");

        expect($identifiers)->toBe(
            $builders,
            basename($rule).' emits a finding without an arazzo.custom.* identifier',
        );
    }
});
