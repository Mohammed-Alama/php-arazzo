#!/usr/bin/env php
<?php

/**
 * Suppression policy gate — a correctness error may never be hidden from
 * PHPStan (issues #201 and #223, epic #197).
 *
 * Two rules, both enforced here:
 *
 *   1. Suppressions live in a `phpstan-baseline.neon`. An `ignoreErrors` block
 *      in a PHPStan config is a violation however it is written: a config
 *      pattern is hand-written, and hand-written patterns carry no
 *      `identifier:`, so they slip past the identifier rule below entirely.
 *      Anything that must be suppressed goes in the baseline, where this gate
 *      can see and audit it.
 *   2. A baseline entry is legal iff it carries no `identifier:` at all — this
 *      repo's own PHPStan rules emit none, so those findings are rule-authoring
 *      signal, not correctness bugs — or its identifier matches `missingType.*`,
 *      an untyped collection being a documentation gap rather than a crash.
 *
 * Everything else (`method.notFound`, `function.alreadyNarrowedType`, …) is a
 * real defect that has to be fixed in the code, never frozen in a suppression.
 *
 * Usage
 *   php scripts/check-baselines.php [--root=DIR] [--json]
 *
 * Exit codes
 *   0  clean
 *   1  at least one disallowed suppression
 *   2  usage / internal error
 *
 * Wired into `.githooks/pre-commit`, the PHPStan CI job, `make verify` and
 * `scripts/quality-gates.php` (#202). The quality gate passes `--json` so the
 * snapshot can record the violation count; every other caller uses the default
 * human-readable output.
 */

declare(strict_types=1);

/**
 * One suppression this gate refuses to accept, as read from a baseline or a
 * PHPStan config.
 */
final class Suppression
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $identifier,
        public readonly string $message,
        public readonly string $path,
        public readonly string $kind,
    ) {}
}

/**
 * Reads every baseline and every PHPStan config under a root and reports the
 * suppressions that breach the policy above.
 */
final class SuppressionPolicy
{
    /** Directory names never worth descending into when hunting for NEON. */
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

    /** A suppression declared in a PHPStan config (never legal). */
    public const CONFIG = 'config';

    /** A suppression declared in a `phpstan-baseline.neon` (legal when identifier-free). */
    public const BASELINE = 'baseline';

    public function __construct(private readonly string $root) {}

    /**
     * Every `phpstan-baseline.neon` below the root, repo-relative, sorted.
     *
     * @return list<string>
     */
    public function baselines(): array
    {
        return array_values(array_filter(
            $this->neonFiles(),
            static fn (string $file): bool => basename($file) === 'phpstan-baseline.neon',
        ));
    }

    /**
     * Every NEON file below the root that is *not* a baseline, repo-relative,
     * sorted. These are the PHPStan configs (and includes) that must declare no
     * suppression of their own.
     *
     * @return list<string>
     */
    public function configs(): array
    {
        return array_values(array_filter(
            $this->neonFiles(),
            static fn (string $file): bool => basename($file) !== 'phpstan-baseline.neon',
        ));
    }

    /**
     * Every suppression across every baseline and config that breaches the
     * policy.
     *
     * @return list<Suppression>
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

        foreach ($this->configs() as $config) {
            foreach ($this->declaredSuppressions($config) as $entry) {
                $violations[] = $entry;
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
     * Every `*.neon` / `*.neon.dist` below the root, sorted.
     *
     * @return list<string>
     */
    private function neonFiles(): array
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
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $name = $file->getFilename();

            if (str_ends_with($name, '.neon') || str_ends_with($name, '.neon.dist')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Minimal NEON reader for the shape PHPStan emits: a list of `-` blocks,
     * each with `message` / optional `identifier` / `count` / `path` keys. We
     * only need the identifier, so a line scan beats pulling in a NEON parser
     * (and keeps the reported line number accurate).
     *
     * @return list<Suppression>
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
                if ($current instanceof Suppression) {
                    $entries[] = $current;
                }

                $current = new Suppression($file, $index + 1, '', '', '', self::BASELINE);
            }

            if (!$current instanceof Suppression) {
                continue;
            }

