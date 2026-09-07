<?php

declare(strict_types=1);

/**
 * The runner consumes the expression + document engines exclusively through their
 * public faces (ExpressionEngineInterface, DocumentInterface) and shared value
 * shapes. Any direct import of their internals below would couple the runner to
 * implementation details that the two engines are free to change.
 */
const RUNNER_FORBIDDEN_SEAM_IMPORTS = [
    'Alama\\Arazzo\\Expression\\Ast\\',
    'Alama\\Arazzo\\Expression\\Parser',
    'Alama\\Arazzo\\Expression\\Lexer',
    'Alama\\Arazzo\\Expression\\ExpressionEvaluator',
    'Alama\\Arazzo\\Expression\\JsonPathEvaluator',
    'Alama\\Arazzo\\Expression\\JsonPointer',
    'Alama\\Arazzo\\Expression\\SelectorEvaluator',
    'Alama\\Arazzo\\Expression\\StringInterpolator',
    'Alama\\Arazzo\\Expression\\Xpath\\',
    'Alama\\Arazzo\\Expression\\Evaluation\\',
    'Alama\\Arazzo\\Document\\Normalizer\\OpenApiOperationResolver',
    'Alama\\Arazzo\\Document\\Validator\\PreflightValidator',
];

function runnerSourceFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getRealPath();
        }
    }

    sort($files);

    return $files;
}

function runnerSeamViolations(array $files): array
{
    $violations = [];
    foreach (RUNNER_FORBIDDEN_SEAM_IMPORTS as $forbidden) {
        foreach ($files as $file) {
            if (str_contains((string) file_get_contents($file), $forbidden)) {
                $violations[] = $file.': '.$forbidden;
            }
        }
    }

    return $violations;
}

it('keeps runner src free of expression and document internals', function (): void {
    expect(runnerSeamViolations(runnerSourceFiles()))->toBe([]);
});

it('flags a forbidden internal import when one sneaks in', function (): void {
    $temp = tempnam(sys_get_temp_dir(), 'runner_seam_');
    file_put_contents($temp, "<?php\nuse Alama\\Arazzo\\Expression\\ExpressionEvaluator;\n");

    $violations = runnerSeamViolations([$temp]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('ExpressionEvaluator');

    unlink($temp);
});
