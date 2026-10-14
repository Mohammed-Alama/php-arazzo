<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/** @param array<string, mixed>|string $input */
function runIntakeValidator(array|string $input): Process
{
    $process = new Process(['php', 'tools/issue-intake/bin/validate-issue']);
    $process->setInput(is_string($input) ? $input : (string) json_encode($input));
    $process->run();

    return $process;
}

it('exits 1 and prints findings for a malformed body', function () {
    $process = runIntakeValidator([
        'body' => 'nothing here',
        'labels' => ['bug', 'level:epic'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    $stdout = json_decode($process->getOutput(), true);

    expect($process->getExitCode())->toBe(1)
        ->and($stdout['conforming'])->toBeFalse()
        ->and($stdout['findings'])->not->toBeEmpty()
        ->and($process->getErrorOutput())->toContain('Missing required section');
});

it('exits 0 and reports conforming for a complete body', function () {
    $schema = arazzoSchema();

    $process = runIntakeValidator([
        'body' => issueBody($schema, bugSectionIds()),
        'labels' => ['bug', 'level:epic'],
        'parent' => null,
        'parentLevel' => null,
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and(json_decode($process->getOutput(), true))
        ->toBe([
            'conforming' => true,
            'findings' => [],
            'kind' => 'bug',
            'level' => 'level:epic',
        ]);
});

it('exits 2 on invalid JSON', function () {
    expect(runIntakeValidator('not json')->getExitCode())->toBe(2);
});

it('exits 2 when stdin is empty', function () {
    expect(runIntakeValidator('')->getExitCode())->toBe(2);
});
