<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Events\CorrelationPendingEvent;
use Alama\Arazzo\Runner\Events\CorrelationResumedEvent;
use Alama\Arazzo\Runner\Events\RunCompletedEvent;
use Alama\Arazzo\Runner\Events\RunFailedEvent;
use Alama\Arazzo\Runner\Events\RunStartedEvent;
use Alama\Arazzo\Runner\Events\StepExecutedEvent;
use Alama\Arazzo\Runner\Events\StepFailedEvent;
use Alama\Arazzo\Runner\Events\StepRetriedEvent;
use Alama\Arazzo\Runner\Events\StepStartedEvent;

it('constructs RunStartedEvent with all fields', function () {
    $at = new DateTimeImmutable();
    $e = new RunStartedEvent('exec-1', 'wf', 'def', ['x' => 1], $at);
    expect($e->executionId)->toBe('exec-1')
        ->and($e->workflowId)->toBe('wf')
        ->and($e->definitionId)->toBe('def')
        ->and($e->inputs)->toBe(['x' => 1])
        ->and($e->at)->toBe($at);
});

it('constructs RunCompletedEvent', function () {
    $e = new RunCompletedEvent('exec-1', 'wf', ['out' => 42], new DateTimeImmutable());
    expect($e->outputs)->toBe(['out' => 42]);
});

it('constructs RunFailedEvent with a Throwable cause', function () {
    $cause = new RuntimeException('boom');
    $e = new RunFailedEvent('exec-1', 'wf', $cause, new DateTimeImmutable());
    expect($e->cause)->toBe($cause);
});

it('constructs StepStartedEvent', function () {
    $e = new StepStartedEvent('exec-1', 'wf', 'stepA', 2, new DateTimeImmutable());
    expect($e->stepId)->toBe('stepA')->and($e->attempt)->toBe(2);
});

it('constructs StepExecutedEvent', function () {
    $e = new StepExecutedEvent('exec-1', 'wf', 'stepA', 200, ['id' => 42], true, new DateTimeImmutable());
    expect($e->statusCode)->toBe(200)
        ->and($e->outputs)->toBe(['id' => 42])
        ->and($e->criteriaMet)->toBeTrue();
});

it('constructs StepRetriedEvent with nullable lastError', function () {
    $e1 = new StepRetriedEvent('exec-1', 'wf', 'stepA', 3, null, new DateTimeImmutable());
    expect($e1->lastError)->toBeNull();

    $err = new RuntimeException('nope');
    $e2 = new StepRetriedEvent('exec-1', 'wf', 'stepA', 3, $err, new DateTimeImmutable());
    expect($e2->lastError)->toBe($err);
});

it('constructs StepFailedEvent', function () {
    $e = new StepFailedEvent('exec-1', 'wf', 'stepA', new RuntimeException('x'), new DateTimeImmutable());
    expect($e->cause)->toBeInstanceOf(RuntimeException::class);
});

it('constructs CorrelationPendingEvent', function () {
    $e = new CorrelationPendingEvent('exec-1', 'wf', 'stepA', 'corr-9', 'channels/x', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9')->and($e->channelPath)->toBe('channels/x');
});

it('constructs CorrelationResumedEvent', function () {
    $e = new CorrelationResumedEvent('exec-1', 'wf', 'stepA', 'corr-9', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9');
});
