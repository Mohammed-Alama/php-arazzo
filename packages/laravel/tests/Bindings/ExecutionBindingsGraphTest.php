<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;

it('registers the graph binding and no leaked node aliases (F2.2)', function (): void {
    $bindings = app()->getBindings();
    expect(array_key_exists(AsyncExecutionGraph::class, $bindings))->toBeTrue();

    foreach ([
        'Alama\Arazzo\Runner\Sync\StepExecutor',
        'Alama\Arazzo\Runner\Async\StepOutcomeHandler',
        'Alama\Arazzo\Runner\Async\CorrelationResumer',
        'Alama\Arazzo\Runner\Async\StepExecutionWorker',
    ] as $removedAlias) {
        expect(array_key_exists($removedAlias, $bindings))->toBeFalse();
    }
});

it('builds one graph, aliases WorkflowExecutor from it, and keeps the rest behind the facade', function (): void {
    $graph = app(AsyncExecutionGraph::class);

    expect(app(WorkflowExecutor::class))->toBe($graph->workflowExecutor());

    // The async control-flow nodes stay container-agnostic: reachable only
    // through the graph's public accessors.
    expect($graph->worker())->toBe($graph->worker())
        ->and($graph->resumer())->toBe($graph->resumer());
});
