<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Transfer;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use Alama\Arazzo\Protocol\Rpc\Transfer\RpcResponseTransferMapper;
use GuzzleHttp\Psr7\Response;

/**
 * @return list<array{name: string, type: string, number: int, repeated: bool}>
 */
function addResultFields(): array
{
    return [['name' => 'result', 'type' => 'int32', 'number' => 1, 'repeated' => false]];
}

function rpcAddResultBody(int $result, bool $framed): string
{
    $message = (new RpcMessageCodec())->encodeBinary(addResultFields(), ['result' => $result]);

    return $framed ? (new RpcMessageCodec())->frame($message) : $message;
}

function rpcResponseMapper(): RpcResponseTransferMapper
{
    return new RpcResponseTransferMapper(new RpcMessageCodec());
}

it('maps a grpc success response to an integer status and a decoded proto view', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['Grpc-Status' => '0', 'Content-Type' => 'application/grpc'], rpcAddResultBody(5, true)),
        RpcProtocol::Grpc,
        addResultFields(),
    );

    expect($transfer->status())->toBe(0);
    expect($transfer->hasView('proto'))->toBeTrue();
    expect($transfer->view('proto'))->toBe(['result' => 5]);
    expect($transfer->meta())->toBe([]);
});

it('maps a grpc failure to the integer error code and message detail', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['Grpc-Status' => '13', 'Grpc-Message' => 'invalid argument'], "\x00\x00\x00\x00\x00"),
        RpcProtocol::Grpc,
        addResultFields(),
    );

    expect($transfer->status())->toBe(13);
    expect($transfer->meta())->toMatchArray(['rpc.error' => 13, 'rpc.message' => 'invalid argument']);
});

it('reads grpc-web status as an integer from the same grpc-status home', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['GRPC-STATUS' => '0'], rpcAddResultBody(8, true)),
        RpcProtocol::GrpcWeb,
        addResultFields(),
    );

    expect($transfer->status())->toBe(0);
    expect($transfer->view('proto'))->toBe(['result' => 8]);
});

it('maps a twirp success without an error header to the HTTP status', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['Content-Type' => 'application/protobuf'], rpcAddResultBody(5, false)),
        RpcProtocol::Twirp,
        addResultFields(),
    );

    expect($transfer->status())->toBe(200);
    expect($transfer->view('proto'))->toBe(['result' => 5]);
    expect($transfer->meta())->toBe([]);
});

it('maps a twirp failure to the string X-Twirp-Error code', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(404, ['X-Twirp-Error' => 'not_found'], 'NotFound: route does not exist'),
        RpcProtocol::Twirp,
        addResultFields(),
    );

    expect($transfer->status())->toBe('not_found');
    expect($transfer->meta())->toMatchArray(['rpc.error' => 'not_found', 'rpc.message' => 'NotFound: route does not exist']);
    expect($transfer->hasView('proto'))->toBeFalse();
});

it('maps a connect failure to the string Connect-Error-Code', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(500, ['Connect-Error-Code' => 'unavailable'], ''),
        RpcProtocol::Connect,
        addResultFields(),
    );

    expect($transfer->status())->toBe('unavailable');
    expect($transfer->meta())->toMatchArray(['rpc.error' => 'unavailable']);
});

it('decodes a JSON content-type body into a json view', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['Content-Type' => 'application/json'], '{"result":5}'),
        RpcProtocol::Twirp,
        addResultFields(),
    );

    expect($transfer->hasView('json'))->toBeTrue();
    expect($transfer->view('json'))->toBe(['result' => 5]);
});

it('flags a non-2xx HTTP status without an RPC code as an error', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(503, [], ''),
        RpcProtocol::Twirp,
        addResultFields(),
    );

    expect($transfer->status())->toBe(503);
    expect($transfer->meta())->toMatchArray(['error' => 'HTTP 503']);
});

it('flags a body that cannot be unframed as an error', function (): void {
    $transfer = rpcResponseMapper()->map(
        new Response(200, ['Grpc-Status' => '0'], "\x00\x00\x00\x10"),
        RpcProtocol::Grpc,
        addResultFields(),
    );

    expect($transfer->meta())->toHaveKey('error');
});
