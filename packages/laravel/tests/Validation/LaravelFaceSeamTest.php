<?php

declare(strict_types=1);

/**
 * The laravel package's composition resistors must consume the runner through
 * its public face + value types. Runner execution/protocol internals are
 * forbidden in packages/laravel/src; the OpenApiExecutorInterface SPI (swapped
 * by tests via app()->instance()) and the AsyncExecutionGraph node handles the
 * container re-exports as aliases are the only exempt runner-execution types.
 * Document-parser/normalizer carve-outs (Resolver/Persistence/API) are owned
 * by the later @internal sweep (#63).
 */
const LARAVEL_FORBIDDEN_SEAM_PREFIXES = [
    'Alama\\Arazzo\\Runner\\Execution\\',
    'Alama\\Arazzo\\Runner\\Protocol\\',
];

const LARAVEL_SEAM_SPI_ALLOWLIST = [
    'Alama\\Arazzo\\Runner\\Execution\\Interfaces\\OpenApiExecutorInterface',
    'Alama\\Arazzo\\Runner\\Execution\\StepExecutor',
    'Alama\\Arazzo\\Runner\\Execution\\WorkflowExecutor',
    'Alama\\Arazzo\\Runner\\Execution\\StepOutcomeHandler',
    'Alama\\Arazzo\\Runner\\Execution\\CorrelationResumer',
    'Alama\\Arazzo\\Runner\\Execution\\StepExecutionWorker',
    'Alama\\Arazzo\\Runner\\Execution\\WorkflowEngine',
];

function laravelSourceFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/** @return array<string, string> imported class => violating prefix */
function laravelSeamViolations(): array
{
    $violations = [];
    foreach (laravelSourceFiles() as $file) {
        $violations = array_merge($violations, laravelSeamViolationsForFile($file));
    }

    return $violations;
}

/** @return array<string, string> imported class => violating prefix */
function laravelSeamViolationsForFile(string $file): array
{
    $violations = [];
    $restricted = str_replace(LARAVEL_SEAM_SPI_ALLOWLIST, '', (string) file_get_contents($file));
    foreach (LARAVEL_FORBIDDEN_SEAM_PREFIXES as $prefix) {
        if (preg_match_all('#^use '.preg_quote($prefix, '#').'([A-Za-z0-9_]+);#m', $restricted, $matches) > 0) {
            foreach ($matches[1] as $imported) {
                $violations[$imported] = $prefix;
            }
        }
    }

    return $violations;
}

it('keeps laravel wiring off runner execution/protocol internals', function (): void {
    expect(laravelSeamViolations())->toBe([]);
});

it('detects a planted runner-internal import (sanity)', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'seam').'.php';
    file_put_contents($tmp, implode("\n", [
        '<?php',
        'use Alama\Arazzo\Runner\Execution\StepOutputExtractor;',
        'class Probe {}',
    ]));
    $violations = laravelSeamViolationsForFile($tmp);

    expect($violations)->toBe(['StepOutputExtractor' => LARAVEL_FORBIDDEN_SEAM_PREFIXES[0]]);

    unlink($tmp);
});
