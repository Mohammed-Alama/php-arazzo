<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Interaction;

it('models an actor interaction with expected payload and timeout', function (): void {
    $interaction = new Interaction(expectedPayload: 'anything', timeout: 'PT30S');

    expect($interaction->expectedPayload)->toBe('anything')
        ->and($interaction->timeout)->toBe('PT30S');
});

it('defaults to null payload and timeout', function (): void {
    $interaction = new Interaction();

    expect($interaction->expectedPayload)->toBeNull()
        ->and($interaction->timeout)->toBeNull();
});
