<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('core does not depend on illuminate framework')
    ->expect('Alama\Arazzo')
    ->not->toUse('Illuminate');

arch('spec dtos are strictly typed and readonly')
    ->expect('Alama\Arazzo\Contracts\Spec')
    ->classes()
    ->toBeReadonly()
    ->toUseStrictTypes();
