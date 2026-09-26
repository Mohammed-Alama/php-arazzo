<?php

declare(strict_types=1);

arch('runner does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\State')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Events')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Jobs')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Protocol')
    ->not->toUse('Illuminate');

arch('runner does not leak expression internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\EvaluationEngine')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Alama\Arazzo\Cli\Renderer')
    ->expect('Alama\Arazzo\Runner\State')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Runner\Events')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Runner\Jobs')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Runner\Protocol')
    ->not->toUse('Alama\Arazzo\Cli\Console');

arch('runner does not depend on cebe directly')
    ->expect('Alama\Arazzo\Runner')
    ->not->toUse('cebe');
