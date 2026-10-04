<?php

declare(strict_types=1);

arch('protocol-http does not depend on the runner')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner');

arch('protocol-http does not depend on Laravel runtime types in the slice core')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Illuminate\Support')
    ->ignoring('Alama\Arazzo\Protocol\Http\Laravel');
