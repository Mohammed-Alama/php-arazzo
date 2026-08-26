<?php

declare(strict_types=1);

namespace ArazzoDocs;

final class ScannedFile
{
    /**
     * @param  list<string>  $uses  normalized FQCNs
     * @param  list<string>  $useStatements  raw statements incl. aliases ("X\Y as Z")
     */
    public function __construct(
        public readonly string $path,
        public readonly string $relativeDir,
        public readonly string $namespace,
        public readonly string $className,
        public readonly bool $isInterface,
        public readonly array $uses,
        public readonly array $useStatements,
        public readonly string $content,
    ) {}
}

final class Scanner
{
    /** @return array<string, list<ScannedFile>> module name => files */
    public static function scan(string $srcDir, string $namespacePrefix): array
    {
        $modules = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $scanned = self::scanFile($file->getPathname(), $srcDir, $namespacePrefix);
            $modules[$scanned->path === '' ? '_' : $scanned->path][] = $scanned;
        }

        ksort($modules);

        return $modules;
    }

    private static function scanFile(string $pathname, string $srcDir, string $namespacePrefix): ScannedFile
    {
        $content = (string) file_get_contents($pathname);
        $relative = str_replace($srcDir.'/', '', $pathname);

        preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatches);
        $namespace = trim($nsMatches[1] ?? $namespacePrefix);

        preg_match('/^(?:abstract|final)?\s*(?:class|interface|enum|trait)\s+(\w+)/m', $content, $classMatches);
        $className = $classMatches[1] ?? basename($pathname, '.php');

        // module = first directory under src/ (or "_" for src root)
        $parts = explode('/', $relative);
        $module = count($parts) > 1 ? $parts[0] : '_';

        preg_match_all('/^use\s+([^;]+);/m', $content, $useMatches);

        $uses = [];
        $useStatements = [];
        foreach ($useMatches[1] as $statement) {
            $useStatements[] = trim($statement);
            $fqcn = trim(preg_replace('/\s+as\s+\w+$/', '', trim($statement)) ?? $statement);
            if (!str_starts_with($fqcn, 'Alama\\Arazzo\\')) {
                continue;
            }
            $uses[] = $fqcn;
        }

        return new ScannedFile(
            path: $module,
            relativeDir: implode('/', array_slice($parts, 0, -1)),
            namespace: $namespace,
            className: $className,
            isInterface: (bool) preg_match('/^interface\s+\w+/m', $content),
            uses: $uses,
            useStatements: $useStatements,
            content: $content,
        );
    }
}
