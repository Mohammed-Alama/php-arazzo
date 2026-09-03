<?php

declare(strict_types=1);

namespace ArazzoDocs;

/**
 * Composer packages that hold the split-out core source (contracts, expression,
 * document, runner, cli). `packages/core` itself is now an empty aggregator
 * package: its src/ is a placeholder (see .gitkeep) and its tests/ holds only
 * cross-cutting integration/architecture/conformance suites, so callers that
 * need real source or per-package unit tests must scan these instead.
 *
 * @var list<string>
 */
const CORE_SRC_PACKAGES = ['contracts', 'expression', 'document', 'runner', 'cli'];

/**
 * Real cross-package dependency direction, bottom (most depended-upon) to top
 * (most dependent), read directly from each package's composer.json `require`:
 * contracts <- expression <- document <- runner <- cli <- laravel. A package
 * may only import from packages strictly below it in this list; an import
 * pointing the other way is a layering violation.
 *
 * @var list<string>
 */
const PACKAGE_LAYER_ORDER = ['contracts', 'expression', 'document', 'runner', 'cli', 'laravel'];

/**
 * Namespace-segment each package claims, used to render package-qualified
 * namespace labels (`Alama\Arazzo\{Package}\{SubNamespace}`).
 *
 * @var array<string, string> package slug => namespace segment
 */
const PACKAGE_NAMESPACE = [
    'contracts' => 'Contracts',
    'expression' => 'Expression',
    'document' => 'Document',
    'runner' => 'Runner',
    'cli' => 'Cli',
    'laravel' => 'Laravel',
];

/**
 * Owning package (composer slug) of each core module (first dir under a package's
 * src/). Module names are unique across packages, so this is authoritative and
 * deterministic.
 *
 * @var array<string, string> module => package slug
 */
const MODULE_PACKAGE_MAP = [
    'Contracts' => 'contracts',
    'Dependency' => 'contracts',
    'Spec' => 'contracts',
    'Support' => 'contracts',
    'Evaluation' => 'expression',
    'Expression' => 'expression',
    'Document' => 'document',
    'Normalizer' => 'document',
    'Parser' => 'document',
    'Resolver' => 'document',
    'Validator' => 'document',
    'Async' => 'runner',
    'Events' => 'runner',
    'Execution' => 'runner',
    'Infrastructure' => 'runner',
    'Jobs' => 'runner',
    'Policy' => 'runner',
    'Protocol' => 'runner',
    'Runner' => 'runner',
    'State' => 'runner',
    'Telemetry' => 'runner',
    'Console' => 'cli',
    'Generator' => 'cli',
    'Renderer' => 'cli',
];

final class ScannedFile
{
    /**
     * @param  string  $package  owning composer package slug — 'contracts',
     *                           'expression', 'document', 'runner', 'cli', or
     *                           'laravel'. This is the real package boundary;
     *                           $path/module is only the first directory under
     *                           that package's own src/ and says nothing about
     *                           which composer package a file lives in.
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
        public readonly string $package = '',
    ) {}
}

/**
 * Namespace segment a module claims, e.g. module `Execution` -> `Runner`.
 * Mirrors the actual package.getName => PACKAGE_NAMESPACE mapping.
 */
function modulePackageSegment(string $module): string
{
    return PACKAGE_NAMESPACE[MODULE_PACKAGE_MAP[$module] ?? ''] ?? $module;
}

/**
 * Full package-qualified namespace label for a scanned core module, e.g.
 * module `Execution` -> `Alama\Arazzo\Runner\Execution`.
 */
function moduleNamespace(string $module): string
{
    return 'Alama\\Arazzo\\'.modulePackageSegment($module).'\\'.$module;
}

final class Scanner
{
    /**
     * @param  string  $package  owning composer package slug, e.g. 'contracts',
     *                           'runner', 'laravel'. Stamped onto every
     *                           ScannedFile so callers can group/analyze by real
     *                           package boundary instead of by bare module name.
     * @return array<string, list<ScannedFile>> module name => files
     */
    public static function scan(string $srcDir, string $namespacePrefix, string $package = ''): array
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
            $scanned = self::scanFile($file->getPathname(), $srcDir, $namespacePrefix, $package);
            $modules[$scanned->path === '' ? '_' : $scanned->path][] = $scanned;
        }

        ksort($modules);

        return $modules;
    }

    private static function scanFile(string $pathname, string $srcDir, string $namespacePrefix, string $package): ScannedFile
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
            package: $package,
        );
    }
}
