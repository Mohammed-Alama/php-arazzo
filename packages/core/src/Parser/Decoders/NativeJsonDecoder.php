<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser\Decoders;

use Alama\Arazzo\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Parser\Interfaces\JsonDecoder;
use JsonException;

final class NativeJsonDecoder implements JsonDecoder
{
    public function decode(string $source): mixed
    {
        try {
            return json_decode($source, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DecodeException($e->getMessage(), 0, $e);
        }
    }
}
