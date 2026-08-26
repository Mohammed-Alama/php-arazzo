<?php

declare(strict_types=1);

it('keeps the core runtime package free of Laravel dependencies', function (): void {
    $src = dirname(__DIR__, 2).'/src';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match('/^\s*use\s+Illuminate\\\\/m', $contents) === 1
            || preg_match('/^namespace\s+.*Laravel;/m', $contents) === 1) {
            $violations[] = str_replace($src.'/', '', (string) $file->getPathname());
        }
    }

    expect($violations)->toBe([], 'Laravel references found in core runtime: '.implode(', ', $violations));
});
