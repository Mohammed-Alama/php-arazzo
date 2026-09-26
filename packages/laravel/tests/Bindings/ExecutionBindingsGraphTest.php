<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Tests\TestCase;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;

uses(TestCase::class);

it('has the binding registered', function (): void {
    $bindings = app()->getBindings();
    expect(array_key_exists(AsyncExecutionGraph::class, $bindings))->toBeTrue();
    expect(array_key_exists(StepExecutionWorker::class, $bindings))->toBeTrue();
});

it('builds one graph and aliases every node from it', function (): void {
    $graph = app(AsyncExecutionGraph::class);

    expect(app(StepExecutionWorker::class))->toBe($graph->worker())
        ->and(app(CorrelationResumer::class))->toBe($graph->resumer())
        ->and(app(WorkflowExecutor::class))->toBe($graph->workflowExecutor());
});
