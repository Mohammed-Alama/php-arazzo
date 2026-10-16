<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function repoRoot(): string
{
    return dirname(__DIR__, 3);
}

function headSha(): string
{
    $process = new Process(['git', '-C', repoRoot(), 'rev-parse', 'HEAD']);
    $process->run();

    return trim($process->getOutput());
}

function runCodePermalink(string ...$arguments): Process
{
    $process = new Process(['php', 'tools/issue-intake/bin/code-permalink', ...$arguments], repoRoot());
    $process->run();

    return $process;
}

it('prints a permalink pinned to the resolved commit', function () {
    $process = runCodePermalink('config/issues/schema.yaml:1', '--repo=Mohammed-Alama/php-arazzo');

    expect($process->getExitCode())->toBe(0)
        ->and(trim($process->getOutput()))->toBe(
            'https://github.com/Mohammed-Alama/php-arazzo/blob/'.headSha().'/config/issues/schema.yaml#L1',
        );
});

it('folds the snippet into a <details> block with --fold', function () {
    $process = runCodePermalink('config/issues/schema.yaml:1-2', '--repo=Mohammed-Alama/php-arazzo', '--fold');
    $output = $process->getOutput();

    expect($process->getExitCode())->toBe(0)
        ->and($output)->toContain(
            '<details><summary><a href="https://github.com/Mohammed-Alama/php-arazzo/blob/'.headSha().'/config/issues/schema.yaml#L1-L2">',
        )
        ->and($output)->toContain('```yaml')
        ->and($output)->toContain('</details>');
});

it('exits 2 for a malformed location', function () {
    expect(runCodePermalink('config/issues/schema.yaml', '--repo=Mohammed-Alama/php-arazzo')->getExitCode())->toBe(2);
});

it('exits 2 for a path that is not committed at the ref', function () {
    $process = runCodePermalink('does/not/exist.php:1', '--repo=Mohammed-Alama/php-arazzo');

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('does not exist');
});
