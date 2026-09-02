<?php

declare(strict_types=1);

arch('cli does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Console')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Generator')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Renderer')
    ->not->toUse('Illuminate');
