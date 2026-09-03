<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\Data\SubWorkflowResult;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;

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
