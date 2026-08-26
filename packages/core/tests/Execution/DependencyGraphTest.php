<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Evaluation\DependencyGraph;
use Alama\Arazzo\Spec\Step;

it('computes topological order correctly', function (): void {
    $steps = [
        new Step('C', null, null, null, null, [], null, [], [], [], [], ['A', 'B']),
        new Step('A', null, null, null, null, [], null, [], [], [], [], []),
        new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->toBeNull()
        ->and($graph->getUnresolvedReferences())->toBe([])
        ->and($graph->getTopologicalOrder())->toBe(['A', 'B', 'C']);
});

it('detects cycles correctly', function (): void {
    $steps = [
        new Step('A', null, null, null, null, [], null, [], [], [], [], ['B']),
        new Step('B', null, null, null, null, [], null, [], [], [], [], ['C']),
        new Step('C', null, null, null, null, [], null, [], [], [], [], ['A']),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->not->toBeNull()
        ->and($graph->getCycle())->toBe(['A', 'B', 'C', 'A']);
});

it('detects unresolved references correctly', function (): void {
    $steps = [
        new Step('A', null, null, null, null, [], null, [], [], [], [], ['missing1', 'missing2']),
        new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']),
    ];

    $graph = new DependencyGraph($steps);

    expect($graph->getCycle())->toBeNull()
        ->and($graph->getUnresolvedReferences())->toBe([
            'A' => ['missing1', 'missing2'],
        ])
        ->and($graph->getTopologicalOrder())->toBe(['A', 'B']);
});
