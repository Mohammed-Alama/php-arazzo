#!/usr/bin/env php
<?php

/**
 * Package-manifest drift gate — an edit to a package manifest has to reach the
 * lock that CI installs from (#238, epic #27).
 *
 * `composer validate --check-lock` compares the root manifest's content-hash
 * and nothing else, and the lock's per-package snapshot is taken when the lock
 * is written. Nothing re-reads a package manifest afterwards, and `composer
 * install` links a path repository as it is, without re-resolving. So an edit
 * to a package manifest passes the hook, passes the install-from-lock jobs,
 * and ships a lock that describes something else.
 *
 * Compared, and why each one earns its place:
 *
 *   require, require-dev, replace, provide, conflict — the resolved graph.
 *   autoload, autoload-dev — the class maps `composer install` writes into
 *       vendor/composer. A namespace move is invisible everywhere else and
 *       leaves the installed autoloader describing the old layout.
 *   extra — read by plugins at install time; Laravel package discovery lives
 *       here.
 *   version, type — only when the manifest declares them. Composer supplies a
 *       default otherwise, and this repo's path repositories pin versions
 *       through `options.versions` (#237), so comparing the lock's value
 *       against an absent declaration would fail for no reason.
 *   name — a path repository pointed at a different package.
 *
 * Not compared, because nothing installs from them: description, keywords,
 * support, homepage, authors, license, funding. Prose edits stay free.
 *
 * Requirement names compare case-insensitively. Composer lower-cases a package
 * name when it loads it, so `PSR/Log` in a manifest and `psr/log` in the lock
 * are the same requirement, and comparing the raw strings would fail forever
 * with no `composer update` able to clear it.
 *
 * A metadata-only resolve answers the same question but cannot be a
 * pre-commit gate: it reaches the network even for a path-only change. On a
 * cold cache, with a dependency added to a package, `composer update --lock
 * --dry-run` exits 100 under `COMPOSER_DISABLE_NETWORK=1` while fetching
 * packagist metadata. This runs in the pre-commit hook, so it works offline.
 *
 * Usage
 *   php scripts/check-lock-manifests.php [--root=DIR]
 *
 * Exit codes
 *   0  every path package matches the lock's snapshot
 *   1  at least one drifted field
 *   2  usage / internal error
 */

declare(strict_types=1);

/** One disagreement between the lock's snapshot and a package manifest. */
final class Drift
{
    public function __construct(
        public readonly string $package,
        public readonly string $detail,
    ) {}
}

/** Reads the lock and re-reads every path package manifest it describes. */
final class LockManifestDrift
{
    /**
     * Requirements, compared by name. Case-insensitively, and by name rather
     * than position: composer sorts a lock, a manifest need not.
     *
     * @var list<string>
     */
    private const GRAPH_FIELDS = ['require', 'require-dev', 'replace', 'provide', 'conflict'];

    /**
     * Structures `composer install` consumes, compared key by key.
     *
     * @var list<string>
     */
    private const STRUCTURAL_FIELDS = ['autoload', 'autoload-dev', 'extra'];

    /**
     * Fields composer defaults when a manifest omits them, so only a declared
     * value is held to the lock.
     *
     * @var list<string>
     */
    private const DECLARED_FIELDS = ['version', 'type'];

    public function __construct(private readonly string $root) {}

    /** @return list<Drift> */
    public function drifts(): array
    {
        $lock = $this->readJson($this->root.'/composer.lock');
        $drifts = [];

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            $dist = is_array($package) ? ($package['dist'] ?? []) : [];

            if (!is_array($dist) || ($dist['type'] ?? null) !== 'path') {
                continue;
            }

            $name = (string) ($package['name'] ?? '(unnamed)');
            $url = (string) ($dist['url'] ?? '');

            // Composer accepts an absolute path repository and records it as
            // written, so a relative-only lookup would call a manifest that is
            // sitting right there gone.
            $manifestFile = (str_starts_with($url, '/') ? $url : $this->root.'/'.$url).'/composer.json';

            if (!is_file($manifestFile)) {
                $drifts[] = new Drift(
                    $name,
                    sprintf('the manifest it points at (%s/composer.json) is gone', $url),
                );

                continue;
            }

            $drifts = [...$drifts, ...$this->comparePackage($name, $package, $this->readJson($manifestFile))];
        }

