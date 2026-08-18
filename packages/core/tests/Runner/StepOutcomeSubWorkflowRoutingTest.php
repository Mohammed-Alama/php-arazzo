<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Resolver\SelectorEvaluator;
use Alama\Arazzo\Runner\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Engine;
use Alama\Arazzo\Runner\ExecutionStatus;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\StepOutcomeHandler;
use Alama\Arazzo\Runner\SubWorkflowInvoker;
use Alama\Arazzo\Runner\SubWorkflowResult;
use Alama\Arazzo\Runner\WorkflowContext;

it('routes SubWorkflowSuccessAction to SubWorkflowInvoker', function () {
    $invoker = Mockery::mock(SubWorkflowInvoker::class);
    $action = new SubWorkflowSuccessAction('sub1', 'workflow_2', [], []);
    $invoker->shouldReceive('invoke')->with($action, Mockery::type(WorkflowContext::class))->once()->andReturn(new SubWorkflowResult(['subOut' => 'abc'], ExecutionStatus::Succeeded->value, 'child_1'));

    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('evaluate')->once();

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
        Mockery::mock(QueueDriverInterface::class),
        $engine,

        $exec,
        $ledger,
        $pending,
        $resolver,
        $store,
        $invoker,
        Mockery::mock(SelectorEvaluator::class),
        Mockery::mock(ExpressionEvaluator::class),
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
