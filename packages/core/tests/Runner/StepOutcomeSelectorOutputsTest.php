<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Execution\RunControlFlow;
use Alama\Arazzo\Runner\Execution\RunPersistence;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ExpressionType;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

it('resolves a Selector output through SelectorEvaluator', function () {
    $selectors = Mockery::mock(SelectorEvaluator::class);
    $selectors->shouldReceive('evaluate')->once()->andReturn('bar');

    $engine = new WorkflowEngine(Mockery::mock(ExpressionResolverInterface::class));

    $store = Mockery::mock(StateStoreInterface::class);
    $store->shouldReceive('save');

    $pending = Mockery::mock(PendingCorrelationRegistryInterface::class);
    $pending->shouldReceive('existsForExecution')->once()->andReturn(false);

    $exec = Mockery::mock(ExecutionRegistryInterface::class);
    $exec->shouldReceive('complete')->once();

    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldReceive('append')->once();

    $handler = new StepOutcomeHandler(
        new RunPersistence($store, $ledger, $exec),
        new RunControlFlow(new WorkflowEngine(Mockery::mock(ExpressionResolverInterface::class)), Mockery::mock(QueueDriverInterface::class)),
        pendingCorrelations: $pending,
        invoker: Mockery::mock(SubWorkflowInvoker::class),
        selectors: $selectors,
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
        onSuccess: [],
        onFailure: [],
        outputs: ['id' => new Selector(null, '$.foo', ExpressionType::JsonPath)],
    );

    $workflow = new Workflow('test_wf', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), []);
    $context = new WorkflowContext('def_1', [], ['step1' => []], [], 'test_wf', 'exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);
});
