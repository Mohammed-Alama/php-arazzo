<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\StepFlow;

it('defaults every control field', function (): void {
    $flow = new StepFlow();

    expect($flow->dependsOn)->toBe([])
        ->and($flow->timeout)->toBeNull()
        ->and($flow->timeoutDuration)->toBeNull()
        ->and($flow->onSuccess)->toBe([])
        ->and($flow->onFailure)->toBe([])
        ->and($flow->onTimeout)->toBe([])
        ->and($flow->onCancel)->toBe([])
        ->and($flow->strictValidation)->toBeNull()
        ->and($flow->idempotencyKey)->toBeNull()
        ->and($flow->idempotencyHeader)->toBeNull();
});

it('carries dependency, timing and idempotency fields', function (): void {
    $flow = new StepFlow(
        dependsOn: ['a'],
        timeout: 30000,
        timeoutDuration: 'PT30S',
        strictValidation: true,
        idempotencyKey: true,
        idempotencyHeader: 'Idempotency-Key',
    );

    expect($flow->dependsOn)->toBe(['a'])
        ->and($flow->timeout)->toBe(30000)
        ->and($flow->timeoutDuration)->toBe('PT30S')
        ->and($flow->strictValidation)->toBeTrue()
        ->and($flow->idempotencyKey)->toBeTrue()
        ->and($flow->idempotencyHeader)->toBe('Idempotency-Key');
});
