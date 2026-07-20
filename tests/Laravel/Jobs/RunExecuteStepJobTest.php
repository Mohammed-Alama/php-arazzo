<?php

declare(strict_types=1);

namespace Tests\Laravel\Jobs;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Laravel\Jobs\RunExecuteStepJob;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

class RecordingStepExecutionWorker extends StepExecutionWorker
{
    /** @var list<ExecuteStepJob> */
    public array $handled = [];

    public function __construct()
    {
    }

    public function handle(ExecuteStepJob $job): void
    {
        $this->handled[] = $job;
    }
}

it('round-trips ExecuteStepJob through a real Laravel queue connection and reaches StepExecutionWorker::handle()', function (): void {
    config(['queue.default' => 'sync']);

    $recorder = new RecordingStepExecutionWorker();
    $this->app->instance(StepExecutionWorker::class, $recorder);

    $step = new Step('A', null, null, null, null, [], null, [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withExecutionId('exec_1');
    $innerJob = new ExecuteStepJob($step, $context);

    Queue::connection('sync')->push(new RunExecuteStepJob($innerJob));

    expect($recorder->handled)->toHaveCount(1);
    expect($recorder->handled[0]->step->stepId)->toBe('A');
    expect($recorder->handled[0]->context->getExecutionId())->toBe('exec_1');
    // A genuinely different instance -- confirms it went through real serialize/unserialize,
    // not just an in-memory closure call.
    expect($recorder->handled[0])->not->toBe($innerJob);
});
