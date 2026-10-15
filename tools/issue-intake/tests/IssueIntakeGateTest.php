<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * A hermetic harness for .github/scripts/issue-intake-gate.sh (spec A4).
 *
 * The gate runs `gh` from PATH, so we prepend a stub that answers the three
 * reads it makes — `issue view`, the parent GraphQL query, and the marker
 * comment listing — with fixture JSON, and logs every call. The validator it
 * shells out to is the real one, so this exercises the gate's own plumbing
 * (exemption, grandfathering, marker idempotency) rather than a mock of it.
 *
 * @param  array{issue?: string, parent?: string, comments?: string}  $fixtures
 * @param  array<string, string>  $env
 * @return array{0: Process, 1: string} [process, gh call log]
 */
function runIntakeGate(array $fixtures = [], array $env = []): array
{
    $dir = sys_get_temp_dir().'/intake-gate-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    $log = $dir.'/calls.log';

    file_put_contents($dir.'/issue.json', $fixtures['issue'] ?? '{"body":"nothing","labels":[]}');
    file_put_contents($dir.'/parent.json', $fixtures['parent'] ?? '{"data":{"repository":{"issue":{"parent":null}}}}');
    file_put_contents($dir.'/comments.json', $fixtures['comments'] ?? '[]');
    file_put_contents($log, '');

    file_put_contents($dir.'/gh', <<<'SH'
        #!/usr/bin/env bash
        printf '%s\n' "$*" >> "$GH_GATE_LOG"

        if [ "$1 $2" = "issue view" ]; then
            cat "$GH_GATE_ISSUE"
            exit 0
        fi

        if [ "$1 $2" = "issue edit" ]; then
            exit 0
        fi

        if [ "$1" = "api" ]; then
            case "$2" in
                graphql) cat "$GH_GATE_PARENT" ;;
                -X)      : ;;
                *)       cat "$GH_GATE_COMMENTS" ;;
            esac
            exit 0
        fi

        exit 0
        SH);
    chmod($dir.'/gh', 0o755);

    $process = new Process(
        ['bash', '.github/scripts/issue-intake-gate.sh'],
        env: $env + [
            'PATH' => $dir.':'.getenv('PATH'),
            'GH_GATE_LOG' => $log,
            'GH_GATE_ISSUE' => $dir.'/issue.json',
            'GH_GATE_PARENT' => $dir.'/parent.json',
            'GH_GATE_COMMENTS' => $dir.'/comments.json',
            'GITHUB_REPOSITORY' => 'o/r',
            'ISSUE_NUMBER' => '5',
            'EVENT_NAME' => 'issues',
            'EVENT_ACTION' => 'opened',
        ],
    );
    $process->run();

    return [$process, is_file($log) ? (string) file_get_contents($log) : ''];
}

it('exempts an ecosystem-labelled issue', function () {
    [$process, $log] = runIntakeGate([
        'issue' => '{"body":"whatever","labels":[{"name":"ecosystem"}]}',
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('exempt')
        ->and($log)->not->toContain('issue edit');
});

it('skips a legacy edit that carries no level label', function () {
    [$process, $log] = runIntakeGate(
        ['issue' => '{"body":"legacy","labels":[{"name":"bug"}]}'],
        ['EVENT_ACTION' => 'edited'],
    );

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('legacy')
        ->and($log)->not->toContain('--add-label');
});

it('flags a non-conforming issue needs-info with one marker comment', function () {
    [$process, $log] = runIntakeGate([
        'issue' => '{"body":"nothing","labels":[]}',
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('needs-info')
        ->and($log)->toContain('--add-label needs-info')
        ->and($log)->toContain('api -X POST')
        ->and($log)->toContain('comments');
});

it('clears needs-info on a conforming issue we previously commented on', function () {
    $body = issueBody(arazzoSchema(), bugSectionIds());

    [$process, $log] = runIntakeGate([
        'issue' => (string) json_encode([
            'body' => $body,
            'labels' => [['name' => 'bug'], ['name' => 'level:package'], ['name' => 'package:core'], ['name' => 'needs-info']],
        ]),
        'comments' => (string) json_encode([['id' => 99, 'body' => '<!-- intake-gate -->']]),
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('cleared needs-info')
        ->and($log)->toContain('--remove-label needs-info');
});

it('removes its stale marker comment once the issue conforms', function () {
    $body = issueBody(arazzoSchema(), bugSectionIds());

    [$process, $log] = runIntakeGate([
        'issue' => (string) json_encode([
            'body' => $body,
            'labels' => [['name' => 'bug'], ['name' => 'level:package'], ['name' => 'package:core'], ['name' => 'needs-info']],
        ]),
        'comments' => (string) json_encode([['id' => 99, 'body' => '<!-- intake-gate -->']]),
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($log)->toContain('--remove-label needs-info')
        ->and($log)->toContain('api -X DELETE')
        ->and($log)->toContain('comments/99');
});

it('removes a stale marker comment even when the label was cleared elsewhere', function () {
    $body = issueBody(arazzoSchema(), bugSectionIds());

    [$process, $log] = runIntakeGate([
        'issue' => (string) json_encode([
            'body' => $body,
            'labels' => [['name' => 'bug'], ['name' => 'level:package'], ['name' => 'package:core']],
        ]),
        'comments' => (string) json_encode([['id' => 99, 'body' => '<!-- intake-gate -->']]),
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($log)->not->toContain('--remove-label')
        ->and($log)->toContain('api -X DELETE')
        ->and($log)->toContain('comments/99');
});
