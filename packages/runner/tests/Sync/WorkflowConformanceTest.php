<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Sync;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\Sync\ExecutionGraphFactory;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Tests\Support\FakeResponseValidator;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__.'/../Support/FakeSeams.php';

/**
 * @param  list<ResponseInterface>  $responses
 * @return array{0: WorkflowExecutor, 1: SequenceOpenApiExecutor, 2: ArazzoDocument}
 */
function confExecutor(array $workflows, array $responses = []): array
{
    $document = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('conf', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: $workflows,
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $documents = Mockery::mock(DocumentInterface::class);
    $documents->shouldReceive('preflight')->andReturn(new ValidationResult($document, [], []));

    $resolver = Mockery::mock(OpenApiOperationResolver::class);
    $resolver->shouldReceive('resolve')->andReturn(new OpenApiOperationHandle(
        new ResolvedOperation(
            source: new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
            normalized: new NormalizedOpenApiOperation('/x', 'get', null, [], [], [], [], [], []),
            rpcProtocol: null,
            operationName: null,
            rpcMethod: null,
            graphqlOperation: null,
            interaction: null,
        ),
        new OpenApi([]),
        new Operation([]),
    ));

    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $fake = new SequenceOpenApiExecutor($responses);

    $executor = (new ExecutionGraphFactory(
        new OperationRuntime($documents, $resolver),
        $engine,
        $fake,
        new StepOutputExtractor($resolver, $engine, new ExpressionEngine()),
        new FakeResponseValidator(),
    ))->createWorkflowExecutor(new OperationExecutorRegistry());

    return [$executor, $fake, $document];
}

/** @param  list<SuccessCriterion>  $criteria  @param  mixed[]  $onSuccess  @param  mixed[]  $onFailure  @param  array<string,Expression>  $outputs */
function confStep(string $id, string $opId, array $criteria = [], array $onSuccess = [], array $onFailure = [], array $outputs = []): Step
{
    return StepFactory::http(
        stepId: $id,
        description: null,
        flow: new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure),
        io: new StepIo(successCriteria: $criteria, outputs: $outputs),
        operationId: $opId,
    );
}

function confRetry(int $retryLimit, ?string $stepId = null, ?string $workflowId = null): RetryAction
{
    return new RetryAction('retry', 0.0, $retryLimit, $stepId, $workflowId, []);
}

/**
 * @internal test double returning queued PSR-7 responses, one per execute() call.
 */
final class SequenceOpenApiExecutor implements OpenApiExecutorInterface
{
    /** @var list<ResponseInterface> */
    public array $queue;

    public int $calls = 0;

    /** @param  list<ResponseInterface>  $queue */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function execute($operation, $payload, ?callable $requestInterceptor = null, ?float $timeoutSeconds = null): ResponseInterface
    {
        $this->calls++;
        $last = array_key_last($this->queue);

        return $this->queue[$this->calls - 1] ?? $this->queue[$last] ?? $this->queue[0] ?? new Response(200);
    }
}

describe('conformance: retry semantics', function () {
    it('S2: retryLimit 2 retries a flaky step until it succeeds (2 requests)', function () {
        $retry = confRetry(2);
        $step = confStep('fetch', 'get-item', [
            new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple),
        ], onFailure: [$retry]);

        $workflow = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(404),
            new Response(200, ['Content-Type' => 'application/json'], '{"id":"item_1"}'),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('succeeded')
            ->and($fake->calls)->toBe(2, 'expected 1 initial + 1 retry for a flaky step')
            ->and($result->stepResults)->toHaveKey('fetch');
    });

    it('S3: retry limit exhausted after 3 requests ends the workflow as failed', function () {
        $retry = confRetry(2);
        $step = confStep('fetch', 'get-item', [
            new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple),
        ], onFailure: [$retry]);

        $workflow = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(404),
            new Response(404),
            new Response(404),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('failed')
            ->and($fake->calls)->toBe(3, 'expected 1 initial + 2 retries before exhaustion');
    });
});

