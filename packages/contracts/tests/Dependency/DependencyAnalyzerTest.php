<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Dependency\DependencyAnalyzer;
use Alama\Arazzo\Contracts\Dependency\DependencyGraph;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\State\WorkflowContext;

test('finds runnable steps based on dependsOn', function () {
    $stepA = new Step('A', null, new StepTarget(), new StepFlow(), new StepIo());
    $stepB = new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo());

    $graph = new DependencyGraph([$stepA, $stepB]);
    $analyzer = new DependencyAnalyzer($graph);

    // Initial state
    $context = new WorkflowContext('def_1');
    $runnable = $analyzer->getRunnableSteps($context);
    expect($runnable)->toHaveCount(1)
        ->and($runnable[0]->stepId)->toBe('A');

    // State after A completes
    $context2 = $context->withStepResult('A', ['outputs' => []])
        ->withStepStatus('A', StepStatus::Succeeded);
    $runnable2 = $analyzer->getRunnableSteps($context2);
    expect($runnable2)->toHaveCount(1)
        ->and($runnable2[0]->stepId)->toBe('B');
});

test('does not treat a Retrying step as complete or as runnable again', function () {
    $stepA = new Step('A', null, new StepTarget(), new StepFlow(), new StepIo());
    $stepB = new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo());

    $graph = new DependencyGraph([$stepA, $stepB]);
    $analyzer = new DependencyAnalyzer($graph);
    $context = new WorkflowContext('def_1');

    // Set A to Retrying
    $context = $context->withStepResult('A', ['outputs' => []])
        ->withStepStatus('A', StepStatus::Retrying);

    $runnable = $analyzer->getRunnableSteps($context);
    expect($runnable)->toBeEmpty();
});

test('does not treat a Suspended step as complete', function () {
    $stepA = new Step('A', null, new StepTarget(), new StepFlow(), new StepIo());
    $stepB = new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo());

    $graph = new DependencyGraph([$stepA, $stepB]);
    $analyzer = new DependencyAnalyzer($graph);
    $context = new WorkflowContext('def_1');

    // Set A to Suspended
    $context = $context->withStepResult('A', ['outputs' => []])
        ->withStepStatus('A', StepStatus::Suspended);

    $runnable = $analyzer->getRunnableSteps($context);
    expect($runnable)->toBeEmpty();
});

test('treats a goto-reset Pending step as runnable again even though a steps entry exists', function () {
    $stepA = new Step('A', null, new StepTarget(), new StepFlow(), new StepIo());

    $graph = new DependencyGraph([$stepA]);
    $analyzer = new DependencyAnalyzer($graph);
    $context = new WorkflowContext('def_1');

    // Set A to Pending (but simulating it had a previous result)
    $context = $context->withStepResult('A', ['outputs' => []])
        ->withStepStatus('A', StepStatus::Pending);

    $runnable = $analyzer->getRunnableSteps($context);
    expect($runnable)->toHaveCount(1)
        ->and($runnable[0]->stepId)->toBe('A');
});
