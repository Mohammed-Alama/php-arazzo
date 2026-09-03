<?php

declare(strict_types=1);

arch('expression does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Illuminate');

arch('expression does not leak document/runner internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Document\Parser')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Document\Validator');

arch('expression facade seams are entry-point only')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\ExpressionEngine')
    ->not->toUse('Alama\Arazzo\Document\Document')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');
