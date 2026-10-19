<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcRequestBuilder;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\RequestInterface;

class RpcBuilderMockEngine implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $expression->raw;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return null;
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return null;
    }

    public function supportedXPathVersions(): array
    {
        return [];
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $value;
    }

    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        return $value;
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return $body;
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return null;
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return null;
    }
}

/**
 * @return list<array{name: string, type: string, number: int, repeated: bool}>
 */
function rpcBuilderAddFields(): array
{
    return [
        ['name' => 'a', 'type' => 'int32', 'number' => 1, 'repeated' => false],
        ['name' => 'b', 'type' => 'int32', 'number' => 2, 'repeated' => false],
    ];
}

function rpcBuilderResolvedOperation(
    RpcProtocol $protocol = RpcProtocol::Grpc,
    array $requestFields = [],
    string $serverUrl = 'http://localhost:9090',
): ResolvedOperation {
    return new ResolvedOperation(
        source: new SourceDescription('calculator', 'calculator.proto', SourceType::Protobuf),
        normalized: new NormalizedOpenApiOperation(
            path: 'CalculatorService/Add',
            method: 'POST',
            resolvedServerUrl: $serverUrl,
            pathParameters: [],
            queryParameters: [],
            headerParameters: [],
            cookieParameters: [],
            requestBodies: ['application/protobuf' => [
                'requestFields' => $requestFields,
                'responseFields' => [],
            ]],
            responses: [],
        ),
        rpcProtocol: $protocol,
    );
}

function rpcBuilder(): RpcRequestBuilder
{
    $psr17 = new HttpFactory();

    return new RpcRequestBuilder(
        requestFactory: $psr17,
        streamFactory: $psr17,
        evaluationEngine: new RpcBuilderMockEngine(),
        codec: new RpcMessageCodec(),
    );
}

function rpcBuilderStep(array $parameters = []): Step
{
    return new Step('add', null, StepTarget::rpc('$sourceDescriptions.calculator.CalculatorService/Add', RpcProtocol::Grpc), new StepFlow(), new StepIo(parameters: $parameters));
}

function rpcBuilderContext(): WorkflowContext
{
    return new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');
}

function rpcBuilderDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [new SourceDescription('calculator', 'calculator.proto', SourceType::Protobuf)],
        [],
        new Components([], [], [], []),
        [],
    );
}

/**
 * @return array{0: RequestInterface}
 */
function rpcBuilderBuild(array $parameters, RpcProtocol $protocol = RpcProtocol::Grpc, string $serverUrl = 'http://localhost:9090', ?array $fields = null): array
{
    $request = rpcBuilder()->build(
        rpcBuilderStep($parameters),
        rpcBuilderContext(),
        rpcBuilderDocument(),
        rpcBuilderResolvedOperation($protocol, $fields ?? rpcBuilderAddFields(), $serverUrl),
        'exec-1',
    );

    return [$request];
}

it('uses the canonical Content-Type for every wire variant', function (): void {
    expect(rpcBuilderBuild([], RpcProtocol::Grpc)[0]->getHeaderLine('Content-Type'))->toBe('application/grpc');
    expect(rpcBuilderBuild([], RpcProtocol::GrpcWeb)[0]->getHeaderLine('Content-Type'))->toBe('application/grpc-web+proto');
    expect(rpcBuilderBuild([], RpcProtocol::Twirp)[0]->getHeaderLine('Content-Type'))->toBe('application/protobuf');
    expect(rpcBuilderBuild([], RpcProtocol::Connect)[0]->getHeaderLine('Content-Type'))->toBe('application/connect+proto');
});

it('frames grpc/grpc-web bodies and sends raw protobuf for twirp/connect', function (): void {
    $parameters = [new Parameter('a', null, 5), new Parameter('b', null, 3)];

    expect((string) rpcBuilderBuild($parameters, RpcProtocol::Grpc)[0]->getBody())->toBe("\x00\x00\x00\x00\x04\x08\x05\x10\x03");
    expect((string) rpcBuilderBuild($parameters, RpcProtocol::GrpcWeb)[0]->getBody())->toBe("\x00\x00\x00\x00\x04\x08\x05\x10\x03");
    expect((string) rpcBuilderBuild($parameters, RpcProtocol::Twirp)[0]->getBody())->toBe("\x08\x05\x10\x03");
    expect((string) rpcBuilderBuild($parameters, RpcProtocol::Connect)[0]->getBody())->toBe("\x08\x05\x10\x03");
});

it('prefers the url or endpoint override over the resolved server url', function (): void {
    [$url] = rpcBuilderBuild([new Parameter('url', null, 'http://elsewhere:9191')]);
    expect((string) $url->getUri())->toBe('http://elsewhere:9191');

    [$endpoint] = rpcBuilderBuild([new Parameter('endpoint', null, 'http://elsewhere:9292')], serverUrl: 'http://localhost:9090');
    expect((string) $endpoint->getUri())->toBe('http://elsewhere:9292');

    [$fallback] = rpcBuilderBuild([]);
    expect((string) $fallback->getUri())->toBe('http://localhost:9090');
});

it('routes in:metadata parameters to request headers and keeps them out of the body', function (): void {
    [$request] = rpcBuilderBuild([
        new Parameter('authorization', ParameterIn::Metadata, 'Bearer xyz'),
        new Parameter('trace-id', ParameterIn::Metadata, 't-1'),
        new Parameter('a', null, 5),
        new Parameter('b', null, 3),
    ]);

    expect($request->getHeaderLine('authorization'))->toBe('Bearer xyz');
    expect($request->getHeaderLine('trace-id'))->toBe('t-1');
    expect((string) $request->getBody())->toBe("\x00\x00\x00\x00\x04\x08\x05\x10\x03");
});

it('throws when the resolved step has no rpcProtocol, naming the four variants', function (): void {
    $resolved = rpcBuilderResolvedOperation(RpcProtocol::Grpc);

    rpcBuilder()->build(
        rpcBuilderStep(),
        rpcBuilderContext(),
        rpcBuilderDocument(),
        new ResolvedOperation(source: $resolved->source, normalized: $resolved->normalized, rpcProtocol: null),
        'exec-1',
    );
})->throws(ExecutionException::class, 'grpc');

it('throws when the operation declares no application/protobuf body', function (): void {
    $resolved = rpcBuilderResolvedOperation(RpcProtocol::Grpc, [], 'http://localhost:9090');

    rpcBuilder()->build(
        rpcBuilderStep(),
        rpcBuilderContext(),
        rpcBuilderDocument(),
        new ResolvedOperation(
            source: $resolved->source,
            normalized: new NormalizedOpenApiOperation(
                path: 'CalculatorService/Add',
                method: 'POST',
                resolvedServerUrl: 'http://localhost:9090',
                pathParameters: [],
                queryParameters: [],
                headerParameters: [],
                cookieParameters: [],
                requestBodies: [],
                responses: [],
            ),
            rpcProtocol: RpcProtocol::Grpc,
        ),
        'exec-1',
    );
})->throws(ExecutionException::class, 'application/protobuf');

it('throws when no endpoint can be resolved', function (): void {
    $resolved = rpcBuilderResolvedOperation(RpcProtocol::Grpc, rpcBuilderAddFields(), '');

    rpcBuilder()->build(
        rpcBuilderStep(),
        rpcBuilderContext(),
        rpcBuilderDocument(),
        $resolved,
        'exec-1',
    );
})->throws(ExecutionException::class, 'endpoint');
