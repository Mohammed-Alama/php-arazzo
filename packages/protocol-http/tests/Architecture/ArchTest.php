<?php

declare(strict_types=1);

arch('protocol-http does not depend on the runner')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Alama\Arazzo\Runner');

arch('protocol-http does not depend on Laravel runtime types in the slice core')
    ->expect('Alama\Arazzo\Protocol\Http')
    ->not->toUse('Illuminate\Support')
    ->ignoring('Alama\Arazzo\Protocol\Http\Laravel');

// Regression guards for the F1.1 split: the OpenAPI normalizers moved *down* into
// this package, so the constraint that now matters is the opposite direction --
// the layers protocol-http builds on must not be able to name it.
arch('sources does not depend on protocol-http')
    ->expect('Alama\Arazzo\Sources')
    ->not->toUse('Alama\Arazzo\Protocol\Http');

arch('document does not depend on protocol-http')
    ->expect('Alama\Arazzo\Document')
    ->not->toUse('Alama\Arazzo\Protocol\Http');
