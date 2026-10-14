<?php

declare(strict_types=1);

arch('engine does not depend on the runner (F3 gate: InjectionResult inversion)')
    ->expect('Alama\Arazzo\Engine')
    ->not->toUse('Alama\Arazzo\Runner');
