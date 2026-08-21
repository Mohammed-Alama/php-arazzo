<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\Arazzo\Laravel\Queue\Jobs\RunExecuteStepJob;
use Alama\Arazzo\Laravel\Queue\Jobs\RunResumeCorrelationJob;
use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Runner\Jobs\ResumeCorrelationJob;
use Alama\Arazzo\Spec\Step;
use Illuminate\Support\Facades\Queue;

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

    (new LaravelQueueDriver())->dispatch(new ResumeCorrelationJob('corr_1', ['x' => 1]));

    Queue::assertPushed(RunResumeCorrelationJob::class, fn ($pushed) => $pushed->inner->correlationId === 'corr_1');
});
