<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\AsyncApiStepExecutor;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AsyncApiExecutorMockClient implements HttpClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(202);
    }
}

class AsyncApiExecutorMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    /** @var list<array{correlationId: string, executionId: string, stepId: string, channelPath: string}> */
    public array $created = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void
    {
        $this->created[] = compact('correlationId', 'executionId', 'stepId', 'channelPath');
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void
    {
    }

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

class AsyncApiExecutorMockResolver implements ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        return new Request('POST', 'http://broker.local/publish');
    }

    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
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

function asyncApiExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
}

it('supports steps with action send or receive, not steps without an action', function (): void {
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $plainStep = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $sendStep = new Step('s2', null, null, null, null, [], null, [], [], [], [], [], 'send');
    $receiveStep = new Step('s3', null, null, null, null, [], null, [], [], [], [], [], 'receive');

    expect($executor->supports($plainStep, asyncApiExecutorDocument()))->toBeFalse();
    expect($executor->supports($sendStep, asyncApiExecutorDocument()))->toBeTrue();
    expect($executor->supports($receiveStep, asyncApiExecutorDocument()))->toBeTrue();
});

it('publishes and resolves immediately for action send', function (): void {
    $client = new AsyncApiExecutorMockClient();
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        $client,
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step('publish-ride', null, null, null, null, [], null, [], [], [], [], [], 'send');
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(202);
    expect($client->lastRequest)->not->toBeNull();
});

it('writes a PendingCorrelation and suspends for action receive', function (): void {
    $pendingCorrelations = new AsyncApiExecutorMockPendingCorrelations();
    $executor = new AsyncApiStepExecutor(
        $pendingCorrelations,
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step(
        'wait-for-ride', null, null, null, null, [], null, [], [], [], [], [],
        'receive', 'channels/rides/created', new Expression('{$inputs.correlationId}'),
    );
    $context = new WorkflowContext('def_1', ['correlationId' => 'corr_abc'], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeTrue();
    expect($pendingCorrelations->created)->toHaveCount(1);
    expect($pendingCorrelations->created[0]['correlationId'])->toBe('corr_abc');
    expect($pendingCorrelations->created[0]['executionId'])->toBe('exec_1');
    expect($pendingCorrelations->created[0]['stepId'])->toBe('wait-for-ride');
    expect($pendingCorrelations->created[0]['channelPath'])->toBe('channels/rides/created');
});

it('throws when a receive step has no correlationId expression', function (): void {
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
        new AsyncApiExecutorMockResolver(),
    );

    $step = new Step('wait', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/x', null);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    expect(fn () => $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1'))
        ->toThrow(\LogicException::class);
});
