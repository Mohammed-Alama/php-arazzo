<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * A hermetic harness for .github/scripts/package-index.sh (spec §A7).
 *
 * The script runs `gh` from PATH, so we prepend a stub that answers the
 * reads it makes — `issue view`, `issue list`, GraphQL for parent/epics,
 * and comment listing — with fixture JSON, and logs every call.
 *
 * @param  array{target?: string, parent?: string, epics?: string, list?: string, comments?: string}  $fixtures
 * @param  array<string, string>  $env
 * @return array{0: Process, 1: string} [process, gh call log]
 */
function runPackageIndex(array $fixtures = [], array $env = []): array
{
    $dir = sys_get_temp_dir().'/package-index-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    $log = $dir.'/calls.log';

    file_put_contents($dir.'/target.json', $fixtures['target'] ?? '{"data":{"repository":{"issue":null}}}');
    file_put_contents($dir.'/parent.json', $fixtures['parent'] ?? '{"data":{"repository":{"issue":{"parent":null}}}}');
    file_put_contents($dir.'/epics.json', $fixtures['epics'] ?? '{"data":{"repository":{"issue":{"subIssues":{"nodes":[]}}}}}');
    file_put_contents($dir.'/list.json', $fixtures['list'] ?? '[]');
    file_put_contents($dir.'/comments.json', $fixtures['comments'] ?? '[]');
    file_put_contents($log, '');

    file_put_contents($dir.'/gh', <<<'SH'
        #!/usr/bin/env bash
        printf '%s\n' "$*" >> "$GH_INDEX_LOG"

        if [ "$1 $2" = "issue view" ]; then
            cat "$GH_INDEX_TARGET"
            exit 0
        fi

        if [ "$1 $2" = "issue edit" ]; then
            exit 0
        fi

        if [ "$1 $2" = "issue list" ]; then
            cat "$GH_INDEX_LIST"
            exit 0
        fi

        if [ "$1" = "api" ]; then
            # gh api graphql -f query="..." -F owner=... -F repo=... -F number=...
            # Join all args to check which query is being run
            all_args="$*"
            case "$all_args" in
                *parent*) cat "$GH_INDEX_PARENT" ;;
                *)        cat "$GH_INDEX_TARGET" ;;
            esac
            exit 0
        fi

        if [ "$1" = "api" ] && [ "$2" != "graphql" ]; then
            # REST API calls like comments
            cat "$GH_INDEX_COMMENTS"
            exit 0
        fi

        exit 0
        SH);
    chmod($dir.'/gh', 0o755);

    $process = new Process(
        ['bash', '.github/scripts/package-index.sh'],
        env: $env + [
            'PATH' => $dir.':'.getenv('PATH'),
            'GH_INDEX_LOG' => $log,
            'GH_INDEX_TARGET' => $dir.'/target.json',
            'GH_INDEX_PARENT' => $dir.'/parent.json',
            'GH_INDEX_EPICS' => $dir.'/epics.json',
            'GH_INDEX_LIST' => $dir.'/list.json',
            'GH_INDEX_COMMENTS' => $dir.'/comments.json',
            'GITHUB_REPOSITORY' => 'o/r',
            'ISSUE_NUMBER' => '5',
            'EVENT_NAME' => 'issues',
        ],
    );
    $process->run();

    return [$process, is_file($log) ? (string) file_get_contents($log) : ''];
}

it('writes the body when the render differs', function () {
    [$process, $log] = runPackageIndex([
        'target' => '{"data":{"repository":{"issue":{"number":5,"body":"stale","labels":{"nodes":[{"name":"level:package"},{"name":"package:runner"}]},"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
        'epics' => '{"data":{"repository":{"issue":{"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
    ]);

    if ($process->getExitCode() !== 0) {
        fwrite(STDERR, "LOG:\n$log\n");
        fwrite(STDERR, "ERR:\n".$process->getErrorOutput()."\n");
        fwrite(STDERR, "OUT:\n".$process->getOutput()."\n");
    }
    expect($process->getExitCode())->toBe(0)
        ->and($log)->toContain('issue edit 5')
        ->and($log)->toContain('--body-file');
});

it('writes nothing when the render is unchanged', function () {
    // The exact body render-package-index produces for this one epic (Task 4).
    $body = "<!-- package-index: generated -->\n"
        ."**Every epic under `runner`.** 1 open.\n\n"
        ."| Epic | State | Stories |\n| --- | --- | --- |\n| [#87](87) CLI | open | 2 |\n"
        ."<!-- /package-index -->\n";

    [$process, $log] = runPackageIndex([
        'target' => '{"data":{"repository":{"issue":{"number":5,"body":'.json_encode($body).',"labels":{"nodes":[{"name":"level:package"},{"name":"package:runner"}]},"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
        'epics' => '{"data":{"repository":{"issue":{"subIssues":{"nodes":[{"number":87,"title":"CLI","state":"OPEN","subIssues":{"totalCount":2}}]}}}}}',
    ]);

    fwrite(STDERR, "LOG:\n$log\n");
    fwrite(STDERR, "ERR:\n".$process->getErrorOutput()."\n");
    fwrite(STDERR, "OUT:\n".$process->getOutput()."\n");

    expect($process->getExitCode())->toBe(0)
        ->and($log)->not->toContain('issue edit');
});

it('flags two open package-epics for one package, and never closes', function () {
    [$process, $log] = runPackageIndex(
        [
            'target' => '{"data":{"repository":{"issue":{"number":5,"body":"stale","labels":{"nodes":[{"name":"level:package"},{"name":"package:runner"}]},"subIssues":{"nodes":[]}}}}}',
            'list' => '[{"number":5,"labels":[{"name":"package:runner"}]},{"number":6,"labels":[{"name":"package:runner"}]}]',
        ],
        ['SWEEP' => '1', 'ISSUE_NUMBER' => ''],
    );

    expect($process->getExitCode())->toBe(0)
        ->and($log)->toContain('--add-label needs-info')
        ->and($log)->toContain('package-index')
        ->and($log)->not->toContain('issue close');
});
