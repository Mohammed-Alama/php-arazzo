<?php

declare(strict_types=1);

arch('expression does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Illuminate');

arch('expression does not leak document/runner internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Parser')
    ->not->toUse('Alama\Arazzo\Execution')
    ->not->toUse('Alama\Arazzo\Console')
    ->not->toUse('Alama\Arazzo\Validator');
