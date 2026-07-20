<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\HttpStepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpStepExecutorMockResolver implements ExpressionResolverInterface
{
    public ?WorkflowContext $lastContextSeenByExtractOutputs = null;

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return new Request('GET', 'http://localhost/thing');
    }

    /** @return array<string, mixed> */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        $this->lastContextSeenByExtractOutputs = $context;

        return ['echoedBody' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}

class HttpStepExecutorMockClient implements HttpClientInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

function httpStepExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
}

it('supports a step with no action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockClient(new Response(200)), new HttpStepExecutorMockResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeTrue();
});

it('does not support a step with an action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockClient(new Response(200)), new HttpStepExecutorMockResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], [], 'send');

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeFalse();
});

it('executes the request and returns a resolved outcome with statusCode/outputs/body', function (): void {
    $response = new Response(201, [], json_encode(['id' => 42]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($client, $resolver);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(201);
    expect($outcome->responseBody)->toBe(['id' => 42]);
    expect($outcome->outputs)->toBe(['echoedBody' => ['id' => 42]]);
});

it('stores the response on the context before calling extractOutputs, fixing the stale-context ordering bug', function (): void {
    $response = new Response(200, [], json_encode(['x' => 1]));
    $client = new HttpStepExecutorMockClient($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($client, $resolver);

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($resolver->lastContextSeenByExtractOutputs->getSteps()['s1']['response']['body'])->toBe(['x' => 1]);
});
