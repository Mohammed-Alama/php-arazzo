#!/usr/bin/env php
<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Schema;
use Alama\Tools\IssueIntake\Validator;

/**
 * The paved path to a conforming issue (spec A6, decision D12).
 *
 * Renders a body from per-section content, validates it with the one engine,
 * and only then delegates to `gh`. An issue that would not conform never
 * reaches GitHub: we exit 1 with exactly the findings the shim and CI would
 * print, before any network call that mutates state.
 *
 * usage: file-issue.php --kind=<kind> --level=<level> --title=<title> \
 *            --sections=<file|-> [--parent=<n>] [--edit=<n>] [--repo=<owner/name>]
 *
 * `--sections` is a JSON object mapping a section id to markdown content
 * (`-` reads stdin). Section ids come from config/issues/schema.yaml; unknown
 * ids are ignored, and the validator names any required section that is still
 * missing. `FILE_ISSUE_GH_BIN` overrides the `gh` binary (the test seam).
 */
$autoload = dirname(__DIR__, 4).'/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "file-issue: cannot locate vendor/autoload.php\n");
    exit(2);
}

require $autoload;

/** @return never */
function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, "file-issue: {$message}\n");
    exit($code);
}

function usage(): void
{
    fwrite(STDOUT, <<<'TXT'
        usage: file-issue.php --kind=<kind> --level=<level> --title=<title> --sections=<file|-> [--parent=<n>] [--edit=<n>] [--repo=<owner/name>]

          --kind=<kind>        one of: bug, enhancement, chore
          --level=<label>      one of: level:epic, level:story, level:subtask
          --title=<title>      the issue title
          --sections=<file|->  JSON object mapping section id -> markdown content ("-" reads stdin)
          --parent=<n>         file as a sub-issue of <n> (checked against <n>'s level:* label)
          --edit=<n>           edit issue <n> instead of creating a new one
          --repo=<owner/name>  target repository (defaults to the current one)

        The body is validated before `gh` is called; nothing is filed when it
        does not conform.
        TXT."\n");
}

/**
 * Run a command, capturing its output. Stdin is /dev/null so `gh` can never
 * block on a prompt.
 *
 * @param  list<string>  $command
 * @return array{0: int, 1: string, 2: string}
 */
function run(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        return [127, '', 'could not start '.implode(' ', $command)];
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

/**
 * Read a parent issue's level from its `level:*` label, if it has one.
 *
 * `gh issue view --json` cannot report a parent, but the caller already knows
 * the parent's number — only its level label is needed to check the invariant.
 */
function resolveParentLevel(string $gh, int $parent, ?string $repo): ?string
{
    $command = [$gh, 'issue', 'view', (string) $parent, '--json', 'labels'];

    if ($repo !== null) {
        $command[] = '--repo';
        $command[] = $repo;
    }

    [$code, $stdout, $stderr] = run($command);

    if ($code !== 0) {
        fail("could not read parent #{$parent}: ".trim($stderr !== '' ? $stderr : $stdout));
    }

    $decoded = json_decode($stdout, true);
    $labels = is_array($decoded) && is_array($decoded['labels'] ?? null) ? $decoded['labels'] : [];

    foreach ($labels as $label) {
        $name = is_array($label) ? ($label['name'] ?? null) : null;

        if (is_string($name) && str_starts_with($name, 'level:')) {
            return $name;
        }
    }

    return null;
}

$options = [];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    if (preg_match('/^--([a-z][a-z-]*)=(.*)$/s', $argument, $matches) !== 1) {
        usage();
        fail("unexpected argument \"{$argument}\"");
    }

    $options[$matches[1]] = $matches[2];
}

foreach (['kind', 'level', 'title', 'sections'] as $required) {
    if (($options[$required] ?? '') === '') {
        usage();
        fail("missing required --{$required}");
    }
}

$schemaPath = getenv('ISSUE_INTAKE_SCHEMA') ?: dirname(__DIR__, 4).'/config/issues/schema.yaml';

try {
    $schema = Schema::fromFile($schemaPath);
} catch (Throwable $error) {
    fail('cannot load schema: '.$error->getMessage());
}

$kind = $options['kind'];
$level = $options['level'];

if (!in_array($kind, $schema->kinds(), true)) {
    fail("unknown kind \"{$kind}\"; expected one of: ".implode(', ', $schema->kinds()));
}

$levels = array_keys($schema->levels());

if (!in_array($level, $levels, true)) {
    fail("unknown level \"{$level}\"; expected one of: ".implode(', ', $levels));
}

$parent = null;

if (isset($options['parent'])) {
    if (ctype_digit($options['parent']) === false) {
        fail('--parent must be an issue number');
    }

    $parent = (int) $options['parent'];
}

$edit = null;

if (isset($options['edit'])) {
    if (ctype_digit($options['edit']) === false) {
        fail('--edit must be an issue number');
    }

    $edit = (int) $options['edit'];
}

$repo = ($options['repo'] ?? '') !== '' ? $options['repo'] : null;

$raw = $options['sections'] === '-'
    ? (stream_get_contents(STDIN) ?: '')
    : (is_file($options['sections']) ? (file_get_contents($options['sections']) ?: '') : null);

if ($raw === null) {
    fail("sections file not found: {$options['sections']}");
}

$decoded = json_decode($raw, true);

if (!is_array($decoded)) {
    fail('--sections must be a JSON object mapping section id -> content');
}

$content = [];

foreach ($decoded as $id => $value) {
    if (is_array($value)) {
        $value = implode("\n", array_filter($value, 'is_string'));
    }

    if (!is_string($value)) {
        fail("section \"{$id}\" must be a string (or a list of strings)");
    }

    $content[(string) $id] = $value;
}

// Render the sections the kind cares about, in schema (visual) order.
$wanted = [...$schema->requiredIdsFor($kind), ...$schema->optionalFor($kind)];
$blocks = [];

foreach ($schema->sectionIds() as $id) {
    if (!in_array($id, $wanted, true) || trim($content[$id] ?? '') === '') {
        continue;
    }

    $blocks[] = '### '.$schema->section($id)['heading']."\n\n".trim($content[$id]);
}

$body = implode("\n\n", $blocks);

$gh = getenv('FILE_ISSUE_GH_BIN') ?: 'gh';
$parentLevel = $parent !== null ? resolveParentLevel($gh, $parent, $repo) : null;

$findings = (new Validator($schema))->validate([
    'body' => $body,
    'labels' => [$kind, $level],
    'parent' => $parent,
    'parentLevel' => $parentLevel,
]);

if ($findings !== []) {
    foreach ($findings as $finding) {
        fwrite(STDERR, "[{$finding->code}] {$finding->message}\n");
    }

    fail('refusing to reach gh; nothing was filed. Fix the findings above.', 1);
}

$bodyFile = tempnam(sys_get_temp_dir(), 'file-issue-');

if ($bodyFile === false) {
    fail('cannot create a temporary file for the body');
}

file_put_contents($bodyFile, $body);

$command = $edit === null
    ? [$gh, 'issue', 'create', '--title', $options['title'], '--body-file', $bodyFile, '--label', $kind, '--label', $level]
    : [$gh, 'issue', 'edit', (string) $edit, '--body-file', $bodyFile, '--add-label', $kind, '--add-label', $level];

if ($parent !== null) {
    $command[] = '--parent';
    $command[] = (string) $parent;
}

if ($repo !== null) {
    $command[] = '--repo';
    $command[] = $repo;
}

$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
$exit = is_resource($process) ? proc_close($process) : 127;

@unlink($bodyFile);

exit($exit);
