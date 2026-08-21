<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Context\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Execution\Engine;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\SubWorkflowInvoker;
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

    $handler = new StepOutcomeHandler(
        Mockery::mock(QueueDriverInterface::class),
        $engine,

        $exec,
        $ledger,
        $pending,
        Mockery::mock(ExpressionResolverInterface::class),
        $store,
        Mockery::mock(SubWorkflowInvoker::class),
        $selectors,
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
        onSuccess: [],
        onFailure: [],
        outputs: ['id' => new Selector(null, '$.foo', ExpressionType::JsonPath)],
    );

    $workflow = new Workflow('test_wf', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), []);
    $context = new WorkflowContext('def_1', [], ['step1' => []], [], 'test_wf', 'exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);
});
