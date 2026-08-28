<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Interfaces\HttpClientInterface;
use Alama\Arazzo\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\PendingCorrelation;
use Alama\Arazzo\Spec\RequestBody;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AsyncApiExecutorMockClient implements HttpClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(202);
    }
}

class AsyncApiExecutorMockPendingCorrelations implements PendingCorrelationRegistryInterface
{
    /** @var list<array{correlationId: string, executionId: string, stepId: string, channelPath: string}> */
    public array $created = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->created[] = compact('correlationId', 'executionId', 'stepId', 'channelPath');
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return null;
    }

    public function consume(string $correlationId): void {}

    public function existsForExecution(string $executionId): bool
    {
        return false;
    }
}

function asyncApiExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], null, SpecVersion::V1_1);
}

it('supports steps with action send or receive, not steps without an action', function (): void {
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
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
    $httpFactory = new HttpFactory();
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        $client,
        $httpFactory,
        $httpFactory,
        $httpFactory,
    );

    $step = new Step(
        'publish-ride', null, null, null, null, [], null, [], [], [], [], [],
        'send',
        'https://broker.local/publish/rides',
    );
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(202);
    expect($client->lastRequest)->not->toBeNull()
        ->and($client->lastRequest->getMethod())->toBe('POST')
        ->and((string) $client->lastRequest->getUri())->toBe('https://broker.local/publish/rides');
});

it('compiles parameters and requestBody replacements into the message', function (): void {
    $client = new AsyncApiExecutorMockClient();
    $httpFactory = new HttpFactory();
    $executor = new AsyncApiStepExecutor(
        new AsyncApiExecutorMockPendingCorrelations(),
        new ExpressionEvaluator(),
        $client,
        $httpFactory,
        $httpFactory,
        $httpFactory,
    );

    $step = new Step(
        'publish-order',
        null, null, null, null,
        [
            new Parameter('source', ParameterIn::Query, new Expression('{$inputs.origin}')),
            new Parameter('X-Trace', ParameterIn::Header, 'trace-123'),
        ],
        new RequestBody(
            'application/json',
            ['orderId' => 'old'],
            [new PayloadReplacement('/orderId', new Expression('{$inputs.orderId}'))],
        ),
        [], [], [], [], [],
        'send',
        'https://broker.local/publish/orders?apiVersion=2',
    );
    $context = new WorkflowContext('def_1', ['origin' => 'web', 'orderId' => 'ord_42'], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1');

    expect($outcome->statusCode)->toBe(202);
    expect($client->lastRequest)->not->toBeNull();

    $uri = $client->lastRequest->getUri();
    expect((string) $uri)->toContain('https://broker.local/publish/orders')
        ->and((string) $uri)->toContain('apiVersion=2')
        ->and((string) $uri)->toContain('source=web');

    $body = json_decode($client->lastRequest->getBody()->getContents(), true);
    expect($body)->toBe(['orderId' => 'ord_42'])
        ->and($client->lastRequest->getHeaderLine('X-Trace'))->toBe('trace-123');
});

it('writes a PendingCorrelation and suspends for action receive', function (): void {
    $pendingCorrelations = new AsyncApiExecutorMockPendingCorrelations();
    $executor = new AsyncApiStepExecutor(
        $pendingCorrelations,
        new ExpressionEvaluator(),
        new AsyncApiExecutorMockClient(),
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
    );

    $step = new Step('wait', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/x', null);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    expect(fn () => $executor->execute($step, $context, asyncApiExecutorDocument(), 'exec_1'))
        ->toThrow(\LogicException::class);
});
