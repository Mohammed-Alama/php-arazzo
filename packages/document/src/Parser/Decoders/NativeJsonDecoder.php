<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser\Decoders;

use Alama\Arazzo\Document\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Document\Parser\Interfaces\JsonDecoder;
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
