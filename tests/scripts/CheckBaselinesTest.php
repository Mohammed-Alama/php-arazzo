<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Run the checker against a fixture root and return the finished process.
 *
 * @param  list<string>  $args
 */
function runCheckBaselines(string $root, array $args = []): Process
{
    $repo = dirname(__DIR__, 2);

    $process = new Process(
        [PHP_BINARY, $repo.'/scripts/check-baselines.php', '--root='.$root, ...$args],
        $repo,
    );
    $process->run();

    return $process;
}

/**
 * Materialise a baseline fixture in a throw-away directory and answer it.
 *
 * @param  array<string, string>  $baselines  relative path => file contents
 */
function baselineFixture(array $baselines): string
{
    $root = sys_get_temp_dir().'/arazzo-baselines-'.bin2hex(random_bytes(6));

    foreach ($baselines as $relative => $contents) {
        $file = $root.'/'.$relative;
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);
    }

    return $root;
}

it('passes when every entry is legal — no identifier, or a missingType.* identifier', function () {
    $root = baselineFixture([
        'packages/document/phpstan-baseline.neon' => <<<'NEON'
        parameters:
            ignoreErrors:
                -
                    message: '#^Class Parser has too many methods \(37\)\. Maximum allowed is 20\.$#'
                    count: 1
                    path: src/Parser/Parser.php

                -
                    message: '#^Method Foo::bar\(\) return type has no value type specified in iterable type array\.$#'
                    identifier: missingType.iterableValue
                    count: 1
                    path: src/Foo.php
        NEON,
    ]);

    $process = runCheckBaselines($root);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->not->toContain('missingType');
});

it('fails and reports file:line  identifier for a baselined correctness error', function () {
    $root = baselineFixture([
        'packages/document/phpstan-baseline.neon' => <<<'NEON'
        parameters:
            ignoreErrors:
                -
                    message: '#^Call to an undefined method Parser::parseSelector\(\)\.$#'
                    identifier: method.notFound
                    count: 1
                    path: src/Parser/Parser.php
        NEON,
    ]);

    $process = runCheckBaselines($root);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('packages/document/phpstan-baseline.neon:5')
        ->and($process->getOutput())->toContain('method.notFound')
        ->and($process->getErrorOutput())->toContain('1 baselined correctness error');
});

it('emits machine-readable findings for the quality-gate integration', function () {
    $root = baselineFixture([
        'packages/runner/phpstan-baseline.neon' => <<<'NEON'
        parameters:
            ignoreErrors:
                -
                    message: '#^Call to an undefined method Foo::bar\(\)\.$#'
                    identifier: method.notFound
                    count: 1
                    path: src/Foo.php
        NEON,
    ]);

    $process = runCheckBaselines($root, ['--json']);

    $payload = json_decode($process->getOutput(), true);

    expect($process->getExitCode())->toBe(1)
        ->and($payload['count'])->toBe(1)
        ->and($payload['violations'][0]['file'])->toBe('packages/runner/phpstan-baseline.neon')
        ->and($payload['violations'][0]['line'])->toBe(5)
        ->and($payload['violations'][0]['identifier'])->toBe('method.notFound');
});

it('discovers baselines in nested directories and counts every finding', function () {
    $body = <<<'NEON'
    parameters:
        ignoreErrors:
            -
                message: '#^Call to an undefined method Foo::bar\(\)\.$#'
                identifier: method.notFound
                count: 1
                path: src/Foo.php
    NEON;

    $root = baselineFixture([
        'packages/document/phpstan-baseline.neon' => $body,
        'packages/laravel/phpstan-baseline.neon' => $body,
    ]);

    $payload = json_decode(runCheckBaselines($root, ['--json'])->getOutput(), true);

    expect($payload['count'])->toBe(2);
});

it('ignores baselines inside vendor, .worktrees and other build noise', function () {
    $dirty = <<<'NEON'
    parameters:
        ignoreErrors:
            -
                message: '#^Call to an undefined method Foo::bar\(\)\.$#'
                identifier: method.notFound
                count: 1
                path: src/Foo.php
    NEON;

    $root = baselineFixture([
        'vendor/thing/phpstan-baseline.neon' => $dirty,
        '.worktrees/other/phpstan-baseline.neon' => $dirty,
        'packages/document/phpstan-baseline.neon' => <<<'NEON'
        parameters:
            ignoreErrors:
                -
                    message: '#^Class Parser has too many methods \(37\)\. Maximum allowed is 20\.$#'
                    count: 1
                    path: src/Parser/Parser.php
        NEON,
    ]);

    $process = runCheckBaselines($root);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toBe('');
});

it('exits 2 when the root does not exist', function () {
    $process = runCheckBaselines('/definitely/not/here-'.bin2hex(random_bytes(4)));

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('root not found');
});
