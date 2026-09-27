<?php

declare(strict_types=1);

// NOTE: the trailing backslashes are load-bearing. Pest's toUse() matches
// fully-qualified class names, not bare namespaces, so `not->toUse('Alama\Arazzo\Runner')`
// passes vacuously. `Alama\Arazzo\Runner\` prefix-matches properly and
// transitively covers Alama\Arazzo\Runner\Protocol\*. The vendor guards below
// obey the same rule: `cebe\openapi\` (50 classes) and `GuzzleHttp\` both
// resolve, while the bare `cebe\` resolves to nothing and guards nothing.
arch('request-pipeline is protocol- and runner-agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse([
        'Alama\Arazzo\Engine\\',
        'Alama\Arazzo\Runner\\',
    ]);

arch('request-pipeline is framework agnostic')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse('Illuminate\\');

// The only transport this package may speak is PSR-7/PSR-17. A vendor OpenAPI
// parser or a concrete HTTP client would bind it to one implementation — the
// same coupling that keeps response-schema validation in the runner.
arch('request-pipeline binds no vendor OpenAPI parser or HTTP client')
    ->expect('Alama\Arazzo\RequestPipeline')
    ->not->toUse([
        'cebe\\openapi\\',
        'GuzzleHttp\\',
    ]);
