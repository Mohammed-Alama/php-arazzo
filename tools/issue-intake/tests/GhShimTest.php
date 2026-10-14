<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * A stub `gh`: logs every call to `$log`, answers `issue view` with one label
 * from `GATE_STUB_PARENT_LABEL`, and optionally captures stdin. Used as the
 * real binary via GATE_GH_BIN so nothing touches GitHub.
 *
 * @return array{0: string, 1: string} [binary, log]
 */
function shimStub(): array
{
    $dir = sys_get_temp_dir().'/gh-shim-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    $binary = $dir.'/gh';
    $log = $dir.'/calls.log';

    file_put_contents($binary, <<<'SH'
        #!/usr/bin/env bash
        printf '%s\n' "$*" >> "$GATE_GH_LOG"
        if [ "$1 $2" = "issue view" ]; then
            printf '{"labels":[{"name":"%s"}]}\n' "${GATE_STUB_PARENT_LABEL:-level:epic}"
            exit 0
        fi
        if [ -n "${GATE_STUB_CAPTURE:-}" ]; then
            cat > "$GATE_STUB_CAPTURE"
        fi
        exit 0
        SH);
    chmod($binary, 0o755);
    file_put_contents($log, '');

    return [$binary, $log];
}

/**
 * @param  list<string>  $args
 * @param  array<string, string>  $env
 */
function runShim(array $args, string $binary, string $log, array $env = [], string $input = ''): Process
{
    $process = new Process(
        ['scripts/bin/gh', ...$args],
        env: ['GATE_GH_BIN' => $binary, 'GATE_GH_LOG' => $log] + $env,
    );

    if ($input !== '') {
        $process->setInput($input);
    }

    $process->run();

    return $process;
}

it('passes --version through untouched', function () {
    [$gh, $log] = shimStub();

    $process = runShim(['--version'], $gh, $log);

    // The claim is that the shim hands the call to the real binary, so assert
    // on the binary's own call log — not on what a system `echo` makes of
    // `--version` (GNU echo intercepts the flag; BSD echo does not).
    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('--version');
});

it('blocks an incomplete issue create before calling real gh', function () {
    [, $log] = shimStub();
    $marker = tempnam(sys_get_temp_dir(), 'marker');

    $process = runShim(
        ['issue', 'create', '--title', 'x', '--body', 'nothing', '--label', 'bug', '--label', 'level:epic'],
        $marker, $log,
    );

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('Missing required section')
        ->and($process->getErrorOutput())->toContain('file-issue')
        ->and(file_get_contents($marker))->toBe('');
});

it('delegates a conforming issue create untouched', function () {
    [$gh, $log] = shimStub();

    $process = runShim([
        'issue', 'create', '--title', 'x', '--body', issueBody(arazzoSchema(), bugSectionIds()),
        '--label', 'bug', '--label', 'level:epic',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue create');
});

it('reads --body-file - once and re-supplies it downstream', function () {
    [$gh, $log] = shimStub();
    $capture = tempnam(sys_get_temp_dir(), 'capture');
    $body = issueBody(arazzoSchema(), bugSectionIds());

    $process = runShim([
        'issue', 'create', '--title', 'x', '--body-file', '-', '--label', 'bug', '--label', 'level:epic',
    ], $gh, $log, ['GATE_STUB_CAPTURE' => $capture], $body);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue create')
        ->and(file_get_contents($capture))->toBe($body);
});

it('does not guard a label-only edit (the triage escape hatch)', function () {
    [$gh, $log] = shimStub();

    $process = runShim(['issue', 'edit', '5', '--add-label', 'needs-info'], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue edit 5 --add-label needs-info');
});

it('merges existing labels when editing with a body', function () {
    [$gh, $log] = shimStub();   // `issue view` answers level:epic

    $process = runShim([
        'issue', 'edit', '5', '--body', issueBody(arazzoSchema(), bugSectionIds()), '--add-label', 'bug',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue view 5 --json labels')
        ->and(file_get_contents($log))->toContain('issue edit 5');
});

it('refuses a story whose parent has no level label', function () {
    [$gh, $log] = shimStub();

    $process = runShim([
        'issue', 'create', '--title', 'x', '--body', issueBody(arazzoSchema(), bugSectionIds()),
        '--label', 'bug', '--label', 'level:user-story', '--parent', '99',
    ], $gh, $log, ['GATE_STUB_PARENT_LABEL' => 'chore']);

    expect($process->getExitCode())->toBe(1)
        ->and(file_get_contents($log))->not->toContain('issue create')
        ->and($process->getErrorOutput())->toContain('has no level');
});

it('blocks an incomplete issue created through gh api', function () {
    [$gh, $log] = shimStub();

    $process = runShim([
        'api', '-X', 'POST', 'repos/o/r/issues', '-f', 'body=nothing',
        '-f', 'labels[]=bug', '-f', 'labels[]=level:epic',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(1)
        ->and(trim(file_get_contents($log)))->toBe('')
        ->and($process->getErrorOutput())->toContain('refusing to run gh');
});

it('does not guard gh api sub-resources such as comments', function () {
    [$gh, $log] = shimStub();

    $process = runShim([
        'api', '-X', 'POST', 'repos/o/r/issues/5/comments', '-f', 'body=hello',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issues/5/comments');
});

it('does not guard a read-only gh api request', function () {
    [$gh, $log] = shimStub();

    $process = runShim(['api', 'repos/o/r/issues/5'], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('repos/o/r/issues/5');
});

it('lets an ecosystem-labelled create through untouched (A4)', function () {
    [$gh, $log] = shimStub();

    $process = runShim(
        ['issue', 'create', '--title', 'x', '--body', 'nothing', '--label', 'ecosystem'],
        $gh, $log,
    );

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue create');
});

it('lets an ecosystem-labelled gh api write through untouched (A4)', function () {
    [$gh, $log] = shimStub();

    $process = runShim([
        'api', '-X', 'POST', 'repos/o/r/issues', '-f', 'body=nothing', '-f', 'labels[]=ecosystem',
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('repos/o/r/issues');
});

it('install-gh-shim symlinks gh and is idempotent', function () {
    $dir = sys_get_temp_dir().'/gh-shim-install-'.bin2hex(random_bytes(6));

    $install = function () use ($dir): Process {
        $process = new Process(['scripts/bin/install-gh-shim'], env: ['GH_SHIM_DIR' => $dir]);
        $process->run();

        return $process;
    };

    $first = $install();

    expect($first->getExitCode())->toBe(0)
        ->and(is_link("$dir/gh"))->toBeTrue()
        ->and(readlink("$dir/gh"))->toBe(realpath('scripts/bin/gh'));

    $second = $install();

    expect($second->getExitCode())->toBe(0)
        ->and($second->getOutput())->toContain('already installed')
        ->and(is_link("$dir/gh"))->toBeTrue();
});
