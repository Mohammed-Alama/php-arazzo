<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Loader;

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
