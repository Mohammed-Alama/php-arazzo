<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\TestCase;

require_once __DIR__.'/Support/helpers.php';

require_once __DIR__.'/../../../vendor/autoload.php';

pest()->extend(TestCase::class)->in(__DIR__);
