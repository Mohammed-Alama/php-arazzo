<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;

it('lists the RPC wire protocols')
    ->expect(RpcProtocol::cases())
    ->toBe(
        [
            RpcProtocol::Grpc,
            RpcProtocol::GrpcWeb,
            RpcProtocol::Twirp,
            RpcProtocol::Connect,
        ],
    );

it('uses spec-exact string values')
    ->expect(RpcProtocol::Grpc->value)->toBe('grpc')
    ->and(RpcProtocol::GrpcWeb->value)->toBe('grpc-web')
    ->and(RpcProtocol::Twirp->value)->toBe('twirp')
    ->and(RpcProtocol::Connect->value)->toBe('connect');