describe('conformance: onSuccess end/goto', function () {
    it('S4: onSuccess end skips intermediate steps', function () {
        $end = new SuccessEndAction('end', []);
        $stepA = confStep('a', 'op-a', [], [$end]);
        $stepB = confStep('b', 'op-b'); // must never run

        $workflow = new Workflow('wf', null, null, null, [], [$stepA, $stepB], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(200, ['Content-Type' => 'application/json'], '{"id":1}'),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('succeeded')
            ->and($fake->calls)->toBe(1, 'onSuccess end must stop after the first step')
            ->and($result->stepResults)->not->toHaveKey('b');
    });

    it('S5: onSuccess goto transfers to the target, skipping intermediate steps in sequence', function () {
        $goto = new SuccessGotoAction('goto', 'c', null, []);
        $stepA = confStep('a', 'op-a', [], [$goto]);
        // 'c' emits output 'x'; 'b' may only succeed once 'c' has run, so a
        // working goto must execute 'c' immediately after 'a' (intermediate
        // 'b' is deferred, not run before the target).
        $stepC = confStep('c', 'op-c', [], [], [], outputs: ['x' => new Expression('{$response.body#/x}')]);
        $stepB = confStep('b', 'op-b', [
            new SuccessCriterion(null, '{$steps.c.outputs.x} == "done"', CriterionType::Simple),
        ]);

        $workflow = new Workflow('wf', null, null, null, [], [$stepA, $stepB, $stepC], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(200, ['Content-Type' => 'application/json'], '{"x":"done"}'), // a
            new Response(200, ['Content-Type' => 'application/json'], '{"x":"done"}'), // c (goto target, runs immediately after a)
            new Response(200, ['Content-Type' => 'application/json'], '{"x":"done"}'), // b (runs after c)
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('succeeded')
            ->and($fake->calls)->toBe(3, 'goto target c runs before intermediate b')
            ->and($result->stepResults)->toHaveKey('a')
            ->and($result->stepResults)->toHaveKey('c')
            ->and($result->stepResults)->toHaveKey('b');
    });
});

describe('conformance: onFailure goto recovery', function () {
    it('S6: onFailure goto runs the recovery step and records its request', function () {
        $recover = confStep('recover', 'op-recover'); // 200 -> succeeds
        $goto = new FailureGotoAction('recover', 'recover', null, []);
        $stepA = confStep('a', 'op-a', [
            new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple),
        ], onFailure: [$goto]);

        $workflow = new Workflow('wf', null, null, null, [], [$stepA, $recover], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(404),
            new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('succeeded', 'recovery step handles the failure')
            ->and($fake->calls)->toBe(2, 'failing step request + recovery step request are recorded')
            ->and($result->stepResults)->toHaveKey('a')
            ->and($result->stepResults)->toHaveKey('recover');
    });
});

describe('conformance: failure short-circuit', function () {
    it('S1: a failed step short-circuits the workflow; nothing runs after failure', function () {
        $stepA = confStep('a', 'op-a', [
            new SuccessCriterion(null, '$statusCode == 200', CriterionType::Simple),
        ]); // no onFailure -> a 404 fails the step
        $stepB = confStep('b', 'op-b'); // must never run

        $workflow = new Workflow('wf', null, null, null, [], [$stepA, $stepB], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(404),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('failed')
            ->and($fake->calls)->toBe(1, 'only the failing step is called')
            ->and($result->stepResults)->toHaveKey('a')
            ->and($result->stepResults)->not->toHaveKey('b');
    });
});

describe('conformance: success criteria', function () {
    it('S7: regex and body-comparison (jsonpath) criteria evaluate against the response body', function () {
        $step = confStep('fetch', 'op-item', [
            new SuccessCriterion('{$response.body}', '"item_1"', CriterionType::Regex),
            new SuccessCriterion(null, '$.id', CriterionType::JsonPath),
        ]);

        $workflow = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(200, ['Content-Type' => 'application/json'], '{"id":"item_1"}'),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('succeeded')
            ->and($fake->calls)->toBe(1);
    });

    it('S7: a non-matching regex criterion fails the step and the workflow', function () {
        $step = confStep('fetch', 'op-item', [
            new SuccessCriterion('{$response.body}', 'nope', CriterionType::Regex),
        ]);

        $workflow = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
        [$executor, $fake, $document] = confExecutor([$workflow], [
            new Response(200, ['Content-Type' => 'application/json'], '{"id":"item_1"}'),
        ]);

        $result = $executor->execute($workflow, $document, []);

        expect($result->status)->toBe('failed')
            ->and($fake->calls)->toBe(1);
    });
});

describe('conformance: workflowId child invocation', function () {
    it('S8: workflowId step runs the child and exposes outputs as $steps.<step>.outputs', function () {
        $child = new Workflow(
            workflowId: 'child',
            summary: null,
            description: null,
            inputs: null,
            dependsOn: [],
            steps: [
                StepFactory::http(
                    'fetch', null, new StepFlow(),
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
                    'call-child', null, new StepFlow(),
                    new StepIo(outputs: ['order_id' => new Expression('$steps.call-child.outputs.order_id')]),
                    'child',
                ),
            ],
            successActions: [],
            failureActions: [],
            outputs: ['order_id' => new Expression('$steps.call-child.outputs.order_id')],
            parameters: [],
        );

        [$executor, $fake, $document] = confExecutor([$parent, $child], [
            new Response(200, ['Content-Type' => 'application/json'], '{"id":"ord_1"}'),
        ]);

        $result = $executor->execute($parent, $document, []);

        expect($result->status)->toBe('succeeded')
            ->and($fake->calls)->toBe(1, 'only the child HTTP operation is called')
            ->and($result->stepResults)->toHaveKey('call-child')
            ->and($result->stepResults['call-child']->success)->toBeTrue()
            ->and($result->stepResults['call-child']->outputs)->toBe(['order_id' => 'ord_1'])
            ->and($result->outputs)->toBe(['order_id' => 'ord_1']);
    });
});
