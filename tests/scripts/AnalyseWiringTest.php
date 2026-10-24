<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| The PHPStan gate's own wiring
|--------------------------------------------------------------------------
| #230: `analyse-core` pointed at `paths: src` after `a35f36ca` made core a
| metapackage, so the script could only ever fail with "No files found to
| analyse" - and it was the one `analyse-*` target missing from the aggregate
| that the hook, `make verify` and CI actually run. The eight custom rule
| classes every package loads were therefore analysed by nothing.
|
| These tests hold the wiring, not the analysis: each `analyse-*` target must
| be in the aggregate, every config must name a directory that holds PHP, and
| the rule classes must be inside some target's paths. Whether the analysis is
| green is `composer run analyse-core`'s job.
*/

/**
 * The root composer.json scripts.
 *
 * @return array<string, string|list<string>>
 */
function analyseComposerScripts(string $repo): array
{
    /** @var array{scripts: array<string, string|list<string>>} $json */
    $json = json_decode((string) file_get_contents($repo.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    return $json['scripts'];
}

/**
 * Every per-package analysis target: script name => config path, repo-relative.
 *
 * @return array<string, string>
 */
function analyseTargets(string $repo): array
{
    $targets = [];

    foreach (analyseComposerScripts($repo) as $name => $command) {
        if ($name === 'analyse' || !str_starts_with($name, 'analyse-') || !is_string($command)) {
            continue;
        }

        if (preg_match('/-c\s+(\S+)/', $command, $matches) !== 1) {
            continue;
        }

        $targets[$name] = $matches[1];
    }

    return $targets;
}

/**
 * The `parameters.paths` of a PHPStan config, resolved against its own directory.
 *
 * @return list<string>
 */
function analyseConfigPaths(string $configFile): array
{
    /** @var array{parameters?: array{paths?: list<string>}} $yaml */
    $yaml = Yaml::parseFile($configFile);

    return array_map(
        static fn (string $path): string => dirname($configFile).'/'.$path,
        $yaml['parameters']['paths'] ?? [],
    );
}

/**
 * Whether a directory holds at least one PHP file, directly or below.
 */
function analyseDirectoryHasPhp(string $directory): bool
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            return true;
        }
    }

    return false;
}

it('lists every analyse-* target in the aggregate the gates run', function () {
    $repo = dirname(__DIR__, 2);

    $scripts = analyseComposerScripts($repo);
    $aggregate = $scripts['analyse'] ?? [];

    expect($aggregate)->toBeArray();

    $aggregated = array_map(
        static fn (string $entry): string => ltrim($entry, '@'),
        $aggregate,
    );

    $targets = array_keys(analyseTargets($repo));

    expect($aggregated)->toBe(array_values(array_unique($aggregated)), 'the aggregate lists a target twice');
    expect(array_diff($targets, $aggregated))->toBe([], 'these analyse-* targets never run in the aggregate');
    expect(array_diff($aggregated, $targets))->toBe([], 'the aggregate names targets that do not exist');
});

it('analyses every package config', function () {
    $repo = dirname(__DIR__, 2);

    $configs = array_map(
        static fn (string $config): string => 'packages/'.basename(dirname($config)).'/phpstan.neon.dist',
        glob($repo.'/packages/*/phpstan.neon.dist') ?: [],
    );

    expect($configs)->not->toBeEmpty();
    expect(array_values(analyseTargets($repo)))->toEqualCanonicalizing($configs);
});

it('points every analyse-* target at paths that contain PHP', function () {
    $repo = dirname(__DIR__, 2);

    foreach (analyseTargets($repo) as $name => $config) {
        $configFile = $repo.'/'.$config;

        expect(is_file($configFile))->toBeTrue("{$name}: config {$config} does not exist");

        $paths = analyseConfigPaths($configFile);

        expect($paths)->not->toBeEmpty("{$name}: {$config} names no paths");

        foreach ($paths as $path) {
            expect(is_dir($path))->toBeTrue("{$name}: {$config} points at {$path}, which is not a directory");
            expect(analyseDirectoryHasPhp($path))->toBeTrue("{$name}: {$config} points at {$path}, which holds no PHP");
        }
    }
});

it('analyses the custom rule classes that every package loads', function () {
    $repo = dirname(__DIR__, 2);

    $ruleFiles = glob($repo.'/packages/core/phpstan/rules/*.php') ?: [];
    expect($ruleFiles)->toHaveCount(8);

    $analysed = [];

    foreach (analyseTargets($repo) as $config) {
        foreach (analyseConfigPaths($repo.'/'.$config) as $path) {
            $analysed[] = rtrim($path, '/').'/';
        }
    }

    $uncovered = array_values(array_filter(
        $ruleFiles,
        static function (string $file) use ($analysed): bool {
            foreach ($analysed as $path) {
                if (str_starts_with($file, $path)) {
                    return false;
                }
            }

            return true;
        },
    ));

    expect($uncovered)->toBe([], 'these rule classes are loaded by every package but analysed by none');
});

it('leaves no renamed config backup behind', function () {
    $repo = dirname(__DIR__, 2);

    $backups = array_merge(
        glob($repo.'/packages/*/phpstan.neon.dist.bak') ?: [],
        glob($repo.'/packages/*/*.bak') ?: [],
    );

    expect($backups)->toBe([]);
});
