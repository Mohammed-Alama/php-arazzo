<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Laravel\RpcServiceProvider;
use Alama\Arazzo\Laravel\SoapServiceProvider;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Rpc\Execution\RpcOperationExecutor;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use Alama\Arazzo\Protocol\Rpc\Protocol\ProtobufOperationResolver;
use Alama\Arazzo\Protocol\Rpc\Registrar\RpcRegistrar;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransfer;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * F5.2: the RPC vertical slice wires into the composed app exactly like the
 * HTTP/SOAP slices — value seams, the plugin tag vocabulary, and the shared
 * D/E registries (union of the registered protocols), plus the `.proto`
 * fixture and a container-resolved end-to-end execute per wire variant against
 * a fixture transport.
 *
 * The provider is registered at runtime (not via the package's
 * `extra.laravel.providers`, so the HTTP-only test app keeps its counts) to
 * prove it composes on top of ProtocolHttpServiceProvider.
 */
beforeEach(function (): void {
    $this->app->register(RpcServiceProvider::class);
});

function rpcProviderFixture(): string
{
    return __DIR__.'/../../protocol-rpc/tests/Fixtures/calculator.proto';
}

function rpcProviderDocument(RpcProtocol $variant): ArazzoDocument
{
    $step = new Step(
        stepId: 's1',
        description: null,
        target: StepTarget::rpc('$sourceDescriptions.calculator.CalculatorService/Add', $variant),
        flow: new StepFlow(),
        io: new StepIo(parameters: [
            new Parameter('url', null, 'http://localhost:9090'),
            new Parameter('a', null, 5),
            new Parameter('b', null, 3),
        ]),
    );

    $workflow = new Workflow(
        workflowId: 'wf-1',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: [],
        parameters: [],
    );

    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Calc', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('calculator', rpcProviderFixture(), SourceType::Protobuf)],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function rpcProviderClient(ResponseInterface $response): HttpClientInterface
{
    return new class($response) implements HttpClientInterface
    {
        /** @var list<RequestInterface> */
        public array $requests = [];

        public function __construct(private readonly ResponseInterface $response) {}

        public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
        {
            $this->requests[] = $request;

            return $this->response;
        }
    };
}

/**
 * The framed request body adds the 5-byte gRPC prefix; twirp/connect send the
 * raw protobuf message ("\x08\x05\x10\x03" = a=5(int32,1), b=3(int32,2)).
 */
function rpcProviderRequestBody(RpcProtocol $variant): string
{
    $message = "\x08\x05\x10\x03";

    return match ($variant) {
        RpcProtocol::Grpc, RpcProtocol::GrpcWeb => "\x00\x00\x00\x00\x04".$message,
        default => $message,
    };
}

/**
 * @return array<string, string>
 */
function rpcProviderResponseHeaders(RpcProtocol $variant): array
{
    return match ($variant) {
        RpcProtocol::Grpc => ['Content-Type' => 'application/grpc', 'Grpc-Status' => '0'],
        RpcProtocol::GrpcWeb => ['Content-Type' => 'application/grpc-web+proto', 'Grpc-Status' => '0'],
        RpcProtocol::Twirp => ['Content-Type' => 'application/protobuf'],
        RpcProtocol::Connect => ['Content-Type' => 'application/connect+proto'],
    };
}

/**
 * AddResponse{result int32 = 1} with result=8 ("\x08\x08"), framed for the
 * grpc styles.
 */
function rpcProviderResponseBody(RpcProtocol $variant): string
{
    return match ($variant) {
        RpcProtocol::Grpc, RpcProtocol::GrpcWeb => "\x00\x00\x00\x00\x02\x08\x08",
        default => "\x08\x08",
    };
}

function rpcProviderExpectedContentType(RpcProtocol $variant): string
{
    return match ($variant) {
        RpcProtocol::Grpc => 'application/grpc',
        RpcProtocol::GrpcWeb => 'application/grpc-web+proto',
        RpcProtocol::Twirp => 'application/protobuf',
        RpcProtocol::Connect => 'application/connect+proto',
    };
}

it('binds the RPC value seams to their protocol-rpc implementations', function (): void {
    expect(app(RpcOperationExecutor::class))->toBeInstanceOf(RpcOperationExecutor::class)
        ->and(app(RpcRegistrar::class))->toBeInstanceOf(RpcRegistrar::class)
        ->and(app(ProtoSourceNormalizer::class))->toBeInstanceOf(ProtoSourceNormalizer::class)
        ->and(app(ProtobufOperationResolver::class))->toBeInstanceOf(ProtobufOperationResolver::class);
});

it('tags the RPC services under the configured plugin vocabulary', function (): void {
    $parentTag = config('arazzo.plugins.tags.0');
    $rpcTag = config('arazzo.plugins.rpc_operation_executor');

    expect($parentTag)->toBe('arazzo.plugins.*')
        ->and($rpcTag)->toBe('arazzo.plugins.rpc-operation-executor');

    $parent = array_map(
        static fn (object $service): string => $service::class,
        iterator_to_array(app()->tagged($parentTag), false),
    );
    $executors = iterator_to_array(app()->tagged($rpcTag), false);

    expect($parent)
        ->toContain(ProtoSourceNormalizer::class)
        ->toContain(RpcOperationExecutor::class)
        ->and($executors)->toHaveCount(1)
        ->and($executors[0])->toBeInstanceOf(RpcOperationExecutor::class);
});

it('coexists with protocol-http in the shared capability registries', function (): void {
    $plugins = app(OperationExecutorRegistry::class)->all();

    expect($plugins)->toHaveCount(3)
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof RpcOperationExecutor))->toBeTrue()
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof HttpStepExecutor))->toBeTrue();

    $normalizers = app(SourceNormalizerRegistryInterface::class);

    expect($normalizers->get(SourceType::Protobuf))->toBeInstanceOf(ProtoSourceNormalizer::class)
        ->and($normalizers->get(SourceType::Openapi))->toBeInstanceOf(OpenApiSourceNormalizer::class);
});

