<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

it('never imports expression parser/AST/internal services in validator src', function (): void {
    // The document validator consumes the expression package only through
    // ExpressionEngineInterface plus cross-seam value shapes (SymbolTable,
    // Data\*, Enum\ReferenceKind, Exceptions\*). Concrete parsers, AST nodes
    // and evaluator services must stay out of validator classes.
    $forbidden = [
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
    ];

    $violations = [];
    $root = dirname(__DIR__, 2).'/src/Validator';
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $content = (string) file_get_contents($file->getPathname());
        \preg_match_all('/use (Alama\\\\Arazzo\\\\Expression\\\\[A-Za-z\\\\]+)/', $content, $matches);

        foreach ($matches[1] as $import) {
            foreach ($forbidden as $prefix) {
                if (str_starts_with($import, $prefix)) {
                    $violations[] = $file->getFilename().' imports '.$import;
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
