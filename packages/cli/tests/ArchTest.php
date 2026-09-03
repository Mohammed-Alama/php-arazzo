<?php

declare(strict_types=1);

arch('cli does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Cli\Console')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Cli\Renderer')
    ->not->toUse('Illuminate');
