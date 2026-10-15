<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/** @param array<string, mixed> $payload */
function runRenderIndex(array $payload): Process
{
    $process = new Process(['php', 'tools/issue-intake/bin/render-package-index']);
    $process->setInput((string) json_encode($payload));
    $process->run();

    return $process;
}

it('renders a body and reports it as changed', function () {
    $process = runRenderIndex([
        'package' => 'runner',
        'body' => '',
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    $decoded = json_decode($process->getOutput(), true);

    expect($process->getExitCode())->toBe(0)
        ->and($decoded['changed'])->toBeTrue()
        ->and($decoded['body'])->toContain('#87');
});

it('reports no change when the body already holds the render', function () {
    $once = runRenderIndex([
        'package' => 'runner',
        'body' => '',
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    $second = runRenderIndex([
        'package' => 'runner',
        'body' => json_decode($once->getOutput(), true)['body'],
        'epics' => [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 2]],
    ]);

    expect(json_decode($second->getOutput(), true)['changed'])->toBeFalse();
});

it('exits 2 on a malformed payload', function () {
    $process = new Process(['php', 'tools/issue-intake/bin/render-package-index']);
    $process->setInput('not json');
    $process->run();

    expect($process->getExitCode())->toBe(2);
});
