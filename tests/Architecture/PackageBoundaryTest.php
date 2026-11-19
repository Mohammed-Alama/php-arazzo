<?php

declare(strict_types=1);

/**
 * Ensures no circular composer/package dependencies exist among the surviving packages.
 * Reads each package's own composer.json `require` block and builds the dependency graph,
 * then detects cycles via DFS.
 */
it('has no circular package dependencies', function (): void {
    $repo = __DIR__.'/../..';
    $packagesDir = $repo.'/packages';
    $graph = [];

    foreach (glob("{$packagesDir}/*", GLOB_ONLYDIR) as $dir) {
        $manifestFile = "{$dir}/composer.json";
        if (!is_file($manifestFile)) {
            continue;
        }
        $manifest = json_decode(file_get_contents($manifestFile), true);
        $name = $manifest['name'] ?? '';
        if (!str_starts_with($name, 'php-arazzo/arazzo-')) {
            continue;
        }
        $require = array_keys($manifest['require'] ?? []);
        $deps = array_filter(
            $require,
            fn (string $dep): bool => str_starts_with($dep, 'php-arazzo/arazzo-') && $dep !== $name,
        );
        $graph[$name] = array_values($deps);
    }

    // Detect cycles via DFS
    $visited = [];
    $stack = [];
    $cycle = null;

    function dfs(string $node, array &$graph, array &$visited, array &$stack, ?string &$cycle): void
    {
        if ($cycle !== null) {
            return;
        }
        if (in_array($node, $stack, true)) {
            $cycle = implode(' → ', array_slice($stack, array_search($node, $stack, true))).' → '.$node;

            return;
        }
        if (isset($visited[$node])) {
            return;
        }
        $visited[$node] = true;
        $stack[] = $node;
        foreach (($graph[$node] ?? []) as $dep) {
            dfs($dep, $graph, $visited, $stack, $cycle);
        }
        array_pop($stack);
    }

    foreach (array_keys($graph) as $pkg) {
        if ($cycle !== null) {
            break;
        }
        dfs($pkg, $graph, $visited, $stack, $cycle);
    }

    expect($cycle)->toBeNull("Circular dependency detected: $cycle");
});
