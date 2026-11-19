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

/**
 * The production files that import anything from the test-support namespace.
 *
 * @return list<string>
 */
function testSupportReferences(string $repo): array
{
    $offenders = [];

    foreach (productionSourceFiles($repo) as $relative) {
        $contents = (string) file_get_contents($repo.'/'.$relative);

        if (str_contains($contents, 'Alama\Arazzo\Tests')) {
            $offenders[] = $relative;
        }
    }

    return $offenders;
}

it('keeps production source free of test-support references', function () {
    expect(testSupportReferences(dirname(__DIR__, 2)))->toBe([]);
});

it('flags a production file that references test support, so the guard fails on a real import', function () {
    $repo = sys_get_temp_dir().'/arazzo-boundary-'.uniqid();
    $source = $repo.'/packages/demo/src/Bad.php';

    mkdir(dirname($source), 0777, true);
    file_put_contents($source, "<?php\n\nuse Alama\\Arazzo\\Tests\\Support\\FakePsr18Client;\n");

    $offenders = testSupportReferences($repo);

    unlink($source);
    rmdir($repo.'/packages/demo/src');
    rmdir($repo.'/packages/demo');
    rmdir($repo.'/packages');
    rmdir($repo);

    expect($offenders)->toBe(['packages/demo/src/Bad.php']);
});
