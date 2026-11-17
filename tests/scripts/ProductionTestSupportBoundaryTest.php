<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Production / test-support boundary
|--------------------------------------------------------------------------
| #334: the CLI composition root imported FakePsr18Client from the test
| support namespace and silently faked every outbound request. Production
| source must never depend on the test suite — the fakes belong at the HTTP
| boundary in tests, never inside src/.
*/

/**
 * Every PHP file under packages/<name>/src, as repo-relative paths.
 *
 * @return list<string>
 */
function productionSourceFiles(string $repo): array
{
    $paths = [];

    $directories = glob($repo.'/packages/*/src', GLOB_ONLYDIR) ?: [];

    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = str_replace($repo.'/', '', $file->getPathname());
        }
    }

    sort($paths);

    return $paths;
}

it('finds production source to scan', function () {
    $files = productionSourceFiles(dirname(__DIR__, 2));

    expect($files)->not->toBe([]);
});

it('keeps production source free of test-support references', function () {
    $repo = dirname(__DIR__, 2);
    $offenders = [];

    foreach (productionSourceFiles($repo) as $relative) {
        $contents = (string) file_get_contents($repo.'/'.$relative);

        if (str_contains($contents, 'Alama\Arazzo\Tests')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});
