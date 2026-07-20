<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

it('wraps ExecuteStepJob in RunExecuteStepJob and pushes immediately when no delay is given', function (): void {
    Queue::fake();

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $job = new ExecuteStepJob($step, new WorkflowContext('def_1'));

    (new LaravelQueueDriver())->dispatch($job);

    Queue::assertPushed(RunExecuteStepJob::class, fn (RunExecuteStepJob $pushed) => $pushed->inner->step->stepId === 'A');
});

it('wraps and dispatches via later() when a delay is given', function (): void {
    Queue::fake();

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $job = new ExecuteStepJob($step, new WorkflowContext('def_1'));

    (new LaravelQueueDriver())->dispatch($job, 30);

    Queue::assertPushed(RunExecuteStepJob::class);
});

it('wraps ResumeCorrelationJob in RunResumeCorrelationJob', function (): void {
    Queue::fake();

    (new LaravelQueueDriver())->dispatch(new \Alama\LaravelArazzo\Execution\Jobs\ResumeCorrelationJob('corr_1', ['x' => 1]));

    Queue::assertPushed(\Alama\LaravelArazzo\Laravel\Jobs\RunResumeCorrelationJob::class, fn ($pushed) => $pushed->inner->correlationId === 'corr_1');
});
