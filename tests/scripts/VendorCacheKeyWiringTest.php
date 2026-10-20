<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| Vendor cache keys follow the PHP pin
|--------------------------------------------------------------------------
| #234: three jobs in ci.yml, and one each in package-index.yml and
| issue-intake.yml, install from the lock and cache vendor/ because composer
| install is the slowest thing they do. A vendor tree is built by one PHP, so
| its cache key has to carry that PHP's version. It carried a literal instead,
| with a comment as the only thing tying it to the pin two lines above: nine
| occurrences of php8.4 in ci.yml alone, of which six were load-bearing.
|
| #249 moved the pin to one value per workflow and made the keys read it. The
| failure that motivated it is a missed key line: the cache restores an exact
| hit, cache-hit is true, the install is skipped, and the job runs on a tree
| built by the previous interpreter. A missed restore-key line is the milder
| case - the restore is partial, cache-hit is false, and composer install runs
| anyway, only slower.
|
| #251: the first version of these tests inferred "this workflow caches
| vendor" from the key prefix, read only .yml, and trusted the workflow-level
| pin without checking the matrix and job overrides that can move it. Each
| hole let a workflow drift while green. These tests hold the tie in both
| directions, across every workflow that caches vendor/, however it spells the
| cache step and wherever it states the version.
*/

/**
 * Every workflow, parsed, keyed by filename.
 *
 * GitHub runs both extensions, so both are read. A file under
 * .github/workflows that is not a mapping is a mistake worth naming rather
 * than a TypeError from three frames away.
 *
 * @return array<string, array<string, mixed>>
 */
function ciWorkflowFiles(string $repo): array
{
    $paths = array_merge(
        glob($repo.'/.github/workflows/*.yml') ?: [],
        glob($repo.'/.github/workflows/*.yaml') ?: [],
    );
    sort($paths);

    $workflows = [];

    foreach ($paths as $path) {
        $parsed = Yaml::parseFile($path);

        if (!is_array($parsed)) {
            throw new RuntimeException(basename($path).' is not a workflow mapping');
        }

        $workflows[basename($path)] = $parsed;
    }

    return $workflows;
}

/**
 * Whether a cache step stores a vendor tree.
 *
 * The path is the fact that matters. Reading the key prefix instead missed a
 * vendor cache under any other name, which is exactly how a new workflow would
 * arrive.
 *
 * @param  array<string, mixed>  $step
 */
