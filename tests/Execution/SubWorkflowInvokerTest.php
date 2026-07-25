<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Dto\ExecutionResult;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\SubWorkflowInvoker;
use Alama\LaravelArazzo\Execution\SubWorkflowResult;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;
use Alama\LaravelArazzo\Resolution\SelectorEvaluator;
use Alama\LaravelArazzo\Resolution\Xpath\DomXpathEvaluator;

it('binds parameters, executes child workflow, returns SubWorkflowResult', function () {
    $document = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [
            new Workflow(
                workflowId: 'reconcile',
                summary: null,
                description: null,
                inputs: null,
                dependsOn: [],
                steps: [],
                successActions: [],
                failureActions: [],
                outputs: ['some_output' => 'val'],
                parameters: []
            )
        ],
        components: new Components([], [], [], []),
        specificationExtensions: []
    );

    $registry = new InMemoryDefinitionRegistry();
    $definitionId = $registry->register($document);

    $executor = Mockery::mock(WorkflowExecutor::class);
    $executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($workflow, $doc, $boundInputs, $childCtx) {
            return $workflow->workflowId === 'reconcile' 
                && $boundInputs === ['ride' => 'r-42']
                && $childCtx instanceof WorkflowContext
                && $childCtx->parentRunId !== null;
        })
        ->andReturn(new ExecutionResult('reconcile', 'completed', ['some_output' => 'val'], []));

    $exprEval = new ExpressionEvaluator();
    $selEval = new SelectorEvaluator(new DomXpathEvaluator(), $exprEval);

    $parent = new WorkflowContext($definitionId, ['rideId' => 'r-42']);
    $action = new SubWorkflowSuccessAction(
        'call', 'reconcile',
        ['ride' => new Expression('{$inputs.rideId}')],
        [],
    );

    $invoker = new SubWorkflowInvoker($registry, $executor, $exprEval, $selEval);
    $result = $invoker->invoke($action, $parent);

    expect($result)->toBeInstanceOf(SubWorkflowResult::class)
        ->and($result->childRunId)->not->toBe($parent->getExecutionId())
        ->and($result->outputs)->toBe(['some_output' => 'val']);
});
