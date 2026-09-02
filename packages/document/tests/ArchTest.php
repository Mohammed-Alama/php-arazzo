<?php

declare(strict_types=1);

arch('document does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Parser')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Resolver')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Normalizer')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Validator')
    ->not->toUse('Illuminate');

arch('document does not leak runner internals')
    ->expect('Alama\Arazzo\Parser')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console')
    ->not->toUse('Alama\Arazzo\Events')
    ->not->toUse('Alama\Arazzo\Protocol')
    ->expect('Alama\Arazzo\Resolver')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console')
    ->expect('Alama\Arazzo\Normalizer')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console')
    ->expect('Alama\Arazzo\Validator')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console')
    ->not->toUse('Alama\Arazzo\Events');
