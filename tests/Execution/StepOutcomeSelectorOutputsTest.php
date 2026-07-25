<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ExpressionType;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Execution\StepOutcomeHandler;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Contracts\ExecutionRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\SubWorkflowInvoker;
use Alama\LaravelArazzo\Resolution\SelectorEvaluator;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;

it('resolves a Selector output through SelectorEvaluator', function () {
    $selectors = Mockery::mock(SelectorEvaluator::class);
    $selectors->shouldReceive('evaluate')->once()->andReturn('bar');

    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('evaluate')->once();

    $store = Mockery::mock(StateStoreInterface::class);
    $store->shouldReceive('save');

    $analyzer = Mockery::mock(DependencyAnalyzer::class);
    $analyzer->shouldReceive('getRunnableSteps')->once()->andReturn([]);
    
    $pending = Mockery::mock(PendingCorrelationRegistryInterface::class);
    $pending->shouldReceive('existsForExecution')->once()->andReturn(false);

    $exec = Mockery::mock(ExecutionRegistryInterface::class);
    $exec->shouldReceive('complete')->once();
    
    $ledger = Mockery::mock(EventLedgerInterface::class);
    $ledger->shouldReceive('append')->once();

    $handler = new StepOutcomeHandler(
        Mockery::mock(QueueDriverInterface::class),
        $engine,
        $analyzer,
        $exec,
        $ledger,
        $pending,
        Mockery::mock(ExpressionResolverInterface::class),
        $store,
        Mockery::mock(SubWorkflowInvoker::class),
        $selectors,
        Mockery::mock(ExpressionEvaluator::class)
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
        outputs: ['id' => new Selector(null, '$.foo', ExpressionType::JsonPath)]
    );

    $workflow = new Workflow('test_wf', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new \Alama\LaravelArazzo\Dto\Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), []);
    $context = new WorkflowContext('def_1', [], ['step1' => []], [], 'test_wf', 'exec_1');

    $handler->handle($document, $workflow, $step, $context, 'exec_1', true);
});
