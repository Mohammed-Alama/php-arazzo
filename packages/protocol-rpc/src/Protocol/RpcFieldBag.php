<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Protocol;

/**
 * Single home of the message-field bag contract shared by the request builder
 * and the executor: normalises an `application/protobuf` requestBodies row
 * (produced by ProtoSourceNormalizer) into the wire-codec field shape. The
 * normalizer always emits the shape, so the fallbacks here are defensive for
 * hand-built or decorated operations.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcFieldBag
{
    /**
     * @return list<array{name: string, type: string, number: int, repeated: bool}>
     */
    public static function normalize(mixed $bag): array
    {
        if (!is_array($bag)) {
            return [];
        }

        $fields = [];

        foreach ($bag as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? null;
            $type = $row['type'] ?? null;
            $number = $row['number'] ?? null;
            $repeated = $row['repeated'] ?? null;

            $fields[] = [
                'name' => is_string($name) ? $name : '',
                'type' => is_string($type) ? $type : '',
                'number' => is_int($number) ? $number : 0,
                'repeated' => is_bool($repeated) ? $repeated : false,
            ];
        }

        return $fields;
    }
}
