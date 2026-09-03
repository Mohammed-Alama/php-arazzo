<?php

declare(strict_types=1);

arch('contracts spec DTOs are strictly typed and readonly')
    ->expect('Alama\Arazzo\Contracts\Spec')
    ->classes()
    ->toBeReadonly()
    ->toUseStrictTypes();
