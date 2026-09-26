<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;

function makeResolvedOperation(?RpcProtocol $rpcProtocol = null, ?string $operationName = null, ?string $rpcMethod = null, ?GraphQlOperation $graphqlOperation = null, ?Interaction $interaction = null): ResolvedOperation
{
    $source = new SourceDescription('api', '/u', SourceType::Wsdl);
    $normalized = new NormalizedOpenApiOperation(
        path: '/pets',
        method: 'get',
        resolvedServerUrl: 'https://example.test',
        pathParameters: [],
        queryParameters: [],
        headerParameters: [],
        cookieParameters: [],
        requestBodies: [],
        responses: [],
    );

    return new ResolvedOperation(
        source: $source,
        normalized: $normalized,
        rpcProtocol: $rpcProtocol,
        operationName: $operationName,
        rpcMethod: $rpcMethod,
        graphqlOperation: $graphqlOperation,
        interaction: $interaction,
    );
}

it('keeps constructing with 2-arity for runner BC', function (): void {
    $source = new SourceDescription('api', '/u', SourceType::Openapi);
    $op = new ResolvedOperation(
        $source,
        new NormalizedOpenApiOperation('/', 'get', 'https://example.test', [], [], [], [], [], []),
    );

    expect($op->sourceType())->toBe(SourceType::Openapi);
    expect($op->binding())->toBe('http');
    expect($op->rpcProtocol)->toBeNull();
});

it('derives sourceType from the source description', function (): void {
    expect(makeResolvedOperation()->sourceType())->toBe(SourceType::Wsdl);
});

it('derives binding from rpcProtocol, then source type', function (): void {
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Grpc)->binding())->toBe('grpc');
    expect(makeResolvedOperation()->binding())->toBe('soap');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::GrpcWeb)->binding())->toBe('grpc-web');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Twirp)->binding())->toBe('twirp');
    expect(makeResolvedOperation(rpcProtocol: RpcProtocol::Connect)->binding())->toBe('connect');
});

it('carries the protocol-specific operation reference fields', function (): void {
    $op = makeResolvedOperation(
        rpcProtocol: RpcProtocol::Grpc,
        operationName: 'com.acme.PetService/GetPet',
        rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
        graphqlOperation: new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: 'query GetPet'),
        interaction: new Interaction(expectedPayload: 'Proceed'),
    );

    expect($op->operationName)->toBe('com.acme.PetService/GetPet');
    expect($op->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($op->graphqlOperation)->toBeInstanceOf(GraphQlOperation::class);
    expect($op->interaction?->expectedPayload)->toBe('Proceed');
});
