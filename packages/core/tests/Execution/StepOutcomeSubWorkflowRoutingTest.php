<?php

declare(strict_types=1);

use Alama\Arazzo\Async\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Execution\RunControlFlow;
use Alama\Arazzo\Execution\RunPersistence;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Execution\SubWorkflowResult;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\ExecutionStatus;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Spec\WorkflowContext;
use Alama\Arazzo\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\State\Interfaces\StateStoreInterface;

it('routes SubWorkflowSuccessAction to SubWorkflowInvoker', function () {
    $invoker = Mockery::mock(SubWorkflowInvoker::class);
    $action = new SubWorkflowSuccessAction('sub1', 'workflow_2', [], []);
    $invoker->shouldReceive('invoke')->with($action, Mockery::type(WorkflowContext::class))->once()->andReturn(new SubWorkflowResult(['subOut' => 'abc'], ExecutionStatus::Succeeded->value, 'child_1'));

    $store = Mockery::mock(StateStoreInterface::class);
    $store->shouldReceive('save');

    $pending = Mockery::mock(PendingCorrelationRegistryInterface::class);
    $pending->shouldReceive('existsForExecution')->once()->andReturn(false);

    $exec = Mockery::mock(ExecutionRegistryInterface::class);
    $exec->shouldReceive('complete')->once();

    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldReceive('append')->once();

    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('evaluateCriteria')->andReturn(true);

    $handler = new StepOutcomeHandler(
        new RunPersistence($store, $ledger, $exec),
        new RunControlFlow(new WorkflowEngine($resolver), Mockery::mock(QueueDriverInterface::class)),
        pendingCorrelations: $pending,
        invoker: $invoker,
        selectors: Mockery::mock(SelectorEvaluator::class),
        expressions: Mockery::mock(ExpressionEvaluator::class),
    );

    $step = new Step(
        stepId: 'step1',
        description: 'Test step',
        operationId: 'op1',
        operationPath: null,
        workflowId: null,
        action: null,
        channelPath: null,
        correlationId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [$action],
        onFailure: [],
        outputs: [],
    );

    $workflow = new Workflow('test_wf', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), []);
    $context = new WorkflowContext('def_1', [], ['step1' => []], [], 'test_wf', 'exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);
});
