<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Spec\Action\FailureEndAction;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Tests\Support\Fx;

require_once __DIR__.'/StepOutcomeHandlerTest.php';

function makeRetryHandler(float $retryBackoffMultiplier = 1.0): array
{
    [$handler, $queue] = makeStepOutcomeHandler(retryBackoffMultiplier: $retryBackoffMultiplier);

    return [$handler, $queue];
}

it('honors an HTTP Retry-After header over the declared delay', function (): void {
    [$handler, $queue] = makeRetryHandler();

    $retry = new RetryAction(name: 'r', retryAfter: 5, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $step = Fx::step('flaky', 'op', onFailure: [$retry]);
    $workflow = Fx::wf('w', [$step]);
    $document = Fx::doc(workflows: [$workflow]);
    $context = (new WorkflowContext('def_1', [], [], [], 'w', 'exec_retry'))
        ->withStepResponse('flaky', [
            'statusCode' => 503,
            'headers' => ['Retry-After' => '42'],
            'body' => [],
        ]);

    $handler->handle($document, $workflow, $step, $context, 'exec_retry', false);

    expect($queue->dispatched[0]['delaySeconds'])->toBe(42);
});

it('parses HTTP-date Retry-After values into relative seconds', function (): void {
    [$handler, $queue] = makeRetryHandler();

    $retry = new RetryAction(name: 'r', retryAfter: 5, retryLimit: 3, stepId: null, workflowId: null, criteria: []);
    $step = Fx::step('flaky', 'op', onFailure: [$retry]);
    $workflow = Fx::wf('w', [$step]);
    $document = Fx::doc(workflows: [$workflow]);
    $context = (new WorkflowContext('def_1', [], [], [], 'w', 'exec_date'))
        ->withStepResponse('flaky', [
            'statusCode' => 503,
            'headers' => ['Retry-After' => gmdate(DATE_RFC7231, time() + 60)],
            'body' => [],
        ]);

    $handler->handle($document, $workflow, $step, $context, 'exec_date', false);

    expect($queue->dispatched[0]['delaySeconds'])->toBeGreaterThanOrEqual(59)
        ->and($queue->dispatched[0]['delaySeconds'])->toBeLessThanOrEqual(60);
});

it('applies the configured backoff multiplier per attempt', function (): void {
    [$handler, $queue] = makeRetryHandler(retryBackoffMultiplier: 2.0);

    $step = Fx::step('flaky', 'op', onFailure: [
        new RetryAction(name: 'r', retryAfter: 10, retryLimit: 5, stepId: null, workflowId: null, criteria: []),
        new FailureEndAction('give-up', []),
    ]);
    $workflow = Fx::wf('w', [$step]);
    $document = Fx::doc(workflows: [$workflow]);

    // attempt #3 -> 10 * 2^2 = 40
    $context = (new WorkflowContext('def_1'))
        ->withStepAttemptIncremented('flaky')
        ->withStepAttemptIncremented('flaky')
        ->withStepStatus('flaky', StepStatus::Failed)
        ->withStepResponse('flaky', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    $handler->handle($document, $workflow, $step, $context, 'exec_backoff', false);

    expect($queue->dispatched[0]['delaySeconds'])->toBe(40);
});
