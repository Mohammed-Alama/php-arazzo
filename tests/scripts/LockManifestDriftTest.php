<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| The lock's snapshot of the package manifests
|--------------------------------------------------------------------------
| #238: composer validate --check-lock compares the root manifest's
| content-hash, and the lock's per-package snapshot is taken when the lock is
| written. Nothing re-reads a package manifest afterwards, and composer install
| links a path repository without re-resolving, so an edit to a package
| manifest is invisible to the hook, to the install-from-lock jobs and to the
| weekly canary.
|
| scripts/check-lock-manifests.php closes that. These tests pin both
| directions: what it has to catch, and what it has to stay quiet about.
|
| They work from fixtures rather than from this working tree on purpose. CI's
| test job rewrites a package manifest to swap in a different Laravel, so
| "silent on the working tree" is not a property a checkout has. The committed
| tree is checked by a CI step instead, on the job that installs from the lock
| and changes nothing: see ComposerLockWiringTest.
*/

/**
 * Build a throw-away root holding a lock and the path manifests it describes.
 *
 * @param  list<array<string, mixed>>  $lockedPackages
 * @param  array<string, array<string, mixed>>  $manifests  relative path => manifest
 */
function manifestFixture(array $lockedPackages, array $manifests): string
{
    $root = sys_get_temp_dir().'/arazzo-lock-manifests-'.bin2hex(random_bytes(6));

    if (!is_dir($root)) {
        mkdir($root, 0777, true);
    }

    file_put_contents($root.'/composer.lock', json_encode([
        'packages' => $lockedPackages,
        'packages-dev' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    foreach ($manifests as $relative => $manifest) {
        $file = $root.'/'.$relative;

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents(
            $file,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    return $root;
}

/**
 * A locked path package, shaped the way composer records one.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function lockedPathPackage(string $name, string $url, array $extra = []): array
{
    return array_merge([
        'name' => $name,
        'version' => 'dev-main',
        'type' => 'library',
        'dist' => ['type' => 'path', 'url' => $url, 'reference' => str_repeat('a', 40)],
        'require' => ['php' => '^8.4'],
    ], $extra);
}

/**
 * Run the gate against a root and return the finished process.
 */
function runDriftCheck(string $root): Process
{
    $repo = dirname(__DIR__, 2);

    $process = new Process(
        [PHP_BINARY, $repo.'/scripts/check-lock-manifests.php', '--root='.$root],
        $repo,
    );
    $process->run();

    return $process;
}

/**
 * The gate's two streams joined, whichever one it chose.
 */
function driftOutput(Process $process): string
{
    return $process->getOutput().$process->getErrorOutput();
}

/**
 * Rewrite the fixture's lock, for the shapes a static helper cannot express.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
 */
function rewriteLock(string $root, callable $mutate): void
{
    $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);

    file_put_contents(
        $root.'/composer.lock',
        json_encode($mutate($lock), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
    );
}

it('names the package and the requirement a manifest gained', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core')],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            'require' => ['php' => '^8.4', 'vimeo/psalm' => '^6.0'],
        ]],
    );

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(1)
        ->and(driftOutput($process))->toContain('alama/arazzo-core')
        ->and(driftOutput($process))->toContain('vimeo/psalm')
        ->and(driftOutput($process))->toContain('composer update');
});

it('names a constraint that changed and a requirement that was dropped', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core', [
            'require' => ['php' => '^8.4', 'psr/log' => '^3.0', 'symfony/yaml' => '^7.0'],
        ])],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            // symfony/yaml moved, psr/log is gone from the manifest entirely.
            'require' => ['php' => '^8.4', 'symfony/yaml' => '^8.0'],
        ]],
    );

    $process = runDriftCheck($root);
    $output = driftOutput($process);

    expect($process->getExitCode())->toBe(1)
        ->and($output)->toContain('symfony/yaml')
        ->and($output)->toContain('^7.0')
        ->and($output)->toContain('^8.0')
        ->and($output)->toContain('psr/log');
});

it('names a namespace the autoload map moved', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core', [
            'autoload' => ['psr-4' => ['Alama\\Arazzo\\' => 'src/']],
        ])],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            'require' => ['php' => '^8.4'],
            'autoload' => ['psr-4' => ['Alama\\Arazzo\\Core\\' => 'src/']],
        ]],
    );

    $process = runDriftCheck($root);
    $output = driftOutput($process);

    // composer install writes this map into vendor/composer, so a namespace that
    // moved without a resolve ships an autoloader describing the old layout.
    expect($process->getExitCode())->toBe(1)
        ->and($output)->toContain('autoload.psr-4')
        ->and($output)->toContain('Alama\\Arazzo\\Core\\');
});

it('names a version the manifest moved without a composer update', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/laravel-arazzo', 'packages/laravel', ['version' => '2.0.0-alpha.1'])],
        ['packages/laravel/composer.json' => [
            'name' => 'alama/laravel-arazzo',
            'version' => '2.1.0',
            'require' => ['php' => '^8.4'],
        ]],
    );

    $process = runDriftCheck($root);
    $output = driftOutput($process);

    expect($process->getExitCode())->toBe(1)
        ->and($output)->toContain('version')
        ->and($output)->toContain('2.0.0-alpha.1')
        ->and($output)->toContain('2.1.0');
});

it('names a path repository pointed at another package', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core')],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-something-else',
            'require' => ['php' => '^8.4'],
        ]],
    );

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(1)
        ->and(driftOutput($process))->toContain('alama/arazzo-something-else');
});

it('stays silent on a change that cannot move the resolved graph', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core')],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            'description' => 'Renamed while I was in there',
            'keywords' => ['arazzo', 'workflow'],
            'support' => ['issues' => 'https://example.test/issues'],
            'homepage' => 'https://example.test',
            'authors' => [['name' => 'Someone']],
            'license' => 'MIT',
            'require' => ['php' => '^8.4'],
        ]],
    );

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(0)
        ->and(driftOutput($process))->toBe('');
});

it('leaves alone the fields composer defaults, and a name that only differs in case', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core', [
            // A pinned package declares no version and no type, and composer
            // lower-cases a requirement name on load.
            'require' => ['php' => '^8.4', 'psr/log' => '^3.0'],
        ])],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            'require' => ['php' => '^8.4', 'PSR/Log' => '^3.0'],
        ]],
    );

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(0)
        ->and(driftOutput($process))->toBe('');
});

it('reports a locked path package whose manifest has gone', function () {
    $root = manifestFixture([lockedPathPackage('alama/arazzo-vanished', 'packages/vanished')], []);

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(1)
        ->and(driftOutput($process))->toContain('alama/arazzo-vanished')
        ->and(driftOutput($process))->toContain('composer update');
});

it('reads a path repository given as an absolute path', function () {
    $root = manifestFixture(
        [lockedPathPackage('alama/arazzo-core', 'packages/core')],
        ['packages/core/composer.json' => [
            'name' => 'alama/arazzo-core',
            'require' => ['php' => '^8.4'],
        ]],
    );

    // Composer records an absolute path repository as written, so a
    // relative-only lookup would call a manifest that is right there gone.
    rewriteLock($root, function (array $lock) use ($root): array {
        $lock['packages'][0]['dist']['url'] = $root.'/packages/core';

        return $lock;
    });

    $process = runDriftCheck($root);

    expect($process->getExitCode())->toBe(0)
        ->and(driftOutput($process))->toBe('');
});
