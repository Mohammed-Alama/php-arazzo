<?php

/*
|--------------------------------------------------------------------------
| Monorepo test bootstrap
|--------------------------------------------------------------------------
| The root phpunit.xml.dist aggregates the package suites so that the git
| repository root doubles as the Tia project root. Each package's
| tests/Pest.php stays the single source of truth for its own wiring;
| this file simply loads them with root-level Tia configuration.
|
| Pest only ever loads the bootstrap next to the configured test path, so
| this file is the one that has to pull in every package. Globbing keeps a
| freshly split package wired up without editing this list.
|
|   composer tia
*/

declare(strict_types=1);

$packageBootstraps = glob(__DIR__.'/../packages/*/tests/Pest.php') ?: [];

sort($packageBootstraps);

foreach ($packageBootstraps as $packageBootstrap) {
    require_once $packageBootstrap;
}

pest()->tia()->locally();
