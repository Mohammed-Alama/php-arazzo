<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Execution\Contracts\EventLedgerInterface;
use Alama\Arazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\Engine;
use Alama\Arazzo\Execution\ExpressionEvaluator;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\SubWorkflowInvoker;
use Alama\Arazzo\Execution\WorkflowContext;
use Alama\Arazzo\Resolution\SelectorEvaluator;

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
