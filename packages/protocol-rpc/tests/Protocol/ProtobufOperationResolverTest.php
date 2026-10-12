<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Protocol;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use Alama\Arazzo\Protocol\Rpc\Protocol\ProtobufOperationResolver;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;
use RuntimeException;

const RPC_FIXTURE = __DIR__.'/../Fixtures/calculator.proto';

function makeRpcOperationResolver(): ProtobufOperationResolver
{
    return new ProtobufOperationResolver(fetcher: new LocalFetcher(), normalizer: new ProtoSourceNormalizer());
}

function rpcSource(string $name, string $url, SourceType $type = SourceType::Protobuf): SourceDescription
{
    return new SourceDescription($name, $url, $type);
}

function rpcStepDocument(array $sources, string $rpcMethod, RpcProtocol $protocol): array
{
    $document = new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        $sources,
        [],
        new Components([], [], [], []),
        [],
    );

    $step = new Step('op', null, StepTarget::rpc($rpcMethod, $protocol), new StepFlow(), new StepIo());

    return [$document, $step];
}

it('supports dotted rpc steps against a protobuf source and rejects others', function (): void {
    $resolver = makeRpcOperationResolver();

    [$rpcDocument, $rpcStep] = rpcStepDocument(
        [rpcSource('calculator', RPC_FIXTURE)],
        '$sourceDescriptions.calculator.CalculatorService/Add',
        RpcProtocol::Grpc,
    );
    expect($resolver->supportsStep($rpcStep, $rpcDocument))->toBeTrue();

    [$openApiDocument, $openApiStep] = rpcStepDocument(
        [rpcSource('rest', 'openapi.yaml', SourceType::Openapi)],
        '$sourceDescriptions.rest.Orders/Get',
        RpcProtocol::Twirp,
    );
    expect($resolver->supportsStep($openApiStep, $openApiDocument))->toBeFalse();

    [$mixedDocument, $mixedStep] = rpcStepDocument(
        [rpcSource('calculator', RPC_FIXTURE)],
        'CalculatorService/Add',
        RpcProtocol::Grpc,
    );
    expect($resolver->supportsStep($mixedStep, $mixedDocument))->toBeFalse();
});

it('rejects action steps even when a protobuf source is described', function (): void {
    $resolver = makeRpcOperationResolver();
    $document = new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [rpcSource('calculator', RPC_FIXTURE)],
        [],
        new Components([], [], [], []),
        [],
    );
    $step = new Step('op', null, new StepTarget(action: 'send'), new StepFlow(), new StepIo());

    expect($resolver->supportsStep($step, $document))->toBeFalse();
});

it('resolves a dotted rpc reference into a ResolvedOperation carrying the step protocol', function (): void {
    $resolver = makeRpcOperationResolver();
    [$document, $step] = rpcStepDocument(
        [rpcSource('calculator', RPC_FIXTURE)],
        '$sourceDescriptions.calculator.CalculatorService/Add',
        RpcProtocol::Grpc,
    );

    $resolved = $resolver->resolve($step, $document);

    expect($resolved)->toBeInstanceOf(ResolvedOperation::class);
    expect($resolved->source->name)->toBe('calculator');
    expect($resolved->rpcProtocol)->toBe(RpcProtocol::Grpc);
    expect($resolved->rpcMethod)->toBe('CalculatorService/Add');
    expect($resolved->normalized->path)->toBe('CalculatorService/Add');
});

it('throws when the referenced method does not exist in the .proto', function (): void {
    $resolver = makeRpcOperationResolver();
    [$document, $step] = rpcStepDocument(
        [rpcSource('calculator', RPC_FIXTURE)],
        '$sourceDescriptions.calculator.CalculatorService/Nope',
        RpcProtocol::Grpc,
    );

    expect(fn () => $resolver->resolve($step, $document))->toThrow(RuntimeException::class, 'Nope');
});

it('treats a reference to an undescribed source as a non-reference, mirroring WSDL', function (): void {
    $resolver = makeRpcOperationResolver();
    [$document, $step] = rpcStepDocument(
        [rpcSource('calculator', RPC_FIXTURE)],
        '$sourceDescriptions.orders.CalculatorService/Add',
        RpcProtocol::Grpc,
    );

    expect($resolver->supportsStep($step, $document))->toBeFalse();
    expect(fn () => $resolver->resolve($step, $document))->toThrow(RuntimeException::class, 'does not reference a protobuf source');
});

it('throws when the step does not reference a protobuf source', function (): void {
    $resolver = makeRpcOperationResolver();
    [$document, $step] = rpcStepDocument(
        [rpcSource('rest', 'openapi.yaml', SourceType::Openapi)],
        '$sourceDescriptions.rest.Orders/Get',
        RpcProtocol::Connect,
    );

    expect(fn () => $resolver->resolve($step, $document))->toThrow(RuntimeException::class, 'protobuf');
});
