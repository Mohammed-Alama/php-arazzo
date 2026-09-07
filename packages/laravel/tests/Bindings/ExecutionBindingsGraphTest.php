<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;

it('builds one graph and aliases every node from it', function (): void {
    $graph = app(AsyncExecutionGraph::class);

    expect(app(StepExecutionWorker::class))->toBe($graph->worker())
        ->and(app(CorrelationResumer::class))->toBe($graph->resumer())
        ->and(app(WorkflowExecutor::class))->toBe($graph->workflowExecutor());
});
