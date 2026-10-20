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
| A bump that missed one restore-key line restored a tree built by the old
| interpreter, skipped the install, and left the failure on whatever step ran
| next. These tests hold the tie, in both directions, across every workflow
| that caches vendor/.
*/

/**
 * Every workflow, parsed, keyed by a name fit for a failure message.
 *
 * @return array<string, array<string, mixed>>
 */
function ciWorkflowFiles(string $repo): array
{
    $workflows = [];

    foreach (glob($repo.'/.github/workflows/*.yml') ?: [] as $path) {
        $workflows[basename($path)] = Yaml::parseFile($path);
    }

    return $workflows;
}

/**
 * Every cache key and restore key in one workflow, with its job.
 *
 * @param  array<string, mixed>  $workflow
 * @return list<array{job: string, key: string, field: string}>
 */
function ciCacheKeys(array $workflow): array
{
    $keys = [];

    foreach ($workflow['jobs'] ?? [] as $job => $definition) {
        foreach ($definition['steps'] ?? [] as $step) {
            foreach (['key', 'restore-keys'] as $field) {
                $value = $step['with'][$field] ?? null;

                if (is_string($value)) {
                    $keys[] = ['job' => (string) $job, 'key' => $value, 'field' => $field];
                }
            }
        }
    }

    return $keys;
}

it('keeps a PHP version literal out of every cache key', function () {
    $repo = dirname(__DIR__, 2);
    $workflows = ciWorkflowFiles($repo);

    $vendorFiles = [];
    $offending = [];

    foreach ($workflows as $file => $workflow) {
        foreach (ciCacheKeys($workflow) as $entry) {
            if (str_contains($entry['key'], 'vendor-')) {
                $vendorFiles[$file] = true;
            }

            if (preg_match('/php\s*\d/i', $entry['key']) === 1) {
                $offending[] = "{$file} {$entry['job']} {$entry['field']}: {$entry['key']}";
            }
        }
    }

    // Exactly the workflows that cache a vendor tree, so a new one has to make
    // this decision rather than inherit the literal. Four cache steps in
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
            static fn (array $entry): bool => str_contains($entry['key'], 'vendor-'),
        ));

        if ($keys === []) {
            continue;
        }

        // One definition, at the workflow level: that is what makes a bump a
        // one-place edit rather than one per key.
        $version = $workflow['env']['PHP_VERSION'] ?? null;

        expect($version)->toBeString("{$file} caches vendor/ but defines no env.PHP_VERSION")
            ->and($version)->toMatch('/^\d+\.\d+$/');

        $pins = [];

        foreach ($workflow['jobs'] ?? [] as $job => $definition) {
            foreach ($definition['steps'] ?? [] as $step) {
                $pin = $step['with']['php-version'] ?? null;

                if (is_string($pin)) {
                    $pins[(string) $job] = $pin;
                }
            }
        }

        // The jobs that key on the version are exactly the jobs that pin from
        // it. Caching composer downloads alone is a different case and does not
        // belong here: those files do not depend on the interpreter.
        foreach (array_unique(array_column($keys, 'job')) as $job) {
            expect($pins[$job] ?? null)
                ->toBe('${{ env.PHP_VERSION }}', "{$file}: {$job} keys on PHP but does not pin from it");
        }

        foreach ($keys as $entry) {
            expect($entry['key'])->toContain('${{ env.PHP_VERSION }}');
        }

        $checked++;
    }

    expect($checked)->toBe(3);
});
