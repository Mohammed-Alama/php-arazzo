<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\Sync\ExecutionGraphFactory;
use Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Tests\Support\FakeOpenApiExecutor;
use Alama\Arazzo\Tests\Support\FakeResponseValidator;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Response;
use Mockery;

require_once __DIR__.'/../Support/FakeSeams.php';

/**
 * Parent -> child document. The child fetches one operation and publishes its
 * id as a workflow output; the parent invokes it and re-exports that output.
 */
function subWorkflowDocument(): ArazzoDocument
{
    $child = new Workflow(
        workflowId: 'child',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: [
            StepFactory::http(
                'fetch',
                null,
                new StepFlow(),
                new StepIo(outputs: ['id' => new Expression('$response.body#/id')]),
                'get-order',
            ),
        ],
        successActions: [],
        failureActions: [],
        outputs: ['order_id' => new Expression('$steps.fetch.outputs.id')],
        parameters: [],
    );

    $parent = new Workflow(
        workflowId: 'parent',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: [
            StepFactory::workflow(
                'call-child',
                null,
                new StepFlow(),
                new StepIo(),
                'child',
            ),
        ],
        successActions: [],
        failureActions: [],
        outputs: ['order_id' => new Expression('$steps.call-child.outputs.order_id')],
        parameters: [],
    );

    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('sub-workflow', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('orders', 'https://api.test/openapi.json', SourceType::Openapi)],
        workflows: [$parent, $child],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function subWorkflowGraph(OperationExecutorRegistry $registry): WorkflowExecutor
{
    $document = subWorkflowDocument();

    $documents = Mockery::mock(DocumentInterface::class);
    $documents->shouldReceive('preflight')->andReturn(new ValidationResult($document, [], []));

    $resolver = Mockery::mock(OpenApiOperationResolver::class);
    $resolver->shouldReceive('resolve')->andReturn(new OpenApiOperationHandle(
        new ResolvedOperation(
            source: new SourceDescription('orders', 'https://api.test/openapi.json', SourceType::Openapi),
            normalized: new NormalizedOpenApiOperation('/orders', 'get', null, [], [], [], [], [], []),
            rpcProtocol: null,
            operationName: null,
            rpcMethod: null,
            graphqlOperation: null,
            interaction: null,
        ),
        new OpenApi([]),
        new Operation([]),
    ));

    $evaluation = new EvaluationEngine(expression: new ExpressionEngine());

    return (new ExecutionGraphFactory(
        new OperationRuntime($documents, $resolver),
        $evaluation,
        new FakeOpenApiExecutor(new Response(200, ['Content-Type' => 'application/json'], '{"id":"ord_1"}')),
        new StepOutputExtractor($resolver, $evaluation, new ExpressionEngine()),
        new FakeResponseValidator(),
    ))->createWorkflowExecutor($registry);
}

it('registers the sub-workflow executor on the synchronous graph, like the async assembler', function () {
    $registry = new OperationExecutorRegistry();

    subWorkflowGraph($registry);

    expect($registry->all())->toHaveCount(1)
        ->and($registry->all()[0])->toBeInstanceOf(SubWorkflowStepExecutor::class);
});

it('runs a parent -> child workflow synchronously', function () {
    $document = subWorkflowDocument();
    $result = subWorkflowGraph(new OperationExecutorRegistry())
        ->execute($document->workflows[0], $document, []);

    expect($result->status)->toBe('succeeded')
        ->and($result->stepResults)->toHaveKey('call-child')
        ->and($result->stepResults['call-child']->success)->toBeTrue()
        // The child's workflow output reaches the parent step through $outputs.
        ->and($result->stepResults['call-child']->outputs)->toBe(['order_id' => 'ord_1'])
        // ...and the parent workflow re-exports it through $steps.
        ->and($result->outputs)->toBe(['order_id' => 'ord_1'])
        ->and($result->workflowCallStack)->toBe(['parent']);
});
