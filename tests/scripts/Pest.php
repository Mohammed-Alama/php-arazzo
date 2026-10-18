<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Scripts test bootstrap
|--------------------------------------------------------------------------
| Kept deliberately tiny: the scripts suite exercises standalone CLIs by
| spawning them, so it needs the autoloader and nothing else. It lives in its
| own directory so Pest does not have to load the monorepo Tia bootstrap at
| tests/Pest.php.
*/

require_once __DIR__.'/../../../vendor/autoload.php';
