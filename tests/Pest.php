<?php

/*
|--------------------------------------------------------------------------
| Monorepo test bootstrap
|--------------------------------------------------------------------------
| The root phpunit.xml.dist aggregates both package suites so that the
| git repository root doubles as the Tia project root. Each package's
| tests/Pest.php stays the single source of truth for its own wiring;
| this file simply loads them with root-level Tia configuration.
|
|   composer tia
*/

declare(strict_types=1);

require_once __DIR__.'/../packages/core/tests/Pest.php';
require_once __DIR__.'/../packages/laravel/tests/Pest.php';

pest()->tia()->locally();
