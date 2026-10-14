<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * A stub `gh`: logs every call to `$log` and answers `issue view` with a
 * single label from `STUB_PARENT_LABEL`. Never touches GitHub.
 *
 * @return array{0: string, 1: string} [binary, log]
 */
function stubGh(): array
{
    $dir = sys_get_temp_dir().'/file-issue-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    $binary = $dir.'/gh';
    $log = $dir.'/calls.log';

    file_put_contents($binary, <<<'SH'
        #!/usr/bin/env bash
        printf '%s\n' "$*" >> "$FILE_ISSUE_LOG"
        if [ "$1 $2" = "issue view" ]; then
            printf '{"labels":[{"name":"%s"}]}\n' "${STUB_PARENT_LABEL:-level:epic}"
            exit 0
        fi
        exit 0
        SH);
    chmod($binary, 0o755);
    file_put_contents($log, '');

    return [$binary, $log];
}

/** @param list<string> $ids */
function sectionsFile(array $ids): string
{
    $sections = [];

    foreach ($ids as $id) {
        $sections[$id] = "filled {$id}";
    }

    $path = tempnam(sys_get_temp_dir(), 'sections-');
    file_put_contents($path, (string) json_encode($sections));

    return $path;
}

/**
 * @param  list<string>  $args
 */
function runFileIssue(array $args, string $binary, string $log, string $parentLabel = 'level:epic'): Process
{
    $process = new Process(
        ['php', '.agents/skills/file-issue/scripts/file-issue.php', ...$args],
        env: [
            'FILE_ISSUE_GH_BIN' => $binary,
            'FILE_ISSUE_LOG' => $log,
            'STUB_PARENT_LABEL' => $parentLabel,
        ],
    );
    $process->run();

    return $process;
}

it('files a conforming issue and relays the kind and level labels to gh', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:epic', '--title=[Bug] x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue create')
        ->and(file_get_contents($log))->toContain('--label bug --label level:epic');
});

it('refuses an incomplete issue before gh is called', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:epic', '--title=x', '--sections='.sectionsFile(['core-metadata']),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(1)
        ->and(file_get_contents($log))->toBe('')
        ->and($process->getErrorOutput())->toContain('Missing required section');
});

it('files a story under an epic', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:user-story', '--parent=25', '--title=x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('--parent 25');
});

it('refuses a story under a parent that has no level label', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:user-story', '--parent=99', '--title=x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log, parentLabel: 'chore');

    expect($process->getExitCode())->toBe(1)
        ->and(file_get_contents($log))->not->toContain('issue create')
        ->and($process->getErrorOutput())->toContain('has no level');
});

it('refuses an epic that has a parent', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:epic', '--parent=25', '--title=x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(1)
        ->and(file_get_contents($log))->not->toContain('issue create')
        ->and($process->getErrorOutput())->toContain('parent-forbidden');
});

it('edits an existing issue instead of creating one with --edit', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=bug', '--level=level:epic', '--edit=42', '--title=x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(0)
        ->and(file_get_contents($log))->toContain('issue edit 42')
        ->and(file_get_contents($log))->toContain('--add-label bug --add-label level:epic');
});

it('exits 2 for an unknown kind', function () {
    [$gh, $log] = stubGh();

    $process = runFileIssue([
        '--kind=banana', '--level=level:epic', '--title=x', '--sections='.sectionsFile(bugSectionIds()),
    ], $gh, $log);

    expect($process->getExitCode())->toBe(2);
});