            if (preg_match('/^\s*identifier:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new Suppression($file, $index + 1, $this->unquote($matches[1]), $current->message, $current->path, self::BASELINE);
            } elseif (preg_match('/^\s*message:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new Suppression($file, $current->line, $current->identifier, $this->unquote($matches[1]), $current->path, self::BASELINE);
            } elseif (preg_match('/^\s*path:\s*(.+?)\s*$/', $line, $matches) === 1) {
                $current = new Suppression($file, $current->line, $current->identifier, $current->message, $this->unquote($matches[1]), self::BASELINE);
            }
        }

        if ($current instanceof Suppression) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * Every suppression an `ignoreErrors` block declares in a PHPStan config.
     *
     * Each entry is a violation by definition: the policy is that suppressions
     * live in the baseline, and a config pattern is the one place a hand-written
     * (identifier-free) pattern can hide where the identifier rule cannot see it
     * (#223).
     *
     * Understood shapes — the block PHPStan writes,
     *
     *     ignoreErrors:
     *         -
     *             message: '#^…$#'
     *             count: 1
     *             path: src/Foo.php
     *
     * the inline form it also accepts, `ignoreErrors:` followed by `- '#^…$#'`
     * items, and an empty declaration. `ignoreErrors: []` — what
     * `--generate-baseline` writes when it finds nothing — stays legal, and no
     * `paths:` / `excludePaths:` / `scanDirectories:` / `includes:` list is
     * mistaken for an entry, because only lines under an `ignoreErrors:` key
     * are read.
     *
     * @return list<Suppression>
     */
    private function declaredSuppressions(string $file): array
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $entries = [];
        $current = null;
        $blockIndent = null;

        foreach ($lines as $index => $line) {
            if ($blockIndent !== null) {
                $trimmed = trim($line);

                if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                    $indent = strlen($line) - strlen(ltrim($line));

                    if ($indent <= $blockIndent) {
                        // Back at the config's own keys: the block is over, and
                        // this line may open the next one.
                        if ($current instanceof Suppression) {
                            $entries[] = $current;
                        }

                        $current = null;
                        $blockIndent = null;
                    }
                }

                if ($blockIndent !== null) {
                    if (preg_match('/^\s*-\s*(.*)$/', $line, $matches) === 1) {
                        if ($current instanceof Suppression) {
                            $entries[] = $current;
                        }

                        $current = new Suppression($file, $index + 1, '', $this->unquote($matches[1]), '', self::CONFIG);

                        continue;
                    }

                    if ($current instanceof Suppression) {
                        if (preg_match('/^\s*message:\s*(.+?)\s*$/', $line, $matches) === 1) {
                            $current = new Suppression($file, $current->line, $current->identifier, $this->unquote($matches[1]), $current->path, self::CONFIG);
                        } elseif (preg_match('/^\s*identifier:\s*(.+?)\s*$/', $line, $matches) === 1) {
                            $current = new Suppression($file, $current->line, $this->unquote($matches[1]), $current->message, $current->path, self::CONFIG);
                        } elseif (preg_match('/^\s*path:\s*(.+?)\s*$/', $line, $matches) === 1) {
                            $current = new Suppression($file, $current->line, $current->identifier, $current->message, $this->unquote($matches[1]), self::CONFIG);
                        }
                    }

                    continue;
                }
            }

            if (preg_match('/^(\s*)ignoreErrors:\s*(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            // Drop a trailing NEON comment before reading the value.
            $value = trim(preg_replace('/\s+#.*$/', '', $matches[2]) ?? '');

            if ($value === '[]') {
                continue;
            }

            if ($value === '') {
                $blockIndent = strlen($matches[1]);
                $current = null;

                continue;
            }

            // Any other inline value — a populated list, or a `[` whose items
            // span lines — declares at least one suppression. Report it as one.
            $entries[] = new Suppression($file, $index + 1, '', $this->unquote($value), '', self::CONFIG);
        }

        if ($current instanceof Suppression) {
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

    $violations = (new SuppressionPolicy($root))->violations();

    if ($json) {
        $payload = [
            'root' => $root,
            'count' => count($violations),
            'violations' => array_map(
                static fn (Suppression $entry): array => [
                    'file' => check_baselines_relative($root, $entry->file),
                    'line' => $entry->line,
                    'identifier' => $entry->identifier,
                    'message' => $entry->message,
                    'path' => $entry->path,
                    'kind' => $entry->kind,
                ],
                $violations,
            ),
        ];

        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return $violations === [] ? 0 : 1;
    }

    foreach ($violations as $violation) {
        fwrite(STDOUT, sprintf(
            "%s:%d  [%s] %s\n",
            check_baselines_relative($root, $violation->file),
            $violation->line,
            $violation->kind,
            $violation->identifier !== '' ? $violation->identifier : $violation->message,
        ));
    }

    if ($violations !== []) {
        fwrite(STDERR, sprintf(
            "\n%d correctness error%s hidden from PHPStan — fix the code, never the config or baseline.\n",
            count($violations),
            count($violations) === 1 ? '' : 's',
        ));

        return 1;
    }

    return 0;
}

exit(check_baselines_main($argv));
