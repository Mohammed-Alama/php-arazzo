<?php

declare(strict_types=1);

use Alama\Arazzo\Events\CorrelationPending;
use Alama\Arazzo\Events\CorrelationResumed;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Events\StepExecuted;
use Alama\Arazzo\Events\StepFailed;
use Alama\Arazzo\Events\StepRetried;
use Alama\Arazzo\Events\StepStarted;

it('constructs RunStarted with all fields', function () {
    $at = new DateTimeImmutable();
    $e = new RunStarted('exec-1', 'wf', 'def', ['x' => 1], $at);
    expect($e->executionId)->toBe('exec-1')
        ->and($e->workflowId)->toBe('wf')
        ->and($e->definitionId)->toBe('def')
        ->and($e->inputs)->toBe(['x' => 1])
        ->and($e->at)->toBe($at);
});

it('constructs RunCompleted', function () {
    $e = new RunCompleted('exec-1', 'wf', ['out' => 42], new DateTimeImmutable());
    expect($e->outputs)->toBe(['out' => 42]);
});

it('constructs RunFailed with a Throwable cause', function () {
    $cause = new RuntimeException('boom');
    $e = new RunFailed('exec-1', 'wf', $cause, new DateTimeImmutable());
    expect($e->cause)->toBe($cause);
});

it('constructs StepStarted', function () {
    $e = new StepStarted('exec-1', 'wf', 'stepA', 2, new DateTimeImmutable());
    expect($e->stepId)->toBe('stepA')->and($e->attempt)->toBe(2);
});

it('constructs StepExecuted', function () {
    $e = new StepExecuted('exec-1', 'wf', 'stepA', 200, ['id' => 42], true, new DateTimeImmutable());
    expect($e->statusCode)->toBe(200)
        ->and($e->outputs)->toBe(['id' => 42])
        ->and($e->criteriaMet)->toBeTrue();
});

it('constructs StepRetried with nullable lastError', function () {
    $e1 = new StepRetried('exec-1', 'wf', 'stepA', 3, null, new DateTimeImmutable());
    expect($e1->lastError)->toBeNull();

    $err = new RuntimeException('nope');
    $e2 = new StepRetried('exec-1', 'wf', 'stepA', 3, $err, new DateTimeImmutable());
    expect($e2->lastError)->toBe($err);
});

it('constructs StepFailed', function () {
    $e = new StepFailed('exec-1', 'wf', 'stepA', new RuntimeException('x'), new DateTimeImmutable());
    expect($e->cause)->toBeInstanceOf(RuntimeException::class);
});

it('constructs CorrelationPending', function () {
    $e = new CorrelationPending('exec-1', 'wf', 'stepA', 'corr-9', 'channels/x', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9')->and($e->channelPath)->toBe('channels/x');
});

it('constructs CorrelationResumed', function () {
    $e = new CorrelationResumed('exec-1', 'wf', 'stepA', 'corr-9', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9');
});