        return $drifts;
    }

    /**
     * @param  array<array-key, mixed>  $package
     * @param  array<array-key, mixed>  $manifest
     * @return list<Drift>
     */
    private function comparePackage(string $name, array $package, array $manifest): array
    {
        $drifts = [];

        if (isset($manifest['name']) && (string) $manifest['name'] !== $name) {
            $drifts[] = new Drift($name, sprintf(
                '~ name: the lock describes %s, the manifest calls itself %s',
                $name,
                (string) $manifest['name'],
            ));
        }

        foreach (self::GRAPH_FIELDS as $field) {
            $drifts = [...$drifts, ...$this->compare(
                $field,
                $this->lowerKeys($package[$field] ?? []),
                $this->lowerKeys($manifest[$field] ?? []),
                $name,
            )];
        }

        foreach (self::STRUCTURAL_FIELDS as $field) {
            $drifts = [...$drifts, ...$this->compare(
                $field,
                $package[$field] ?? [],
                $manifest[$field] ?? [],
                $name,
            )];
        }

        foreach (self::DECLARED_FIELDS as $field) {
            if (!isset($manifest[$field])) {
                continue;
            }

            $drifts = [...$drifts, ...$this->compare($field, $package[$field] ?? null, $manifest[$field], $name)];
        }

        return $drifts;
    }

    /**
     * Compare one field, reporting the deepest path that differs so the message
     * names the requirement or the namespace rather than the whole block.
     *
     * @return list<Drift>
     */
    private function compare(string $path, mixed $locked, mixed $declared, string $package): array
    {
        if (is_array($locked) && is_array($declared)) {
            if (array_is_list($locked) && array_is_list($declared)) {
                if ($this->normalise($locked) === $this->normalise($declared)) {
                    return [];
                }

                return [new Drift($package, sprintf(
                    '~ %s: lock has %s, manifest has %s',
                    $path,
                    $this->describe($locked),
                    $this->describe($declared),
                ))];
            }

            $drifts = [];

            foreach ($declared as $key => $value) {
                if (!array_key_exists($key, $locked)) {
                    $drifts[] = new Drift($package, sprintf(
                        '+ %s.%s: %s (missing from the lock)',
                        $path,
                        $key,
                        $this->describe($value),
                    ));

                    continue;
                }

                $drifts = [...$drifts, ...$this->compare($path.'.'.$key, $locked[$key], $value, $package)];
            }

            foreach ($locked as $key => $value) {
                if (!array_key_exists($key, $declared)) {
                    $drifts[] = new Drift($package, sprintf(
                        '- %s.%s: %s (no longer in the manifest)',
                        $path,
                        $key,
                        $this->describe($value),
                    ));
                }
            }

            return $drifts;
        }

        if ($this->describe($locked) === $this->describe($declared)) {
            return [];
        }

        return [new Drift($package, sprintf(
            '~ %s: lock says %s, manifest says %s',
            $path,
            $this->describe($locked),
            $this->describe($declared),
        ))];
    }

    /**
     * Lower-case the requirement names of a `require`-style map. The top level
     * only: keys below it are namespaces and path prefixes, which composer
     * compares as written.
     *
     * @return array<array-key, mixed>
     */
    private function lowerKeys(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $lowered = [];

        foreach ($value as $key => $item) {
            $lowered[is_string($key) ? strtolower($key) : $key] = $item;
        }

        return $lowered;
    }

    /**
     * Sort nested values, so two structures that differ only in ordering
     * compare equal — composer is free to write a key order of its own.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function normalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalise($item);
            }
        }

        if (array_is_list($value)) {
            sort($value);
        } else {
            ksort($value);
        }

        return $value;
    }

    /** A string reads best bare; a structure has to be shown. */
    private function describe(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '(absent)';
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    /** @return array<array-key, mixed> */
    private function readJson(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}

/**
 * @param  list<string>  $argv
 */
function check_lock_manifests_main(array $argv): int
{
    $root = dirname(__DIR__);

    foreach (array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--root=')) {
            fwrite(STDERR, "check-lock-manifests: unknown argument '{$argument}'\n");
            fwrite(STDOUT, "usage: php scripts/check-lock-manifests.php [--root=DIR]\n");

            return 2;
        }

        $root = substr($argument, strlen('--root='));
    }

    if (!is_file($root.'/composer.lock')) {
        fwrite(STDERR, "check-lock-manifests: no composer.lock under {$root}\n");

        return 2;
    }

    try {
        $drifts = (new LockManifestDrift($root))->drifts();
    } catch (JsonException $exception) {
        fwrite(STDERR, 'check-lock-manifests: could not read a manifest: '.$exception->getMessage()."\n");

        return 2;
    }

    if ($drifts === []) {
        return 0;
    }

    fwrite(STDOUT, "❌ a package manifest no longer matches composer.lock:\n\n");

    $heading = null;

    foreach ($drifts as $drift) {
        if ($drift->package !== $heading) {
            $heading = $drift->package;
            fwrite(STDOUT, "  {$heading}\n");
        }

        fwrite(STDOUT, "    {$drift->detail}\n");
    }

    fwrite(STDOUT, "\n💡 Run: composer update\n");
    fwrite(STDOUT, "   CI installs from this lock, so a stale snapshot fails the build.\n");

    return 1;
}

exit(check_lock_manifests_main($argv));
