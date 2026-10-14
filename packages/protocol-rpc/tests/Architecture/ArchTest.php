<?php

declare(strict_types=1);

arch('protocol-rpc does not depend on the runner, the other protocols, or a framework')
    ->expect('Alama\Arazzo\Protocol\Rpc')
    ->not->toUse(['Alama\Arazzo\Runner', 'Alama\Arazzo\Protocol\Http', 'Alama\Arazzo\Protocol\Soap', 'Illuminate']);
