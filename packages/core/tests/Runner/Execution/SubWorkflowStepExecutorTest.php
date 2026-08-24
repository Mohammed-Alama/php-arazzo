<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Exceptions\ExecutionException;
use Alama\Arazzo\Runner\Execution\ExecutionResult;
use Alama\Arazzo\Runner\Execution\SubWorkflowStepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Tests\Support\Fx;
use Mockery;

function nestedExecutorDocument(): ArazzoDocument
{
    $fetchUser = Fx::wf('fetch-user', [
        Fx::step('load', 'load-op', outputs: ['name' => new Expression('{$response.body#/name}')]),
    ], inputs: ['type' => 'object', 'properties' => ['userId' => []]]);

    $main = Fx::wf('main', [
        new Step(
            'enrich',
            null, null, null,
            'fetch-user',
            [new Parameter('userId', ParameterIn::Query, new Expression('{$inputs.uid}'))],
            null, [], [], [],
            ['userName' => new Expression('{$steps.enrich.outputs.name}')],
        ),
    ]);

    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [$main, $fetchUser],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('supports steps targeting a workflowId and not plain operation steps', function (): void {
    $executor = new SubWorkflowStepExecutor(
        Mockery::mock(WorkflowExecutor::class),
        new ExpressionEvaluator(),
    );

    $document = nestedExecutorDocument();

    expect($executor->supports($document->workflows[0]->steps[0], $document))->toBeTrue()
        ->and($executor->supports(Fx::step('plain', 'op'), $document))->toBeFalse()
        ->and($executor->supports(new Step(
            'async', null, null, null, null, [], null, [], [], [], [], [], 'send', 'https://broker/x',
        ), $document))->toBeFalse();
});

it('executes the child workflow and surfaces its outputs as step outputs', function (): void {
    $captured = [];
    $childExecutor = Mockery::mock(WorkflowExecutor::class);
    $childExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (Workflow $target, ArazzoDocument $doc, array $bound, WorkflowContext $child) use (&$captured) {
            $captured = ['workflowId' => $target->workflowId, 'bound' => $bound, 'parentId' => $child->parentRunId];

            return true;
        })
        ->andReturn(new ExecutionResult('fetch-user', 'succeeded', ['name' => 'user-42'], []));

    $executor = new SubWorkflowStepExecutor($childExecutor, new ExpressionEvaluator());

    $document = nestedExecutorDocument();
    $step = $document->workflows[0]->steps[0];
    $context = new WorkflowContext('def_1', ['uid' => 42], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, $document, 'exec_1');

    expect($outcome->suspended)->toBeFalse()
        ->and($outcome->statusCode)->toBe(200)
        ->and($outcome->outputs)->toBe(['name' => 'user-42'])
        ->and($outcome->inputs)->toBe(['userId' => 42])
        ->and($outcome->request['url'] ?? '')->toBe('#workflows/fetch-user')
        ->and($captured['workflowId'])->toBe('fetch-user')
        ->and($captured['bound'])->toBe(['userId' => 42])
        ->and($captured['parentId'])->toBe('exec_1');
});

it('throws a typed error when the target workflow does not exist', function (): void {
    $executor = new SubWorkflowStepExecutor(Mockery::mock(WorkflowExecutor::class), new ExpressionEvaluator());

    $document = nestedExecutorDocument();
    $orphan = new Step('ghost', null, null, null, 'missing-wf', [], null, [], [], [], []);

    $executor->execute($orphan, new WorkflowContext('def_1'), $document, 'exec_1');
})->throws(ExecutionException::class, "Sub-workflow 'missing-wf' not found");
