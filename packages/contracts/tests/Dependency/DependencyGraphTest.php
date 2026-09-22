<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Contracts\Dependency\DependencyGraph;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;

it('computes topological order correctly', function (): void {
    $steps = [
        new Step('C', null, new StepTarget(), new StepFlow(dependsOn: ['A', 'B']), new StepIo()),
        new Step('A', null, new StepTarget(), new StepFlow(), new StepIo()),
        new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo()),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->toBeNull()
        ->and($graph->getUnresolvedReferences())->toBe([])
        ->and($graph->getTopologicalOrder())->toBe(['A', 'B', 'C']);
});

it('detects cycles correctly', function (): void {
    $steps = [
        new Step('A', null, new StepTarget(), new StepFlow(dependsOn: ['B']), new StepIo()),
        new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['C']), new StepIo()),
        new Step('C', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo()),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->not->toBeNull()
        ->and($graph->getCycle())->toBe(['A', 'B', 'C', 'A']);
});

it('detects unresolved references correctly', function (): void {
    $steps = [
        new Step('A', null, new StepTarget(), new StepFlow(dependsOn: ['missing1', 'missing2']), new StepIo()),
        new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo()),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->toBeNull()
        ->and($graph->getUnresolvedReferences())->toBe([
            'A' => ['missing1', 'missing2'],
        ])
        ->and($graph->getTopologicalOrder())->toBe(['A', 'B']);
});