it('composes with protocol-soap when RPC is registered last', function (): void {
    $this->app->register(SoapServiceProvider::class);
    // RPC must own the union callback (its provider is documented last in the
    // package's extra.laravel.providers); re-run register() to force it.
    (new RpcServiceProvider($this->app))->register();

    $plugins = app(OperationExecutorRegistry::class)->all();

    expect($plugins)->toHaveCount(4)
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof RpcOperationExecutor))->toBeTrue()
        ->and(collect($plugins)->contains(static fn (object $p): bool => $p instanceof HttpStepExecutor))->toBeTrue();

    $normalizers = app(SourceNormalizerRegistryInterface::class);

    expect($normalizers->get(SourceType::Protobuf))->toBeInstanceOf(ProtoSourceNormalizer::class);
});

it('routes protobuf steps and executes end-to-end per wire variant', function (
    RpcProtocol $variant,
    int $expectedStatus,
): void {
    $client = rpcProviderClient(new Response(200, rpcProviderResponseHeaders($variant), rpcProviderResponseBody($variant)));
    app()->instance(HttpClientInterface::class, $client);

    $document = rpcProviderDocument($variant);
    $step = $document->workflows[0]->steps[0];

    // Protocol routing happens at the shared registry (what UnifiedStepCarrier
    // drives): the container-built union must resolve a protobuf step to the
    // RPC executor, not the HTTP executor.
    $executor = app(OperationExecutorRegistry::class)->resolve($step, $document);

    expect($executor)->toBeInstanceOf(RpcOperationExecutor::class);

    $outcome = $executor->execute(
        $step,
        new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'),
        $document,
        'exec-1',
    );

    expect($outcome->failureCategory)->toBeNull()
        ->and($outcome->statusCode)->toBe($expectedStatus)
        ->and($client->requests)->toHaveCount(1)
        ->and($client->requests[0]->getMethod())->toBe('POST')
        ->and((string) $client->requests[0]->getUri())->toBe('http://localhost:9090')
        ->and($client->requests[0]->getHeaderLine('Content-Type'))->toBe(rpcProviderExpectedContentType($variant))
        ->and((string) $client->requests[0]->getBody())->toBe(rpcProviderRequestBody($variant))
        ->and($outcome->responseBody['transfer'])->toBeInstanceOf(RpcResponseTransfer::class)
        ->and($outcome->responseBody['transfer']->view('proto'))->toBe(['result' => 8]);
})->with([
    'grpc' => [RpcProtocol::Grpc, 0],
    'grpc-web' => [RpcProtocol::GrpcWeb, 0],
    'twirp' => [RpcProtocol::Twirp, 200],
    'connect' => [RpcProtocol::Connect, 200],
]);
