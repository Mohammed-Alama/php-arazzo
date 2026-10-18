<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * A stub `gh` answering `pr view` with a configurable closed-issue count.
 *
 * @return array{0: string, 1: string} [binary, log]
 */
function linkageStub(): array
{
    $dir = sys_get_temp_dir().'/pr-linkage-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    $binary = $dir.'/gh';
    $log = $dir.'/calls.log';

    $stub = <<<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$GATE_GH_LOG"
if [ "$1 $2" = "pr view" ]; then
    printf '%s\n' "${GATE_STUB_LINKED_COUNT:-0}"
    exit 0
fi
exit 0
SH;
    file_put_contents($binary, $stub);
    chmod($binary, 0o755);
    file_put_contents($log, '');

    return [$binary, $log];
}

/**
 * @param  array<string, string>  $env
 */
function runLinkageGate(string $binary, string $log, array $env = []): Process
{
    $process = new Process(
        ['bash', '.github/scripts/pr-linkage-gate.sh'],
        env: $env + [
            'GATE_GH_BIN' => $binary,
            'GATE_GH_LOG' => $log,
            'GITHUB_REPOSITORY' => 'o/r',
            'PR_NUMBER' => '42',
            'PR_AUTHOR' => 'someone',
            'PR_LABELS' => '',
        ],
    );
    $process->run();

    return $process;
}

it('fails when the PR closes no issue', function () {
    [$gh, $log] = linkageStub();

    $process = runLinkageGate($gh, $log, ['GATE_STUB_LINKED_COUNT' => '0']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('::error::')
        ->and($process->getOutput())->toContain('Closes #');
});

it('passes when the PR closes an issue', function () {
    [$gh, $log] = linkageStub();

    $process = runLinkageGate($gh, $log, ['GATE_STUB_LINKED_COUNT' => '1']);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('pr view 42');
});

it('exempts an ecosystem-labelled PR without calling gh', function () {
    [$gh, $log] = linkageStub();

    $process = runLinkageGate($gh, $log, ['PR_LABELS' => 'ecosystem,kind:chore']);

    expect($process->getExitCode())->toBe(0)
        ->and(trim(file_get_contents($log)))->toBe('');
});

it('exempts a bot-authored PR', function () {
    [$gh, $log] = linkageStub();

    $process = runLinkageGate($gh, $log, ['PR_AUTHOR' => 'dependabot[bot]']);

    expect($process->getExitCode())->toBe(0)
        ->and(trim(file_get_contents($log)))->toBe('');
});

it('exits 2 when the required environment is missing', function () {
    [$gh, $log] = linkageStub();

    $process = new Process(
        ['bash', '.github/scripts/pr-linkage-gate.sh'],
        env: ['GATE_GH_BIN' => $gh, 'GATE_GH_LOG' => $log],
    );
    $process->run();

    expect($process->getExitCode())->not->toBe(0);
});