function ciStepCachesVendor(array $step): bool
{
    $path = $step['with']['path'] ?? null;
    $entries = is_string($path) ? [$path] : (is_array($path) ? $path : []);

    foreach ($entries as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        foreach (preg_split('/\R/', $entry) ?: [] as $line) {
            if (preg_match('#(^|/)vendor/?$#', trim($line)) === 1) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Every cache key and restore key in one workflow, with its job and step.
 *
 * @param  array<string, mixed>  $workflow
 * @return list<array{job: string, step: int, key: string, field: string, vendor: bool}>
 */
function ciCacheKeys(array $workflow): array
{
    $keys = [];

    foreach ($workflow['jobs'] ?? [] as $job => $definition) {
        foreach ($definition['steps'] ?? [] as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $vendor = ciStepCachesVendor($step);

            foreach (['key', 'restore-keys'] as $field) {
                $value = $step['with'][$field] ?? null;

                if (is_string($value)) {
                    $keys[] = [
                        'job' => (string) $job,
                        'step' => (int) $index,
                        'key' => $value,
                        'field' => $field,
                        'vendor' => $vendor,
                    ];
                }
            }
        }
    }

    return $keys;
}

/**
 * An expression written the way GitHub evaluates it, not the way it is typed.
 *
 * ${{ env.PHP_VERSION }} and ${{env.PHP_VERSION}} are the same expression.
 */
function ciExpression(string $value): string
{
    return preg_replace('/\s+/', '', $value) ?? $value;
}

it('keeps a PHP version literal out of every cache key', function () {
    $repo = dirname(__DIR__, 2);
    $workflows = ciWorkflowFiles($repo);

    $vendorFiles = [];
    $offending = [];

    foreach ($workflows as $file => $workflow) {
        foreach (ciCacheKeys($workflow) as $entry) {
            if ($entry['vendor']) {
                $vendorFiles[$file] = true;
            }

            if (preg_match('/\bphp[-_ ]?\d/i', $entry['key']) === 1) {
                $offending[] = "{$file} {$entry['job']} {$entry['field']}: {$entry['key']}";
            }
        }
    }

    // Exactly the workflows that cache a vendor tree, so a new one has to make
    // this decision rather than inherit the literal. Three cache steps in
    // ci.yml, one in each of the other two.
    expect(array_keys($vendorFiles))->toBe(['ci.yml', 'issue-intake.yml', 'package-index.yml'])
        ->and($offending)->toBe([], 'a cache key carries a PHP version literal: '.implode(' | ', $offending));
});

it('takes the version in a vendor key from the pin the same job hands setup-php', function () {
    $repo = dirname(__DIR__, 2);
    $checked = 0;

    foreach (ciWorkflowFiles($repo) as $file => $workflow) {
        $keys = array_values(array_filter(
            ciCacheKeys($workflow),
            static fn (array $entry): bool => $entry['vendor'],
        ));

        if ($keys === []) {
            continue;
        }

        // One definition, at the workflow level: that is what makes a bump a
        // one-place edit rather than one per key. A patch pin is a valid pin.
        $version = $workflow['env']['PHP_VERSION'] ?? null;

        expect($version)->toBeString("{$file} caches vendor/ but defines no env.PHP_VERSION")
            ->and($version)->toMatch('/^\d+\.\d+(\.\d+)?$/');

        // Nothing below may restate it: job env overrides the workflow env at
        // runtime, and a step env overrides both.
        foreach ($workflow['jobs'] ?? [] as $job => $definition) {
            expect($definition['env']['PHP_VERSION'] ?? null)
                ->toBeNull("{$file}: the {$job} job overrides env.PHP_VERSION");

            foreach ($definition['steps'] ?? [] as $step) {
                if (is_array($step)) {
                    expect($step['env']['PHP_VERSION'] ?? null)
                        ->toBeNull("{$file}: a step in the {$job} job overrides env.PHP_VERSION");
                }
            }
        }

        // A matrix cannot read env, so it has to agree rather than share. Left
        // behind on a bump, it silently keeps one job on the old interpreter.
        foreach ($workflow['jobs'] ?? [] as $job => $definition) {
            $matrix = $definition['strategy']['matrix']['php'] ?? null;

            foreach (is_array($matrix) ? $matrix : [$matrix] as $value) {
                if ($value === null) {
                    continue;
                }

                expect((string) $value)
                    ->toBe($version, "{$file}: the {$job} matrix disagrees with env.PHP_VERSION");
            }
        }

        $pins = [];

        foreach ($workflow['jobs'] ?? [] as $job => $definition) {
            foreach ($definition['steps'] ?? [] as $step) {
                $pin = is_array($step) ? ($step['with']['php-version'] ?? null) : null;

                if (is_string($pin)) {
                    $pins[(string) $job] = $pin;
                }
            }
        }

        // The jobs that cache vendor are exactly the jobs that pin from the
        // shared version. Caching composer downloads alone is a different case
        // and does not belong here: those files do not depend on the
        // interpreter.
        foreach (array_unique(array_column($keys, 'job')) as $job) {
            expect(ciExpression($pins[$job] ?? ''))
                ->toBe('${{env.PHP_VERSION}}', "{$file}: {$job} caches vendor/ but does not pin from it");
        }

        foreach ($keys as $entry) {
            expect(ciExpression($entry['key']))->toContain('${{env.PHP_VERSION}}');
        }

        $checked++;
    }

    // Three vendor-caching workflows today. If path detection breaks, this is
    // the assertion that notices, rather than an empty loop that passes.
    expect($checked)->toBe(3);
});
