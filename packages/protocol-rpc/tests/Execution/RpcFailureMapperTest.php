<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Execution;

use Alama\Arazzo\Protocol\Rpc\Execution\RpcFailureMapper;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransfer;

function rpcFailedTransfer(int|string $status = 13, array $meta = ['rpc.error' => 13, 'rpc.message' => 'invalid']): RpcResponseTransfer
{
    return new RpcResponseTransfer(status: $status, headers: [], rawBody: '', views: [], meta: $meta);
}

it('maps an rpc.error transfer onto a failed rpc-error outcome', function (): void {
    $outcome = (new RpcFailureMapper())->map(rpcFailedTransfer());

    expect($outcome->failureCategory)->toBe('rpc-error');
    expect($outcome->statusCode)->toBe(13);
    expect($outcome->responseBody)->toMatchArray([
        'rpc.error' => 13,
        'rpc.message' => 'invalid',
    ]);
    expect($outcome->responseBody['transfer'])->toBeInstanceOf(RpcResponseTransfer::class);
});

it('keeps a string RPC code as a 500 outcome status', function (): void {
    $outcome = (new RpcFailureMapper())->map(rpcFailedTransfer('not_found', ['rpc.error' => 'not_found']));

    expect($outcome->failureCategory)->toBe('rpc-error');
    expect($outcome->statusCode)->toBe(500);
    expect($outcome->responseBody['rpc.error'])->toBe('not_found');
});

it('accepts the transport category for transport failures', function (): void {
    $outcome = (new RpcFailureMapper())->map(
        new RpcResponseTransfer(status: 503, headers: [], rawBody: '', views: [], meta: ['error' => 'HTTP 503']),
        'transport',
    );

    expect($outcome->failureCategory)->toBe('transport');
    expect($outcome->statusCode)->toBe(503);
    expect($outcome->responseBody)->toHaveKey('transfer');
    expect($outcome->responseBody)->not->toHaveKey('rpc.error');
});
