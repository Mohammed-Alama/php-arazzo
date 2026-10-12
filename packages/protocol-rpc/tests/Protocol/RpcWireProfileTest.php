<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Protocol;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcWireProfile;

it('maps every rpcProtocol variant onto its wire profile', function (): void {
    expect(RpcWireProfile::fromProtocol(RpcProtocol::Grpc))->toBe(RpcWireProfile::Grpc);
    expect(RpcWireProfile::fromProtocol(RpcProtocol::GrpcWeb))->toBe(RpcWireProfile::GrpcWeb);
    expect(RpcWireProfile::fromProtocol(RpcProtocol::Twirp))->toBe(RpcWireProfile::Twirp);
    expect(RpcWireProfile::fromProtocol(RpcProtocol::Connect))->toBe(RpcWireProfile::Connect);
});

it('declares the canonical request Content-Type per variant', function (): void {
    expect(RpcWireProfile::Grpc->requestContentType())->toBe('application/grpc');
    expect(RpcWireProfile::GrpcWeb->requestContentType())->toBe('application/grpc-web+proto');
    expect(RpcWireProfile::Twirp->requestContentType())->toBe('application/protobuf');
    expect(RpcWireProfile::Connect->requestContentType())->toBe('application/connect+proto');
});

it('frames messages only on the grpc and grpc-web variants', function (): void {
    expect(RpcWireProfile::Grpc->framesMessages())->toBeTrue();
    expect(RpcWireProfile::GrpcWeb->framesMessages())->toBeTrue();
    expect(RpcWireProfile::Twirp->framesMessages())->toBeFalse();
    expect(RpcWireProfile::Connect->framesMessages())->toBeFalse();
});

it('reads an integer grpc status only for grpc and grpc-web', function (): void {
    expect(RpcWireProfile::Grpc->statusIsInteger())->toBeTrue();
    expect(RpcWireProfile::GrpcWeb->statusIsInteger())->toBeTrue();
    expect(RpcWireProfile::Twirp->statusIsInteger())->toBeFalse();
    expect(RpcWireProfile::Connect->statusIsInteger())->toBeFalse();
});

it('names the error-code wire home per variant', function (): void {
    expect(RpcWireProfile::Grpc->statusHeaders())->toBe(['grpc-status']);
    expect(RpcWireProfile::GrpcWeb->statusHeaders())->toBe(['grpc-status']);
    expect(RpcWireProfile::Twirp->statusHeaders())->toBe(['x-twirp-error']);
    expect(RpcWireProfile::Connect->statusHeaders())->toBe(['connect-error-code']);
});
