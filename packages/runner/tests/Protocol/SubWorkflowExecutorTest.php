<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Protocol\SubWorkflowExecutor;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;

function subDoc(): ArazzoDocument
{
    $child = new Workflow('child_wf', null, null, null, [], [
        new Step('c1', null, 'createRide', null, null, [], null, [], [], [], []),
    ], [], [], [], []);

    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [$child],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function subExecutor(): SubWorkflowExecutor
{
    return new SubWorkflowExecutor(new WorkflowEngine(new TestExpressionResolver()), subResolver());
}

function subResolver(): ExpressionResolverInterface
{
    return new TestExpressionResolver();
}

it('supports steps that target a workflow and not plain operations', function (): void {
    $executor = new SubWorkflowExecutor(new WorkflowEngine(subResolver()), subResolver());

    expect($executor->supports(new Step('s', null, null, null, 'child_wf', [], null, [], [], [], []), subDoc()))->toBeTrue()
        ->and($executor->supports(new Step('s', null, 'op', null, null, [], null, [], [], [], []), subDoc()))->toBeFalse()
        ->and($executor->supports(new Step('s', null, null, '/paths/x', null, [], null, [], [], [], []), subDoc()))->toBeFalse();
});

it('returns a typed failure outcome when the target workflow is missing', function (): void {
    $outcome = subExecutor()->execute(
        new Step('s', null, null, null, 'nope_wf', [], null, [], [], [], []),
        new WorkflowContext('def'),
        subDoc(),
        'exec_1',
    );

    expect($outcome)->toBeInstanceOf(StepExecutionOutcome::class)
        ->and($outcome->failureCategory)->toBe('execution');
});

it('runs the child workflow inline and surfaces its outputs', function (): void {
    $outcome = subExecutor()->execute(
        new Step('invoke-child', null, null, null, 'child_wf', [], null, [], [], [], []),
        new WorkflowContext('def'),
        subDoc(),
        'exec_2',
    );

    // TestExpressionResolver-based engine completes the single child step;
    // outcome carries the workflowId marker in the request payload.
    expect($outcome->failureCategory)->toBeNull()
        ->and($outcome->request['workflowId'] ?? null)->toBe('child_wf');
});
