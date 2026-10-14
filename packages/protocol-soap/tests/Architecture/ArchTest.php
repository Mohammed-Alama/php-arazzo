<?php

declare(strict_types=1);

arch('protocol-soap is vendor-free and does not depend on the HTTP protocol')
    ->expect('Alama\Arazzo\Protocol\Soap')
    ->not->toUse('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner')
    ->ignoring('Alama\Arazzo\Protocol\Soap\Laravel');
