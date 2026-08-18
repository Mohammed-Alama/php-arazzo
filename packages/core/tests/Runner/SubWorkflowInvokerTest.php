<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Exceptions\ExecutionException;
use Alama\Arazzo\Resolver\SelectorEvaluator;
use Alama\Arazzo\Resolver\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Dto\ExecutionResult;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\SubWorkflowInvoker;
use Alama\Arazzo\Runner\SubWorkflowResult;
use Alama\Arazzo\Runner\WorkflowContext;
use Alama\Arazzo\Runner\WorkflowExecutor;

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
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
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
        ->and($result->outputs)->toBe(['some_output' => 'val'])
        ->and($result->status)->toBe('completed');
});

it('throws ExecutionException when sub workflow cannot be found', function () {
    $document = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $registry = new InMemoryDefinitionRegistry();
    $definitionId = $registry->register($document);

    $executor = Mockery::mock(WorkflowExecutor::class);
    $exprEval = new ExpressionEvaluator();
    $selEval = new SelectorEvaluator(new DomXpathEvaluator(), $exprEval);

    $parent = new WorkflowContext($definitionId, []);
    $action = new SubWorkflowSuccessAction(
        'call', 'not-found-workflow',
        [],
        [],
    );

    $invoker = new SubWorkflowInvoker($registry, $executor, $exprEval, $selEval);

    expect(fn () => $invoker->invoke($action, $parent))
        ->toThrow(ExecutionException::class, "Sub-workflow 'not-found-workflow' not found in registry.");
});
