<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Protocol;

use RuntimeException;

/**
 * Minimal protobuf wire codec (plan F5.1): binary encode/decode of scalar
 * message fields plus the 5-byte gRPC message framing used by the
 * grpc/grpc-web variants.
 *
 * Supports the scalar field types the parsed `.proto` fixture exercises —
 * int32/uint32/int64/uint64/sint32/sint64/bool/string/bytes/double/float —
 * with `repeated` cardinality. Encoding an unsupported type throws rather
 * than emit wrong bytes; decoding skips unknown fields and nested-message
 * fields (standard protobuf "skip unknown" behaviour). Both grpc styles use
 * flag byte 0 (uncompressed) — a non-zero flag short-circuits unframing.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcMessageCodec
{
    private const SCALAR_TYPES = [
        'int32', 'int64', 'uint32', 'uint64', 'sint32', 'sint64', 'bool',
        'string', 'bytes', 'double', 'float',
    ];

    /**
     * @param  list<array{name: string, type: string, number: int, repeated: bool}>  $fields
     * @param  array<string, mixed>  $values
     */
    public function encodeBinary(array $fields, array $values): string
    {
        $bytes = '';

        foreach ($fields as $field) {
            if (!array_key_exists($field['name'], $values)) {
                continue;
            }

            foreach ($field['repeated'] ? (array) $values[$field['name']] : [$values[$field['name']]] as $single) {
                $bytes .= self::encodeScalar($field['type'], $field['number'], $single, $field['name']);
            }
        }

        return $bytes;
    }

    /**
     * @param  list<array{name: string, type: string, number: int, repeated: bool}>  $fields
     * @return array<string, mixed>
     */
    public function decodeBinary(array $fields, string $bytes): array
    {
        $byNumber = [];
        foreach ($fields as $field) {
            $byNumber[$field['number']] = $field;
        }

        $offset = 0;
        $length = strlen($bytes);
        $values = [];
        /** @var array<string, list<mixed>> $repeated */
        $repeated = [];

        while ($offset < $length) {
            $tag = self::readVarint($bytes, $offset);
            $number = $tag >> 3;
            $wire = $tag & 7;
            $field = $byNumber[$number] ?? null;

            $raw = match ($wire) {
                0 => self::readVarint($bytes, $offset),
                1 => self::unpackFloat('e', $bytes, $offset, 8),
                2 => self::readLengthDelimited($bytes, $offset),
                5 => self::unpackFloat('g', $bytes, $offset, 4),
                default => throw new RuntimeException("Unsupported protobuf wire type {$wire} while decoding an RPC response."),
            };

            if ($field === null || !in_array($field['type'], self::SCALAR_TYPES, true)) {
                continue;
            }

            $value = self::interpretScalar($field['type'], $raw);

            if ($field['repeated']) {
                $repeated[$field['name']][] = $value;
            } elseif (!array_key_exists($field['name'], $values)) {
                $values[$field['name']] = $value;
            }
        }

        return $values + $repeated;
    }

    /**
     * Prefixes a message with the 5-byte gRPC frame (flag 0 + big-endian length).
     */
    public function frame(string $message): string
    {
        return "\x00".pack('N', strlen($message)).$message;
    }

    /**
     * Returns the first gRPC frame's message payload, or null when the body is
     * too short or the frame is compressed (flag != 0). Trailing grpc-web
     * frames after the first message are ignored (unary reads one message).
     */
    public function unframe(string $framed): ?string
    {
        if (strlen($framed) < 5 || ord($framed[0]) !== 0) {
            return null;
        }

        $unpacked = unpack('N', substr($framed, 1, 4));
        if ($unpacked === false) {
            return null;
        }

        $length = $unpacked[1];
        if (!is_int($length)) {
            return null;
        }

        if (strlen($framed) < 5 + $length) {
            return null;
        }

        return substr($framed, 5, $length);
    }

    private function encodeScalar(string $type, int $number, mixed $value, string $name): string
    {
        if (!is_scalar($value)) {
            throw new RuntimeException("Non-scalar value for field '{$name}' cannot be wire-encoded.");
        }

        $key = self::writeVarint(($number << 3) | self::wireTypeOf($type));

        return match ($type) {
            'int32', 'int64', 'uint32', 'uint64', 'bool' => $key.self::writeVarint((int) $value),
            'sint32', 'sint64' => $key.self::writeVarint(self::zigzagEncode((int) $value)),
            'string', 'bytes' => $key.self::writeVarint(strlen((string) $value)).(string) $value,
            'double' => $key.pack('e', (float) $value),
            'float' => $key.pack('g', (float) $value),
            default => throw new RuntimeException("Unsupported protobuf field type '{$type}' for field '{$name}'."),
        };
    }

    private function wireTypeOf(string $type): int
    {
        return match ($type) {
            'int32', 'int64', 'uint32', 'uint64', 'sint32', 'sint64', 'bool' => 0,
            'string', 'bytes' => 2,
            'double' => 1,
            'float' => 5,
            default => throw new RuntimeException("Unsupported protobuf field type '{$type}'."),
        };
    }

    private function zigzagEncode(int $value): int
    {
        return ($value << 1) ^ ($value >> 63);
    }

    private function zigzagDecode(int $value): int
    {
        return ($value >> 1) ^ -($value & 1);
    }

    private function interpretScalar(string $type, int|float|string $raw): mixed
    {
        return match ($type) {
            'sint32', 'sint64' => self::zigzagDecode((int) $raw),
            'bool' => (bool) $raw,
            'string', 'bytes' => (string) $raw,
            'double', 'float' => (float) $raw,
            default => (int) $raw,
        };
    }

    private static function writeVarint(int $value): string
    {
        $bytes = '';

        if ($value < 0) {
            // Negative values are encoded as the 64-bit two's complement low
            // 10 bytes (protobuf int32/int64 grammar).
            for ($i = 0; $i < 9; $i++) {
                $bytes .= chr(($value & 0x7F) | 0x80);
                $value = self::unsignedShiftRight($value, 7);
            }

            return $bytes.chr($value & 0x7F);
        }

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $bytes .= chr($value === 0 ? $byte : $byte | 0x80);
        } while ($value !== 0);

        return $bytes;
    }

    /**
     * Unsigned 64-bit right shift of a two's-complement int.
     */
    private static function unsignedShiftRight(int $value, int $shift): int
    {
        if ($value >= 0) {
            return $value >> $shift;
        }

        return ($value >> $shift) & (PHP_INT_MAX >> ($shift - 1));
    }

    private static function readVarint(string $bytes, int &$offset): int
    {
        $result = 0;
        $shift = 0;

        while (true) {
            $byte = ord($bytes[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            if (($byte & 0x80) === 0) {
                return $result;
            }
            $shift += 7;
        }
    }

    /**
     * Reads a wire-type-2 length-delimited payload (string/bytes/binary message).
     */
    private static function readLengthDelimited(string $bytes, int &$offset): string
    {
        $length = self::readVarint($bytes, $offset);
        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * Reads a fixed-width little-endian float/double and advances past it.
     */
    private static function unpackFloat(string $format, string $bytes, int &$offset, int $width): float
    {
        $unpacked = unpack($format, substr($bytes, $offset, $width));
        $offset += $width;

        if ($unpacked === false) {
            return 0.0;
        }

        $value = $unpacked[1];

        return is_int($value) || is_float($value) ? (float) $value : 0.0;
    }
}
