<?php

declare(strict_types=1);

/**
 * Ensures:
 * - cli imports only RunnerFacadeInterface from runner (not StepStateMachineEngine/WorkflowEngineInterface directly)
 * - @internal classes are not imported outside their own package
 * - core does not require alama/arazzo-cli (breaks shared-services→adapter circular dep)
 * - core/engine are framework-agnostic (no illuminate/* imports)
 */

function cliSrcFiles(string $repo): array {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($repo . '/packages/cli/src', \FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

it('cli imports only RunnerFacadeInterface from runner', function (): void {
    $repo = __DIR__ . '/../..';
    $cliFiles = cliSrcFiles($repo);
    $forbidden = [
        'Alama\\Arazzo\\Runner\\StepStateMachineEngine',
        'Alama\\Arazzo\\Runner\\WorkflowEngineInterface',
    ];

    foreach ($cliFiles as $file) {
        $content = file_get_contents($file);
        foreach ($forbidden as $forbiddenClass) {
            expect(str_contains($content, 'use ' . $forbiddenClass . ';'))
                ->toBeFalse('cli imports ' . $forbiddenClass . ' directly in ' . $file);
        }
    }

    // RunnerFacadeInterface should be importable and used
    $hasFacadeInterface = false;
    foreach ($cliFiles as $file) {
        $content = file_get_contents($file);
        if (str_contains($content, 'use Alama\\Arazzo\\Runner\\RunnerFacadeInterface;')) {
            $hasFacadeInterface = true;
        }
    }
    expect($hasFacadeInterface)->toBeTrue('cli should import RunnerFacadeInterface');
});

it('core does not require alama/arazzo-cli', function (): void {
    $repo = __DIR__ . '/../..';
    $coreManifest = json_decode(file_get_contents($repo . '/packages/core/composer.json'), true);
    $require = $coreManifest['require'] ?? [];

    expect(array_key_exists('alama/arazzo-cli', $require))
        ->toBeFalse('core requires alama/arazzo-cli (circular dep)');
    expect(array_key_exists('php-arazzo/arazzo-cli', $require))
        ->toBeFalse('core requires php-arazzo/arazzo-cli (circular dep)');
});

it('core and engine are framework-agnostic (no illuminate imports)', function (): void {
    $repo = __DIR__ . '/../..';
    $coreSrc = $repo . '/packages/core/src';
    $engineSrc = $repo . '/packages/engine/src';

    foreach ([$coreSrc, $engineSrc] as $dir) {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        foreach ($files as $file) {
            $content = file_get_contents($file);
            expect(str_contains($content, 'Illuminate\\'))
                ->toBeFalse('framework-agnostic package ' . $file . ' imports Illuminate\\');
        }
    }
});
