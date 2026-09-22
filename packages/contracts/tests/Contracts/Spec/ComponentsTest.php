<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Interaction;

it('appends an interactions bag additively', function (): void {
    $interactions = ['confirm-payment' => new Interaction(expectedPayload: 'approve')];
    $components = new Components(
        inputs: [],
        parameters: [],
        successActions: [],
        failureActions: [],
        interactions: $interactions,
    );

    expect($components->interactions)->toBe($interactions);
});

it('defaults interactions to an empty bag', function (): void {
    $components = new Components(inputs: [], parameters: [], successActions: [], failureActions: []);

    expect($components->interactions)->toBe([]);
});
