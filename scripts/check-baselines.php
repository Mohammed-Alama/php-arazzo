#!/usr/bin/env php
<?php

/**
 * Baseline policy gate — a correctness error may never be hidden in a PHPStan
 * baseline (issue #201, epic #197).
 *
 * A `phpstan-baseline.neon` entry is legal iff:
 *
 *   - it carries no `identifier:` at all — this repo's own PHPStan rules emit
 *     none, so those findings are rule-authoring signal, not correctness bugs;
 *   - or its identifier matches `missingType.*` — an untyped collection is a
 *     documentation gap, not a crash waiting to happen.
 *
 * Everything else (`method.notFound`, `function.alreadyNarrowedType`, …) is a
 * real defect that must be fixed in the code, never frozen in a baseline.
 *
 * Usage
 *   php scripts/check-baselines.php [--root=DIR] [--json]
 *
 * Exit codes
 *   0  clean
 *   1  at least one disallowed entry
 *   2  usage / internal error
 *
 * Wired into `.githooks/pre-commit`, the PHPStan CI job, `make verify` and
 * `scripts/quality-gates.php` (#202). The quality gate passes `--json` so the
 * snapshot can record the violation count; every other caller uses the default
 * human-readable output.
 */

declare(strict_types=1);

/**
 * One `ignoreErrors` entry as read from a baseline.
 */
final class BaselineEntry
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $identifier,
        public readonly string $message,
        public readonly string $path,
    ) {}
}

/**
 * Reads every baseline under a root and reports the entries that breach the
 * policy above.
 */
final class BaselinePolicy
{
    /** Directory names never worth descending into when hunting for baselines. */
    private const EXCLUDED = [
        '.git',
        '.cache',
        '.scratch',
        '.worktrees',
        'build',
        'coverage-report',
        'node_modules',
        'storage',
        'vendor',
    ];

    public function __construct(private readonly string $root) {}

    /**
     * Every `phpstan-baseline.neon` below the root, repo-relative, sorted.
     *
     * @return list<string>
     */
    public function baselines(): array
    {
        $directory = new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS);

        $filtered = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file): bool {
                if ($file->isDir()) {
                    return !in_array($file->getFilename(), self::EXCLUDED, true);
                }

                return true;
            },
        );

        $found = [];

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            if ($file instanceof SplFileInfo && $file->getFilename() === 'phpstan-baseline.neon') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Every entry across every baseline that breaches the policy.
     *
     * @return list<BaselineEntry>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->baselines() as $baseline) {
            foreach ($this->entries($baseline) as $entry) {
                if (!$this->allows($entry->identifier)) {
                    $violations[] = $entry;
                }
            }
        }

        return $violations;
    }

    /**
     * A baseline entry is legal only when it carries no identifier or a
     * `missingType.*` one.
     */
    private function allows(string $identifier): bool
    {
        return $identifier === '' || preg_match('/^missingType(\.|$)/', $identifier) === 1;
    }

    /**
     * Minimal NEON reader for the shape PHPStan emits: a list of `-` blocks,
     * each with `message` / optional `identifier` / `count` / `path` keys. We
     * only need the identifier, so a line scan beats pulling in a NEON parser
     * (and keeps the reported line number accurate).
     *
     * @return list<BaselineEntry>
     */
    private function entries(string $file): array
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $entries = [];
        $current = null;

        foreach ($lines as $index => $line) {
            if ($this->startsEntry($line)) {
                if ($current instanceof BaselineEntry) {
                    $entries[] = $current;
                }

                $current = new BaselineEntry($file, $index + 1, '', '', '');
            }

            if (!$current instanceof BaselineEntry) {
                continue;
            }

            if (preg_match('/^\s*identifier:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new BaselineEntry($file, $index + 1, $this->unquote($matches[1]), $current->message, $current->path);
            } elseif (preg_match('/^\s*message:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new BaselineEntry($file, $current->line, $current->identifier, $this->unquote($matches[1]), $current->path);
            } elseif (preg_match('/^\s*path:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new BaselineEntry($file, $current->line, $current->identifier, $current->message, $this->unquote($matches[1]));
            }
        }

        if ($current instanceof BaselineEntry) {
            $entries[] = $current;
        }

        return $entries;
    }

    private function startsEntry(string $line): bool
    {
        return preg_match('/^\s*-\s*$/', $line) === 1
            || preg_match('/^\s*-\s+(message|identifier|path|count):/', $line) === 1;
    }

    private function unquote(string $value): string
    {
        return trim(trim($value), '\'"');
    }
}

/**
 * Make an absolute path readable relative to the scanned root.
 */
function check_baselines_relative(string $root, string $file): string
{
    $prefix = rtrim($root, '/').'/';

    return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
}

/**
 * @param  list<string>  $argv
 */
function check_baselines_main(array $argv): int
{
    $root = dirname(__DIR__);
    $json = false;

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--json') {
            $json = true;
        } elseif (str_starts_with($argument, '--root=')) {
            $root = substr($argument, strlen('--root='));
        } elseif ($argument === '-h' || $argument === '--help') {
            fwrite(STDOUT, "usage: php scripts/check-baselines.php [--root=DIR] [--json]\n");

            return 0;
        } else {
            fwrite(STDERR, "check-baselines: unknown argument '{$argument}'\n");

            return 2;
        }
    }

    if (!is_dir($root)) {
        fwrite(STDERR, "check-baselines: root not found: {$root}\n");

        return 2;
    }

    $violations = (new BaselinePolicy($root))->violations();

    if ($json) {
        $payload = [
            'root' => $root,
            'count' => count($violations),
            'violations' => array_map(
                static fn (BaselineEntry $entry): array => [
                    'file' => check_baselines_relative($root, $entry->file),
                    'line' => $entry->line,
                    'identifier' => $entry->identifier,
                    'message' => $entry->message,
                    'path' => $entry->path,
                ],
                $violations,
            ),
        ];

        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return $violations === [] ? 0 : 1;
    }

    foreach ($violations as $violation) {
        fwrite(STDOUT, sprintf(
            "%s:%d  %s\n",
            check_baselines_relative($root, $violation->file),
            $violation->line,
            $violation->identifier,
        ));
    }

    if ($violations !== []) {
        fwrite(STDERR, sprintf(
            "\n%d baselined correctness error%s — fix the code, never the baseline.\n",
            count($violations),
            count($violations) === 1 ? '' : 's',
        ));

        return 1;
    }

    return 0;
}

exit(check_baselines_main($argv));
