<?php

declare(strict_types=1);

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Policy\ExponentialBackoffCalculator;
use Alama\Arazzo\Policy\RetryPolicy;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Step;

function retryPolicyStep(): Step
{
    return new Step('step', null, null, null, null, [], null, [], [], [], []);
}

function retryPolicyContext(array $headers = []): WorkflowContext
{
    return new WorkflowContext('def', [], [
        'step' => ['response' => ['statusCode' => 500, 'headers' => $headers, 'body' => []]],
    ]);
}

it('computes exponential backoff per attempt', function (): void {
    $calculator = new ExponentialBackoffCalculator();

    expect($calculator->calculate(10, 1, 2.0))->toBe(10)
        ->and($calculator->calculate(10, 2, 2.0))->toBe(20)
        ->and($calculator->calculate(10, 3, 2.0))->toBe(40)
        ->and($calculator->calculate(10, 4, 1.0))->toBe(10);
});

it('returns zero delay for non-positive base', function (): void {
    expect((new ExponentialBackoffCalculator())->calculate(0, 3, 2.0))->toBe(0)
        ->and((new ExponentialBackoffCalculator())->calculate(-5, 3, 2.0))->toBe(0);
});

it('honors a numeric Retry-After header over the declared delay', function (): void {
    $policy = new RetryPolicy(maxAttempts: 5, backoffMultiplier: 3.0);
    $action = new RetryAction(name: 'retry', retryAfter: 99.0, retryLimit: null, stepId: null, workflowId: null, criteria: []);

    expect($policy->calculateDelay($action, retryPolicyStep(), retryPolicyContext(['Retry-After' => '42']), 1))->toBe(42);
});

it('scales the declared delay by the multiplier per upcoming attempt', function (): void {
    $policy = new RetryPolicy(maxAttempts: 5, backoffMultiplier: 2.0);
    $action = new RetryAction(name: 'retry', retryAfter: 10.0, retryLimit: null, stepId: null, workflowId: null, criteria: []);

    expect($policy->calculateDelay($action, retryPolicyStep(), retryPolicyContext(), 1))->toBe(10)
        ->and($policy->calculateDelay($action, retryPolicyStep(), retryPolicyContext(), 2))->toBe(20)
        ->and($policy->calculateDelay($action, retryPolicyStep(), retryPolicyContext(), 3))->toBe(40);
});

it('is exhausted at the tighter of action and policy limits', function (): void {
    $policy = new RetryPolicy(maxAttempts: 3);

    expect($policy->isExhausted(2, 10))->toBeFalse()
        ->and($policy->isExhausted(3, 10))->toBeTrue()
        ->and($policy->isExhausted(1, 1))->toBeTrue()
        ->and($policy->isExhausted(3, null))->toBeTrue();
});
