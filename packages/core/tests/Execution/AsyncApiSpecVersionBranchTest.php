<?php

declare(strict_types=1);

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\State\Interfaces\PendingCorrelationRegistryInterface;

it('rejects async fields on 1.0 doc at execution', function () {
    $executor = new AsyncApiStepExecutor(
        Mockery::mock(PendingCorrelationRegistryInterface::class),
        Mockery::mock(ExpressionEvaluator::class),
        Mockery::mock(HttpClientInterface::class),
    );

    $step = new Step(
        stepId: 'step1',
        description: 'Test step',
        operationId: null,
        operationPath: null,
        workflowId: null,
        action: 'receive',
        channelPath: 'test/channel',
        correlationId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $document = new ArazzoDocument('1.0.0', new Info('Title', null, null, '1.0.0'), [], [], new Components([], [], [], []), [], null, SpecVersion::V1_0);
    $context = new WorkflowContext('def_1', [], [], [], 'test_wf', 'exec_1');

    $executor->execute($step, $context, $document, 'exec_1');
})->throws(LogicException::class, "AsyncAPI step 'step1' encountered under a 1.0.0 document; upgrade to arazzo: 1.1.0.");

it('accepts async fields on 1.1 doc', function () {
    $pending = Mockery::mock(PendingCorrelationRegistryInterface::class);
    $pending->shouldReceive('create')->with('corr-1', 'exec_1', 'step1', 'test/channel', null)->once();

    $evaluator = Mockery::mock(ExpressionEvaluator::class);
    $evaluator->shouldReceive('evaluate')->once()->andReturn('corr-1');

    $executor = new AsyncApiStepExecutor(
        $pending,
        $evaluator,
        Mockery::mock(HttpClientInterface::class),
    );

    $step = new Step(
        stepId: 'step1',
        description: 'Test step',
        operationId: null,
        operationPath: null,
        workflowId: null,
        action: 'receive',
        channelPath: 'test/channel',
        correlationId: new Expression('$.foo'),
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $document = new ArazzoDocument('1.0.0', new Info('Title', null, null, '1.1.0'), [], [], new Components([], [], [], []), [], null, SpecVersion::V1_1);
    $context = new WorkflowContext('def_1', [], [], [], 'test_wf', 'exec_1');

    $executor->execute($step, $context, $document, 'exec_1');
});
