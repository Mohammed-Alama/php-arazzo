<?php

declare(strict_types=1);

// NOTE: the trailing backslashes are load-bearing. Pest's toUse() matches
// fully-qualified class names, not bare namespaces, so `not->toUse('Alama\Arazzo\Runner')`
// passes vacuously. `Alama\Arazzo\Runner\` prefix-matches properly and
// transitively covers Alama\Arazzo\Runner\Protocol\*.
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse([
        'Alama\Arazzo\Engine\\',
        'Alama\Arazzo\Runner\\',
    ]);

arch('request-pipeline is framework agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse('Illuminate\\');
