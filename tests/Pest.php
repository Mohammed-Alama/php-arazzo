<?php

/*
|--------------------------------------------------------------------------
| Monorepo test bootstrap
|--------------------------------------------------------------------------
| The root phpunit.xml.dist aggregates the package and tool suites so that
| the git repository root doubles as the Tia project root. Each package's
| (and tool's) tests/Pest.php stays the single source of truth for its own
| wiring; this file simply loads them with root-level Tia configuration.
|
| Pest only ever loads the bootstrap next to the configured test path, so
| this file is the one that has to pull in every package and tool. Globbing
| keeps a freshly split package or new tool wired up without editing this
| list.
|
|   composer tia
*/

declare(strict_types=1);

$bootstraps = [
    ...glob(__DIR__.'/../packages/*/tests/Pest.php') ?: [],
    ...glob(__DIR__.'/../tools/*/tests/Pest.php') ?: [],
];

sort($bootstraps);

foreach ($bootstraps as $bootstrap) {
    require_once $bootstrap;
}

pest()->tia()->locally();
