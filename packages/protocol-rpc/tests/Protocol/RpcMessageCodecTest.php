<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Protocol;

use Alama\Arazzo\Protocol\Rpc\Protocol\RpcMessageCodec;
use RuntimeException;

/**
 * @return list<array{name: string, type: string, number: int, repeated: bool}>
 */
function adderFields(): array
{
    return [
        ['name' => 'a', 'type' => 'int32', 'number' => 1, 'repeated' => false],
        ['name' => 'b', 'type' => 'int32', 'number' => 2, 'repeated' => false],
    ];
}

it('encodes an int32 message to the expected protobuf bytes', function (): void {
    $codec = new RpcMessageCodec();

    expect(bin2hex($codec->encodeBinary(adderFields(), ['a' => 5, 'b' => 3])))->toBe('08051003');
});

it('round-trips a scalar message through binary encode and decode', function (): void {
    $fields = [
        ['name' => 'a', 'type' => 'int32', 'number' => 1, 'repeated' => false],
        ['name' => 'b', 'type' => 'sint64', 'number' => 2, 'repeated' => false],
        ['name' => 'label', 'type' => 'string', 'number' => 3, 'repeated' => false],
        ['name' => 'ratio', 'type' => 'double', 'number' => 4, 'repeated' => false],
        ['name' => 'ok', 'type' => 'bool', 'number' => 5, 'repeated' => false],
        ['name' => 'score', 'type' => 'float', 'number' => 6, 'repeated' => false],
    ];
    $values = ['a' => -1, 'b' => -3, 'label' => 'hi', 'ratio' => 2.5, 'ok' => true, 'score' => 1.5];

    $codec = new RpcMessageCodec();
    $decoded = $codec->decodeBinary($fields, $codec->encodeBinary($fields, $values));

    expect($decoded)->toMatchArray(['a' => -1, 'b' => -3, 'label' => 'hi', 'ok' => true]);
    expect($decoded['ratio'])->toBe(2.5);
    expect($decoded['score'])->toBe(1.5);
});

it('encodes repeated fields as keyed repetitions and decodes them into lists', function (): void {
    $fields = [
        ['name' => 'ids', 'type' => 'int32', 'number' => 1, 'repeated' => true],
    ];

    $codec = new RpcMessageCodec();
    $encoded = $codec->encodeBinary($fields, ['ids' => [7, 8, 9]]);

    expect(bin2hex($encoded))->toBe('080708080809');
    expect($codec->decodeBinary($fields, $encoded))->toBe(['ids' => [7, 8, 9]]);
});

it('skips unknown fields and unknown wire types are tolerated via skip', function (): void {
    $fields = [['name' => 'a', 'type' => 'int32', 'number' => 1, 'repeated' => false]];

    // Field 99 (len-delimited) interleaved, plus -1 as int32 → only `a` materialises.
    $bytes = "\x08\x05\x9a\x06\x03abc\x10\x03";

    $codec = new RpcMessageCodec();

    expect($codec->decodeBinary($fields, $bytes))->toBe(['a' => 5]);
});

it('frames and unframes messages with the grpc 5-byte prefix', function (): void {
    $codec = new RpcMessageCodec();
    $message = "\x08\x05\x10\x03";

    $framed = $codec->frame($message);
    expect(bin2hex($framed))->toBe('000000000408051003');
    expect($codec->unframe($framed))->toBe($message);
    expect($codec->unframe(''))->toBeNull();
    expect($codec->unframe("\x00\x00\x00\x00"))->toBeNull();
    // compressed frames (flag != 0) are refused
    expect($codec->unframe("\x01\x00\x00\x00\x00"))->toBeNull();
});

it('throws when encoding an unsupported field type', function (): void {
    $fields = [
        ['name' => 'nested', 'type' => 'AddRequest', 'number' => 1, 'repeated' => false],
    ];

    (new RpcMessageCodec())->encodeBinary($fields, ['nested' => 'x']);
})->throws(RuntimeException::class, 'AddRequest');
